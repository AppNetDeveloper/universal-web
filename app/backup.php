<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Copias de seguridad.
 *
 *  - Global: base de datos completa (VACUUM INTO, consistente), data/config.php (necesario para descifrar
 *    los secretos guardados: la copia es SENSIBLE) y todo public/media.
 *  - Por web: site.json (web, dominios, ajustes, páginas, entradas, medios, mensajes, formularios, código
 *    personalizado y usuarios sin contraseñas ni tokens) + los archivos de public/media/<slug>/.
 *
 * Formato: .tar.gz (PharData: solo hacen falta Phar + zlib, no zip). Con contraseña se cifra el archivo
 * entero con AES-256-GCM (clave PBKDF2-SHA256, 200.000 iteraciones): "UWBK1" + sal(16) + iv(12) + tag(16) + datos.
 * LÍMITE: el cifrado/descifrado trabaja con el archivo completo en memoria (~3x su tamaño); se comprueba
 * memory_limit y se avisa con un error claro. Para webs muy grandes, usa copias sin cifrar y cifra fuera.
 *
 * Las copias viven en UW_DATA/backups/ (fuera de la web, con .htaccess "Require all denied").
 * Restaurar una copia GLOBAL solo es posible por CLI: php bin/uw backup:restore.
 */

const BK_MAGIC = 'UWBK1';
const BK_KDF_ROUNDS = 200000;
const BK_MAX_UNPACKED = 2 * 1024 * 1024 * 1024; // protección contra "zip bombs"
const BK_MAX_ENTRIES = 200000;
const BK_UPLOAD_MAX = 1024 * 1024 * 1024;

// ---------------------------------------------------------------- rutas y nombres

function bk_protect_dir(string $dir): void
{
    if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) throw new UwError(t('No se puede crear la carpeta {dir} en data/', ['dir' => basename($dir)]));
    if (!is_file("$dir/.htaccess")) @file_put_contents("$dir/.htaccess", "Require all denied\n<IfModule !mod_authz_core.c>\nOrder allow,deny\nDeny from all\n</IfModule>\n");
    if (!is_file("$dir/index.html")) @file_put_contents("$dir/index.html", '');
}

function bk_dir(): string
{
    $d = UW_DATA . '/backups';
    bk_protect_dir($d);
    return $d;
}

/** Carpeta de trabajo temporal (en data/, mismo disco que las copias). Se borra con bk_rmtree. */
function bk_work(string $prefix = 'work'): string
{
    $base = UW_DATA . '/tmp';
    bk_protect_dir($base);
    $d = $base . '/' . $prefix . '-' . bin2hex(random_bytes(6));
    if (!@mkdir($d, 0750)) throw new UwError(t('No se puede crear la carpeta temporal en data/tmp'));
    return $d;
}

function bk_rmtree(string $path): void
{
    if (is_link($path) || is_file($path)) { @unlink($path); return; }
    if (!is_dir($path)) return;
    foreach (scandir($path) ?: [] as $f) {
        if ($f !== '.' && $f !== '..') bk_rmtree("$path/$f");
    }
    @rmdir($path);
}

/** Copia recursiva (ignora enlaces simbólicos). */
function bk_copytree(string $from, string $to): void
{
    if (!is_dir($to) && !mkdir($to, 0775, true)) throw new UwError(t('No se puede escribir en {dir}', ['dir' => basename($to)]));
    foreach (scandir($from) ?: [] as $f) {
        if ($f === '.' || $f === '..' || is_link("$from/$f")) continue;
        if (is_dir("$from/$f")) bk_copytree("$from/$f", "$to/$f");
        elseif (!copy("$from/$f", "$to/$f")) throw new UwError(t('No se pudo copiar {f}', ['f' => $f]));
    }
}

/** Mueve una carpeta: rename, y si es entre discos distintos, copia + borrado. */
function bk_movetree(string $from, string $to): void
{
    if (@rename($from, $to)) return;
    bk_copytree($from, $to);
    bk_rmtree($from);
}

/** Interpreta un nombre de copia. null si no es un nombre válido (estricto: sin barras, sin ..). */
function backup_info(string $name): ?array
{
    if (strlen($name) > 160) return null;
    if (preg_match('/^uw-global-(\d{8})-(\d{6})(-auto)?\.tar\.gz(\.enc)?$/', $name, $m)) {
        return ['type' => 'global', 'site_slug' => null, 'auto' => ($m[3] ?? '') !== '', 'encrypted' => ($m[4] ?? '') !== '', 'ts' => $m[1] . $m[2]];
    }
    if (preg_match('/^uw-site-([a-z0-9](?:[a-z0-9-]*[a-z0-9])?)-(\d{8})-(\d{6})\.tar\.gz(\.enc)?$/', $name, $m)) {
        return ['type' => 'site', 'site_slug' => $m[1], 'auto' => false, 'encrypted' => ($m[4] ?? '') !== '', 'ts' => $m[2] . $m[3]];
    }
    return null;
}

/** Ruta absoluta de una copia existente. */
function backup_file(string $name): string
{
    if (backup_info($name) === null) throw new UwError(t('Nombre de copia no válido'));
    $path = bk_dir() . '/' . $name;
    if (!is_file($path) || is_link($path)) throw new UwError(t('La copia no existe'));
    return $path;
}

function bk_unique_name(string $dir, string $prefix, string $suffix, string $ext): array
{
    $t = time();
    do {
        $ts = date('Ymd-His', $t++);
        $name = "$prefix-$ts$suffix$ext";
    } while (file_exists("$dir/$name"));
    return [$name, $ts];
}

function bk_secrets_hash(): string
{
    $s = (string) config('secret', '');
    return $s === '' ? '' : substr(hash('sha256', 'uwbk-secret|' . $s), 0, 16);
}

