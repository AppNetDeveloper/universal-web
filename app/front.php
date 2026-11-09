<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Front público: temas, SEO (meta, Open Graph, JSON-LD), sitemap, robots, RSS y API JSON.
 * Un tema es una carpeta en themes/ con layout.php y plantillas; lo que no defina
 * se toma del tema "default".
 */

// ---------------------------------------------------------------- imágenes adaptadas al móvil

/**
 * Atributos de una <img> de /media/: src, srcset con las variantes que EXISTEN en disco (480w, 960w y el original),
 * sizes y width/height del original (evitan saltos de diseño). Para otras rutas (externas, con «..», sin archivo) solo src.
 * Devuelve el texto para pegar dentro de la etiqueta, con un espacio delante.
 */
function img_attrs(string $src, string $sizes = '100vw'): string
{
    static $cache = [];
    $out = ' src="' . e($src) . '"';
    $path = parse_url($src, PHP_URL_PATH);
    if (!is_string($path) || !str_starts_with($path, '/media/') || str_contains($path, "\0") || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', rawurldecode($path))) return $out;
    $file = UW_PUBLIC . rawurldecode($path);
    if (!array_key_exists($file, $cache)) {
        $info = is_file($file) ? @getimagesize($file) : false;
        $cache[$file] = $info && $info[0] > 0 && $info[1] > 0 ? [(int) $info[0], (int) $info[1]] : null;
    }
    if (!$cache[$file]) return $out;
    [$w, $h] = $cache[$file];
    $dir = substr($path, 0, (int) strrpos($path, '/'));
    $set = [];
    foreach (media_variant_paths($file) as $vw => $vf) {
        if ($vw < $w && is_file($vf)) $set[] = e($dir . '/' . rawurlencode(basename($vf))) . " {$vw}w";
    }
    if ($set) {
        $set[] = e($path) . " {$w}w";
        $out .= ' srcset="' . implode(', ', $set) . '" sizes="' . e($sizes) . '"';
    }
    return $out . ' width="' . $w . '" height="' . $h . '"';
}

// ---------------------------------------------------------------- idioma de la parte pública

/** Idioma en el que se está mostrando la web pública (lo fija front_handle según la URL). */
final class FrontLang
{
    public static string $code = 'es';
}

/** Traduce un texto fijo de los temas (el español es la clave) al idioma de la web que se está viendo. */
function __(string $es, array $vars = []): string
{
    static $dicts = [];
    $base = substr(FrontLang::$code, 0, 2);
    $txt = $es;
    if ($base !== 'es') {
        $dicts[$base] ??= is_file($f = UW_ROOT . "/app/lang/front-$base.php") ? (require $f) : [];
        $txt = $dicts[$base][$es] ?? ($dicts['en'] ??= (require UW_ROOT . '/app/lang/front-en.php'))[$es] ?? $es;
    }
    foreach ($vars as $k => $v) $txt = str_replace('{' . $k . '}', (string) $v, $txt);
    return $txt;
}

/** Nombre de cada idioma en su propio idioma (para el selector). */
const LANG_NAMES = ['es' => 'Español', 'en' => 'English', 'ro' => 'Română', 'fr' => 'Français', 'de' => 'Deutsch', 'it' => 'Italiano',
    'pt' => 'Português', 'ca' => 'Català', 'eu' => 'Euskara', 'gl' => 'Galego', 'nl' => 'Nederlands', 'pl' => 'Polski', 'uk' => 'Українська', 'ru' => 'Русский', 'ar' => 'العربية', 'zh' => '中文'];

/** Enlaces del selector de idioma para la página actual: [código, nombre, url, activo]. */
function lang_switcher(SiteService $svc, string $path): array
{
    $langs = $svc->liveLangs();
    if (count($langs) < 2) return [];
    $plain = $svc->lang === $svc->defaultLang() ? $path : (substr($path, strlen($svc->langPrefix())) ?: '/');
    $alts = $GLOBALS['uw_alternates'] ?? null;
    // En páginas y entradas, solo se enlaza la traducción si existe; si no, la portada de ese idioma.
    return array_map(fn($l) => [$l, LANG_NAMES[$l] ?? strtoupper($l), $alts !== null ? ($alts[$l] ?? $svc->langPath('/', $l)) : $svc->langPath($plain, $l), $l === $svc->lang], $langs);
}

// ---------------------------------------------------------------- iconos (SVG propios, trazo 24x24)

const UW_ICONS = [
    'truck' => '<path d="M3 6h11v10H3zM14 9h4l3 3.5V16h-7"/><circle cx="7" cy="17.5" r="1.8"/><circle cx="17.5" cy="17.5" r="1.8"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'bolt' => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.6 3.8 5.6 3.8 9s-1.3 6.4-3.8 9c-2.5-2.6-3.8-5.6-3.8-9S9.5 5.6 12 3z"/>',
    'island' => '<path d="M3 20c3-1.5 6-1.5 9 0s6 1.5 9 0M12 18v-8M12 10c-1.5-2.5-4.5-3-7-2M12 10c1.5-2.5 4.5-3 7-2M12 10c-.5-2.5 1-5 3-6"/>',
    'warehouse' => '<path d="M3 21V9l9-5 9 5v12M7 21v-8h10v8M7 17h10"/>',
    'return' => '<path d="M9 14 4 9l5-5"/><path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>',
    'shield' => '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="m9 12 2 2 4-4"/>',
    'leaf' => '<path d="M5 19c0-8 5-13 15-14-1 10-6 15-14 15"/><path d="M5 19c3-4 6-6 9-7"/>',
    'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
    'users' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.7-3.6 3.3-5.5 6.5-5.5s5.8 1.9 6.5 5.5M16 4.5a3.5 3.5 0 0 1 0 7M18 14.6c2 .7 3.2 2.5 3.5 5.4"/>',
    'star' => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
    'check' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
    'chart' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
    'box' => '<path d="M3 7.5 12 3l9 4.5v9L12 21l-9-4.5z"/><path d="M3 7.5 12 12l9-4.5M12 12v9"/>',
    'pallet' => '<path d="M4 4h16v9H4zM12 4v9M2 16h20M4 16v4M12 16v4M20 16v4"/>',
    'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    'route' => '<circle cx="6" cy="19" r="2"/><circle cx="18" cy="5" r="2"/><path d="M8 19h8.5a3.5 3.5 0 0 0 0-7h-9a3.5 3.5 0 0 1 0-7H16"/>',
    'phone' => '<path d="M5 3h4l2 5-2.5 1.5a11 11 0 0 0 6 6L16 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
    'mail' => '<path d="M3 5h18v14H3z"/><path d="m3 6 9 7 9-7"/>',
    'heart' => '<path d="M12 20s-8-4.6-8-10.5A4.5 4.5 0 0 1 12 6.6a4.5 4.5 0 0 1 8 2.9C20 15.4 12 20 12 20z"/>',
    'recycle' => '<path d="M7 19H4.5a1.5 1.5 0 0 1-1.3-2.3L5.5 13M17 19h2.5a1.5 1.5 0 0 0 1.3-2.3L18.5 13M9.5 5.5l1.2-2a1.5 1.5 0 0 1 2.6 0L16 8"/><path d="m14 19 3 0-1.5-3M5 13l3-1-1-3M16 8l-3 .5 1.5 2.8"/>',
    'award' => '<circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>',
    'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
    'calendar' => '<path d="M4 5h16v16H4zM4 10h16M8 3v4M16 3v4"/>',
    'lock' => '<path d="M5 11h14v10H5zM8 11V8a4 4 0 0 1 8 0v3"/>',
    'cpu' => '<path d="M7 7h10v10H7zM10 10h4v4h-4zM9 3v4M15 3v4M9 17v4M15 17v4M3 9h4M3 15h4M17 9h4M17 15h4"/>',
    'handshake' => '<path d="m11 17 2 2a1.4 1.4 0 0 0 2-2M14 14l2.5 2.5a1.4 1.4 0 0 0 2-2L15 11l-3 1-2.5-2.5L13 6l5 0 3 4M3 10l3-4h5M3 10l7 7a1.4 1.4 0 0 0 2-2"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'map' => '<path d="m3 6 6-3 6 3 6-3v15l-6 3-6-3-6 3z"/><path d="M9 3v15M15 6v15"/>',
];
const UW_ICON_NAMES = ['truck', 'clock', 'bolt', 'globe', 'island', 'warehouse', 'return', 'shield', 'leaf', 'sun', 'users', 'star', 'check', 'chart', 'box', 'pallet', 'pin', 'route', 'phone', 'mail', 'heart', 'recycle', 'award', 'target', 'calendar', 'lock', 'cpu', 'handshake', 'arrow', 'search', 'map'];

/** Icono SVG por nombre; si no es un nombre conocido se devuelve tal cual (escapado), p. ej. un emoji. */
function uw_icon(string $name, string $class = 'icon'): string
{
    $key = strtolower(trim($name));
    if (isset(UW_ICONS[$key])) {
        return '<svg class="' . e($class) . '" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . UW_ICONS[$key] . '</svg>';
    }
    return '<span class="' . e($class) . ' icon-emoji" aria-hidden="true">' . e($name) . '</span>';
}

/** "sí", "si", "yes", "1", "true" → true (campos de bloque de tipo texto usados como interruptor). */
function uw_truthy(mixed $v): bool
{
    return in_array(mb_strtolower(trim((string) $v)), ['sí', 'si', 'yes', '1', 'true', 'on'], true);
}

// ---------------------------------------------------------------- temas

/** «Chuleta» para quien escribe CSS personalizado (personas o IA): variables y clases reales del tema. */
function theme_css_hints(string $theme): array
{
    $meta = json_dec((string) @file_get_contents(UW_ROOT . "/themes/$theme/theme.json"));
    return ($meta['css_hints'] ?? []) + [
        'variables' => ['--accent (color principal, se cambia en Ajustes)', '--text', '--muted', '--bg', '--bg-soft', '--border', '--radius', '--font', '--max (ancho del contenido)'],
        'clases' => ['.btn (botones)', '.btn-ghost', '.site-header', '.site-nav a', '.hero', '.hero h1', '.section', '.section-head h2', '.card', '.feature', '.stats', '.cta', '.faq', '.form', '.site-footer'],
        'consejo' => 'Para cambiar el color de toda la web basta con site_update accent. Para el resto, sobrescribe variables en :root o usa estas clases. Lee primero code_get y añade tus reglas al CSS existente: code_update reemplaza el campo entero.',
    ];
}

function theme_file(string $theme, string $file): string
{
    if (!theme_exists($theme)) $theme = 'default';
    $path = UW_ROOT . "/themes/$theme/$file";
    return is_file($path) ? $path : UW_ROOT . "/themes/default/$file";
}

function render_template(string $__file, array $__vars): string
{
    extract($__vars, EXTR_SKIP);
    ob_start();
    try {
        include $__file;
        return (string) ob_get_clean();
    } catch (Throwable $ex) {
        ob_end_clean();
        throw $ex;
    }
}

/**
 * Hojas de estilo del tema en el orden en que las carga su layout: la base `default`, el padre de
 * theme.json «extends» (industrial/calido → corporate) y el tema. La plantilla HTML propia cargaba
 * SOLO la del tema y, sin la base, las rejillas (.stats, .grid) y el ancho de .container
 * desaparecían: cifras y tarjetas apiladas y el contenido de borde a borde (18-oct, prueba con ADRI).
 */
function theme_css_chain(array $site): array
{
    $theme = theme_exists((string) $site['theme']) ? (string) $site['theme'] : 'default';
    $chain = ['default'];
    if ($theme !== 'default') {
        $parent = (string) (json_dec((string) @file_get_contents(UW_ROOT . "/themes/$theme/theme.json"))['extends'] ?? '');
        if ($parent !== '' && theme_exists($parent)) $chain[] = $parent;
        $chain[] = $theme;
    }
    return array_map(fn($t) => asset_v("/theme-assets/$t/style.css"), array_values(array_unique($chain)));
}

function theme_asset(array $site, string $file): string
{
    $theme = is_file(UW_ROOT . "/themes/{$site['theme']}/assets/$file") ? $site['theme'] : 'default';
    return asset_v("/theme-assets/$theme/$file");
}

function render_blocks(SiteService $svc, array $blocks): string
{
    $html = '';
    $types = block_types();
    foreach ($blocks as $i => $b) {
        // Solo tipos conocidos y datos re-saneados: protege aunque la página venga de una copia manipulada.
        if (!is_array($b) || !is_string($b['type'] ?? null) || !isset($types[$b['type']])) continue;
        $b['data'] = sanitize_fields($types[$b['type']]['fields'], is_array($b['data'] ?? null) ? $b['data'] : []);
        $html .= render_template(theme_file($svc->site['theme'], "blocks/{$b['type']}.php"), [
            'd' => $b['data'], 'svc' => $svc, 'site' => $svc->site, 'index' => $i,
        ]);
    }
    return $html;
}

function abs_url(SiteService $svc, string $url): string
{
    if ($url === '' || preg_match('#^https?://#', $url)) return $url;
    return $svc->url(str_starts_with($url, '/') ? $url : "/$url");
}

/** Renderiza una plantilla del tema dentro de su layout. */
function render_view(SiteService $svc, string $template, array $vars, array $seo, int $status = 200): void
{
    $settings = $svc->settings();
    $base = ['svc' => $svc, 'site' => $svc->site, 's' => $settings, 'path' => $svc->langPath($seo['path'] ?? '/'), 'langs' => lang_switcher($svc, $svc->langPath($seo['path'] ?? '/'))];
    $content = render_template(theme_file($svc->site['theme'], $template), $vars + $base);
    // Plantilla HTML propia de la web (si está activa o en vista previa firmada); si no, el layout del tema.
    $html = ($tpl = tpl_active($svc))
        ? tpl_render_page($svc, $tpl, $content, seo_head($svc, $seo), $base['path'], $base['langs'])
        : render_template(theme_file($svc->site['theme'], 'layout.php'), $base + ['content' => $content, 'head' => seo_head($svc, $seo)]);
    if (!empty($GLOBALS['uw_tpl_preview']) && !tpl_get($svc->id())['enabled']) {
        $html = (string) preg_replace('#</body>#i', '<div style="position:fixed;z-index:10000;top:0;left:50%;transform:translateX(-50%);background:#111827;color:#fff;font:600 13px/1 system-ui,sans-serif;padding:9px 14px;border-radius:0 0 10px 10px">'
            . e(__('Vista previa de la plantilla HTML (aún no activa)')) . ' · <a href="?_tpl=" style="color:#93c5fd">' . e(__('Salir')) . '</a></div></body>', $html, 1);
        out_header('Cache-Control: private, no-store');
        out_header('X-Robots-Tag: noindex');
    }
    if (!empty($GLOBALS['uw_site_preview'])) {
        // Web en vista previa sin dominio: aviso visible, sin caché ni indexación.
        $html = (string) preg_replace('#</body>#i', site_preview_bar($svc->site) . '</body>', $html, 1);
        out_header('Cache-Control: private, no-store');
        out_header('X-Robots-Tag: noindex');
    }
    if (!empty($GLOBALS['uw_theme_preview'])) {
        // Vista previa de un tema: solo para quien tiene sesión, sin caché ni indexación.
        $html = str_replace('</body>', theme_preview_bar($GLOBALS['uw_theme_preview']) . '</body>', $html);
        out_header('Cache-Control: private, no-store');
        out_header('X-Robots-Tag: noindex');
    }
    out_status($status);
    out_header('Content-Type: text/html; charset=utf-8');
    if ($settings['ai_markdown']) out_header('Vary: Accept, User-Agent'); // la misma URL puede servirse en Markdown a agentes de IA
    echo $html;
}

function seo_head(SiteService $svc, array $o): string
{
    $s = $svc->settings();
    $name = $svc->site['name'];
    $title = $o['title'] ?? '';
    // «Título | Nombre», salvo que el título SEO ya lleve el nombre de la web (no repetirlo).
    $full = ($o['is_home'] ?? false) ? ($title ?: $name . ($s['tagline'] ? " — {$s['tagline']}" : ''))
        : ($title === '' ? $name : (mb_stripos($title, $name) !== false ? $title : "$title | $name"));
    $desc = str_limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($o['description'] ?? '') ?: $s['seo']['description']))), 170);
    $image = abs_url($svc, (string) (($o['image'] ?? '') ?: $s['seo']['image']));
    $url = $svc->langUrl($o['path'] ?? '/');

    $h = '<title>' . e($full) . "</title>\n";
    if ($desc !== '') $h .= '<meta name="description" content="' . e($desc) . "\">\n";
    $h .= '<link rel="canonical" href="' . e($url) . "\">\n";
    if (!empty($o['noindex'])) $h .= "<meta name=\"robots\" content=\"noindex\">\n";
    $og = ['og:type' => $o['type'] ?? 'website', 'og:title' => $full, 'og:description' => $desc, 'og:url' => $url, 'og:site_name' => $name, 'og:locale' => $s['language'], 'og:image' => $image];
    foreach ($og as $k => $v) if ($v !== '') $h .= '<meta property="' . $k . '" content="' . e($v) . "\">\n";
    $h .= '<meta name="twitter:card" content="' . ($image ? 'summary_large_image' : 'summary') . "\">\n";
    $h .= '<link rel="alternate" type="application/rss+xml" title="' . e($name) . '" href="' . e($svc->url('/feed.xml')) . "\">\n";

    // Datos estructurados (GEO): organización, WebSite, artículo, FAQPage y migas de pan en un solo grafo.
    $p = $o['path'] ?? '/';
    if (empty($o['noindex']) && ($p === '/' || preg_match('#^/(blog/)?[a-z0-9-]+$#', $p))) {
        $h .= '<link rel="alternate" type="text/markdown" title="Versión Markdown" href="' . e($svc->langUrl($p === '/' ? '/index.md' : "$p.md")) . "\">\n";
    }
    // hreflang: versiones de esta página en otros idiomas (Google y las IAs eligen la correcta)
    if (!empty($o['alternates']) && count($o['alternates']) > 1) {
        foreach ($o['alternates'] as $l => $u) $h .= '<link rel="alternate" hreflang="' . e($l) . '" href="' . e($svc->url($u)) . "\">\n";
        if (isset($o['alternates'][$svc->defaultLang()])) $h .= '<link rel="alternate" hreflang="x-default" href="' . e($svc->url($o['alternates'][$svc->defaultLang()])) . "\">\n";
    }
    $h .= '<script type="application/ld+json">' . str_replace('</', '<\/', json_enc(geo_jsonld($svc, $o))) . "</script>\n";
    return $h;
}

