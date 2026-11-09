<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Instalador de UN fichero para hosting compartido (cPanel, Plesk, FTP).
 *
 * Uso: sube SOLO este fichero a la carpeta raíz de tu dominio (public_html) y abre https://tu-dominio/instalar.php
 * Descarga el paquete oficial, comprueba su huella SHA-256, lo descomprime aquí y te lleva al asistente /install.
 * Al terminar se borra solo.
 *
 * Quién es el dueño (la debilidad típica de estos instaladores: el primero que lo abre, manda):
 * el instalador genera un código y pide crear en esta carpeta un fichero vacío llamado «uw-<código>.txt». Se eligió esto
 * (frente a renombrar el instalador, leer un fichero o poner una contraseña) porque «Nuevo archivo» existe en TODOS los
 * administradores de ficheros y clientes FTP, no exige abrir ni editar nada, y solo puede hacerlo quien tiene acceso de
 * escritura al hosting, que es exactamente lo que queremos demostrar. Al comprobarlo se entrega una cookie secreta
 * (solo su hash queda guardado); sin ella no se descarga ni se descomprime nada. Después, la llave data/.install-key
 * hace que /install solo lo pueda usar quien venga de aquí (ver install_key_gate() en app/install.php).
 *
 * No duplica la lógica de instalación: solo coloca los ficheros. El administrador lo crea /install (uw_install()).
 */

// ---------------------------------------------------------------- configuración

/** Paquete oficial. Junto a él debe existir la misma URL + «.sha256» con la huella. */
const UWI_PACKAGE_URL = 'https://github.com/AppNetDeveloper/universal-web/releases/latest/download/universal-web.zip';
const UWI_MIN_PHP = '8.1.0';
const UWI_MAX_BYTES = 100 * 1024 * 1024;
const UWI_STATE_PREFIX = '.uw-instalador-';
const UWI_COOKIE = 'uwi_owner';

// ---------------------------------------------------------------- utilidades

function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function uwi_dir(): string
{
    return __DIR__;
}

/** Carpeta de estado: nombre aleatorio e impredecible, con .htaccess y index.html por si el servidor la sirviera. */
function uwi_state_dir(bool $create): ?string
{
    $found = glob(uwi_dir() . '/' . UWI_STATE_PREFIX . '*', GLOB_ONLYDIR) ?: [];
    if ($found) return $found[0];
    if (!$create) return null;
    $dir = uwi_dir() . '/' . UWI_STATE_PREFIX . bin2hex(random_bytes(8));
    if (!@mkdir($dir, 0700)) return null;
    @file_put_contents($dir . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($dir . '/index.html', '');
    return $dir;
}

function uwi_state(): array
{
    $dir = uwi_state_dir(false);
    $raw = $dir && is_file("$dir/estado.json") ? @file_get_contents("$dir/estado.json") : false;
    $d = $raw ? json_decode($raw, true) : null;
    return is_array($d) ? $d : [];
}

function uwi_state_save(array $state): bool
{
    $dir = uwi_state_dir(true);
    return $dir !== null && @file_put_contents("$dir/estado.json", json_encode($state), LOCK_EX) !== false;
}

function uwi_rrmdir(string $dir): void
{
    if (!is_dir($dir)) return;
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') continue;
        $p = "$dir/$f";
        is_dir($p) && !is_link($p) ? uwi_rrmdir($p) : @unlink($p);
    }
    @rmdir($dir);
}

function uwi_is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
}

/** Misma web: si el navegador manda Origin/Referer, tiene que ser nuestro host. */
function uwi_same_origin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        $v = $_SERVER[$h] ?? '';
        if ($v === '' || $v === 'null') continue;
        $p = parse_url($v);
        return strtolower((string) ($p['host'] ?? '')) . (isset($p['port']) ? ':' . $p['port'] : '') === $host;
    }
    return true;
}

// ---------------------------------------------------------------- requisitos

