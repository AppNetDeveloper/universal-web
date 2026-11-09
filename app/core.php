<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Núcleo: configuración, base de datos, utilidades, Markdown, sesión y auditoría.
 * Sin dependencias externas: funciona en PHP 8.1+ con pdo_sqlite y mbstring.
 */

const UW_VERSION = '0.3.0';
const UW_SCHEMA = 14;
const UW_AUTHOR_URL = 'https://www.bionicadri.com';
const UW_DEMO_URL = 'https://bionicadri.com/contacto.html#formulario';

/** Crédito de autoría (lo exige la Licencia BionicAdri: no se debe eliminar). */
function uw_credit(): string
{
    return '<span class="uw-credit">' . (function_exists('__') ? __('Hecho con Universal Web') : 'Hecho con Universal Web') . ' · Powered by <a href="' . UW_AUTHOR_URL . '" target="_blank" rel="noopener">bionicadri.com</a></span>';
}

/**
 * Versión de un archivo estático para la URL (?v=…): cambia cuando cambia el archivo, así un despliegue
 * con git nunca deja CSS/JS viejos en la caché de los visitantes (se sirven con caché larga).
 * $path es la URL pública: /assets/… (carpeta public) o /theme-assets/tema/… (carpeta themes/tema/assets).
 */
function asset_v(string $path): string
{
    static $cache = [];
    if (isset($cache[$path])) return $cache[$path];
    $file = preg_match('#^/theme-assets/([a-z0-9_-]+)/(.+)$#', $path, $m) ? UW_ROOT . "/themes/{$m[1]}/assets/{$m[2]}" : UW_PUBLIC . $path;
    $mt = is_file($file) ? (int) filemtime($file) : 0;
    return $cache[$path] = $path . '?v=' . substr(md5(UW_VERSION . '|' . $mt), 0, 10);
}

/** Error con mensaje seguro para mostrar al usuario / a la IA. */
final class UwError extends RuntimeException {}

// ---------------------------------------------------------------- config

function config(?string $key = null, mixed $default = null): mixed
{
    static $cfg = null;
    if ($cfg === null) {
        $file = UW_DATA . '/config.php';
        $cfg = is_file($file) ? (require $file) : [];
    }
    return $key === null ? $cfg : ($cfg[$key] ?? $default);
}

function is_installed(): bool
{
    return is_file(UW_DATA . '/config.php');
}

// ---------------------------------------------------------------- base de datos

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . UW_DATA . '/universal.sqlite', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        $pdo->exec('PRAGMA journal_mode = WAL');
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/** Las claves de $data vienen siempre del código, nunca del usuario. */
function insert(string $table, array $data): int
{
    $cols = array_keys($data);
    $sql = sprintf('INSERT INTO %s (%s) VALUES (%s)', $table, implode(',', $cols), implode(',', array_fill(0, count($cols), '?')));
    q($sql, array_values($data));
    return (int) db()->lastInsertId();
}

function update(string $table, array $data, string $where, array $params = []): void
{
    $set = implode(',', array_map(fn($c) => "$c = ?", array_keys($data)));
    q("UPDATE $table SET $set WHERE $where", [...array_values($data), ...$params]);
}