function bk_mem_check(int $bytes): void
{
    $lim = trim((string) ini_get('memory_limit'));
    if ($lim === '' || $lim === '-1') return;
    $n = (int) $lim;
    $n *= match (strtolower(substr($lim, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
    if ($n > 0 && $bytes > $n - memory_get_usage()) {
        throw new UwError(t('La copia es demasiado grande para cifrarla/descifrarla con la memoria disponible de PHP (memory_limit). Crea la copia sin contraseña o aumenta memory_limit.'));
    }
}

// ---------------------------------------------------------------- cifrado

function bk_encrypt_file(string $src, string $dst, string $password): void
{
    if (!function_exists('openssl_encrypt') || !in_array('aes-256-gcm', openssl_get_cipher_methods(), true)) throw new UwError(t('Este PHP no tiene OpenSSL con AES-GCM: no se puede cifrar'));
    bk_mem_check((int) filesize($src) * 3);
    $salt = random_bytes(16);
    $iv = random_bytes(12);
    $key = hash_pbkdf2('sha256', $password, $salt, BK_KDF_ROUNDS, 32, true);
    $tag = '';
    $plain = (string) file_get_contents($src);
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, BK_MAGIC, 16);
    unset($plain);
    if ($cipher === false || strlen($tag) !== 16) throw new UwError(t('No se pudo cifrar la copia'));
    if (file_put_contents($dst, BK_MAGIC . $salt . $iv . $tag . $cipher) === false) throw new UwError(t('No se pudo escribir la copia cifrada'));
}

function bk_decrypt_file(string $src, string $dst, string $password): void
{
    if ($password === '') throw new UwError(t('Esta copia está cifrada: indica su contraseña'));
    if (!function_exists('openssl_decrypt')) throw new UwError(t('Este PHP no tiene OpenSSL: no se puede descifrar'));
    bk_mem_check((int) filesize($src) * 3);
    $data = (string) file_get_contents($src);
    if (strlen($data) < 49 || !str_starts_with($data, BK_MAGIC)) throw new UwError(t('El archivo no es una copia cifrada válida'));
    $salt = substr($data, 5, 16);
    $iv = substr($data, 21, 12);
    $tag = substr($data, 33, 16);
    $key = hash_pbkdf2('sha256', $password, $salt, BK_KDF_ROUNDS, 32, true);
    $plain = openssl_decrypt(substr($data, 49), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, BK_MAGIC);
    unset($data);
    if ($plain === false) throw new UwError(t('Contraseña incorrecta o copia dañada'));
    file_put_contents($dst, $plain);
}

/**
 * Devuelve la ruta de un .tar.gz legible: la propia copia o una versión descifrada temporal.
 * @return array{0:string,1:?string} [ruta del tar.gz, carpeta temporal a borrar o null]
 */
function bk_plain_archive(string $path, ?string $password): array
{
    $fh = @fopen($path, 'rb');
    if (!$fh) throw new UwError(t('No se puede leer la copia'));
    $head = (string) fread($fh, 5);
    fclose($fh);
    if ($head === BK_MAGIC) {
        $work = bk_work('dec');
        try {
            bk_decrypt_file($path, "$work/archive.tar.gz", (string) $password);
        } catch (Throwable $ex) {
            bk_rmtree($work);
            throw $ex;
        }
        return ["$work/archive.tar.gz", $work];
    }
    if (strncmp($head, "\x1f\x8b", 2) !== 0) throw new UwError(t('El archivo no es una copia de Universal Web (.tar.gz)'));
    // PharData necesita que el nombre acabe en .tar.gz: si no, se enlaza/copia a un nombre válido.
    if (!str_ends_with(strtolower($path), '.tar.gz')) {
        $work = bk_work('arc');
        copy($path, "$work/archive.tar.gz");
        return ["$work/archive.tar.gz", $work];
    }
    return [$path, null];
}

/** @return array<string,int> entradas del tar (ruta relativa => tamaño), validadas. */
function bk_entries(PharData $phar, string $archive): array
{
    $prefix = 'phar://' . $archive . '/';
    $out = [];
    $total = 0;
    foreach (new RecursiveIteratorIterator($phar) as $f) {
        $p = $f->getPathname();
        if (!str_starts_with($p, $prefix)) continue;
        $rel = substr($p, strlen($prefix));
        if ($rel === '' || $rel[0] === '/' || str_contains($rel, "\0") || str_contains($rel, '\\')) continue;
        foreach (explode('/', $rel) as $seg) if ($seg === '..' || $seg === '.' || $seg === '') continue 2;
        $out[$rel] = (int) $f->getSize();
        $total += $out[$rel];
        if (count($out) > BK_MAX_ENTRIES || $total > BK_MAX_UNPACKED) throw new UwError(t('La copia es demasiado grande o tiene demasiados archivos'));
    }
    return $out;
}

function bk_open_phar(string $archive): PharData
{
    // Antes de que Phar descomprima nada, se mide el tamaño real descomprimido en streaming (anti "zip bomb").
    $gz = @gzopen($archive, 'rb');
    if ($gz) {
        $total = 0;
        while (!gzeof($gz)) {
            $chunk = gzread($gz, 1 << 20);
            if ($chunk === false) break;
            $total += strlen($chunk);
            if ($total > BK_MAX_UNPACKED) { gzclose($gz); throw new UwError(t('La copia descomprimida es demasiado grande')); }
        }
        gzclose($gz);
    }
    try {
        return new PharData($archive);
    } catch (Throwable) {
        throw new UwError(t('El archivo no es una copia válida'));
    }
}

function bk_read_json(PharData $phar, string $file): ?array
{
    try {
        if (!isset($phar[$file])) return null;
        $d = json_decode($phar[$file]->getContent(), true);
        return is_array($d) ? $d : null;
    } catch (Throwable) {
        return null;
    }
}

// ---------------------------------------------------------------- crear

/** Crea una copia. $siteId null = GLOBAL. Devuelve los metadatos (con 'name'). */
function backup_create(?int $siteId, ?string $password = null, bool $auto = false): array
{
    if (!class_exists('PharData') || !function_exists('gzopen')) throw new UwError(t('Este PHP necesita las extensiones Phar y zlib para crear copias'));
    $password = ($password === null || $password === '') ? null : $password;
    if ($password !== null && mb_strlen($password) < 8) throw new UwError(t('La contraseña de la copia debe tener al menos 8 caracteres'));
    $site = null;
    if ($siteId !== null) $site = site_by_id($siteId) ?? throw new UwError(t('La web no existe'));
    @set_time_limit(0);

    $dir = bk_dir();
    $work = bk_work('create');
    try {
        $media = UW_PUBLIC . '/media';
        $mediaRoot = $site ? "$media/{$site['slug']}" : $media;
        $files = [];      // ruta en el tar => ruta en disco
        $strings = [];    // ruta en el tar => contenido
        $nMedia = 0;
        if (is_dir($mediaRoot) && !is_link($mediaRoot)) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($mediaRoot, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (!$f->isFile() || $f->isLink()) continue;
                $files['media/' . substr($f->getPathname(), strlen($media) + 1)] = $f->getPathname();
                if ($f->getFilename() !== '.htaccess') $nMedia++;
            }
        }

        $meta = [
            'type' => $siteId === null ? 'global' : 'site', 'site_slug' => $site['slug'] ?? null, 'site_name' => $site['name'] ?? null,
            'created_at' => now(), 'created_by' => Actor::$name, 'version' => UW_VERSION, 'schema' => UW_SCHEMA,
            'auto' => $auto, 'encrypted' => $password !== null,
        ];
        if ($site === null) {
            $dbCopy = "$work/universal.sqlite";
            db()->exec('VACUUM INTO ' . db()->quote($dbCopy));
            $files['universal.sqlite'] = $dbCopy;
            $hasConfig = is_file(UW_DATA . '/config.php');
            if ($hasConfig) $files['config.php'] = UW_DATA . '/config.php';
            $meta['includes_config'] = $hasConfig;
            $meta['note'] = 'Incluye data/config.php (clave secreta): trata esta copia como información sensible.';
            $meta['counts'] = [
                'sites' => (int) val('SELECT COUNT(*) FROM sites'), 'pages' => (int) val('SELECT COUNT(*) FROM pages'),
                'posts' => (int) val('SELECT COUNT(*) FROM posts'), 'media' => $nMedia, 'users' => (int) val('SELECT COUNT(*) FROM users'),
            ];
        } else {
            $export = bk_export_site($site);
            $strings['site.json'] = json_enc($export, true);
            $meta['counts'] = [
                'pages' => count($export['pages']), 'posts' => count($export['posts']), 'media' => $nMedia,
                'messages' => count($export['messages']), 'users' => count($export['users']),
            ];
        }
        $strings['manifest.json'] = json_enc($meta, true);

        $tar = "$work/out.tar";
        $phar = new PharData($tar);
        foreach ($strings as $rel => $content) $phar->addFromString($rel, $content);
        foreach ($files as $rel => $abs) $phar->addFile($abs, $rel);
        $gz = $phar->compress(Phar::GZ);
        unset($gz, $phar);
        @unlink($tar);
        $final = "$work/out.tar.gz";
        if (!is_file($final)) throw new UwError(t('No se pudo generar el archivo de la copia'));

        $ext = '.tar.gz';
        if ($password !== null) {
            bk_encrypt_file($final, "$work/out.enc", $password);
            $final = "$work/out.enc";
            $ext .= '.enc';
        }
        [$name] = $site === null
            ? bk_unique_name($dir, 'uw-global', $auto ? '-auto' : '', $ext)
            : bk_unique_name($dir, 'uw-site-' . $site['slug'], $auto ? '-auto' : '', $ext);
        if (!@rename($final, "$dir/$name") && !@copy($final, "$dir/$name")) throw new UwError(t('No se pudo guardar la copia en data/backups'));
        @chmod("$dir/$name", 0640);
        $meta['size'] = (int) filesize("$dir/$name");
        $meta['name'] = $name;
        file_put_contents("$dir/$name.json", json_enc($meta, true));
        @chmod("$dir/$name.json", 0640);
    } finally {
        bk_rmtree($work);
    }
    audit($siteId, 'backup.create', $name, ['size' => $meta['size'], 'encrypted' => $meta['encrypted'], 'auto' => $auto]);
    if ($auto) bk_retention($site === null ? 'uw-global' : 'uw-site-' . $site['slug']);
    // Copia fuera del servidor (si esta web, o las globales, tienen destino activo). Un fallo no anula la copia local.
    if (function_exists('offsite_after_backup') && ($sent = offsite_after_backup($siteId, "$dir/$name"))) $meta['offsite'] = $sent;
    return $meta;
}

/** Datos exportables de una web (sin hashes de contraseñas ni tokens). */
function bk_export_site(array $site): array
{
    $id = (int) $site['id'];
    $strip = function (array $r): array { unset($r['id'], $r['site_id']); return $r; };
    $forms = val('SELECT config FROM site_forms WHERE site_id = ?', [$id]);
    $code = row('SELECT * FROM site_code WHERE site_id = ?', [$id]);
    return [
        'format' => 1, 'version' => UW_VERSION, 'schema' => UW_SCHEMA,
        'site' => ['slug' => $site['slug'], 'name' => $site['name'], 'theme' => $site['theme'], 'settings' => json_dec($site['settings'], []), 'created_at' => $site['created_at']],
        'domains' => site_domains($id),
        'pages' => array_map($strip, rows('SELECT * FROM pages WHERE site_id = ? ORDER BY id', [$id])),
        'posts' => array_map($strip, rows('SELECT * FROM posts WHERE site_id = ? ORDER BY id', [$id])),
        'media' => array_map($strip, rows('SELECT * FROM media WHERE site_id = ? ORDER BY id', [$id])),
        'messages' => array_map($strip, rows('SELECT * FROM messages WHERE site_id = ? ORDER BY id', [$id])),
        'forms' => $forms === null ? null : json_dec($forms, []),
        'secrets_from_install' => bk_secrets_hash(),
        'site_code' => $code ? $strip($code) : null,
        'site_code_history' => array_map($strip, rows('SELECT * FROM site_code_history WHERE site_id = ? ORDER BY id', [$id])),
        'users' => array_map(fn($u) => ['email' => $u['email'], 'name' => $u['name'], 'role' => $u['role']],
            rows('SELECT u.email, u.name, su.role FROM site_users su JOIN users u ON u.id = su.user_id WHERE su.site_id = ? ORDER BY u.id', [$id])),
    ];
}

/** Conserva solo las N copias globales automáticas más recientes. */
function bk_retention(string $prefix = 'uw-global'): void
{
    $keep = max(1, (int) platform()['backup_keep']);
    $names = [];
    foreach (glob(bk_dir() . '/' . $prefix . '-*-auto.tar.gz*') ?: [] as $f) {
        $n = basename($f);
        if (str_ends_with($n, '.json')) continue;
        // El comodín «uw-site-a-*» también casa con la web «a-b»: solo las de ESTE prefijo + fecha.
        if (!preg_match('/^' . preg_quote($prefix, '/') . '-\d{8}-\d{6}/', $n)) continue;
        $i = backup_info($n);
        if ($i && $i['auto']) $names[] = $n;
    }
    rsort($names); // el sello de fecha va en el nombre: orden alfabético inverso = más nuevas primero
    foreach (array_slice($names, $keep) as $old) {
        @unlink(bk_dir() . "/$old");
        @unlink(bk_dir() . "/$old.json");
    }
}

// ---------------------------------------------------------------- listar / borrar

function backup_list(?int $siteId): array
{
    $slug = null;
    if ($siteId !== null) {
        $s = site_by_id($siteId);
        if (!$s) return [];
        $slug = $s['slug'];
    }
    $out = [];
    foreach (glob(bk_dir() . '/uw-*.tar.gz*') ?: [] as $f) {
        $name = basename($f);
        $info = backup_info($name);
        if (!$info || is_link($f) || !is_file($f)) continue;
        if ($slug !== null && ($info['type'] !== 'site' || $info['site_slug'] !== $slug)) continue;
        $meta = [];
        if (is_file("$f.json")) $meta = json_dec((string) file_get_contents("$f.json"), []);
        $ts = DateTime::createFromFormat('Ymd His', substr($info['ts'], 0, 8) . ' ' . substr($info['ts'], 8));
        $out[] = [
            'name' => $name, 'type' => $info['type'], 'site_slug' => $info['site_slug'],
            'site_name' => $meta['site_name'] ?? null, 'auto' => $info['auto'],
            'created_at' => (string) ($meta['created_at'] ?? ($ts ? $ts->format('Y-m-d H:i:s') : '')),
            'created_by' => (string) ($meta['created_by'] ?? '—'), 'size' => (int) filesize($f), 'encrypted' => $info['encrypted'],
            'counts' => $meta['counts'] ?? [], 'version' => $meta['version'] ?? null,
        ];
    }
    usort($out, fn($a, $b) => [$b['created_at'], $b['name']] <=> [$a['created_at'], $a['name']]);
    return $out;
}

function backup_delete(string $name): void
{
    $path = backup_file($name);
    $info = backup_info($name);
    if (!@unlink($path)) throw new UwError(t('No se pudo borrar la copia'));
    @unlink("$path.json");
    $site = $info['site_slug'] ? site_by_slug($info['site_slug']) : null;
    audit($site ? (int) $site['id'] : null, 'backup.delete', $name);
}

// ---------------------------------------------------------------- cargar una copia por web (validada)

/** Extrae y valida lo necesario de una copia por web. Devuelve ['data'=>..., 'work'=>carpeta con media/, 'warnings'=>[]]. */
function bk_load_site(string $path, ?string $password): array
{
    [$tar, $decDir] = bk_plain_archive($path, $password);
    $work = null;
    try {
        $phar = bk_open_phar($tar);
        $real = realpath($tar) ?: $tar;
        $manifest = bk_read_json($phar, 'manifest.json');
        $data = bk_read_json($phar, 'site.json');
        if (!$manifest || ($manifest['type'] ?? '') !== 'site' || !$data) throw new UwError(t('Esta no es una copia de una web (falta site.json)'));
        if ((int) ($data['schema'] ?? 0) > UW_SCHEMA) throw new UwError(t('La copia es de una versión más nueva de Universal Web: actualiza antes de restaurarla'));
        $slug = (string) ($data['site']['slug'] ?? '');
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug) || !is_array($data['site']['settings'] ?? null)) throw new UwError(t('La copia está dañada: datos de la web no válidos'));
        foreach (['pages', 'posts', 'media', 'messages', 'users', 'domains'] as $k) if (!is_array($data[$k] ?? null)) $data[$k] = [];

        $work = bk_work('restore');
        $entries = bk_entries($phar, $real);
        $skipped = 0;
        $extracted = 0;
        foreach ($entries as $rel => $size) {
            if (!preg_match('#^media/' . preg_quote($slug, '#') . '/((?:[A-Za-z0-9_][A-Za-z0-9._-]*/)*[A-Za-z0-9_][A-Za-z0-9._-]*)$#', $rel)) {
                if (str_starts_with($rel, 'media/')) $skipped++;
                continue;
            }
            $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
            if (!isset(MEDIA_TYPES[$ext])) { $skipped++; continue; }
            $dest = "$work/$rel";
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
            if (!@copy('phar://' . $real . '/' . $rel, $dest)) { $skipped++; continue; }
            if (!media_content_matches($dest, $ext)) { @unlink($dest); $skipped++; continue; }
            $extracted++;
        }
        unset($phar);
        $warnings = [];
        if ($skipped) $warnings[] = t('{n} archivo(s) de medios de la copia se omitieron por no ser válidos.', ['n' => $skipped]);
        return ['data' => $data, 'work' => $work, 'files' => $extracted, 'warnings' => $warnings];
    } catch (Throwable $ex) {
        if ($work) bk_rmtree($work);
        throw $ex;
    } finally {
        if ($decDir) bk_rmtree($decDir);
    }
}