/** @return list<array{0:string,1:bool,2:string}> [qué, ok, qué hacer si falta, en lenguaje llano] */
function uwi_requirements(): array
{
    $skip = getenv('UW_INSTALL_SKIP_REQS') === '1' && getenv('UW_INSTALL_ZIP') !== false; // solo para pruebas locales
    $r = [];
    $r[] = ['PHP ' . UWI_MIN_PHP . ' o superior', version_compare(PHP_VERSION, UWI_MIN_PHP, '>='),
        'Tu hosting usa PHP ' . PHP_VERSION . '. En el panel de tu hosting busca «Selector de versión de PHP» (cPanel) o «Configuración de PHP» (Plesk) y elige 8.1 o superior.'];
    foreach (['pdo_sqlite' => 'la base de datos (SQLite)', 'mbstring' => 'el texto con acentos', 'json' => 'el formato de datos'] as $ext => $why) {
        $r[] = ["Extensión $ext ($why)", $skip && $ext === 'pdo_sqlite' ? true : extension_loaded($ext),
            "Actívala en el mismo «Selector de versión de PHP» marcando la casilla «$ext». Si no la ves, pídesela al soporte de tu hosting."];
    }
    $r[] = ['Poder descomprimir el paquete (zip o phar)', class_exists('ZipArchive') || class_exists('PharData'),
        'Activa la extensión «zip» en el selector de versión de PHP de tu hosting.'];
    $r[] = ['Poder descargar el paquete (curl o allow_url_fopen)', function_exists('curl_init') || (bool) ini_get('allow_url_fopen'),
        'Activa la extensión «curl» (o «allow_url_fopen») en el selector de versión de PHP de tu hosting.'];
    $w = is_writable(uwi_dir());
    $r[] = ['Poder escribir en esta carpeta', $w, 'Da permisos de escritura (755 o 775) a la carpeta ' . basename(uwi_dir()) . ' desde tu administrador de archivos.'];
    return $r;
}

// ---------------------------------------------------------------- descarga y verificación

function uwi_http_get(string $url, int $max): string
{
    if (!str_starts_with($url, 'https://')) throw new RuntimeException('La dirección de descarga debe empezar por https://');
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        $buf = '';
        curl_setopt_array($ch, [
            CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5, CURLOPT_TIMEOUT => 240, CURLOPT_CONNECTTIMEOUT => 20,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_USERAGENT => 'UniversalWeb-Installer',
            CURLOPT_WRITEFUNCTION => function ($c, $chunk) use (&$buf, $max) {
                $buf .= $chunk;
                return strlen($buf) > $max ? -1 : strlen($chunk);
            },
        ]);
        $ok = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($ok === false) throw new RuntimeException('No se pudo descargar (' . ($err ?: 'error de red') . ').');
        if ($code !== 200) throw new RuntimeException("El servidor de descarga respondió $code.");
        return $buf;
    }
    $ctx = stream_context_create(['http' => ['timeout' => 240, 'follow_location' => 1, 'max_redirects' => 5, 'user_agent' => 'UniversalWeb-Installer']]);
    $data = @file_get_contents($url, false, $ctx, 0, $max + 1);
    if ($data === false || $data === '') throw new RuntimeException('No se pudo descargar el paquete.');
    if (strlen($data) > $max) throw new RuntimeException('El paquete es más grande de lo esperado.');
    return $data;
}

/** Devuelve [contenido del zip, huella esperada]; en pruebas puede leer un zip local (UW_INSTALL_ZIP + UW_INSTALL_SHA256). */
function uwi_fetch_package(): array
{
    $local = getenv('UW_INSTALL_ZIP');
    if ($local !== false && $local !== '') {
        $zip = @file_get_contents($local);
        if ($zip === false) throw new RuntimeException('No se pudo leer el zip local de pruebas.');
        return [$zip, strtolower(trim((string) getenv('UW_INSTALL_SHA256')))];
    }
    $zip = uwi_http_get(UWI_PACKAGE_URL, UWI_MAX_BYTES);
    $sumTxt = uwi_http_get(UWI_PACKAGE_URL . '.sha256', 4096);
    return [$zip, strtolower(preg_match('/\b([0-9a-fA-F]{64})\b/', $sumTxt, $m) ? $m[1] : '')];
}

