<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Panel de administración (/admin). Todo el contenido pasa por SiteService de la web actual.
 * Rutas: se recibe el subpath ('/', '/login', '/pages/edit'...). POST-redirect-GET siempre.
 */

const ADM = '/admin';

function adm_routes(): array
{
    // "MÉTODO ruta" => [función, rol mínimo, ¿necesita web?]
    return [
        'GET /' => ['adm_dashboard', 'editor', true],
        'GET /pages' => ['adm_pages', 'editor', true],
        'GET /pages/edit' => ['adm_page_edit', 'editor', true],
        'POST /pages/save' => ['adm_page_save', 'editor', true],
        'POST /pages/delete' => ['adm_page_delete', 'editor', true],
        'GET /posts' => ['adm_posts', 'editor', true],
        'GET /posts/edit' => ['adm_post_edit', 'editor', true],
        'POST /posts/save' => ['adm_post_save', 'editor', true],
        'POST /posts/delete' => ['adm_post_delete', 'editor', true],
        'POST /pages/restore' => ['adm_page_restore', 'editor', true],
        'POST /posts/restore' => ['adm_post_restore', 'editor', true],
        'GET /trash' => ['adm_trash', 'editor', true],
        'POST /trash/restore' => ['adm_trash_restore', 'editor', true],
        'GET /media' => ['adm_media', 'editor', true],
        'POST /media/upload' => ['adm_media_upload', 'editor', true],
        'POST /media/delete' => ['adm_media_delete', 'editor', true],
        'GET /messages' => ['adm_messages', 'editor', true],
        'POST /messages/mark' => ['adm_message_mark', 'editor', true],
        'POST /messages/delete' => ['adm_message_delete', 'editor', true],
        'POST /messages/resend' => ['adm_message_resend', 'editor', true],
        'GET /seo' => ['adm_seo', 'editor', true],
        'POST /seo/redirects' => ['adm_redirect_save', 'admin', true],
        'POST /seo/redirects/delete' => ['adm_redirect_delete', 'admin', true],
        'GET /settings' => ['adm_settings', 'admin', true],
        'POST /settings' => ['adm_settings_save', 'admin', true],
        'GET /languages' => ['adm_languages', 'admin', true],
        'POST /languages' => ['adm_languages_save', 'admin', true],
        'POST /languages/texts' => ['adm_languages_texts', 'admin', true],
        'GET /cookies' => ['adm_cookies', 'admin', true],
        'POST /cookies' => ['adm_cookies_save', 'admin', true],
        'GET /template' => ['adm_template', 'admin', true],
        'POST /template' => ['adm_template_save', 'admin', true],
        'POST /template/restore' => ['adm_template_restore', 'admin', true],
        'GET /errors' => ['adm_errors', 'editor', true],
        'POST /errors/clear' => ['adm_errors_clear', 'admin', true],
        'GET /themes' => ['adm_themes', 'admin', true],
        'POST /themes/apply' => ['adm_theme_apply', 'admin', true],
        'GET /themes/download' => ['adm_theme_download', 'admin', true],
        'POST /themes/upload' => ['adm_theme_upload', 'super', true],
        'POST /themes/delete' => ['adm_theme_delete', 'super', true],
        'GET /forms' => ['adm_forms', 'admin', true],
        'POST /forms' => ['adm_forms_save', 'admin', true],
        'POST /forms/test' => ['adm_forms_test', 'admin', true],
        'POST /forms/webhook-test' => ['adm_forms_webhook_test', 'admin', true],
        'POST /media/upload-url' => ['adm_media_upload_url', 'editor', true],
        'GET /tokens' => ['adm_tokens', 'editor', true],
        'POST /tokens/create' => ['adm_token_create', 'editor', true],
        'POST /tokens/revoke' => ['adm_token_revoke', 'editor', true],
        'GET /design' => ['adm_design', 'admin', true],
        'POST /design' => ['adm_design_save', 'admin', true],
        'POST /design/restore' => ['adm_design_restore', 'admin', true],
        'GET /backups' => ['adm_backups', 'admin', true],
        'POST /backups/create' => ['adm_backup_create', 'admin', true],
        'GET /backups/download' => ['adm_backup_download', 'admin', true],
        'POST /backups/delete' => ['adm_backup_delete', 'admin', true],
        'POST /backups/restore' => ['adm_backup_restore', 'admin', true],
        'POST /backups/import' => ['adm_backup_import', 'super', true],
        'POST /backups/settings' => ['adm_backup_settings', 'super', true],
        'POST /backups/offsite' => ['adm_backup_offsite_save', 'admin', true],
        'POST /backups/offsite/test' => ['adm_backup_offsite_test', 'admin', true],
        'POST /backups/offsite/send' => ['adm_backup_offsite_send', 'admin', true],
        'GET /publish' => ['adm_publish', 'admin', true],
        'POST /publish' => ['adm_publish_save', 'super', true],
        'POST /publish/run' => ['adm_publish_run', 'admin', true],
        'GET /publish/download' => ['adm_publish_download', 'admin', true],
        'GET /mail' => ['adm_mail', 'super', false],
        'POST /mail' => ['adm_mail_save', 'super', false],
        'POST /mail/test' => ['adm_mail_test', 'super', false],
        'GET /security' => ['adm_security', 'super', false],
        'POST /security' => ['adm_security_save', 'super', false],
        'GET /users' => ['adm_users', 'admin', true],
        'POST /users/add' => ['adm_user_add', 'admin', true],
        'POST /users/role' => ['adm_user_role', 'admin', true],
        'POST /users/remove' => ['adm_user_remove', 'admin', true],
        'POST /users/edit' => ['adm_user_edit', 'super', true],
        'POST /users/super-remove' => ['adm_user_super_remove', 'super', true],
        'GET /activity' => ['adm_activity', 'admin', true],
        'GET /help' => ['adm_help', 'editor', true],
        'GET /sites' => ['adm_sites', 'super', false],
        'POST /sites/create' => ['adm_site_create', 'super', false],
        'GET /account' => ['adm_account', 'editor', false],
        'POST /account/password' => ['adm_account_password', 'editor', false],
        'POST /account/profile' => ['adm_account_profile', 'editor', false],
        'POST /account/locale' => ['adm_account_locale', 'editor', false],
        'POST /account/2fa/start' => ['adm_2fa_start', 'editor', false],
        'POST /account/2fa/enable' => ['adm_2fa_enable', 'editor', false],
        'POST /account/2fa/cancel' => ['adm_2fa_cancel', 'editor', false],
        'POST /account/2fa/manage' => ['adm_2fa_manage', 'editor', false],
        'POST /users/2fa-reset' => ['adm_2fa_reset', 'super', true],
    ];
}