function migrate(): void
{
    db()->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT);
    CREATE TABLE IF NOT EXISTS sites (
        id INTEGER PRIMARY KEY, slug TEXT UNIQUE NOT NULL, name TEXT NOT NULL,
        theme TEXT NOT NULL DEFAULT 'default', settings TEXT NOT NULL DEFAULT '{}', created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS site_domains (
        domain TEXT PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        is_primary INTEGER NOT NULL DEFAULT 0);
    CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY, email TEXT UNIQUE NOT NULL COLLATE NOCASE, name TEXT NOT NULL,
        password_hash TEXT NOT NULL, is_superadmin INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL, last_login_at TEXT);
    CREATE TABLE IF NOT EXISTS site_users (
        site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        role TEXT NOT NULL, PRIMARY KEY (site_id, user_id));
    CREATE TABLE IF NOT EXISTS pages (
        id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        slug TEXT NOT NULL, title TEXT NOT NULL, blocks TEXT NOT NULL DEFAULT '[]', seo TEXT NOT NULL DEFAULT '{}',
        status TEXT NOT NULL DEFAULT 'draft', created_at TEXT NOT NULL, updated_at TEXT NOT NULL,
        UNIQUE (site_id, slug));
    CREATE TABLE IF NOT EXISTS posts (
        id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        slug TEXT NOT NULL, title TEXT NOT NULL, excerpt TEXT NOT NULL DEFAULT '', content TEXT NOT NULL DEFAULT '',
        cover_image TEXT NOT NULL DEFAULT '', tags TEXT NOT NULL DEFAULT '[]', author TEXT NOT NULL DEFAULT '',
        status TEXT NOT NULL DEFAULT 'draft', published_at TEXT, seo TEXT NOT NULL DEFAULT '{}',
        created_at TEXT NOT NULL, updated_at TEXT NOT NULL, UNIQUE (site_id, slug));
    CREATE INDEX IF NOT EXISTS posts_pub ON posts (site_id, status, published_at);
    CREATE TABLE IF NOT EXISTS media (
        id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        path TEXT NOT NULL, original_name TEXT NOT NULL, mime TEXT NOT NULL, size INTEGER NOT NULL,
        alt TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        name TEXT NOT NULL, email TEXT NOT NULL, subject TEXT NOT NULL DEFAULT '', body TEXT NOT NULL,
        page TEXT NOT NULL DEFAULT '', ip TEXT NOT NULL DEFAULT '', is_read INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS tokens (
        id INTEGER PRIMARY KEY, site_id INTEGER REFERENCES sites(id) ON DELETE CASCADE,
        name TEXT NOT NULL, token_hash TEXT UNIQUE NOT NULL, prefix TEXT NOT NULL, scopes TEXT NOT NULL,
        created_by INTEGER, created_at TEXT NOT NULL, last_used_at TEXT, revoked_at TEXT);
    CREATE TABLE IF NOT EXISTS audit_log (
        id INTEGER PRIMARY KEY, site_id INTEGER, actor_type TEXT NOT NULL, actor_id INTEGER,
        actor_name TEXT NOT NULL, action TEXT NOT NULL, target TEXT NOT NULL DEFAULT '',
        details TEXT NOT NULL DEFAULT '{}', created_at TEXT NOT NULL);
    CREATE INDEX IF NOT EXISTS audit_site ON audit_log (site_id, id);
    CREATE TABLE IF NOT EXISTS throttle (key TEXT PRIMARY KEY, hits INTEGER NOT NULL, reset_at INTEGER NOT NULL);
    CREATE TABLE IF NOT EXISTS site_forms (
        site_id INTEGER PRIMARY KEY REFERENCES sites(id) ON DELETE CASCADE,
        config TEXT NOT NULL, updated_at TEXT NOT NULL);
    SQL);
    // v2: estado del envío por email de cada mensaje.
    $cols = array_column(rows('PRAGMA table_info(messages)'), 'name');
    if (!in_array('mail_status', $cols, true)) db()->exec("ALTER TABLE messages ADD COLUMN mail_status TEXT NOT NULL DEFAULT ''");
    if (!in_array('mail_error', $cols, true)) db()->exec("ALTER TABLE messages ADD COLUMN mail_error TEXT NOT NULL DEFAULT ''");
    // v3: CSS/JS/<head> personalizados por web, con historial de versiones.
    db()->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS site_code (
        site_id INTEGER PRIMARY KEY REFERENCES sites(id) ON DELETE CASCADE,
        css TEXT NOT NULL DEFAULT '', js TEXT NOT NULL DEFAULT '', head_html TEXT NOT NULL DEFAULT '',
        favicon TEXT NOT NULL DEFAULT '', version INTEGER NOT NULL DEFAULT 0, updated_at TEXT NOT NULL);
    CREATE TABLE IF NOT EXISTS site_code_history (
        id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        version INTEGER NOT NULL, css TEXT NOT NULL, js TEXT NOT NULL, head_html TEXT NOT NULL, favicon TEXT NOT NULL,
        actor_name TEXT NOT NULL, created_at TEXT NOT NULL);
    CREATE INDEX IF NOT EXISTS site_code_history_site ON site_code_history (site_id, version);
    SQL);
    // v4: teléfono en los mensajes de contacto.
    if (!in_array('phone', array_column(rows('PRAGMA table_info(messages)'), 'name'), true)) db()->exec("ALTER TABLE messages ADD COLUMN phone TEXT NOT NULL DEFAULT ''");
    // v5: verificación en dos pasos (TOTP).
    $ucols = array_column(rows('PRAGMA table_info(users)'), 'name');
    if (!in_array('totp_secret', $ucols, true)) db()->exec("ALTER TABLE users ADD COLUMN totp_secret TEXT NOT NULL DEFAULT ''");
    if (!in_array('totp_last_step', $ucols, true)) db()->exec("ALTER TABLE users ADD COLUMN totp_last_step INTEGER NOT NULL DEFAULT 0");
    if (!in_array('recovery_codes', $ucols, true)) db()->exec("ALTER TABLE users ADD COLUMN recovery_codes TEXT NOT NULL DEFAULT '[]'");
    // v6: publicación estática en otro servidor.
    db()->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS site_publish (
        site_id INTEGER PRIMARY KEY REFERENCES sites(id) ON DELETE CASCADE,
        config TEXT NOT NULL DEFAULT '{}', dirty INTEGER NOT NULL DEFAULT 0,
        last_published_at TEXT, last_status TEXT NOT NULL DEFAULT '', last_log TEXT NOT NULL DEFAULT '');
    SQL);
    // v7: multi-idioma (páginas y entradas por idioma) e idioma del panel por usuario.
    if (!in_array('locale', array_column(rows('PRAGMA table_info(users)'), 'name'), true)) db()->exec("ALTER TABLE users ADD COLUMN locale TEXT NOT NULL DEFAULT 'es'");
    foreach (['pages', 'posts'] as $t) {
        if (in_array('lang', array_column(rows("PRAGMA table_info($t)"), 'name'), true)) continue;
        // SQLite no permite cambiar una restricción UNIQUE: se reconstruye la tabla con (site_id, lang, slug).
        $create = (string) val("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?", [$t]);
        $new = str_replace(["CREATE TABLE $t", 'UNIQUE (site_id, slug)'], ["CREATE TABLE {$t}_v7", 'UNIQUE (site_id, lang, slug)'], $create);
        $new = (string) preg_replace('/\bslug TEXT NOT NULL,/', "slug TEXT NOT NULL, lang TEXT NOT NULL DEFAULT 'es',", $new, 1);
        $cols = implode(', ', array_column(rows("PRAGMA table_info($t)"), 'name'));
        db()->beginTransaction();
        db()->exec($new);
        db()->exec("INSERT INTO {$t}_v7 ($cols, lang) SELECT $cols, COALESCE((SELECT json_extract(s.settings, '$.language') FROM sites s WHERE s.id = $t.site_id), 'es') FROM $t");
        db()->exec("DROP TABLE $t");
        db()->exec("ALTER TABLE {$t}_v7 RENAME TO $t");
        db()->commit();
    }
    db()->exec('CREATE INDEX IF NOT EXISTS posts_pub ON posts (site_id, lang, status, published_at)');
    // v8: copias fuera del servidor (ámbito = id de la web, 0 = copias globales)
    db()->exec("CREATE TABLE IF NOT EXISTS backup_offsite (scope INTEGER PRIMARY KEY, config TEXT NOT NULL DEFAULT '{}',
        last_at TEXT, last_status TEXT NOT NULL DEFAULT '', last_log TEXT NOT NULL DEFAULT '', last_auto INTEGER NOT NULL DEFAULT 0)");
    // v9: recuperación de contraseña (solo se guarda el hash del token)
    db()->exec('CREATE TABLE IF NOT EXISTS password_resets (id INTEGER PRIMARY KEY, user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
        token_hash TEXT UNIQUE NOT NULL, created_at TEXT NOT NULL, expires_at INTEGER NOT NULL, used_at TEXT, ip TEXT NOT NULL DEFAULT \'\')');
    // v10: plantilla HTML segura por web (y su historial)
    db()->exec("CREATE TABLE IF NOT EXISTS site_template (site_id INTEGER PRIMARY KEY REFERENCES sites(id) ON DELETE CASCADE, enabled INTEGER NOT NULL DEFAULT 0,
        base_assets INTEGER NOT NULL DEFAULT 1, layout TEXT NOT NULL DEFAULT '', css TEXT NOT NULL DEFAULT '', version INTEGER NOT NULL DEFAULT 0, updated_at TEXT);
    CREATE TABLE IF NOT EXISTS site_template_history (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, version INTEGER NOT NULL,
        layout TEXT NOT NULL, css TEXT NOT NULL, enabled INTEGER NOT NULL, base_assets INTEGER NOT NULL, actor_name TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL)");
    // v11: biblioteca de medios para IA (título, tipo, generado por IA) y audio «Escuchar» en las entradas
    $mcols = array_column(rows('PRAGMA table_info(media)'), 'name');
    if (!in_array('title', $mcols, true)) db()->exec("ALTER TABLE media ADD COLUMN title TEXT NOT NULL DEFAULT ''");
    if (!in_array('kind', $mcols, true)) {
        db()->exec("ALTER TABLE media ADD COLUMN kind TEXT NOT NULL DEFAULT ''");
        db()->exec("UPDATE media SET kind = CASE WHEN mime LIKE 'image/%' THEN 'image' WHEN mime LIKE 'video/%' THEN 'video' WHEN mime LIKE 'audio/%' THEN 'audio' WHEN mime = 'application/pdf' THEN 'pdf' ELSE 'other' END");
    }
    if (!in_array('ai_generated', $mcols, true)) db()->exec('ALTER TABLE media ADD COLUMN ai_generated INTEGER NOT NULL DEFAULT 0');
    if (!in_array('audio', array_column(rows('PRAGMA table_info(posts)'), 'name'), true)) db()->exec("ALTER TABLE posts ADD COLUMN audio TEXT NOT NULL DEFAULT ''");
    // v12: errores de la web (navegadores de los visitantes y PHP), agrupados por huella
    db()->exec("CREATE TABLE IF NOT EXISTS site_errors (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE, hash TEXT NOT NULL,
        source TEXT NOT NULL, message TEXT NOT NULL, url TEXT NOT NULL DEFAULT '', detail TEXT NOT NULL DEFAULT '', count INTEGER NOT NULL DEFAULT 1,
        first_at TEXT NOT NULL, last_at TEXT NOT NULL, UNIQUE (site_id, hash));
        CREATE INDEX IF NOT EXISTS site_errors_last ON site_errors (site_id, last_at)");
    // v13: historial de páginas y entradas (se guarda la versión anterior en cada cambio) y papelera de 30 días
    db()->exec("CREATE TABLE IF NOT EXISTS content_versions (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        kind TEXT NOT NULL, lang TEXT NOT NULL, slug TEXT NOT NULL, data TEXT NOT NULL, actor_name TEXT NOT NULL DEFAULT '', created_at TEXT NOT NULL);
        CREATE INDEX IF NOT EXISTS content_versions_item ON content_versions (site_id, kind, lang, slug, id);
        CREATE TABLE IF NOT EXISTS content_trash (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        kind TEXT NOT NULL, lang TEXT NOT NULL, slug TEXT NOT NULL, title TEXT NOT NULL, data TEXT NOT NULL, actor_name TEXT NOT NULL DEFAULT '', deleted_at TEXT NOT NULL)");
    // v14: redirecciones 301 (se crean solas al cambiar el slug de una página publicada, o a mano)
    db()->exec("CREATE TABLE IF NOT EXISTS redirects (id INTEGER PRIMARY KEY, site_id INTEGER NOT NULL REFERENCES sites(id) ON DELETE CASCADE,
        lang TEXT NOT NULL DEFAULT '', from_path TEXT NOT NULL, to_path TEXT NOT NULL, hits INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL,
        UNIQUE (site_id, from_path))");
    q("INSERT OR REPLACE INTO meta (key, value) VALUES ('schema_version', ?)", [(string) UW_SCHEMA]);
}