function uwi_safe_name(string $n): ?string
{
    if ($n === '' || str_contains($n, "\0") || str_contains($n, '\\') || str_starts_with($n, '/') || preg_match('~^[A-Za-z]:~', $n)) return null;
    foreach (explode('/', $n) as $part) if ($part === '..') return null;
    return $n;
}

/** Descomprime en $dest sin tocar data/ ni este instalador. Devuelve cuántos ficheros colocó. */
function uwi_extract(string $zipFile, string $dest): int
{
    $self = basename(__FILE__);
    $n = 0;
    if (class_exists('ZipArchive')) {
        $z = new ZipArchive();
        if ($z->open($zipFile) !== true) throw new RuntimeException('El paquete descargado no es un zip válido.');
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = (string) $z->getNameIndex($i);
            if (uwi_safe_name($name) === null) { $z->close(); throw new RuntimeException('El paquete contiene una ruta no permitida: se detiene por seguridad.'); }
            if ($name === $self || $name === 'data' || str_starts_with($name, 'data/')) continue;
            $target = "$dest/$name";
            if (str_ends_with($name, '/')) { if (!is_dir($target) && !@mkdir($target, 0755, true)) throw new RuntimeException("No se pudo crear $name"); continue; }
            if (!is_dir(dirname($target)) && !@mkdir(dirname($target), 0755, true)) throw new RuntimeException('No se pudo crear la carpeta de ' . $name);
            $in = $z->getStream($name);
            $out = $in ? @fopen($target, 'wb') : false;
            if (!$in || !$out) { $z->close(); throw new RuntimeException("No se pudo escribir $name"); }
            stream_copy_to_stream($in, $out);
            fclose($in); fclose($out);
            @chmod($target, 0644);
            $n++;
        }
        $z->close();
        return $n;
    }
    // Sin ZipArchive: PharData (extensión phar). Se validan antes todas las rutas.
    $ph = new PharData($zipFile);
    $names = [];
    foreach (new RecursiveIteratorIterator($ph) as $f) {
        $rel = ltrim(str_replace('\\', '/', substr((string) $f, strlen('phar://' . $zipFile))), '/');
        if (uwi_safe_name($rel) === null) throw new RuntimeException('El paquete contiene una ruta no permitida: se detiene por seguridad.');
        if ($rel === $self || str_starts_with($rel, 'data/')) continue;
        $names[] = $rel;
    }
    if ($names) $ph->extractTo($dest, $names, true);
    return count($names);
}

/**
 * Ficheros del paquete que ya existen aquí con OTRO contenido (otra web en la misma carpeta): se pisarían sin
 * avisar. Los idénticos (un reintento tras un fallo a medias) no cuentan. El .htaccess se guarda aparte y no cuenta.
 */
function uwi_conflicts(string $zipFile, string $dest): array
{
    $self = basename(__FILE__);
    $out = [];
    $check = function (string $name, callable $content) use ($dest, $self, &$out) {
        if ($name === $self || $name === '.htaccess' || str_ends_with($name, '/') || str_starts_with($name, 'data/')) return;
        $t = "$dest/$name";
        if (is_file($t) && hash_file('sha256', $t) !== hash('sha256', (string) $content())) $out[] = $name;
    };
    if (class_exists('ZipArchive')) {
        $z = new ZipArchive();
        if ($z->open($zipFile) !== true) return [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $name = (string) $z->getNameIndex($i);
            $check($name, fn() => $z->getFromIndex($i));
        }
        $z->close();
        return $out;
    }
    $ph = new PharData($zipFile);
    foreach (new RecursiveIteratorIterator($ph) as $f) {
        $rel = ltrim(str_replace('\\', '/', substr((string) $f, strlen('phar://' . $zipFile))), '/');
        $check($rel, fn() => file_get_contents((string) $f));
    }
    return $out;
}

