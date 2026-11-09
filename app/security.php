<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Endurecimiento: cabeceras, lista de IPs permitidas para /admin y /mcp, aislamiento del panel
 * frente al JavaScript de la web pública (mismo origen) y caducidad de sesión.
 *
 * Amenaza principal: el JS personalizado de una web pública, ejecutado en el navegador de un admin
 * con sesión, podría hacer fetch('/admin/...') (mismo origen, con cookies), leer tokens CSRF, o abrir
 * /admin en una ventana/iframe y manejarla. Defensas:
 *  - Fetch Metadata: el panel solo acepta navegaciones de documento (nunca fetch/XHR/iframe/script).
 *  - COOP same-origin: una ventana abierta con window.open() pierde la referencia al opener y viceversa.
 *  - X-Frame-Options DENY + frame-ancestors 'none': no se puede incrustar.
 *  - CORP same-origin + CSP connect-src 'self' + form-action 'self' en el propio panel.
 */

function security_headers(string $path): void
{
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
    if (is_https()) header('Strict-Transport-Security: max-age=31536000');

    if ($path === '/mcp') {
        header('Cache-Control: no-store');
        header('X-Frame-Options: DENY');
        return;
    }
    if ($path === '/admin' || str_starts_with($path, '/admin/')) {
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: https:; media-src 'self' https:; font-src 'self' data:; connect-src 'self'; "
            . "frame-ancestors 'none'; form-action 'self'; base-uri 'none'; object-src 'none'");
        return;
    }
    // Web pública: se puede enmarcar solo desde el mismo origen. Sin CSP (el admin puede añadir su propio JS).
    header('X-Frame-Options: SAMEORIGIN');
}

/** Devuelve false (y ya ha respondido) si la petición a /admin o /mcp no está permitida. */
function security_gate(string $path): bool
{
    $isMcp = $path === '/mcp';

    // (a) Lista de IPs/redes permitidas (p. ej. solo la red ZeroTier). Vacía = sin restricción.
    $allow = platform()['admin_allowlist'];
    if ($allow && !ip_in_list(client_ip(), $allow)) return security_deny($isMcp, 'Acceso restringido a la red autorizada.');

    // (b) Fetch Metadata (solo navegadores modernos; sin cabeceras = curl/clientes antiguos -> se permite).
    $site = strtolower((string) request_header('Sec-Fetch-Site'));
    $mode = strtolower((string) request_header('Sec-Fetch-Mode'));
    $dest = strtolower((string) request_header('Sec-Fetch-Dest'));

    if ($isMcp) {
        // El MCP lo usan clientes que no son navegadores, con token Bearer (sin cookies), así que no hay
        // riesgo CSRF y hay clientes web legítimos de otro origen: no se restringe por Fetch Metadata.
        // Solo se impide que se cargue como recurso embebido (iframe/script/imagen...).
        if ($dest !== '' && in_array($dest, ['iframe', 'frame', 'embed', 'object', 'script', 'style', 'image'], true)) {
            return security_deny(true, 'No permitido.');
        }
        return true;
    }

    // Panel: solo navegaciones de documento (clic en enlace, envío de formulario, URL escrita).
    if ($site !== '' && $site !== 'same-origin' && $site !== 'none') {
        // cross-site / same-site (otro subdominio, p. ej. otra web con JS propio): solo se tolera
        // un clic de enlace GET (p. ej. desde un correo), nunca fetch/XHR, formularios ni iframes.
        $ok = $mode === 'navigate' && $dest === 'document' && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET';
        if (!$ok) return security_deny(false, 'Petición bloqueada por seguridad (origen no permitido).');
    }
    if ($mode !== '' && $mode !== 'navigate') return security_deny(false, 'Petición bloqueada por seguridad (el panel no admite peticiones de scripts).');
    if ($dest !== '' && $dest !== 'document') return security_deny(false, 'Petición bloqueada por seguridad (el panel no se puede incrustar).');
    return true;
}

function security_deny(bool $json, string $msg): bool
{
    http_response_code(403);
    header('Cache-Control: no-store');
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'forbidden']);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><meta name="robots" content="noindex"><title>403</title>'
            . '<body style="font:16px system-ui;max-width:32rem;margin:15vh auto;padding:0 1rem"><h1>403</h1><p>' . e($msg) . '</p>';
    }
    return false;
}

