<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Detección de fallos («la consola» de la web, también para la IA).
 *
 * 1) site_check: abre cada página publicada en el propio servidor (sin navegador) y busca avisos de PHP, enlaces internos
 *    rotos, recursos que no existen (imágenes, CSS, JS, audio, vídeo), imágenes sin alt o enlazadas desde otras webs,
 *    marcadores de plantilla sin rellenar, H1 duplicados o ausentes y enlaces vacíos.
 * 2) Errores reales: un script mínimo en la web pública avisa de errores de JavaScript y de archivos que no cargan en el
 *    navegador de los visitantes; el servidor anota sus propios avisos y errores de PHP. Se agrupan (mismo error = un
 *    registro con contador), sin guardar IPs ni datos personales, y se consultan en el panel («Errores») o por MCP.
 */

const ERRORS_KEEP = 300;   // registros por web (se borran los más antiguos)

function errors_record(int $siteId, string $source, string $message, string $url = '', string $detail = ''): void
{
    if (!in_array($source, ['js', 'resource', 'php'], true)) return;
    $message = mb_substr(trim(preg_replace('/\s+/', ' ', $message)), 0, 500);
    if ($message === '') return;
    $url = mb_substr(trim($url), 0, 500);
    $hash = substr(hash('sha256', "$siteId|$source|$message|" . (string) parse_url($url, PHP_URL_PATH)), 0, 32);
    try {
        q('INSERT INTO site_errors (site_id, hash, source, message, url, detail, count, first_at, last_at) VALUES (?, ?, ?, ?, ?, ?, 1, ?, ?)
            ON CONFLICT(site_id, hash) DO UPDATE SET count = count + 1, last_at = excluded.last_at, detail = excluded.detail, url = excluded.url',
            [$siteId, $hash, $source, $message, $url, mb_substr($detail, 0, 1000), now(), now()]);
        if (random_int(1, 20) === 1) {
            q('DELETE FROM site_errors WHERE site_id = ? AND id NOT IN (SELECT id FROM site_errors WHERE site_id = ? ORDER BY last_at DESC LIMIT ' . ERRORS_KEEP . ')', [$siteId, $siteId]);
        }
    } catch (Throwable) {
        // Registrar un error nunca puede romper la página.
    }
}

function errors_list(int $siteId, string $source = '', int $limit = 50): array
{
    $sql = 'SELECT source, message, url, detail, count, first_at, last_at FROM site_errors WHERE site_id = ?';
    $p = [$siteId];
    if ($source !== '') { $sql .= ' AND source = ?'; $p[] = $source; }
    return rows($sql . ' ORDER BY last_at DESC LIMIT ' . max(1, min(200, $limit)), $p);
}

function errors_clear(int $siteId): int
{
    $n = (int) val('SELECT COUNT(*) FROM site_errors WHERE site_id = ?', [$siteId]);
    q('DELETE FROM site_errors WHERE site_id = ?', [$siteId]);
    audit($siteId, 'site.errors_clear', (string) $n);
    return $n;
}

// ---------------------------------------------------------------- errores reales (navegador y PHP)

/** Script mínimo para la web pública: errores de JS y recursos que no cargan → /api/client-errors (máx. 5 por visita). */
function errors_beacon_js(): string
{
    return <<<'JS'
<script data-uw-errors>
(function () {
  var sent = 0, seen = {};
  function send(kind, msg, src, detail) {
    var key = kind + msg + src;
    if (sent >= 5 || seen[key] || !navigator.sendBeacon) return;
    seen[key] = 1; sent++;
    try { navigator.sendBeacon('/api/client-errors', JSON.stringify({ kind: kind, message: String(msg).slice(0, 400), source: String(src || '').slice(0, 400), detail: String(detail || '').slice(0, 600), page: location.pathname })); } catch (e) {}
  }
  window.addEventListener('error', function (ev) {
    var t = ev.target;
    if (t && t !== window && (t.src || t.href)) send('resource', (t.tagName || 'archivo').toLowerCase() + ' no carga', t.src || t.href, '');
    else send('js', ev.message || 'Error', (ev.filename || '') + (ev.lineno ? ':' + ev.lineno : ''), ev.error && ev.error.stack);
  }, true);
  window.addEventListener('unhandledrejection', function (ev) { var r = ev.reason || {}; send('js', 'Promesa rechazada: ' + (r.message || r), '', r.stack); });
})();
</script>
JS;
}

