<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Segunda mitad de una actualización, en un proceso NUEVO (el que cambió los archivos sigue con el código viejo en
 * memoria). La lanza la primera petición que llega con data/updates/pending.json en fase «swapped» (public/index.php)
 * o `php bin/uw update:finish`. Con el código nuevo: migraciones (al cargar la aplicación), vaciado de caché y prueba
 * de salud. Si algo falla —una excepción, un error fatal o una migración que no termina—, la función de cierre vuelve
 * sola a la versión anterior con el rollback.php que se guardó del código viejo, y lo deja escrito en last-result.json.
 *
 * Autónomo hasta cargar bootstrap.php: no puede depender de nada de la aplicación.
 */

function uw_update_data_dir(): string
{
    return getenv('UW_DATA') ?: dirname(__DIR__) . '/data';
}

/** Cerrojo de un archivo de data/updates. Si ya existe se abre en lectura (flock vale igual y no hace falta poder escribirlo). */
function uw_update_lock_open(string $file)
{
    return is_file($file) ? @fopen($file, 'r') : @fopen($file, 'c');
}

/** Usuario que ejecuta este proceso (o -1 si no se sabe). */
function uw_update_uid(): int
{
    return function_exists('posix_geteuid') ? posix_geteuid() : -1;
}

/**
 * Desde la terminal como root, todo lo de data/ vuelve al dueño de data/ (el servidor web): si no, la base de datos
 * repuesta, los cerrojos y el resultado quedarían de root y la web no podría escribirlos. Autónomo (sin la aplicación).
 */
function uw_update_chown_data(): void
{
    if (PHP_SAPI !== 'cli' || uw_update_uid() !== 0) return;
    $data = uw_update_data_dir();
    $owner = @fileowner($data);
    $group = @filegroup($data);
    if (!$owner) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($data, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $f) { if (!$f->isLink()) { @chown($f->getPathname(), $owner); @chgrp($f->getPathname(), $group); } }
}

/** Borra una carpeta entera (autónomo). */
function uw_update_rmtree(string $dir): void
{
    if (!is_dir($dir) || is_link($dir)) { @unlink($dir); return; }
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) { $f->isDir() && !$f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
    @rmdir($dir);
}

