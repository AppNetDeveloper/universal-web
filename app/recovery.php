<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Recuperación de contraseña del panel y correo propio del panel.
 *
 * El superadmin configura en «Correo del panel» un servidor SMTP (independiente del de los formularios de cada
 * web) y la URL pública del panel. Con eso, en el login aparece «¿Has olvidado tu contraseña?»:
 * - la respuesta es siempre la misma exista o no el email (no se puede averiguar quién tiene cuenta);
 * - límite de peticiones por IP y por email;
 * - el enlace lleva un token aleatorio de un solo uso que caduca en 1 hora y del que solo se guarda el hash;
 * - el enlace usa la URL configurada, nunca la cabecera Host (que un atacante podría falsificar);
 * - al cambiar la contraseña se cierran las demás sesiones; la verificación en dos pasos se sigue pidiendo.
 */

const RESET_TTL = 3600;

function panel_mail_defaults(): array
{
    return [
        'enabled' => false, 'panel_url' => '',
        'transport' => 'smtp', 'smtp_host' => '', 'smtp_port' => 587, 'smtp_encryption' => 'tls',
        'smtp_username' => '', 'smtp_password' => '', 'smtp_verify_cert' => true,
        'from_email' => '', 'from_name' => 'Universal Web',
    ];
}

function panel_mail_raw(): array
{
    return array_replace(panel_mail_defaults(), json_dec(val("SELECT value FROM meta WHERE key = 'panel_mail'")));
}

function panel_mail_config(): array
{
    $c = panel_mail_raw();
    $c['smtp_password'] = secret_decrypt((string) $c['smtp_password']);
    return $c;
}

/** ¿Puede el panel enviar correos (recuperación de contraseña)? */
function panel_mail_ready(): bool
{
    $c = panel_mail_raw();
    return $c['enabled'] && $c['panel_url'] !== '' && filter_var($c['from_email'], FILTER_VALIDATE_EMAIL) && ($c['transport'] === 'mail' || $c['smtp_host'] !== '');
}