function admin_handle(string $path, string $method): void
{
    session_start_safe();
    header('X-Frame-Options: DENY');
    header('Cache-Control: no-store');
    if ($path !== '/' && str_ends_with($path, '/')) $path = rtrim($path, '/');

    if ($path === '/login') { adm_login($method); return; }
    if ($path === '/login/2fa') { adm_login_2fa($method); return; }
    if ($path === '/forgot') { adm_forgot($method); return; }
    if ($path === '/reset') { adm_reset($method); return; }

    security_session_check(); // caducidad por inactividad y renovación del id de sesión
    $user = current_user();
    if (!$user) {
        if ($method === 'POST') { http_response_code(401); exit(t('Sesión caducada. Vuelve a entrar en /admin/login.')); }
        redirect(ADM . '/login');
    }
    // Si la contraseña cambió desde que se abrió esta sesión (recuperación o cambio en otro dispositivo), se cierra.
    $mark = session_password_mark($user);
    if (($_SESSION['pwmark'] ??= $mark) !== $mark) {
        $_SESSION = [];
        session_regenerate_id(true);
        flash(t('Tu contraseña ha cambiado: vuelve a entrar.'), 'error');
        redirect(ADM . '/login');
    }
    Actor::set('user', (int) $user['id'], (string) $user['name']);
    // 2FA obligatorio (Seguridad y red): hasta activarlo solo se puede ir a Mi cuenta o salir.
    if (twofa_required_for($user) && !str_starts_with($path, '/account') && $path !== '/logout') {
        flash(t('Por seguridad, activa la verificación en dos pasos para usar el panel.'), 'error');
        redirect(ADM . '/account#dos-pasos');
    }

    if ($path === '/logout' && $method === 'POST') {
        csrf_check();
        $_SESSION = [];
        session_regenerate_id(true);
        redirect(ADM . '/login');
    }
    if ($method === 'POST') csrf_check();

    // Webs accesibles y web actual.
    $sites = user_sites($user);
    $ids = array_map(fn($s) => (int) $s['id'], $sites);
    $sid = (int) ($_SESSION['site_id'] ?? 0);
    if (!in_array($sid, $ids, true)) {
        $hs = site_for_host(request_host());
        $sid = ($hs && in_array((int) $hs['id'], $ids, true)) ? (int) $hs['id'] : ($ids[0] ?? 0);
        $_SESSION['site_id'] = $sid;
    }

    if ($path === '/switch' && $method === 'GET') {
        $want = (int) ($_GET['site'] ?? 0);
        if (in_array($want, $ids, true)) $_SESSION['site_id'] = $want;
        $back = (string) ($_GET['to'] ?? '');
        redirect(ADM . (preg_match('#^/[a-z/]*$#', $back) && $back !== '/switch' ? $back : '/'));
    }

    $svc = null;
    $role = $user['is_superadmin'] ? 'super' : null;
    if ($sid) {
        $site = site_by_id($sid);
        if ($site) {
            $svc = new SiteService($site);
            $role = user_role($user, $sid);
        }
    }

    $route = adm_routes()["$method $path"] ?? null;
    $c = ['svc' => $svc, 'user' => $user, 'role' => $role, 'sites' => $sites, 'path' => $path];
    if (!$route) { adm_render('error', $c, ['title' => 'No encontrado', 'code' => 404, 'message' => 'Esta página del panel no existe.']); return; }

    [$fn, $min, $needsSite] = $route;
    if ($needsSite && !$svc) {
        if ($user['is_superadmin']) redirect(ADM . '/sites');
        adm_render('error', $c, ['title' => 'Sin acceso', 'code' => 403, 'message' => 'Tu usuario no tiene ninguna web asignada. Pide a un administrador que te dé acceso.']);
        return;
    }
    if (!role_at_least($role, $min)) {
        adm_render('error', $c, ['title' => 'Sin permiso', 'code' => 403, 'message' => 'No tienes permiso para ver esta sección.']);
        return;
    }

    try {
        $fn($c);
    } catch (UwError $ex) {
        if ($method !== 'POST') {
            adm_render('error', $c, ['title' => 'Error', 'code' => 422, 'message' => $ex->getMessage()]);
            return;
        }
        $_SESSION['old'] = ['path' => $path, 'post' => adm_safe_post($_POST)];
        flash(t_msg($ex->getMessage()), 'error');
        redirect(adm_back($path));
    }
}

// ---------------------------------------------------------------- utilidades

function adm_back(string $path): string
{
    $forms = ['/pages/save' => '/pages/edit', '/posts/save' => '/posts/edit', '/sites/create' => '/sites', '/tokens/create' => '/tokens',
        '/users/add' => '/users', '/users/role' => '/users', '/users/remove' => '/users', '/users/super-remove' => '/users', '/account/password' => '/account', '/account/profile' => '/account', '/account/locale' => '/account', '/settings' => '/settings',
        '/forms' => '/forms', '/forms/test' => '/forms', '/forms/webhook-test' => '/forms', '/media/upload-url' => '/media', '/design' => '/design', '/design/restore' => '/design',
        '/backups/create' => '/backups', '/backups/delete' => '/backups', '/backups/restore' => '/backups', '/backups/import' => '/backups',
        '/backups/settings' => '/backups', '/security' => '/security', '/publish' => '/publish', '/publish/run' => '/publish',
        '/account/2fa/start' => '/account', '/account/2fa/enable' => '/account', '/account/2fa/manage' => '/account', '/users/2fa-reset' => '/users'];
    if ($path === '/users/edit') return ADM . '/users?edit=' . (int) ($_POST['user_id'] ?? 0);
    if (isset($forms[$path])) {
        $id = (int) ($_POST['id'] ?? 0);
        return ADM . $forms[$path] . (in_array($path, ['/pages/save', '/posts/save'], true) && $id ? "?id=$id" : '');
    }
    $ref = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_PATH);
    $q = (string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_QUERY);
    if (str_starts_with($ref, ADM . '/') && $ref !== ADM . '/login') return $ref . ($q !== '' ? "?$q" : '');
    return ADM . '/';
}

/**
 * Lo que se puede guardar en la sesión para rellenar un formulario que falló: sin contraseñas, claves,
 * tokens ni códigos, tampoco dentro de arrays (settings[api_key]). Antes la clave privada SFTP quedaba en
 * claro en el fichero de sesión. `keywords` y similares sí se conservan.
 */
function adm_safe_post(array $post): array
{
    $out = [];
    foreach ($post as $k => $v) {
        if ($k === '_csrf' || preg_match('/pass|secret|(^|_)key$|token|code|^(current|new|confirm)$/i', (string) $k)) continue;
        $out[$k] = is_array($v) ? adm_safe_post($v) : $v;
    }
    return $out;
}