function bk_table_cols(string $table): array
{
    static $cache = [];
    return $cache[$table] ??= array_column(rows("PRAGMA table_info($table)"), 'name');
}

/** Inserta una fila de la copia usando solo columnas que existen aquí. */
function bk_insert_row(string $table, int $siteId, array $r, ?callable $map = null): void
{
    $cols = bk_table_cols($table);
    $in = ['site_id' => $siteId];
    foreach ($r as $k => $v) {
        if ($k === 'id' || $k === 'site_id' || !in_array($k, $cols, true)) continue;
        if (is_array($v)) $v = json_enc($v);
        elseif (is_bool($v)) $v = $v ? 1 : 0;
        elseif ($v !== null && !is_scalar($v)) continue;
        if ($map && is_string($v)) $v = $map($v);
        $in[$k] = $v;
    }
    insert($table, $in);
}

/** Aplica una copia ya cargada sobre una web existente. Devuelve avisos. */
function bk_apply_site(array $site, array $loaded, bool $attachUsers, bool $warnDomains = true): array
{
    $data = $loaded['data'];
    $id = (int) $site['id'];
    $target = (string) $site['slug'];
    $orig = (string) $data['site']['slug'];
    $warnings = $loaded['warnings'];
    $map = $orig === $target ? null : fn(string $s) => str_replace('/media/' . $orig . '/', '/media/' . $target . '/', $s);

    // Secretos de formularios: solo se pueden usar si la clave de esta instalación es la de la copia.
    $forms = is_array($data['forms'] ?? null) ? $data['forms'] : null;
    if ($forms) {
        $same = ($data['secrets_from_install'] ?? '') !== '' && hash_equals(bk_secrets_hash(), (string) $data['secrets_from_install']);
        $lost = [];
        foreach (FORM_SECRET_FIELDS as $k) {
            if (!$same && !empty($forms[$k])) { $forms[$k] = ''; $lost[] = $k; }
        }
        if (isset($forms['webhook_secret']) && !$same && $forms['webhook_secret'] !== '') { $forms['webhook_secret'] = ''; $lost[] = 'webhook_secret'; }
        if ($lost) $warnings[] = t('Los secretos de Formularios ({list}) no se pueden descifrar en esta instalación: se han borrado, vuelve a introducirlos en Formularios.', ['list' => implode(', ', $lost)]);
    }

    $theme = (string) ($data['site']['theme'] ?? 'default');
    if (!theme_exists($theme)) {
        $warnings[] = t("El tema '{theme}' no existe en esta instalación: se mantiene el tema actual.", ['theme' => $theme]);
        $theme = (string) $site['theme'];
    }
    $curDomains = site_domains($id);
    $bkDomains = array_values(array_filter(array_map('strval', $data['domains'])));
    if ($warnDomains && $bkDomains && $orig !== '' && array_diff($bkDomains, $curDomains)) {
        $warnings[] = t('Los dominios de la copia ({backup}) difieren de los actuales ({current}): no se han modificado.', ['backup' => implode(', ', $bkDomains), 'current' => implode(', ', $curDomains)]);
    }

    $mediaDir = UW_PUBLIC . "/media/$target";
    $newMedia = $loaded['work'] . "/media/$orig";
    $trash = null;
    $swapped = false;
    db()->beginTransaction();
    try {
        foreach (['pages', 'posts', 'media', 'messages', 'site_code', 'site_code_history', 'site_forms'] as $t) q("DELETE FROM $t WHERE site_id = ?", [$id]);
        update('sites', [
            'name' => mb_substr(trim((string) ($data['site']['name'] ?? $site['name'])), 0, 100) ?: $site['name'], 'theme' => $theme,
            'settings' => json_enc(array_replace_recursive(site_defaults(), $map ? json_dec($map(json_enc($data['site']['settings'])), []) : $data['site']['settings'])),
        ], 'id = ?', [$id]);
        foreach ($data['pages'] as $r) if (is_array($r)) bk_insert_row('pages', $id, $r, $map);
        foreach ($data['posts'] as $r) if (is_array($r)) bk_insert_row('posts', $id, $r, $map);
        foreach ($data['media'] as $r) {
            if (!is_array($r) || !is_string($r['path'] ?? null)) continue;
            $path = $orig !== $target && str_starts_with($r['path'], "$orig/") ? "$target/" . substr($r['path'], strlen($orig) + 1) : $r['path'];
            if (!preg_match('#^' . preg_quote($target, '#') . '/(?:[A-Za-z0-9_][A-Za-z0-9._-]*/)*[A-Za-z0-9_][A-Za-z0-9._-]*$#', $path)) continue;
            bk_insert_row('media', $id, ['path' => $path] + $r);
        }
        foreach ($data['messages'] as $r) if (is_array($r)) bk_insert_row('messages', $id, $r);
        if ($forms) insert('site_forms', ['site_id' => $id, 'config' => json_enc($forms), 'updated_at' => now()]);
        if (is_array($data['site_code'] ?? null)) bk_insert_row('site_code', $id, $data['site_code'], $map);
        foreach ((array) ($data['site_code_history'] ?? []) as $r) if (is_array($r)) bk_insert_row('site_code_history', $id, $r, $map);

        if ($attachUsers) {
            $missing = [];
            foreach ($data['users'] as $u) {
                $email = strtolower((string) ($u['email'] ?? ''));
                $role = (string) ($u['role'] ?? '');
                if (!in_array($role, ['editor', 'admin'], true)) continue;
                $uid = row('SELECT id, is_superadmin FROM users WHERE email = ?', [$email]);
                if (!$uid) { $missing[] = $email; continue; }
                if ($uid['is_superadmin'] || val('SELECT 1 FROM site_users WHERE site_id = ? AND user_id = ?', [$id, $uid['id']])) continue;
                insert('site_users', ['site_id' => $id, 'user_id' => $uid['id'], 'role' => $role]);
            }
            if ($missing) $warnings[] = t('Estos usuarios de la copia no existen en esta instalación y no se han creado (créalos y añádelos a la web): {list}.', ['list' => implode(', ', $missing)]);
        }

        // Archivos de medios: la carpeta de la web se sustituye entera (solo la de esta web).
        $mediaBase = UW_PUBLIC . '/media';
        if (!is_dir($mediaBase) && !mkdir($mediaBase, 0775, true)) throw new UwError(t('No se puede escribir en public/media'));
        if (is_dir($mediaDir) && !is_link($mediaDir)) {
            $trash = bk_work('trash') . '/old';
            bk_movetree($mediaDir, $trash);
        }
        $swapped = true;
        if (is_dir($newMedia)) bk_movetree($newMedia, $mediaDir);
        else @mkdir($mediaDir, 0775, true);
        db()->commit();
    } catch (Throwable $ex) {
        if (db()->inTransaction()) db()->rollBack();
        if ($swapped) {
            bk_rmtree($mediaDir);
            if ($trash && is_dir($trash)) bk_movetree($trash, $mediaDir);
        }
        throw $ex instanceof UwError ? $ex : new UwError(t('No se pudo restaurar la copia: {err}', ['err' => $ex->getMessage()]));
    } finally {
        if ($trash) bk_rmtree(dirname($trash));
    }
    return $warnings;
}