/** Escribe el resultado de la última actualización (lo lee el panel aunque la base de datos haya vuelto atrás). */
function uw_update_write_result(array $r): void
{
    @file_put_contents(uw_update_data_dir() . '/updates/last-result.json', json_encode($r + ['at' => date('c'), 'notified' => false], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . "\n", LOCK_EX);
}

/**
 * Termina una actualización pendiente si la hay. Devuelve null si no había nada que hacer, 'ok' si terminó bien (la
 * aplicación ya está cargada) o 'rolled_back' si no terminó (en ese caso la petición acaba en la función de cierre).
 */
function uw_update_finish_entry(): ?string
{
    $data = uw_update_data_dir();
    $pf = "$data/updates/pending.json";
    if (!is_file($pf)) return null;
    $p = json_decode((string) @file_get_contents($pf), true);
    // Una actualización lanzada desde la terminal (como root) solo la termina la terminal: un visitante (www-data) no
    // podría reponer el código si fallara. Mientras, la web sigue en mantenimiento (uw_maintenance_gate).
    if (is_array($p) && isset($p['uid']) && (int) $p['uid'] !== uw_update_uid()) return null;
    $lock = uw_update_lock_open("$data/updates/finish.lock");
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return null; // otra petición la está terminando: esta verá el mantenimiento
    $p = json_decode((string) @file_get_contents($pf), true);
    if (!is_array($p) || !preg_match('/^[0-9a-z.-]+$/', (string) ($p['id'] ?? ''))) { flock($lock, LOCK_UN); return null; }
    if (($p['stage'] ?? '') === 'swapping') {
        // El proceso que cambiaba los archivos murió a mitad (lo mataron, se quedó sin memoria…): si ya no tiene el
        // cerrojo, se deshace lo que hubiera cambiado. La base de datos aún no se había tocado.
        $ul = uw_update_lock_open("$data/updates/update.lock");
        if ($ul && flock($ul, LOCK_EX | LOCK_NB)) {
            $GLOBALS['uw_update_pending'] = $p;
            $GLOBALS['uw_update_error'] = 'la actualización se interrumpió mientras cambiaba los archivos';
            $GLOBALS['uw_update_no_db'] = true;
            uw_update_finish_shutdown();
            flock($ul, LOCK_UN);
            flock($lock, LOCK_UN);
            if (PHP_SAPI !== 'cli') exit;
        }
        flock($lock, LOCK_UN);
        return 'rolled_back';
    }
    if (($p['stage'] ?? '') !== 'swapped') { flock($lock, LOCK_UN); return null; }
    $GLOBALS['uw_update_pending'] = $p;
    $GLOBALS['uw_update_lock'] = $lock;
    register_shutdown_function('uw_update_finish_shutdown');
    try {
        require_once dirname(__DIR__) . '/app/bootstrap.php'; // ensure_schema(): aplica las migraciones de la versión nueva
        update_finish_after_boot($p); // app/update.php: comprobaciones; lanza excepción si algo no cuadra
    } catch (Throwable $e) {
        $GLOBALS['uw_update_error'] = $e->getMessage() . ' (' . str_replace(dirname(__DIR__) . '/', '', $e->getFile()) . ':' . $e->getLine() . ')';
        exit(1); // la función de cierre vuelve a la versión anterior
    }
    $GLOBALS['uw_update_ok'] = true;
    @flock($lock, LOCK_UN);
    return 'ok';
}

/** Si la actualización no terminó bien, vuelve a la versión anterior (código y base de datos) y lo explica. */
function uw_update_finish_shutdown(): void
{
    $p = $GLOBALS['uw_update_pending'] ?? null;
    if (!$p || !empty($GLOBALS['uw_update_ok'])) return;
    $data = uw_update_data_dir();
    $dir = "$data/updates/{$p['id']}";
    $err = error_get_last();
    $why = (string) ($GLOBALS['uw_update_error'] ?? '');
    if ($why === '' && $err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
        $why = $err['message'] . ' (' . str_replace(dirname(__DIR__) . '/', '', (string) $err['file']) . ':' . $err['line'] . ')';
    }
    if ($why === '') $why = 'la versión nueva no terminó de arrancar';
    // La vuelta atrás del código VIEJO (copiada al empezar con sus funciones renombradas a uw_update_prev_*, para que no
    // choquen con las del código nuevo ya cargado). Si no está, la del código actual.
    if (is_file("$dir/rollback.php")) { require_once "$dir/rollback.php"; }
    $fn = function_exists('uw_update_prev_rollback') ? 'uw_update_prev_rollback' : null;
    if ($fn === null) { require_once dirname(__DIR__) . '/app/update_rollback.php'; $fn = 'uw_update_rollback'; }
    $res = $fn($dir, dirname(__DIR__), $data, empty($GLOBALS['uw_update_no_db']));
    @unlink("$data/updates/pending.json");
    @unlink("$data/maintenance.json");
    foreach (glob("$data/cache/pages/*/*.html") ?: [] as $f) @unlink($f);
    uw_update_rmtree($dir); // la copia de un intento fallido ya no sirve (la de la última actualización buena se conserva)
    uw_update_write_result(['ok' => false, 'from' => $p['from'], 'to' => $p['to'], 'auto' => !empty($p['auto']), 'rolled_back' => true,
        'error' => mb_substr(preg_replace('/\s+/', ' ', $why), 0, 600), 'rollback' => $res,
        'message' => "La actualización a la {$p['to']} ha fallado y la web ha vuelto sola a la {$p['from']}, con sus datos tal cual estaban. Motivo: " . mb_substr($why, 0, 300)]);
    if (!empty($GLOBALS['uw_update_lock'])) @flock($GLOBALS['uw_update_lock'], LOCK_UN);
    uw_update_chown_data();
    if (PHP_SAPI === 'cli') { fwrite(STDERR, "La actualización falló y se ha vuelto a la {$p['from']}. Motivo: $why\n"); return; }
    if (!headers_sent()) {
        while (ob_get_level()) ob_end_clean();
        http_response_code(503);
        header('Retry-After: 5');
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=utf-8');
        $admin = str_starts_with((string) ($_SERVER['REQUEST_URI'] ?? ''), '/admin');
        echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>Actualización</title>'
            . '<body style="font:16px/1.6 system-ui;max-width:34rem;margin:15vh auto;padding:0 1rem">'
            . ($admin ? '<h1>La actualización ha fallado</h1><p>La web ha vuelto sola a la versión anterior. <a href="/admin/update">Ver qué ha pasado</a></p>'
                : '<h1>Un momento</h1><p>Vuelve a cargar la página.</p><script>setTimeout(function(){location.reload()},3000)</script>');
    }
}
