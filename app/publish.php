<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Publicación de una web en OTRO servidor como HTML estático.
 *
 * Cada web elige su modo: «dynamic» (la sirve este servidor, por defecto) o «static» (se genera HTML con los
 * mismos temas y se sube a un destino: carpeta, SSH/rsync o FTP/FTPS, o se descarga). Así el panel y la base
 * de datos pueden quedar en una red privada (p. ej. ZeroTier) y en internet solo hay archivos.
 * La configuración del destino (rutas, claves, contraseñas) solo la toca un superadmin.
 */

const PUBLISH_SECRET_FIELDS = ['ssh_key', 'ftp_password'];

function publish_defaults(): array
{
    return [
        'mode' => 'dynamic',          // dynamic | static
        'public_url' => '',           // https://www.miweb.com  (dominio donde vive la web estática)
        'api_url' => '',              // URL pública de este servidor para el formulario (vacío = el formulario no funcionará)
        'target' => 'download',       // download | directory | rsync | ftp
        'auto_publish' => true,       // publicar solo tras cada cambio (requiere PHP-FPM o cron)
        'directory_path' => '',
        'ssh_host' => '', 'ssh_port' => 22, 'ssh_user' => '', 'ssh_path' => '', 'ssh_key' => '',
        'ftp_host' => '', 'ftp_port' => 21, 'ftp_user' => '', 'ftp_password' => '', 'ftp_path' => '/', 'ftp_tls' => true,
    ];
}

function publish_row(int $siteId): array
{
    return row('SELECT * FROM site_publish WHERE site_id = ?', [$siteId])
        ?? ['site_id' => $siteId, 'config' => '{}', 'dirty' => 0, 'last_published_at' => null, 'last_status' => '', 'last_log' => ''];
}

/** Configuración con secretos cifrados (tal cual en la BD). */
function publish_config_raw(int $siteId): array
{
    return array_replace(publish_defaults(), json_dec(publish_row($siteId)['config']));
}

function publish_config(int $siteId): array
{
    $c = publish_config_raw($siteId);
    foreach (PUBLISH_SECRET_FIELDS as $k) $c[$k] = secret_decrypt((string) $c[$k]);
    return $c;
}

/** URL pública de una web estática (sin barra final) o null si la sirve este servidor. */
function publish_public_base(int $siteId): ?string
{
    static $cache = [];
    if (!array_key_exists($siteId, $cache)) {
        $c = is_installed() ? publish_config_raw($siteId) : publish_defaults();
        $cache[$siteId] = ($c['mode'] === 'static' && $c['public_url'] !== '') ? rtrim($c['public_url'], '/') : null;
    }
    return $cache[$siteId];
}

function publish_save_config(int $siteId, array $in): array
{
    $cur = publish_config_raw($siteId);
    $c = $cur;
    $c['mode'] = in_array($in['mode'] ?? '', ['dynamic', 'static'], true) ? $in['mode'] : 'dynamic';
    $c['target'] = in_array($in['target'] ?? '', ['download', 'directory', 'rsync', 'ftp'], true) ? $in['target'] : 'download';
    $c['auto_publish'] = !empty($in['auto_publish']);
    $c['ftp_tls'] = !empty($in['ftp_tls']);
    foreach (['public_url', 'api_url'] as $k) {
        $v = rtrim(trim((string) ($in[$k] ?? '')), '/');
        if ($v !== '' && !preg_match('#^https?://[a-z0-9.-]+(:\d+)?$#i', $v)) throw new UwError("$k debe ser solo el dominio con https://, sin rutas (ej. https://www.miweb.com)");
        $c[$k] = $v;
    }
    foreach (['directory_path', 'ssh_host', 'ssh_user', 'ssh_path', 'ftp_host', 'ftp_user', 'ftp_path'] as $k) $c[$k] = trim((string) ($in[$k] ?? ''));
    $c['ssh_port'] = max(1, min(65535, (int) ($in['ssh_port'] ?? 22)));
    $c['ftp_port'] = max(1, min(65535, (int) ($in['ftp_port'] ?? 21)));
    foreach (PUBLISH_SECRET_FIELDS as $k) {
        if (!empty($in["clear_$k"])) $c[$k] = '';
        elseif (trim((string) ($in[$k] ?? '')) !== '') $c[$k] = secret_encrypt(str_replace("\r\n", "\n", trim((string) $in[$k])) . ($k === 'ssh_key' ? "\n" : ''));
    }
    if ($c['mode'] === 'static') {
        if ($c['public_url'] === '') throw new UwError('Indica la URL pública donde vivirá la web estática');
        match ($c['target']) {
            'directory' => $c['directory_path'] !== '' && str_starts_with($c['directory_path'], '/') ?: throw new UwError('Indica una ruta absoluta para la carpeta de destino'),
            'rsync' => ($c['ssh_host'] !== '' && preg_match('/^[a-z0-9_.-]+$/i', $c['ssh_host']) && preg_match('/^[a-z0-9_.-]+$/i', $c['ssh_user']) && $c['ssh_path'] !== '') ?: throw new UwError('SSH: indica servidor, usuario (solo letras, números, . _ -) y ruta remota'),
            'ftp' => ($c['ftp_host'] !== '' && $c['ftp_user'] !== '') ?: throw new UwError('FTP: indica servidor y usuario'),
            default => true,
        };
    }
    q('INSERT INTO site_publish (site_id, config) VALUES (?, ?) ON CONFLICT(site_id) DO UPDATE SET config = excluded.config', [$siteId, json_enc($c)]);
    audit($siteId, 'publish.config', $c['mode'] . '/' . $c['target']);
    return $c;
}

