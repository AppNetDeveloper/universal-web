<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Personalización por web: CSS, JavaScript, código del <head> y favicon, con historial de versiones.
 * El código lo escribe un administrador de la web y se sirve tal cual (es de confianza por diseño).
 */

const CODE_LIMITS = ['css' => 200 * 1024, 'js' => 200 * 1024, 'head_html' => 20 * 1024];
const CODE_HISTORY_KEEP = 30;

function site_code(int $siteId): array
{
    $r = row('SELECT css, js, head_html, favicon, version, updated_at FROM site_code WHERE site_id = ?', [$siteId]);
    return $r ? ['version' => (int) $r['version']] + $r : ['css' => '', 'js' => '', 'head_html' => '', 'favicon' => '', 'version' => 0, 'updated_at' => ''];
}

function site_code_save(int $siteId, array $in): array
{
    $cur = site_code($siteId);
    $new = [];
    foreach (['css', 'js', 'head_html', 'favicon'] as $k) {
        if (!array_key_exists($k, $in)) continue;
        $v = str_replace(["\r\n", "\r"], "\n", (string) $in[$k]);
        if ($k === 'favicon') {
            $v = trim($v);
            if ($v !== '' && !preg_match('#^(/media/[^\s"\'<>]+|https://[^\s"\'<>]+)$#', $v)) throw new UwError(t('El favicon debe ser una URL de /media/... o https://...'));
            if (strlen($v) > 500) throw new UwError(t('La URL del favicon es demasiado larga'));
        } else {
            if (strlen($v) > CODE_LIMITS[$k]) {
                $names = ['css' => 'El CSS', 'js' => 'El JavaScript', 'head_html' => 'El código del <head>'];
                throw new UwError(t('{name} supera el máximo de {kb} KB', ['name' => t($names[$k]), 'kb' => CODE_LIMITS[$k] / 1024]));
            }
            if (str_contains($v, "\0")) throw new UwError(t('El código contiene caracteres no válidos'));
        }
        if ($v !== $cur[$k]) $new[$k] = $v;
    }
    if (!$new) return $cur;

    $pdo = db();
    $pdo->beginTransaction();
    try {
        // También el estado inicial (vacío): así «deshacer» el primer cambio deja la web sin código propio.
        insert('site_code_history', [
            'site_id' => $siteId, 'version' => $cur['version'], 'css' => $cur['css'], 'js' => $cur['js'],
            'head_html' => $cur['head_html'], 'favicon' => $cur['favicon'], 'actor_name' => Actor::$name, 'created_at' => now(),
        ]);
        q('DELETE FROM site_code_history WHERE site_id = ? AND id NOT IN (SELECT id FROM site_code_history WHERE site_id = ? ORDER BY id DESC LIMIT ' . CODE_HISTORY_KEEP . ')', [$siteId, $siteId]);
        $version = $cur['version'] + 1;
        $data = $new + ['version' => $version, 'updated_at' => now()];
        if ($cur['version'] > 0 || row('SELECT 1 FROM site_code WHERE site_id = ?', [$siteId])) update('site_code', $data, 'site_id = ?', [$siteId]);
        else insert('site_code', $data + ['site_id' => $siteId] + array_diff_key($cur, $data));
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    audit($siteId, 'design.update', '', ['fields' => array_keys($new), 'version' => $version]);
    return site_code($siteId);
}

function site_code_history(int $siteId, int $limit = 30): array
{
    return rows('SELECT id, version, actor_name, created_at, LENGTH(css) AS css_size, LENGTH(js) AS js_size, LENGTH(head_html) AS head_size
        FROM site_code_history WHERE site_id = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)), [$siteId]);
}

function site_code_restore(int $siteId, int $historyId): array
{
    $h = row('SELECT * FROM site_code_history WHERE id = ? AND site_id = ?', [$historyId, $siteId]) ?? throw new UwError(t('Versión no encontrada'));
    $r = site_code_save($siteId, ['css' => $h['css'], 'js' => $h['js'], 'head_html' => $h['head_html'], 'favicon' => $h['favicon']]);
    audit($siteId, 'design.restore', 'v' . $h['version']);
    return $r;
}

function site_code_head(SiteService $svc): string
{
    $c = site_code($svc->id());
    $v = (int) $c['version'];
    $o = cookies_head($svc);
    if ($c['favicon'] !== '') $o .= '<link rel="icon" href="' . e($c['favicon']) . "\">\n";
    if (trim($c['css']) !== '') $o .= '<link rel="stylesheet" href="/site-assets/custom.css?v=' . $v . "\">\n";
    if (trim($c['head_html']) !== '') $o .= $c['head_html'] . "\n";
    return $o;
}

function site_code_body_end(SiteService $svc): string
{
    $c = site_code($svc->id());
    return (trim($c['js']) !== '' ? '<script src="/site-assets/custom.js?v=' . (int) $c['version'] . "\" defer></script>\n" : '') . cookies_banner($svc) . errors_beacon_js();
}