/** Actualiza la base de datos de una instalación existente tras actualizar el código. */
function ensure_schema(): void
{
    if ((int) val("SELECT value FROM meta WHERE key = 'schema_version'") < UW_SCHEMA) migrate();
}

// ---------------------------------------------------------------- utilidades

function e(mixed $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function json_enc(mixed $v, bool $pretty = false): string
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR;
    return json_encode($v, $pretty ? $flags | JSON_PRETTY_PRINT : $flags);
}

function json_dec(?string $s, mixed $default = []): mixed
{
    if ($s === null || $s === '') return $default;
    $v = json_decode($s, true);
    return $v ?? $default;
}

function slugify(string $s): string
{
    static $map = [
        'á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'å' => 'a', 'ă' => 'a',
        'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u',
        'ñ' => 'n', 'ç' => 'c', 'ș' => 's', 'ş' => 's', 'ț' => 't', 'ţ' => 't', 'ß' => 'ss',
    ];
    $s = strtr(mb_strtolower(trim($s)), $map);
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', $s), '-');
}

function str_limit(string $s, int $len): string
{
    return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
}

function normalize_datetime(?string $s): ?string
{
    if ($s === null || trim($s) === '') return null;
    $t = strtotime(str_replace('T', ' ', $s));
    if ($t === false) throw new UwError("Fecha no válida: '$s'. Usa el formato AAAA-MM-DD HH:MM");
    return date('Y-m-d H:i:s', $t);
}