// ---------------------------------------------------------------- rutas del front

/** Cookie de acceso a una web privada: ligada a la web y a su contraseña actual (cambiarla expulsa a todos). */
function private_cookie_name(SiteService $svc): string
{
    return 'uw_demo_' . $svc->id();
}

function private_cookie_value(SiteService $svc): string
{
    return hash_hmac('sha256', $svc->id() . '|' . $svc->settings()['private']['password_hash'], secret_key());
}

/**
 * Modo demo: la web solo se ve con contraseña (o con sesión del panel), nunca se indexa y no expone su API.
 * Devuelve true si la petición ya se ha respondido (pantalla de acceso, robots, etc.).
 */
function private_gate(SiteService $svc, string $path, string $method): bool
{
    if (Out::$capture || !$svc->isPrivate()) return false; // la generación estática no pasa por aquí
    out_header('X-Robots-Tag: noindex, nofollow, noarchive');
    if ($path === '/robots.txt') { out_header('Content-Type: text/plain; charset=utf-8'); echo "User-agent: *\nDisallow: /\n"; return true; }
    if (in_array($path, ['/api/contact', '/api/form-token'], true)) return false;
    $ok = hash_equals(private_cookie_value($svc), (string) ($_COOKIE[private_cookie_name($svc)] ?? ''));
    if ($path === '/__acceso' && $method === 'POST') {
        $s = $svc->settings()['private'];
        $back = preg_match('#^/(?!/)[a-z0-9/._-]*$#', (string) ($_POST['back'] ?? '')) ? $_POST['back'] : '/';
        if (!throttle('demo:' . $svc->id() . ':' . client_ip(), 10, 900)) { private_page($svc, $back, __('Demasiados intentos. Espera unos minutos.')); return true; }
        if (password_verify((string) ($_POST['password'] ?? ''), (string) $s['password_hash'])) {
            setcookie(private_cookie_name($svc), private_cookie_value($svc), ['expires' => time() + 30 * 86400, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
            redirect($back);
        }
        private_page($svc, $back, __('Contraseña incorrecta.'));
        return true;
    }
    if ($ok || can_preview($svc)) return false;
    private_page($svc, $path, '');
    return true;
}

function private_page(SiteService $svc, string $back, string $error): void
{
    out_status(401);
    out_header('Content-Type: text/html; charset=utf-8');
    $s = $svc->settings();
    $accent = preg_match('/^#[0-9a-f]{6}$/i', (string) $s['accent']) ? $s['accent'] : '#4f46e5';
    echo '<!doctype html><html lang="' . e(FrontLang::$code) . '"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">'
        . '<title>' . e(__('Acceso privado')) . ' · ' . e($svc->site['name']) . '</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;font-family:system-ui,sans-serif;background:#f4f6fb;color:#0f172a}'
        . 'form{background:#fff;padding:32px;border-radius:16px;box-shadow:0 20px 50px rgba(15,23,42,.12);width:min(360px,90vw)}h1{font-size:1.25rem;margin:0 0 6px}p{color:#5b6678;margin:0 0 18px;font-size:.95rem}'
        . 'input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #d6dce6;border-radius:10px;font:inherit;margin-bottom:12px}button{width:100%;padding:12px;border:0;border-radius:10px;background:' . e($accent) . ';color:#fff;font:inherit;font-weight:600;cursor:pointer}'
        . '.err{color:#b91c1c;background:#fef2f2;padding:8px 12px;border-radius:8px}.c{margin-top:16px;font-size:.8rem;text-align:center;color:#8a94a6}.c a{color:inherit}</style></head><body>'
        . '<form method="post" action="/__acceso"><h1>' . e($svc->site['name']) . '</h1><p>' . e(__('Esta web es una demo privada. Introduce la contraseña para verla.')) . '</p>'
        . ($error !== '' ? '<p class="err">' . e($error) . '</p>' : '')
        . '<input type="password" name="password" required autofocus autocomplete="current-password" placeholder="' . e(__('Contraseña')) . '">'
        . '<input type="hidden" name="back" value="' . e($back) . '"><button>' . e(__('Entrar')) . '</button>'
        . '<div class="c">' . uw_credit() . '</div></form></body></html>';
}

function front_handle(SiteService $svc, string $path, string $method): void
{
    $GLOBALS['uw_orig_path'] = $path; // ruta pública completa (con prefijo de idioma) para las redirecciones 301
    // /en/..., /ro/...: idiomas adicionales de la web (el principal va sin prefijo)
    if (preg_match('#^/([a-z]{2}(?:-[a-z]{2})?)(/.*)?$#', $path, $m) && $m[1] !== $svc->defaultLang() && in_array($m[1], $svc->langs(), true)) {
        $svc->setLang($m[1]);
        $path = ($m[2] ?? '') === '' ? '/' : $m[2];
    }
    FrontLang::$code = $svc->lang;
    $GLOBALS['uw_theme_preview'] = theme_preview_apply($svc);
    if (private_gate($svc, $path, $method)) return;
    if ($path === '/sitemap.xml') { sitemap_xml($svc); return; }
    // Versión Markdown para agentes de IA: /pagina.md, o la misma URL con «Accept: text/markdown» o desde un bot de IA.
    if (str_ends_with($path, '.md') && preg_match('#^/(blog/)?[a-z0-9-]+\.md$#', $path)) {
        $md = geo_markdown_for($svc, $path === '/index.md' ? '/' : substr($path, 0, -3));
        if ($md === null) { not_found($svc); return; }
        geo_serve_markdown($md);
        return;
    }
    if ($method === 'GET' && !Out::$capture && geo_wants_markdown($svc) && ($md = geo_markdown_for($svc, $path)) !== null) { geo_serve_markdown($md); return; }
    if ($path === '/robots.txt') { out_header('Content-Type: text/plain; charset=utf-8'); echo geo_robots_txt($svc); return; }
    if ($path === '/llms.txt' || $path === '/llms-full.txt') {
        out_header('Content-Type: text/markdown; charset=utf-8');
        echo $path === '/llms.txt' ? geo_llms_txt($svc) : geo_llms_full($svc);
        return;
    }
    if ($path === '/feed.xml') { rss_xml($svc); return; }
    if ($path === '/' . indexnow_key($svc) . '.txt') { out_header('Content-Type: text/plain; charset=utf-8'); echo indexnow_key($svc); return; }

    $preview = isset($_GET['preview']) && can_preview($svc);

    if ($path === '/blog') {
        $tag = isset($_GET['tag']) ? (string) $_GET['tag'] : null;
        $per = Out::$capture ? 1000 : 9;
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $o = ['public' => true, 'tag' => $tag];
        $total = $svc->countPosts($o);
        $posts = $svc->posts($o + ['limit' => $per, 'offset' => ($page - 1) * $per]);
        render_view($svc, 'blog.php', ['posts' => $posts, 'tag' => $tag, 'page' => $page, 'pages' => (int) ceil($total / $per)], [
            'title' => $tag ? "Blog: $tag" : 'Blog', 'path' => '/blog', 'description' => $svc->settings()['seo']['description'],
        ]);
        return;
    }

    if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $m)) {
        $post = $svc->post($m[1]);
        if (!$post || (!$preview && $post['state'] !== 'published')) { not_found($svc); return; }
        render_view($svc, 'post.php', ['post' => $post], [
            'breadcrumb' => [[__('Inicio'), $svc->langPath('/')], ['Blog', $svc->langPath('/blog')], [$post['title'], $svc->langPath("/blog/{$post['slug']}")]],
            'alternates' => $GLOBALS['uw_alternates'] = (function () use ($svc, $post) {
                $out = [];
                foreach ($svc->translations('posts', $post['slug']) as $l => $st) if ($st === 'published' && in_array($l, $svc->langs(), true)) $out[$l] = $svc->langPath("/blog/{$post['slug']}", $l);
                return $out;
            })(),
            'title' => $post['seo']['title'] ?: $post['title'], 'description' => $post['seo']['description'] ?: $post['excerpt'],
            'image' => $post['seo']['image'] ?: $post['cover_image'], 'path' => "/blog/{$post['slug']}", 'type' => 'article',
            'noindex' => $post['seo']['noindex'] || $post['state'] !== 'published',
            'faq' => geo_faq_markdown($post['content']),
            'jsonld' => ['@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'],
                'datePublished' => date('c', strtotime((string) $post['published_at'])), 'dateModified' => date('c', strtotime($post['updated_at'])),
                'author' => ['@type' => 'Person', 'name' => $post['author'] ?: $svc->site['name']],
                'image' => abs_url($svc, $post['cover_image']), 'mainEntityOfPage' => $post['url']],
        ]);
        return;
    }

    if ($path === '/' || preg_match('#^/([a-z0-9-]+)$#', $path, $m)) {
        $slug = $path === '/' ? 'index' : $m[1];
        if ($slug === 'index' && $path !== '/') { out_header('Location: /', true, 301); return; }
        $page = $svc->page($slug);
        // Política de cookies automática si la web no tiene su propia página /cookies
        if (!$page && $slug === 'cookies' && cookies_config($svc)['enabled'] && cookies_config($svc)['policy_url'] === '') $page = cookies_policy_page($svc);
        if (!$page || (!$preview && $page['status'] !== 'published')) { not_found($svc); return; }
        $hero = current(array_filter($page['blocks'], fn($b) => $b['type'] === 'hero')) ?: null;
        render_view($svc, 'page.php', ['page' => $page], [
            'title' => $page['seo']['title'] ?: ($slug === 'index' ? '' : $page['title']), 'description' => $page['seo']['description'],
            'image' => $page['seo']['image'] ?: ($hero['data']['image'] ?? ''), 'path' => $slug === 'index' ? '/' : "/$slug",
            'noindex' => $page['seo']['noindex'] || $page['status'] !== 'published', 'is_home' => $slug === 'index',
            'faq' => geo_faq_items($page['blocks']), 'breadcrumb' => $slug === 'index' ? [] : [[__('Inicio'), $svc->langPath('/')], [$page['title'], $svc->langPath("/$slug")]],
            'alternates' => $GLOBALS['uw_alternates'] = (function () use ($svc, $slug) {
                $out = [];
                foreach ($svc->translations('pages', $slug) as $l => $st) if ($st === 'published' && in_array($l, $svc->langs(), true)) $out[$l] = $svc->langPath($slug === 'index' ? '/' : "/$slug", $l);
                return $out;
            })(),
        ]);
        return;
    }
    not_found($svc);
}