function adm_render(string $view, array $c, array $vars = []): void
{
    $dir = UW_ROOT . '/app/views/admin/';
    // Si un formulario falló al guardar y su pantalla no recuperó los valores por su cuenta, los devuelve admin.js.
    $refill = $_SESSION['old'] ?? null;
    unset($_SESSION['old']);
    if ($refill) $refill['post'] = adm_safe_post((array) $refill['post']);
    $common = $c + ['flashes' => flash_take(), 'activeNav' => $c['path'] ?? '/', 'refill' => $refill, 'view' => $view];
    $content = render_template($dir . $view . '.php', $vars + $common);
    $html = render_template($dir . '_layout.php', $vars + $common + ['content' => $content]);
    if (!empty($vars['code'])) http_response_code((int) $vars['code']);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

/** Valores del formulario anterior que falló al guardar (de un solo uso). */
function adm_old(string $path): ?array
{
    $o = $_SESSION['old'] ?? null;
    if (!$o || ($o['path'] ?? '') !== $path) return null; // es de otro formulario: lo recupera admin.js
    unset($_SESSION['old']);
    return $o['post'];
}

function adm_json_script(string $id, mixed $data): string
{
    $json = str_replace(['</', '<!--'], ['<\/', '<!--'], json_enc($data));
    return '<script type="application/json" id="' . e($id) . '">' . $json . '</script>';
}

function adm_media_datalist(SiteService $svc): string
{
    $o = '<datalist id="media-urls">';
    foreach ($svc->media() as $m) if ($m['mime'] !== 'application/pdf') $o .= '<option value="' . e($m['url']) . '">' . e($m['original_name']) . '</option>';
    return $o . '</datalist>';
}

function adm_badge(string $label, string $kind = 'gray'): string
{
    return '<span class="badge badge-' . e($kind) . '">' . e($label) . '</span>';
}

function adm_state_badge(string $state): string
{
    return match ($state) {
        'published' => adm_badge(t('Publicada'), 'green'),
        'scheduled' => adm_badge(t('Programada'), 'blue'),
        default => adm_badge(t('Borrador'), 'gray'),
    };
}

function adm_date(?string $d): string
{
    if (!$d) return '—';
    $t = strtotime($d);
    return $t ? date('d/m/Y H:i', $t) : $d;
}

function adm_icon(string $name): string
{
    static $p = [
        'home' => 'M3 11l9-8 9 8M5 10v10h5v-6h4v6h5V10',
        'file' => 'M6 3h8l5 5v13H6zM14 3v5h5',
        'edit' => 'M4 20h4L19 9l-4-4L4 16zM14 6l4 4',
        'image' => 'M4 5h16v14H4zM4 16l5-5 4 4 3-3 4 4M9 9h.01',
        'mail' => 'M3 6h18v12H3zM3 7l9 7 9-7',
        'send' => 'M22 2 11 13M22 2l-7 20-4-9-9-4z',
        'palette' => 'M12 3a9 9 0 1 0 0 18c1 0 1.5-.8 1.5-1.5 0-.4-.2-.8-.4-1.1-.2-.3-.4-.6-.4-1 0-.8.7-1.4 1.5-1.4H16a5 5 0 0 0 5-5c0-4.4-4-8-9-8zM7.5 12.5h.01M9.5 8h.01M14.5 8h.01M17 11.5h.01',
        'archive' => 'M3 4h18v4H3zM5 8v12h14V8M10 12h4',
        'help' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.01',
        'trash' => 'M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13M10 11v6M14 11v6',
        'shield' => 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
        'upload' => 'M12 16V4M7 9l5-5 5 5M4 16v4h16v-4',
        'search' => 'M11 4a7 7 0 100 14 7 7 0 000-14zM21 21l-5-5',
        'settings' => 'M12 9a3 3 0 100 6 3 3 0 000-6zM19 12l2-1-2-4-2 1-2-1-1-2h-4l-1 2-2 1-2-1-2 4 2 1v2l-2 1 2 4 2-1 2 1 1 2h4l1-2 2-1 2 1 2-4-2-1z',
        'key' => 'M14 10a4 4 0 11-3 6.5L3 17v-3h3v-2h2l2-2',
        'users' => 'M9 11a3 3 0 100-6 3 3 0 000 6zM3 20c0-3 3-5 6-5s6 2 6 5M17 6a3 3 0 010 5M21 20c0-2-1-4-3-4.5',
        'activity' => 'M3 12h4l3-8 4 16 3-8h4',
        'lang' => 'M4 5h8M8 3v2m2 0c-1 4-3.5 7-6 8.5M6 9c1.2 2 3 3.6 5 4.5M13 21l4-9 4 9m-6.8-3h5.6',
        'cookie' => 'M12 3a9 9 0 109 9 3 3 0 01-3-3 3 3 0 01-3-3 3 3 0 01-3-3zM8.5 11h.01M12 16h.01M15.5 13h.01',
        'layout' => 'M4 4h16v16H4zM4 9h16M9 9v11',
        'globe' => 'M12 3a9 9 0 100 18 9 9 0 000-18zM3 12h18M12 3c3 3 3 15 0 18M12 3c-3 3-3 15 0 18',
        'out' => 'M10 4H4v16h6M16 8l4 4-4 4M20 12H9',
        'ext' => 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6',
        'menu' => 'M4 6h16M4 12h16M4 18h16',
        'user' => 'M12 12a4 4 0 100-8 4 4 0 000 8zM4 21c0-4 4-6 8-6s8 2 8 6',
        'bot' => 'M5 8h14v10H5zM12 4v4M9 13h.01M15 13h.01',
        'pdf' => 'M6 3h8l5 5v13H6zM14 3v5h5M9 15h6',
    ];
    return '<svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="' . ($p[$name] ?? '') . '"/></svg>';
}

function adm_id(string $key = 'id'): int
{
    return (int) ($_POST[$key] ?? $_GET[$key] ?? 0);
}

function adm_seo_from_post(): array
{
    return [
        'title' => (string) ($_POST['seo_title'] ?? ''),
        'description' => (string) ($_POST['seo_description'] ?? ''),
        'image' => (string) ($_POST['seo_image'] ?? ''),
        'noindex' => isset($_POST['seo_noindex']),
    ];
}

// ---------------------------------------------------------------- acceso

/** Hash de relleno con el mismo coste que los reales: un email inexistente tarda lo mismo (no se puede adivinar quién existe). */
function login_dummy_hash(): string
{
    $h = (string) val("SELECT value FROM meta WHERE key = 'dummy_hash'");
    if ($h === '' || password_needs_rehash($h, PASSWORD_DEFAULT)) {
        $h = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        q("INSERT OR REPLACE INTO meta (key, value) VALUES ('dummy_hash', ?)", [$h]);
    }
    return $h;
}

function adm_login(string $method): void
{
    if ($method === 'GET') panel_lang_redirect(ADM . '/login'); // ?lang=es|ro|en
    if (current_user()) redirect(ADM . '/');
    if ($method === 'POST') {
        csrf_check();
        if (!throttle('login:' . client_ip(), 10, 900)) {
            flash(t('Demasiados intentos. Espera unos minutos y vuelve a probar.'), 'error');
            redirect(ADM . '/login');
        }
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!throttle('login-email:' . hash('sha256', $email), 10, 900)) {
            flash(t('Demasiados intentos. Espera unos minutos y vuelve a probar.'), 'error');
            redirect(ADM . '/login');
        }
        $u = $email !== '' ? row('SELECT * FROM users WHERE email = ?', [$email]) : null;
        $hash = $u['password_hash'] ?? login_dummy_hash();
        if (password_verify((string) ($_POST['password'] ?? ''), $hash) && $u) {
            if (twofa_enabled($u)) {
                // Contraseña correcta, pero aún no hay sesión: falta el código de la app (5 minutos).
                session_regenerate_id(true);
                $_SESSION['2fa_uid'] = (int) $u['id'];
                $_SESSION['2fa_until'] = time() + 300;
                redirect(ADM . '/login/2fa');
            }
            session_regenerate_id(true);
            $_SESSION['uid'] = (int) $u['id'];
            $_SESSION['pwmark'] = session_password_mark($u);
            panel_adopt_cookie_locale((int) $u['id']);
            update('users', ['last_login_at' => now()], 'id = ?', [$u['id']]);
            Actor::set('user', (int) $u['id'], (string) $u['name']);
            audit(null, 'user.login', $u['email']);
            redirect(ADM . '/');
        }
        flash(t('Email o contraseña incorrectos.'), 'error');
        redirect(ADM . '/login');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo render_template(UW_ROOT . '/app/views/admin/login.php', ['flashes' => flash_take(), 'siteName' => (site_for_host(request_host())['name'] ?? 'Universal Web')]);
}

// ---------------------------------------------------------------- panel

function adm_dashboard(array $c): void
{
    $svc = $c['svc'];
    adm_render('dashboard', $c, [
        'title' => 'Panel', 'stats' => $svc->stats(), 'seo' => $svc->seoAudit(), 'activity' => $svc->activity(10),
        'mcpUrl' => $svc->url('/mcp'),
        // Solo quien administra la web (admin o superadmin) puede generar el enlace de vista previa sin dominio.
        'previewLink' => role_at_least($c['role'], 'admin') ? site_preview_link($svc->site) : '',
    ]);
}

// ---- páginas

function adm_pages(array $c): void
{
    $svc = $c['svc'];
    // Con varios idiomas: una fila por slug con sus traducciones (y las páginas que solo existen en otro idioma).
    $groups = [];
    foreach ($svc->pages(false, true) as $p) $groups[$p['slug']][$p['lang']] = $p;
    adm_render('pages', $c, ['title' => 'Páginas', 'pages' => $svc->pages(), 'groups' => $groups, 'langs' => $svc->langs()]);
}

function adm_page_edit(array $c): void
{
    $svc = $c['svc'];
    $id = adm_id();
    $page = $id ? $svc->pageById($id) : null;
    if ($id && !$page) throw new UwError(t('Página no encontrada'));
    $page ??= ['id' => 0, 'title' => '', 'slug' => '', 'status' => 'draft', 'blocks' => [], 'seo' => sanitize_seo([]), 'url' => '', 'lang' => adm_lang_param($svc)];
    // Traducir: nueva página en otro idioma con el mismo slug y una copia del contenido original para reescribir.
    $from = !$id && isset($_GET['translate']) ? $svc->pageById((int) $_GET['translate']) : null;
    // El SEO no se copia en el idioma original: título y descripción se escriben ya traducidos (la auditoría avisa si faltan).
    if ($from) $page = ['title' => $from['title'], 'slug' => $from['slug'], 'blocks' => $from['blocks'], 'seo' => ['title' => '', 'description' => ''] + $from['seo']] + $page;
    if ($old = adm_old('/pages/save')) {
        $page['lang'] = in_array($old['lang'] ?? '', $svc->langs(), true) ? $old['lang'] : $page['lang'];
        $page['title'] = (string) ($old['title'] ?? '');
        $page['slug'] = (string) ($old['slug'] ?? '');
        $page['status'] = (string) ($old['status'] ?? 'draft');
        $page['seo'] = sanitize_seo(['title' => $old['seo_title'] ?? '', 'description' => $old['seo_description'] ?? '', 'image' => $old['seo_image'] ?? '', 'noindex' => isset($old['seo_noindex'])]);
        $b = json_decode((string) ($old['blocks'] ?? '[]'), true);
        if (is_array($b)) $page['blocks'] = $b;
    }
    adm_render('page_edit', $c, ['title' => $id ? 'Editar página' : 'Nueva página', 'page' => $page, 'schema' => block_types(), 'from' => $from,
        'translations' => $page['slug'] !== '' ? $svc->translations('pages', $page['slug']) : []]);
}

function adm_page_save(array $c): void
{
    $svc = $c['svc'];
    $id = adm_id() ?: null;
    $raw = (string) ($_POST['blocks'] ?? '[]');
    $blocks = json_decode($raw, true);
    if (!is_array($blocks)) throw new UwError(t('El JSON de bloques no es válido'));
    $page = $svc->savePage([
        'title' => (string) ($_POST['title'] ?? ''), 'slug' => trim((string) ($_POST['slug'] ?? '')) ?: slugify((string) ($_POST['title'] ?? '')),
        'status' => (string) ($_POST['status'] ?? 'draft'), 'seo' => adm_seo_from_post(), 'blocks' => $blocks,
        'lang' => $id ? null : adm_lang_param($svc, (string) ($_POST['lang'] ?? '')),
    ], $id);
    flash(t('Página guardada.'));
    redirect(ADM . '/pages/edit?id=' . $page['id']);
}

function adm_page_delete(array $c): void
{
    $c['svc']->deletePage(adm_id());
    flash(t('Página movida a la papelera (30 días).'));
    redirect(ADM . '/pages');
}

// ---- entradas

function adm_posts(array $c): void
{
    $svc = $c['svc'];
    $status = in_array($_GET['status'] ?? '', ['draft', 'published'], true) ? $_GET['status'] : '';
    $per = 25;
    $page = max(1, (int) ($_GET['p'] ?? 1));
    $lang = adm_lang_param($svc);
    $o = ($status ? ['status' => $status] : []) + ['lang' => $lang];
    $total = $svc->countPosts($o);
    adm_render('posts', $c, [
        'title' => 'Entradas', 'status' => $status, 'lang' => $lang, 'langs' => $svc->langs(), 'page' => $page, 'pages' => max(1, (int) ceil($total / $per)), 'total' => $total,
        'posts' => $svc->posts($o + ['limit' => $per, 'offset' => ($page - 1) * $per]),
    ]);
}

function adm_post_edit(array $c): void
{
    $svc = $c['svc'];
    $id = adm_id();
    $post = $id ? $svc->postById($id) : null;
    if ($id && !$post) throw new UwError(t('Entrada no encontrada'));
    $post ??= ['id' => 0, 'title' => '', 'slug' => '', 'excerpt' => '', 'content' => '', 'cover_image' => '', 'tags' => [], 'author' => $c['user']['name'],
        'status' => 'draft', 'published_at' => null, 'state' => 'draft', 'seo' => sanitize_seo([]), 'url' => '', 'lang' => adm_lang_param($svc)];
    $from = !$id && isset($_GET['translate']) ? $svc->postById((int) $_GET['translate']) : null;
    if ($from) {
        foreach (['title', 'slug', 'excerpt', 'content', 'cover_image', 'tags'] as $k) $post[$k] = $from[$k];
        $post['seo'] = ['title' => '', 'description' => ''] + $from['seo'];
    }
    if ($old = adm_old('/posts/save')) {
        $post['lang'] = in_array($old['lang'] ?? '', $svc->langs(), true) ? $old['lang'] : $post['lang'];
        foreach (['title', 'slug', 'excerpt', 'content', 'cover_image', 'author', 'status'] as $k) $post[$k] = (string) ($old[$k] ?? '');
        $post['tags'] = array_filter(array_map('trim', explode(',', (string) ($old['tags'] ?? ''))));
        $post['published_at'] = (string) ($old['published_at'] ?? '');
        $post['seo'] = sanitize_seo(['title' => $old['seo_title'] ?? '', 'description' => $old['seo_description'] ?? '', 'image' => $old['seo_image'] ?? '', 'noindex' => isset($old['seo_noindex'])]);
    }
    adm_render('post_edit', $c, ['title' => $id ? 'Editar entrada' : 'Nueva entrada', 'post' => $post, 'from' => $from,
        'translations' => $post['slug'] !== '' ? $svc->translations('posts', $post['slug']) : []]);
}

function adm_post_save(array $c): void
{
    $id = adm_id() ?: null;
    $post = $c['svc']->savePost([
        'title' => (string) ($_POST['title'] ?? ''), 'slug' => trim((string) ($_POST['slug'] ?? '')),
        'excerpt' => (string) ($_POST['excerpt'] ?? ''), 'content' => (string) ($_POST['content'] ?? ''),
        'cover_image' => (string) ($_POST['cover_image'] ?? ''), 'audio' => (string) ($_POST['audio'] ?? ''), 'tags' => (string) ($_POST['tags'] ?? ''),
        'author' => (string) ($_POST['author'] ?? ''), 'status' => (string) ($_POST['status'] ?? 'draft'),
        'published_at' => (string) ($_POST['published_at'] ?? ''), 'seo' => adm_seo_from_post(),
        'lang' => $id ? null : adm_lang_param($c['svc'], (string) ($_POST['lang'] ?? '')),
    ], $id);
    flash(t('Entrada guardada.'));
    redirect(ADM . '/posts/edit?id=' . $post['id']);
}

function adm_post_delete(array $c): void
{
    $c['svc']->deletePost(adm_id());
    flash(t('Entrada movida a la papelera (30 días).'));
    redirect(ADM . '/posts');
}

// ---- medios

function adm_media(array $c): void
{
    adm_render('media', $c, ['title' => 'Medios', 'media' => $c['svc']->media()]);
}

function adm_media_upload(array $c): void
{
    $f = $_FILES['files'] ?? null;
    if (!$f || !is_array($f['name'] ?? null)) throw new UwError(t('Elige al menos un archivo'));
    $ok = 0;
    $errors = [];
    foreach ($f['name'] as $i => $name) {
        $err = $f['error'][$i] ?? UPLOAD_ERR_NO_FILE;
        if ($err === UPLOAD_ERR_NO_FILE) continue;
        try {
            if ($err !== UPLOAD_ERR_OK) throw new UwError(t($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'supera el tamaño máximo permitido' : 'error al subir'));
            if (!is_uploaded_file($f['tmp_name'][$i])) throw new UwError(t('subida no válida'));
            $c['svc']->storeMedia($f['tmp_name'][$i], (string) $name, (string) ($_POST['alt'] ?? ''), true);
            $ok++;
        } catch (UwError $ex) {
            $errors[] = $name . ': ' . t_msg($ex->getMessage());
        }
    }
    if ($ok) flash($ok === 1 ? t('Archivo subido.') : t('{n} archivos subidos.', ['n' => $ok]));
    foreach ($errors as $m) flash($m, 'error');
    if (!$ok && !$errors) flash(t('Elige al menos un archivo.'), 'error');
    redirect(ADM . '/media');
}

function adm_media_delete(array $c): void
{
    $c['svc']->deleteMedia(adm_id());
    flash(t('Archivo eliminado.'));
    redirect(ADM . '/media');
}

// ---- mensajes

function adm_messages(array $c): void
{
    adm_render('messages', $c, ['title' => 'Mensajes', 'messages' => $c['svc']->messages(false, 200)]);
}

function adm_message_mark(array $c): void
{
    $c['svc']->setMessageRead(adm_id(), ($_POST['read'] ?? '1') === '1');
    redirect(ADM . '/messages');
}

function adm_message_delete(array $c): void
{
    $c['svc']->deleteMessage(adm_id());
    flash(t('Mensaje eliminado.'));
    redirect(ADM . '/messages');
}

function adm_message_resend(array $c): void
{
    $status = form_notify($c['svc'], adm_id(), false);
    if ($status === 'sent') flash(t('Aviso enviado por email.'));
    elseif ($status === 'off') flash(t('Los avisos por email están desactivados en Formularios.'), 'error');
    else flash(t('No se pudo enviar: {err}', ['err' => t_msg((string) val('SELECT mail_error FROM messages WHERE id = ? AND site_id = ?', [adm_id(), $c['svc']->id()]))]), 'error');
    redirect(ADM . '/messages');
}

// ---- formularios y correo

function adm_forms(array $c): void
{
    $raw = form_config_raw($c['svc']->id());
    // Los secretos nunca vuelven al navegador: solo si están guardados o no.
    $has = array_combine(FORM_SECRET_FIELDS, array_map(fn($k) => $raw[$k] !== '', FORM_SECRET_FIELDS));
    foreach (FORM_SECRET_FIELDS as $k) $raw[$k] = '';
    if ($old = adm_old('/forms')) {
        // Tras un error, mostrar lo que se escribió (las casillas sin marcar no llegan en el POST).
        $raw = array_replace($raw, array_intersect_key($old, $raw));
        foreach (['notify_enabled', 'autoreply_enabled', 'turnstile_enabled', 'smtp_verify_cert', 'webhook_enabled'] as $k) $raw[$k] = !empty($old[$k]);
        foreach (FORM_SECRET_FIELDS as $k) $raw[$k] = '';
    }
    // El secreto del webhook sí se muestra: hay que copiarlo en el CRM para verificar las firmas.
    $webhookSecret = $has['webhook_secret'] ? form_config($c['svc']->id())['webhook_secret'] : '';
    adm_render('forms', $c, ['title' => 'Formularios', 'f' => $raw, 'has' => $has, 'webhookSecret' => $webhookSecret,
        'mailAvailable' => function_exists('mail')]);
}

function adm_forms_save(array $c): void
{
    form_config_save($c['svc']->id(), $_POST);
    flash(t('Configuración de formularios guardada.'));
    redirect(ADM . '/forms');
}

function adm_forms_test(array $c): void
{
    $svc = $c['svc'];
    $to = trim((string) ($_POST['to'] ?? '')) ?: (string) $c['user']['email'];
    $cfg = form_config($svc->id());
    mail_send($cfg, [$to], t('Prueba de correo · {name}', ['name' => $svc->site['name']]),
        t("Este es un correo de prueba enviado desde el panel de {name}.\n\nSi lo recibes, la configuración de envío funciona.", ['name' => $svc->site['name']]));
    audit($svc->id(), 'forms.test', $to);
    flash(t('Correo de prueba enviado a {to}. Revisa la bandeja (y la carpeta de spam).', ['to' => $to]));
    redirect(ADM . '/forms');
}

function adm_forms_webhook_test(array $c): void
{
    $svc = $c['svc'];
    $cfg = form_config($svc->id());
    if ($cfg['webhook_url'] === '') throw new UwError(t('Guarda primero la URL del webhook'));
    form_webhook_send($cfg, 'test', ['site' => ['slug' => $svc->site['slug'], 'name' => $svc->site['name'], 'url' => $svc->url('/')],
        'message' => ['id' => 0, 'name' => 'Prueba', 'email' => 'prueba@example.com', 'subject' => 'Webhook de prueba', 'body' => 'Mensaje de prueba desde el panel.', 'page' => '/contacto', 'created_at' => now()]]);
    flash(t('El webhook respondió correctamente.'));
    redirect(ADM . '/forms');
}

// ---- SEO

function adm_seo(array $c): void
{
    $svc = $c['svc'];
    adm_render('seo', $c, [
        'title' => 'SEO', 'audit' => $svc->seoAudit(), 'geo' => geo_audit($svc), 'redirects' => redirect_list($svc->id()),
        'links' => ['sitemap.xml' => $svc->url('/sitemap.xml'), 'robots.txt' => $svc->url('/robots.txt'), 'feed.xml' => $svc->url('/feed.xml')],
    ]);
}

function adm_redirect_save(array $c): void
{
    try {
        $r = redirect_save_manual($c['svc'], (string) ($_POST['from'] ?? ''), (string) ($_POST['to'] ?? ''));
        flash(t('Redirección creada: {from} → {to}', ['from' => $r['from'], 'to' => $r['to']]) . (isset($r['warning']) ? ' · ' . t_msg($r['warning']) : ''));
    } catch (UwError $e) {
        flash(t_msg($e->getMessage()), 'error');
    }
    redirect(ADM . '/seo');
}

function adm_redirect_delete(array $c): void
{
    $f = redirect_norm_from((string) ($_POST['from'] ?? ''));
    if ($f !== null && redirect_drop($c['svc']->id(), $f)) { audit($c['svc']->id(), 'redirect.delete', $f); flash(t('Redirección borrada.')); }
    redirect(ADM . '/seo');
}

// ---- ajustes

function adm_settings(array $c): void
{
    $svc = $c['svc'];
    adm_render('settings', $c, [
        'title' => 'Ajustes', 's' => $svc->settings(), 'themes' => themes_available(), 'domains' => site_domains($svc->id()),
    ]);
}

function adm_settings_save(array $c): void
{
    $svc = $c['svc'];
    $g = fn(string $k) => (string) ($_POST[$k] ?? '');
    $menu = [];
    foreach ((array) ($_POST['menu_label'] ?? []) as $i => $label) {
        $menu[] = ['label' => (string) $label, 'url' => (string) ($_POST['menu_url'][$i] ?? '')];
    }
    $social = [];
    foreach (array_keys(site_defaults()['social']) as $k) $social[$k] = (string) ($_POST['social'][$k] ?? '');
    $domains = normalize_domains($g('domains'));
    // Primero dominios (puede fallar) y luego ajustes.
    // Una web creada sin dominio puede seguir sin él; una que ya tiene dominios no se queda sin ninguno.
    site_set_domains($svc->id(), $domains, $c['role'] === 'super', !site_domains($svc->id()));
    $svc->setPrivate(!empty($_POST['private_enabled']), trim($g('private_password')));
    $svc->updateSettings([
        'name' => $g('name'), 'tagline' => $g('tagline'), 'language' => $g('language'), 'theme' => $g('theme'),
        'accent' => $g('accent'), 'logo' => $g('logo'), 'footer_text' => $g('footer_text'),
        'cta' => ['label' => $g('cta_label'), 'url' => $g('cta_url')],
        'business' => ['type' => $g('business_type') ?: 'Organization', 'legal_name' => $g('business_legal_name'), 'area_served' => $g('business_area_served'), 'founded' => $g('business_founded')],
        'ai_crawlers' => $g('ai_crawlers') ?: 'allow_all',
        'ai_markdown' => !empty($_POST['ai_markdown']),
        'utility_menu' => array_map(fn($l) => array_combine(['label', 'url'], array_map('trim', array_pad(explode('|', $l, 2), 2, ''))),
            array_filter(array_map('trim', explode("\n", $g('utility_menu'))))),
        'contact' => ['email' => (string) ($_POST['contact']['email'] ?? ''), 'phone' => (string) ($_POST['contact']['phone'] ?? ''), 'address' => (string) ($_POST['contact']['address'] ?? '')],
        'social' => $social,
        'seo' => ['description' => (string) ($_POST['seo']['description'] ?? ''), 'image' => (string) ($_POST['seo']['image'] ?? '')],
        'menu' => $menu,
    ]);
    flash(t('Ajustes guardados.'));
    redirect(ADM . '/settings');
}

/** Idioma pedido (?lang= o el dado) si la web lo tiene; si no, el principal. */
function adm_lang_param(SiteService $svc, ?string $lang = null): string
{
    $lang ??= (string) ($_GET['lang'] ?? '');
    return in_array($lang, $svc->langs(), true) ? $lang : $svc->defaultLang();
}

// ---- idiomas

function adm_languages(array $c): void
{
    $svc = $c['svc'];
    $have = [];
    foreach (['pages', 'posts'] as $t) {
        foreach (rows("SELECT slug, lang FROM $t WHERE site_id = ?", [$svc->id()]) as $r) $have[$t][$r['slug']][$r['lang']] = true;
    }
    adm_render('languages', $c, ['title' => 'Idiomas', 'base' => $svc->baseSettings(), 'langs' => $svc->langs(), 'have' => $have]);
}

function adm_languages_save(array $c): void
{
    $extra = array_values(array_filter(array_map('strval', (array) ($_POST['languages'] ?? []))));
    $c['svc']->updateSettings(['languages' => $extra]);
    flash(t('Idiomas guardados.'));
    redirect(ADM . '/languages');
}

function adm_languages_texts(array $c): void
{
    $lang = (string) ($_POST['lang'] ?? '');
    $lines = fn(string $k) => array_map(fn($l) => array_combine(['label', 'url'], array_map('trim', array_pad(explode('|', $l, 2), 2, ''))),
        array_values(array_filter(array_map('trim', explode("\n", (string) ($_POST[$k] ?? ''))))));
    $c['svc']->updateSettings(['translations' => [$lang => [
        'tagline' => (string) ($_POST['tagline'] ?? ''), 'footer_text' => (string) ($_POST['footer_text'] ?? ''),
        'seo' => ['description' => (string) ($_POST['seo_description'] ?? '')],
        'menu' => $lines('menu'), 'utility_menu' => $lines('utility_menu'),
        'cta' => ['label' => (string) ($_POST['cta_label'] ?? ''), 'url' => (string) ($_POST['cta_url'] ?? '')],
        'cookies_text' => (string) ($_POST['cookies_text'] ?? ''),
    ]]]);
    flash(t('Textos en {lang} guardados.', ['lang' => LANG_NAMES[$lang] ?? $lang]));
    redirect(ADM . '/languages#lang-' . rawurlencode($lang));
}

// ---- cookies

function adm_cookies(array $c): void
{
    adm_render('cookies', $c, ['title' => 'Cookies', 'ck' => cookies_config($c['svc'])]);
}

function adm_cookies_save(array $c): void
{
    $g = fn(string $k) => (string) ($_POST[$k] ?? '');
    $c['svc']->updateSettings(['cookies' => [
        'enabled' => !empty($_POST['enabled']), 'text' => $g('text'), 'policy_url' => $g('policy_url'), 'position' => $g('position') ?: 'bottom',
        'ga4' => $g('ga4'), 'gtm' => $g('gtm'), 'clarity' => $g('clarity'), 'meta_pixel' => $g('meta_pixel'), 'reset_consent' => !empty($_POST['reset_consent']),
    ]]);
    flash(t('Aviso de cookies guardado.'));
    redirect(ADM . '/cookies');
}

// ---- temas

function adm_themes(array $c): void
{
    adm_render('themes', $c, ['title' => 'Temas', 'themes' => theme_catalog(), 'current' => $c['svc']->site['theme'],
        'uploadAllowed' => theme_upload_allowed(), 'has2fa' => twofa_enabled($c['user'])]);
}

function adm_theme_apply(array $c): void
{
    $slug = (string) ($_POST['theme'] ?? '');
    $c['svc']->updateSettings(['theme' => $slug]);
    flash(t('Tema «{name}» aplicado a esta web.', ['name' => theme_meta($slug)['name'] ?? $slug]));
    redirect(ADM . '/themes');
}

function adm_theme_download(array $c): void
{
    $slug = (string) ($_GET['theme'] ?? '');
    $zip = theme_package($slug);
    audit($c['svc']->id(), 'theme.download', $slug);
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="universal-web-theme-' . $slug . '.zip"');
    header('Content-Length: ' . filesize($zip));
    header('Cache-Control: no-store');
    readfile($zip);
    @unlink($zip);
    exit;
}

function adm_theme_upload(array $c): void
{
    // Subir un tema es instalar código PHP: apagado por defecto, solo superadmin, con 2FA y confirmación expresa.
    if (!theme_upload_allowed()) throw new UwError(t('La subida de temas está desactivada. Actívala en data/config.php con «allow_theme_upload» => true'));
    if (!twofa_enabled($c['user'])) throw new UwError(t('Para subir temas necesitas tener activa la verificación en dos pasos'));
    if (empty($_POST['understand'])) throw new UwError(t('Confirma que entiendes que un tema ejecuta código en el servidor'));
    $f = $_FILES['file'] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) throw new UwError(t('No se ha recibido ningún archivo'));
    $pkg = theme_inspect((string) $f['tmp_name']);
    $slug = theme_install($pkg, !empty($_POST['replace']));
    flash(t('Tema «{name}» instalado. Pruébalo con «Vista previa» antes de aplicarlo.', ['name' => (string) $pkg['meta']['name']]));
    foreach ($pkg['warnings'] as $w) flash($w, 'error');
    redirect(ADM . '/themes#theme-' . $slug);
}