/**
 * Salida HTTP del front. Normalmente envía cabeceras; en modo captura (generación estática) las guarda,
 * para poder renderizar páginas dentro de otra petición (p. ej. desde el panel) sin alterar su respuesta.
 */
final class Out
{
    public static bool $capture = false;
    public static int $status = 200;
    public static array $headers = [];

    public static function reset(): void
    {
        self::$status = 200;
        self::$headers = [];
    }
}

function out_header(string $h, bool $replace = true, int $code = 0): void
{
    if (Out::$capture) {
        Out::$headers[strtolower(strtok($h, ':'))] = $h;
        if ($code) Out::$status = $code;
        return;
    }
    header($h, $replace, $code);
}

function out_status(int $code): void
{
    if (Out::$capture) { Out::$status = $code; return; }
    http_response_code($code);
}

function json_response(mixed $data, int $status = 200): void
{
    out_status($status);
    out_header('Content-Type: application/json; charset=utf-8');
    echo json_enc($data, true);
}

function redirect(string $to): never
{
    header('Location: ' . $to, true, 303);
    exit;
}

function is_https(): bool
{
    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443) return true;
    // X-Forwarded-Proto solo se cree si viene de un proxy de confianza (p. ej. un VPS por ZeroTier).
    return from_trusted_proxy() && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
}