function bk_actor_is_super(): bool
{
    if (Actor::$type === 'system') return true;
    if (Actor::$type === 'user' && Actor::$id) return (bool) val('SELECT is_superadmin FROM users WHERE id = ?', [Actor::$id]);
    return false;
}

/**
 * Restaura una copia POR WEB dentro de una web existente. Antes crea una copia de seguridad de su estado actual.
 * Un admin de web solo puede restaurar copias de su mismo slug; el superadmin (o el CLI) cualquier copia por web.
 * @return array{warnings:string[],safety:string} avisos y nombre de la copia previa
 */
function backup_restore_site(int $siteId, string $name, ?string $password = null, ?bool $anySlug = null): array
{
    $site = site_by_id($siteId) ?? throw new UwError(t('La web no existe'));
    $info = backup_info($name);
    if (!$info || $info['type'] !== 'site') throw new UwError(t('Solo se pueden restaurar copias de una web desde aquí'));
    $anySlug ??= bk_actor_is_super();
    if (!$anySlug && $info['site_slug'] !== $site['slug']) throw new UwError(t('Esta copia pertenece a otra web'));
    $path = backup_file($name);
    @set_time_limit(0);

    $loaded = bk_load_site($path, $password);
    try {
        if (!$anySlug && $loaded['data']['site']['slug'] !== $site['slug']) throw new UwError(t('Esta copia pertenece a otra web'));
        $safety = backup_create($siteId, $password);
        $warnings = bk_apply_site($site, $loaded, $anySlug);
    } finally {
        bk_rmtree($loaded['work']);
    }
    audit($siteId, 'backup.restore', $name, ['safety' => $safety['name']]);
    return ['warnings' => $warnings, 'safety' => $safety['name']];
}

