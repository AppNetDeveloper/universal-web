<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Caché de página completa (0.4.0).
 *
 * La primera visita a una página pública genera el HTML con PHP y lo guarda en data/cache/pages/<web>/ junto con sus
 * cabeceras. Las siguientes se sirven desde public/index.php ANTES de cargar la aplicación (sin base de datos ni temas):
 * leer un archivo y enviarlo, casi lo mismo que una web estática.
 *
 * Nunca se guarda ni se sirve desde caché: lo que no sea GET/HEAD, URLs con consulta (?…), visitantes con cookies del
 * panel o de vista previa (uw_session, uw_ver, uw_demo_*, uw_theme_preview, uw_tpl_preview), peticiones de Markdown
 * (Accept: text/markdown) o de agentes de IA, webs privadas, respuestas que no son 200 o que fijan cookies, y el panel,
 * el MCP o la API. Se vacía sola con cada cambio (todo pasa por audit()), al cambiar el código (fecha de app/core.php)
 * y caduca en una hora (antes si hay una entrada programada que se publica antes).
 *
 * Este archivo lo carga public/index.php sin el resto de la aplicación: la parte «rápida» no puede usar sus funciones.
 */

const PAGE_CACHE_TTL = 3600;
/** Tope de páginas guardadas por web (que nadie llene el disco pidiendo direcciones distintas). */
const PAGE_CACHE_MAX_FILES = 5000;
/** Agentes de IA (reciben Markdown si la web lo tiene activado): nunca se les sirve la caché HTML. */
const PAGE_CACHE_AI_UA = '/GPTBot|OAI-SearchBot|ChatGPT-User|ClaudeBot|Claude-SearchBot|Claude-User|Google-Extended|PerplexityBot|Perplexity-User|CCBot|Applebot-Extended|Meta-ExternalAgent|Bytespider/i';

final class PageCache
{
    public static bool $skip = false;
    public static bool $capturing = false;
    public static ?int $siteId = null;
    public static string $key = '';

    /** Esta respuesta no se puede guardar en caché (vista previa, Markdown negociado, cookies…). */
    public static function skip(): void
    {
        self::$skip = true;
    }
}

function page_cache_dir(): string
{
    return (getenv('UW_DATA') ?: dirname(__DIR__) . '/data') . '/cache/pages';
}

/** ¿Se puede servir/guardar esta petición? Mismas reglas para leer y para escribir. */
function page_cache_request_ok(): bool
{
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if ($m !== 'GET' && $m !== 'HEAD') return false;
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    if (str_contains($uri, '?')) return false;
    if (preg_match('#^/(admin|mcp|api|install|_uw|_ver|__acceso|site-assets|theme-assets|media|assets)(/|$)#', $uri)) return false;
    foreach (array_keys($_COOKIE) as $c) {
        if ($c !== 'uw_consent' && preg_match('/^(__Host-)?uw_/', (string) $c)) return false;
    }
    if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/markdown')) return false;
    if (preg_match(PAGE_CACHE_AI_UA, (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''))) return false;
    return true;
}

/**
 * Clave de la copia: esquema + host (con puerto) + ruta. El esquema se decide igual que is_https(): X-Forwarded-Proto
 * solo cuenta si viene de un proxy de confianza (la lista se copia al archivo-bandera), para que nadie pueda envenenar
 * la copia «https» con una petición http que traiga esa cabecera.
 */
function page_cache_key(?array $trusted = null): string
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    if (!$https && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        $trusted ??= function_exists('platform') && is_installed() ? (array) platform()['trusted_proxies'] : [];
        $https = page_cache_ip_in((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $trusted);
    }
    $path = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    if ($path !== '/' && str_ends_with($path, '/')) $path = rtrim($path, '/');
    return sha1(($https ? 'https' : 'http') . '|' . strtolower((string) ($_SERVER['HTTP_HOST'] ?? '')) . '|' . $path);
}

/** ip_in_list() mínima para la parte rápida (sin la aplicación cargada). */
function page_cache_ip_in(string $ip, array $list): bool
{
    $bin = @inet_pton($ip);
    if ($bin === false) return false;
    foreach ($list as $entry) {
        [$net, $bits] = array_pad(explode('/', trim((string) $entry), 2), 2, null);
        $nb = @inet_pton((string) $net);
        if ($nb === false || strlen($nb) !== strlen($bin)) continue;
        $bits = $bits === null ? strlen($bin) * 8 : (int) $bits;
        $bytes = intdiv($bits, 8);
        if (substr($bin, 0, $bytes) !== substr($nb, 0, $bytes)) continue;
        $rem = $bits % 8;
        if ($rem === 0 || ((ord($bin[$bytes]) ^ ord($nb[$bytes])) & (0xFF << (8 - $rem)) & 0xFF) === 0) return true;
    }
    return false;
}

