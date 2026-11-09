<?php
// Gestor de temas: catálogo, vista previa, descarga (con manual para desarrolladores) y subida de temas nuevos.
// Un tema es código PHP que se ejecuta en el servidor: subirlos está DESACTIVADO por defecto y, aun activado
// ('allow_theme_upload' => true en data/config.php), solo lo puede hacer un superadmin con verificación en dos pasos.

declare(strict_types=1);

const THEMES_BUILTIN = ['default', 'noche', 'corporate'];
const THEME_MAX_UPLOAD = 15 * 1024 * 1024;   // tamaño del archivo subido
const THEME_MAX_UNPACKED = 40 * 1024 * 1024; // tamaño descomprimido
const THEME_MAX_FILES = 600;
const THEME_EXTENSIONS = ['php', 'css', 'js', 'json', 'md', 'txt', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'avif', 'gif', 'ico', 'woff', 'woff2'];
// Funciones que un tema no necesita jamás: si aparecen, se rechaza la subida.
const THEME_FORBIDDEN_CALLS = ['exec', 'shell_exec', 'system', 'passthru', 'proc_open', 'popen', 'pcntl_exec', 'eval', 'assert', 'create_function',
    'dl', 'putenv', 'ini_set', 'set_include_path', 'unlink', 'rmdir', 'rename', 'copy', 'file_put_contents', 'fwrite', 'fputs', 'move_uploaded_file',
    'chmod', 'chown', 'symlink', 'link', 'mail', 'curl_exec', 'curl_multi_exec', 'fsockopen', 'pfsockopen', 'stream_socket_client', 'socket_create',
    'db', 'q', 'insert', 'update', 'platform_save', 'config_save', 'user_create', 'token_create', 'phpinfo', 'call_user_func', 'call_user_func_array'];

// Nombres prohibidos que también son palabras normales en textos e iconos ('mail', 'link'…): solo se vigilan como llamadas.
const THEME_COMMON_WORDS = ['mail', 'link', 'copy', 'rename', 'update', 'insert', 'q', 'db', 'system'];

// Funciones que reciben otra función: el callback tiene que ser una función anónima, nunca un nombre en texto.
const THEME_CALLBACK_ARGS = ['array_map' => 0, 'array_filter' => 1, 'array_walk' => 1, 'array_walk_recursive' => 1, 'array_reduce' => 1, 'usort' => 1,
    'uasort' => 1, 'uksort' => 1, 'preg_replace_callback' => 1, 'iterator_apply' => 1, 'register_shutdown_function' => 0, 'register_tick_function' => 0,
    'forward_static_call' => 0, 'forward_static_call_array' => 0, 'set_error_handler' => 0, 'set_exception_handler' => 0, 'spl_autoload_register' => 0,
    'ob_start' => 0, 'header_register_callback' => 0, 'array_udiff' => -1, 'array_uintersect' => -1, 'is_callable' => -2];

function theme_upload_allowed(): bool
{
    return (bool) config('allow_theme_upload', false);
}

/** Ficha de un tema a partir de su theme.json. */
function theme_meta(string $slug): ?array
{
    if (!theme_exists($slug)) return null;
    $dir = UW_ROOT . "/themes/$slug";
    $m = json_dec((string) file_get_contents("$dir/theme.json"));
    $shot = '';
    foreach (['screenshot.webp', 'screenshot.png', 'screenshot.jpg'] as $f) if (is_file("$dir/assets/$f")) { $shot = "/theme-assets/$slug/$f"; break; }
    return [
        'slug' => $slug,
        'name' => (string) ($m['name'] ?? $slug),
        'description' => (string) ($m['description'] ?? ''),
        'author' => (string) ($m['author'] ?? ''),
        'version' => (string) ($m['version'] ?? '1.0'),
        'parent' => (string) ($m['parent'] ?? 'default'),
        'best_for' => array_values((array) ($m['best_for'] ?? [])),
        'suggested_accent' => (string) ($m['suggested_accent'] ?? ''),
        'screenshot' => $shot,
        'builtin' => in_array($slug, THEMES_BUILTIN, true),
        'templates' => array_values(array_map(fn($f) => substr($f, strlen($dir) + 1), array_merge(glob("$dir/*.php") ?: [], glob("$dir/blocks/*.php") ?: []))),
        'sites' => array_map(fn($r) => $r['name'], rows('SELECT name FROM sites WHERE theme = ? ORDER BY name', [$slug])),
    ];
}