function not_found(SiteService $svc): void
{
    if (redirect_serve($svc, (string) ($GLOBALS['uw_orig_path'] ?? ''))) return;
    render_view($svc, '404.php', [], ['title' => __('Página no encontrada'), 'noindex' => true], 404);
}

function can_preview(SiteService $svc): bool
{
    // Enlace firmado de «Ver» del panel (draft_preview_href): sirve en el dominio de la web aunque la sesión del panel
    // viva en otro host (dominio exclusivo del panel). Va ligado a la web y a la ruta, y caduca en una hora.
    if (draft_preview_valid((int) $svc->id(), (string) ($GLOBALS['uw_orig_path'] ?? ''), (string) ($_GET['pt'] ?? ''))) return true;
    session_start_safe();
    $u = current_user();
    return $u && user_role($u, $svc->id()) !== null;
}

function sitemap_xml(SiteService $svc): void
{
    out_header('Content-Type: application/xml; charset=utf-8');
    $x = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
    $add = function (string $loc, string $mod) use (&$x) {
        $x .= '  <url><loc>' . e($loc) . '</loc><lastmod>' . date('Y-m-d', strtotime($mod)) . "</lastmod></url>\n";
    };
    foreach ($svc->pages(true, true) as $p) {
        if (!$svc->page($p['slug'], $p['lang'])['seo']['noindex']) $add($p['url'], $p['updated_at']);
    }
    foreach ($svc->langs() as $l) {
        $posts = $svc->posts(['public' => true, 'limit' => 100, 'lang' => $l]);
        if ($posts) $add($svc->langUrl('/blog', $l), $posts[0]['updated_at']);
        foreach ($posts as $p) if (!$p['seo']['noindex']) $add($p['url'], $p['updated_at']);
    }
    echo $x . "</urlset>\n";
}