/** POST /api/client-errors desde el navegador del visitante. */
function errors_ingest(SiteService $svc): void
{
    if (!throttle('client-errors:' . client_ip(), 30, 600)) { out_status(204); return; }
    $raw = (string) file_get_contents('php://input', false, null, 0, 4096);
    $in = json_decode($raw, true);
    if (!is_array($in)) { out_status(204); return; }
    $kind = ($in['kind'] ?? '') === 'resource' ? 'resource' : 'js';
    $page = (string) ($in['page'] ?? '');
    $src = (string) ($in['source'] ?? '');
    // Solo errores de esta misma web: se ignoran extensiones del navegador y scripts de terceros sin información.
    if (preg_match('#^(chrome|moz|safari)-extension://#', $src) || ($kind === 'js' && ($in['message'] ?? '') === 'Script error.')) { out_status(204); return; }
    errors_record($svc->id(), $kind, (string) ($in['message'] ?? ''), $src !== '' ? $src : $page, 'Página: ' . mb_substr($page, 0, 200) . ((string) ($in['detail'] ?? '') !== '' ? "\n" . (string) $in['detail'] : ''));
    out_status(204);
}

/** Durante la respuesta del front: anota avisos de PHP (no cambia cómo se manejan). */
function errors_capture_php(int $siteId): void
{
    set_error_handler(function (int $no, string $msg, string $file, int $line) use ($siteId) {
        if (!(error_reporting() & $no)) return false;
        $rel = str_starts_with($file, UW_ROOT) ? substr($file, strlen(UW_ROOT) + 1) : basename($file);
        errors_record($siteId, 'php', $msg, (string) ($_SERVER['REQUEST_URI'] ?? ''), "$rel:$line");
        return false;
    }, E_WARNING | E_NOTICE | E_USER_WARNING | E_USER_NOTICE | E_DEPRECATED | E_USER_DEPRECATED);
}

// ---------------------------------------------------------------- revisión automática

/**
 * Revisa las páginas publicadas (y el blog) generándolas en el servidor.
 * Devuelve ['checked' => n, 'issues' => [['severity', 'where', 'message', 'fix']], 'summary' => …].
 */
