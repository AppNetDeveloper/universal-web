<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Copias de seguridad fuera del servidor.
 *
 * Cada web tiene su propio destino (lo configura un admin de esa web) y el superadmin otro para las copias
 * globales (ámbito 0). Destinos: FTP/FTPS, SFTP (clave o contraseña), S3 y compatibles (AWS, Cloudflare R2,
 * Backblaze B2, Wasabi, MinIO) y carpeta local (solo superadmin). Cada copia creada se envía sola; las webs
 * pueden programar además una copia diaria o semanal. Se conservan las últimas N en el destino y, con
 * contraseña, se cifran antes de salir del servidor. Nunca se conecta a redes privadas (salvo las permitidas
 * por el superadmin en Seguridad y red) y las contraseñas y claves se guardan cifradas.
 */

const OFFSITE_TARGETS = ['sftp', 'ftp', 's3', 'directory'];
const OFFSITE_SECRET_FIELDS = ['ftp_password', 'sftp_password', 'sftp_key', 's3_secret', 'encrypt_password'];

function offsite_defaults(): array
{
    return [
        'enabled' => false,
        'target' => 'sftp',
        'schedule' => 'off',          // off | daily | weekly (solo webs: copia programada + envío)
        'keep' => 10,                 // copias que se conservan en el destino
        'encrypt_password' => '',     // si se pone, la copia se cifra (AES-256-GCM) antes de salir
        'ftp_host' => '', 'ftp_port' => 21, 'ftp_user' => '', 'ftp_password' => '', 'ftp_path' => '/backups', 'ftp_tls' => true,
        'sftp_host' => '', 'sftp_port' => 22, 'sftp_user' => '', 'sftp_password' => '', 'sftp_key' => '', 'sftp_path' => 'backups', 'sftp_fingerprint' => '',
        's3_endpoint' => '', 's3_region' => 'us-east-1', 's3_bucket' => '', 's3_prefix' => 'universal-web/', 's3_key' => '', 's3_secret' => '', 's3_path_style' => false,
        'directory_path' => '',
    ];
}

function offsite_row(int $scope): array
{
    return row('SELECT * FROM backup_offsite WHERE scope = ?', [$scope])
        ?? ['scope' => $scope, 'config' => '{}', 'last_at' => null, 'last_status' => '', 'last_log' => '', 'last_auto' => 0];
}

/** Configuración tal cual se guarda (secretos cifrados). */
function offsite_config_raw(int $scope): array
{
    return array_replace(offsite_defaults(), json_dec(offsite_row($scope)['config']));
}

function offsite_config(int $scope): array
{
    $c = offsite_config_raw($scope);
    foreach (OFFSITE_SECRET_FIELDS as $k) $c[$k] = secret_decrypt((string) $c[$k]);
    return $c;
}

/** Qué secretos hay guardados (para el formulario: nunca se devuelven). */
function offsite_has_secrets(int $scope): array
{
    $c = offsite_config_raw($scope);
    return array_combine(OFFSITE_SECRET_FIELDS, array_map(fn($k) => (string) $c[$k] !== '', OFFSITE_SECRET_FIELDS));
}

/** Guarda la configuración. Secreto vacío = se conserva el anterior; clear_<campo> lo borra. */
function offsite_save(int $scope, array $in, bool $super): array
{
    $c = offsite_config_raw($scope);
    $c['enabled'] = !empty($in['enabled']);
    $target = (string) ($in['target'] ?? $c['target']);
    if (!in_array($target, OFFSITE_TARGETS, true)) throw new UwError(t('Destino no válido'));
    if ($target === 'directory' && !$super) throw new UwError(t('Solo un superadmin puede guardar copias en una carpeta del servidor'));
    $c['target'] = $target;
    $c['schedule'] = in_array($in['schedule'] ?? '', ['off', 'daily', 'weekly'], true) ? $in['schedule'] : 'off';
    $c['keep'] = max(1, min(365, (int) ($in['keep'] ?? $c['keep'])));
    foreach (['ftp_host', 'ftp_user', 'ftp_path', 'sftp_host', 'sftp_user', 'sftp_path', 'sftp_fingerprint', 's3_endpoint', 's3_region', 's3_bucket', 's3_prefix', 's3_key', 'directory_path'] as $k) {
        if (array_key_exists($k, $in)) $c[$k] = mb_substr(trim((string) $in[$k]), 0, 500);
    }
    foreach (['ftp_port' => 21, 'sftp_port' => 22] as $k => $def) if (array_key_exists($k, $in)) $c[$k] = max(1, min(65535, (int) $in[$k] ?: $def));
    foreach (['ftp_tls', 's3_path_style'] as $k) $c[$k] = !empty($in[$k]);
    foreach (OFFSITE_SECRET_FIELDS as $k) {
        $v = str_replace("\r\n", "\n", trim((string) ($in[$k] ?? '')));
        if (!empty($in["clear_$k"])) $c[$k] = '';
        elseif ($v !== '') $c[$k] = secret_encrypt($k === 'sftp_key' ? $v . "\n" : $v);
    }
    if ((string) secret_decrypt($c['encrypt_password']) !== '' && mb_strlen(secret_decrypt($c['encrypt_password'])) < 8) throw new UwError(t('La contraseña de cifrado debe tener al menos 8 caracteres'));
    if ($c['enabled']) offsite_validate($c);
    q('INSERT INTO backup_offsite (scope, config) VALUES (?, ?) ON CONFLICT(scope) DO UPDATE SET config = excluded.config', [$scope, json_enc($c)]);
    audit($scope ?: null, 'backup.offsite', $c['target'], ['enabled' => $c['enabled'], 'schedule' => $c['schedule']]);
    return $c;
}

