<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Actualizaciones desde el panel (0.4.0).
 *
 *  1. Comprobación: una vez al día (y al entrar el superadmin, como mucho cada 6 h) se mira la lista de versiones de
 *     github.com/AppNetDeveloper/universal-web. Tiempo de espera corto: sin red o con GitHub caído no pasa nada.
 *  2. Cada versión publica un paquete .zip y un «sobre» .update.json: {payload: base64(JSON), sig: base64(firma)}.
 *     El JSON lleva versión, nombre, sha256 y tamaño del zip y el resumen de cambios. La firma es ed25519 (libsodium)
 *     sobre los bytes EXACTOS del JSON, con la clave cuya parte pública está en UPDATE_PUBLIC_KEY. Sin firma válida,
 *     el paquete no se instala nunca (ni a mano ni en automático). Tampoco una versión igual o anterior (repetición).
 *  3. Actualizar (fase 1, este proceso, con el código viejo): copia completa (base de datos + config + subidas) y copia
 *     del código que se va a tocar → mantenimiento → descarga → verificación (firma, sha256, tamaño, contenido del zip,
 *     sintaxis PHP) → reemplazo del código archivo a archivo, sin tocar data/, public/media/ ni los temas personalizados.
 *  4. Fase 2 (app/update_finish.php, en un proceso nuevo con el código nuevo): migraciones, vaciado de la caché,
 *     prueba de salud y fin del mantenimiento. Si algo falla, vuelve sola a la versión anterior.
 *
 * Por MCP solo se consulta (update_status). Las instalaciones 0.3.0 no traen esto: su primera actualización es manual.
 */

require_once __DIR__ . '/update_rollback.php';
require_once __DIR__ . '/update_finish.php';

const UPDATE_REPO = 'AppNetDeveloper/universal-web';
/** Clave pública ed25519 de BionicAdri para firmar las versiones (la privada nunca está en el repositorio). */
const UPDATE_PUBLIC_KEY = 'UXe2pMHvK+5GYnPPUdhQYs22qP8X/2RNdghcsFFEe7g=';
const UPDATE_CHECK_EVERY = 86400;
const UPDATE_ADMIN_CHECK_EVERY = 6 * 3600;
const UPDATE_MAX_ZIP = 60 * 1024 * 1024;
const UPDATE_MAX_FILES = 5000;
/** Lo que puede traer un paquete en la raíz. Nunca data/, .git ni subidas. */
const UPDATE_ROOT_FILES = ['README.md', 'LICENSE', 'CHANGELOG.md', 'CONTRIBUTING.md', 'install.sh', '.htaccess', '.gitignore'];
const UPDATE_ROOT_DIRS = ['app', 'bin', 'deploy', 'docs', 'public', 'themes'];

function update_dir(): string
{
    $d = UW_DATA . '/updates';
    if (!is_dir($d)) { @mkdir($d, 0750, true); @file_put_contents("$d/.htaccess", "Require all denied\nDeny from all\n"); }
    return $d;
}

/** Política de actualización automática: off (por defecto) | patch (solo 0.4.x) | all. */
function update_policy(): string
{
    $p = (string) (platform()['auto_update'] ?? 'off');
    return in_array($p, ['off', 'patch', 'all'], true) ? $p : 'off';
}

function update_state(): array
{
    return json_dec((string) val("SELECT value FROM meta WHERE key = 'update_state'")) + ['checked_at' => 0, 'latest' => null, 'error' => ''];
}

function update_state_save(array $s): void
{
    q("INSERT OR REPLACE INTO meta (key, value) VALUES ('update_state', ?)", [json_enc($s)]);
}

/** ¿$new es una versión que esta política deja instalar sobre $cur? (patch = mismo x.y) */
function update_allowed(string $cur, string $new, string $policy): bool
{
    if (!preg_match('/^\d+\.\d+\.\d+$/', $new) || version_compare($new, $cur, '<=')) return false;
    if ($policy === 'patch') return implode('.', array_slice(explode('.', $new), 0, 2)) === implode('.', array_slice(explode('.', $cur), 0, 2));
    return true;
}