/** Llamar justo después de session_start_safe() en el panel: caducidad por inactividad, vínculo al navegador y rotación del id. */
function security_session_check(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return;
    $now = time();
    $ua = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));

    if (!empty($_SESSION['uid'])) {
        $idle = max(15, (int) platform()['session_idle_minutes']) * 60;
        $expired = isset($_SESSION['last_seen']) && $now - (int) $_SESSION['last_seen'] > $idle;
        $hijack = isset($_SESSION['ua']) && !hash_equals((string) $_SESSION['ua'], $ua);
        if ($expired || $hijack) {
            $_SESSION = [];
            session_regenerate_id(true);
            flash($expired ? t('Sesión caducada por inactividad') : t('Sesión cerrada: el navegador ha cambiado. Entra de nuevo.'), 'error');
            redirect(ADM . '/login');
        }
        if ($now - (int) ($_SESSION['regen_at'] ?? 0) > 1800) {
            session_regenerate_id(true);
            $_SESSION['regen_at'] = $now;
        }
    }
    $_SESSION['last_seen'] = $now;
    $_SESSION['ua'] ??= $ua;
    $_SESSION['regen_at'] ??= $now;
}

// ---------------------------------------------------------------- panel: Seguridad y red (superadmin)

function adm_security(array $c): void
{
    $p = platform();
    adm_render('security', $c, [
        'title' => 'Seguridad y red', 'p' => $p,
        'ip' => client_ip(), 'remote' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''), 'viaProxy' => from_trusted_proxy(),
        'https' => is_https(), 'old' => adm_old('/security'),
    ]);
}

function adm_security_save(array $c): void
{
    $parse = function (string $field, string $label): array {
        $out = [];
        foreach (preg_split('/[\r\n,;]+/', (string) ($_POST[$field] ?? '')) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (!valid_cidr($line)) throw new UwError(t('{label}: «{line}» no es una IP ni una red CIDR válida (ej. 10.147.17.0/24).', ['label' => t($label), 'line' => mb_substr($line, 0, 60)]));
            $out[] = $line;
        }
        return array_values(array_unique($out));
    };
    $allow = $parse('admin_allowlist', 'IPs permitidas');
    $proxies = $parse('trusted_proxies', 'Proxies de confianza');
    $outbound = $parse('outbound_allowlist', 'Destinos privados permitidos');
    $adminHost = strtolower(trim((string) ($_POST['admin_host'] ?? '')));
    if ($adminHost !== '') {
        $adminHost = normalize_domains($adminHost)[0] ?? '';
        if (val('SELECT 1 FROM site_domains WHERE domain = ?', [$adminHost])) throw new UwError(t('{host} ya es el dominio de una web: el panel necesita un dominio exclusivo (p. ej. panel.tudominio.com).', ['host' => $adminHost]));
        if (request_host() !== $adminHost) throw new UwError(t('Abre primero el panel desde https://{host}/admin (con el DNS ya apuntando aquí) y guarda desde allí: si no, te quedarías fuera.', ['host' => $adminHost]));
    }
    $idle = (int) ($_POST['session_idle_minutes'] ?? 480);
    if ($idle < 15 || $idle > 10080) throw new UwError(t('La caducidad de sesión debe estar entre 15 y 10080 minutos.'));

    // Los proxies de confianza cambian client_ip(): se evalúa con la nueva configuración antes de guardar.
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $ip = $remote;
    if (ip_in_list($remote, $proxies)) {
        foreach (array_reverse(array_filter(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))))) as $hop) {
            if (!filter_var($hop, FILTER_VALIDATE_IP)) break;
            if (!ip_in_list($hop, $proxies)) { $ip = $hop; break; }
        }
    }
    if ($allow && !ip_in_list($ip, $allow)) {
        throw new UwError(t('Tu IP actual ({ip}) no está en la lista: te quedarías fuera. Añádela primero.', ['ip' => $ip]));
    }
    // Antes de obligar al 2FA, el superadmin que lo activa debe tenerlo (si no, se quedaría bloqueado en «Mi cuenta»).
    $require2fa = !empty($_POST['require_2fa']);
    if ($require2fa && !twofa_enabled($c['user'])) throw new UwError(t('Activa primero tu propia verificación en dos pasos (Mi cuenta) antes de hacerla obligatoria para todos.'));
    platform_save(['admin_allowlist' => $allow, 'trusted_proxies' => $proxies, 'session_idle_minutes' => $idle, 'require_2fa' => $require2fa,
        'admin_host' => $adminHost, 'outbound_allowlist' => $outbound]);
    audit(null, 'security.update', ($allow ? count($allow) . ' IPs permitidas' : 'sin restricción de IP') . ', ' . count($proxies) . ' proxies');
    flash(t('Ajustes de seguridad guardados.'));
    redirect(ADM . '/security');
}