/**
 * Campos de texto que acaban en comandos FTP/SFTP: sin caracteres de control (un salto de línea en la ruta
 * metía órdenes en el lote de `sftp -b`, incluida `!comando`) y usuario/servidor sin `-` inicial (se
 * leían como opciones de `sftp`). Se comprueba al guardar y otra vez al conectar.
 */
function offsite_check_fields(array $c): void
{
    foreach (['ftp_host', 'ftp_user', 'ftp_path', 'sftp_host', 'sftp_user', 'sftp_path', 'sftp_fingerprint', 's3_endpoint', 's3_region', 's3_bucket', 's3_prefix', 's3_key', 'directory_path'] as $k) {
        if (preg_match('/[\x00-\x1f\x7f]/', (string) ($c[$k] ?? ''))) throw new UwError(t('El campo {field} tiene caracteres no permitidos', ['field' => $k]));
    }
    // El resto solo para el destino elegido: un campo viejo de otro destino no bloquea guardar.
    $target = (string) ($c['target'] ?? '');
    $own = ['ftp' => ['ftp_host', 'ftp_user'], 'sftp' => ['sftp_host', 'sftp_user']][$target] ?? [];
    foreach ($own as $k) {
        if (str_starts_with((string) ($c[$k] ?? ''), '-')) throw new UwError(t('El campo {field} no puede empezar por «-»', ['field' => $k]));
    }
    if ($target !== 'sftp') return;
    // Usuario: también DOMINIO\usuario (OpenSSH de Windows). Servidor: nombre, IPv4 o IPv6 (con zona %).
    if (($c['sftp_user'] ?? '') !== '' && !preg_match('/^[A-Za-z0-9._@+\\\\][A-Za-z0-9._@+\\\\#-]{0,99}$/', (string) $c['sftp_user'])) throw new UwError(t('Usuario SFTP no válido'));
    if (($c['sftp_host'] ?? '') !== '' && !preg_match('/^[A-Za-z0-9._:%\[\]][A-Za-z0-9._:%\[\]-]{0,252}$/', (string) $c['sftp_host'])) throw new UwError(t('Servidor SFTP no válido'));
}

function offsite_validate(array $c): void
{
    offsite_check_fields($c);
    $need = fn(string $k, string $label) => trim((string) $c[$k]) === '' ? throw new UwError(t('Falta {field}', ['field' => $label])) : null;
    switch ($c['target']) {
        case 'ftp': $need('ftp_host', t('el servidor FTP')); $need('ftp_user', t('el usuario FTP')); break;
        case 'sftp':
            $need('sftp_host', t('el servidor SFTP')); $need('sftp_user', t('el usuario SFTP'));
            if ((string) $c['sftp_password'] === '' && (string) $c['sftp_key'] === '') throw new UwError(t('SFTP necesita una contraseña o una clave privada'));
            if ($c['sftp_fingerprint'] !== '' && !preg_match('#^(SHA256:)?[A-Za-z0-9+/]{43}=?$#', $c['sftp_fingerprint'])) throw new UwError(t('La huella del servidor debe ser SHA256 en base64 (la que muestra ssh-keygen -lf)'));
            break;
        case 's3':
            $need('s3_bucket', t('el bucket')); $need('s3_key', t('la clave de acceso (Access Key)'));
            if (!preg_match('/^[a-z0-9][a-z0-9.-]{1,61}[a-z0-9]$/', $c['s3_bucket'])) throw new UwError(t('Nombre de bucket no válido'));
            if ($c['s3_endpoint'] !== '' && !preg_match('#^https?://[a-z0-9.-]+(:\d+)?/?$#i', $c['s3_endpoint'])) throw new UwError(t('El endpoint S3 debe ser https://servidor (sin ruta)'));
            if (!preg_match('/^[a-z0-9-]{2,30}$/', $c['s3_region'])) throw new UwError(t('Región S3 no válida (p. ej. eu-west-1, auto)'));
            break;
        case 'directory':
            $need('directory_path', t('la carpeta'));
            if (!str_starts_with($c['directory_path'], '/')) throw new UwError(t('La carpeta debe ser una ruta absoluta'));
            $real = realpath($c['directory_path']) ?: $c['directory_path'];
            foreach ([UW_PUBLIC, UW_ROOT . '/app', UW_ROOT . '/themes'] as $bad) if (str_starts_with($real . '/', rtrim($bad, '/') . '/')) throw new UwError(t('Esa carpeta no puede usarse para copias (es pública o de la aplicación)'));
            break;
    }
}