/** Descarga pequeña (lista de versiones, sobre firmado) con tiempo de espera corto. */
function update_fetch(string $url, int $max, int $timeout): string
{
    if (preg_match('#^file://(/.+)$#', $url, $m)) { // solo para pruebas y la CLI (--feed=file:///…)
        if (PHP_SAPI !== 'cli') throw new UwError('Origen no permitido');
        $raw = @file_get_contents($m[1], false, null, 0, $max + 1);
        if ($raw === false) throw new UwError("No se puede leer {$m[1]}");
        return $raw;
    }
    $tmp = http_fetch_public($url, $max, 3, $timeout);
    try { return (string) file_get_contents($tmp); } finally { @unlink($tmp); }
}

/**
 * Elige la versión más nueva que se puede instalar de la lista de GitHub (o de un JSON con el mismo formato):
 * [{tag_name, draft, prerelease, body, assets: [{name, browser_download_url}]}]. Devuelve null si no hay.
 */
function update_pick(array $releases, string $current, string $policy = 'all'): ?array
{
    $best = null;
    foreach ($releases as $r) {
        if (!is_array($r) || !empty($r['draft']) || !empty($r['prerelease'])) continue;
        $v = ltrim((string) ($r['tag_name'] ?? ''), 'v');
        if (!update_allowed($current, $v, $policy === 'patch' ? 'patch' : 'all')) continue;
        $assets = [];
        foreach ((array) ($r['assets'] ?? []) as $a) if (is_array($a)) $assets[(string) ($a['name'] ?? '')] = (string) ($a['browser_download_url'] ?? '');
        $env = $assets["universal-web-$v.update.json"] ?? '';
        $zip = $assets["universal-web-$v.zip"] ?? '';
        if ($env === '' || $zip === '') continue; // versión sin sobre firmado: no se puede instalar desde el panel
        if ($best === null || version_compare($v, $best['version'], '>')) {
            $best = ['version' => $v, 'envelope_url' => $env, 'zip_url' => $zip, 'notes' => mb_substr((string) ($r['body'] ?? ''), 0, 20000), 'url' => (string) ($r['html_url'] ?? '')];
        }
    }
    return $best;
}

/**
 * Comprueba si hay versión nueva (cachea el resultado). Nunca lanza: sin red, guarda el error y sigue.
 * $feed: para pruebas, otra lista en el mismo formato (file:///… solo desde la CLI).
 */
function update_check(bool $force = false, int $maxAge = UPDATE_CHECK_EVERY, ?string $feed = null): array
{
    $st = update_state();
    if (!$force && (int) $st['checked_at'] > time() - $maxAge) return $st;
    $st['checked_at'] = time();
    try {
        $raw = update_fetch($feed ?? 'https://api.github.com/repos/' . UPDATE_REPO . '/releases?per_page=30', 2 * 1024 * 1024, 5);
        $list = json_decode($raw, true);
        if (!is_array($list) || !array_is_list($list)) throw new UwError('Respuesta no válida de la lista de versiones');
        $st['latest'] = update_signed_notes(update_pick($list, UW_VERSION, 'all'), $feed !== null);
        $st['latest_patch'] = ($st['latest_patch'] = update_pick($list, UW_VERSION, 'patch')) && $st['latest'] && $st['latest_patch']['version'] === $st['latest']['version']
            ? $st['latest'] : update_signed_notes($st['latest_patch'], $feed !== null);
        $st['error'] = '';
    } catch (Throwable $e) {
        $st['error'] = mb_substr($e->getMessage(), 0, 300);
    }
    update_state_save($st);
    return $st;
}

/**
 * Lo que se enseña de una versión sale de su sobre FIRMADO (las notas de la release de GitHub no están firmadas: alguien
 * con acceso a la release podría escribir ahí instrucciones falsas). Si el sobre no está o su firma no vale, la versión no
 * se ofrece. $test: lista de pruebas sin sobres reales (se usan sus notas tal cual).
 */
function update_signed_notes(?array $latest, bool $test = false): ?array
{
    if ($latest === null || $test) return $latest;
    $m = update_verify_envelope(update_fetch((string) $latest['envelope_url'], 256 * 1024, 5));
    if ($m['version'] !== $latest['version']) throw new UwError('El sobre firmado no corresponde a la versión ' . $latest['version']);
    unset($latest['notes']);
    return $latest + ['changelog' => (string) ($m['changelog'] ?? ''), 'released_at' => (string) ($m['released_at'] ?? '')];
}

