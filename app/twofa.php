<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Verificación en dos pasos (2FA) con apps de autenticación: TOTP RFC 6238 (SHA-1, 6 dígitos, 30 s),
 * compatible con Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden…
 * El secreto se guarda cifrado; los códigos de recuperación, como hash; un código no se puede reutilizar.
 */

const TOTP_PERIOD = 30;
const TOTP_DIGITS = 6;
const TOTP_WINDOW = 1;          // acepta el paso anterior y el siguiente (desfase de reloj de ±30 s)
const RECOVERY_CODES = 10;

function base32_encode(string $bin): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($bin) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $out = '';
    foreach (str_split($bits, 5) as $chunk) $out .= $alphabet[bindec(str_pad($chunk, 5, '0'))];
    return $out;
}

function base32_decode(string $b32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper((string) preg_replace('/[\s=-]/', '', $b32));
    $bits = '';
    foreach (str_split($b32) as $c) {
        $v = strpos($alphabet, $c);
        if ($v === false) throw new UwError(t('Secreto 2FA no válido'));
        $bits .= str_pad(decbin($v), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $byte) if (strlen($byte) === 8) $out .= chr(bindec($byte));
    return $out;
}

function totp_code(string $key, int $step): string
{
    $h = hash_hmac('sha1', pack('J', $step), $key, true);
    $o = ord($h[19]) & 0x0F;
    $n = ((ord($h[$o]) & 0x7F) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % (10 ** TOTP_DIGITS)), TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/** Devuelve el paso de tiempo que coincide con el código (o null). Comparación en tiempo constante. */
function totp_match(string $secretB32, string $code, ?int $now = null): ?int
{
    $code = (string) preg_replace('/\D/', '', $code);
    if (strlen($code) !== TOTP_DIGITS) return null;
    $key = base32_decode($secretB32);
    $step = intdiv($now ?? time(), TOTP_PERIOD);
    $found = null;
    for ($i = -TOTP_WINDOW; $i <= TOTP_WINDOW; $i++) {
        if (hash_equals(totp_code($key, $step + $i), $code)) $found = $step + $i;
    }
    return $found;
}

function twofa_enabled(array $user): bool
{
    return ($user['totp_secret'] ?? '') !== '';
}

function twofa_new_secret(): string
{
    return base32_encode(random_bytes(20)); // 160 bits, lo recomendado por RFC 4226
}

function twofa_uri(array $user, string $secretB32): string
{
    $issuer = 'Universal Web (' . request_host() . ')';
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode((string) $user['email'])
        . '?secret=' . $secretB32 . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=' . TOTP_DIGITS . '&period=' . TOTP_PERIOD;
}

/** Genera códigos de recuperación: devuelve [códigos en claro (mostrar una vez), json de hashes (guardar)]. */
function twofa_recovery_generate(): array
{
    $plain = [];
    for ($i = 0; $i < RECOVERY_CODES; $i++) {
        $raw = strtolower(base32_encode(random_bytes(7)));            // 12 caracteres
        $plain[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    return [$plain, json_enc(array_map(fn($c) => hash('sha256', $c), $plain))];
}

/**
 * Comprueba un código (de la app o de recuperación) para un usuario y lo "gasta":
 * guarda el último paso usado (no se puede reutilizar) o elimina el código de recuperación.
 * Devuelve 'totp', 'recovery' o null.
 */
function twofa_verify_and_consume(array $user, string $input): ?string
{
    $input = strtolower(trim($input));
    if (preg_match('/^[a-z2-7]{5}-?[a-z2-7]{5}$/', $input)) {
        $norm = substr(str_replace('-', '', $input), 0, 5) . '-' . substr(str_replace('-', '', $input), 5);
        $hashes = json_dec($user['recovery_codes'] ?? '[]');
        $h = hash('sha256', $norm);
        foreach ($hashes as $i => $stored) {
            if (hash_equals((string) $stored, $h)) {
                unset($hashes[$i]);
                update('users', ['recovery_codes' => json_enc(array_values($hashes))], 'id = ?', [$user['id']]);
                return 'recovery';
            }
        }
        return null;
    }
    $step = totp_match(secret_decrypt((string) $user['totp_secret']), $input);
    if ($step === null || $step <= (int) ($user['totp_last_step'] ?? 0)) return null; // código ya usado
    update('users', ['totp_last_step' => $step], 'id = ?', [$user['id']]);
    return 'totp';
}

function twofa_enable(array $user, string $secretB32, string $code): array
{
    $step = totp_match($secretB32, $code);
    if ($step === null) throw new UwError(t('El código no es correcto. Comprueba que la hora del móvil es automática y escribe el código actual de la app.'));
    [$plain, $hashes] = twofa_recovery_generate();
    update('users', ['totp_secret' => secret_encrypt($secretB32), 'totp_last_step' => $step, 'recovery_codes' => $hashes], 'id = ?', [$user['id']]);
    audit(null, 'user.2fa_enable', (string) $user['email']);
    return $plain;
}

function twofa_disable(int $userId, string $reason = 'user.2fa_disable'): void
{
    $u = user_by_id($userId) ?? throw new UwError(t('Usuario no encontrado'));
    update('users', ['totp_secret' => '', 'totp_last_step' => 0, 'recovery_codes' => '[]'], 'id = ?', [$userId]);
    audit(null, $reason, (string) $u['email']);
}

/** ¿Debe este usuario activar 2FA antes de usar el panel? */
function twofa_required_for(array $user): bool
{
    return !empty(platform()['require_2fa']) && !twofa_enabled($user);
}

// ---------------------------------------------------------------- panel

/** Segundo paso del login: /admin/login/2fa */
function adm_login_2fa(string $method): void
{
    if ($method === 'GET') panel_lang_redirect(ADM . '/login/2fa'); // ?lang=es|ro|en
    $uid = (int) ($_SESSION['2fa_uid'] ?? 0);
    if (!$uid || ($_SESSION['2fa_until'] ?? 0) < time()) {
        unset($_SESSION['2fa_uid'], $_SESSION['2fa_until']);
        if ($uid) flash(t('Ha pasado demasiado tiempo. Vuelve a entrar.'), 'error');
        redirect(ADM . '/login');
    }
    $u = user_by_id($uid);
    if (!$u || !twofa_enabled($u)) { unset($_SESSION['2fa_uid']); redirect(ADM . '/login'); }

    if ($method === 'POST') {
        csrf_check();
        // Límite por usuario y por IP: el código tiene 6 dígitos, la fuerza bruta debe ser imposible.
        if (!throttle('2fa:' . $uid, 5, 900) || !throttle('2fa-ip:' . client_ip(), 20, 900)) {
            unset($_SESSION['2fa_uid'], $_SESSION['2fa_until']);
            audit(null, 'user.2fa_locked', (string) $u['email']);
            flash(t('Demasiados códigos incorrectos. Espera 15 minutos y vuelve a entrar.'), 'error');
            redirect(ADM . '/login');
        }
        $kind = twofa_verify_and_consume($u, (string) ($_POST['code'] ?? ''));
        if ($kind) {
            unset($_SESSION['2fa_uid'], $_SESSION['2fa_until']);
            session_regenerate_id(true);
            $_SESSION['uid'] = $uid;
            $_SESSION['pwmark'] = session_password_mark($u);
            panel_adopt_cookie_locale($uid);
            update('users', ['last_login_at' => now()], 'id = ?', [$uid]);
            Actor::set('user', $uid, (string) $u['name']);
            audit(null, 'user.login', (string) $u['email'], ['2fa' => $kind]);
            if ($kind === 'recovery') {
                $left = count(json_dec((string) user_by_id($uid)['recovery_codes']));
                flash(t('Has entrado con un código de recuperación. Te quedan {left}: si has perdido el móvil, desactiva y vuelve a activar la verificación en dos pasos en Mi cuenta.', ['left' => $left]), 'error');
            }
            redirect(ADM . '/');
        }
        audit(null, 'user.2fa_failed', (string) $u['email']);
        flash(t('Código incorrecto o ya usado.'), 'error');
        redirect(ADM . '/login/2fa');
    }
    header('Content-Type: text/html; charset=utf-8');
    echo render_template(UW_ROOT . '/app/views/admin/login_2fa.php', ['flashes' => flash_take(), 'email' => $u['email']]);
}

function adm_2fa_start(array $c): void
{
    if (twofa_enabled($c['user'])) throw new UwError(t('La verificación en dos pasos ya está activa'));
    $_SESSION['2fa_setup'] = twofa_new_secret();
    redirect(ADM . '/account#dos-pasos');
}

function adm_2fa_enable(array $c): void
{
    $secret = (string) ($_SESSION['2fa_setup'] ?? '');
    if ($secret === '') throw new UwError(t('Empieza de nuevo la activación'));
    $_SESSION['2fa_codes_once'] = twofa_enable($c['user'], $secret, (string) ($_POST['code'] ?? ''));
    unset($_SESSION['2fa_setup']);
    flash(t('Verificación en dos pasos activada. Guarda ahora tus códigos de recuperación.'));
    redirect(ADM . '/account#dos-pasos');
}

function adm_2fa_cancel(array $c): void
{
    unset($_SESSION['2fa_setup']);
    redirect(ADM . '/account');
}

/** Regenerar códigos de recuperación o desactivar: exige contraseña y un código válido. */
function adm_2fa_manage(array $c): void
{
    $u = $c['user'];
    if (!twofa_enabled($u)) throw new UwError(t('La verificación en dos pasos no está activa'));
    if (!password_verify((string) ($_POST['password'] ?? ''), $u['password_hash'])) throw new UwError(t('La contraseña no es correcta'));
    if (!throttle('2fa:' . $u['id'], 5, 900)) throw new UwError(t('Demasiados intentos. Espera 15 minutos.'));
    if (!twofa_verify_and_consume($u, (string) ($_POST['code'] ?? ''))) throw new UwError(t('El código no es correcto o ya se ha usado'));
    if (($_POST['action'] ?? '') === 'disable') {
        if (!empty(platform()['require_2fa'])) throw new UwError(t('La verificación en dos pasos es obligatoria en esta instalación: no se puede desactivar.'));
        twofa_disable((int) $u['id']);
        flash(t('Verificación en dos pasos desactivada.'));
    } else {
        [$plain, $hashes] = twofa_recovery_generate();
        update('users', ['recovery_codes' => $hashes], 'id = ?', [$u['id']]);
        audit(null, 'user.2fa_recovery_regen', (string) $u['email']);
        $_SESSION['2fa_codes_once'] = $plain;
        flash(t('Códigos de recuperación nuevos generados. Los anteriores ya no sirven.'));
    }
    redirect(ADM . '/account#dos-pasos');
}

/** Superadmin: quitar el 2FA de otro usuario (p. ej. ha perdido el móvil y los códigos). */
function adm_2fa_reset(array $c): void
{
    $uid = adm_id('user_id');
    if ($uid === (int) $c['user']['id']) throw new UwError(t('Para tu propia cuenta usa Mi cuenta'));
    twofa_disable($uid, 'user.2fa_reset');
    flash(t('Verificación en dos pasos reseteada: el usuario deberá configurarla de nuevo.'));
    redirect(ADM . '/users');
}