// ---------------------------------------------------------------- envío

/** Envía una copia (ruta absoluta en data/backups) al destino del ámbito. Devuelve el registro. */
function offsite_send(int $scope, string $file): array
{
    $c = offsite_config($scope);
    if (!$c['enabled']) throw new UwError(t('La copia fuera del servidor no está activada'));
    offsite_validate($c);
    @set_time_limit(0);
    $log = [];
    $name = basename($file);
    $upload = $file;
    $tmp = null;
    if ($c['encrypt_password'] !== '' && !str_ends_with($name, '.enc')) {
        $tmp = bk_work('offsite') . '/out.enc';
        bk_encrypt_file($file, $tmp, $c['encrypt_password']);
        $upload = $tmp;
        $name .= '.enc';
        $log[] = t('Copia cifrada antes de enviarla');
    }
    try {
        $t = offsite_target($c);
        try {
            $t->put($name, $upload);
            $log[] = t('Enviada: {name} ({size})', ['name' => $name, 'size' => bk_human_size((int) filesize($upload))]);
            // Retención en el destino: se conservan las N más recientes de esta misma web (o globales).
            $prefix = (string) preg_replace('/-\d{8}-\d{6}.*$/', '-', $name);
            $mine = array_values(array_filter($t->list(), fn($n) => preg_match('/^' . preg_quote($prefix, '/') . '\d{8}-\d{6}.*\.tar\.gz(\.enc)?$/', $n)));
            rsort($mine);
            foreach (array_slice($mine, (int) $c['keep']) as $old) { $t->delete($old); $log[] = t('Borrada la copia antigua {name}', ['name' => $old]); }
        } finally {
            $t->close();
        }
        offsite_record($scope, 'ok', $log);
        return ['ok' => true, 'name' => $name, 'log' => $log];
    } catch (UwError $e) {
        $log[] = $e->getMessage();
        offsite_record($scope, 'error', $log);
        throw $e;
    } finally {
        if ($tmp) bk_rmtree(dirname($tmp));
    }
}

function offsite_record(int $scope, string $status, array $log): void
{
    q('INSERT INTO backup_offsite (scope, config, last_at, last_status, last_log) VALUES (?, ?, ?, ?, ?)
        ON CONFLICT(scope) DO UPDATE SET last_at = excluded.last_at, last_status = excluded.last_status, last_log = excluded.last_log',
        [$scope, '{}', now(), $status, implode("\n", $log)]);
}