function publish_mark_dirty(int $siteId): void
{
    q("UPDATE site_publish SET dirty = 1 WHERE site_id = ? AND json_extract(config, '$.mode') = 'static'", [$siteId]);
    static $registered = false;
    if (!$registered && (json_dec((string) val("SELECT config FROM site_publish WHERE site_id = ?", [$siteId]))['auto_publish'] ?? false)) {
        $registered = true;
        register_shutdown_function('publish_auto_run');
    }
}

/** Publicación automática tras la respuesta (PHP-FPM) o desde la CLI. En otros casos: cron con `uw publish --dirty`. */
function publish_auto_run(): void
{
    if (PHP_SAPI !== 'cli') {
        if (!function_exists('fastcgi_finish_request')) return;
        fastcgi_finish_request();
    }
    foreach (rows("SELECT site_id FROM site_publish WHERE dirty = 1 AND json_extract(config, '$.mode') = 'static' AND json_extract(config, '$.auto_publish')") as $r) {
        $site = site_by_id((int) $r['site_id']);
        $cfg = publish_config_raw((int) $r['site_id']);
        if (!$site || $cfg['target'] === 'download') continue;
        try { publish_run(new SiteService($site)); } catch (Throwable $ex) { error_log('[universal-web publish] ' . $ex->getMessage()); }
    }
}

// ---------------------------------------------------------------- generación

/** Renderiza una ruta del front dentro de esta petición (sin enviar cabeceras). */
function publish_render(SiteService $svc, string $path): array
{
    $saved = [$_SERVER, $_GET];
    $u = parse_url((string) publish_public_base($svc->id()) ?: $svc->url('/'));
    $_SERVER['HTTP_HOST'] = ($u['host'] ?? 'localhost') . (isset($u['port']) ? ':' . $u['port'] : '');
    $_SERVER['HTTPS'] = ($u['scheme'] ?? '') === 'https' ? 'on' : '';
    $_SERVER['REQUEST_URI'] = $path;
    $_GET = [];
    Out::$capture = true;
    Out::reset();
    ob_start();
    try {
        $p = parse_url($path, PHP_URL_PATH);
        if (str_starts_with($p, '/api/')) api_handle($svc, $p, 'GET');
        else front_handle($svc, $p, 'GET');
        return ['status' => Out::$status, 'body' => (string) ob_get_clean()];
    } catch (Throwable $ex) {
        ob_end_clean();
        throw $ex;
    } finally {
        Out::$capture = false;
        [$_SERVER, $_GET] = $saved;
    }
}