function site_check(SiteService $svc, int $maxPages = 60, ?array $onlyPaths = null): array
{
    $paths = [];
    foreach ($svc->liveLangs() as $l) {
        $svc->setLang($l);
        foreach ($svc->pages(true) as $p) $paths[] = $svc->langPath($p['slug'] === 'index' ? '/' : '/' . $p['slug']);
        foreach ($svc->posts(['public' => true, 'limit' => 20]) as $p) $paths[] = $svc->langPath('/blog/' . $p['slug']);
        $paths[] = $svc->langPath('/blog');
    }
    $svc->setLang($svc->defaultLang());
    $paths = $onlyPaths ?? array_slice(array_values(array_unique($paths)), 0, $maxPages);
    $issues = [];
    $add = function (string $sev, string $where, string $msg, string $fix = '') use (&$issues) {
        $issues[] = ['severity' => $sev, 'where' => $where, 'message' => $msg, 'fix' => $fix];
    };
    $exists = [];   // caché de comprobaciones de URL interna
    foreach ($paths as $path) {
        $warnings = [];
        set_error_handler(function (int $no, string $msg, string $file, int $line) use (&$warnings) {
            $warnings[] = $msg . ' (' . (str_starts_with($file, UW_ROOT) ? substr($file, strlen(UW_ROOT) + 1) : basename($file)) . ":$line)";
            return true;
        });
        try {
            $r = site_check_render($svc, $path);
        } catch (Throwable $e) {
            restore_error_handler();
            $add('error', $path, 'La página falla al generarse: ' . $e->getMessage(), 'revisa los bloques de la página (page_get) o el código personalizado');
            continue;
        }
        restore_error_handler();
        foreach (array_unique($warnings) as $w) $add('error', $path, "Aviso de PHP: $w", 'suele ser un dato con formato inesperado en un bloque o en la plantilla');
        if ($r['status'] !== 200) { $add('error', $path, "Responde {$r['status']}", 'página no publicada o slug mal escrito'); continue; }
        $html = $r['body'];
        if (preg_match('/\{\{\{?\s*[#^\/]?[a-z0-9_.]+\s*\}?\}\}/i', $html, $m)) $add('error', $path, "Marcador de plantilla sin rellenar: {$m[0]}", 'revisa la plantilla HTML (template_get): ese marcador no existe');
        // Análisis propio (sin la extensión DOM, que no siempre existe en hosting compartido)
        $html = (string) preg_replace('#<(script|style)\b(?![^>]*\bsrc=)[^>]*>.*?</\1>#is', '', $html); // código en línea: no cuenta
        $tags = site_check_tags($html);
        $h1 = preg_match_all('#<h1[\s>]#i', $html);
        if ($h1 === 0) $add('warning', $path, 'La página no tiene H1', 'añade un bloque hero (portada) o un título');
        if ($h1 > 1) $add('warning', $path, "La página tiene $h1 H1 (debe haber uno)", 'usa un solo bloque hero por página');
        $imgsNoAlt = 0; $external = [];
        foreach ($tags as [$tag, $at]) {
            if ($tag !== 'img') continue;
            if (!array_key_exists('alt', $at)) $imgsNoAlt++;
            $src = trim($at['src'] ?? '');
            if (preg_match('#^(https?:)?//#i', $src) && !site_check_same_host($svc, $src)) $external[] = $src;
        }
        if ($imgsNoAlt) $add('warning', $path, "$imgsNoAlt imagen(es) sin atributo alt", 'pon un texto alternativo en la imagen (o media_update)');
        if ($external) $add('warning', $path, count($external) . ' imagen(es) cargadas desde otra web: ' . parse_url($external[0], PHP_URL_HOST), 'usa imágenes propias subidas con media_upload');
        // Peso: imágenes de la biblioteca demasiado grandes y página total (lo que más frena en móvil)
        $weight = 0; $heavy = [];
        foreach ($tags as [$tag, $at]) {
            if (!in_array($tag, ['img', 'source'], true)) continue;
            $p = (string) parse_url(trim($at['src'] ?? ''), PHP_URL_PATH);
            if (!str_starts_with($p, '/media/') || str_contains($p, '..') || isset($seenImg[$path][$p])) continue;
            $seenImg[$path][$p] = true;
            $bytes = is_file(UW_PUBLIC . $p) ? (int) filesize(UW_PUBLIC . $p) : 0;
            $weight += $bytes;
            if ($bytes > MEDIA_HEAVY_BYTES && !preg_match('/\.(gif|svg)$/i', $p)) $heavy[] = basename($p) . ' (' . round($bytes / 1024) . ' KB)';
        }
        if ($heavy) $add('warning', $path, 'Imágenes pesadas: ' . implode(', ', array_slice($heavy, 0, 4)) . (count($heavy) > 4 ? '…' : ''), 'vuelve a subirlas con media_upload (se optimizan solas a WebP) y cambia la URL en el bloque');
        if ($weight > 3 * 1048576) $add('notice', $path, 'La página carga ' . round($weight / 1048576, 1) . ' MB de imágenes: lenta en móvil', 'menos fotos o más ligeras'); 
        // Recursos y enlaces internos
        $targets = [];
        $attrOf = ['img' => 'src', 'source' => 'src', 'video' => 'poster', 'audio' => 'src', 'script' => 'src', 'link' => 'href', 'a' => 'href'];
        foreach ($tags as [$tag, $at, $text]) {
            if (!isset($attrOf[$tag]) || !array_key_exists($attrOf[$tag], $at)) continue;
            $u = trim($at[$attrOf[$tag]]);
            if ($tag === 'link' && !in_array(strtolower($at['rel'] ?? ''), ['stylesheet', 'preload', 'icon'], true)) continue;
            if ($tag === 'a' && $u === '' && trim($text) !== '') { $add('notice', $path, 'Enlace vacío: «' . mb_substr(trim($text), 0, 40) . '»'); continue; }
            if ($u === '' || $u[0] === '#' || preg_match('#^(mailto|tel|javascript|data):#i', $u)) continue;
            $targets[$tag . ' ' . $u] = [$tag, $u];
        }
        foreach ($targets as [$tag, $u]) {
            if (preg_match('#^(https?:)?//#i', $u) && !site_check_same_host($svc, $u)) continue; // externos: no se descargan
            $p = (string) parse_url($u, PHP_URL_PATH);
            if ($p === '' || !str_starts_with($p, '/')) continue;
            $exists[$p] ??= site_check_internal_exists($svc, $p);
            if ($exists[$p] === true && $tag === 'a' && ($red = redirect_find($svc->id(), rawurldecode($p))) && !redirect_page_exists($svc, rawurldecode($p)))
                $add('notice', $path, "Enlace a una URL redirigida: $p. Apunta directamente a {$red['to_path']}", 'cambia el enlace al destino para no pasar por la 301');
            if ($exists[$p] === false) {
                $what = $tag === 'a' ? 'Enlace roto' : 'Recurso que no existe';
                $add('error', $path, "$what: $p", $tag === 'a' ? 'corrige el enlace o crea/publica esa página (en ese idioma)' : 'sube el archivo con media_upload o corrige la URL');
            }
        }
    }
    // Plantilla HTML activa: marcadores inventados, sin menú, sin lang…
    $tpl = tpl_get($svc->id());
    if ($onlyPaths === null && $tpl['enabled'] && $tpl['layout'] !== '') {
        try { foreach (tpl_validate($tpl['layout'], $tpl['css']) as $w) $add('warning', 'Plantilla HTML', $w, 'template_get / template_update'); }
        catch (UwError $e) { $add('error', 'Plantilla HTML', $e->getMessage(), 'template_update'); }
    }
    // Errores recientes de visitantes y del servidor
    $recent = (int) val("SELECT COALESCE(SUM(count), 0) FROM site_errors WHERE site_id = ? AND last_at >= datetime('now', '-7 days')", [$svc->id()]);
    if ($onlyPaths === null && $recent > 0) $add('warning', 'Visitantes', "$recent error(es) en los últimos 7 días en navegadores o en el servidor", 'consulta errors_list');
    $errors = count(array_filter($issues, fn($i) => $i['severity'] === 'error'));
    return ['checked' => count($paths), 'issues' => $issues,
        'summary' => $issues ? count($issues) . " aviso(s), $errors grave(s), en " . count($paths) . ' página(s)' : 'Sin fallos en ' . count($paths) . ' página(s)'];
}