/** Tras crear cualquier copia: si su ámbito tiene destino activo, se envía. Nunca hace fallar la copia local. */
function offsite_after_backup(?int $siteId, string $file): ?array
{
    $scope = (int) ($siteId ?? 0);
    if (!offsite_config_raw($scope)['enabled']) return null;
    try {
        return offsite_send($scope, $file);
    } catch (Throwable $e) {
        error_log('[universal-web] copia remota: ' . $e->getMessage());
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

/** Prueba de conexión: sube un archivo pequeño, comprueba que aparece y lo borra. */
function offsite_test(int $scope): array
{
    $c = offsite_config($scope);
    offsite_validate($c);
    $name = 'uw-test-' . bin2hex(random_bytes(4)) . '.txt';
    $f = bk_work('offsite-test') . "/$name";
    file_put_contents($f, "Universal Web: prueba de conexión " . now() . "\n");
    $log = [];
    try {
        $t = offsite_target($c);
        try {
            $t->put($name, $f);
            $log[] = t('Subida de prueba correcta');
            if (!in_array($name, $t->list(), true)) throw new UwError(t('El archivo de prueba se subió pero no aparece al listar la carpeta'));
            $log[] = t('Listado correcto');
            $t->delete($name);
            $log[] = t('Borrado correcto');
        } finally {
            $t->close();
        }
        offsite_record($scope, 'ok', array_merge([t('Prueba de conexión')], $log));
        return $log;
    } catch (UwError $e) {
        offsite_record($scope, 'error', array_merge([t('Prueba de conexión')], $log, [$e->getMessage()]));
        throw $e;
    } finally {
        bk_rmtree(dirname($f));
    }
}

/** Copias programadas por web (diaria o semanal) con envío. Se llama tras responder, como la copia automática global. */
function offsite_auto_tick(): void
{
    try {
        $due = [];
        foreach (rows("SELECT scope, config, last_auto FROM backup_offsite WHERE scope > 0") as $r) {
            $c = array_replace(offsite_defaults(), json_dec($r['config']));
            if (!$c['enabled'] || $c['schedule'] === 'off') continue;
            if ((int) $r['last_auto'] > time() - ($c['schedule'] === 'weekly' ? 7 * 86400 : 86400)) continue;
            if (site_by_id((int) $r['scope'])) $due[] = (int) $r['scope'];
        }
        if (!$due) return;
        if (PHP_SAPI !== 'cli') {
            if (!function_exists('fastcgi_finish_request')) return; // sin PHP-FPM: usa el cron «php bin/uw backup --auto»
            fastcgi_finish_request();
        }
        $lock = @fopen(bk_dir() . '/.offsite.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) return;
        @ignore_user_abort(true);
        $prev = [Actor::$type, Actor::$id, Actor::$name];
        Actor::set('system', null, 'copia programada');
        try {
            foreach ($due as $siteId) {
                // Si falla, se reintenta en 1 hora y no a cada visita.
                q('UPDATE backup_offsite SET last_auto = ? WHERE scope = ?', [time() - 86400 * 7 + 3600, $siteId]);
                try {
                    backup_create($siteId, null, true);
                    q('UPDATE backup_offsite SET last_auto = ? WHERE scope = ?', [time(), $siteId]);
                } catch (Throwable $e) {
                    error_log('[universal-web] copia programada de la web ' . $siteId . ': ' . $e->getMessage());
                }
            }
        } finally {
            Actor::set(...$prev);
            flock($lock, LOCK_UN);
        }
    } catch (Throwable $e) {
        error_log('[universal-web] copias programadas: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------- destinos

interface OffsiteTarget
{
    public function put(string $name, string $local): void;
    /** Nombres de archivo de la carpeta de destino. */
    public function list(): array;
    public function delete(string $name): void;
    public function close(): void;
}

function offsite_target(array $c): OffsiteTarget
{
    offsite_check_fields($c);
    return match ($c['target']) {
        'ftp' => new OffsiteFtp($c),
        'sftp' => offsite_sftp_cli_available() && $c['sftp_key'] !== '' ? new OffsiteSftpCli($c) : new OffsiteSftpCurl($c),
        's3' => new OffsiteS3($c),
        'directory' => new OffsiteDirectory($c),
    };
}

function offsite_sftp_cli_available(): bool
{
    return function_exists('exec') && trim((string) @shell_exec('command -v sftp 2>/dev/null')) !== '';
}

final class OffsiteDirectory implements OffsiteTarget
{
    private string $dir;

    public function __construct(array $c)
    {
        $this->dir = rtrim($c['directory_path'], '/');
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0750, true)) throw new UwError(t('No se puede crear la carpeta {dir}', ['dir' => $this->dir]));
        if (!is_writable($this->dir)) throw new UwError(t('Sin permiso de escritura en {dir}', ['dir' => $this->dir]));
    }

    public function put(string $name, string $local): void
    {
        if (!@copy($local, "$this->dir/$name")) throw new UwError(t('No se pudo copiar a {dir}', ['dir' => $this->dir]));
        @chmod("$this->dir/$name", 0640);
    }

    public function list(): array
    {
        return array_values(array_map('basename', array_filter(glob("$this->dir/*") ?: [], 'is_file')));
    }

    public function delete(string $name): void
    {
        @unlink("$this->dir/" . basename($name));
    }

    public function close(): void {}
}

final class OffsiteFtp implements OffsiteTarget
{
    /** @var \FTP\Connection|resource */
    private $conn;
    private string $path;

    public function __construct(array $c)
    {
        if (!function_exists('ftp_connect')) throw new UwError(t('Este servidor no tiene la extensión FTP de PHP: usa SFTP o S3'));
        $ip = outbound_resolve($c['ftp_host']);
        $conn = $c['ftp_tls'] ? (function_exists('ftp_ssl_connect') ? @ftp_ssl_connect($ip, (int) $c['ftp_port'], 30) : false) : @ftp_connect($ip, (int) $c['ftp_port'], 30);
        if (!$conn) throw new UwError(t('No se pudo conectar a {host}:{port}', ['host' => $c['ftp_host'], 'port' => $c['ftp_port']]) . ($c['ftp_tls'] ? ' (FTPS)' : ''));
        if (!@ftp_login($conn, $c['ftp_user'], $c['ftp_password'])) { ftp_close($conn); throw new UwError(t('FTP: usuario o contraseña incorrectos')); }
        // Modo pasivo a la MISMA IP comprobada: la dirección del «227» la elige el servidor y podía
        // apuntar a un puerto interno de esta máquina (SSRF).
        if (defined('FTP_USEPASVADDRESS')) ftp_set_option($conn, FTP_USEPASVADDRESS, false);
        ftp_pasv($conn, true);
        $this->conn = $conn;
        $this->path = '/' . trim($c['ftp_path'], '/');
        $acc = '';
        foreach (array_filter(explode('/', $this->path)) as $part) { $acc .= '/' . $part; @ftp_mkdir($conn, $acc); }
    }

    public function put(string $name, string $local): void
    {
        if (!@ftp_put($this->conn, rtrim($this->path, '/') . '/' . $name, $local, FTP_BINARY)) throw new UwError(t('FTP: no se pudo subir {name}', ['name' => $name]));
    }

    public function list(): array
    {
        return array_values(array_map('basename', @ftp_nlist($this->conn, $this->path) ?: []));
    }

    public function delete(string $name): void
    {
        @ftp_delete($this->conn, rtrim($this->path, '/') . '/' . basename($name));
    }

    public function close(): void
    {
        @ftp_close($this->conn);
    }
}

/**
 * SFTP con el programa sftp del sistema (clave privada). Con huella configurada se exige esa huella;
 * sin ella, la del servidor se recuerda la primera vez.
 */
final class OffsiteSftpCli implements OffsiteTarget
{
    private string $key;
    private ?string $knownHosts = null;
    private string $hostKeyAlgo = '';
    private string $dest;
    private array $opts;
    private string $path;

    public function __construct(array $c)
    {
        $ip = outbound_resolve($c['sftp_host']);
        $this->key = tempnam(sys_get_temp_dir(), 'uwk');
        try {
            file_put_contents($this->key, $c['sftp_key']);
            chmod($this->key, 0600);
            $this->path = rtrim($c['sftp_path'], '/') ?: '.';
            $this->dest = $c['sftp_user'] . '@' . $ip;
            $port = (int) $c['sftp_port'];
            // Se conecta a la IP ya comprobada y se identifica el servidor por su nombre (HostKeyAlias).
            $hostKey = ['-o', 'StrictHostKeyChecking=accept-new', '-o', 'UserKnownHostsFile=' . bk_dir() . '/known_hosts'];
            if ((string) $c['sftp_fingerprint'] !== '') {
                $this->knownHosts = $this->pinnedKnownHosts($ip, $port, (string) $c['sftp_host'], (string) $c['sftp_fingerprint']);
                $hostKey = ['-o', 'StrictHostKeyChecking=yes', '-o', 'UserKnownHostsFile=' . $this->knownHosts, '-o', 'HostKeyAlgorithms=' . $this->hostKeyAlgo];
            }
            $this->opts = array_merge(['-i', $this->key, '-P', (string) $port, '-o', 'BatchMode=yes'], $hostKey,
                ['-o', 'HostKeyAlias=' . $c['sftp_host'], '-o', 'ConnectTimeout=20']);
            $this->run(['-mkdir ' . $this->quote($this->path)]);
        } catch (Throwable $e) {
            $this->close(); // la clave privada no se queda en /tmp si la conexión falla
            throw $e;
        }
    }

    /** known_hosts temporal con SOLO la clave del servidor cuya huella SHA256 coincide con la configurada. */
    private function pinnedKnownHosts(string $ip, int $port, string $alias, string $fingerprint): string
    {
        if (trim((string) @shell_exec('command -v ssh-keyscan 2>/dev/null')) === '') throw new UwError(t('SFTP: falta el programa ssh-keyscan para comprobar la huella del servidor'));
        $want = rtrim(preg_replace('/^SHA256:/', '', trim($fingerprint)), '=');
        exec('ssh-keyscan -T 10 -p ' . $port . ' -- ' . escapeshellarg($ip) . ' 2>/dev/null', $lines);
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line));
            if (count($parts) < 3 || str_starts_with($parts[0], '#')) continue;
            $blob = base64_decode($parts[2], true);
            if ($blob === false || !hash_equals($want, rtrim(base64_encode(hash('sha256', $blob, true)), '='))) continue;
            $file = tempnam(sys_get_temp_dir(), 'uwh');
            file_put_contents($file, "$alias {$parts[1]} {$parts[2]}\n");
            $this->hostKeyAlgo = $parts[1]; // si no, el cliente podría negociar otro tipo de clave y fallar
            return $file;
        }
        throw new UwError(t('SFTP: la huella del servidor no coincide con la configurada'));
    }

    private function quote(string $p): string
    {
        // Cada línea del lote es una orden de sftp: un salto de línea aquí sería otra orden (`!comando`).
        if (preg_match('/[\x00-\x1f\x7f]/', $p)) throw new UwError(t('Ruta SFTP no válida'));
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $p) . '"';
    }

    private function run(array $commands): array
    {
        $batch = tempnam(sys_get_temp_dir(), 'uwb');
        file_put_contents($batch, implode("\n", $commands) . "\n");
        $cmd = 'sftp -b ' . escapeshellarg($batch) . ' ' . implode(' ', array_map('escapeshellarg', $this->opts)) . ' -- ' . escapeshellarg($this->dest) . ' 2>&1';
        exec($cmd, $out, $code);
        unlink($batch);
        if ($code !== 0) throw new UwError(t('SFTP falló: {err}', ['err' => mb_substr(implode(' ', array_filter($out, fn($l) => !str_starts_with($l, 'sftp>'))), 0, 300)]));
        return $out;
    }

    public function put(string $name, string $local): void
    {
        $this->run(['put ' . $this->quote($local) . ' ' . $this->quote("$this->path/$name")]);
    }

    public function list(): array
    {
        $out = $this->run(['ls -1 ' . $this->quote($this->path)]);
        return array_values(array_filter(array_map(fn($l) => basename(trim($l)), array_filter($out, fn($l) => !str_starts_with($l, 'sftp>'))), fn($n) => $n !== ''));
    }

    public function delete(string $name): void
    {
        $this->run(['-rm ' . $this->quote($this->path . '/' . basename($name))]);
    }

    public function close(): void
    {
        @unlink($this->key);
        if ($this->knownHosts !== null) @unlink($this->knownHosts);
    }
}