/**
 * Parte rápida (sin la aplicación): si hay copia válida, la envía y devuelve true. Se llama desde public/index.php.
 * Respeta el modo mantenimiento y las peticiones condicionales (If-None-Match → 304).
 */
function page_cache_fast_serve(): bool
{
    $base = page_cache_dir();
    $flag = @file_get_contents("$base/../enabled");
    if ($flag === false || is_file("$base/../../maintenance.json") || !page_cache_request_ok()) return false;
    $cfg = json_decode($flag, true);
    $key = page_cache_key(is_array($cfg) ? (array) ($cfg['trusted_proxies'] ?? []) : []);
    $map = @file_get_contents("$base/../hosts/" . substr($key, 0, 2) . "/$key");
    if ($map === false || !ctype_digit(trim($map))) return false;
    $file = "$base/" . trim($map) . "/$key.html";
    $fh = @fopen($file, 'rb');
    if (!$fh) return false;
    $meta = json_decode((string) fgets($fh), true);
    $core = @filemtime(dirname(__DIR__) . '/app/core.php');
    if (!is_array($meta) || ($meta['exp'] ?? 0) < time() || ($meta['core'] ?? 0) !== $core) { fclose($fh); @unlink($file); return false; }
    header_remove('X-Powered-By');
    foreach ((array) ($meta['h'] ?? []) as $h) header((string) $h, false);
    header('X-UW-Cache: HIT');
    header('Age: ' . max(0, time() - (int) $meta['at']));
    header('ETag: "' . $meta['etag'] . '"');
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), ' "W/') === $meta['etag']) { http_response_code(304); fclose($fh); return true; }
    header('Content-Length: ' . (int) $meta['len']);
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') fpassthru($fh);
    fclose($fh);
    return true;
}

/** Interruptor global (Panel → Seguridad y red). Por defecto activada. */
function page_cache_enabled(): bool
{
    return is_installed() && !empty(platform()['page_cache']);
}

/** Deja el archivo-bandera que lee la parte rápida (no puede consultar la base de datos). */
function page_cache_sync_flag(): void
{
    $flag = page_cache_dir() . '/../enabled';
    if (page_cache_enabled()) {
        $want = json_encode(['trusted_proxies' => array_values((array) platform()['trusted_proxies'])]) . "\n";
        if (@file_get_contents($flag) !== $want) { @mkdir(dirname($flag), 0750, true); @file_put_contents($flag, $want); page_cache_clear(null); }
    } elseif (is_file($flag)) {
        @unlink($flag);
        page_cache_clear(null);
    }
}

/** Empieza a capturar la respuesta del front de una web (si se puede guardar). */
function page_cache_begin(SiteService $svc): void
{
    PageCache::$skip = false;
    PageCache::$capturing = false;
    if (Out::$capture || !page_cache_enabled() || !page_cache_request_ok() || $svc->isPrivate() || !empty($GLOBALS['uw_site_preview'])) return;
    // Solo los dominios de la web (no una IP ni un Host inventado) y sin puertos raros: cada Host o puerto distinto sería
    // otra copia, y alguien podría llenar el disco pidiendo la misma página con miles de cabeceras Host diferentes.
    $hostHeader = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if (!in_array(request_host(), site_domains($svc->id()), true)) return;
    if (preg_match('/:(\d+)$/', $hostHeader, $pm) && (int) $pm[1] !== (int) ($_SERVER['SERVER_PORT'] ?? 0)) return;
    PageCache::$capturing = true;
    PageCache::$siteId = $svc->id();
    PageCache::$key = page_cache_key();
    ob_start();
}