function adm_theme_delete(array $c): void
{
    theme_delete((string) ($_POST['theme'] ?? ''));
    flash(t('Tema eliminado (se guarda una copia en data/theme-backups).'));
    redirect(ADM . '/themes');
}

// ---- tokens

function adm_tokens(array $c): void
{
    $svc = $c['svc'];
    $new = $_SESSION['new_token'] ?? null;
    unset($_SESSION['new_token']);
    // Admins ven todos los tokens de la web (y super, también los globales); editores, solo los suyos.
    $sql = 'SELECT t.*, u.name AS owner_name FROM tokens t LEFT JOIN users u ON u.id = t.created_by WHERE ';
    if (role_at_least($c['role'], 'admin')) {
        $sql .= '(t.site_id = ?' . ($c['role'] === 'super' ? ' OR t.site_id IS NULL' : '') . ')';
        $params = [$svc->id()];
    } else {
        $sql .= 't.site_id = ? AND t.created_by = ?';
        $params = [$svc->id(), $c['user']['id']];
    }
    adm_render('tokens', $c, [
        'title' => 'Tokens de IA', 'tokens' => rows($sql . ' ORDER BY t.id DESC', $params), 'newToken' => $new, 'mcpUrl' => $svc->url('/mcp'),
    ]);
}