function theme_catalog(): array
{
    $out = [];
    foreach (array_keys(themes_available()) as $slug) if ($m = theme_meta($slug)) $out[] = $m;
    usort($out, fn($a, $b) => [!$a['builtin'], $a['name']] <=> [!$b['builtin'], $b['name']]);
    return $out;
}

/** Empaqueta un tema en .zip con el manual del desarrollador dentro. Devuelve la ruta del archivo temporal. */
function theme_package(string $slug): string
{
    if (!theme_exists($slug)) throw new UwError(t('El tema no existe'));
    $tmpDir = UW_DATA . '/tmp';
    if (!is_dir($tmpDir)) mkdir($tmpDir, 0750, true);
    $zipPath = "$tmpDir/theme-$slug-" . bin2hex(random_bytes(4)) . '.zip';
    $zip = new PharData($zipPath, 0, null, Phar::ZIP);
    $src = UW_ROOT . "/themes/$slug";
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if (!$f->isFile() || $f->isLink()) continue;
        $zip->addFile($f->getPathname(), "$slug/" . substr($f->getPathname(), strlen($src) + 1));
    }
    if (is_file($manual = UW_ROOT . '/docs/THEMES.md')) $zip->addFromString("$slug/MANUAL.md", (string) file_get_contents($manual));
    return $zipPath;
}

/**
 * Comprueba y lee un .zip o .tar.gz de tema. Devuelve ['slug', 'meta', 'files' => [ruta => contenido], 'warnings'].
 * No escribe nada en disco salvo una copia temporal del archivo subido.
 */
function theme_inspect(string $file): array
{
    $size = (int) @filesize($file);
    if ($size <= 0) throw new UwError(t('No se ha recibido ningún archivo'));
    if ($size > THEME_MAX_UPLOAD) throw new UwError(t('El tema supera el máximo de {mb} MB', ['mb' => THEME_MAX_UPLOAD / 1048576]));
    $magic = (string) file_get_contents($file, false, null, 0, 4);
    $ext = str_starts_with($magic, "PK\x03\x04") ? 'zip' : (str_starts_with($magic, "\x1f\x8b") ? 'tar.gz' : '');
    if ($ext === '') throw new UwError(t('El archivo debe ser un .zip o un .tar.gz'));
    $tmp = UW_DATA . '/tmp/theme-up-' . bin2hex(random_bytes(6)) . ".$ext";
    if (!is_dir(dirname($tmp))) mkdir(dirname($tmp), 0750, true);
    copy($file, $tmp);
    try {
        if ($ext === 'tar.gz' && ($gz = gzopen($tmp, 'rb'))) { // tamaño real descomprimido antes de abrirlo (anti «zip bomb»)
            $n = 0;
            while (!gzeof($gz) && ($chunk = gzread($gz, 1 << 20)) !== false) if (($n += strlen($chunk)) > THEME_MAX_UNPACKED) { gzclose($gz); throw new UwError(t('El tema descomprimido supera el máximo de {mb} MB', ['mb' => THEME_MAX_UNPACKED / 1048576])); }
            gzclose($gz);
        }
        $archive = new PharData($tmp);
        $files = [];
        $total = 0;
        $root = 'phar://' . $tmp . '/';
        foreach (new RecursiveIteratorIterator($archive, RecursiveIteratorIterator::SELF_FIRST) as $entry) {
            $rel = substr($entry->getPathname(), strlen($root));
            if ($entry->isDir()) continue;
            if (count($files) >= THEME_MAX_FILES) throw new UwError(t('El tema tiene demasiados archivos (máximo {n})', ['n' => THEME_MAX_FILES]));
            if (!preg_match('#^[A-Za-z0-9_./-]+$#', $rel) || str_contains($rel, '..') || str_contains($rel, '//') || preg_match('#(^|/)\.#', $rel)) {
                throw new UwError(t('Nombre de archivo no permitido en el tema: {f}', ['f' => mb_substr($rel, 0, 120)]));
            }
            $total += $entry->getSize();
            if ($total > THEME_MAX_UNPACKED) throw new UwError(t('El tema descomprimido supera el máximo de {mb} MB', ['mb' => THEME_MAX_UNPACKED / 1048576]));
            $files[$rel] = (string) file_get_contents($entry->getPathname());
        }
    } catch (UwError $e) {
        throw $e;
    } catch (Throwable $e) {
        throw new UwError(t('No se puede leer el archivo: {err}', ['err' => $e->getMessage()]));
    } finally {
        @unlink($tmp);
    }
    if (!$files) throw new UwError(t('El archivo está vacío'));

    // Todo dentro de una carpeta (mi-tema/theme.json) o en la raíz (theme.json)
    $prefix = '';
    if (!isset($files['theme.json'])) {
        $tops = array_unique(array_map(fn($p) => explode('/', $p, 2)[0], array_keys($files)));
        if (count($tops) !== 1 || !isset($files[$tops[0] . '/theme.json'])) throw new UwError(t('Falta theme.json en la raíz del tema'));
        $prefix = $tops[0] . '/';
    }
    $clean = [];
    foreach ($files as $p => $c) $clean[substr($p, strlen($prefix))] = $c;
    $meta = json_decode($clean['theme.json'], true);
    if (!is_array($meta) || trim((string) ($meta['name'] ?? '')) === '') throw new UwError(t('theme.json no es válido: necesita al menos {"name": "..."}'));
    $slug = strtolower((string) ($meta['slug'] ?? rtrim($prefix, '/')));
    if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,39}$/', $slug)) throw new UwError(t('Identificador de tema no válido: usa "slug" en theme.json (minúsculas, números y guiones)'));
    if (in_array($slug, THEMES_BUILTIN, true)) throw new UwError(t('«{slug}» es un tema de serie: cambia el "slug" de theme.json para subir tu versión', ['slug' => $slug]));
    if (!isset($clean['layout.php'])) throw new UwError(t('Falta layout.php: es la plantilla obligatoria de todo tema'));

    $warnings = [];
    foreach ($clean as $p => $c) {
        $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
        if (!in_array($ext, THEME_EXTENSIONS, true)) throw new UwError(t('Tipo de archivo no permitido en el tema: {f}', ['f' => $p]));
        if ($ext === 'php') {
            if (!preg_match('#^(blocks/)?[a-z0-9_-]+\.php$#', $p)) throw new UwError(t('Los .php solo pueden estar en la raíz del tema o en blocks/: {f}', ['f' => $p]));
            theme_check_php($p, $c);
        } elseif (preg_match('/<\?php|<\?=/i', $c) && $ext !== 'md' && $ext !== 'txt') {
            throw new UwError(t('Código PHP escondido en un archivo que no es .php: {f}', ['f' => $p]));
        }
        if ($ext === 'svg' && preg_match('/<script|\bon[a-z]+\s*=|javascript:/i', $c)) throw new UwError(t('SVG con código ejecutable: {f}', ['f' => $p]));
    }
    if (!preg_grep('#^assets/#', array_keys($clean))) $warnings[] = t('El tema no tiene carpeta assets/ (CSS, JS, imágenes)');
    return ['slug' => $slug, 'meta' => $meta, 'files' => $clean, 'warnings' => $warnings];
}