/** SFTP con cURL (contraseña o clave). Con huella SHA256 configurada se verifica el servidor. */
final class OffsiteSftpCurl implements OffsiteTarget
{
    private array $c;
    private string $ip;
    private ?string $key = null;

    public function __construct(array $c)
    {
        if (!function_exists('curl_init') || !in_array('sftp', curl_version()['protocols'] ?? [], true)) {
            throw new UwError(t('Este servidor no puede usar SFTP (cURL sin SFTP ni programa sftp con clave): usa FTPS o S3'));
        }
        $this->c = $c;
        $this->ip = outbound_resolve($c['sftp_host']);
        if ($c['sftp_key'] !== '') {
            $this->key = tempnam(sys_get_temp_dir(), 'uwk');
            file_put_contents($this->key, $c['sftp_key']);
            chmod($this->key, 0600);
        }
    }

    private function url(string $file = ''): string
    {
        $path = '/' . ltrim($this->c['sftp_path'], '/');
        // Rutas relativas a la carpeta del usuario: sftp://host/~/ruta
        if (!str_starts_with($this->c['sftp_path'], '/')) $path = '/~/' . trim($this->c['sftp_path'], '/');
        return 'sftp://' . $this->c['sftp_host'] . ':' . (int) $this->c['sftp_port'] . rtrim($path, '/') . '/' . rawurlencode($file);
    }