/** Genera la web estática completa en una carpeta temporal. Devuelve [carpeta, nº de archivos]. */
function publish_build(SiteService $svc): array
{
    $cfg = publish_config_raw($svc->id());
    $dir = UW_DATA . '/publish/' . $svc->site['slug'] . '/build-' . date('YmdHis') . '-' . bin2hex(random_bytes(3));
    if (!mkdir($dir, 0750, true)) throw new UwError('No se puede crear la carpeta de generación');
    $files = 0;
    $put = function (string $rel, string $content) use ($dir, &$files) {
        $f = $dir . '/' . ltrim($rel, '/');
        if (!is_dir(dirname($f))) mkdir(dirname($f), 0750, true);
        file_put_contents($f, $content);
        $files++;
    };
    $copyTree = function (string $from, string $to) use ($dir, &$files) {
        if (!is_dir($from)) return;
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->isLink() || str_ends_with($f->getFilename(), '.php') || $f->getFilename() === '.htaccess') continue;
            $dest = $dir . '/' . $to . '/' . substr($f->getPathname(), strlen($from) + 1);
            if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0750, true);
            copy($f->getPathname(), $dest);
            $files++;
        }
    };
    // El formulario apunta a la API pública de este servidor y pide una marca de tiempo fresca al cargar.
    $fixForm = function (string $html) use ($cfg): string {
        // Aviso de errores del navegador: en estático va a la API de este servidor, o se quita si no hay a dónde enviarlo.
        $html = $cfg['api_url'] !== ''
            ? str_replace("navigator.sendBeacon('/api/client-errors'", 'navigator.sendBeacon(' . json_encode($cfg['api_url'] . '/api/client-errors', JSON_UNESCAPED_SLASHES) . '', $html)
            : (string) preg_replace('#<script data-uw-errors>.*?</script>\s*#s', '', $html);
        if ($cfg['api_url'] === '' || !str_contains($html, 'action="/api/contact"')) return $html;
        $api = e($cfg['api_url']);
        $html = str_replace('action="/api/contact"', 'action="' . $api . '/api/contact"', $html);
        $js = '<script>fetch(' . json_encode($cfg['api_url'] . '/api/form-token') . ').then(function(r){return r.json()}).then(function(d){'
            . 'document.querySelectorAll(\'input[name="_ts"]\').forEach(function(i){i.value=d._ts})}).catch(function(){});</script>';
        return str_replace('</body>', $js . "\n</body>", $html);
    };

    // Una pasada por idioma: el idioma principal en la raíz y los demás en /en/, /ro/…
    $mainLang = $svc->defaultLang();
    foreach ($svc->liveLangs() as $lang) {
        $svc->setLang($lang);
        $pre = $svc->langPrefix();
        $dirPre = $pre === '' ? '' : ltrim($pre, '/') . '/';
        $paths = [($pre ?: '/') => $dirPre . 'index.html'];
        foreach ($svc->pages(true) as $p) if ($p['slug'] !== 'index') $paths["$pre/{$p['slug']}"] = $dirPre . $p['slug'] . '/index.html';
        if (cookies_config($svc)['enabled']) $paths["$pre/cookies"] ??= $dirPre . 'cookies/index.html';
        $posts = $svc->posts(['public' => true, 'limit' => 100]);
        if ($posts || $lang === $mainLang) $paths["$pre/blog"] = $dirPre . 'blog/index.html';
        foreach ($posts as $p) $paths["$pre/blog/{$p['slug']}"] = $dirPre . 'blog/' . $p['slug'] . '/index.html';
        foreach ($paths as $path => $file) {
            $r = publish_render($svc, $path);
            if ($r['status'] === 200) $put($file, $fixForm($r['body']));
            // Versión Markdown junto a cada página (/servicios.md, /index.md, /en/blog/x.md)
            $svc->setLang($lang);
            $local = substr($path, strlen($pre)) ?: '/';
            if (($md = geo_markdown_for($svc, $local)) !== null) $put($dirPre . ($local === '/' ? 'index.md' : ltrim($local, '/') . '.md'), $md);
        }
        foreach (['/llms.txt', '/llms-full.txt', '/feed.xml'] as $f) {
            $r = publish_render($svc, $pre . $f);
            if ($r['status'] === 200) $put($dirPre . ltrim($f, '/'), $r['body']);
        }
    }
    $svc->setLang($mainLang);
    // Redirecciones 301: página puente (meta refresh + canonical + noindex) y _redirects (Netlify/Cloudflare Pages)
    $reds = redirect_list($svc->id());
    foreach ($reds as $r) {
        $dest = abs_url($svc, $r['to_path']);
        if (publish_public_base($svc->id()) !== null && $r['to_path'][0] === '/') $dest = publish_public_base($svc->id()) . $r['to_path'];
        $put(trim($r['from_path'], '/') . '/index.html', '<!doctype html><html><head><meta charset="utf-8"><title>' . e(__('Redirigiendo…')) . '</title>'
            . '<meta http-equiv="refresh" content="0;url=' . e($dest) . '"><link rel="canonical" href="' . e($dest) . '"><meta name="robots" content="noindex">'
            . '</head><body><p><a href="' . e($dest) . '">' . e($dest) . '</a></p></body></html>');
    }
    if ($reds) $put('_redirects', implode("\n", array_map(fn($r) => $r['from_path'] . ' ' . $r['to_path'] . ' 301', $reds)) . "\n");
    $put('404.html', $fixForm(publish_render($svc, '/__no-existe__')['body']));
    foreach (['/sitemap.xml' => 'sitemap.xml', '/robots.txt' => 'robots.txt'] as $path => $file) $put($file, publish_render($svc, $path)['body']);
    $put(indexnow_key($svc) . '.txt', indexnow_key($svc));
    $svc->setLang($mainLang);

    // Instantánea de la API JSON (para fronts headless que lean de la web pública)
    $api = ['/api/site.json', '/api/schema.json', '/api/pages.json', '/api/posts.json'];
    foreach ($svc->pages(true) as $p) $api[] = "/api/pages/{$p['slug']}.json";
    foreach ($svc->posts(['public' => true, 'limit' => 100]) as $p) $api[] = "/api/posts/{$p['slug']}.json";
    foreach ($api as $path) $put(ltrim($path, '/'), publish_render($svc, $path)['body']);

    // Recursos: temas (el corporativo carga también el CSS base), código personalizado y medios de ESTA web
    $copyTree(UW_ROOT . '/themes/default/assets', 'theme-assets/default');
    if ($svc->site['theme'] !== 'default' && theme_exists($svc->site['theme'])) {
        $copyTree(UW_ROOT . "/themes/{$svc->site['theme']}/assets", "theme-assets/{$svc->site['theme']}");
        // Temas que se apoyan en el CSS de otro (theme.json "extends", p. ej. industrial → corporate)
        $parent = (string) (json_dec((string) file_get_contents(UW_ROOT . "/themes/{$svc->site['theme']}/theme.json"))['extends'] ?? '');
        if ($parent !== '' && $parent !== 'default' && theme_exists($parent)) $copyTree(UW_ROOT . "/themes/$parent/assets", "theme-assets/$parent");
    }
    $code = site_code($svc->id());
    if ($code['css'] !== '') $put('site-assets/custom.css', $code['css']);
    if ($code['js'] !== '') $put('site-assets/custom.js', $code['js']);
    $tpl = tpl_get($svc->id());
    if ($tpl['enabled'] && $tpl['css'] !== '') $put('site-assets/template.css', $tpl['css']);
    $copyTree(UW_PUBLIC . '/media/' . $svc->site['slug'], 'media/' . $svc->site['slug']);
    $put('.uw-published', 'Universal Web · ' . $svc->site['slug'] . ' · ' . date('c') . "\n");
    return [$dir, $files];
}