/** Revisión estática de una plantilla PHP: sintaxis correcta y sin funciones peligrosas. */
function theme_check_php(string $name, string $code): void
{
    try {
        $tokens = PhpToken::tokenize($code, TOKEN_PARSE);
    } catch (ParseError $e) {
        throw new UwError(t('Error de sintaxis en {f}, línea {l}: {err}', ['f' => $name, 'l' => $e->getLine(), 'err' => $e->getMessage()]));
    }
    $sig = array_values(array_filter($tokens, fn($t) => !$t->isIgnorable()));
    // Variables a las que se asigna una función anónima ($isActive = fn(...) => ...): se pueden llamar.
    $closures = [];
    foreach ($sig as $i => $t) {
        if ($t->is(T_VARIABLE) && ($sig[$i + 1]->text ?? '') === '=' && in_array(strtolower($sig[$i + 2]->text ?? ''), ['fn', 'function', 'static'], true)) $closures[$t->text] = true;
    }
    foreach ($sig as $i => $t) {
        if ($t->is([T_EVAL, T_HALT_COMPILER]) || $t->text === '`') throw new UwError(t('{f}: construcción no permitida en un tema ({x})', ['f' => $name, 'x' => $t->text]));
        if ($t->is([T_INCLUDE, T_INCLUDE_ONCE, T_REQUIRE, T_REQUIRE_ONCE])) {
            // Solo se permite incluir otras plantillas a través de theme_file() o __DIR__
            $next = $sig[$i + 1] ?? null;
            $ok = $next && ($next->is(T_DIR) || ($next->is(T_STRING) && $next->text === 'theme_file')
                || ($next->is(T_STRING) && $next->text === 'dirname' && ($sig[$i + 3] ?? null)?->is(T_DIR)));
            if (!$ok) throw new UwError(t('{f}: include solo con theme_file() o __DIR__', ['f' => $name]));
        }
        if ($t->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && in_array(strtolower(ltrim($t->text, '\\')), THEME_FORBIDDEN_CALLS, true)
            && ($sig[$i + 1]->text ?? '') === '(' && !in_array($sig[$i - 1]->text ?? '', ['->', '?->', '::', 'function'], true)) {
            throw new UwError(t('{f}: la función {fn}() no está permitida en un tema', ['f' => $name, 'fn' => $t->text]));
        }
        // Texto con el nombre de una función prohibida ('system', "exec"…) o Reflection: vías para llamarlas indirectamente
        if ($t->is(T_CONSTANT_ENCAPSED_STRING) && in_array(strtolower(trim(substr($t->text, 1, -1), '\\ ')), array_diff(THEME_FORBIDDEN_CALLS, THEME_COMMON_WORDS), true)) {
            throw new UwError(t('{f}: la función {fn}() no está permitida en un tema', ['f' => $name, 'fn' => trim(substr($t->text, 1, -1))]));
        }
        if ($t->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && stripos(ltrim($t->text, '\\'), 'Reflection') === 0) throw new UwError(t('{f}: Reflection no está permitido en un tema', ['f' => $name]));
        if ($t->is([T_STRING, T_NAME_FULLY_QUALIFIED]) && isset(THEME_CALLBACK_ARGS[$fn = strtolower(ltrim($t->text, '\\'))]) && ($sig[$i + 1]->text ?? '') === '(') {
            $args = theme_call_args($sig, $i + 1);
            $pos = THEME_CALLBACK_ARGS[$fn];
            $arg = $pos === -2 ? null : ($pos === -1 ? end($args) : ($args[$pos] ?? null));
            if ($arg && !(in_array(strtolower($arg[0]->text), ['fn', 'function', 'static'], true) || ($arg[0]->is(T_VARIABLE) && isset($closures[$arg[0]->text]) && count($arg) === 1))) {
                throw new UwError(t('{f}: en {fn}() el callback tiene que ser una función anónima (fn() => …)', ['f' => $name, 'fn' => $t->text]));
            }
        }
        // Llamadas dinámicas ($fn(...), "system"(...)) para saltarse la lista
        if ((($t->is(T_VARIABLE) && !isset($closures[$t->text])) || $t->is(T_CONSTANT_ENCAPSED_STRING)) && ($sig[$i + 1]->text ?? '') === '(' && !in_array($sig[$i - 1]->text ?? '', ['->', '?->', '::'], true)) {
            throw new UwError(t('{f}: no se permiten llamadas dinámicas a funciones ({x}(...))', ['f' => $name, 'x' => $t->text]));
        }
    }
}