/** Resumen para el panel y el MCP (solo consulta). */
function update_status_public(): array
{
    $st = update_state();
    $latest = $st['latest'] && version_compare((string) $st['latest']['version'], UW_VERSION, '>') ? $st['latest'] : null;
    [$can, $why] = update_can_write();
    $last = json_dec((string) @file_get_contents(update_dir() . '/last-result.json'));
    return [
        'installed' => UW_VERSION, 'available' => $latest !== null, 'latest' => $latest['version'] ?? null,
        'changelog' => $latest ? update_changelog_for($latest) : '', 'checked_at' => $st['checked_at'] ? date('c', (int) $st['checked_at']) : null,
        'check_error' => $st['error'] ?: null, 'auto_update' => update_policy(), 'can_update_from_panel' => $can, 'reason' => $can ? null : $why,
        'last_update' => $last ? array_intersect_key($last, array_flip(['ok', 'from', 'to', 'at', 'message', 'auto'])) : null,
        'how' => 'Para actualizar: Panel → Actualizaciones → «Actualizar» (superadmin), o `php bin/uw update --yes` en el servidor. Por MCP no se puede lanzar.',
    ];
}

/** Resumen de cambios de una versión: el de la release o, si no, el que trae su sobre (cacheado). */
function update_changelog_for(array $latest): string
{
    return trim((string) ($latest['changelog'] ?? ''));
}

/** ¿Puede este proceso escribir el código? [bool, motivo legible]. */
function update_can_write(): array
{
    $user = function_exists('posix_geteuid') && function_exists('posix_getpwuid') ? ((posix_getpwuid(posix_geteuid()) ?: [])['name'] ?? '') : '';
    foreach (['app', 'app/core.php', 'public', 'public/index.php', 'themes', 'bin'] as $rel) {
        $f = UW_ROOT . '/' . $rel;
        if (file_exists($f) && !is_writable($f)) {
            return [false, "El servidor web" . ($user !== '' ? " (usuario $user)" : '') . " no puede escribir en el código ($rel): así lo deja install.sh por seguridad. Actualiza desde la terminal del servidor con: sudo uw update --yes"];
        }
    }
    if (!is_writable(UW_DATA)) return [false, 'No se puede escribir en la carpeta de datos'];
    if (!class_exists('ZipArchive')) return [false, 'Este PHP no tiene la extensión zip (ZipArchive)'];
    if (!function_exists('sodium_crypto_sign_verify_detached')) return [false, 'Este PHP no tiene libsodium para comprobar la firma'];
    return [true, ''];
}

/**
 * Comprueba el sobre firmado y devuelve el JSON de la versión. $publicKey solo lo pasan las pruebas (un par desechable):
 * en el resto de casos vale la clave del código. Lanza UwError con el motivo exacto.
 */
function update_verify_envelope(string $raw, ?string $publicKey = null): array
{
    if (!function_exists('sodium_crypto_sign_verify_detached')) throw new UwError('Este PHP no tiene libsodium: no se puede comprobar la firma');
    $env = json_decode($raw, true);
    if (!is_array($env) || !is_string($env['payload'] ?? null) || !is_string($env['sig'] ?? null)) throw new UwError('El archivo de la versión no tiene el formato esperado (payload y firma)');
    $payload = base64_decode($env['payload'], true);
    $sig = base64_decode($env['sig'], true);
    $pub = base64_decode($publicKey ?? UPDATE_PUBLIC_KEY, true);
    if ($payload === false || $sig === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || $pub === false || strlen($pub) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) throw new UwError('Firma o clave con formato no válido');
    if (!sodium_crypto_sign_verify_detached($sig, $payload, $pub)) throw new UwError('La firma del paquete NO es válida: no se instala. Puede estar dañado o no ser una versión oficial de BionicAdri.');
    $m = json_decode($payload, true);
    if (!is_array($m) || ($m['name'] ?? '') !== 'universal-web' || (int) ($m['format'] ?? 0) !== 1) throw new UwError('El paquete firmado no es de Universal Web');
    if (!preg_match('/^\d+\.\d+\.\d+$/', (string) ($m['version'] ?? '')) || !preg_match('/^[a-f0-9]{64}$/', (string) ($m['sha256'] ?? '')) || (int) ($m['size'] ?? 0) <= 0) throw new UwError('Datos de la versión incompletos');
    return $m;
}