/** Host de la petición en minúsculas, sin puerto. */
function request_host(): string
{
    $h = strtolower((string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    return (string) preg_replace('/:\d+$/', '', $h);
}

function request_port_suffix(): string
{
    $h = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (preg_match('/:(\d+)$/', $h, $m) && !in_array($m[1], ['80', '443'], true)) return ':' . $m[1];
    return '';
}

/** IP real del visitante. Tras un proxy de confianza se toma de X-Forwarded-For (la primera no confiable desde el final). */
function client_ip(): string
{
    $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    if (!from_trusted_proxy()) return $remote;
    $chain = array_reverse(array_filter(array_map('trim', explode(',', (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '')))));
    foreach ($chain as $ip) {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) break;
        if (!ip_in_list($ip, platform()['trusted_proxies'])) return $ip;
    }
    return $remote;
}

function from_trusted_proxy(): bool
{
    static $r = null;
    return $r ??= is_installed() && ip_in_list((string) ($_SERVER['REMOTE_ADDR'] ?? ''), platform()['trusted_proxies']);
}

/** ¿Está $ip en alguna de las IPs o redes CIDR de la lista? (IPv4 e IPv6) */
function ip_in_list(string $ip, array $list): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    foreach ($list as $entry) {
        [$net, $bits] = array_pad(explode('/', trim((string) $entry), 2), 2, null);
        $netBin = @inet_pton((string) $net);
        if ($netBin === false || strlen($netBin) !== strlen($bin)) continue;
        $bits = $bits === null ? strlen($bin) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($bin, 0, $bytes) !== substr($netBin, 0, $bytes)) continue;
        $rem = $bits % 8;
        if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($netBin[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) return true;
    }
    return false;
}