function adm_token_create(array $c): void
{
    // Solo el superadmin crea tokens globales; el resto, únicamente para la web actual.
    $global = $c['role'] === 'super' && ($_POST['target'] ?? '') === 'global';
    $raw = token_create($global ? null : $c['svc']->id(), (string) ($_POST['name'] ?? ''), (array) ($_POST['scopes'] ?? []), (int) $c['user']['id']);
    $_SESSION['new_token'] = ['raw' => $raw, 'name' => trim((string) $_POST['name']), 'global' => $global];
    redirect(ADM . '/tokens');
}

function adm_token_revoke(array $c): void
{
    $id = adm_id();
    $t = row('SELECT * FROM tokens WHERE id = ?', [$id]);
    $own = $t && (int) $t['created_by'] === (int) $c['user']['id'];
    $allowed = $t && ($t['site_id'] === null
        ? $c['role'] === 'super'
        : (int) $t['site_id'] === $c['svc']->id() && ($own || role_at_least($c['role'], 'admin')));
    if (!$allowed) throw new UwError(t('Token no encontrado'));
    q('UPDATE tokens SET revoked_at = ? WHERE id = ? AND revoked_at IS NULL', [now(), $id]);
    audit($t['site_id'] === null ? null : (int) $t['site_id'], 'token.revoke', $t['name']);
    flash(t('Token revocado.'));
    redirect(ADM . '/tokens');
}