/** Valida el zip (rutas, enlaces, tamaño, versión, sintaxis PHP) y lo extrae en $dest. Devuelve la lista de archivos. */
function update_extract(string $zipFile, array $m, string $dest): array
{
    $z = new ZipArchive();
    if ($z->open($zipFile) !== true) throw new UwError('El paquete no es un zip válido');
    $files = [];
    $total = 0;
    try {
        if ($z->numFiles > UPDATE_MAX_FILES) throw new UwError('El paquete trae demasiados archivos');
        for ($i = 0; $i < $z->numFiles; $i++) {
            $st = $z->statIndex($i);
            $name = (string) $st['name'];
            if (str_ends_with($name, '/')) continue;
            $top = explode('/', $name)[0];
            $okTop = in_array($top, UPDATE_ROOT_DIRS, true) ? str_contains($name, '/') : in_array($name, UPDATE_ROOT_FILES, true);
            if (!$okTop || !uw_update_safe_rel($name) || str_contains($name, '//')) throw new UwError("El paquete trae una ruta no permitida: $name");
            $z->getExternalAttributesIndex($i, $os, $attr);
            if ((($attr >> 16) & 0170000) === 0120000) throw new UwError("El paquete trae un enlace simbólico: $name");
            $total += (int) $st['size'];
            if ($total > 200 * 1024 * 1024) throw new UwError('El paquete descomprimido es demasiado grande');
            $files[$name] = (($attr >> 16) & 0111) ? 0755 : 0644;
        }
        if (!isset($files['app/core.php'], $files['public/index.php'], $files['app/bootstrap.php'])) throw new UwError('Al paquete le faltan archivos básicos (app/core.php, public/index.php)');
        if (!preg_match("/const UW_VERSION = '" . preg_quote((string) $m['version'], '/') . "';/", (string) $z->getFromName('app/core.php'))) throw new UwError('La versión del código no coincide con la del paquete firmado');
        if (!@mkdir($dest, 0750, true) && !is_dir($dest)) throw new UwError('No se puede crear la carpeta temporal de la actualización');
        foreach ($files as $name => $mode) {
            $data = $z->getFromName($name);
            if ($data === false) throw new UwError("No se puede leer $name del paquete");
            // Sintaxis PHP sin ejecutar nada: un archivo roto no llega a sustituir al bueno.
            if (str_ends_with($name, '.php') || $name === 'bin/uw') {
                try { token_get_all($data, TOKEN_PARSE); } catch (ParseError $e) { throw new UwError("El paquete trae un archivo PHP con errores: $name (línea {$e->getLine()})"); }
            }
            if (!is_dir(dirname("$dest/$name"))) mkdir(dirname("$dest/$name"), 0750, true);
            file_put_contents("$dest/$name", $data);
            chmod("$dest/$name", $mode);
        }
    } finally {
        $z->close();
    }
    return $files;
}

/** Lista de archivos (y su huella) de la versión instalada, o null si no se sabe (instalación por git o anterior a 0.4.0). */
function update_installed_files(): ?array
{
    $j = json_dec((string) @file_get_contents(UW_ROOT . '/app/release-files.json'), null);
    return is_array($j) && is_array($j['files'] ?? null) ? $j['files'] : null;
}

/** Temas de serie que se han modificado a mano (algún archivo no coincide con el de la versión instalada): no se tocan. */
function update_modified_themes(?array $old): array
{
    if ($old === null) return [];
    $out = [];
    foreach ($old as $rel => $sha) {
        if (!preg_match('#^themes/([a-z0-9_-]+)/#', (string) $rel, $m) || isset($out[$m[1]])) continue;
        $f = UW_ROOT . '/' . $rel;
        if (!is_file($f) || hash_file('sha256', $f) !== $sha) $out[$m[1]] = true;
    }
    return array_keys($out);
}

/**
 * Fase 1 de la actualización con un sobre ya descargado y su zip. $publicKey solo en pruebas.
 * Devuelve ['id', 'from', 'to', 'skipped_themes'] y deja pending.json para la fase 2. Si falla antes de cambiar el
 * código, no toca nada; si falla cambiándolo, vuelve atrás al momento.
 */