function valid_cidr(string $entry): bool
{
    [$net, $bits] = array_pad(explode('/', trim($entry), 2), 2, null);
    $bin = @inet_pton((string) $net);
    if ($bin === false) return false;
    return $bits === null || (ctype_digit($bits) && (int) $bits <= strlen($bin) * 8);
}

// ---------------------------------------------------------------- ajustes de plataforma (superadmin)

function platform_defaults(): array
{
    return [
        'admin_allowlist' => [],   // IPs/CIDR que pueden usar /admin y /mcp (vacío = todas). Ej. red ZeroTier 10.147.17.0/24
        'trusted_proxies' => [],   // proxies cuyo X-Forwarded-For/Proto se cree (p. ej. el VPS frontal por ZeroTier)
        'session_idle_minutes' => 480,
        'auto_backup' => false,    // copia global automática diaria
        'backup_keep' => 7,        // copias automáticas que se conservan
        'require_2fa' => false,    // obliga a todos los usuarios del panel a usar verificación en dos pasos
        'admin_host' => '',        // si se indica, /admin y /mcp SOLO responden en este dominio (aislado de las webs)
        'outbound_allowlist' => [],// redes privadas a las que webhooks y SMTP pueden conectar (p. ej. 127.0.0.1 o la red ZeroTier)
        'public_ip' => '',         // IP pública que se da para el DNS de los dominios (vacío = se averigua sola)
        'public_ipv6' => '',       // IPv6 pública (opcional; vacío = la del dominio del panel, si tiene)
        'hosting' => '',           // auto_tls (Caddy de install.sh) | panel (Plesk/cPanel) | vacío = se detecta
    ];
}

function platform(bool $reload = false): array
{
    static $cache = null;
    if ($cache === null || $reload) $cache = array_replace(platform_defaults(), json_dec(val("SELECT value FROM meta WHERE key = 'platform'")));
    return $cache;
}

function platform_save(array $values): void
{
    q("INSERT OR REPLACE INTO meta (key, value) VALUES ('platform', ?)", [json_enc(array_replace(platform(), $values))]);
    platform(true);
}

function request_header(string $name): ?string
{
    $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
    $v = $_SERVER[$key] ?? $_SERVER['REDIRECT_' . $key] ?? null;
    if ($v === null && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $hv) {
            if (strcasecmp($k, $name) === 0) return $hv;
        }
    }
    return $v;
}

/** Limita intentos. Devuelve false si se supera $max en $window segundos. */
function throttle(string $key, int $max, int $window): bool
{
    $t = time();
    $r = row('SELECT hits, reset_at FROM throttle WHERE key = ?', [$key]);
    if (!$r || $r['reset_at'] < $t) {
        q('INSERT OR REPLACE INTO throttle (key, hits, reset_at) VALUES (?, 1, ?)', [$key, $t + $window]);
        return true;
    }
    if ($r['hits'] >= $max) return false;
    q('UPDATE throttle SET hits = hits + 1 WHERE key = ?', [$key]);
    return true;
}

// ---------------------------------------------------------------- Markdown (subconjunto seguro)