/** Instala de verdad. Devuelve [llave de /install, avisos]. */
function uwi_install(): array
{
    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    $warn = [];
    $dest = uwi_dir();
    if (file_exists("$dest/data")) throw new RuntimeException('Ya existe una carpeta «data». Para no pisar tus datos, el instalador no continúa.');

    [$zip, $expected] = uwi_fetch_package();
    if (!preg_match('/^[0-9a-f]{64}$/', $expected)) throw new RuntimeException('No se pudo obtener la huella SHA-256 del paquete: no se instala nada.');
    if (!hash_equals($expected, hash('sha256', $zip))) {
        throw new RuntimeException('La huella SHA-256 del paquete NO coincide: el fichero está corrupto o ha sido alterado. No se ha descomprimido nada.');
    }
    $state = uwi_state_dir(true);
    if ($state === null) throw new RuntimeException('No se pudo crear una carpeta temporal aquí.');
    $zipFile = "$state/paquete.zip";
    if (file_put_contents($zipFile, $zip) === false) throw new RuntimeException('No se pudo guardar el paquete en el hosting (¿sin espacio?).');
    unset($zip);
    if ($c = uwi_conflicts($zipFile, $dest)) {
        uwi_rrmdir($state);
        throw new RuntimeException('Esta carpeta ya tiene ficheros que se pisarían (' . implode(', ', array_slice($c, 0, 6))
            . (count($c) > 6 ? '…' : '') . '). Instala Universal Web en una carpeta vacía o en otro dominio; no se ha tocado nada.');
    }

    // Un .htaccess ya existente (otra web en la misma carpeta) se conserva como copia antes de poner el de Universal Web.
    if (is_file("$dest/.htaccess")) {
        if (@rename("$dest/.htaccess", "$dest/.htaccess.antes-de-universal-web")) $warn[] = 'Tu .htaccess anterior se guardó como .htaccess.antes-de-universal-web.';
    }
    $n = uwi_extract($zipFile, $dest);
    if ($n < 10 || !is_file("$dest/app/bootstrap.php")) throw new RuntimeException('El paquete no tiene el contenido esperado.');

    // data/ nace cerrada y con la llave de /install (solo quien venga de este instalador podrá usar el asistente).
    $key = bin2hex(random_bytes(24));
    if (!@mkdir("$dest/data", 0775) && !is_dir("$dest/data")) throw new RuntimeException('Se descomprimió, pero no se pudo crear la carpeta «data».');
    file_put_contents("$dest/data/.htaccess", "# Universal Web: esta carpeta nunca debe servirse por HTTP\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n");
    file_put_contents("$dest/data/index.html", '');
    file_put_contents("$dest/data/.install-key", $key);
    @chmod("$dest/data/.install-key", 0600);
    if (!is_dir("$dest/public/media")) @mkdir("$dest/public/media", 0775, true);
    return [$key, $warn];
}

/** Limpieza final: zip, estado, fichero de prueba y el propio instalador. Devuelve lo que no se pudo borrar. */
function uwi_cleanup(array $state): array
{
    $left = [];
    $dir = uwi_state_dir(false);
    if ($dir) { uwi_rrmdir($dir); if (is_dir($dir)) $left[] = basename($dir); }
    if (!empty($state['code'])) { $p = uwi_dir() . '/uw-' . $state['code'] . '.txt'; if (is_file($p) && !@unlink($p)) $left[] = basename($p); }
    if (!@unlink(__FILE__) && is_file(__FILE__)) $left[] = basename(__FILE__);
    return $left;
}

// ---------------------------------------------------------------- página