function update_install_package(string $envelopeRaw, string $zipFile, string $by, bool $auto = false, ?string $publicKey = null, ?string $policy = null): array
{
    $m = update_verify_envelope($envelopeRaw, $publicKey);
    if (!update_allowed(UW_VERSION, (string) $m['version'], $policy ?? 'all')) throw new UwError("La versión {$m['version']} no es más nueva que la instalada (" . UW_VERSION . '): no se instala');
    if (isset($m['min_php']) && version_compare(PHP_VERSION, (string) $m['min_php'], '<')) throw new UwError("La versión {$m['version']} necesita PHP {$m['min_php']} o superior (tienes " . PHP_VERSION . ')');
    if (!is_file($zipFile) || filesize($zipFile) !== (int) $m['size'] || !hash_equals((string) $m['sha256'], (string) hash_file('sha256', $zipFile))) {
        throw new UwError('El paquete descargado no coincide con su huella firmada (sha256): no se instala');
    }
    [$can, $why] = update_can_write();
    if (!$can) throw new UwError($why);
    $dir = update_dir();
    $lock = uw_update_lock_open("$dir/update.lock");
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new UwError('Ya hay una actualización en marcha');
    if (is_file("$dir/pending.json")) throw new UwError('Hay una actualización a medio terminar: recarga la web para que termine (o se deshaga) antes de lanzar otra');
    @set_time_limit(0);
    $id = date('Ymd-His') . '-' . preg_replace('/[^0-9.]/', '', (string) $m['version']);
    $work = "$dir/$id";
    $maint = false;
    $swapped = false;
    try {
        if (!@mkdir("$work/code", 0750, true)) throw new UwError('No se puede crear la carpeta de la actualización');
        $free = @disk_free_space(UW_DATA);
        $dbSize = (int) @filesize(UW_DATA . '/universal.sqlite');
        if ($free !== false && $free < 3 * (int) $m['size'] + 2 * $dbSize + 50 * 1024 * 1024) throw new UwError('No hay espacio libre suficiente en el disco para actualizar con copia de seguridad');
        // 1) Copia de seguridad completa (base de datos + config + subidas), con la web aún abierta
        $backup = backup_create(null, null, false);
        // 2) Mantenimiento y, ya sin escrituras de visitantes, la copia exacta de la base de datos para volver atrás
        uw_maintenance_on('update ' . UW_VERSION . ' → ' . $m['version'], 30);
        $maint = true;
        db()->exec('VACUUM INTO ' . db()->quote("$work/universal.sqlite"));
        // 3) Paquete verificado → carpeta temporal (rutas, enlaces, versión y sintaxis PHP comprobados)
        $new = update_extract($zipFile, $m, "$work/new");
        $old = update_installed_files();
        $skipThemes = update_modified_themes($old);
        $inSkipped = fn(string $rel) => (bool) preg_match('#^themes/(' . implode('|', array_map(fn($t) => preg_quote($t, '#'), $skipThemes ?: ['\x00'])) . ')/#', $rel);
        $toWrite = array_values(array_filter(array_keys($new), fn($r) => !$inSkipped($r)));
        $toDelete = $old === null ? [] : array_values(array_filter(array_diff(array_keys($old), array_keys($new)), fn($r) => uw_update_safe_rel((string) $r) && !$inSkipped((string) $r)));
        // 4) Copia del código que se va a tocar (para volver atrás) y lista de lo que es nuevo
        $added = [];
        foreach (array_merge($toWrite, $toDelete) as $rel) {
            $f = UW_ROOT . '/' . $rel;
            if (is_file($f)) { if (!uw_update_put($f, "$work/code/$rel")) throw new UwError("No se puede guardar la copia de $rel"); }
            elseif (in_array($rel, $toWrite, true)) $added[] = $rel;
        }
        file_put_contents("$work/added.txt", implode("\n", $added) . "\n");
        // La vuelta atrás del código VIEJO, que sabemos que funciona, con nombres propios (no chocan con los del nuevo)
        file_put_contents("$work/rollback.php", str_replace('uw_update_', 'uw_update_prev_', (string) file_get_contents(__DIR__ . '/update_rollback.php')));
        file_put_contents("$work/info.json", json_enc(['from' => UW_VERSION, 'to' => $m['version'], 'by' => $by, 'auto' => $auto, 'backup' => $backup['name'] ?? '', 'skipped_themes' => $skipThemes, 'changelog' => $m['changelog'] ?? ''], true));
        // 5) Reemplazo del código, archivo a archivo (cada uno de forma atómica). Si este proceso muere a mitad, la
        //    siguiente petición ve «swapping» sin nadie con el cerrojo y deshace el cambio (app/update_finish.php).
        file_put_contents("$dir/pending.json", json_enc(['id' => $id, 'from' => UW_VERSION, 'to' => $m['version'], 'stage' => 'swapping', 'by' => $by, 'auto' => $auto, 'at' => date('c'), 'uid' => uw_update_uid()]));
        $swapped = true;
        foreach ($toWrite as $rel) {
            if (!uw_update_put("$work/new/$rel", UW_ROOT . '/' . $rel)) throw new UwError("No se pudo escribir $rel");
        }
        foreach ($toDelete as $rel) @unlink(UW_ROOT . '/' . $rel);
        if (function_exists('opcache_reset')) @opcache_reset();
        file_put_contents("$dir/pending.json", json_enc(['id' => $id, 'from' => UW_VERSION, 'to' => $m['version'], 'stage' => 'swapped', 'by' => $by, 'auto' => $auto, 'at' => date('c'), 'uid' => uw_update_uid()]));
        audit(null, 'update.swap', UW_VERSION . ' → ' . $m['version'], ['by' => $by, 'auto' => $auto]);
        return ['id' => $id, 'from' => UW_VERSION, 'to' => $m['version'], 'skipped_themes' => $skipThemes, 'backup' => $backup['name'] ?? ''];
    } catch (Throwable $e) {
        if ($swapped) uw_update_rollback($work, UW_ROOT, UW_DATA, false);
        @unlink("$dir/pending.json");
        $failedWork = $work;
        if ($maint) uw_maintenance_off();
        @file_put_contents("$dir/last-result.json", json_enc(['ok' => false, 'from' => UW_VERSION, 'to' => $m['version'], 'auto' => $auto, 'rolled_back' => $swapped,
            'error' => $e->getMessage(), 'at' => date('c'), 'notified' => false,
            'message' => $swapped ? "La actualización a la {$m['version']} ha fallado al cambiar los archivos y se ha vuelto a la " . UW_VERSION . ". Motivo: " . $e->getMessage()
                : "No se ha actualizado (la web sigue en la " . UW_VERSION . "): " . $e->getMessage()], true));
        throw $e instanceof UwError ? $e : new UwError('Error al actualizar: ' . $e->getMessage());
    } finally {
        bk_rmtree(isset($failedWork) ? $failedWork : "$work/new"); // de un intento fallido no queda nada (ni su copia de la BD)
        flock($lock, LOCK_UN);
        update_fix_owner();
    }
}