function rss_xml(SiteService $svc): void
{
    out_header('Content-Type: application/rss+xml; charset=utf-8');
    $s = $svc->settings();
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<rss version=\"2.0\"><channel>\n";
    echo '<title>' . e($svc->site['name']) . '</title><link>' . e($svc->url('/')) . '</link><description>' . e($s['seo']['description'] ?: $s['tagline']) . "</description>\n";
    foreach ($svc->posts(['public' => true, 'limit' => 20]) as $p) {
        echo '<item><title>' . e($p['title']) . '</title><link>' . e($p['url']) . '</link><guid>' . e($p['url']) . '</guid>'
            . '<pubDate>' . date(DATE_RSS, strtotime((string) $p['published_at'])) . '</pubDate><description>' . e($p['excerpt']) . "</description></item>\n";
    }
    echo "</channel></rss>\n";
}

// ---------------------------------------------------------------- API JSON pública (solo lectura + contacto)

function api_handle(SiteService $svc, string $path, string $method): void
{
    if (private_gate($svc, $path, $method)) return;
    if (isset($_GET['lang']) && is_string($_GET['lang'])) {
        try { $svc->setLang($_GET['lang']); } catch (UwError $e) { json_response(['error' => $e->getMessage()], 400); return; }
    }
    FrontLang::$code = $svc->lang;
    out_header('Access-Control-Allow-Origin: *');
    out_header('Access-Control-Allow-Headers: Content-Type');
    if ($method === 'OPTIONS') { out_status(204); return; }

    if ($path === '/api/client-errors' && $method === 'POST') { errors_ingest($svc); return; }
    if ($path === '/api/contact' && $method === 'POST') {
        $isJson = str_contains((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'json');
        $in = $isJson ? (json_decode((string) file_get_contents('php://input'), true) ?: []) : $_POST;
        $back = preg_match('#^/(?!/)[a-z0-9/-]*$#', (string) ($in['page'] ?? '')) ? $in['page'] : '/';
        // Si la web se publica en otro servidor, se vuelve a su dominio público.
        if (function_exists('publish_public_base') && ($public = publish_public_base($svc->id())) !== null) $back = $public . $back;
        try {
            form_check_protection($svc, $in, $isJson);
            if ($isJson && !throttle('contact-json:' . $svc->id(), 30, 3600)) throw new UwError('Demasiados mensajes. Inténtalo más tarde.');
            // Casilla de privacidad (RGPD): si el formulario la mostraba, aceptarla es obligatorio.
            if (!empty($in['_privacy']) && empty($in['privacy'])) throw new UwError('Debes aceptar la política de privacidad para enviar el mensaje.');
            $id = $svc->addMessage($in);
            form_notify($svc, $id); // si el correo falla, el mensaje queda guardado con el error
            if ($isJson) json_response(['ok' => true]);
            else redirect($back . '?enviado=1#contacto');
        } catch (UwError $ex) {
            if ($isJson) json_response(['ok' => false, 'error' => $ex->getMessage()], 422);
            else redirect($back . '?error=' . rawurlencode($ex->getMessage()) . '#contacto');
        }
        return;
    }
    if ($path === '/api/form-token' && $method === 'GET') {
        // Webs estáticas: el formulario pide aquí una marca de tiempo firmada recién hecha (la del HTML generado caduca).
        $t = time();
        json_response(['_ts' => $t . '.' . hash_hmac('sha256', (string) $t, secret_key())]);
        return;
    }
    if ($method !== 'GET') { json_response(['error' => 'Método no permitido'], 405); return; }

    $pub = fn(array $p) => ['slug' => $p['slug'], 'title' => $p['title'], 'url' => $p['url'], 'updated_at' => $p['updated_at']];
    $postOut = fn(array $p, bool $full) => [
        'slug' => $p['slug'], 'title' => $p['title'], 'excerpt' => $p['excerpt'], 'cover_image' => abs_url($svc, $p['cover_image']),
        'tags' => $p['tags'], 'author' => $p['author'], 'published_at' => $p['published_at'], 'url' => $p['url'],
    ] + ($full ? ['content' => $p['content'], 'content_html' => md($p['content']), 'seo' => $p['seo']] : []);

    if ($path === '/api/site.json') {
        $info = $svc->publicInfo();
        unset($info['id']);
        $info['logo'] = abs_url($svc, (string) $info['logo']);
        $info['seo']['image'] = abs_url($svc, (string) $info['seo']['image']);
        json_response(['site' => $info, 'pages' => array_map($pub, $svc->pages(true)), 'generated_at' => date('c')]);
    } elseif ($path === '/api/schema.json') {
        json_response(['blocks' => block_types()]);
    } elseif ($path === '/api/pages.json') {
        json_response(['pages' => array_map($pub, $svc->pages(true))]);
    } elseif (preg_match('#^/api/pages/([a-z0-9-]+)\.json$#', $path, $m)) {
        $p = $svc->page($m[1]);
        if (!$p || $p['status'] !== 'published') { json_response(['error' => 'Página no encontrada'], 404); return; }
        json_response(['page' => $pub($p) + ['seo' => $p['seo'], 'blocks' => blocks_with_html($p['blocks'], $svc)]]);
    } elseif ($path === '/api/posts.json') {
        $o = ['public' => true, 'tag' => $_GET['tag'] ?? null];
        $limit = max(1, min(100, (int) ($_GET['limit'] ?? 20)));
        $offset = max(0, (int) ($_GET['offset'] ?? 0));
        json_response(['total' => $svc->countPosts($o), 'posts' => array_map(fn($p) => $postOut($p, false), $svc->posts($o + ['limit' => $limit, 'offset' => $offset]))]);
    } elseif (preg_match('#^/api/posts/([a-z0-9-]+)\.json$#', $path, $m)) {
        $p = $svc->post($m[1]);
        if (!$p || $p['state'] !== 'published') { json_response(['error' => 'Entrada no encontrada'], 404); return; }
        json_response(['post' => $postOut($p, true)]);
    } else {
        json_response(['error' => 'No encontrado', 'endpoints' => ['/api/site.json', '/api/schema.json', '/api/pages.json', '/api/pages/{slug}.json', '/api/posts.json', '/api/posts/{slug}.json', 'POST /api/contact']], 404);
    }
}

function serve_theme_asset(string $path): void
{
    if (!preg_match('#^/theme-assets/([a-z0-9_-]+)/([a-zA-Z0-9_./-]+)$#', $path, $m) || str_contains($m[2], '..')) {
        out_status(404);
        return;
    }
    $file = UW_ROOT . "/themes/{$m[1]}/assets/{$m[2]}";
    $types = ['css' => 'text/css', 'js' => 'text/javascript', 'svg' => 'image/svg+xml', 'png' => 'image/png', 'jpg' => 'image/jpeg',
        'webp' => 'image/webp', 'woff2' => 'font/woff2', 'ico' => 'image/x-icon'];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!is_file($file) || !isset($types[$ext])) { out_status(404); return; }
    out_header('Content-Type: ' . $types[$ext]);
    out_header('Cache-Control: public, max-age=604800');
    readfile($file);
}