    private function curl(string $url, array $opts): string
    {
        $h = curl_init($url);
        $base = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 3600,
            CURLOPT_RESOLVE => [$this->c['sftp_host'] . ':' . (int) $this->c['sftp_port'] . ':' . $this->ip],
            CURLOPT_USERNAME => $this->c['sftp_user'], CURLOPT_FTP_CREATE_MISSING_DIRS => CURLFTP_CREATE_DIR,
        ];
        if ($this->key) { $base[CURLOPT_SSH_PRIVATE_KEYFILE] = $this->key; $base[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PUBLICKEY; }
        else { $base[CURLOPT_PASSWORD] = $this->c['sftp_password']; $base[CURLOPT_SSH_AUTH_TYPES] = CURLSSH_AUTH_PASSWORD; }
        if ($this->c['sftp_fingerprint'] !== '' && defined('CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256')) {
            $base[CURLOPT_SSH_HOST_PUBLIC_KEY_SHA256] = rtrim(preg_replace('/^SHA256:/', '', $this->c['sftp_fingerprint']), '=');
        }
        curl_setopt_array($h, $opts + $base);
        $out = curl_exec($h);
        $err = curl_error($h);
        if ($out === false) throw new UwError(t('SFTP falló: {err}', ['err' => $err]));
        return (string) $out;
    }