// ---------------------------------------------------------------- despliegue

/** Lista de archivos de una carpeta con su hash (para subir solo lo que cambia). */
function publish_manifest(string $dir): array
{
    $m = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) if ($f->isFile()) $m[substr($f->getPathname(), strlen($dir) + 1)] = sha1_file($f->getPathname());
    ksort($m);
    return $m;
}

function publish_state_file(SiteService $svc): string
{
    return UW_DATA . '/publish/' . $svc->site['slug'] . '/last-manifest.json';
}

function publish_deploy(SiteService $svc, string $build, array $cfg, array &$log): array
{
    $new = publish_manifest($build);
    $old = json_dec((string) @file_get_contents(publish_state_file($svc)));
    $changed = array_keys(array_filter($new, fn($h, $f) => ($old[$f] ?? null) !== $h, ARRAY_FILTER_USE_BOTH));
    $removed = array_keys(array_diff_key($old, $new));

    switch ($cfg['target']) {
        case 'directory':
            $dest = rtrim($cfg['directory_path'], '/');
            if (realpath($dest) && str_starts_with(realpath($dest) . '/', realpath(UW_ROOT) . '/')) throw new UwError('La carpeta de destino no puede estar dentro de Universal Web');
            if (!is_dir($dest) && !mkdir($dest, 0755, true)) throw new UwError("No se puede crear $dest");
            // Solo se borra lo que publicamos nosotros (la carpeta debe ser exclusiva: lleva la marca .uw-published).
            if (!is_file("$dest/.uw-published") && count(scandir($dest)) > 2) throw new UwError("$dest no está vacía ni es de Universal Web: usa una carpeta exclusiva para esta web");
            foreach ($changed as $f) {
                if (!is_dir(dirname("$dest/$f"))) mkdir(dirname("$dest/$f"), 0755, true);
                copy("$build/$f", "$dest/$f");
            }
            foreach ($removed as $f) if (is_file("$dest/$f")) unlink("$dest/$f");
            $log[] = count($changed) . " archivos copiados, " . count($removed) . " borrados en $dest";
            break;

        case 'rsync':
            foreach (['rsync', 'ssh'] as $bin) if (trim((string) shell_exec('command -v ' . $bin . ' 2>/dev/null')) === '') throw new UwError("Falta el programa $bin en este servidor");
            $cfgSecret = publish_config($svc->id());
            $key = tempnam(sys_get_temp_dir(), 'uwk');
            file_put_contents($key, $cfgSecret['ssh_key']);
            chmod($key, 0600);
            $known = UW_DATA . '/publish/known_hosts';
            $ssh = sprintf('ssh -i %s -p %d -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o UserKnownHostsFile=%s -o ConnectTimeout=15',
                escapeshellarg($key), (int) $cfg['ssh_port'], escapeshellarg($known));
            $remote = $cfg['ssh_user'] . '@' . $cfg['ssh_host'] . ':' . rtrim($cfg['ssh_path'], '/') . '/';
            $cmd = 'rsync -rlz --checksum --delete --chmod=D755,F644 --exclude=.uw-keep -e ' . escapeshellarg($ssh) . ' ' . escapeshellarg($build . '/') . ' ' . escapeshellarg($remote) . ' 2>&1';
            exec($cmd, $out, $code);
            unlink($key);
            if ($code !== 0) throw new UwError('rsync falló (código ' . $code . '): ' . mb_substr(implode(' ', $out), 0, 400));
            $log[] = "rsync a {$cfg['ssh_user']}@{$cfg['ssh_host']}:{$cfg['ssh_path']} completado (" . count($changed) . ' cambios)';
            break;

        case 'ftp':
            if (!function_exists('ftp_connect')) throw new UwError('Este servidor no tiene la extensión FTP de PHP');
            $cfgSecret = publish_config($svc->id());
            $conn = $cfg['ftp_tls'] ? @ftp_ssl_connect($cfg['ftp_host'], (int) $cfg['ftp_port'], 20) : @ftp_connect($cfg['ftp_host'], (int) $cfg['ftp_port'], 20);
            if (!$conn) throw new UwError("No se pudo conectar a {$cfg['ftp_host']}:{$cfg['ftp_port']}" . ($cfg['ftp_tls'] ? ' por FTPS' : ''));
            if (!@ftp_login($conn, $cfg['ftp_user'], $cfgSecret['ftp_password'])) { ftp_close($conn); throw new UwError('FTP: usuario o contraseña incorrectos'); }
            ftp_pasv($conn, true);
            $base = '/' . trim($cfg['ftp_path'], '/');
            $mkdirs = [];
            foreach ($changed as $f) {
                $remoteDir = rtrim($base . '/' . dirname($f), '/.');
                if (!isset($mkdirs[$remoteDir])) {
                    $acc = '';
                    foreach (array_filter(explode('/', $remoteDir)) as $part) { $acc .= '/' . $part; @ftp_mkdir($conn, $acc); }
                    $mkdirs[$remoteDir] = true;
                }
                if (!@ftp_put($conn, rtrim($base, '/') . '/' . $f, "$build/$f", FTP_BINARY)) { ftp_close($conn); throw new UwError("FTP: no se pudo subir $f"); }
            }
            foreach ($removed as $f) @ftp_delete($conn, rtrim($base, '/') . '/' . $f);
            ftp_close($conn);
            $log[] = count($changed) . ' archivos subidos y ' . count($removed) . " borrados por FTP" . ($cfg['ftp_tls'] ? 'S' : '');
            break;

        default:
            throw new UwError('Este destino no se publica automáticamente: usa «Descargar web estática»');
    }
    file_put_contents(publish_state_file($svc), json_enc($new));
    return ['changed' => count($changed), 'removed' => count($removed), 'total' => count($new)];
}