// ---- usuarios

function adm_users(array $c): void
{
    $svc = $c['svc'];
    adm_render('users', $c, [
        'title' => 'Usuarios',
        'members' => rows('SELECT u.id, u.email, u.name, u.last_login_at, u.totp_secret != \'\' AS has_2fa, su.role FROM site_users su JOIN users u ON u.id = su.user_id WHERE su.site_id = ? AND u.is_superadmin = 0 ORDER BY u.name', [$svc->id()]),
        'supers' => rows('SELECT id, email, name, last_login_at, totp_secret != \'\' AS has_2fa FROM users WHERE is_superadmin = 1 ORDER BY name'),
        // Solo el superadmin edita a otros: un administrador de una web podría, cambiando el email, quedarse con una cuenta que entra en otras webs.
        'editing' => $c['role'] === 'super' && (int) ($_GET['edit'] ?? 0) > 0 ? user_by_id((int) $_GET['edit']) : null,
    ]);
}

function adm_user_add(array $c): void
{
    $svc = $c['svc'];
    $role = (string) ($_POST['role'] ?? 'editor');
    if (!in_array($role, ['editor', 'admin', 'super'], true)) throw new UwError(t('Rol no válido'));
    if ($role === 'super') {
        if ($c['role'] !== 'super') throw new UwError(t('Solo un superadmin puede crear otro superadmin'));
        adm_require_own_password($c['user']);
    }
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $u = $email !== '' ? row('SELECT * FROM users WHERE email = ?', [$email]) : null;
    if ($u && $u['is_superadmin']) throw new UwError(t('Ese usuario ya es superadmin y tiene acceso a todas las webs'));
    // Un admin de web no puede "fichar" a usuarios de otras webs: solo el superadmin vincula cuentas existentes.
    if ($u && $c['role'] !== 'super' && !val('SELECT 1 FROM site_users WHERE site_id = ? AND user_id = ?', [$svc->id(), $u['id']])) {
        throw new UwError(t('No se puede usar ese email. Usa otro o pide a un superadmin que añada esa cuenta a la web.'));
    }
    // La cuenta existente conserva su contraseña: dilo, o quien escribió una nueva creerá que es esa y no podrá entrar.
    $kept = $u && (string) ($_POST['password'] ?? '') !== '' ? ' ' . t('Esa cuenta ya existía y conserva su contraseña: la que has escrito no se ha usado. Puedes cambiarla en Editar.') : '';
    if ($role === 'super') {
        if ($u) update('users', ['is_superadmin' => 1], 'id = ?', [$u['id']]);
        else user_create($email, (string) ($_POST['name'] ?? ''), (string) ($_POST['password'] ?? ''), true);
        audit($svc->id(), 'user.super_add', $email, ['existing' => (bool) $u]);
        flash(t('Superadmin añadido: tiene acceso a todas las webs.') . $kept);
        redirect(ADM . '/users');
    }
    if (!$u) {
        $uid = user_create($email, (string) ($_POST['name'] ?? ''), (string) ($_POST['password'] ?? ''));
    } else {
        $uid = (int) $u['id'];
    }
    q('INSERT OR REPLACE INTO site_users (site_id, user_id, role) VALUES (?, ?, ?)', [$svc->id(), $uid, $role]);
    audit($svc->id(), 'user.add', $email, ['role' => $role, 'existing' => (bool) $u]);
    flash($u ? t('Usuario existente añadido a esta web.') . $kept : t('Usuario creado y añadido.'));
    redirect(ADM . '/users');
}