/**
 * SOLO superadmin: crea una web NUEVA a partir de una copia por web (migrar/clonar entre instalaciones).
 * @return array{site:array,warnings:string[]}
 */
function backup_import_site(string $name, ?string $password, string $newSlug, array $domains): array
{
    $info = backup_info($name);
    if (!$info || $info['type'] !== 'site') throw new UwError(t('Elige una copia de una web'));
    $newSlug = slugify($newSlug);
    if ($newSlug === '') throw new UwError(t('Indica el identificador (slug) de la web nueva'));
    $domains = normalize_domains($domains);
    if (!$domains) throw new UwError(t('Indica al menos un dominio'));
    @set_time_limit(0);

    $loaded = bk_load_site(backup_file($name), $password);
    $site = null;
    try {
        $d = $loaded['data'];
        $theme = theme_exists((string) ($d['site']['theme'] ?? '')) ? (string) $d['site']['theme'] : 'default';
        $site = site_create((string) ($d['site']['name'] ?? $newSlug), $newSlug, $domains, $theme);
        // Si quedaran restos de medios de un slug reutilizado, no se mezclan con la copia.
        $warnings = bk_apply_site($site, $loaded, true, false);
    } catch (Throwable $ex) {
        if ($site) {
            q('DELETE FROM sites WHERE id = ?', [$site['id']]);
            bk_rmtree(UW_PUBLIC . '/media/' . $site['slug']);
        }
        throw $ex;
    } finally {
        bk_rmtree($loaded['work']);
    }
    audit((int) $site['id'], 'backup.import', $name, ['slug' => $newSlug]);
    return ['site' => site_by_id((int) $site['id']), 'warnings' => $warnings];
}