function site_check_render(SiteService $svc, string $path): array
{
    $saved = [$_SERVER, $_GET, $_COOKIE];
    $_SERVER['REQUEST_URI'] = $path;
    $_SERVER['HTTP_HOST'] = (string) (parse_url($svc->url('/'), PHP_URL_HOST) ?? 'localhost');
    $_GET = [];
    $_COOKIE = [];
    Out::$capture = true;
    Out::reset();
    ob_start();
    try {
        front_handle(new SiteService($svc->site), (string) parse_url($path, PHP_URL_PATH), 'GET');
        return ['status' => Out::$status, 'body' => (string) ob_get_clean()];
    } catch (Throwable $e) {
        ob_end_clean();
        throw $e;
    } finally {
        Out::$capture = false;
        [$_SERVER, $_GET, $_COOKIE] = $saved;
    }
}

function site_check_same_host(SiteService $svc, string $url): bool
{
    $h = strtolower((string) parse_url(str_starts_with($url, '//') ? "https:$url" : $url, PHP_URL_HOST));
    return $h !== '' && in_array($h, array_map('strtolower', site_domains($svc->id())), true);
}

/** ¿Existe esta ruta interna? Archivos (media, temas, código) por disco y páginas por la base de datos. */
function site_check_internal_exists(SiteService $svc, string $p): bool
{
    $p = rawurldecode($p);
    if (str_contains($p, '..')) return false;
    if (redirect_find($svc->id(), $p) && !redirect_page_exists($svc, $p)) return true; // tiene redirección 301: no es un enlace roto
    if (str_starts_with($p, '/media/') || str_starts_with($p, '/assets/')) return is_file(UW_PUBLIC . $p);
    if (preg_match('#^/theme-assets/([a-z0-9_-]+)/(.+)$#', $p, $m)) return is_file(UW_ROOT . "/themes/{$m[1]}/assets/{$m[2]}");
    if ($p === '/site-assets/custom.css' || $p === '/site-assets/custom.js') return trim(site_code($svc->id())[$p === '/site-assets/custom.css' ? 'css' : 'js']) !== '';
    if ($p === '/site-assets/template.css') return trim(tpl_get($svc->id())['css']) !== '';
    if (preg_match('#^/(sitemap\.xml|robots\.txt|feed\.xml|llms(-full)?\.txt|api/.*|admin.*|cookies)$#', $p)) return true;
    // Idioma
    $lang = $svc->defaultLang();
    if (preg_match('#^/([a-z]{2}(?:-[a-z]{2})?)(/.*)?$#', $p, $m) && in_array($m[1], $svc->langs(), true) && $m[1] !== $svc->defaultLang()) { $lang = $m[1]; $p = ($m[2] ?? '') ?: '/'; }
    $p = rtrim($p, '/') ?: '/';
    if (str_ends_with($p, '.md')) $p = substr($p, 0, -3) === '/index' ? '/' : substr($p, 0, -3);
    if ($p === '/') return (bool) (($pg = $svc->page('index', $lang)) && $pg['status'] === 'published');
    if ($p === '/blog') return true;
    if (preg_match('#^/blog/([a-z0-9-]+)$#', $p, $m)) return (bool) val("SELECT 1 FROM posts WHERE site_id = ? AND lang = ? AND slug = ? AND status = 'published'", [$svc->id(), $lang, $m[1]]);
    if (preg_match('#^/([a-z0-9-]+)$#', $p, $m)) return (bool) (($pg = $svc->page($m[1], $lang)) && $pg['status'] === 'published');
    return true; // otras rutas: no se puede saber sin pedirlas; no se marcan
}