    public function put(string $name, string $local): void
    {
        $fp = fopen($local, 'rb');
        try {
            $this->curl($this->url($name), [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fp, CURLOPT_INFILESIZE => filesize($local)]);
        } finally {
            fclose($fp);
        }
    }

    public function list(): array
    {
        $out = $this->curl($this->url(), [CURLOPT_DIRLISTONLY => true]);
        return array_values(array_filter(array_map('trim', explode("\n", $out)), fn($n) => $n !== '' && $n !== '.' && $n !== '..'));
    }

    public function delete(string $name): void
    {
        // Los comandos SFTP no expanden «~»: una ruta relativa ya es relativa a la carpeta del usuario.
        $dir = str_starts_with($this->c['sftp_path'], '/') ? '/' . trim($this->c['sftp_path'], '/') : trim($this->c['sftp_path'], '/');
        $this->curl($this->url(), [CURLOPT_QUOTE => ['rm "' . $dir . '/' . basename($name) . '"'], CURLOPT_NOBODY => true]);
    }

    public function close(): void
    {
        if ($this->key) @unlink($this->key);
    }
}

/** S3 y compatibles con firma AWS Signature V4 (sin SDK). */
final class OffsiteS3 implements OffsiteTarget
{
    private array $c;
    private string $host;
    private string $scheme = 'https';
    private int $port = 443;
    private string $ip;

    public function __construct(array $c)
    {
        if (!function_exists('curl_init')) throw new UwError(t('Este PHP no tiene cURL'));
        $this->c = $c;
        $ep = $c['s3_endpoint'] !== '' ? $c['s3_endpoint'] : 'https://s3.' . $c['s3_region'] . '.amazonaws.com';
        $u = parse_url($ep);
        $this->scheme = strtolower((string) ($u['scheme'] ?? 'https')) === 'http' ? 'http' : 'https'; // http solo para MinIO en red propia
        $this->port = (int) ($u['port'] ?? ($this->scheme === 'http' ? 80 : 443));
        $base = strtolower((string) $u['host']);
        $this->host = $c['s3_path_style'] ? $base : $c['s3_bucket'] . '.' . $base;
        $this->ip = outbound_resolve($this->host);
    }

    private function key(string $name): string
    {
        $p = trim($this->c['s3_prefix'], '/');
        return ($p !== '' ? $p . '/' : '') . $name;
    }

    private function request(string $method, string $objectKey, array $query = [], ?string $file = null): array
    {
        $path = '/' . ($this->c['s3_path_style'] ? $this->c['s3_bucket'] . '/' : '') . $objectKey;
        $uri = implode('/', array_map('rawurlencode', explode('/', $path)));
        ksort($query);
        $qs = implode('&', array_map(fn($k, $v) => rawurlencode((string) $k) . '=' . rawurlencode((string) $v), array_keys($query), $query));
        $payload = $file !== null ? hash_file('sha256', $file) : hash('sha256', '');
        $date = gmdate('Ymd\THis\Z');
        $day = substr($date, 0, 8);
        $hostHeader = $this->host . ($this->port !== ($this->scheme === 'http' ? 80 : 443) ? ':' . $this->port : '');
        $headers = ['host' => $hostHeader, 'x-amz-content-sha256' => $payload, 'x-amz-date' => $date];
        $signed = implode(';', array_keys($headers));
        $canon = "$method\n$uri\n$qs\n" . implode('', array_map(fn($k, $v) => "$k:$v\n", array_keys($headers), $headers)) . "\n$signed\n$payload";
        $scope = "$day/{$this->c['s3_region']}/s3/aws4_request";
        $toSign = "AWS4-HMAC-SHA256\n$date\n$scope\n" . hash('sha256', $canon);
        $k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $this->c['s3_region'], hash_hmac('sha256', $day, 'AWS4' . $this->c['s3_secret'], true), true), true), true);
        $auth = "AWS4-HMAC-SHA256 Credential={$this->c['s3_key']}/$scope, SignedHeaders=$signed, Signature=" . hash_hmac('sha256', $toSign, $k);

        $h = curl_init("{$this->scheme}://$hostHeader$uri" . ($qs !== '' ? "?$qs" : ''));
        $opts = [
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CUSTOMREQUEST => $method, CURLOPT_CONNECTTIMEOUT => 20, CURLOPT_TIMEOUT => 3600,
            CURLOPT_RESOLVE => ["{$this->host}:{$this->port}:{$this->ip}"],
            CURLOPT_HTTPHEADER => ["Authorization: $auth", "x-amz-content-sha256: $payload", "x-amz-date: $date"],
        ];
        $fp = null;
        if ($file !== null) {
            $fp = fopen($file, 'rb');
            $opts += [CURLOPT_UPLOAD => true, CURLOPT_INFILE => $fp, CURLOPT_INFILESIZE => filesize($file)];
        }
        curl_setopt_array($h, $opts);
        $body = curl_exec($h);
        $status = (int) curl_getinfo($h, CURLINFO_RESPONSE_CODE);
        $err = curl_error($h);
        if ($fp) fclose($fp);
        if ($body === false) throw new UwError(t('S3: no se pudo conectar: {err}', ['err' => $err]));
        if ($status >= 300) {
            preg_match('#<Code>([^<]+)</Code>.*?<Message>([^<]*)</Message>#s', (string) $body, $m);
            throw new UwError(t('S3 respondió {status}: {err}', ['status' => $status, 'err' => $m ? "$m[1] — $m[2]" : mb_substr(strip_tags((string) $body), 0, 200)]));
        }
        return [$status, (string) $body];
    }

    public function put(string $name, string $local): void
    {
        $this->request('PUT', $this->key($name), [], $local);
    }

    public function list(): array
    {
        $names = [];
        $token = null;
        $prefix = $this->key('');
        do {
            $q = ['list-type' => '2', 'prefix' => $prefix] + ($token ? ['continuation-token' => $token] : []);
            [, $xml] = $this->request('GET', '', $q);
            preg_match_all('#<Key>([^<]+)</Key>#', $xml, $m);
            foreach ($m[1] as $k) $names[] = basename(html_entity_decode($k, ENT_XML1));
            $token = preg_match('#<NextContinuationToken>([^<]+)</NextContinuationToken>#', $xml, $t) ? html_entity_decode($t[1], ENT_XML1) : null;
        } while ($token);
        return $names;
    }

    public function delete(string $name): void
    {
        $this->request('DELETE', $this->key(basename($name)));
    }

    public function close(): void {}
}