/** Genera y despliega. Devuelve un resumen; el resultado queda guardado para el panel. */
function publish_run(SiteService $svc): array
{
    $cfg = publish_config_raw($svc->id());
    if ($cfg['mode'] !== 'static') throw new UwError('Esta web se sirve desde este servidor (modo dinámico): no hace falta publicarla');
    $lockFile = UW_DATA . '/publish/' . $svc->site['slug'] . '.lock';
    if (!is_dir(dirname($lockFile))) mkdir(dirname($lockFile), 0750, true);
    $lock = fopen($lockFile, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new UwError('Ya hay una publicación en curso para esta web');
    $t0 = microtime(true);
    $log = [];
    $build = null;
    try {
        q('UPDATE site_publish SET dirty = 0 WHERE site_id = ?', [$svc->id()]);
        [$build, $files] = publish_build($svc);
        $log[] = "Generados $files archivos";
        $r = publish_deploy($svc, $build, $cfg, $log);
        $status = 'ok';
    } catch (Throwable $ex) {
        $status = 'error';
        $log[] = 'ERROR: ' . ($ex instanceof UwError ? $ex->getMessage() : 'error interno');
        if (!$ex instanceof UwError) error_log('[universal-web publish] ' . $ex);
        q('UPDATE site_publish SET dirty = 1 WHERE site_id = ?', [$svc->id()]);
    } finally {
        if ($build) publish_rmtree($build);
        flock($lock, LOCK_UN);
    }
    $summary = ['status' => $status, 'seconds' => round(microtime(true) - $t0, 1), 'log' => $log, 'url' => $cfg['public_url']] + ($r ?? []);
    q('UPDATE site_publish SET last_published_at = ?, last_status = ?, last_log = ? WHERE site_id = ?', [now(), $status, implode("\n", $log), $svc->id()]);
    audit($svc->id(), 'publish.run', $status, ['files' => $r['total'] ?? 0, 'changed' => $r['changed'] ?? 0]);
    if ($status !== 'ok') throw new UwError(end($log));
    return $summary;
}

function publish_rmtree(string $dir): void
{
    if (!is_dir($dir)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $f) $f->isDir() && !$f->isLink() ? rmdir($f->getPathname()) : unlink($f->getPathname());
    rmdir($dir);
}

// ---------------------------------------------------------------- panel

function adm_publish(array $c): void
{
    $svc = $c['svc'];
    $raw = publish_config_raw($svc->id());
    $has = array_combine(PUBLISH_SECRET_FIELDS, array_map(fn($k) => $raw[$k] !== '', PUBLISH_SECRET_FIELDS));
    foreach (PUBLISH_SECRET_FIELDS as $k) $raw[$k] = '';
    if ($old = adm_old('/publish')) {
        $raw = array_replace($raw, array_intersect_key($old, $raw));
        foreach (['auto_publish', 'ftp_tls'] as $k) $raw[$k] = !empty($old[$k]);
        foreach (PUBLISH_SECRET_FIELDS as $k) $raw[$k] = '';
    }
    adm_render('publish', $c, ['title' => 'Publicación', 'p' => $raw, 'has' => $has, 'state' => publish_row($svc->id()),
        'ftpAvailable' => function_exists('ftp_connect'), 'rsyncAvailable' => trim((string) @shell_exec('command -v rsync 2>/dev/null')) !== '',
        'fpm' => function_exists('fastcgi_finish_request')]);
}

function adm_publish_save(array $c): void
{
    publish_save_config($c['svc']->id(), $_POST);
    flash(t('Configuración de publicación guardada.'));
    redirect(ADM . '/publish');
}

function adm_publish_run(array $c): void
{
    $r = publish_run($c['svc']);
    flash(t('Web publicada: {total} archivos, {changed} actualizados, {removed} borrados ({seconds} s).', ['total' => $r['total'], 'changed' => $r['changed'], 'removed' => $r['removed'], 'seconds' => $r['seconds']]));
    redirect(ADM . '/publish');
}

/** Descarga la web estática en .tar.gz (para subirla a mano a cualquier hosting). */
function adm_publish_download(array $c): void
{
    $svc = $c['svc'];
    [$build] = publish_build($svc);
    $tar = UW_DATA . '/publish/' . $svc->site['slug'] . '/web-' . date('Ymd-His') . '.tar';
    try {
        $phar = new PharData($tar);
        $phar->buildFromDirectory($build);
        $phar->compress(Phar::GZ);
        unset($phar);
        $gz = $tar . '.gz';
        audit($svc->id(), 'publish.download', basename($gz));
        header('Content-Type: application/gzip');
        header('Content-Disposition: attachment; filename="' . $svc->site['slug'] . '-estatica-' . date('Ymd-His') . '.tar.gz"');
        header('Content-Length: ' . filesize($gz));
        readfile($gz);
        @unlink($gz);
    } finally {
        @unlink($tar);
        publish_rmtree($build);
    }
    exit;
}