function md(string $text): string
{
    $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $text));
    $html = '';
    $n = count($lines);
    $isBlockStart = fn(string $l) => (bool) preg_match('/^(#{1,6}\s|```|>|\||\s*([-*+]|\d+\.)\s+|(-{3,}|\*{3,})\s*$)/', $l);

    for ($i = 0; $i < $n;) {
        $line = $lines[$i];
        if (trim($line) === '') { $i++; continue; }

        if (preg_match('/^```\s*([\w+-]*)/', $line, $m)) {
            $code = [];
            for ($i++; $i < $n && !str_starts_with(trim($lines[$i]), '```'); $i++) $code[] = $lines[$i];
            $i++;
            $cls = $m[1] !== '' ? ' class="language-' . e($m[1]) . '"' : '';
            $html .= "<pre><code$cls>" . e(implode("\n", $code)) . "</code></pre>\n";
            continue;
        }
        if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m)) {
            $l = strlen($m[1]);
            $html .= "<h$l>" . md_inline(trim($m[2])) . "</h$l>\n";
            $i++;
            continue;
        }
        if (preg_match('/^(-{3,}|\*{3,})\s*$/', $line)) { $html .= "<hr>\n"; $i++; continue; }
        if (str_starts_with($line, '>')) {
            $quote = [];
            for (; $i < $n && str_starts_with($lines[$i], '>'); $i++) $quote[] = preg_replace('/^>\s?/', '', $lines[$i]);
            $html .= '<blockquote>' . md(implode("\n", $quote)) . "</blockquote>\n";
            continue;
        }
        // Tabla: | a | b | con la fila |---|---| debajo de la cabecera
        if (str_starts_with(ltrim($line), '|') && $i + 1 < $n && preg_match('/^\s*\|?\s*:?-{3,}/', $lines[$i + 1])) {
            $cells = fn(string $l) => array_map('trim', explode('|', trim(trim($l), '|')));
            $head = $cells($line);
            $rows = [];
            for ($i += 2; $i < $n && str_starts_with(ltrim($lines[$i]), '|'); $i++) $rows[] = $cells($lines[$i]);
            $html .= '<div class="md-table"><table><thead><tr>' . implode('', array_map(fn($c) => '<th>' . md_inline($c) . '</th>', $head)) . '</tr></thead><tbody>'
                . implode('', array_map(fn($r) => '<tr>' . implode('', array_map(fn($c) => '<td>' . md_inline($c) . '</td>', $r)) . '</tr>', $rows)) . "</tbody></table></div>\n";
            continue;
        }
        if (preg_match('/^\s*([-*+]|\d+\.)\s+/', $line, $m)) {
            $ordered = ctype_digit(rtrim($m[1], '.'));
            $items = []; $subs = []; $subOrdered = false;
            for (; $i < $n; $i++) {
                if (preg_match('/^(\s*)([-*+]|\d+\.)\s+(.*)$/', $lines[$i], $mm)) {
                    // Sangrado bajo un elemento: sublista de ese elemento
                    if (strlen($mm[1]) >= 2 && $items) { $subs[count($items) - 1][] = $mm[3]; $subOrdered = ctype_digit(rtrim($mm[2], '.')); continue; }
                    $items[] = $mm[3];
                    continue;
                }
                // Línea sangrada sin viñeta: continúa el último elemento
                if ($items && preg_match('/^\s{2,}\S/', $lines[$i])) {
                    $k = count($items) - 1;
                    if (!empty($subs[$k])) $subs[$k][count($subs[$k]) - 1] .= ' ' . trim($lines[$i]); else $items[$k] .= ' ' . trim($lines[$i]);
                    continue;
                }
                break;
            }
            $tag = $ordered ? 'ol' : 'ul';
            $st = $subOrdered ? 'ol' : 'ul';
            $html .= "<$tag>" . implode('', array_map(fn($k, $t) => '<li>' . md_inline($t)
                . (!empty($subs[$k]) ? "<$st>" . implode('', array_map(fn($x) => '<li>' . md_inline($x) . '</li>', $subs[$k])) . "</$st>" : '') . '</li>', array_keys($items), $items)) . "</$tag>\n";
            continue;
        }
        $para = [];
        for (; $i < $n && trim($lines[$i]) !== '' && ($para === [] || !$isBlockStart($lines[$i])); $i++) $para[] = trim($lines[$i]);
        $html .= '<p>' . md_inline(implode("\n", $para)) . "</p>\n";
    }
    return $html;
}