// ---------------------------------------------------------------- panel

/** Datos para el formulario: configuración sin secretos, qué secretos hay, último envío y qué admite este servidor. */
function offsite_view(int $scope): array
{
    $c = offsite_config_raw($scope);
    foreach (OFFSITE_SECRET_FIELDS as $k) $c[$k] = '';
    $r = offsite_row($scope);
    return ['scope' => $scope, 'cfg' => $c, 'has' => offsite_has_secrets($scope), 'last_at' => $r['last_at'], 'last_status' => $r['last_status'], 'last_log' => $r['last_log'],
        'can' => ['ftp' => function_exists('ftp_connect'), 'sftp' => (offsite_sftp_cli_available() || (function_exists('curl_init') && in_array('sftp', curl_version()['protocols'] ?? [], true))),
            'sftp_password' => function_exists('curl_init') && in_array('sftp', curl_version()['protocols'] ?? [], true), 's3' => function_exists('curl_init')]];
}

function adm_offsite_scope(array $c): int
{
    if (!empty($_POST['global'])) {
        if ($c['role'] !== 'super') throw new UwError(t('Solo el superadmin puede configurar el destino de las copias globales'));
        return 0;
    }
    return $c['svc']->id();
}

function adm_backup_offsite_save(array $c): void
{
    $scope = adm_offsite_scope($c);
    offsite_save($scope, $_POST, $c['role'] === 'super');
    flash(t('Destino de las copias guardado.'));
    redirect(ADM . '/backups#offsite-' . ($scope ?: 'global'));
}

function adm_backup_offsite_test(array $c): void
{
    $scope = adm_offsite_scope($c);
    offsite_save($scope, $_POST, $c['role'] === 'super'); // se prueba lo que hay en el formulario (y queda guardado)
    offsite_test($scope);
    flash(t('Conexión correcta: se pudo subir, listar y borrar un archivo de prueba.'));
    redirect(ADM . '/backups#offsite-' . ($scope ?: 'global'));
}

function adm_backup_offsite_send(array $c): void
{
    $scope = adm_offsite_scope($c);
    $name = basename((string) ($_POST['name'] ?? ''));
    $info = backup_info($name) ?? throw new UwError(t('Copia no encontrada'));
    if ($scope === 0 ? $info['type'] !== 'global' : ($info['type'] !== 'site' || $info['site_slug'] !== $c['svc']->site['slug'])) throw new UwError(t('Esa copia no es de este ámbito'));
    $r = offsite_send($scope, bk_dir() . '/' . $name);
    flash(t('Copia enviada fuera del servidor: {name}', ['name' => $r['name']]));
    redirect(ADM . '/backups#offsite-' . ($scope ?: 'global'));
}
