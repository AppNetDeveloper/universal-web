<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Formularios y correo por web: configuración (tabla site_forms, fuera de los ajustes públicos),
 * secretos cifrados, cliente SMTP propio (SSL / STARTTLS / AUTH), mail() como alternativa,
 * aviso al dueño, respuesta automática al visitante, Cloudflare Turnstile y trampa de tiempo.
 * El MCP no tiene acceso a nada de esto.
 */

// ---------------------------------------------------------------- secretos cifrados

function secret_key(): string
{
    $secret = (string) config('secret', '');
    if ($secret === '') throw new UwError('Falta la clave "secret" en data/config.php');
    return hash('sha256', $secret . '|secrets', true);
}

function secret_encrypt(string $plain): string
{
    if ($plain === '') return '';
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return 's1:' . base64_encode($nonce . sodium_crypto_secretbox($plain, $nonce, secret_key()));
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) throw new UwError('No se pudo cifrar el secreto');
    return 'o1:' . base64_encode($iv . $tag . $cipher);
}

function secret_decrypt(string $stored): string
{
    if ($stored === '') return '';
    $bin = base64_decode(substr($stored, 3), true);
    $plain = false;
    if ($bin !== false && str_starts_with($stored, 's1:') && function_exists('sodium_crypto_secretbox_open')) {
        $n = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        $plain = sodium_crypto_secretbox_open(substr($bin, $n), substr($bin, 0, $n), secret_key());
    } elseif ($bin !== false && str_starts_with($stored, 'o1:')) {
        $plain = openssl_decrypt(substr($bin, 28), 'aes-256-gcm', secret_key(), OPENSSL_RAW_DATA, substr($bin, 0, 12), substr($bin, 12, 16));
    }
    if ($plain === false) throw new UwError('No se pudo descifrar un secreto guardado: vuelve a introducirlo en Formularios');
    return $plain;
}

// ---------------------------------------------------------------- configuración por web

const FORM_SECRET_FIELDS = ['smtp_password', 'turnstile_secret', 'webhook_secret'];

function form_defaults(): array
{
    return [
        'notify_enabled' => false, 'recipients' => [], 'subject_prefix' => '',
        'transport' => 'smtp', // smtp | mail
        'smtp_host' => '', 'smtp_port' => 587, 'smtp_encryption' => 'tls', // tls (STARTTLS) | ssl | none
        'smtp_username' => '', 'smtp_password' => '', 'smtp_verify_cert' => true,
        'from_email' => '', 'from_name' => '',
        'autoreply_enabled' => false, 'autoreply_subject' => 'Hemos recibido tu mensaje',
        'autoreply_body' => "Hola {nombre}:\n\nGracias por escribirnos. Hemos recibido tu mensaje y te responderemos lo antes posible.\n\nUn saludo,\n{web}",
        'turnstile_enabled' => false, 'turnstile_site_key' => '', 'turnstile_secret' => '',
        'webhook_enabled' => false, 'webhook_url' => '', 'webhook_secret' => '',
    ];
}

/** Configuración con los secretos todavía cifrados (tal cual está en la base de datos). */
function form_config_raw(int $siteId): array
{
    return array_replace(form_defaults(), json_dec(val('SELECT config FROM site_forms WHERE site_id = ?', [$siteId])));
}

/** Configuración lista para usar, con los secretos descifrados. Solo para uso interno. */
function form_config(int $siteId): array
{
    $c = form_config_raw($siteId);
    foreach (FORM_SECRET_FIELDS as $k) $c[$k] = secret_decrypt((string) $c[$k]);
    return $c;
}

/**
 * Guarda la configuración. Un secreto vacío en $in conserva el anterior (el panel nunca lo
 * muestra); para borrarlo se pasa "clear_<campo>".
 */