// ---------------------------------------------------------------- vista previa de una web SIN dominio (18-oct)
// Una web nueva no se veía hasta que alguien daba de alta su dominio (DNS, alias, certificado): la IA la montaba a
// ciegas y el cliente no podía revisarla. Un enlace firmado en el host del panel deja una cookie firmada y, desde
// ahí, ese navegador ve esa web con todos sus enlaces. El dominio se aplica después, cuando el cliente la aprueba.

const SITE_PREVIEW_TTL = 7 * 86400;

function site_preview_sig(int $siteId, int $exp): string
{
    return substr(hash_hmac('sha256', "ver|$siteId|$exp", secret_key()), 0, 32);
}

/** Dirección del panel de administración (para decírsela a la persona: «entra en …»). */
function panel_url(): string
{
    $host = (string) (platform()['admin_host'] ?? '') ?: request_host();
    return (is_https() ? 'https://' : 'http://') . $host . request_port_suffix() . '/admin';
}

/** Enlace de vista previa (7 días) en el host desde el que se pide: el del panel o el de la petición MCP. */
function site_preview_link(array $site): string
{
    $exp = time() + SITE_PREVIEW_TTL;
    $host = (string) (platform()['admin_host'] ?? '') ?: request_host();
    return (is_https() ? 'https://' : 'http://') . $host . request_port_suffix() . '/_ver/' . rawurlencode((string) $site['slug'])
        . '?t=' . $exp . '.' . site_preview_sig((int) $site['id'], $exp);
}