// ---------------------------------------------------------------- subida de una copia (otra instalación)

/**
 * Guarda un archivo subido por formulario como copia, tras comprobar su contenido.
 * $onlySlug: si no es null, solo se admite una copia por web de ese slug (admins de web).
 * Devuelve el nombre de la copia guardada.
 */
function backup_store_upload(array $file, ?string $password, ?string $onlySlug = null): string
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new UwError(t('El archivo supera el tamaño máximo de subida del servidor ({max})', ['max' => ini_get('upload_max_filesize')]));
    if ($err !== UPLOAD_ERR_OK) throw new UwError(t('No se pudo subir el archivo'));
    $tmp = (string) ($file['tmp_name'] ?? '');
    if (!is_uploaded_file($tmp)) throw new UwError(t('Subida no válida'));
    $orig = strtolower((string) ($file['name'] ?? ''));
    if (!str_ends_with($orig, '.tar.gz') && !str_ends_with($orig, '.tar.gz.enc')) throw new UwError(t('El archivo debe ser una copia de Universal Web (.tar.gz o .tar.gz.enc)'));
    $size = (int) filesize($tmp);
    if ($size <= 0 || $size > BK_UPLOAD_MAX) throw new UwError(t('El archivo está vacío o es demasiado grande'));
    $work = bk_work('upload');
    try {
        if (!move_uploaded_file($tmp, "$work/upload.bin")) throw new UwError(t('No se pudo guardar el archivo subido'));
        return bk_ingest("$work/upload.bin", $password, $onlySlug, true);
    } finally {
        bk_rmtree($work);
    }
}

/** CLI: incorpora a data/backups una copia que está en otra ruta (tras comprobarla). Devuelve su nombre. */
function backup_store_path(string $path, ?string $password): string
{
    if (!is_file($path)) throw new UwError(t('No existe el archivo {path}', ['path' => $path]));
    $work = bk_work('ingest');
    try {
        if (!copy($path, "$work/upload.bin")) throw new UwError(t('No se pudo leer el archivo'));
        return bk_ingest("$work/upload.bin", $password, null, false);
    } finally {
        bk_rmtree($work);
    }
}

/** Valida un archivo de copia ya en nuestra carpeta temporal y lo guarda en data/backups con nombre y sidecar propios. */
function bk_ingest(string $staged, ?string $password, ?string $onlySlug, bool $uploaded): string
{
    $password = ($password === null || $password === '') ? null : $password;
    $dir = bk_dir();
    $fh = fopen($staged, 'rb');
    $head = (string) fread($fh, 5);
    fclose($fh);
    $encrypted = $head === BK_MAGIC;
    if ($encrypted && $password === null) throw new UwError(t('La copia está cifrada: indica su contraseña para poder comprobarla'));
    [$tar, $dec] = bk_plain_archive($staged, $password);
    try {
        $phar = bk_open_phar($tar);
        $m = bk_read_json($phar, 'manifest.json');
        unset($phar);
    } finally {
        if ($dec) bk_rmtree($dec);
    }
    if (!$m || !in_array($m['type'] ?? '', ['global', 'site'], true)) throw new UwError(t('El archivo no es una copia válida de Universal Web (falta manifest.json)'));
    if ((int) ($m['schema'] ?? 0) > UW_SCHEMA) throw new UwError(t('La copia es de una versión más nueva de Universal Web'));
    $ext = $encrypted ? '.tar.gz.enc' : '.tar.gz';
    if ($m['type'] === 'site') {
        $slug = (string) ($m['site_slug'] ?? '');
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) throw new UwError(t('La copia tiene un identificador de web no válido'));
        if ($onlySlug !== null && $slug !== $onlySlug) throw new UwError(t('Esta copia pertenece a otra web'));
        [$name] = bk_unique_name($dir, "uw-site-$slug", '', $ext);
    } else {
        if ($onlySlug !== null) throw new UwError(t('Esta copia no es de una sola web'));
        [$name] = bk_unique_name($dir, 'uw-global', '', $ext);
    }
    if (!@rename($staged, "$dir/$name") && !@copy($staged, "$dir/$name")) throw new UwError(t('No se pudo guardar la copia'));
    @chmod("$dir/$name", 0640);
    $meta = [
        'type' => $m['type'], 'site_slug' => $m['site_slug'] ?? null, 'site_name' => $m['site_name'] ?? null,
        'created_at' => (string) ($m['created_at'] ?? now()), 'created_by' => Actor::$name . ($uploaded ? ' (subida)' : ' (importada)'), 'size' => (int) filesize("$dir/$name"),
        'encrypted' => $encrypted, 'version' => $m['version'] ?? null, 'schema' => $m['schema'] ?? null, 'counts' => $m['counts'] ?? [], 'uploaded' => true,
    ];
    file_put_contents("$dir/$name.json", json_enc($meta, true));
    @chmod("$dir/$name.json", 0640);
    $site = $meta['site_slug'] ? site_by_slug((string) $meta['site_slug']) : null;
    audit($site ? (int) $site['id'] : null, 'backup.upload', $name);
    return $name;
}