function form_config_save(int $siteId, array $in): array
{
    $cur = form_config_raw($siteId);
    $c = $cur;
    foreach (['notify_enabled', 'autoreply_enabled', 'turnstile_enabled', 'smtp_verify_cert', 'webhook_enabled'] as $k) $c[$k] = !empty($in[$k]);
    $c['webhook_url'] = trim((string) ($in['webhook_url'] ?? ''));
    if ($c['webhook_url'] !== '' && !preg_match('#^https?://[^\s]+$#i', $c['webhook_url'])) throw new UwError('La URL del webhook debe empezar por http:// o https://');
    foreach (['subject_prefix', 'smtp_host', 'smtp_username', 'from_name', 'autoreply_subject', 'autoreply_body', 'turnstile_site_key'] as $k) {
        $c[$k] = mb_substr(trim((string) ($in[$k] ?? '')), 0, $k === 'autoreply_body' ? 5000 : 300);
    }
    foreach (['autoreply_subject', 'autoreply_body'] as $k) if ($c[$k] === '') $c[$k] = form_defaults()[$k];
    $c['transport'] = in_array($in['transport'] ?? '', ['smtp', 'mail'], true) ? $in['transport'] : 'smtp';
    $c['smtp_encryption'] = in_array($in['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $in['smtp_encryption'] : 'tls';
    $c['smtp_port'] = max(1, min(65535, (int) ($in['smtp_port'] ?? 587)));
    $c['from_email'] = trim((string) ($in['from_email'] ?? ''));

    $recipients = is_array($in['recipients'] ?? null) ? $in['recipients'] : preg_split('/[\s,;]+/', (string) ($in['recipients'] ?? ''));
    $c['recipients'] = array_values(array_unique(array_filter(array_map('trim', $recipients))));
    foreach ($c['recipients'] as $r) if (!filter_var($r, FILTER_VALIDATE_EMAIL)) throw new UwError("Destinatario no válido: $r");
    if ($c['from_email'] !== '' && !filter_var($c['from_email'], FILTER_VALIDATE_EMAIL)) throw new UwError('El email del remitente no es válido');

    foreach (FORM_SECRET_FIELDS as $k) {
        if (!empty($in["clear_$k"])) $c[$k] = '';
        elseif (trim((string) ($in[$k] ?? '')) !== '') $c[$k] = secret_encrypt(trim((string) $in[$k]));
    }

    if ($c['notify_enabled'] || $c['autoreply_enabled']) {
        if ($c['from_email'] === '') throw new UwError('Para enviar correos indica el email del remitente');
        if ($c['transport'] === 'smtp' && $c['smtp_host'] === '') throw new UwError('Indica el servidor SMTP');
    }
    if ($c['notify_enabled'] && !$c['recipients']) throw new UwError('Indica al menos un destinatario para los avisos');
    if ($c['webhook_enabled'] && $c['webhook_url'] === '') throw new UwError('Indica la URL del webhook');
    if ($c['autoreply_enabled'] && !$c['turnstile_enabled']) throw new UwError('La respuesta automática exige activar Cloudflare Turnstile: sin él, cualquiera podría usar tu formulario para enviar correos a terceros.');
    // Cada webhook lleva firma HMAC: si no hay secreto, se genera uno.
    if ($c['webhook_enabled'] && $c['webhook_secret'] === '') $c['webhook_secret'] = secret_encrypt(bin2hex(random_bytes(24)));
    if ($c['turnstile_enabled'] && ($c['turnstile_site_key'] === '' || $c['turnstile_secret'] === '')) {
        throw new UwError('Para activar Turnstile hacen falta la clave del sitio y la clave secreta');
    }

    q('INSERT INTO site_forms (site_id, config, updated_at) VALUES (?, ?, ?) ON CONFLICT(site_id) DO UPDATE SET config = excluded.config, updated_at = excluded.updated_at',
        [$siteId, json_enc($c), now()]);
    $changed = array_keys(array_filter($c, fn($v, $k) => $v !== $cur[$k], ARRAY_FILTER_USE_BOTH));
    audit($siteId, 'forms.update', '', ['fields' => $changed]); // sin valores: nunca se registran secretos
    return $c;
}

// ---------------------------------------------------------------- envío

/** Envía un email de texto con la configuración de la web. Lanza UwError con el motivo exacto. */
function mail_send(array $cfg, array $to, string $subject, string $body, ?string $replyTo = null): void
{
    $clean = fn(string $s) => trim(str_replace(["\r", "\n"], ' ', $s));
    $from = $clean($cfg['from_email']);
    if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new UwError('Falta el email del remitente');
    $to = array_values(array_filter($to, fn($t) => filter_var($t, FILTER_VALIDATE_EMAIL)));
    if (!$to) throw new UwError('No hay destinatarios válidos');
    if ($replyTo !== null && !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) $replyTo = null;

    $encode = fn(string $s) => preg_match('/[^\x20-\x7e]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $headers = [
        'Date' => date(DATE_RFC2822),
        'From' => ($cfg['from_name'] !== '' ? $encode($clean($cfg['from_name'])) . ' ' : '') . "<$from>",
        'To' => implode(', ', $to),
        'Subject' => $encode($clean($subject)),
        'Message-ID' => '<' . bin2hex(random_bytes(12)) . "@$domain>",
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Content-Transfer-Encoding' => 'base64',
        'X-Mailer' => 'Universal Web ' . UW_VERSION,
    ];
    if ($replyTo) $headers['Reply-To'] = $replyTo;
    $encodedBody = rtrim(chunk_split(base64_encode(str_replace(["\r\n", "\r"], "\n", $body)), 76, "\r\n"));

    if ($cfg['transport'] === 'mail') {
        $h = $headers;
        unset($h['To'], $h['Subject']);
        $lines = implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($h), $h));
        if (!@mail(implode(', ', $to), $headers['Subject'], $encodedBody, $lines)) {
            throw new UwError('La función mail() de PHP falló: el hosting no permite enviar así. Usa SMTP.');
        }
        return;
    }
    $data = implode("\r\n", array_map(fn($k, $v) => "$k: $v", array_keys($headers), $headers)) . "\r\n\r\n" . $encodedBody;
    (new SmtpClient($cfg))->send($from, $to, $data);
}