function uwi_page(string $title, string $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: no-referrer');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>' . e($title) . '</title><style>'
        . ':root{--bg:#f4f5fb;--card:#fff;--ink:#1c1d2b;--mut:#5d6177;--line:#e3e5f0;--acc:#4f46e5;--ok:#047857;--bad:#b91c1c;--warn:#b45309}'
        . '@media (prefers-color-scheme:dark){:root{--bg:#10111a;--card:#191b28;--ink:#eceefb;--mut:#a3a7c0;--line:#2a2d42;--acc:#818cf8;--ok:#34d399;--bad:#f87171;--warn:#fbbf24}}'
        . '*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:17px/1.6 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
        . '.wrap{max-width:640px;margin:0 auto;padding:28px 16px 56px}h1{font-size:1.6rem;margin:0 0 4px}.sub{color:var(--mut);margin:0 0 22px}'
        . '.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:20px;margin-bottom:16px}h2{font-size:1.1rem;margin:0 0 10px}'
        . 'ul{list-style:none;margin:0;padding:0}li{padding:8px 0;border-top:1px solid var(--line)}li:first-child{border-top:0}'
        . '.ok{color:var(--ok);font-weight:700}.no{color:var(--bad);font-weight:700}.det{display:block;color:var(--mut);font-size:.9rem}'
        . '.code{display:block;font:600 1.15rem ui-monospace,Menlo,Consolas,monospace;background:var(--bg);border:1px dashed var(--acc);border-radius:10px;padding:12px;margin:10px 0;word-break:break-all;user-select:all}'
        . 'ol{padding-left:1.3rem}ol li{border:0;padding:4px 0}'
        . 'button,.btn{display:inline-block;border:0;border-radius:10px;padding:13px 22px;font:inherit;font-weight:600;color:#fff;background:#4f46e5;cursor:pointer;text-decoration:none}'
        . 'button:focus-visible,.btn:focus-visible{outline:3px solid var(--warn);outline-offset:2px}'
        . '.msg{border-radius:12px;padding:12px 16px;margin-bottom:16px;border:1px solid}.msg.bad{color:var(--bad);border-color:var(--bad)}.msg.warn{color:var(--warn);border-color:var(--warn)}'
        . 'small,.mut{color:var(--mut)}code{background:var(--bg);padding:1px 6px;border-radius:6px}'
        . '</style></head><body><main class="wrap"><h1>Instalar Universal Web</h1><p class="sub">Un solo fichero, un par de clics.</p>'
        . $body . '</main></body></html>';
    exit;
}

function uwi_msg(string $text, string $kind = 'bad'): string
{
    return '<div class="msg ' . $kind . '" role="alert">' . e($text) . '</div>';
}

// ---------------------------------------------------------------- flujo