// ---------------------------------------------------------------- panel

function adm_errors(array $c): void
{
    $svc = $c['svc'];
    $check = isset($_GET['check']) ? site_check($svc) : null;
    adm_render('errors', $c, ['title' => 'Errores', 'errors' => errors_list($svc->id(), (string) ($_GET['source'] ?? ''), 100), 'check' => $check, 'source' => (string) ($_GET['source'] ?? '')]);
}

function adm_errors_clear(array $c): void
{
    $n = errors_clear($c['svc']->id());
    flash(t('{n} error(es) borrados.', ['n' => $n]));
    redirect(ADM . '/errors');
}

/** Etiquetas relevantes con sus atributos: [[tag, [atributo => valor], texto (solo <a>)]]. */
function site_check_tags(string $html): array
{
    $out = [];
    preg_match_all('#<(img|source|video|audio|script|link|a)\b([^>]*)>#i', $html, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($m as $t) {
        $tag = strtolower($t[1][0]);
        $at = [];
        preg_match_all('#([a-z][a-z0-9:_-]*)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+)))?#i', $t[2][0], $am, PREG_SET_ORDER);
        foreach ($am as $a) {
            $v = ($a[2] ?? '') !== '' ? $a[2] : ((($a[3] ?? '') !== '') ? $a[3] : ($a[4] ?? ''));
            $at[strtolower($a[1])] = html_entity_decode($v, ENT_QUOTES | ENT_HTML5);
        }
        $text = '';
        if ($tag === 'a') {
            $end = stripos($html, '</a>', $t[0][1]);
            if ($end !== false) $text = html_entity_decode(strip_tags(substr($html, $t[0][1] + strlen($t[0][0]), $end - $t[0][1] - strlen($t[0][0]))), ENT_QUOTES | ENT_HTML5);
        }
        $out[] = [$tag, $at, $text];
    }
    return $out;
}

/**
 * Comprobación rápida tras guardar una página o entrada: fallos de esa URL (enlaces, recursos, H1, alt…) más sus
 * avisos de SEO. La IA la recibe en la misma respuesta de page_save/post_save/page_patch y corrige a la primera.
 */
function content_quick_check(SiteService $svc, string $kind, array $item): array
{
    $path = $svc->langPath($kind === 'post' ? '/blog/' . $item['slug'] : ($item['slug'] === 'index' ? '/' : '/' . $item['slug']), $item['lang']);
    $out = [];
    if (($item['status'] ?? '') !== 'published') {
        $out[] = ['severity' => 'notice', 'message' => 'Guardado como borrador: no se ve en la web hasta publicarlo (status published)'];
    } else {
        foreach (site_check($svc, 1, [$path])['issues'] as $i) $out[] = ['severity' => $i['severity'], 'message' => $i['message'], 'fix' => $i['fix']];
    }
    $seo = $svc->seoAudit();
    foreach ($seo['issues'] ?? [] as $i) {
        $w = (string) ($i['where'] ?? '');
        if (preg_match('#(Página|Entrada) ' . preg_quote($path, '#') . '$#', $w) || str_ends_with($w, $path)) {
            $out[] = ['severity' => (string) ($i['type'] ?? $i['severity'] ?? 'notice'), 'message' => (string) $i['message'], 'fix' => (string) ($i['fix'] ?? '') ?: seo_fix_hint($kind, $item['slug'], (string) $i['message'])];
        }
    }
    $svc->setLang($svc->defaultLang());
    $res = ['url' => $svc->url($path), 'ok' => !array_filter($out, fn($i) => in_array($i['severity'], ['error', 'warning'], true)),
        'issues' => array_slice($out, 0, 12)];
    $seoOnly = $out && !array_filter($out, fn($i) => !str_contains($i['fix'] ?? '', 'seo_bulk_update') && $i['severity'] !== 'notice');
    if ($seoOnly) $res['note'] = 'Lo que queda es solo SEO: se arregla con seo_bulk_update (no hace falta volver a guardar la página).';
    return $res;
}

/** «Cómo arreglarlo» para los avisos de seoAudit, que no traen herramienta: el modelo necesita saber cuál usar. */
function seo_fix_hint(string $kind, string $slug, string $message): string
{
    $ref = '{type:"' . $kind . '",slug:"' . $slug . '"';
    return match (true) {
        str_contains($message, 'meta descripción'), str_contains($message, 'extracto') => "seo_bulk_update con $ref,description:\"<120–155 caracteres>\"}",
        str_contains($message, 'Título') => "seo_bulk_update con $ref,seo_title:\"<50–60 caracteres>\"}",
        str_contains($message, 'texto alternativo') => 'page_patch update con el alt de ese bloque (o seo_autofix)',
        str_contains($message, 'Portada'), str_contains($message, 'H1') => 'deja un solo bloque hero (page_patch delete del sobrante)',
        str_contains($message, 'otra web') => 'genera una imagen propia, media_upload y page_patch update con la nueva URL',
        str_contains($message, 'portada') => 'post_save cover_image con una imagen propia (media_upload)',
        str_contains($message, 'corto') => 'amplía el texto con datos concretos (post_save content)',
        str_contains($message, 'noindex') => "seo_bulk_update con $ref,noindex:false} si debe salir en Google",
        default => 'seo_audit para el detalle',
    };
}