/** Instala un tema ya revisado. Si existe (y no es de serie) lo sustituye guardando copia del anterior. */
function theme_install(array $pkg, bool $replace): string
{
    $slug = $pkg['slug'];
    $dest = UW_ROOT . "/themes/$slug";
    if (is_dir($dest) && !$replace) throw new UwError(t('Ya existe un tema «{slug}». Marca «Sustituir» para actualizarlo.', ['slug' => $slug]));
    if (!is_writable(UW_ROOT . '/themes')) throw new UwError(t('La carpeta themes/ no tiene permiso de escritura en este servidor'));
    $stage = UW_ROOT . "/themes/.stage-$slug-" . bin2hex(random_bytes(4));
    mkdir($stage, 0755);
    try {
        foreach ($pkg['files'] as $rel => $content) {
            $f = "$stage/$rel";
            if (!is_dir(dirname($f))) mkdir(dirname($f), 0755, true);
            file_put_contents($f, $content);
            chmod($f, 0644);
        }
        if (is_dir($dest)) theme_move($dest, theme_backup_dir() . "/$slug-" . date('Ymd-His'));
        if (!@rename($stage, $dest)) throw new UwError(t('No se pudo instalar el tema en themes/{slug}', ['slug' => $slug]));
    } finally {
        if (is_dir($stage)) publish_rmtree($stage);
    }
    if (function_exists('opcache_reset')) @opcache_reset();
    audit(null, 'theme.install', $slug, ['files' => count($pkg['files']), 'replace' => $replace]);
    return $slug;
}