function panel_mail_save(array $in): array
{
    $c = panel_mail_raw();
    $c['enabled'] = !empty($in['enabled']);
    $c['smtp_verify_cert'] = !empty($in['smtp_verify_cert']);
    $c['transport'] = in_array($in['transport'] ?? '', ['smtp', 'mail'], true) ? $in['transport'] : 'smtp';
    $c['smtp_encryption'] = in_array($in['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $in['smtp_encryption'] : 'tls';
    $c['smtp_port'] = max(1, min(65535, (int) ($in['smtp_port'] ?? 587)));
    foreach (['smtp_host', 'smtp_username', 'from_name', 'from_email'] as $k) $c[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, 200);
    $url = rtrim(trim((string) ($in['panel_url'] ?? '')), '/');
    if ($url !== '' && !preg_match('#^https?://[a-z0-9.-]+(:\d+)?$#i', $url)) throw new UwError(t('La URL del panel debe ser como https://panel.midominio.com (sin ruta)'));
    $c['panel_url'] = $url;
    $pw = (string) ($in['smtp_password'] ?? '');
    if (!empty($in['clear_smtp_password'])) $c['smtp_password'] = '';
    elseif ($pw !== '') $c['smtp_password'] = secret_encrypt($pw);
    if ($c['enabled']) {
        if ($c['panel_url'] === '') throw new UwError(t('Indica la URL pública del panel'));
        if (!filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)) throw new UwError(t('Indica un email remitente válido'));
        if ($c['transport'] === 'smtp' && $c['smtp_host'] === '') throw new UwError(t('Indica el servidor SMTP'));
    }
    q("INSERT OR REPLACE INTO meta (key, value) VALUES ('panel_mail', ?)", [json_enc($c)]);
    audit(null, 'platform.mail', $c['enabled'] ? 'on' : 'off');
    return $c;
}

function panel_mail_send(string $to, string $subject, string $body): void
{
    $c = panel_mail_config();
    if (!$c['enabled']) throw new UwError(t('El correo del panel no está configurado'));
    mail_send($c, [$to], $subject, $body);
}

// ---------------------------------------------------------------- recuperación

/** Crea un token para el usuario y envía el correo. Silencioso si el email no existe (lo decide el llamador). */
function password_reset_request(array $user): void
{
    q('DELETE FROM password_resets WHERE user_id = ? OR expires_at < ?', [$user['id'], time()]);
    $token = bin2hex(random_bytes(32));
    insert('password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'created_at' => now(), 'expires_at' => time() + RESET_TTL, 'ip' => client_ip()]);
    $link = panel_mail_raw()['panel_url'] . ADM . '/reset?token=' . $token;
    $body = t('Hola {name}:', ['name' => $user['name']]) . "\n\n"
        . t('Alguien (seguramente tú) ha pedido cambiar la contraseña de tu cuenta del panel Universal Web.') . "\n\n"
        . t('Para elegir una contraseña nueva abre este enlace (caduca en 1 hora y solo sirve una vez):') . "\n\n$link\n\n"
        . t('Si no lo has pedido tú, ignora este correo: tu contraseña no cambia.') . "\n\n"
        . t('Petición desde la IP {ip} el {date}.', ['ip' => client_ip(), 'date' => date('d/m/Y H:i')]) . "\n";
    panel_mail_send((string) $user['email'], t('Recupera tu contraseña del panel'), $body);
    audit(null, 'user.password_reset_request', (string) $user['email']);
}

/** Usuario dueño de un token válido (sin consumirlo) o null. */
function password_reset_user(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) return null;
    $r = row('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ?', [hash('sha256', $token), time()]);
    return $r ? user_by_id((int) $r['user_id']) : null;
}

function password_reset_complete(string $token, string $new, string $confirm): array
{
    $u = password_reset_user($token) ?? throw new UwError(t('El enlace no es válido o ha caducado. Pide otro.'));
    if (mb_strlen($new) < 8) throw new UwError(t('La contraseña nueva debe tener al menos 8 caracteres'));
    if ($new !== $confirm) throw new UwError(t('La confirmación no coincide'));
    db()->beginTransaction();
    // Consumo atómico del enlace: dos envíos a la vez con el mismo token, solo uno borra la fila.
    $own = q('DELETE FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at >= ?', [hash('sha256', $token), time()]);
    if ($own->rowCount() !== 1) {
        db()->rollBack();
        throw new UwError(t('El enlace no es válido o ha caducado. Pide otro.'));
    }
    update('users', ['password_hash' => password_hash($new, PASSWORD_DEFAULT)], 'id = ?', [$u['id']]);
    q('DELETE FROM password_resets WHERE user_id = ?', [$u['id']]);
    db()->commit();
    Actor::set('user', (int) $u['id'], (string) $u['name']);
    audit(null, 'user.password_reset', (string) $u['email']);
    return $u;
}

/** Huella de la contraseña guardada en la sesión: si cambia (recuperación, cambio en otro dispositivo) la sesión se cierra. */
function session_password_mark(array $user): string
{
    return substr(hash('sha256', (string) $user['password_hash']), 0, 16);
}

// ---------------------------------------------------------------- pantallas públicas (sin sesión)

function adm_forgot(string $method): void
{
    if (current_user()) redirect(ADM . '/');
    $ready = panel_mail_ready();
    if ($method === 'POST') {
        csrf_check();
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        // Límite por IP y por email; la respuesta es idéntica en todos los casos.
        if ($ready && throttle('reset:' . client_ip(), 5, 3600) && throttle('reset-email:' . hash('sha256', $email), 3, 3600) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $u = row('SELECT * FROM users WHERE email = ?', [$email]);
            if ($u) {
                // El correo sale DESPUÉS de responder (con PHP-FPM): enviarlo antes hacía que la
                // respuesta tardara más si la cuenta existía (enumeración de usuarios por tiempo).
                register_shutdown_function(function () use ($u) {
                    if (session_status() === PHP_SESSION_ACTIVE) session_write_close(); // el aviso (flash) antes de soltar la respuesta
                    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
                    try { password_reset_request($u); } catch (Throwable $e) { error_log('[universal-web] recuperación de contraseña: ' . $e->getMessage()); }
                });
            }
        }
        usleep(random_int(200000, 600000)); // mismo tiempo en todas las ramas (también email inválido o sin cupo)
        flash(t('Si ese email tiene cuenta, te hemos enviado un enlace para elegir una contraseña nueva. Revisa también el spam.'));
        redirect(ADM . '/forgot');
    }
    panel_lang_redirect(ADM . '/forgot');
    adm_public_view('forgot', ['ready' => $ready]);
}

function adm_reset(string $method): void
{
    header('Referrer-Policy: no-referrer'); // el token no debe salir en el Referer
    $token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
    if ($method === 'POST') {
        csrf_check();
        if (!throttle('reset-try:' . client_ip(), 20, 3600)) { flash(t('Demasiados intentos. Espera unos minutos y vuelve a probar.'), 'error'); redirect(ADM . '/login'); }
        try {
            password_reset_complete($token, (string) ($_POST['new'] ?? ''), (string) ($_POST['confirm'] ?? ''));
        } catch (UwError $e) {
            flash(t_msg($e->getMessage()), 'error');
            redirect(ADM . '/reset?token=' . rawurlencode($token));
        }
        session_regenerate_id(true);
        flash(t('Contraseña cambiada. Ya puedes entrar con la nueva.'));
        redirect(ADM . '/login');
    }
    adm_public_view('reset', ['token' => $token, 'valid' => password_reset_user($token) !== null]);
}

/** Pantallas sin sesión con el mismo aspecto que el login. */
function adm_public_view(string $view, array $vars): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo render_template(UW_ROOT . "/app/views/admin/$view.php", $vars + ['flashes' => flash_take()]);
}

// ---------------------------------------------------------------- configuración (superadmin)

function adm_mail(array $c): void
{
    $cfg = panel_mail_raw();
    $has = $cfg['smtp_password'] !== '';
    $cfg['smtp_password'] = '';
    if ($cfg['panel_url'] === '') $cfg['panel_url'] = (is_https() ? 'https://' : 'http://') . request_host();
    adm_render('mail', $c, ['title' => 'Correo del panel', 'cfg' => $cfg, 'hasPassword' => $has, 'ready' => panel_mail_ready()]);
}

function adm_mail_save(array $c): void
{
    panel_mail_save($_POST);
    flash(t('Correo del panel guardado.'));
    redirect(ADM . '/mail');
}

function adm_mail_test(array $c): void
{
    panel_mail_save($_POST);
    panel_mail_send((string) $c['user']['email'], t('Prueba del correo del panel'), t('Si lees esto, el panel ya puede enviar correos (recuperación de contraseña).') . "\n");
    flash(t('Correo de prueba enviado a {email}.', ['email' => $c['user']['email']]));
    redirect(ADM . '/mail');
}