/** Cliente SMTP mínimo: SSL implícito o STARTTLS, AUTH PLAIN/LOGIN. */
final class SmtpClient
{
    /** @var resource|null */
    private $sock = null;

    public function __construct(private array $cfg) {}

    public function send(string $from, array $to, string $data): void
    {
        try {
            $this->connect();
            $ehlo = $this->ehlo();
            if ($this->cfg['smtp_encryption'] === 'tls') {
                if (!str_contains($ehlo, 'STARTTLS')) throw new UwError('El servidor no ofrece STARTTLS: prueba con cifrado SSL (puerto 465) o ninguno');
                $this->cmd('STARTTLS', 220);
                if (!@stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                    throw new UwError('Falló la negociación TLS (STARTTLS). Revisa el certificado del servidor o desactiva su verificación.');
                }
                $ehlo = $this->ehlo();
            }
            if ($this->cfg['smtp_username'] !== '') $this->auth($ehlo);
            $this->cmd('MAIL FROM:<' . $from . '>', 250);
            foreach ($to as $rcpt) $this->cmd('RCPT TO:<' . $rcpt . '>', [250, 251]);
            $this->cmd('DATA', 354);
            // Cuerpo en base64: ninguna línea empieza por "." (no hace falta dot-stuffing).
            $this->cmd($data . "\r\n.", 250);
            $this->cmd('QUIT', 221);
        } finally {
            if ($this->sock) fclose($this->sock);
            $this->sock = null;
        }
    }

    private function connect(): void
    {
        $host = $this->cfg['smtp_host'];
        $port = (int) $this->cfg['smtp_port'];
        $verify = (bool) $this->cfg['smtp_verify_cert'];
        $ctx = stream_context_create(['ssl' => ['verify_peer' => $verify, 'verify_peer_name' => $verify, 'allow_self_signed' => !$verify, 'peer_name' => $host]]);
        $scheme = $this->cfg['smtp_encryption'] === 'ssl' ? 'ssl' : 'tcp';
        $ip = outbound_resolve($host); // ni redes internas ni puertos locales, salvo permiso del superadmin
        $target = str_contains($ip, ':') ? "[$ip]" : $ip;
        $sock = @stream_socket_client("$scheme://$target:$port", $errno, $errstr, 10, STREAM_CLIENT_CONNECT, $ctx);
        if (!$sock) {
            throw new UwError($errstr === '' && $scheme === 'ssl'
                ? "Falló la conexión SSL con $host:$port: el certificado no es válido" . ($verify ? ' (o es autofirmado)' : '') . ' o ese puerto no usa SSL directo (prueba STARTTLS en el 587).'
                : "No se pudo conectar a $host:$port ($errstr). Revisa servidor, puerto y cifrado.");
        }
        stream_set_timeout($sock, 15);
        $this->sock = $sock;
        $this->expect(220);
    }

