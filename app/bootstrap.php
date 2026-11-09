<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

define('UW_ROOT', dirname(__DIR__));
define('UW_PUBLIC', UW_ROOT . '/public');
define('UW_DATA', getenv('UW_DATA') ?: UW_ROOT . '/data');

require UW_ROOT . '/app/core.php';
require UW_ROOT . '/app/i18n.php';
require UW_ROOT . '/app/content.php';
require UW_ROOT . '/app/front.php';
require UW_ROOT . '/app/mail.php';
require UW_ROOT . '/app/security.php';
require UW_ROOT . '/app/customize.php';
require UW_ROOT . '/app/sitetemplate.php';
require UW_ROOT . '/app/backup.php';
require UW_ROOT . '/app/offsite.php';
require UW_ROOT . '/app/twofa.php';
require UW_ROOT . '/app/recovery.php';
require UW_ROOT . '/app/publish.php';
require UW_ROOT . '/app/geo.php';
require UW_ROOT . '/app/sitecheck.php';
require UW_ROOT . '/app/history.php';
require UW_ROOT . '/app/seo.php';
require UW_ROOT . '/app/domains.php';
require UW_ROOT . '/app/tours.php';
require UW_ROOT . '/app/cookies.php';
require UW_ROOT . '/app/themes.php';
require UW_ROOT . '/app/mcp.php';
require UW_ROOT . '/app/admin.php';
require UW_ROOT . '/app/install.php';

date_default_timezone_set((string) config('timezone', 'Europe/Madrid'));
mb_internal_encoding('UTF-8');
if (is_installed()) ensure_schema();

/** Usuario del panel con sesión iniciada (requiere session_start_safe()). */
function current_user(): ?array
{
    static $cache = [];
    $id = (int) ($_SESSION['uid'] ?? 0);
    if (!$id) return null;
    return $cache[$id] ??= user_by_id($id);
}

/**
 * Caddy (on-demand TLS) pregunta aquí antes de emitir un certificado: 200 si el dominio
 * pertenece a alguna web. Solo responde a peticiones locales.
 */
function domain_check(): void
{
    // Local, o el VPS frontal (proxy de confianza, p. ej. por ZeroTier) en modo «edge».
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $local = in_array($remote, ['127.0.0.1', '::1'], true) || from_trusted_proxy();
    $domain = strtolower((string) ($_GET['domain'] ?? ''));
    $known = $local && (val('SELECT 1 FROM site_domains WHERE domain IN (?, ?)', [$domain, preg_replace('/^www\./', '', $domain)]) !== null);
    http_response_code($known ? 200 : 404);
}

/** Punto de entrada único: decide si la petición va al instalador, MCP, panel, API o front. */
function uw_handle_request(): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $path = rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
    if ($path !== '/' && str_ends_with($path, '/')) $path = rtrim($path, '/');

    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');

    try {
        if (!is_installed()) {
            install_handle($path, $method);
            return;
        }
        security_headers($path);
        if ($path === '/install/conectar-adri') { install_connect_handle($method); return; }
        if ($path === '/mcp' || $path === '/admin' || str_starts_with($path, '/admin/')) {
            // Con un dominio exclusivo para el panel, en los dominios de las webs /admin y /mcp no existen:
            // el JavaScript personalizado de una web nunca comparte origen con el panel.
            $adminHost = (string) platform()['admin_host'];
            if ($adminHost !== '' && request_host() !== $adminHost) { http_response_code(404); echo 'No encontrado.'; return; }
            // Lista de IPs/redes permitidas (p. ej. solo la red ZeroTier) y aislamiento del panel.
            if (!security_gate($path)) return;
        }
        if ($path === '/mcp') { mcp_handle($method); return; }
        if ($path === '/_uw/domain-check') { domain_check(); return; }
        if ($path === '/admin' || str_starts_with($path, '/admin/')) { admin_handle(substr($path, 6) ?: '/', $method); return; }
        if (str_starts_with($path, '/theme-assets/')) { serve_theme_asset($path); return; }
        if (site_preview_handle($path)) return;

        $site = site_preview_from_cookie() ?? site_for_public_host(request_host());
        if (!$site) { unknown_host_page($path); return; }
        $svc = new SiteService($site);
        errors_capture_php((int) $site['id']); // avisos de PHP de esta web → panel «Errores» y errors_list
        if (str_starts_with($path, '/api/')) { api_handle($svc, $path, $method); return; }
        if (str_starts_with($path, '/site-assets/')) { customize_serve($svc, $path); return; }
        front_handle($svc, $path, $method);
        backup_auto_tick(); // copia automática diaria (si está activada), tras responder
        offsite_auto_tick(); // copias programadas por web con envío fuera del servidor
        media_variants_tick(); // tamaños para móvil de las fotos subidas antes de existir srcset (unas pocas por visita)
    } catch (Throwable $ex) {
        error_log('[universal-web] ' . $ex);
        if (isset($svc)) errors_record($svc->id(), 'php', 'Error interno: ' . $ex->getMessage(), (string) ($_SERVER['REQUEST_URI'] ?? ''), str_replace(UW_ROOT . '/', '', $ex->getFile()) . ':' . $ex->getLine());
        if (!headers_sent()) http_response_code(500);
        echo config('debug') ? '<pre>' . e((string) $ex) . '</pre>' : 'Error interno del servidor.';
    }
}

/** Dominio que llega al servidor pero no es de ninguna web: página neutra (sin datos de otras webs). */
function unknown_host_page(string $path): void
{
    $adminHost = (string) platform()['admin_host'];
    if ($adminHost !== '' && request_host() === $adminHost && $path === '/') { redirect('/admin/'); return; }
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>' . e(request_host()) . '</title>'
        . '<style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.6 system-ui,sans-serif;background:#f6f7f9;color:#1c1f26}'
        . 'main{max-width:34rem;padding:2rem 1.5rem}h1{font-size:1.4rem;margin:0 0 .5rem}p{margin:.4rem 0;color:#4b5563}'
        . '@media (prefers-color-scheme:dark){body{background:#0f1115;color:#e6e8ee}p{color:#9aa3b2}}</style></head><body><main>'
        . '<h1>' . e(request_host()) . '</h1><p>Esta web todavía no está disponible.</p><p lang="en">This website is not available yet.</p>'
        . '</main></body></html>';
}