/** Descarga la última versión permitida y la instala (fase 1). */
function update_run_remote(string $by, bool $auto = false, ?string $policy = null): array
{
    $st = update_check(true, 0);
    $latest = ($policy === 'patch' ? ($st['latest_patch'] ?? null) : $st['latest']);
    if (!$latest) throw new UwError($st['error'] !== '' ? 'No se ha podido comprobar si hay versión nueva: ' . $st['error'] : 'Ya tienes la última versión (' . UW_VERSION . ')');
    $env = update_fetch((string) $latest['envelope_url'], 256 * 1024, 15);
    $m = update_verify_envelope($env); // antes de bajar 10 MB, que la versión esté firmada
    $zip = http_fetch_public((string) $latest['zip_url'], UPDATE_MAX_ZIP, 3, 300);
    try {
        return update_install_package($env, $zip, $by, $auto, null, $policy);
    } finally {
        @unlink($zip);
    }
}

/** Fase 2 (con el código nuevo ya cargado): comprobaciones, caché y fin del mantenimiento. Lanza si algo no cuadra. */
function update_finish_after_boot(array $p): void
{
    if (UW_VERSION !== $p['to']) throw new UwError('El código cargado es la ' . UW_VERSION . ', no la ' . $p['to']);
    if ((int) val("SELECT value FROM meta WHERE key = 'schema_version'") < UW_SCHEMA) throw new UwError('La base de datos no se ha actualizado');
    page_cache_clear(null);
    // Prueba de salud: la portada de cada web se genera sin errores.
    foreach (sites_all() as $site) {
        $svc = new SiteService($site);
        $r = publish_render($svc, '/');
        if (!in_array($r['status'], [200, 301, 401, 404], true)) throw new UwError("La portada de «{$site['name']}» responde HTTP {$r['status']}");
    }
    $dir = update_dir();
    $info = json_dec((string) @file_get_contents("$dir/{$p['id']}/info.json"));
    @unlink("$dir/pending.json");
    uw_maintenance_off();
    file_put_contents("$dir/last.json", json_enc(['id' => $p['id'], 'from' => $p['from'], 'to' => $p['to'], 'at' => date('c')]));
    uw_update_write_result(['ok' => true, 'from' => $p['from'], 'to' => $p['to'], 'auto' => !empty($p['auto']), 'skipped_themes' => $info['skipped_themes'] ?? [],
        'message' => "Universal Web se ha actualizado de la {$p['from']} a la {$p['to']}." . (!empty($info['skipped_themes']) ? ' No se han tocado estos temas porque los habías modificado: ' . implode(', ', $info['skipped_themes']) . '.' : '')]);
    Actor::set('system', null, 'actualización');
    audit(null, 'update.done', $p['from'] . ' → ' . $p['to'], ['auto' => !empty($p['auto'])]);
    $st = update_state();
    $st['checked_at'] = 0; // que la próxima consulta vuelva a mirar
    update_state_save($st);
    update_prune($p['id']);
    update_fix_owner();
}