// ---------------------------------------------------------------- restauración GLOBAL (solo CLI)

/**
 * Sustituye base de datos y public/media por los de una copia global. SOLO desde la línea de comandos.
 * $path: ruta de un .tar.gz[.enc]. Hace antes una copia de seguridad global de seguridad.
 * @return array{safety:string,warnings:string[]}
 */
function backup_restore_global(string $path, ?string $password = null, bool $withConfig = false): array
{
    if (PHP_SAPI !== 'cli') throw new UwError(t('Restaurar una copia global solo se puede hacer desde la línea de comandos'));
    if (!is_file($path)) throw new UwError(t('No existe el archivo de la copia'));
    @set_time_limit(0);
    $warnings = [];
    [$tar, $dec] = bk_plain_archive($path, $password);
    $work = bk_work('global');
    try {
        $phar = bk_open_phar($tar);
        $real = realpath($tar) ?: $tar;
        $m = bk_read_json($phar, 'manifest.json');
        if (!$m || ($m['type'] ?? '') !== 'global') throw new UwError(t('Esta no es una copia global'));
        if ((int) ($m['schema'] ?? 0) > UW_SCHEMA) throw new UwError(t('La copia es de una versión más nueva de Universal Web: actualiza antes de restaurarla'));
        $entries = bk_entries($phar, $real);
        if (!isset($entries['universal.sqlite'])) throw new UwError(t('La copia no contiene la base de datos'));
        $skipped = 0;
        foreach ($entries as $rel => $size) {
            if ($rel === 'universal.sqlite' || ($withConfig && $rel === 'config.php')) $dest = "$work/$rel";
            elseif (str_starts_with($rel, 'media/')) {
                $ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
                if ($rel !== 'media/.htaccess' && !isset(MEDIA_TYPES[$ext])) { $skipped++; continue; }
                $dest = "$work/$rel";
            } else continue;
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
            if (!@copy('phar://' . $real . '/' . $rel, $dest)) throw new UwError(t('No se pudo extraer {rel}', ['rel' => $rel]));
            if ($rel !== 'media/.htaccess' && str_starts_with($rel, 'media/') && !media_content_matches($dest, strtolower(pathinfo($rel, PATHINFO_EXTENSION)))) { @unlink($dest); $skipped++; }
        }
        unset($phar);
        if ($skipped) $warnings[] = t('{n} archivo(s) de medios se omitieron por no ser válidos.', ['n' => $skipped]);

        // Comprobar que la base de datos de la copia es utilizable antes de tocar nada.
        $chk = new PDO('sqlite:' . "$work/universal.sqlite");
        $chk->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if ($chk->query('PRAGMA integrity_check')->fetchColumn() !== 'ok' || !$chk->query("SELECT 1 FROM sqlite_master WHERE name = 'sites'")->fetchColumn()) {
            throw new UwError(t('La base de datos de la copia está dañada'));
        }
        unset($chk);

        $safety = backup_create(null, $password);

        // Sustituir la base de datos (vaciando antes el WAL de la conexión actual).
        db()->exec('PRAGMA wal_checkpoint(TRUNCATE)');
        $dbPath = UW_DATA . '/universal.sqlite';
        if (!@rename("$work/universal.sqlite", $dbPath) && !@copy("$work/universal.sqlite", $dbPath)) throw new UwError(t('No se pudo sustituir la base de datos'));
        @unlink($dbPath . '-wal');
        @unlink($dbPath . '-shm');
        if ($withConfig && is_file("$work/config.php")) {
            if (!@copy("$work/config.php", UW_DATA . '/config.php')) throw new UwError(t('No se pudo sustituir config.php'));
        } elseif (!$withConfig && !empty($m['includes_config'])) {
            $warnings[] = t('No se ha restaurado config.php. Si esta copia viene de otra instalación, los secretos guardados (SMTP, Turnstile) no se podrán descifrar: usa --with-config.');
        }

        // Sustituir public/media entero (conservando su .htaccess si la copia no lo trae).
        $media = UW_PUBLIC . '/media';
        $newMedia = "$work/media";
        if (!is_dir($newMedia)) mkdir($newMedia, 0775, true);
        if (!is_file("$newMedia/.htaccess") && is_file("$media/.htaccess")) copy("$media/.htaccess", "$newMedia/.htaccess");
        $old = "$work/old-media";
        if (is_dir($media)) bk_movetree($media, $old);
        try {
            bk_movetree($newMedia, $media);
        } catch (Throwable $ex) {
            if (is_dir($old)) bk_movetree($old, $media);
            throw $ex;
        }
    } finally {
        bk_rmtree($work);
        if ($dec) bk_rmtree($dec);
    }
    return ['safety' => $safety['name'], 'warnings' => $warnings];
}

// ---------------------------------------------------------------- copia automática