function adm_user_role(array $c): void
{
    $svc = $c['svc'];
    $uid = adm_id('user_id');
    $role = (string) ($_POST['role'] ?? '');
    if (!in_array($role, ['editor', 'admin'], true)) throw new UwError(t('Rol no válido'));
    if ($uid === (int) $c['user']['id']) throw new UwError(t('No puedes cambiar tu propio rol'));
    if (!val('SELECT 1 FROM site_users WHERE site_id = ? AND user_id = ?', [$svc->id(), $uid])) throw new UwError(t('Usuario no encontrado en esta web'));
    q('UPDATE site_users SET role = ? WHERE site_id = ? AND user_id = ?', [$role, $svc->id(), $uid]);
    audit($svc->id(), 'user.role', (string) $uid, ['role' => $role]);
    flash(t('Rol actualizado.'));
    redirect(ADM . '/users');
}

function adm_user_remove(array $c): void
{
    $svc = $c['svc'];
    $uid = adm_id('user_id');
    if ($uid === (int) $c['user']['id']) throw new UwError(t('No puedes quitarte a ti mismo de la web'));
    q('DELETE FROM site_users WHERE site_id = ? AND user_id = ?', [$svc->id(), $uid]);
    // Sus tokens de esta web ya no valdrían (el rol se comprueba en cada uso), pero los revocamos
    // para que no revivan si vuelve a ser añadido.
    q('UPDATE tokens SET revoked_at = ? WHERE site_id = ? AND created_by = ? AND revoked_at IS NULL', [now(), $svc->id(), $uid]);
    audit($svc->id(), 'user.remove', (string) $uid);
    flash(t('Usuario quitado de esta web.'));
    redirect(ADM . '/users');
}

/** Confirma con la contraseña de quien actúa los cambios con los que se podría tomar una cuenta. */
function adm_require_own_password(array $user): void
{
    if (!password_verify((string) ($_POST['confirm_password'] ?? ''), (string) $user['password_hash'])) {
        throw new UwError(t('Tu contraseña no es correcta'));
    }
}

/** Guarda nombre y email de una cuenta; si el email cambia, exige la contraseña de quien lo cambia. */
function adm_save_profile(array $c, array $target): void
{
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($email !== strtolower((string) $target['email'])) adm_require_own_password($c['user']);
    $changed = user_update_profile((int) $target['id'], (string) ($_POST['name'] ?? ''), $email);
    audit($c['svc']?->id(), 'user.profile', (string) $target['email'], $changed ? ['new_email' => $email] : []);
}

function adm_account_profile(array $c): void
{
    adm_save_profile($c, $c['user']);
    flash(t('Datos actualizados.'));
    redirect(ADM . '/account');
}