(function () {
    $msgs = '';
    // 1) ¿Ya instalado? Nunca se toca nada.
    if (is_file(uwi_dir() . '/data/config.php')) {
        uwi_page('Ya está instalado', '<div class="card"><h2>Universal Web ya está instalado</h2><p>No se ha cambiado nada y tus datos están a salvo.</p>'
            . '<p><a class="btn" href="/admin">Ir al panel</a></p>'
            . '<p class="mut">Por seguridad, borra el fichero <code>' . e(basename(__FILE__)) . '</code> de tu hosting.</p></div>');
    }
    if (file_exists(uwi_dir() . '/data')) {
        uwi_page('Carpeta data existente', '<div class="card"><h2>Hay una carpeta «data» a medias</h2><p>Para no pisar nada, el instalador se detiene. '
            . 'Si es una instalación anterior, abre <a href="/install">/install</a>; si no la necesitas, bórrala y recarga esta página.</p></div>');
    }

    // 2) Requisitos
    $reqs = uwi_requirements();
    $okAll = !in_array(false, array_column($reqs, 1), true);
    $list = '';
    foreach ($reqs as [$what, $ok, $help]) {
        $list .= '<li><span class="' . ($ok ? 'ok' : 'no') . '">' . ($ok ? '✓' : '✗') . '</span> ' . e($what) . ($ok ? '' : '<span class="det">' . e($help) . '</span>') . '</li>';
    }
    $req = '<div class="card"><h2>1. Tu hosting</h2><ul>' . $list . '</ul>'
        . ($okAll ? '' : '<p class="no">Corrige lo marcado con ✗ y recarga esta página.</p>') . '</div>';
    if (!$okAll) uwi_page('Faltan requisitos', $req);

    // 3) Demostrar que eres el dueño
    $state = uwi_state();
    if (empty($state['code'])) {
        $state = ['code' => bin2hex(random_bytes(6)), 'owner' => ''];
        if (!uwi_state_save($state)) uwi_page('Error', uwi_msg('No se pudo preparar la carpeta temporal. Revisa los permisos de escritura.'), 500);
    }
    $code = (string) $state['code'];
    $proof = uwi_dir() . "/uw-$code.txt";
    $cookie = (string) ($_COOKIE[UWI_COOKIE] ?? '');
    $isOwner = $cookie !== '' && !empty($state['owner']) && hash_equals((string) $state['owner'], hash('sha256', $cookie));
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $action = $post ? (string) ($_POST['accion'] ?? '') : '';
    if ($post && !uwi_same_origin()) uwi_page('Error', uwi_msg('La petición no procede de esta misma web. Recarga la página.'), 400);

    if ($action === 'verificar') {
        if (is_file($proof)) {
            $tok = bin2hex(random_bytes(24));
            $state['owner'] = hash('sha256', $tok);
            uwi_state_save($state);
            @unlink($proof);
            setcookie(UWI_COOKIE, $tok, ['path' => '/', 'httponly' => true, 'samesite' => 'Strict', 'secure' => uwi_is_https()]);
            $_COOKIE[UWI_COOKIE] = $tok;
            $isOwner = true;
        } else {
            $msgs = uwi_msg('Todavía no vemos el fichero «uw-' . $code . '.txt» en esta carpeta. Créalo y pulsa de nuevo el botón.');
        }
    }

    if (!$isOwner) {
        $folder = basename(uwi_dir());
        uwi_page('Confirma que eres el dueño', $msgs . $req . '<div class="card"><h2>2. Confirma que eres el dueño de este hosting</h2>'
            . '<p>Para que nadie más pueda instalar en tu dominio, crea un fichero <strong>vacío</strong> con este nombre exacto en la misma carpeta que <code>' . e(basename(__FILE__)) . '</code> (' . e($folder) . '):</p>'
            . '<span class="code">uw-' . e($code) . '.txt</span>'
            . '<ol><li>Abre el <strong>Administrador de archivos</strong> de tu hosting (o tu programa de FTP).</li>'
            . '<li>Pulsa <strong>«Nuevo archivo»</strong> y escribe el nombre de arriba.</li><li>Vuelve aquí y pulsa el botón.</li></ol>'
            . '<form method="post"><input type="hidden" name="accion" value="verificar"><button type="submit">Ya lo he creado, comprobar</button></form>'
            . '<p class="mut"><small>Es la comprobación de que tienes acceso a los ficheros de este dominio. El fichero se borra solo después.</small></p></div>');
    }

    // 4) Instalar
    if ($action === 'instalar') {
        try {
            [$key, $warn] = uwi_install();
        } catch (Throwable $ex) {
            uwi_page('No se pudo instalar', $req . uwi_msg($ex->getMessage()) . '<div class="card"><form method="post"><input type="hidden" name="accion" value="instalar"><button type="submit">Reintentar</button></form></div>', 500);
        }
        $left = uwi_cleanup($state);
        $next = '/install?k=' . $key;
        if (!$left && !$warn) {
            header('Location: ' . $next, true, 303);
            exit;
        }
        $body = '<div class="card"><h2>Casi listo</h2><p>Los ficheros ya están en su sitio.</p>';
        foreach ($warn as $w) $body .= uwi_msg($w, 'warn');
        if ($left) $body .= uwi_msg('No pude borrar: ' . implode(', ', $left) . '. Bórralo a mano desde el administrador de archivos de tu hosting.', 'warn');
        uwi_page('Casi listo', $body . '<p><a class="btn" href="' . e($next) . '">Continuar: crear mi administrador</a></p></div>');
    }

    uwi_page('Listo para instalar', $req . '<div class="card"><h2>2. Dueño confirmado ✓</h2>'
        . '<p>Se descargará el paquete oficial de Universal Web, se comprobará su huella SHA-256 y se descomprimirá aquí. '
        . 'Después te pedirá tu nombre, email y contraseña. Puede tardar un minuto.</p>'
        . '<form method="post"><input type="hidden" name="accion" value="instalar"><button type="submit">Instalar Universal Web</button></form>'
        . '<p class="mut"><small>Este instalador se borra solo al terminar.</small></p></div>');
})();