const DRAFT_PREVIEW_TTL = 3600;

function draft_preview_sig(int $siteId, string $path, int $exp, ?string $key = null): string
{
    return substr(hash_hmac('sha256', "pv|$siteId|$path|$exp", $key ?? secret_key()), 0, 32);
}

/** Ruta sin barra final (como la deja bootstrap), para firmar y comprobar lo mismo. */
function draft_preview_path(string $path): string
{
    $path = (string) (parse_url($path, PHP_URL_PATH) ?: '/');
    return $path !== '/' ? rtrim($path, '/') : '/';
}

/**
 * Enlace «Ver» de un borrador desde el panel. `?preview=1` solo valía con la sesión del panel; si el panel tiene
 * dominio propio (admin_host) esa sesión no existe en el dominio de la web y daba 404. Se añade `pt` (firma de
 * web + ruta, 1 hora) para que el borrador se vea en el dominio de la web sin sesión.
 */
function draft_preview_href(array $site, string $url, ?string $key = null): string
{
    $exp = time() + DRAFT_PREVIEW_TTL;
    return $url . (str_contains($url, '?') ? '&' : '?') . 'preview=1&pt=' . $exp . '.' . draft_preview_sig((int) $site['id'], draft_preview_path($url), $exp, $key);
}

function draft_preview_valid(int $siteId, string $path, string $token, ?string $key = null): bool
{
    if ($path === '' || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $token, $m) || (int) $m[1] < time()) return false;
    return hash_equals(draft_preview_sig($siteId, draft_preview_path($path), (int) $m[1], $key), $m[2]);
}