function md_inline(string $t): string
{
    $t = e($t);
    $codes = [];
    $t = (string) preg_replace_callback('/`([^`]+)`/', function ($m) use (&$codes) {
        $codes[] = '<code>' . $m[1] . '</code>';
        return "\x01" . (count($codes) - 1) . "\x02";
    }, $t);
    $t = (string) preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)\)/', fn($m) => safe_url($m[2])
        ? '<img src="' . $m[2] . '" alt="' . $m[1] . '" loading="lazy">' : $m[0], $t);
    $t = (string) preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', function ($m) {
        if (!safe_url($m[2])) return $m[0];
        $ext = preg_match('#^https?://#', $m[2]) ? ' rel="noopener" target="_blank"' : '';
        return '<a href="' . $m[2] . '"' . $ext . '>' . $m[1] . '</a>';
    }, $t);
    $t = (string) preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t);
    $t = (string) preg_replace('/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/s', '<em>$1</em>', $t);
    $t = str_replace("\n", "<br>\n", $t);
    return (string) preg_replace_callback("/\x01(\d+)\x02/", fn($m) => $codes[(int) $m[1]], $t);
}

/**
 * Limpia una URL para usarla en href/src: solo http(s), mailto:, tel:, rutas del propio sitio (/…, no //…),
 * anclas, consultas y rutas relativas. Cualquier otro esquema (javascript:, data:, vbscript:…) se descarta.
 */
function clean_url(mixed $url): string
{
    $u = (string) preg_replace('/[\x00-\x20\x7f]+/', '', (string) $url);
    if ($u === '' || str_starts_with($u, '//') || str_starts_with($u, '/\\') || str_starts_with($u, '\\')) return '';
    if (preg_match('#^(https?://[^/]|mailto:|tel:|/|\#|\?)#i', $u)) return $u;
    return preg_match('#^[a-z][a-z0-9+.-]*:#i', $u) ? '' : $u;
}

function safe_url(string $url): bool
{
    $u = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    return (bool) preg_match('#^(https?://|mailto:|tel:|/|\#|\.{0,2}/?[\w-])#i', $u) && !preg_match('#^\s*(javascript|data|vbscript):#i', $u);
}

// ---------------------------------------------------------------- sesión, CSRF, flash

function session_start_safe(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    // Modo estricto: no se aceptan ids de sesión inventados (fijación de sesión). En HTTPS, prefijo __Host-:
    // la cookie solo la puede fijar este mismo host, por HTTPS y para todo el sitio.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name(is_https() ? '__Host-uw_session' : 'uw_session');
    session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_csrf'] ?? ''))) {
        http_response_code(419);
        exit('Sesión caducada. Vuelve atrás y recarga la página.');
    }
}

function flash(string $msg, string $type = 'ok'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function flash_take(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

// ---------------------------------------------------------------- auditoría

/** Quién está actuando: un usuario del panel, un token MCP o el sistema. */
final class Actor
{
    public static string $type = 'system';
    public static ?int $id = null;
    public static string $name = 'sistema';

    public static function set(string $type, ?int $id, string $name): void
    {
        self::$type = $type;
        self::$id = $id;
        self::$name = $name;
    }
}

function audit(?int $siteId, string $action, string $target = '', array $details = []): void
{
    if ($siteId && function_exists('publish_mark_dirty') && preg_match('/^(page|post|site|media|design|backup\.restore)/', $action)) publish_mark_dirty($siteId);
    insert('audit_log', [
        'site_id' => $siteId, 'actor_type' => Actor::$type, 'actor_id' => Actor::$id, 'actor_name' => Actor::$name,
        'action' => $action, 'target' => $target, 'details' => json_enc((object) $details), 'created_at' => now(),
    ]);
}