function customize_serve(SiteService $svc, string $path): void
{
    $files = ['/site-assets/custom.css' => ['css', 'text/css; charset=utf-8'], '/site-assets/custom.js' => ['js', 'text/javascript; charset=utf-8'],
        '/site-assets/template.css' => ['css', 'text/css; charset=utf-8']];
    $f = $files[$path] ?? null;
    $c = $f ? ($path === '/site-assets/template.css' ? tpl_get($svc->id()) : site_code($svc->id())) : null;
    if (!$f || trim($c[$f[0]]) === '') {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'No encontrado';
        return;
    }
    $body = $c[$f[0]];
    $etag = '"uw-' . $svc->id() . '-' . $f[0] . '-' . $c['version'] . '"';
    header('Content-Type: ' . $f[1]);
    header('X-Content-Type-Options: nosniff');
    header('ETag: ' . $etag);
    $immutable = isset($_GET['v']) && (string) $_GET['v'] === (string) $c['version'];
    header('Cache-Control: ' . ($immutable ? 'public, max-age=31536000, immutable' : 'no-cache'));
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        return;
    }
    header('Content-Length: ' . strlen($body));
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') echo $body;
}

// ---------------------------------------------------------------- panel: diseño y código

function adm_design(array $c): void
{
    $svc = $c['svc'];
    $code = site_code($svc->id());
    if ($old = adm_old('/design')) foreach (['css', 'js', 'head_html', 'favicon'] as $k) if (isset($old[$k])) $code[$k] = (string) $old[$k];
    adm_render('design', $c, [
        'title' => 'Diseño y código', 'cfg' => $code, 'history' => site_code_history($svc->id()),
        'limits' => CODE_LIMITS, 'mcpUrl' => $svc->url('/mcp'),
    ]);
}

function adm_design_save(array $c): void
{
    $in = [];
    foreach (['css', 'js', 'head_html', 'favicon'] as $k) if (isset($_POST[$k])) $in[$k] = (string) $_POST[$k];
    $before = site_code($c['svc']->id())['version'];
    $r = site_code_save($c['svc']->id(), $in);
    flash($r['version'] === $before ? t('No había cambios que guardar.') : t('Diseño guardado (versión {v})', ['v' => $r['version']]));
    redirect(ADM . '/design');
}

function adm_design_restore(array $c): void
{
    $r = site_code_restore($c['svc']->id(), adm_id());
    flash(t('Versión restaurada (nueva versión {v})', ['v' => $r['version']]));
    redirect(ADM . '/design');
}

// ---------------------------------------------------------------- panel: medios desde URL

function adm_media_upload_url(array $c): void
{
    $url = trim((string) ($_POST['url'] ?? ''));
    if (!preg_match('#^https?://#i', $url)) throw new UwError(t('Introduce una URL que empiece por http:// o https://'));
    $c['svc']->storeMediaFromUrl($url, trim((string) ($_POST['alt'] ?? '')));
    flash(t('Archivo descargado y añadido a la biblioteca.'));
    redirect(ADM . '/media');
}

// ---------------------------------------------------------------- bloques de vídeo y audio

/**
 * Analiza el origen de un vídeo/audio. Devuelve ['kind' => 'youtube'|'vimeo'|'file'|'', 'url' => ..., 'type' => mime].
 * Solo se aceptan enlaces de YouTube/Vimeo (con ID validado), rutas /media/... o URLs https://.
 */
function media_embed_info(string $src): array
{
    $src = trim($src);
    $none = ['kind' => '', 'url' => '', 'type' => ''];
    if ($src === '' || preg_match('/[\s"\'<>\\\\]/', $src)) return $none;
    if (preg_match('#^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:[^\#]*&)?v=|shorts/|embed/|live/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{11})(?![A-Za-z0-9_-])#i', $src, $m)) {
        return ['kind' => 'youtube', 'url' => 'https://www.youtube-nocookie.com/embed/' . $m[1], 'type' => ''];
    }
    if (preg_match('#^https?://(?:www\.)?vimeo\.com/(?:video/)?(\d{6,12})(?![0-9])#i', $src, $m) || preg_match('#^https://player\.vimeo\.com/video/(\d{6,12})(?![0-9])#i', $src, $m)) {
        return ['kind' => 'vimeo', 'url' => 'https://player.vimeo.com/video/' . $m[1], 'type' => ''];
    }
    if (!(str_starts_with($src, '/media/') || str_starts_with($src, 'https://')) || str_contains($src, '..')) return $none;
    $ext = strtolower(pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION));
    $type = MEDIA_TYPES[$ext] ?? '';
    if (!str_starts_with($type, 'video/') && !str_starts_with($type, 'audio/')) $type = '';
    return ['kind' => 'file', 'url' => $src, 'type' => $type];
}

function media_truthy(mixed $v): bool // alias histórico de uw_truthy()
{
    return in_array(mb_strtolower(trim((string) $v)), ['sí', 'si', 'yes', '1', 'true'], true);
}