/** Tras responder a una visita: si toca, hace la copia global diaria. Barato cuando está desactivada; nunca lanza. */
function backup_auto_tick(): void
{
    try {
        if (empty(platform()['auto_backup'])) return;
        if ((int) val("SELECT value FROM meta WHERE key = 'last_auto_backup'") > time() - 86400) return;
        if (PHP_SAPI !== 'cli') {
            if (!function_exists('fastcgi_finish_request')) return; // sin FPM no se puede hacer sin retrasar al visitante: usa cron
            fastcgi_finish_request();
        }
        $lock = @fopen(bk_dir() . '/.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
        try {
            if ((int) val("SELECT value FROM meta WHERE key = 'last_auto_backup'") > time() - 86400) return;
            @ignore_user_abort(true);
            // Si falla, se reintenta dentro de 1 hora y no a cada visita.
            q("INSERT OR REPLACE INTO meta (key, value) VALUES ('last_auto_backup', ?)", [(string) (time() - 86400 + 3600)]);
            $prev = [Actor::$type, Actor::$id, Actor::$name];
            Actor::set('system', null, 'copia automática');
            try {
                backup_create(null, null, true);
                q("INSERT OR REPLACE INTO meta (key, value) VALUES ('last_auto_backup', ?)", [(string) time()]);
            } finally {
                Actor::set(...$prev);
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    } catch (Throwable $ex) {
        error_log('[universal-web] copia automática: ' . $ex->getMessage());
    }
}

// ---------------------------------------------------------------- panel

function bk_human_size(int $b): string
{
    if ($b < 1024) return $b . ' B';
    if ($b < 1048576) return round($b / 1024, 1) . ' KB';
    if ($b < 1073741824) return round($b / 1048576, 1) . ' MB';
    return round($b / 1073741824, 2) . ' GB';
}

/** ¿Puede este usuario del panel operar sobre esa copia? (se decide por el NOMBRE, nunca por el sidecar) */
function bk_can_access(array $c, ?array $info): bool
{
    if (!$info) return false;
    if ($c['role'] === 'super') return true;
    return $c['role'] === 'admin' && $info['type'] === 'site' && $c['svc'] && $info['site_slug'] === $c['svc']->site['slug'];
}

function bk_require_access(array $c, string $name): array
{
    $info = backup_info($name);
    if (!$info) throw new UwError(t('Nombre de copia no válido'));
    if (!bk_can_access($c, $info)) throw new UwError(t('No tienes permiso para esta copia'));
    return $info;
}

function adm_backups(array $c): void
{
    $svc = $c['svc'];
    $super = $c['role'] === 'super';
    $all = $super ? backup_list(null) : [];
    adm_render('backups', $c, [
        'title' => 'Copias de seguridad',
        'isSuper' => $super,
        'siteBackups' => backup_list($svc->id()),
        'globalBackups' => array_values(array_filter($all, fn($b) => $b['type'] === 'global')),
        'otherBackups' => array_values(array_filter($all, fn($b) => $b['type'] === 'site' && $b['site_slug'] !== $svc->site['slug'])),
        'allSiteBackups' => array_values(array_filter($all, fn($b) => $b['type'] === 'site')),
        'platform' => platform(),
        'lastAuto' => (int) val("SELECT value FROM meta WHERE key = 'last_auto_backup'"),
        'canEncrypt' => function_exists('openssl_encrypt'),
        'uploadMax' => (string) ini_get('upload_max_filesize'),
        'cliPath' => UW_ROOT . '/bin/uw',
        'old' => adm_old('/backups/import') ?? [],
        'offsite' => [$svc->id() => offsite_view($svc->id())] + ($super ? [0 => offsite_view(0)] : []),
    ]);
}

function adm_backup_create(array $c): void
{
    $global = !empty($_POST['global']);
    if ($global && $c['role'] !== 'super') throw new UwError(t('Solo el superadmin puede crear copias globales'));
    $pw = (string) ($_POST['password'] ?? '');
    if ($pw !== '' && $pw !== (string) ($_POST['password2'] ?? '')) throw new UwError(t('La confirmación de la contraseña no coincide'));
    $meta = backup_create($global ? null : $c['svc']->id(), $pw, false);
    flash($pw !== ''
        ? t('Copia creada: {name} ({size}). Está cifrada: guarda la contraseña, no se puede recuperar.', ['name' => $meta['name'], 'size' => bk_human_size($meta['size'])])
        : t('Copia creada: {name} ({size}).', ['name' => $meta['name'], 'size' => bk_human_size($meta['size'])]));
    if (isset($meta['offsite'])) {
        if ($meta['offsite']['ok']) flash(t('También enviada fuera del servidor: {name}', ['name' => $meta['offsite']['name']]));
        else flash(t('La copia local está bien, pero NO se pudo enviar fuera del servidor: {err}', ['err' => t_msg($meta['offsite']['error'])]), 'error');
    }
    redirect(ADM . '/backups');
}

function adm_backup_download(array $c): void
{
    $name = (string) ($_GET['name'] ?? '');
    $info = backup_info($name);
    if (!bk_can_access($c, $info)) {
        adm_render('error', $c, ['title' => 'Sin permiso', 'code' => 403, 'message' => t('No tienes permiso para descargar esta copia.')]);
        return;
    }
    $path = backup_file($name);
    audit(null, 'backup.download', $name);
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: ' . ($info['encrypted'] ? 'application/octet-stream' : 'application/gzip'));
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
    exit;
}

function adm_backup_delete(array $c): void
{
    $name = (string) ($_POST['name'] ?? '');
    bk_require_access($c, $name);
    backup_delete($name);
    flash(t('Copia eliminada.'));
    redirect(ADM . '/backups');
}

function adm_backup_restore(array $c): void
{
    $svc = $c['svc'];
    if (trim((string) ($_POST['confirm'] ?? '')) !== $svc->site['slug']) throw new UwError(t('Para restaurar escribe el identificador de la web ({slug}) en la casilla de confirmación', ['slug' => $svc->site['slug']]));
    $pw = (string) ($_POST['password'] ?? '');
    $uploaded = isset($_FILES['file']) && is_array($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if ($uploaded) {
        $name = backup_store_upload($_FILES['file'], $pw, $c['role'] === 'super' ? null : $svc->site['slug']);
        if (backup_info($name)['type'] !== 'site') { backup_delete($name); throw new UwError(t('Para restaurar una web hace falta una copia de una sola web')); }
    } else {
        $name = (string) ($_POST['name'] ?? '');
        bk_require_access($c, $name);
    }
    $r = backup_restore_site($svc->id(), $name, $pw, $c['role'] === 'super');
    flash(t('Copia restaurada. Antes se guardó el estado anterior en {safety}.', ['safety' => $r['safety']]));
    foreach ($r['warnings'] as $w) flash($w, 'error');
    redirect(ADM . '/backups');
}

function adm_backup_import(array $c): void
{
    $pw = (string) ($_POST['password'] ?? '');
    $uploaded = isset($_FILES['file']) && is_array($_FILES['file']) && ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $name = $uploaded ? backup_store_upload($_FILES['file'], $pw) : (string) ($_POST['name'] ?? '');
    if ($name === '') throw new UwError(t('Elige una copia o sube un archivo'));
    $r = backup_import_site($name, $pw, (string) ($_POST['slug'] ?? ''), normalize_domains((string) ($_POST['domains'] ?? '')));
    flash(t('Web «{name}» creada desde la copia. Cámbiate a ella en el selector de webs.', ['name' => $r['site']['name']]));
    foreach ($r['warnings'] as $w) flash($w, 'error');
    redirect(ADM . '/backups');
}

function adm_backup_settings(array $c): void
{
    platform_save(['auto_backup' => !empty($_POST['auto_backup']), 'backup_keep' => max(1, min(60, (int) ($_POST['backup_keep'] ?? 7)))]);
    audit(null, 'backup.settings', !empty($_POST['auto_backup']) ? 'on' : 'off');
    flash(t('Ajustes de copias automáticas guardados.'));
    redirect(ADM . '/backups');
}