    private function ehlo(): string
    {
        $host = preg_replace('/[^a-z0-9.-]/i', '', (string) ($_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost')) ?: 'localhost';
        return $this->cmd("EHLO $host", 250);
    }

    private function auth(string $ehlo): void
    {
        $user = $this->cfg['smtp_username'];
        $pass = $this->cfg['smtp_password'];
        try {
            if (preg_match('/AUTH[ =][^\n]*PLAIN/i', $ehlo)) {
                $this->cmd('AUTH PLAIN ' . base64_encode("\0$user\0$pass"), 235, true);
            } else {
                $this->cmd('AUTH LOGIN', 334);
                $this->cmd(base64_encode($user), 334, true);
                $this->cmd(base64_encode($pass), 235, true);
            }
        } catch (UwError $ex) {
            throw new UwError('Autenticación SMTP rechazada: revisa usuario y contraseña. ' . $ex->getMessage());
        }
    }

    /** Envía una línea y comprueba el código de respuesta. $secret evita que la línea aparezca en errores. */
    private function cmd(string $line, int|array $expect, bool $secret = false): string
    {
        if (fwrite($this->sock, $line . "\r\n") === false) throw new UwError('Se perdió la conexión con el servidor SMTP');
        return $this->expect($expect, $secret ? '' : strtok($line, "\r\n"));
    }

    private function expect(int|array $codes, string $after = ''): string
    {
        $resp = '';
        while (($l = fgets($this->sock, 1024)) !== false) {
            $resp .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') break;
        }
        if ($resp === '') throw new UwError('El servidor SMTP no responde (tiempo agotado)');
        if (!in_array((int) substr($resp, 0, 3), (array) $codes, true)) {
            $cmd = $after !== '' && !str_starts_with($after, 'AUTH') ? ' tras «' . str_limit($after, 40) . '»' : '';
            throw new UwError('El servidor SMTP respondió' . $cmd . ': ' . trim($resp));
        }
        return $resp;
    }
}

// ---------------------------------------------------------------- formulario público

/** Campos ocultos de protección + widget de Turnstile si está activo. Para usar dentro del <form>. */
function form_protection_fields(SiteService $svc): string
{
    $t = time();
    $html = '<input type="hidden" name="_ts" value="' . $t . '.' . hash_hmac('sha256', (string) $t, secret_key()) . '">';
    $c = form_config_raw($svc->id());
    if ($c['turnstile_enabled'] && $c['turnstile_site_key'] !== '') {
        $html .= '<div class="cf-turnstile" data-sitekey="' . e($c['turnstile_site_key']) . '" data-language="' . e($svc->settings()['language']) . '"></div>'
            . '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
    return $html;
}

/** Comprueba trampa para bots, tiempo mínimo y Turnstile. Lanza UwError si no pasa. */
function form_check_protection(SiteService $svc, array $in, bool $isJson): void
{
    if (($in['website'] ?? '') !== '') throw new UwError('No se pudo enviar el mensaje.');
    // Trampa de tiempo (solo formularios HTML): firmada para que no se pueda falsificar.
    if (!$isJson) {
        [$t, $sig] = array_pad(explode('.', (string) ($in['_ts'] ?? ''), 2), 2, '');
        $age = time() - (int) $t;
        if (!hash_equals(hash_hmac('sha256', $t, secret_key()), $sig) || $age > 86400) throw new UwError('El formulario ha caducado: recarga la página e inténtalo de nuevo.');
        if ($age < 3) throw new UwError('Has enviado el formulario demasiado rápido. Inténtalo de nuevo.');
    }
    $c = form_config($svc->id());
    if ($c['turnstile_enabled']) {
        $token = (string) ($in['cf-turnstile-response'] ?? '');
        if ($token === '' || !turnstile_verify($c['turnstile_secret'], $token)) throw new UwError('No hemos podido verificar que no eres un robot. Inténtalo de nuevo.');
    }
}

function turnstile_verify(string $secret, string $token): bool
{
    $res = http_post_form('https://challenges.cloudflare.com/turnstile/v0/siteverify', ['secret' => $secret, 'response' => $token, 'remoteip' => client_ip()]);
    return $res !== null && !empty(json_dec($res)['success']);
}

function http_post_form(string $url, array $fields): ?string
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($fields), CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        $out = curl_exec($ch);
        return is_string($out) ? $out : null;
    }
    $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 10, 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($fields)]]);
    $out = @file_get_contents($url, false, $ctx);
    return $out === false ? null : $out;
}

// ---------------------------------------------------------------- webhook (CRM: ADRI u otro)

/**
 * Envía el mensaje a una URL externa (p. ej. el CRM de BionicAdri) como JSON firmado:
 * cabecera X-UW-Signature: sha256=<HMAC del cuerpo con el secreto del webhook>.
 * La URL la configura un admin, así que puede apuntar a redes privadas (ZeroTier, intranet).
 */