/** Borra carpetas de actualizaciones antiguas (se conserva la última, para «Volver a la versión anterior»). */
function update_prune(string $keep): void
{
    foreach (glob(update_dir() . '/*', GLOB_ONLYDIR) ?: [] as $d) if (basename($d) !== $keep) bk_rmtree($d);
}

/** Desde la CLI como root, lo creado en data/ pasa al dueño de data/ (el servidor web) para que pueda seguir usándolo. */
function update_fix_owner(): void
{
    uw_update_chown_data();
}

/**
 * Volver a la versión anterior desde la copia de la última actualización. Por defecto solo el código (los datos de
 * después se conservan; las migraciones solo añaden columnas). Con $withDb también la base de datos de antes de
 * actualizar (se pierde lo hecho desde entonces).
 */
function update_manual_rollback(bool $withDb, string $by): array
{
    $dir = update_dir();
    $last = json_dec((string) @file_get_contents("$dir/last.json"));
    $id = (string) ($last['id'] ?? '');
    if ($id === '' || !preg_match('/^[0-9a-z.-]+$/', $id) || !is_dir("$dir/$id/code")) throw new UwError('No hay ninguna actualización anterior guardada a la que volver');
    if (($last['to'] ?? '') !== UW_VERSION) throw new UwError("La última copia es de la actualización a la {$last['to']}, pero ahora tienes la " . UW_VERSION);
    [$can, $why] = update_can_write();
    if (!$can) throw new UwError($why);
    $lock = uw_update_lock_open("$dir/update.lock");
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new UwError('Ya hay una actualización en marcha');
    try {
        backup_create(null, null, false); // copia del estado actual antes de volver (más aún si se repone la base de datos)
        uw_maintenance_on('rollback', 10);
        $res = uw_update_rollback("$dir/$id", UW_ROOT, UW_DATA, $withDb);
        page_cache_clear(null);
        uw_maintenance_off();
        @unlink("$dir/last.json");
        $msg = "Se ha vuelto a la {$last['from']}" . ($withDb ? ', con la base de datos de antes de actualizar' : ' (los datos se conservan)') . '.';
        @file_put_contents("$dir/last-result.json", json_enc(['ok' => true, 'from' => $last['to'], 'to' => $last['from'], 'rollback_manual' => true, 'at' => date('c'), 'notified' => true, 'message' => $msg, 'rollback' => $res], true));
        if (!$withDb) audit(null, 'update.rollback', $last['to'] . ' → ' . $last['from'], ['by' => $by]);
        return ['message' => $msg] + $res;
    } finally {
        flock($lock, LOCK_UN);
        update_fix_owner();
    }
}

/**
 * Tarea diaria «al paso» (tras responder una visita): comprueba si hay versión nueva, avisa por correo del resultado
 * de la última actualización y, si el superadmin lo ha activado, actualiza sola.
 */