/** Termina la captura: envía lo generado y, si cumple las reglas, lo guarda. */
function page_cache_end(): void
{
    if (!PageCache::$capturing) return;
    PageCache::$capturing = false;
    $body = (string) ob_get_clean();
    $status = http_response_code() ?: 200;
    $headers = headers_list();
    $ok = !PageCache::$skip && $status === 200 && $body !== '';
    $keep = [];
    foreach ($headers as $h) {
        $name = strtolower(strtok($h, ':'));
        if ($name === 'set-cookie' || $name === 'location') $ok = false;
        if ($name === 'cache-control' && preg_match('/no-store|private/i', $h)) $ok = false;
        if (in_array($name, ['x-powered-by', 'content-length', 'date', 'set-cookie', 'etag', 'age', 'x-uw-cache'], true)) continue;
        $keep[] = $h;
    }
    $type = strtolower(implode(' ', preg_grep('/^content-type:/i', $headers) ?: []));
    if (!preg_match('#text/html|application/xml|text/xml|text/plain|text/markdown|application/rss#', $type)) $ok = false;
    if ($ok) {
        // Formulario de contacto: su marca de tiempo firmada caduca; la página guardada pide una nueva al cargar.
        if (str_contains($body, 'name="_ts"') && !str_contains($body, 'data-uw-ts')) {
            $body = str_replace('</body>', '<script data-uw-ts>fetch("/api/form-token").then(function(r){return r.json()}).then(function(d){'
                . 'document.querySelectorAll(\'input[name="_ts"]\').forEach(function(i){i.value=d._ts})}).catch(function(){});</script>' . "\n</body>", $body);
        }
        page_cache_store((int) PageCache::$siteId, PageCache::$key, $body, $keep);
        header('X-UW-Cache: MISS');
    }
    header('ETag: "' . substr(sha1($body), 0, 20) . '"');
    echo $body;
}

function page_cache_store(int $siteId, string $key, string $body, array $headers): void
{
    $dir = page_cache_dir() . "/$siteId";
    $hosts = page_cache_dir() . '/../hosts/' . substr($key, 0, 2);
    if (!is_dir($dir) && !@mkdir($dir, 0750, true)) return;
    if (!is_file("$dir/$key.html") && count(glob("$dir/*.html", GLOB_NOSORT) ?: []) >= PAGE_CACHE_MAX_FILES) return;
    if (!is_dir($hosts) && !@mkdir($hosts, 0750, true)) return;
    $exp = time() + PAGE_CACHE_TTL;
    // Una entrada programada que se publica antes de una hora acorta la caducidad (el blog y la portada cambian solos).
    $next = val("SELECT MIN(published_at) FROM posts WHERE site_id = ? AND status = 'published' AND published_at > ?", [$siteId, now()]);
    if ($next) $exp = min($exp, (int) strtotime((string) $next));
    $meta = ['at' => time(), 'exp' => $exp, 'core' => @filemtime(UW_ROOT . '/app/core.php'), 'len' => strlen($body), 'etag' => substr(sha1($body), 0, 20), 'h' => array_values($headers)];
    $tmp = "$dir/.$key." . bin2hex(random_bytes(4));
    if (@file_put_contents($tmp, json_encode($meta, JSON_UNESCAPED_SLASHES) . "\n" . $body) === false) return;
    @rename($tmp, "$dir/$key.html");
    @file_put_contents("$hosts/$key", (string) $siteId);
}

/** Vacía la caché de una web (o de todas con null). Devuelve cuántas páginas había. */
function page_cache_clear(?int $siteId): int
{
    $base = page_cache_dir();
    $n = 0;
    $dirs = $siteId === null ? (glob("$base/*", GLOB_ONLYDIR) ?: []) : ["$base/$siteId"];
    foreach ($dirs as $d) {
        foreach (glob("$d/*.html") ?: [] as $f) { if (@unlink($f)) $n++; }
        foreach (glob("$d/.*.*") ?: [] as $f) @unlink($f);
    }
    if ($siteId === null) foreach (glob("$base/../hosts/*/*") ?: [] as $f) @unlink($f);
    return $n;
}

/** Tamaño de la caché para el panel: [páginas, bytes]. */
function page_cache_stats(): array
{
    $n = 0; $bytes = 0;
    foreach (glob(page_cache_dir() . '/*/*.html') ?: [] as $f) { $n++; $bytes += (int) @filesize($f); }
    return [$n, $bytes];
}

/** Tras un acierto de caché, cada 10 minutos se carga la aplicación una vez (después de responder) para las tareas diarias. */
function uw_cache_tick_due(): bool
{
    $f = page_cache_dir() . '/../tick';
    $t = @filemtime($f);
    if ($t !== false && $t > time() - 600) return false;
    return @touch($f);
}