function theme_delete(string $slug): void
{
    if (!theme_exists($slug)) throw new UwError(t('El tema no existe'));
    if (in_array($slug, THEMES_BUILTIN, true)) throw new UwError(t('Los temas de serie no se pueden borrar'));
    if (val('SELECT COUNT(*) FROM sites WHERE theme = ?', [$slug])) throw new UwError(t('Hay webs usando este tema: cámbiales el tema antes de borrarlo'));
    theme_move(UW_ROOT . "/themes/$slug", theme_backup_dir() . "/$slug-" . date('Ymd-His') . '-deleted');
    audit(null, 'theme.delete', $slug);
}

/** Argumentos (listas de tokens) de la llamada cuyo «(» está en $open. */
function theme_call_args(array $sig, int $open): array
{
    $args = [[]];
    $depth = 0;
    for ($j = $open, $n = count($sig); $j < $n; $j++) {
        $x = $sig[$j]->text;
        if (in_array($x, ['(', '[', '{'], true) || $sig[$j]->is([T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES])) { if ($depth++ === 0) continue; }
        elseif (in_array($x, [')', ']', '}'], true)) { if (--$depth === 0) break; }
        elseif ($x === ',' && $depth === 1) { $args[] = []; continue; }
        $args[count($args) - 1][] = $sig[$j];
    }
    return array_values(array_filter($args));
}

/**
 * Vista previa de un tema. El panel genera un enlace firmado (?_theme=slug.caducidad.firma) válido 2 horas; la web lo
 * guarda en una cookie propia para poder navegar por todas las páginas con ese tema. Funciona aunque el panel esté en
 * otro dominio (admin_host). /?_theme= la quita. Nunca afecta a otros visitantes ni a la publicación estática.
 */
function theme_preview_link(SiteService $svc, string $slug): string
{
    $exp = time() + 7200;
    $tok = "$slug.$exp." . substr(hash_hmac('sha256', $svc->id() . "|$slug|$exp", secret_key()), 0, 32);
    return $svc->url('/?_theme=' . rawurlencode($tok));
}

function theme_preview_apply(SiteService $svc): ?string
{
    if (Out::$capture) return null;
    $tok = isset($_GET['_theme']) ? (string) $_GET['_theme'] : (string) ($_COOKIE['uw_theme_preview'] ?? '');
    if ($tok === '') {
        if (isset($_GET['_theme'])) out_header('Set-Cookie: uw_theme_preview=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax', false);
        return null;
    }
    if (!preg_match('/^([a-z0-9_-]+)\.(\d{10})\.([a-f0-9]{32})$/', $tok, $m) || (int) $m[2] < time()
        || !hash_equals(substr(hash_hmac('sha256', $svc->id() . "|{$m[1]}|{$m[2]}", secret_key()), 0, 32), $m[3]) || !theme_exists($m[1])) return null;
    if (isset($_GET['_theme'])) {
        out_header('Set-Cookie: uw_theme_preview=' . $tok . '; Path=/; Max-Age=' . ((int) $m[2] - time()) . '; HttpOnly; SameSite=Lax' . (is_https() ? '; Secure' : ''), false);
    }
    $svc->site['theme'] = $m[1];
    return $m[1];
}

/** Barra fija que avisa de la vista previa con enlace para salir. */
function theme_preview_bar(string $slug): string
{
    $name = theme_meta($slug)['name'] ?? $slug;
    return '<div style="position:fixed;z-index:10000;top:0;left:50%;transform:translateX(-50%);background:#111827;color:#fff;font:600 13px/1 system-ui,sans-serif;'
        . 'padding:9px 14px;border-radius:0 0 10px 10px;box-shadow:0 4px 14px rgba(0,0,0,.3)">' . e(__('Vista previa del tema «{name}»', ['name' => $name]))
        . ' · <a href="?_theme=" style="color:#93c5fd">' . e(__('Salir')) . '</a></div>';
}

function theme_backup_dir(): string
{
    $bak = UW_DATA . '/theme-backups';
    if (!is_dir($bak)) mkdir($bak, 0750, true);
    return $bak;
}

/** Mueve una carpeta; si está en otro disco (rename no puede), la copia y borra el original. */
function theme_move(string $from, string $to): void
{
    if (@rename($from, $to)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
    if (!is_dir($to)) mkdir($to, 0750, true);
    foreach ($it as $f) {
        $dest = $to . '/' . substr($f->getPathname(), strlen($from) + 1);
        if ($f->isDir()) { if (!is_dir($dest)) mkdir($dest, 0750, true); }
        elseif (!copy($f->getPathname(), $dest)) throw new UwError(t('No se pudo guardar la copia del tema anterior'));
    }
    publish_rmtree($from);
}