function adm_user_edit(array $c): void
{
    $target = user_by_id(adm_id('user_id')) ?? throw new UwError(t('Usuario no encontrado'));
    $new = (string) ($_POST['new_password'] ?? '');
    if ($new !== '') {
        if (mb_strlen($new) < 8) throw new UwError(t('La contraseña nueva debe tener al menos 8 caracteres'));
        adm_require_own_password($c['user']);
    }
    adm_save_profile($c, $target);
    if ($new !== '') {
        update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$target['id']]);
        q('DELETE FROM password_resets WHERE user_id = ?', [$target['id']]);
        if ((int) $target['id'] === (int) $c['user']['id']) $_SESSION['pwmark'] = session_password_mark(user_by_id((int) $target['id']));
        audit($c['svc']->id(), 'user.password_set', (string) $target['email']);
    }
    flash(t('Usuario actualizado.'));
    redirect(ADM . '/users');
}

function adm_user_super_remove(array $c): void
{
    $uid = adm_id('user_id');
    if ($uid === (int) $c['user']['id']) throw new UwError(t('No puedes quitarte a ti mismo como superadmin'));
    $target = user_by_id($uid);
    if (!$target || !$target['is_superadmin']) throw new UwError(t('Usuario no encontrado'));
    update('users', ['is_superadmin' => 0], 'id = ?', [$uid]);
    // Sus tokens globales ya no valdrían (se comprueba en cada uso), pero se revocan para que no revivan si vuelve a serlo.
    q('UPDATE tokens SET revoked_at = ? WHERE site_id IS NULL AND created_by = ? AND revoked_at IS NULL', [now(), $uid]);
    audit($c['svc']->id(), 'user.super_remove', (string) $target['email']);
    flash(t('Ya no es superadmin. Si debe seguir entrando, añádelo a una web.'));
    redirect(ADM . '/users');
}

function adm_account_locale(array $c): void
{
    $loc = (string) ($_POST['locale'] ?? '');
    if (!isset(PANEL_LOCALES[$loc])) throw new UwError(t('Idioma no válido'));
    update('users', ['locale' => $loc], 'id = ?', [$c['user']['id']]);
    panel_set_locale_cookie($loc);
    panel_locale($loc);
    flash(t('Idioma actualizado.'));
    redirect(ADM . '/account');
}

function adm_account(array $c): void
{
    adm_render('account', $c, ['title' => 'Mi cuenta']);
}

function adm_account_password(array $c): void
{
    $u = $c['user'];
    if (!password_verify((string) ($_POST['current'] ?? ''), $u['password_hash'])) throw new UwError(t('La contraseña actual no es correcta'));
    $new = (string) ($_POST['new'] ?? '');
    if (mb_strlen($new) < 8) throw new UwError(t('La contraseña nueva debe tener al menos 8 caracteres'));
    if ($new !== (string) ($_POST['confirm'] ?? '')) throw new UwError(t('La confirmación no coincide'));
    update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
    $_SESSION['pwmark'] = session_password_mark(user_by_id((int) $u['id'])); // esta sesión sigue; las demás se cierran
    audit($c['svc']?->id(), 'user.password', $u['email']);
    flash(t('Contraseña actualizada.'));
    redirect(ADM . '/account');
}

// ---- actividad

function adm_activity(array $c): void
{
    adm_render('activity', $c, ['title' => 'Actividad', 'entries' => $c['svc']->activity(200)]);
}

// ---- webs (superadmin)

function adm_sites(array $c): void
{
    $list = [];
    foreach (sites_all() as $s) {
        $n = fn(string $t) => (int) val("SELECT COUNT(*) FROM $t WHERE site_id = ?", [$s['id']]);
        $list[] = $s + ['domains' => site_domains((int) $s['id']), 'n_pages' => $n('pages'), 'n_posts' => $n('posts')];
    }
    adm_render('sites', $c, ['title' => 'Webs', 'list' => $list, 'themes' => themes_available(), 'old' => adm_old('/sites/create') ?? []]);
}

function adm_site_create(array $c): void
{
    $name = trim((string) ($_POST['name'] ?? ''));
    $site = site_create($name, trim((string) ($_POST['slug'] ?? '')), normalize_domains((string) ($_POST['domains'] ?? '')), (string) ($_POST['theme'] ?? 'default'));
    if (isset($_POST['starter'])) {
        $svc = new SiteService($site);
        $svc->savePage([
            'title' => 'Inicio', 'slug' => 'index', 'status' => 'published',
            'blocks' => [['type' => 'hero', 'data' => ['title' => $site['name'], 'subtitle' => 'Bienvenido a ' . $site['name'], 'cta_text' => 'Contacto', 'cta_link' => '/contacto']]],
        ]);
        $svc->savePage([
            'title' => 'Contacto', 'slug' => 'contacto', 'status' => 'published',
            'blocks' => [['type' => 'contact_form', 'data' => ['title' => 'Contacto', 'text' => 'Escríbenos y te responderemos lo antes posible.']]],
        ]);
    }
    flash(t('Web «{name}» creada.', ['name' => $site['name']]));
    redirect(ADM . '/sites');
}

/** Manual de usuario (docs/MANUAL.md) con índice: el mismo texto que se lee en GitHub. */
function adm_help(array $c): void
{
    $html = md(manual_markdown());
    $toc = [];
    $html = preg_replace_callback('#<h([23])>(.*?)</h\1>#', function ($m) use (&$toc) {
        $id = gh_anchor(strip_tags($m[2]));
        if ($m[1] === '2') $toc[] = [$id, html_entity_decode(strip_tags($m[2]))];
        return "<h{$m[1]} id=\"$id\">{$m[2]}</h{$m[1]}>";
    }, $html);
    // Las notas «> **Consejo:** …» se ven como avisos de color
    $html = preg_replace_callback('#<blockquote>\s*<p><strong>([^<:]+):</strong>#u', function ($m) {
        $kind = preg_match('/importante|derechos|antes de|cuidado|ojo/iu', $m[1]) ? 'warn' : 'tip';
        return '<blockquote class="callout callout-' . $kind . '"><p><strong>' . $m[1] . ':</strong>';
    }, $html);
    adm_render('help', $c, ['title' => 'Ayuda', 'manual' => $html, 'toc' => $toc]);
}

/**
 * El manual tal cual se escribe para GitHub (líneas partidas a ~120 columnas, índice a mano) adaptado a md(), que pinta
 * cada salto de línea: une los párrafos y las citas partidas y quita el índice (el panel hace el suyo a la izquierda).
 */
function manual_markdown(): string
{
    $md = (string) @file_get_contents(UW_ROOT . '/docs/MANUAL.md');
    $md = (string) preg_replace('/^## Índice\n.*?(?=^## )/msu', '', $md);
    $out = [];
    $code = false;
    $block = '/^(#{1,6}\s|```|>|\||\s*([-*+]|\d+\.)\s+|(-{3,}|\*{3,})\s*$)/';
    foreach (explode("\n", str_replace("\r\n", "\n", $md)) as $l) {
        if (str_starts_with($l, '```')) { $code = !$code; $out[] = $l; continue; }
        $prev = $out ? $out[count($out) - 1] : '';
        $joinable = !$code && trim($l) !== '' && trim($prev) !== '' && !preg_match('/^(#|```|\||-{3,})/', $prev);
        if ($joinable && str_starts_with($l, '>') && str_starts_with($prev, '>') && !preg_match($block, ltrim(substr($l, 1)))) {
            $out[count($out) - 1] = rtrim($prev) . ' ' . trim(substr($l, 1));
            continue;
        }
        if ($joinable && !str_starts_with($prev, '>') && !preg_match($block, $l)) {
            $out[count($out) - 1] = rtrim($prev) . ' ' . trim($l);
            continue;
        }
        $out[] = $l;
    }
    return implode("\n", $out);
}

/** Ancla como las de GitHub («13. Tu web con ADRI» → «13-tu-web-con-adri»): así funcionan los enlaces del manual. */
function gh_anchor(string $t): string
{
    $t = mb_strtolower(trim(html_entity_decode($t, ENT_QUOTES, 'UTF-8')));
    return str_replace(' ', '-', (string) preg_replace('/[^\p{L}\p{N} _-]/u', '', $t));
}