/** /_ver/<slug>?t=… (entra) · /_ver/salir (sale). Devuelve true si atendió la petición. */
function site_preview_handle(string $path): bool
{
    if (!str_starts_with($path, '/_ver/')) return false;
    $slug = substr($path, 6);
    out_header('Cache-Control: private, no-store');
    out_header('X-Robots-Tag: noindex');
    $secure = is_https() ? '; Secure' : '';
    if ($slug === 'salir') {
        out_header('Set-Cookie: uw_ver=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax' . $secure, false);
        redirect('/');
    }
    $site = site_by_slug($slug);
    $ok = $site && preg_match('/^(\d{10})\.([a-f0-9]{32})$/', (string) ($_GET['t'] ?? ''), $m) && (int) $m[1] >= time()
        && hash_equals(site_preview_sig((int) $site['id'], (int) $m[1]), $m[2]);
    if (!$ok) { http_response_code(403); echo e(__('Enlace de vista previa no válido o caducado. Pide uno nuevo.')); return true; }
    $val = $site['id'] . '.' . $m[1] . '.' . $m[2];
    out_header('Set-Cookie: uw_ver=' . $val . '; Path=/; Max-Age=' . ((int) $m[1] - time()) . '; HttpOnly; SameSite=Lax' . $secure, false);
    redirect('/');
}

/** Web en vista previa para este navegador (cookie firmada y vigente) o null. */
function site_preview_from_cookie(): ?array
{
    if (!preg_match('/^(\d+)\.(\d{10})\.([a-f0-9]{32})$/', (string) ($_COOKIE['uw_ver'] ?? ''), $m)) return null;
    if ((int) $m[2] < time() || !hash_equals(site_preview_sig((int) $m[1], (int) $m[2]), $m[3])) return null;
    $site = site_by_id((int) $m[1]);
    if ($site) $GLOBALS['uw_site_preview'] = (int) $site['id'];
    return $site;
}

function site_preview_bar(array $site): string
{
    return '<div style="position:fixed;z-index:10000;bottom:12px;left:50%;transform:translateX(-50%);background:#111827;color:#fff;'
        . 'font:600 13px/1 system-ui,sans-serif;padding:9px 14px;border-radius:10px;box-shadow:0 4px 14px rgba(0,0,0,.3)">'
        . e(__('Vista previa de «{name}» (aún sin dominio)', ['name' => $site['name']]))
        . ' · <a href="/_ver/salir" style="color:#93c5fd">' . e(__('Salir')) . '</a></div>';
}