function form_webhook_send(array $cfg, string $event, array $payload): void
{
    $body = json_enc(['event' => $event, 'sent_at' => date('c')] + $payload);
    $sig = 'sha256=' . hash_hmac('sha256', $body, (string) $cfg['webhook_secret']);
    $headers = ['Content-Type: application/json', "X-UW-Event: $event", "X-UW-Signature: $sig", 'User-Agent: UniversalWeb/' . UW_VERSION];
    $u = parse_url((string) $cfg['webhook_url']);
    if (!$u || empty($u['host']) || !in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true)) throw new UwError('URL del webhook no válida');
    $ip = outbound_resolve($u['host']); // bloquea redes privadas salvo que el superadmin las permita
    $port = (int) ($u['port'] ?? (strtolower($u['scheme']) === 'https' ? 443 : 80));
    if (function_exists('curl_init')) {
        $ch = curl_init($cfg['webhook_url']);
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_RESOLVE => [trim($u['host'], '[]') . ":$port:$ip"]]); // IP fijada: sin DNS rebinding
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'timeout' => 8, 'ignore_errors' => true, 'follow_location' => 0]]);
        $res = @file_get_contents($cfg['webhook_url'], false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s?/', $http_response_header[0], $m) ? (int) $m[1] : 0;
        $err = $res === false ? 'sin respuesta' : '';
    }
    if ($code < 200 || $code >= 300) throw new UwError('El webhook respondió ' . ($code ?: 'sin conexión') . ($err ? " ($err)" : ''));
}

// ---------------------------------------------------------------- avisos de un mensaje

/**
 * Envía el aviso al dueño (y la respuesta automática) de un mensaje ya guardado. Nunca lanza:
 * el resultado queda en messages.mail_status / mail_error y el mensaje no se pierde.
 */
function form_notify(SiteService $svc, int $messageId, bool $firstTime = true): string
{
    $m = row('SELECT * FROM messages WHERE id = ? AND site_id = ?', [$messageId, $svc->id()]);
    if (!$m) return 'error';
    $c = form_config($svc->id());

    // 1) Webhook (CRM): independiente del correo y solo la primera vez (un reintento no duplica contactos).
    if ($firstTime && $c['webhook_enabled']) {
        try {
            form_webhook_send($c, 'message.created', [
                'site' => ['slug' => $svc->site['slug'], 'name' => $svc->site['name'], 'url' => $svc->url('/')],
                'message' => array_intersect_key($m, array_flip(['id', 'name', 'email', 'phone', 'subject', 'body', 'page', 'created_at'])),
            ]);
        } catch (Throwable $ex) {
            audit($svc->id(), 'forms.webhook_failed', (string) $messageId, ['error' => $ex->getMessage()]);
        }
    }

    // 2) Aviso por email y respuesta automática.
    $status = 'off';
    $error = '';
    try {
        if ($c['notify_enabled']) {
            $site = $svc->site['name'];
            $subject = trim(($c['subject_prefix'] !== '' ? $c['subject_prefix'] : "[$site]") . ' ' . ($m['subject'] !== '' ? $m['subject'] : "Nuevo mensaje de {$m['name']}"));
            $body = "Nuevo mensaje desde el formulario de $site\n\n"
                . "Nombre: {$m['name']}\nEmail: {$m['email']}\n" . ($m['phone'] !== '' ? "Teléfono: {$m['phone']}\n" : '') . ($m['subject'] !== '' ? "Asunto: {$m['subject']}\n" : '')
                . 'Página: ' . $svc->url($m['page'] ?: '/') . "\nFecha: {$m['created_at']}\n\n{$m['body']}\n\n"
                . "--\nResponde a este correo para contestar directamente a {$m['name']}.";
            mail_send($c, $c['recipients'], $subject, $body, $m['email']);
            $status = 'sent';
        }
        if ($firstTime && $c['autoreply_enabled']) {
            // Solo el nombre (recortado y sin enlaces) y el de la web: el texto del visitante nunca viaja en la respuesta,
            // para que el formulario no sirva para enviar spam a terceros desde tu dominio.
            $name = mb_substr(trim((string) preg_replace('#(https?://|www\.|[<>@/])#i', ' ', $m['name'])), 0, 40);
            $vars = ['{nombre}' => $name, '{web}' => $svc->site['name'], '{mensaje}' => '', '{asunto}' => ''];
            if (!throttle('autoreply:' . $svc->id(), 60, 3600) || !throttle('autoreply-to:' . hash('sha256', strtolower($m['email'])), 2, 86400)) {
                throw new UwError('Límite de respuestas automáticas alcanzado (protección antispam)');
            }
            mail_send($c, [$m['email']], strtr($c['autoreply_subject'], $vars), strtr($c['autoreply_body'], $vars));
        }
    } catch (Throwable $ex) {
        $status = 'failed';
        $error = $ex instanceof UwError ? $ex->getMessage() : 'Error interno al enviar el correo';
        if (!$ex instanceof UwError) error_log('[universal-web mail] ' . $ex);
    }
    q('UPDATE messages SET mail_status = ?, mail_error = ? WHERE id = ?', [$status, mb_substr($error, 0, 1000), $messageId]);
    return $status;
}