function update_daily_tick(): void
{
    if (!is_installed() || Out::$capture) return;
    $dir = update_dir();
    $res = json_dec((string) @file_get_contents("$dir/last-result.json"));
    if ($res && empty($res['notified'])) {
        $res['notified'] = true;
        @file_put_contents("$dir/last-result.json", json_enc($res, true));
        update_notify((string) ($res['message'] ?? ''), !empty($res['ok']));
    }
    $st = update_state();
    if ((int) $st['checked_at'] > time() - UPDATE_CHECK_EVERY) return;
    if (!throttle('update-check', 1, 3600)) return;
    if (PHP_SAPI !== 'cli' && function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    $st = update_check(true);
    $policy = update_policy();
    $target = $policy === 'patch' ? ($st['latest_patch'] ?? null) : ($policy === 'all' ? $st['latest'] : null);
    if (!$target) return;
    // Una versión que ya falló (y se deshizo) no se reintenta sola cada día: se avisó por correo y queda para hacerla a mano.
    if ($res && empty($res['ok']) && ($res['to'] ?? '') === $target['version']) return;
    // En automático solo si no hace esperar a nadie (PHP-FPM o CLI) y el código se puede escribir.
    if (PHP_SAPI !== 'cli' && !function_exists('fastcgi_finish_request')) return;
    if (!update_can_write()[0]) return;
    Actor::set('system', null, 'actualización automática');
    try { update_run_remote('automático', true, $policy); } catch (Throwable $e) { error_log('[universal-web update] ' . $e->getMessage()); }
}

/** Aviso por correo a los superadmins (si el correo del panel está configurado). */
function update_notify(string $msg, bool $ok): void
{
    if ($msg === '' || !function_exists('panel_mail_ready') || !panel_mail_ready()) return;
    foreach (rows('SELECT email FROM users WHERE is_superadmin = 1') as $u) {
        try { panel_mail_send((string) $u['email'], ($ok ? '' : '[!] ') . 'Universal Web: ' . ($ok ? 'actualización completada' : 'la actualización ha fallado'), $msg . "\n\n" . panel_url() . "/update\n"); }
        catch (Throwable $e) { error_log('[universal-web update] correo: ' . $e->getMessage()); }
    }
}

// ---------------------------------------------------------------- panel (superadmin)

function adm_update(array $c): void
{
    $st = update_state();
    adm_render('update', $c, ['title' => 'Actualizaciones', 'status' => update_status_public(), 'state' => $st,
        'result' => json_dec((string) @file_get_contents(update_dir() . '/last-result.json')), 'last' => json_dec((string) @file_get_contents(update_dir() . '/last.json'))]);
}

function adm_update_check(array $c): void
{
    $st = update_check(true, 0);
    flash($st['error'] !== '' ? t('No se ha podido comprobar: {e}', ['e' => t_msg($st['error'])]) : ($st['latest'] ? t('Hay una versión nueva: {v}', ['v' => $st['latest']['version']]) : t('Tienes la última versión.')), $st['error'] !== '' ? 'error' : 'ok');
    redirect(ADM . '/update');
}

function adm_update_run(array $c): void
{
    if (($_POST['confirm'] ?? '') !== '1') { flash(t('Marca la casilla de confirmación para actualizar.'), 'error'); redirect(ADM . '/update'); }
    try {
        $r = update_run_remote(Actor::$name);
        // La segunda mitad la hace la siguiente petición, ya con el código nuevo (public/index.php)
        redirect(ADM . '/update?fin=' . rawurlencode($r['to']));
    } catch (UwError $e) {
        flash(t_msg($e->getMessage()), 'error');
        redirect(ADM . '/update');
    }
}

function adm_update_settings(array $c): void
{
    $p = (string) ($_POST['auto_update'] ?? 'off');
    platform_save(['auto_update' => in_array($p, ['off', 'patch', 'all'], true) ? $p : 'off']);
    audit(null, 'update.settings', $p);
    flash(t('Ajuste de actualizaciones guardado.'));
    redirect(ADM . '/update');
}

function adm_update_rollback(array $c): void
{
    if (($_POST['confirm'] ?? '') !== '1') { flash(t('Marca la casilla de confirmación.'), 'error'); redirect(ADM . '/update'); }
    try {
        $r = update_manual_rollback(!empty($_POST['with_db']), Actor::$name);
        flash(t_msg($r['message']) . ($r['errors'] ? ' · ' . implode(' · ', $r['errors']) : ''), $r['errors'] ? 'error' : 'ok');
    } catch (UwError $e) {
        flash(t_msg($e->getMessage()), 'error');
    }
    redirect(ADM . '/update');
}
