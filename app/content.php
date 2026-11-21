<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Contenido: tipos de bloque (el contrato JSON), sitios, usuarios, tokens y el servicio
 * por sitio. Todo acceso a contenido pasa por SiteService, que siempre filtra por site_id:
 * así una web nunca puede ver ni tocar datos de otra.
 */

const RESERVED_SLUGS = ['admin', 'api', 'mcp', 'blog', 'install', 'media', 'assets', 'theme-assets', 'examples', 'sitemap-xml', 'robots-txt', 'feed-xml'];
const MEDIA_TYPES = [
    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
    'webp' => 'image/webp', 'avif' => 'image/avif', 'ico' => 'image/x-icon', 'pdf' => 'application/pdf',
    'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'm4a' => 'audio/mp4', 'wav' => 'audio/wav',
];
const MEDIA_MAX_BYTES = 8 * 1024 * 1024;          // imágenes y PDF
const MEDIA_MAX_BYTES_AV = 100 * 1024 * 1024;     // vídeo y audio (el límite real lo pone también upload_max_filesize)

// ---------------------------------------------------------------- bloques

function block_types(): array
{
    $f = fn(string $name, string $label, string $type = 'text', array $extra = []) => ['name' => $name, 'label' => $label, 'type' => $type] + $extra;
    return [
        'hero' => ['label' => 'Portada', 'description' => 'Cabecera grande. Úsala una sola vez y al principio: su título es el H1 de la página.', 'fields' => [
            $f('eyebrow', 'Antetítulo (texto corto sobre el título)'), $f('title', 'Título'), $f('subtitle', 'Subtítulo', 'textarea'), $f('image', 'Imagen', 'image'), $f('image_alt', 'Texto alternativo de la imagen'),
            $f('cta_text', 'Botón principal'), $f('cta_link', 'Enlace del botón principal', 'url'),
            $f('cta2_text', 'Botón secundario'), $f('cta2_link', 'Enlace del botón secundario', 'url'),
        ]],
        'text' => ['label' => 'Texto', 'description' => 'Sección de texto con título opcional. body admite Markdown.', 'fields' => [
            $f('title', 'Título'), $f('body', 'Contenido', 'markdown'),
        ]],
        'features' => ['label' => 'Características', 'description' => 'Rejilla de tarjetas (servicios, ventajas). icon: nombre de icono (' . implode(', ', UW_ICON_NAMES) . ') o un emoji. link opcional.', 'fields' => [
            $f('title', 'Título'), $f('subtitle', 'Subtítulo', 'textarea'),
            $f('eyebrow', 'Antetítulo'),
            $f('items', 'Tarjetas', 'list', ['fields' => [$f('icon', 'Icono (nombre o emoji)'), $f('title', 'Título'), $f('text', 'Texto', 'textarea'), $f('link', 'Enlace (opcional)', 'url')]]),
        ]],
        'stats' => ['label' => 'Cifras', 'description' => 'Fila de números destacados.', 'fields' => [
            $f('items', 'Cifras', 'list', ['fields' => [$f('value', 'Valor'), $f('label', 'Etiqueta')]]),
        ]],
        'image' => ['label' => 'Imagen', 'description' => 'Una imagen con pie de foto. alt es obligatorio para SEO y accesibilidad.', 'fields' => [
            $f('src', 'Imagen', 'image'), $f('alt', 'Texto alternativo'), $f('caption', 'Pie de foto'),
        ]],
        'gallery' => ['label' => 'Galería', 'description' => 'Rejilla de fotos y vídeos. Cada elemento: src = imagen (en un vídeo, su portada), alt y, opcionalmente, video = vídeo propio (/media/… .mp4/.webm) o enlace de YouTube/Vimeo.', 'fields' => [
            $f('title', 'Título'), $f('images', 'Elementos', 'list', ['fields' => [$f('src', 'Imagen (o portada del vídeo)', 'image'), $f('alt', 'Texto alternativo'), $f('video', 'Vídeo (opcional)', 'video')]]),
        ]],
        'listen' => ['label' => 'Escuchar esta página', 'description' => 'Reproductor compacto con la versión en audio de la página (voz generada por IA o grabada). Ponlo al principio, justo después de la portada. transcript = texto leído (Markdown, opcional): se muestra desplegable y lo leen Google y las IAs.', 'fields' => [
            $f('src', 'Audio (/media/… .mp3/.m4a/.ogg)', 'audio'), $f('title', 'Texto del botón (por defecto «Escuchar esta página»)'), $f('duration', 'Duración (p. ej. «3 min»)'), $f('transcript', 'Transcripción', 'markdown'),
        ]],
        'cta' => ['label' => 'Llamada a la acción', 'description' => 'Banda destacada con un botón.', 'fields' => [
            $f('title', 'Título'), $f('text', 'Texto', 'textarea'), $f('button_text', 'Texto del botón'), $f('button_link', 'Enlace del botón', 'url'),
        ]],
        'faq' => ['label' => 'Preguntas frecuentes', 'description' => 'Lista de preguntas y respuestas (answer admite Markdown).', 'fields' => [
            $f('title', 'Título'), $f('items', 'Preguntas', 'list', ['fields' => [$f('question', 'Pregunta'), $f('answer', 'Respuesta', 'markdown')]]),
        ]],
        'code' => ['label' => 'Código', 'description' => 'Bloque de código o configuración.', 'fields' => [
            $f('title', 'Título'), $f('language', 'Lenguaje'), $f('code', 'Código', 'code'),
        ]],
        'posts' => ['label' => 'Últimas entradas', 'description' => 'Muestra las últimas entradas publicadas del blog.', 'fields' => [
            $f('title', 'Título'), $f('count', 'Cantidad', 'number'),
        ]],
        'video' => ['label' => 'Vídeo', 'description' => 'Vídeo propio (URL de /media/... .mp4/.webm) o de YouTube/Vimeo (pega el enlace normal). poster = imagen de portada opcional.', 'fields' => [
            $f('title', 'Título'), $f('src', 'Vídeo (archivo o enlace de YouTube/Vimeo)', 'video'), $f('poster', 'Imagen de portada', 'image'),
            $f('caption', 'Pie'), $f('autoplay', 'Reproducción automática en silencio y en bucle (sí/no)'),
        ]],
        'audio' => ['label' => 'Audio', 'description' => 'Reproductor de audio (podcast, música, locución). src = URL de /media/... .mp3/.ogg/.m4a.', 'fields' => [
            $f('title', 'Título'), $f('src', 'Archivo de audio', 'audio'), $f('text', 'Descripción', 'textarea'),
        ]],
        'split' => ['label' => 'Imagen + texto', 'description' => 'Sección a dos columnas: imagen a un lado y texto (Markdown; las listas se muestran con marcas) al otro. reverse = sí pone la imagen a la derecha.', 'fields' => [
            $f('eyebrow', 'Antetítulo'), $f('title', 'Título'), $f('body', 'Texto', 'markdown'), $f('image', 'Imagen', 'image'), $f('image_alt', 'Texto alternativo'),
            $f('button_text', 'Texto del botón'), $f('button_link', 'Enlace del botón', 'url'), $f('reverse', 'Imagen a la derecha (sí/no)'),
        ]],
        'steps' => ['label' => 'Proceso por pasos', 'description' => 'Pasos numerados de un proceso (cómo trabajamos, cómo contratar).', 'fields' => [
            $f('eyebrow', 'Antetítulo'), $f('title', 'Título'), $f('subtitle', 'Subtítulo', 'textarea'),
            $f('items', 'Pasos', 'list', ['fields' => [$f('title', 'Título'), $f('text', 'Texto', 'textarea')]]),
        ]],
        'cards' => ['label' => 'Tarjetas con imagen', 'description' => 'Rejilla de tarjetas con foto (productos, tipos de servicio, equipo, proyectos).', 'fields' => [
            $f('eyebrow', 'Antetítulo'), $f('title', 'Título'), $f('subtitle', 'Subtítulo', 'textarea'),
            $f('items', 'Tarjetas', 'list', ['fields' => [$f('image', 'Imagen', 'image'), $f('alt', 'Texto alternativo'), $f('title', 'Título'), $f('text', 'Texto', 'textarea'), $f('tag', 'Etiqueta corta'), $f('link', 'Enlace', 'url')]]),
        ]],
        'logos' => ['label' => 'Logos', 'description' => 'Fila de logos (clientes, partners, certificaciones).', 'fields' => [
            $f('title', 'Título'), $f('items', 'Logos', 'list', ['fields' => [$f('image', 'Logo', 'image'), $f('alt', 'Nombre'), $f('link', 'Enlace', 'url')]]),
        ]],
        'testimonials' => ['label' => 'Testimonios', 'description' => 'Citas de clientes o del equipo (quote admite texto plano).', 'fields' => [
            $f('title', 'Título'),
            $f('items', 'Testimonios', 'list', ['fields' => [$f('quote', 'Cita', 'textarea'), $f('name', 'Nombre'), $f('role', 'Cargo / empresa'), $f('image', 'Foto', 'image')]]),
        ]],
        'hours' => ['label' => 'Horario', 'description' => 'Horario de apertura. Cada fila: days = días con códigos (lu, ma, mi, ju, vi, sa, do; rangos «lu-vi», listas «lu,mi»), opens/closes = "09:00"/"14:00" y, si hay turno de tarde, opens2/closes2. closed = sí para días cerrados. label es opcional (se genera). Muestra «Abierto ahora» y se publica para Google y las IAs (openingHours).', 'fields' => [
            $f('title', 'Título'), $f('note', 'Nota (festivos, cita previa…)', 'textarea'),
            $f('items', 'Filas', 'list', ['fields' => [$f('days', 'Días (lu-vi, sa, do…)'), $f('label', 'Texto (opcional, p. ej. «Lunes a viernes»)'),
                $f('opens', 'Abre (09:00)'), $f('closes', 'Cierra (14:00)'), $f('opens2', 'Abre tarde (17:00)'), $f('closes2', 'Cierra tarde (20:30)'), $f('closed', 'Cerrado (sí/no)')]]),
        ]],
        'pricelist' => ['label' => 'Carta / Precios', 'description' => 'Lista de productos o servicios con precio: carta de restaurante, tarifas de peluquería o estética, precios de taller. section agrupa (Entrantes, Principales…); price es texto libre ("12", "12,50", "desde 30"); currency por defecto €.', 'fields' => [
            $f('eyebrow', 'Antetítulo'), $f('title', 'Título'), $f('subtitle', 'Subtítulo', 'textarea'), $f('currency', 'Moneda (€ por defecto)'), $f('note', 'Nota (IVA incluido, alérgenos…)', 'textarea'),
            $f('items', 'Elementos', 'list', ['fields' => [$f('section', 'Sección'), $f('name', 'Nombre'), $f('description', 'Descripción', 'textarea'), $f('price', 'Precio'), $f('tag', 'Etiqueta (Nuevo, Vegano…)')]]),
        ]],
        'contact_form' => ['label' => 'Formulario de contacto', 'description' => 'Formulario; los mensajes llegan al panel (sección Mensajes). show_phone = sí añade teléfono; privacy_url activa la casilla obligatoria de privacidad (RGPD).', 'fields' => [
            $f('title', 'Título'), $f('text', 'Texto', 'textarea'), $f('button_text', 'Texto del botón'),
            $f('show_phone', 'Pedir teléfono (sí/no)'), $f('privacy_url', 'Enlace a la política de privacidad', 'url'),
        ]],
    ];
}

function sanitize_blocks(mixed $blocks): array
{
    if (is_string($blocks)) $blocks = json_decode($blocks, true);
    if (!is_array($blocks) || !array_is_list($blocks)) throw new UwError('blocks debe ser una lista JSON de bloques: [{"type": "...", "data": {...}}]');
    $types = block_types();
    $out = [];
    foreach ($blocks as $i => $b) {
        if (!is_array($b) || !isset($b['type']) || !is_string($b['type'])) throw new UwError("El bloque #$i no tiene 'type'");
        if (!isset($types[$b['type']])) {
            throw new UwError("Tipo de bloque desconocido '{$b['type']}'. Tipos válidos: " . implode(', ', array_keys($types)));
        }
        $flat = !(isset($b['data']) && is_array($b['data']));
        $data = $flat ? array_diff_key($b, ['type' => 1]) : $b['data'];
        assert_known_fields($types[$b['type']]['fields'], $data, "Bloque #$i ({$b['type']})");
        $out[] = ['type' => $b['type'], 'data' => sanitize_fields($types[$b['type']]['fields'], $data)];
    }
    return $out;
}

/**
 * Rechaza campos que el tipo de bloque no tiene: antes se tiraban en silencio y una IA
 * que mandaba {"type":"text","text":"..."} guardaba un bloque vacío creyendo que había
 * escrito el contenido. Se aceptan los derivados *_html que devuelve page_get, para que
 * leer → cambiar → guardar funcione tal cual.
 */
function assert_known_fields(array $fields, array $data, string $where): void
{
    $names = array_column($fields, 'name');
    $allowed = array_merge($names, array_map(fn($n) => $n . '_html', $names));
    $unknown = array_diff(array_map('strval', array_keys($data)), $allowed);
    foreach ($names as $n) {
        if (array_key_exists($n . '_html', $data) && !array_key_exists($n, $data)) {
            throw new UwError("$where: '{$n}_html' es solo de lectura; manda el contenido en '$n'. Consulta block_types.");
        }
    }
    if ($unknown) {
        $con = array_map(function ($u) use ($names) {
            $p = sugerir_campo((string) $u, $names);
            return "'$u'" . ($p !== '' ? " ($p)" : '');
        }, array_values($unknown));
        throw new UwError("$where: campo desconocido " . implode(', ', $con) . '. Campos válidos: '
            . implode(', ', $names) . '. NO se ha guardado nada: corrige el bloque y vuelve a enviarlo (consulta block_types).');
    }
    foreach ($fields as $f) {
        if ($f['type'] !== 'list' || !is_array($data[$f['name']] ?? null)) continue;
        foreach (array_values(array_filter($data[$f['name']], 'is_array')) as $j => $it) {
            assert_known_fields($f['fields'], $it, "$where > {$f['name']}[$j]");
        }
    }
}

/**
 * 27-oct (ADRI): a qué campo se refería quien mandó uno que no existe: «alt» → image_alt, «buttons» → los cta_*,
 * o el nombre válido más parecido. Vacío si no hay una pista clara.
 */
function sugerir_campo(string $u, array $names): string
{
    $l = mb_strtolower(trim($u));
    foreach ($names as $n) {
        if (str_ends_with($n, '_' . $l)) return "¿quisiste decir '$n'?";
    }
    if (preg_match('/button|bot[oó]n|cta|call.?to/u', $l)) {
        $cta = array_values(array_filter($names, fn($n) => str_starts_with($n, 'cta')));
        if ($cta) return 'los botones van en ' . implode(', ', $cta);
    }
    $mejor = '';
    $dist = PHP_INT_MAX;
    foreach ($names as $n) {
        $d = levenshtein($l, $n);
        if ($d < $dist) { $dist = $d; $mejor = $n; }
    }
    return ($mejor !== '' && $dist <= max(2, intdiv(strlen($mejor), 3))) ? "¿quisiste decir '$mejor'?" : '';
}

function sanitize_fields(array $fields, array $data): array
{
    $out = [];
    foreach ($fields as $f) {
        $v = $data[$f['name']] ?? null;
        $out[$f['name']] = match ($f['type']) {
            'list' => array_values(array_map(fn($it) => sanitize_fields($f['fields'], is_array($it) ? $it : []), is_array($v) ? array_filter($v, 'is_array') : [])),
            'number' => is_numeric($v) ? (int) $v : 0,
            'url', 'image', 'video', 'audio' => mb_substr(clean_url(is_scalar($v) ? trim((string) $v) : ''), 0, 2000),
            default => mb_substr(is_scalar($v) ? trim((string) $v) : '', 0, 50000),
        };
    }
    return $out;
}

/** Añade versiones *_html de los campos Markdown (útil para fronts externos). */
function blocks_with_html(array $blocks, ?SiteService $svc = null): array
{
    $types = block_types();
    $enrich = function (array $fields, array $data) use (&$enrich, $svc) {
        foreach ($fields as $f) {
            if ($f['type'] === 'markdown') $data[$f['name'] . '_html'] = md((string) ($data[$f['name']] ?? ''));
            // Imágenes, vídeos y audios con URL absoluta: el front puede estar en otro servidor.
            if ($svc && in_array($f['type'], ['image', 'video', 'audio'], true) && str_starts_with((string) ($data[$f['name']] ?? ''), '/')) {
                $data[$f['name']] = abs_url($svc, (string) $data[$f['name']]);
            }
            if ($f['type'] === 'list') $data[$f['name']] = array_map(fn($it) => $enrich($f['fields'], $it), $data[$f['name']] ?? []);
        }
        return $data;
    };
    return array_map(fn($b) => ['type' => $b['type'], 'data' => $enrich($types[$b['type']]['fields'] ?? [], $b['data'])], $blocks);
}

function sanitize_seo(mixed $seo, array $current = []): array
{
    if (!is_array($seo)) $seo = [];
    $out = $current + ['title' => '', 'description' => '', 'image' => '', 'noindex' => false];
    foreach (['title', 'description', 'image'] as $k) {
        if (array_key_exists($k, $seo)) $out[$k] = mb_substr(trim((string) $seo[$k]), 0, 500);
    }
    $out['image'] = clean_url($out['image']);
    if (array_key_exists('noindex', $seo)) $out['noindex'] = filter_var($seo['noindex'], FILTER_VALIDATE_BOOLEAN);
    return $out;
}

// ---------------------------------------------------------------- validación de archivos y descargas seguras

/** Comprueba la "firma" real del archivo, no solo la extensión. */
function media_content_matches(string $file, string $ext): bool
{
    $head = (string) file_get_contents($file, false, null, 0, 16);
    return match ($ext) {
        'pdf' => str_starts_with($head, '%PDF-'),
        'ico' => str_starts_with($head, "\x00\x00\x01\x00"),
        'mp4', 'm4a' => substr($head, 4, 4) === 'ftyp',
        'webm' => str_starts_with($head, "\x1A\x45\xDF\xA3"),
        'ogg' => str_starts_with($head, 'OggS'),
        'wav' => str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WAVE',
        'mp3' => str_starts_with($head, 'ID3') || (strlen($head) > 1 && ord($head[0]) === 0xFF && (ord($head[1]) & 0xE0) === 0xE0),
        default => @getimagesize($file) !== false,
    };
}

function mime_content_type_safe(string $file): string
{
    if (function_exists('finfo_open')) {
        $f = finfo_open(FILEINFO_MIME_TYPE);
        $m = finfo_file($f, $file);
        if (is_string($m)) return $m;
    }
    return 'application/octet-stream';
}

/** IP pública de verdad: ni privada, ni reservada, ni CGNAT, enlace local, ULA, NAT64 o 6to4. */
function ip_is_public(string $ip): bool
{
    return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
        && !ip_in_list($ip, ['100.64.0.0/10', '169.254.0.0/16', '0.0.0.0/8', 'fc00::/7', 'fe80::/10', '64:ff9b::/96', '64:ff9b:1::/48', '2002::/16', '::ffff:0:0/96']);
}

/**
 * Resuelve el host de una conexión saliente configurada por un admin (webhook, SMTP) y devuelve la IP a usar.
 * Las redes privadas o locales solo se permiten si el superadmin las ha autorizado (Seguridad y red).
 */
function outbound_resolve(string $host): string
{
    $host = trim($host, '[]');
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
        array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'), array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));
    if (!$ips) throw new UwError("No se puede resolver el dominio $host");
    foreach ($ips as $ip) {
        if (!ip_is_public($ip) && !ip_in_list($ip, platform()['outbound_allowlist'])) {
            throw new UwError("$host apunta a una red privada o local ($ip). Por seguridad, solo un superadmin puede permitirla en Seguridad y red → Destinos privados permitidos.");
        }
    }
    return $ips[0];
}

/**
 * Descarga una URL pública a un archivo temporal, con protección SSRF: solo http(s), solo IPs públicas
 * (nada de localhost, redes privadas ni ZeroTier), sin redirecciones a otros sitios y con tamaño máximo.
 */
function http_fetch_public(string $url, int $maxBytes, int $redirects = 3, int $timeout = 120): string
{
    $parts = parse_url($url);
    if (!$parts || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host'])) throw new UwError('URL no válida: usa http:// o https://');
    if (isset($parts['user']) || isset($parts['pass'])) throw new UwError('La URL no puede llevar usuario ni contraseña');
    $host = trim($parts['host'], '[]');
    $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : array_merge(
        array_column(@dns_get_record($host, DNS_A) ?: [], 'ip'), array_column(@dns_get_record($host, DNS_AAAA) ?: [], 'ipv6'));
    if (!$ips) throw new UwError("No se puede resolver el dominio $host");
    foreach ($ips as $ip) {
        if (!ip_is_public($ip)) throw new UwError('Por seguridad no se permiten URLs de redes privadas o locales');
    }
    if (!function_exists('curl_init')) throw new UwError('Para descargar por URL hace falta la extensión curl de PHP');
    $port = (int) ($parts['port'] ?? (strtolower($parts['scheme']) === 'https' ? 443 : 80));
    $tmp = tempnam(sys_get_temp_dir(), 'uwdl');
    $out = fopen($tmp, 'wb');
    $ch = curl_init($url);
    $size = 0;
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => min(10, $timeout),
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_RESOLVE => ["$host:$port:" . $ips[0]], // fija la IP validada (evita DNS rebinding)
        CURLOPT_USERAGENT => 'UniversalWeb/' . UW_VERSION,
        CURLOPT_HEADER => false,
        CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($out, &$size, $maxBytes) {
            $size += strlen($data);
            return $size > $maxBytes ? 0 : fwrite($out, $data);
        },
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $location = (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL);
    fclose($out);
    if ($code >= 300 && $code < 400 && $location !== '' && $redirects > 0) {
        unlink($tmp);
        return http_fetch_public($location, $maxBytes, $redirects - 1, $timeout); // cada salto se valida de nuevo
    }
    if ($size > $maxBytes) { unlink($tmp); throw new UwError('El archivo supera el tamaño máximo (' . intdiv($maxBytes, 1048576) . ' MB)'); }
    if (!$ok || $code !== 200) { unlink($tmp); throw new UwError("No se pudo descargar la URL (HTTP $code)"); }
    return $tmp;
}

// ---------------------------------------------------------------- sitios

function site_defaults(): array
{
    return [
        'tagline' => '', 'language' => 'es', 'logo' => '', 'accent' => '#4f46e5', 'footer_text' => '',
        'menu' => [['label' => 'Inicio', 'url' => '/']],
        'cta' => ['label' => '', 'url' => ''],   // botón destacado de la cabecera (si el tema lo muestra)
        'private' => ['enabled' => false, 'password_hash' => ''], // modo demo: web privada con contraseña y sin indexar
        'business' => ['type' => 'Organization', 'legal_name' => '', 'area_served' => '', 'founded' => ''], // GEO: identidad para IAs
        'ai_crawlers' => 'allow_all',            // allow_all | search_only | block_all
        'ai_markdown' => true,                   // los agentes de IA reciben Markdown en vez de HTML (Google/Bing siempre HTML)
        'indexnow' => true,                      // avisa a Bing/Yandex (IndexNow) de cada página publicada o cambiada
        'url_style' => 'clean',                  // clean = /servicios, /blog/x · html = /servicios.html, /blog/x.html, /blog.html (migrar webs estáticas)
        'same_as' => [],                         // perfiles que confirman la identidad (Wikipedia, Wikidata, LinkedIn…) para el JSON-LD (sameAs)
        'locales' => [],                         // og:locale por idioma si no vale el de fábrica: {"en": "en_US", "pt": "pt_BR"}
        'languages' => [],                       // idiomas adicionales (el principal es «language»): p. ej. ['en', 'ro']
        'i18n' => [],                            // textos traducidos por idioma: tagline, footer_text, menu, utility_menu, cta, seo.description
        'cookies' => [],                         // aviso de cookies y analítica con consentimiento (ver cookies_defaults())
        'utility_menu' => [],                    // enlaces secundarios: barra superior (p. ej. «Área de clientes»)
        'contact' => ['email' => '', 'phone' => '', 'address' => ''],
        'social' => ['instagram' => '', 'facebook' => '', 'x' => '', 'linkedin' => '', 'youtube' => '', 'github' => '', 'tiktok' => '', 'whatsapp' => ''],
        'seo' => ['description' => '', 'image' => ''],
    ];
}

function sites_all(): array
{
    return rows('SELECT * FROM sites ORDER BY id');
}

function site_by_id(int $id): ?array
{
    return row('SELECT * FROM sites WHERE id = ?', [$id]);
}

function site_by_slug(string $slug): ?array
{
    return row('SELECT * FROM sites WHERE slug = ?', [$slug]);
}

function site_domains(int $siteId): array
{
    return array_column(rows('SELECT domain FROM site_domains WHERE site_id = ? ORDER BY is_primary DESC, domain', [$siteId]), 'domain');
}

function site_for_host(string $host): ?array
{
    $s = row('SELECT s.* FROM sites s JOIN site_domains d ON d.site_id = s.id WHERE d.domain IN (?, ?)', [$host, preg_replace('/^www\./', '', $host)]);
    if ($s) return $s;
    // Host desconocido (IP, dominio sin asignar): web por defecto.
    return site_by_id((int) config('default_site_id', 1));
}

/**
 * Web pública de un host. Un dominio que ningún sitio tiene asignado NO enseña la web por defecto (con varias webs eso
 * mostraba la de otro cliente hasta añadir el dominio en Ajustes → Dominios).
 * La web por defecto solo se sirve a una IP o nombre local (acceso directo, pruebas) o si la instalación tiene una sola web.
 */
function site_for_public_host(string $host): ?array
{
    $s = row('SELECT s.* FROM sites s JOIN site_domains d ON d.site_id = s.id WHERE d.domain IN (?, ?)', [$host, preg_replace('/^www\./', '', $host)]);
    if ($s) return $s;
    $local = filter_var(trim($host, '[]'), FILTER_VALIDATE_IP) !== false || $host === 'localhost' || str_ends_with($host, '.localhost');
    if ($local || (int) val('SELECT COUNT(*) FROM sites') === 1) return site_by_id((int) config('default_site_id', 1));
    return null;
}

function site_base_url(array $site): string
{
    // Web publicada en otro servidor: sus URLs canónicas son las del dominio público (no las de este servidor).
    if (function_exists('publish_public_base') && ($public = publish_public_base((int) $site['id'])) !== null) return $public;
    $domains = site_domains((int) $site['id']);
    $current = request_host();
    // En vista previa sin dominio (/_ver) todos los enlaces absolutos se quedan en el host actual.
    $enVista = (int) ($GLOBALS['uw_site_preview'] ?? 0) === (int) $site['id'];
    $host = $enVista || in_array($current, $domains, true) ? $current : ($domains[0] ?? $current);
    return (is_https() ? 'https://' : 'http://') . $host . request_port_suffix();
}

function normalize_domains(string|array $domains): array
{
    $list = is_array($domains) ? $domains : preg_split('/[\s,]+/', $domains);
    $out = [];
    foreach ($list as $d) {
        $d = strtolower(trim((string) $d));
        $d = (string) preg_replace('#^https?://#', '', $d);
        $d = (string) preg_replace('#[/:].*$#', '', $d);
        if ($d === '') continue;
        if (!preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $d)) throw new UwError("Dominio no válido: $d");
        $out[] = $d;
    }
    return array_values(array_unique($out));
}

function site_set_domains(int $siteId, array $domains, bool $trusted = false, bool $allowEmpty = false): void
{
    $domains = normalize_domains($domains);
    // Una web nueva puede crearse sin dominio (se ve con la vista previa /_ver); al editar, una web con dominio no se queda sin él.
    if (!$domains && !$allowEmpty) throw new UwError('La web necesita al menos un dominio');
    $adminHost = strtolower((string) (is_installed() ? platform()['admin_host'] : ''));
    foreach ($domains as $d) {
        if ($adminHost !== '' && $d === $adminHost) throw new UwError("$d es el dominio del panel de administración: no puede asignarse a una web");
        // Un admin de web no puede quedarse con IPs ni nombres locales: es por donde suele entrar el superadmin.
        if (!$trusted && (filter_var($d, FILTER_VALIDATE_IP) || $d === 'localhost' || !str_contains($d, '.'))) {
            throw new UwError("$d no se puede usar como dominio de una web (solo un superadmin puede asignar IPs o nombres locales)");
        }
    }
    foreach ($domains as $d) {
        $owner = val('SELECT site_id FROM site_domains WHERE domain = ?', [$d]);
        if ($owner !== null && (int) $owner !== $siteId) throw new UwError("El dominio $d ya pertenece a otra web");
    }
    q('DELETE FROM site_domains WHERE site_id = ?', [$siteId]);
    foreach ($domains as $i => $d) insert('site_domains', ['domain' => $d, 'site_id' => $siteId, 'is_primary' => $i === 0 ? 1 : 0]);
    if (function_exists('page_cache_clear')) page_cache_clear(null); // un dominio que cambia de web no debe seguir sirviendo la copia de la otra
}

function site_create(string $name, string $slug, array $domains, string $theme = 'default', array $settings = []): array
{
    $name = trim($name);
    $slug = slugify($slug !== '' ? $slug : $name);
    if ($name === '' || $slug === '') throw new UwError('La web necesita nombre');
    if (site_by_slug($slug)) throw new UwError("Ya existe una web con el identificador '$slug'");
    if (!theme_exists($theme)) throw new UwError("El tema '$theme' no existe");
    db()->beginTransaction();
    try {
        $id = insert('sites', ['slug' => $slug, 'name' => $name, 'theme' => $theme, 'settings' => json_enc(array_replace_recursive(site_defaults(), $settings)), 'created_at' => now()]);
        site_set_domains($id, $domains, true, true);
        db()->commit();
    } catch (Throwable $ex) {
        db()->rollBack();
        throw $ex;
    }
    audit($id, 'site.create', $slug);
    return site_by_id($id);
}

function themes_available(): array
{
    $out = [];
    foreach (glob(UW_ROOT . '/themes/*/theme.json') ?: [] as $f) {
        $meta = json_dec((string) file_get_contents($f));
        $out[basename(dirname($f))] = ($meta['name'] ?? basename(dirname($f))) . (isset($meta['description']) ? ' — ' . $meta['description'] : '');
    }
    return $out;
}

/** Guía de temas para la IA y el panel: para qué negocios es cada uno y su color sugerido (de theme.json). */
function theme_guide(): array
{
    $out = [];
    foreach (glob(UW_ROOT . '/themes/*/theme.json') ?: [] as $f) {
        $m = json_dec((string) file_get_contents($f));
        $slug = basename(dirname($f));
        $out[$slug] = ['name' => (string) ($m['name'] ?? $slug), 'description' => (string) ($m['description'] ?? ''),
            'best_for' => array_values((array) ($m['best_for'] ?? [])), 'suggested_accent' => (string) ($m['suggested_accent'] ?? ''),
            // Fotos de muestra propias del tema (generadas por IA, sin derechos de terceros): URL local lista para usar en bloques.
            'sample_images' => array_values(array_map(fn($i) => ['url' => "/theme-assets/$slug/" . ltrim((string) ($i['file'] ?? ''), '/'), 'alt' => (string) ($i['alt'] ?? ''), 'use' => (string) ($i['use'] ?? '')],
                array_filter((array) ($m['sample_images'] ?? []), fn($i) => is_array($i) && preg_match('#^samples/[a-z0-9_-]+\.(webp|jpg|png)$#', (string) ($i['file'] ?? ''))))),
        ];
    }
    return $out;
}

/** La misma guía en una línea por tema (para descripciones de herramientas MCP). */
function theme_guide_text(): string
{
    return implode(' · ', array_map(fn($slug, $t) => "$slug = " . ($t['best_for'] ? implode(', ', array_slice($t['best_for'], 0, 4)) : $t['description'])
        . ($t['suggested_accent'] ? " (color {$t['suggested_accent']})" : ''), array_keys(theme_guide()), theme_guide()));
}

function theme_exists(string $theme): bool
{
    return (bool) preg_match('/^[a-z0-9_-]+$/', $theme) && is_file(UW_ROOT . "/themes/$theme/theme.json");
}

// ---------------------------------------------------------------- usuarios y roles

const ROLES = ['editor' => 1, 'admin' => 2, 'super' => 3];

function user_by_id(int $id): ?array
{
    return row('SELECT * FROM users WHERE id = ?', [$id]);
}

function user_create(string $email, string $name, string $password, bool $super = false): int
{
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UwError('Email no válido');
    if (mb_strlen($password) < 8) throw new UwError('La contraseña debe tener al menos 8 caracteres');
    if (row('SELECT id FROM users WHERE email = ?', [$email])) throw new UwError('Ya existe un usuario con ese email');
    return insert('users', [
        'email' => $email, 'name' => trim($name) ?: $email, 'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'is_superadmin' => $super ? 1 : 0, 'created_at' => now(),
    ]);
}

/**
 * Cambia nombre y email de una cuenta. Devuelve true si cambió el email: el email es el usuario de acceso y el destino de la
 * recuperación, así que los enlaces de recuperación pendientes (enviados a la dirección anterior) dejan de valer.
 */
function user_update_profile(int $id, string $name, string $email): bool
{
    $u = user_by_id($id) ?? throw new UwError(t('Usuario no encontrado'));
    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UwError(t('Email no válido'));
    if (val('SELECT 1 FROM users WHERE email = ? AND id != ?', [$email, $id])) throw new UwError(t('Ya existe un usuario con ese email'));
    $changed = $email !== strtolower((string) $u['email']);
    update('users', ['email' => $email, 'name' => mb_substr(trim($name), 0, 100) ?: $email], 'id = ?', [$id]);
    if ($changed) q('DELETE FROM password_resets WHERE user_id = ?', [$id]);
    return $changed;
}

/** Rol efectivo del usuario en una web: super, admin, editor o null. */
function user_role(array $user, int $siteId): ?string
{
    if ($user['is_superadmin']) return 'super';
    return val('SELECT role FROM site_users WHERE site_id = ? AND user_id = ?', [$siteId, $user['id']]);
}

function user_sites(array $user): array
{
    if ($user['is_superadmin']) return sites_all();
    return rows('SELECT s.* FROM sites s JOIN site_users su ON su.site_id = s.id WHERE su.user_id = ? ORDER BY s.id', [$user['id']]);
}

function role_at_least(?string $role, string $min): bool
{
    return $role !== null && ROLES[$role] >= ROLES[$min];
}

// ---------------------------------------------------------------- tokens MCP

const TOKEN_SCOPES = ['read' => 'Leer', 'write' => 'Crear y editar borradores', 'publish' => 'Publicar y editar contenido publicado',
    'messages' => 'Leer y gestionar los mensajes de contacto (datos personales)',
    'code' => 'Editar CSS, JavaScript y código del <head> (exige ser admin)', 'backup' => 'Crear y listar copias de seguridad (exige ser admin)'];

/** Crea un token. Devuelve el valor en claro: solo se muestra una vez. */
function token_create(?int $siteId, string $name, array $scopes, ?int $createdBy): string
{
    $scopes = array_values(array_intersect(array_keys(TOKEN_SCOPES), $scopes));
    if (!$scopes) throw new UwError('Elige al menos un permiso');
    if (trim($name) === '') throw new UwError('Ponle un nombre al token');
    $raw = 'uw_' . bin2hex(random_bytes(20));
    insert('tokens', [
        'site_id' => $siteId, 'name' => trim($name), 'token_hash' => hash('sha256', $raw), 'prefix' => substr($raw, 0, 10),
        'scopes' => implode(',', $scopes), 'created_by' => $createdBy, 'created_at' => now(),
    ]);
    audit($siteId, 'token.create', trim($name), ['scopes' => $scopes]);
    return $raw;
}

/**
 * Rol con el que actúa un token: el que tiene HOY su creador en esa web. Si el creador pierde
 * el acceso (o deja de ser superadmin, en un token global), el token deja de valer.
 * Los tokens creados por la CLI no tienen creador y actúan como admin (o super si son globales).
 */
function token_owner_role(array $t): ?string
{
    if ($t['created_by'] === null) return $t['site_id'] === null ? 'super' : 'admin';
    $owner = user_by_id((int) $t['created_by']);
    if (!$owner) return null;
    if ($t['site_id'] === null) return $owner['is_superadmin'] ? 'super' : null;
    return user_role($owner, (int) $t['site_id']);
}

function token_authenticate(string $raw): ?array
{
    $t = row('SELECT t.*, u.name AS owner_name FROM tokens t LEFT JOIN users u ON u.id = t.created_by WHERE t.token_hash = ? AND t.revoked_at IS NULL', [hash('sha256', $raw)]);
    if (!$t) return null;
    $t['owner_role'] = token_owner_role($t);
    if ($t['owner_role'] === null) return null;
    q('UPDATE tokens SET last_used_at = ? WHERE id = ?', [now(), $t['id']]);
    $t['scopes'] = explode(',', $t['scopes']);
    return $t;
}

// ---------------------------------------------------------------- servicio por sitio

final class SiteService
{
    /** Idioma en el que trabaja este objeto (el principal por defecto). Las rutas /en/... lo cambian. */
    public string $lang = '';

    public function __construct(public array $site)
    {
        $this->lang = $this->defaultLang();
    }

    public function defaultLang(): string
    {
        $l = (string) (json_dec($this->site['settings'])['language'] ?? 'es');
        return preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $l) ? $l : 'es';
    }

    /** Idiomas de la web: el principal primero. */
    public function langs(): array
    {
        $extra = json_dec($this->site['settings'])['languages'] ?? [];
        return array_values(array_unique([$this->defaultLang(), ...array_filter((array) $extra, fn($l) => is_string($l) && preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $l))]));
    }

    public function setLang(string $lang): void
    {
        if (!in_array($lang, $this->langs(), true)) throw new UwError("La web no tiene el idioma «{$lang}». Idiomas: " . implode(', ', $this->langs()) . ' (añádelo con site_update languages)');
        $this->lang = $lang;
        if (class_exists('FrontLang', false)) FrontLang::$code = $lang;
    }

    /** Idiomas visibles en público: el principal y los que ya tienen portada publicada. */
    public function liveLangs(): array
    {
        $ready = array_column(rows("SELECT DISTINCT lang FROM pages WHERE site_id = ? AND slug = 'index' AND status = 'published'", [$this->id()]), 'lang');
        return array_values(array_filter($this->langs(), fn($l) => $l === $this->defaultLang() || in_array($l, $ready, true)));
    }

    public function langPrefix(?string $lang = null): string
    {
        $lang ??= $this->lang;
        return $lang === $this->defaultLang() ? '' : '/' . $lang;
    }

    /**
     * Ruta pública en un idioma. Entiende rutas «lógicas» y las lleva a la dirección real de cada idioma:
     * '/servicios' → '/en/servicios' (o '/en/services.html' si la página inglesa tiene la ruta fijada); '/blog' → el blog de ese
     * idioma; '/' → '/en'. Si la página no existe en ese idioma, enlaza al destino de su redirección (si la hay) o a la versión
     * del idioma principal: nunca a una dirección que da 404. Cualquier otra ruta solo recibe el prefijo del idioma.
     */
    public function langPath(string $path, ?string $lang = null): string
    {
        $lang ??= $this->lang;
        $prefix = $this->langPrefix($lang);
        [$bare, $rest] = preg_match('/^([^?#]*)(.*)$/s', $path, $m) ? [$m[1], $m[2]] : [$path, ''];
        $t = $bare !== '' ? $this->logicalTarget($bare) : null;
        if ($t === null) return $prefix === '' ? $path : ($path === '/' ? $prefix : $prefix . $path);
        [$kind, $slug] = $t;
        if ($kind === 'blog') return $this->blogPath($lang) . $rest;
        $idx = $this->routeIndex()[$kind];
        if (isset($idx[$lang][$slug]) || ($kind === 'page' && $slug === 'index')) return $this->itemPath($kind, $slug, $lang) . $rest;
        $eq = $this->equivalentPath($kind, $slug, $lang);
        if (function_exists('redirect_find') && ($r = redirect_find($this->id(), $eq))) return $r['to_path'];
        if (isset($idx[$this->defaultLang()][$slug])) return $this->itemPath($kind, $slug, $this->defaultLang()) . $rest;
        return $eq . $rest;
    }

    /**
     * Enlace escrito para un idioma (menú traducido): si apunta a una página de este idioma que tiene otra dirección
     * (ruta fija, estilo .html) o que no existe en él, se lleva a la buena. Las rutas desconocidas se dejan tal cual.
     */
    public function linkInLang(string $url): string
    {
        if (!str_starts_with($url, '/') || str_starts_with($url, '//')) return $url;
        [$bare, $rest] = preg_match('/^([^?#]*)(.*)$/s', $url, $m) ? [$m[1], $m[2]] : [$url, ''];
        $hit = $this->resolvePath($bare);
        if (!$hit) return $url;
        if ($hit['kind'] === 'blog') return $this->blogPath($hit['lang']) . $rest;
        $idx = $this->routeIndex()[$hit['kind']];
        if (isset($idx[$hit['lang']][$hit['slug']]) || $hit['slug'] === 'index') return $this->itemPath($hit['kind'], $hit['slug'], $hit['lang']) . $rest;
        $somewhere = array_filter($idx, fn($items) => isset($items[$hit['slug']]));
        if (!$somewhere) return $url;
        $from = array_key_first($somewhere);
        return $this->langPath($this->itemPath($hit['kind'], $hit['slug'], isset($somewhere[$this->defaultLang()]) ? $this->defaultLang() : $from), $hit['lang']) . $rest;
    }

    /** Estilo de las direcciones automáticas: 'clean' (/servicios, /blog/x) o 'html' (/servicios.html, /blog/x.html, /blog.html). */
    public function urlStyle(): string
    {
        return (json_dec($this->site['settings'])['url_style'] ?? 'clean') === 'html' ? 'html' : 'clean';
    }

    /** @var array<string, array<string, array<string, array{path: string, status: string}>>>|null [kind][lang][slug] */
    private ?array $routes = null;

    /** Rutas de todas las páginas y entradas de la web (una sola consulta por petición). */
    private function routeIndex(): array
    {
        if ($this->routes === null) {
            $this->routes = ['page' => [], 'post' => []];
            $sql = "SELECT 'page' AS k, slug, lang, path, status FROM pages WHERE site_id = ? UNION ALL SELECT 'post' AS k, slug, lang, path, status FROM posts WHERE site_id = ?";
            foreach (rows($sql, [$this->id(), $this->id()]) as $r) $this->routes[$r['k']][$r['lang']][$r['slug']] = ['path' => (string) $r['path'], 'status' => (string) $r['status']];
        }
        return $this->routes;
    }

    /** Olvida el índice de rutas (tras guardar o borrar). */
    public function forgetRoutes(): void
    {
        $this->routes = null;
        $this->memo = [];
    }

    /** Ajustes ya calculados en esta petición, por idioma y contenido de los ajustes (se descartan al cambiar algo). */
    private array $memo = [];

    /** Dirección automática (sin fijar) de una página ('page'), entrada ('post') o del blog ('blog'). */
    public function autoPath(string $kind, string $slug, ?string $lang = null): string
    {
        $pre = $this->langPrefix($lang);
        $html = $this->urlStyle() === 'html';
        return match (true) {
            $kind === 'blog' => $pre . ($html ? '/blog.html' : '/blog'),
            $kind === 'post' => $pre . '/blog/' . $slug . ($html ? '.html' : ''),
            $slug === 'index' => $pre === '' ? '/' : $pre . ($html ? '/' : ''),
            default => $pre . '/' . $slug . ($html ? '.html' : ''),
        };
    }

    /** Dirección pública de una página o entrada: la fijada a mano o, si no, la automática. */
    public function itemPath(string $kind, string $slug, ?string $lang = null, ?string $fixed = null): string
    {
        $lang ??= $this->lang;
        $fixed ??= (string) ($this->routeIndex()[$kind][$lang][$slug]['path'] ?? '');
        return $fixed !== '' && $slug !== 'index' ? $fixed : $this->autoPath($kind, $slug, $lang);
    }

    /** Copia en Markdown para agentes de IA: /md/servicios.md, /en/md/index.md, /md/blog-<entrada>.md, /md/blog.md. */
    public function mdPath(string $kind, string $slug, ?string $lang = null): string
    {
        $name = match ($kind) { 'blog' => 'blog', 'post' => 'blog-' . $slug, default => $slug };
        return $this->langPrefix($lang ?? $this->lang) . '/md/' . $name . '.md';
    }

    public function blogPath(?string $lang = null): string
    {
        return $this->autoPath('blog', '', $lang ?? $this->lang);
    }

    /**
     * Dirección que tendría la página en un idioma en el que NO existe: la del idioma principal con el prefijo de ese idioma
     * ('/precios.html' → '/ro/precios.html'). Es el origen de su redirección 301 configurable.
     */
    public function equivalentPath(string $kind, string $slug, string $lang): string
    {
        $def = $this->defaultLang();
        if ($lang === $def || !isset($this->routeIndex()[$kind][$def][$slug])) return $this->autoPath($kind, $slug, $lang);
        $base = $this->itemPath($kind, $slug, $def);
        return '/' . $lang . ($base === '/' ? '/' : $base);
    }

    /** Página o entrada a la que se refiere una ruta escrita en el idioma principal ('/servicios', '/precios.html', '/blog/x'): [kind, slug] o null. */
    public function logicalTarget(string $path): ?array
    {
        $p = $path === '/' ? '/' : rtrim($path, '/');
        if ($p === '/' || $p === '' || $p === '/index.html') return ['page', 'index'];
        if ($p === '/blog' || $p === '/blog.html') return ['blog', ''];
        $idx = $this->routeIndex();
        foreach (['page', 'post'] as $k) {
            foreach ($idx[$k] as $l => $items) {
                foreach ($items as $slug => $it) if ($it['path'] !== '' && $it['path'] === $p) return [$k, $slug];
            }
        }
        if (preg_match('#^/blog/([a-z0-9-]+)(?:\.html)?$#', $p, $m)) return ['post', $m[1]];
        if (preg_match('#^/([a-z0-9-]+)(?:\.html)?$#', $p, $m)) {
            foreach ($idx['page'] as $items) if (isset($items[$m[1]])) return ['page', $m[1]];
        }
        return null;
    }

    /**
     * Qué hay en una ruta pública completa (con prefijo de idioma): ['kind' => page|post|blog, 'slug', 'lang', 'fixed' => bool].
     * Primero las rutas fijadas a mano, luego las automáticas en los dos estilos (con y sin .html). No comprueba si existe
     * ni si está publicada: eso lo decide quien llama, que redirige a la dirección canónica si no coincide con la pedida.
     */
    public function resolvePath(string $full): ?array
    {
        $p = $full === '/' ? '/' : rtrim($full, '/');
        foreach (['pages' => 'page', 'posts' => 'post'] as $t => $k) {
            $r = $p !== '/' ? row("SELECT slug, lang FROM $t WHERE site_id = ? AND path = ? ORDER BY lang = ? DESC LIMIT 1", [$this->id(), $p, $this->defaultLang()]) : null;
            if ($r && in_array($r['lang'], $this->langs(), true)) return ['kind' => $k, 'slug' => $r['slug'], 'lang' => $r['lang'], 'fixed' => true];
        }
        $lang = $this->defaultLang();
        if (preg_match('#^/([a-z]{2}(?:-[a-z]{2})?)(/.*)?$#', $p, $m) && $m[1] !== $lang && in_array($m[1], $this->langs(), true)) {
            $lang = $m[1];
            $p = ($m[2] ?? '') === '' ? '/' : (rtrim($m[2], '/') ?: '/');
        }
        $hit = fn(string $kind, string $slug) => ['kind' => $kind, 'slug' => $slug, 'lang' => $lang, 'fixed' => false];
        if ($p === '/' || $p === '/index.html') return $hit('page', 'index');
        if ($p === '/blog' || $p === '/blog.html') return $hit('blog', '');
        if (preg_match('#^/blog/([a-z0-9-]+)(?:\.html)?$#', $p, $m)) return $hit('post', $m[1]);
        if (preg_match('#^/([a-z0-9-]+)(?:\.html)?$#', $p, $m)) return $hit('page', $m[1]);
        return null;
    }

    /**
     * Valida una ruta fijada a mano. Devuelve la ruta limpia ('' = automática) o lanza UwError con el motivo.
     * Reglas: empieza por /, sin .., sin espacios ni consultas; no pisa rutas del sistema; el prefijo coincide con el idioma
     * (/en/... para inglés; ninguno de otro idioma en el principal); y no la usa ninguna otra página o entrada.
     */
    public function validatePath(string $kind, string $slug, string $lang, string $path, int $ownId = 0): string
    {
        $path = trim($path);
        if ($path === '') return '';
        if ($slug === 'index') throw new UwError('La portada no admite ruta fija: siempre es / (o /en/ en otros idiomas)');
        if (!preg_match('#^(/[A-Za-z0-9._~-]+)+$#', $path) || str_contains($path, '..')) {
            throw new UwError("Ruta no válida: «{$path}». Debe empezar por / y llevar solo letras, números, guiones, puntos y barras (p. ej. /precios.html o /guias/mudanza.html)");
        }
        if (preg_match('#\.(md|txt|xml|json|php\d?|phtml|js|css|ico|png|jpe?g|webp|gif|svg|pdf)$#i', $path)) throw new UwError("La ruta {$path} termina en una extensión reservada para archivos (usa .html o ninguna)");
        $first = strtolower(explode('/', $path)[1]);
        $plain = $lang !== $this->defaultLang() && $first === $lang ? (string) (explode('/', $path, 3)[2] ?? '') : ltrim($path, '/');
        $plainFirst = strtolower(explode('/', $plain)[0]);
        if (in_array($plainFirst, [...RESERVED_SLUGS, 'md', 'site-assets', '_uw', '_ver', '__acceso', 'llms.txt', 'llms-full.txt', 'index.html', 'blog.html'], true) && !($plainFirst === 'blog' && $kind === 'post')) {
            throw new UwError("La ruta {$path} pisa una ruta del sistema (/{$plainFirst}). Elige otra");
        }
        if ($lang !== $this->defaultLang() && $first !== $lang) throw new UwError("Una página en «{$lang}» debe ir bajo /{$lang}/ (p. ej. /{$lang}{$path})");
        if ($lang === $this->defaultLang() && in_array($first, $this->langs(), true) && $first !== $lang) throw new UwError("/{$first}/ es el prefijo del idioma «{$first}»: no se puede usar en una página en «{$lang}»");
        $mine = $kind === 'post' ? 'posts' : 'pages';
        foreach (['pages' => 'page', 'posts' => 'post'] as $t => $k) {
            foreach (rows("SELECT id, slug, lang, path FROM $t WHERE site_id = ?", [$this->id()]) as $r) {
                if ($t === $mine && (int) $r['id'] === $ownId) continue;
                $other = $this->itemPath($k, $r['slug'], $r['lang'], (string) $r['path']);
                if (rtrim($other, '/') === rtrim($path, '/')) throw new UwError("La ruta {$path} ya la usa " . ($k === 'post' ? 'la entrada' : 'la página') . " «{$r['slug']}» ({$r['lang']})");
            }
        }
        return $path;
    }

    public function langUrl(string $path, ?string $lang = null): string
    {
        return $this->url($this->langPath($path, $lang));
    }

    public function id(): int
    {
        return (int) $this->site['id'];
    }

    public function url(string $path = '/'): string
    {
        return site_base_url($this->site) . $path;
    }

    // ------------------------------------------------ ajustes

    /** Ajustes tal y como se ven en el idioma actual (textos traducidos y enlaces internos con su prefijo). */
    public function settings(): array
    {
        return $this->memo['s|' . $this->lang . '|' . md5((string) $this->site['settings'])] ??= $this->computeSettings();
    }

    private function computeSettings(): array
    {
        $s = $this->baseSettings();
        $local = fn(string $u) => str_starts_with($u, '/') && !str_starts_with($u, '//') ? $this->langPath($u) : $u;
        if ($this->lang === $this->defaultLang()) {
            // Enlaces internos a su dirección real (/contacto → /contacto.html si la web usa .html o la página tiene ruta fija)
            foreach (['menu', 'utility_menu'] as $k) $s[$k] = array_map(fn($m) => ['label' => $m['label'], 'url' => $local($m['url'])], $s[$k]);
            $s['cta']['url'] = $local($s['cta']['url']);
            return $s;
        }
        $tr = is_array($s['i18n'][$this->lang] ?? null) ? $s['i18n'][$this->lang] : [];
        foreach (['tagline', 'footer_text'] as $k) if (($tr[$k] ?? '') !== '') $s[$k] = (string) $tr[$k];
        if (($tr['seo']['description'] ?? '') !== '') $s['seo']['description'] = (string) $tr['seo']['description'];
        foreach (['menu', 'utility_menu'] as $k) {
            if (!empty($tr[$k]) && is_array($tr[$k])) {
                $s[$k] = array_values(array_filter(array_map(fn($m) => ['label' => (string) ($m['label'] ?? ''), 'url' => $this->linkInLang(clean_url($m['url'] ?? ''))], array_filter($tr[$k], 'is_array')), fn($m) => $m['url'] !== ''));
            } else {
                // Sin traducción: mismos enlaces llevados a este idioma (/contacto → /en/contacto).
                $s[$k] = array_map(fn($m) => ['label' => $m['label'], 'url' => $local($m['url'])], $s[$k]);
            }
        }
        if (!empty($tr['cta']['label'])) $s['cta'] = ['label' => (string) $tr['cta']['label'], 'url' => $this->linkInLang(clean_url($tr['cta']['url'] ?? $s['cta']['url']))];
        else $s['cta']['url'] = $local($s['cta']['url']);
        $s['language'] = $this->lang;
        return $s;
    }

    /** Ajustes guardados (idioma principal), sin traducir: base para editar. */
    public function baseSettings(): array
    {
        return $this->memo['b|' . md5((string) $this->site['settings'])] ??= $this->computeBaseSettings();
    }

    private function computeBaseSettings(): array
    {
        $s = array_replace_recursive(site_defaults(), json_dec($this->site['settings']));
        // Todas las URLs pasan por clean_url() también al leer: nada de javascript: aunque venga de una copia antigua.
        foreach (['menu', 'utility_menu'] as $k) {
            $s[$k] = array_values(array_filter(array_map(fn($m) => ['label' => (string) ($m['label'] ?? ''), 'url' => clean_url($m['url'] ?? '')],
                is_array($s[$k]) ? array_filter($s[$k], 'is_array') : []), fn($m) => $m['url'] !== ''));
        }
        $s['cta'] = ['label' => (string) ($s['cta']['label'] ?? ''), 'url' => clean_url($s['cta']['url'] ?? '')];
        foreach ($s['social'] as $k => $u) $s['social'][$k] = preg_match('#^https://#i', clean_url($u)) ? clean_url($u) : '';
        $s['logo'] = clean_url($s['logo']);
        $s['seo']['image'] = clean_url($s['seo']['image'] ?? '');
        if (!preg_match('/^#[0-9a-f]{6}$/i', (string) $s['accent'])) $s['accent'] = site_defaults()['accent'];
        return $s;
    }

    /** Actualiza ajustes con un parche parcial. Los objetos se fusionan; menu se reemplaza. */
    public function updateSettings(array $patch): array
    {
        $s = $this->baseSettings();
        unset($patch['private']);
        if (array_key_exists('languages', $patch)) {
            if (!is_array($patch['languages'])) throw new UwError('languages debe ser una lista de códigos de idioma, p. ej. ["en", "ro"]');
            $langs = array_values(array_unique(array_map(fn($l) => strtolower(trim((string) $l)), $patch['languages'])));
            foreach ($langs as $l) if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', $l)) throw new UwError("Código de idioma no válido: «{$l}» (usa ISO 639-1: en, ro, fr…)");
            $s['languages'] = array_values(array_diff($langs, [$patch['language'] ?? $s['language']]));
        }
        if (isset($patch['translations'])) {
            if (!is_array($patch['translations'])) throw new UwError('translations debe ser un objeto {"en": {...}, "ro": {...}}');
            foreach ($patch['translations'] as $l => $tr) {
                if (!in_array($l, $s['languages'], true)) throw new UwError("Primero añade el idioma «{$l}» con languages");
                if (!is_array($tr)) continue;
                $cur = is_array($s['i18n'][$l] ?? null) ? $s['i18n'][$l] : [];
                foreach (['tagline', 'footer_text'] as $k) if (array_key_exists($k, $tr)) $cur[$k] = mb_substr(trim((string) $tr[$k]), 0, 2000);
                if (isset($tr['seo']['description'])) $cur['seo']['description'] = mb_substr(trim((string) $tr['seo']['description']), 0, 500);
                foreach (['menu', 'utility_menu'] as $k) {
                    if (!array_key_exists($k, $tr)) continue;
                    $cur[$k] = array_values(array_filter(array_map(fn($m) => ['label' => trim((string) ($m['label'] ?? '')), 'url' => trim((string) ($m['url'] ?? ''))], array_filter((array) $tr[$k], 'is_array')), fn($m) => $m['label'] !== '' && clean_url($m['url']) !== ''));
                }
                if (array_key_exists('cookies_text', $tr)) $cur['cookies_text'] = mb_substr(trim((string) $tr['cookies_text']), 0, 1000);
                if (isset($tr['cta']) && is_array($tr['cta'])) $cur['cta'] = ['label' => trim((string) ($tr['cta']['label'] ?? '')), 'url' => trim((string) ($tr['cta']['url'] ?? ''))];
                $s['i18n'][$l] = $cur;
            }
        }
        foreach (['tagline', 'language', 'logo', 'accent', 'footer_text'] as $k) {
            if (array_key_exists($k, $patch)) $s[$k] = mb_substr(trim((string) $patch[$k]), 0, 2000);
        }
        if (array_key_exists('accent', $patch) && !preg_match('/^#[0-9a-f]{6}$/i', $s['accent'])) throw new UwError('accent debe ser un color tipo #4f46e5');
        if (array_key_exists('cookies', $patch)) $s['cookies'] = cookies_merge((array) $s['cookies'], $patch['cookies']);
        if (array_key_exists('ai_markdown', $patch)) $s['ai_markdown'] = filter_var($patch['ai_markdown'], FILTER_VALIDATE_BOOLEAN);
        if (array_key_exists('indexnow', $patch)) $s['indexnow'] = filter_var($patch['indexnow'], FILTER_VALIDATE_BOOLEAN);
        if (array_key_exists('url_style', $patch)) {
            if (!in_array($patch['url_style'], ['clean', 'html'], true)) throw new UwError("url_style debe ser 'clean' (/servicios) o 'html' (/servicios.html)");
            $s['url_style'] = $patch['url_style'];
        }
        if (array_key_exists('same_as', $patch)) {
            $list = is_string($patch['same_as']) ? preg_split('/[\s,]+/', $patch['same_as'], -1, PREG_SPLIT_NO_EMPTY) : $patch['same_as'];
            if (!is_array($list)) throw new UwError('same_as debe ser una lista de URLs https://');
            $s['same_as'] = [];
            foreach (array_slice($list, 0, 30) as $u) {
                $u = trim((string) $u);
                if (!preg_match('#^https://[^/\s]+\.[^/\s]+#i', $u) || clean_url($u) !== $u || mb_strlen($u) > 500) throw new UwError("same_as: «{$u}» no es una URL https:// válida");
                $s['same_as'][] = $u;
            }
            $s['same_as'] = array_values(array_unique($s['same_as']));
        }
        if (array_key_exists('locales', $patch)) {
            if (!is_array($patch['locales'])) throw new UwError('locales debe ser un objeto {"en": "en_US"}');
            $s['locales'] = [];
            foreach ($patch['locales'] as $l => $loc) {
                if ((string) $loc === '') continue;
                if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/', (string) $l) || !preg_match('/^[a-z]{2}_[A-Z]{2}$/', (string) $loc)) throw new UwError("locales: «{$l}: {$loc}» no vale; usa {\"en\": \"en_US\"}");
                $s['locales'][$l] = (string) $loc;
            }
        }
        if (array_key_exists('ai_crawlers', $patch)) {
            if (!in_array($patch['ai_crawlers'], ['allow_all', 'search_only', 'block_all'], true)) throw new UwError("ai_crawlers debe ser allow_all, search_only o block_all");
            $s['ai_crawlers'] = $patch['ai_crawlers'];
        }
        if (isset($patch['business']['type']) && !array_key_exists((string) $patch['business']['type'], GEO_BUSINESS_TYPES)) {
            throw new UwError('business.type no válido. Opciones: ' . implode(', ', array_keys(GEO_BUSINESS_TYPES)));
        }
        foreach (['contact', 'social', 'seo', 'business'] as $group) {
            if (!isset($patch[$group])) continue;
            if (!is_array($patch[$group])) throw new UwError("$group debe ser un objeto");
            foreach ($s[$group] as $k => $_) {
                if (array_key_exists($k, $patch[$group])) $s[$group][$k] = mb_substr(trim((string) $patch[$group][$k]), 0, 500);
            }
        }
        foreach (['menu', 'utility_menu'] as $k) {
            if (!array_key_exists($k, $patch)) continue;
            if (!is_array($patch[$k])) throw new UwError("$k debe ser una lista de {label, url}");
            $s[$k] = array_values(array_filter(array_map(fn($m) => [
                'label' => trim((string) ($m['label'] ?? '')), 'url' => trim((string) ($m['url'] ?? '')),
            ], array_filter($patch[$k], 'is_array')), fn($m) => $m['label'] !== '' && $m['url'] !== ''));
        }
        foreach (['menu', 'utility_menu'] as $k) {
            foreach ($s[$k] as $m) if (clean_url($m['url']) === '') throw new UwError("Enlace no permitido en $k: «{$m['url']}». Usa una ruta (/contacto) o una URL https://");
        }
        foreach ($s['social'] as $k => $u) {
            if ($u !== '' && !preg_match('#^https://#i', clean_url($u))) throw new UwError("La red social $k debe ser una URL https://");
        }
        if ($s['logo'] !== '' && clean_url($s['logo']) === '') throw new UwError('URL del logo no permitida');
        if (isset($patch['cta'])) {
            if (!is_array($patch['cta'])) throw new UwError('cta debe ser un objeto {label, url}');
            if (trim((string) ($patch['cta']['url'] ?? '')) !== '' && clean_url($patch['cta']['url']) === '') throw new UwError('Enlace del botón destacado no permitido');
            $s['cta'] = ['label' => trim((string) ($patch['cta']['label'] ?? '')), 'url' => trim((string) ($patch['cta']['url'] ?? ''))];
        }
        $fields = ['settings' => json_enc($s)];
        if (isset($patch['name']) && trim((string) $patch['name']) !== '') $fields['name'] = trim((string) $patch['name']);
        if (isset($patch['theme'])) {
            if (!theme_exists((string) $patch['theme'])) throw new UwError("El tema '{$patch['theme']}' no existe. Disponibles: " . implode(', ', array_keys(themes_available())));
            $fields['theme'] = $patch['theme'];
        }
        if (isset($patch['language']) && !preg_match('/^[a-z]{2}(-[a-z]{2})?$/', (string) $patch['language'])) throw new UwError('language debe ser un código como es, en o ro');
        update('sites', $fields, 'id = ?', [$this->id()]);
        $this->site = site_by_id($this->id());
        if (!in_array($this->lang, $this->langs(), true)) $this->lang = $this->defaultLang();
        audit($this->id(), 'site.update', $this->site['slug'], ['fields' => array_keys($patch)]);
        return $this->settings();
    }

    /** Modo demo: activa/desactiva el acceso privado. Contraseña vacía conserva la anterior. Solo desde el panel. */
    public function setPrivate(bool $enabled, string $password = ''): void
    {
        $s = json_dec($this->site['settings']);
        $cur = $s['private'] ?? ['enabled' => false, 'password_hash' => ''];
        // (se trabaja sobre el JSON guardado: nunca sobre la vista traducida)
        if ($password !== '') {
            if (mb_strlen($password) < 6) throw new UwError('La contraseña de la demo debe tener al menos 6 caracteres');
            $cur['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        if ($enabled && $cur['password_hash'] === '') throw new UwError('Pon una contraseña para la web privada');
        $cur['enabled'] = $enabled;
        $s['private'] = $cur;
        update('sites', ['settings' => json_enc($s)], 'id = ?', [$this->id()]);
        $this->site = site_by_id($this->id());
        audit($this->id(), 'site.private', $enabled ? 'activado' : 'desactivado');
    }

    public function isPrivate(): bool
    {
        return !empty($this->settings()['private']['enabled']);
    }

    public function publicInfo(): array
    {
        $s = $this->settings();
        unset($s['private']); // nunca sale por la API ni por MCP
        $s['languages'] = $this->langs();
        return ['id' => $this->id(), 'slug' => $this->site['slug'], 'name' => $this->site['name'], 'url' => $this->langUrl('/'), 'lang' => $this->lang,
            'theme' => $this->site['theme'], 'domains' => site_domains($this->id())] + $s;
    }

    // ------------------------------------------------ páginas

    private function decodePage(?array $p): ?array
    {
        if (!$p) return null;
        $p['blocks'] = json_dec($p['blocks']);
        $p['seo'] = sanitize_seo(json_dec($p['seo']));
        $p['public_path'] = $this->itemPath('page', $p['slug'], $p['lang'], (string) ($p['path'] ?? ''));
        $p['url'] = $this->url($p['public_path']);
        return $p;
    }

    /** Páginas del idioma actual (o de todos con $allLangs). */
    public function pages(bool $publishedOnly = false, bool $allLangs = false): array
    {
        $sql = 'SELECT id, slug, lang, title, status, path, updated_at, content_at FROM pages WHERE site_id = ?' . ($allLangs ? '' : ' AND lang = ?')
            . ($publishedOnly ? " AND status = 'published'" : '') . " ORDER BY slug = 'index' DESC, title, lang";
        $params = $allLangs ? [$this->id()] : [$this->id(), $this->lang];
        return array_map(function ($p) {
            $p['public_path'] = $this->itemPath('page', $p['slug'], $p['lang'], (string) $p['path']);
            return $p + ['url' => $this->url($p['public_path'])];
        }, rows($sql, $params));
    }

    public function page(string $slug, ?string $lang = null): ?array
    {
        return $this->decodePage(row('SELECT * FROM pages WHERE site_id = ? AND lang = ? AND slug = ?', [$this->id(), $lang ?? $this->lang, $slug]));
    }

    /** Idiomas en los que existe una página (o entrada) con ese slug: ['es' => 'published', 'en' => 'draft']. */
    public function translations(string $table, string $slug): array
    {
        $table = $table === 'posts' ? 'posts' : 'pages';
        return array_column(rows("SELECT lang, status FROM $table WHERE site_id = ? AND slug = ?", [$this->id(), $slug]), 'status', 'lang');
    }

    public function pageById(int $id): ?array
    {
        return $this->decodePage(row('SELECT * FROM pages WHERE site_id = ? AND id = ?', [$this->id(), $id]));
    }

    /** Redirección 301 creada por el último savePage/savePost (['from' => …, 'to' => …]) o null. */
    public ?array $lastRedirect = null;

    public function savePage(array $in, ?int $id = null): array
    {
        $cur = $id ? $this->pageById($id) : null;
        if ($id && !$cur) throw new UwError('Página no encontrada');
        $title = trim((string) ($in['title'] ?? $cur['title'] ?? ''));
        if ($title === '') throw new UwError('La página necesita un título');
        $slug = slugify((string) ($in['slug'] ?? $cur['slug'] ?? $title));
        if ($slug === '') throw new UwError('Slug no válido');
        if (in_array($slug, RESERVED_SLUGS, true) || in_array($slug, $this->langs(), true)) throw new UwError("El slug '$slug' está reservado por el sistema");
        $lang = (string) ($in['lang'] ?? $cur['lang'] ?? $this->lang);
        if (!in_array($lang, $this->langs(), true)) throw new UwError("La web no tiene el idioma «{$lang}». Idiomas: " . implode(', ', $this->langs()));
        $dup = val('SELECT id FROM pages WHERE site_id = ? AND lang = ? AND slug = ? AND id != ?', [$this->id(), $lang, $slug, $id ?? 0]);
        if ($dup) throw new UwError("Ya existe una página con el slug '$slug' en el idioma «{$lang}»");
        $status = (string) ($in['status'] ?? $cur['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published'], true)) throw new UwError("status debe ser 'draft' o 'published'");
        $path = array_key_exists('path', $in) ? $this->validatePath('page', $slug, $lang, (string) $in['path'], (int) $id) : ($cur ? $this->validatePath('page', $slug, $lang, (string) $cur['path'], (int) $id) : '');

        $data = [
            'slug' => $slug, 'lang' => $lang, 'title' => $title, 'status' => $status, 'path' => $path, 'updated_at' => now(),
            'blocks' => json_enc(array_key_exists('blocks', $in) ? sanitize_blocks($in['blocks']) : ($cur['blocks'] ?? [])),
            'seo' => json_enc(sanitize_seo($in['seo'] ?? [], $cur['seo'] ?? [])),
        ];
        $changed = true;
        if ($cur) {
            // Historial: se guarda la versión anterior si cambia algo de verdad (no solo la fecha)
            $raw = row('SELECT * FROM pages WHERE id = ?', [$id]);
            $diff = array_diff_assoc(array_map('strval', array_diff_key($data, ['updated_at' => 1])), array_map('strval', array_intersect_key($raw, $data)));
            if ($diff) history_snapshot($this->id(), 'page', $raw);
            // Fecha de contenido (dateModified, sitemap, IndexNow): solo si cambia el contenido o pasa a publicada
            $changed = (bool) array_diff_key($diff, ['status' => 1]) || ($raw['status'] !== 'published' && $status === 'published');
            if ($changed) $data['content_at'] = now();
            update('pages', $data, 'id = ? AND site_id = ?', [$id, $this->id()]);
        } else {
            $id = insert('pages', $data + ['site_id' => $this->id(), 'created_at' => now(), 'content_at' => now()]);
        }
        $this->forgetRoutes();
        $this->lastRedirect = null;
        $newPath = $this->itemPath('page', $slug, $lang, $path);
        if ($status === 'published') redirect_drop($this->id(), $newPath); // una página viva tapa cualquier redirección desde su ruta
        $oldPath = $cur ? (string) $cur['public_path'] : '';
        if ($cur && $cur['status'] === 'published' && $status === 'published' && rtrim($oldPath, '/') !== rtrim($newPath, '/') && $cur['slug'] !== 'index' && $slug !== 'index')
            $this->lastRedirect = redirect_add($this->id(), $cur['lang'], $oldPath, $newPath);
        audit($this->id(), $cur ? 'page.update' : 'page.create', $lang === $this->defaultLang() ? $slug : "$lang/$slug", ['status' => $status]);
        if ($status === 'published' && $changed) indexnow_queue($this, $newPath);
        if ($cur && $cur['status'] === 'published' && $status !== 'published') indexnow_queue($this, $oldPath); // despublicada: que la retiren
        return $this->pageById($id);
    }

    public function deletePage(int $id): void
    {
        $p = $this->pageById($id) ?? throw new UwError('Página no encontrada');
        trash_put($this->id(), 'page', row('SELECT * FROM pages WHERE id = ?', [$id]));
        q('DELETE FROM pages WHERE id = ? AND site_id = ?', [$id, $this->id()]);
        $this->forgetRoutes();
        audit($this->id(), 'page.delete', $p['slug']);
        if ($p['status'] === 'published') indexnow_queue($this, $p['public_path']);
    }

    // ------------------------------------------------ entradas del blog

    private function decodePost(?array $p): ?array
    {
        if (!$p) return null;
        $p['tags'] = json_dec($p['tags']);
        $p['cover_image'] = clean_url($p['cover_image']);
        $p['seo'] = sanitize_seo(json_dec($p['seo']));
        $p['public_path'] = $this->itemPath('post', $p['slug'], $p['lang'], (string) ($p['path'] ?? ''));
        $p['url'] = $this->url($p['public_path']);
        $p['state'] = $p['status'] === 'published' ? ($p['published_at'] > now() ? 'scheduled' : 'published') : 'draft';
        return $p;
    }

    /** @param array{status?:string, tag?:string, limit?:int, offset?:int, public?:bool} $o */
    public function posts(array $o = []): array
    {
        [$where, $params] = $this->postsWhere($o);
        $limit = max(1, min(100, (int) ($o['limit'] ?? 20)));
        $offset = max(0, (int) ($o['offset'] ?? 0));
        $list = rows("SELECT * FROM posts WHERE $where ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT $limit OFFSET $offset", $params);
        return array_map(fn($p) => $this->decodePost($p), $list);
    }

    public function countPosts(array $o = []): int
    {
        [$where, $params] = $this->postsWhere($o);
        return (int) val("SELECT COUNT(*) FROM posts WHERE $where", $params);
    }

    private function postsWhere(array $o): array
    {
        $where = 'site_id = ?';
        $params = [$this->id()];
        if (empty($o['all_langs'])) {
            $where .= ' AND lang = ?';
            $params[] = $o['lang'] ?? $this->lang;
        }
        if (!empty($o['public'])) {
            $where .= " AND status = 'published' AND published_at <= ?";
            $params[] = now();
        } elseif (!empty($o['status'])) {
            $where .= ' AND status = ?';
            $params[] = $o['status'];
        }
        if (!empty($o['tag'])) {
            $where .= ' AND EXISTS (SELECT 1 FROM json_each(posts.tags) WHERE json_each.value = ?)';
            $params[] = $o['tag'];
        }
        return [$where, $params];
    }

    public function post(string $slug, ?string $lang = null): ?array
    {
        return $this->decodePost(row('SELECT * FROM posts WHERE site_id = ? AND lang = ? AND slug = ?', [$this->id(), $lang ?? $this->lang, $slug]));
    }

    public function postById(int $id): ?array
    {
        return $this->decodePost(row('SELECT * FROM posts WHERE site_id = ? AND id = ?', [$this->id(), $id]));
    }

    public function savePost(array $in, ?int $id = null): array
    {
        $cur = $id ? $this->postById($id) : null;
        if ($id && !$cur) throw new UwError('Entrada no encontrada');
        $title = trim((string) ($in['title'] ?? $cur['title'] ?? ''));
        if ($title === '') throw new UwError('La entrada necesita un título');
        $slug = slugify((string) (($in['slug'] ?? '') !== '' ? $in['slug'] : ($cur['slug'] ?? $title)));
        if ($slug === '') throw new UwError('Slug no válido');
        $lang = (string) ($in['lang'] ?? $cur['lang'] ?? $this->lang);
        if (!in_array($lang, $this->langs(), true)) throw new UwError("La web no tiene el idioma «{$lang}». Idiomas: " . implode(', ', $this->langs()));
        $dup = val('SELECT id FROM posts WHERE site_id = ? AND lang = ? AND slug = ? AND id != ?', [$this->id(), $lang, $slug, $id ?? 0]);
        if ($dup) throw new UwError("Ya existe una entrada con el slug '$slug' en el idioma «{$lang}»");
        $status = (string) ($in['status'] ?? $cur['status'] ?? 'draft');
        if (!in_array($status, ['draft', 'published'], true)) throw new UwError("status debe ser 'draft' o 'published'");

        $tags = $in['tags'] ?? $cur['tags'] ?? [];
        if (is_string($tags)) $tags = explode(',', $tags);
        $tags = array_values(array_unique(array_filter(array_map(fn($t) => mb_strtolower(trim((string) $t)), (array) $tags))));

        $publishedAt = array_key_exists('published_at', $in) ? normalize_datetime((string) $in['published_at']) : ($cur['published_at'] ?? null);
        if ($status === 'published' && !$publishedAt) $publishedAt = now();

        $path = array_key_exists('path', $in) ? $this->validatePath('post', $slug, $lang, (string) $in['path'], (int) $id) : ($cur ? $this->validatePath('post', $slug, $lang, (string) $cur['path'], (int) $id) : '');
        $data = [
            'slug' => $slug, 'lang' => $lang, 'title' => $title, 'status' => $status, 'path' => $path, 'published_at' => $publishedAt, 'updated_at' => now(),
            'excerpt' => trim((string) ($in['excerpt'] ?? $cur['excerpt'] ?? '')),
            'content' => (string) ($in['content'] ?? $cur['content'] ?? ''),
            'cover_image' => clean_url(trim((string) ($in['cover_image'] ?? $cur['cover_image'] ?? ''))),
            'audio' => clean_url(trim((string) ($in['audio'] ?? $cur['audio'] ?? ''))),
            'author' => trim((string) ($in['author'] ?? $cur['author'] ?? Actor::$name)),
            'tags' => json_enc($tags),
            'seo' => json_enc(sanitize_seo($in['seo'] ?? [], $cur['seo'] ?? [])),
        ];
        $changed = true;
        if ($cur) {
            $raw = row('SELECT * FROM posts WHERE id = ?', [$id]);
            $diff = array_diff_assoc(array_map('strval', array_diff_key($data, ['updated_at' => 1])), array_map('strval', array_intersect_key($raw, $data)));
            if ($diff) history_snapshot($this->id(), 'post', $raw);
            $changed = (bool) array_diff_key($diff, ['status' => 1, 'published_at' => 1]) || ($raw['status'] !== 'published' && $status === 'published');
            if ($changed) $data['content_at'] = now();
            update('posts', $data, 'id = ? AND site_id = ?', [$id, $this->id()]);
        } else {
            $id = insert('posts', $data + ['site_id' => $this->id(), 'created_at' => now(), 'content_at' => now()]);
        }
        $this->forgetRoutes();
        $this->lastRedirect = null;
        $newPath = $this->itemPath('post', $slug, $lang, $path);
        if ($status === 'published') redirect_drop($this->id(), $newPath);
        $oldPath = $cur ? (string) $cur['public_path'] : '';
        if ($cur && $cur['status'] === 'published' && $status === 'published' && rtrim($oldPath, '/') !== rtrim($newPath, '/'))
            $this->lastRedirect = redirect_add($this->id(), $cur['lang'], $oldPath, $newPath);
        audit($this->id(), $cur ? 'post.update' : 'post.create', $slug, ['status' => $status]);
        if ($status === 'published' && $changed) indexnow_queue($this, $newPath);
        if ($cur && $cur['status'] === 'published' && $status !== 'published') indexnow_queue($this, $oldPath);
        return $this->postById($id);
    }

    public function deletePost(int $id): void
    {
        $p = $this->postById($id) ?? throw new UwError('Entrada no encontrada');
        trash_put($this->id(), 'post', row('SELECT * FROM posts WHERE id = ?', [$id]));
        q('DELETE FROM posts WHERE id = ? AND site_id = ?', [$id, $this->id()]);
        $this->forgetRoutes();
        audit($this->id(), 'post.delete', $p['slug']);
        if ($p['status'] === 'published') indexnow_queue($this, $p['public_path']);
    }

    // ------------------------------------------------ media

    public function media(): array
    {
        return array_map(fn($m) => $m + ['url' => '/media/' . $m['path']], rows('SELECT * FROM media WHERE site_id = ? ORDER BY id DESC', [$this->id()]));
    }

    /** Búsqueda en la biblioteca: por tipo (image, video, audio, pdf) y texto (título, alt o nombre). */
    public function mediaSearch(string $kind = '', string $q = '', int $limit = 50): array
    {
        $sql = 'SELECT * FROM media WHERE site_id = ?';
        $p = [$this->id()];
        if ($kind !== '') { $sql .= ' AND kind = ?'; $p[] = $kind; }
        // Cada palabra debe aparecer en el título, el alt o el nombre («voz servicios» encuentra «Voz página servicios»).
        foreach (array_slice(array_filter(preg_split('/\s+/', trim($q))), 0, 6) as $w) {
            $sql .= ' AND (title LIKE ? OR alt LIKE ? OR original_name LIKE ?)';
            $like = '%' . $w . '%';
            array_push($p, $like, $like, $like);
        }
        return array_map(fn($m) => $m + ['url' => '/media/' . $m['path']], rows($sql . ' ORDER BY id DESC LIMIT ' . max(1, min(200, $limit)), $p));
    }

    /** Cambia el texto alternativo y el título de un archivo (no el archivo). */
    public function updateMedia(int $id, array $in): array
    {
        row('SELECT id FROM media WHERE id = ? AND site_id = ?', [$id, $this->id()]) ?? throw new UwError('Archivo no encontrado');
        $f = [];
        if (array_key_exists('alt', $in)) $f['alt'] = mb_substr(trim((string) $in['alt']), 0, 500);
        if (array_key_exists('title', $in)) $f['title'] = mb_substr(trim((string) $in['title']), 0, 200);
        if ($f) update('media', $f, 'id = ? AND site_id = ?', [$id, $this->id()]);
        audit($this->id(), 'media.update', (string) $id);
        $m = row('SELECT * FROM media WHERE id = ?', [$id]);
        return $m + ['url' => '/media/' . $m['path']];
    }

    /** Guarda un archivo en public/media/{web}/AAAA/MM/. $uploaded = viene de un formulario HTTP. */
    public function storeMedia(string $tmp, string $originalName, string $alt = '', bool $uploaded = true, array $meta = []): array
    {
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!isset(MEDIA_TYPES[$ext])) throw new UwError('Tipo de archivo no permitido. Permitidos: ' . implode(', ', array_keys(MEDIA_TYPES)));
        $size = (int) filesize($tmp);
        $isAv = str_starts_with(MEDIA_TYPES[$ext], 'video/') || str_starts_with(MEDIA_TYPES[$ext], 'audio/');
        // Una foto de móvil (10–20 MB) se admite si se va a optimizar: lo que se guarda pasa por el límite normal
        $optimizable = empty($meta['keep_original']) && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) && function_exists('imagewebp');
        $max = $isAv ? MEDIA_MAX_BYTES_AV : ($optimizable ? MEDIA_MAX_BYTES_RAW : MEDIA_MAX_BYTES);
        if ($size <= 0 || $size > $max) throw new UwError('El archivo está vacío o supera los ' . intdiv($max, 1048576) . ' MB');
        if (!media_content_matches($tmp, $ext)) throw new UwError("El contenido del archivo no corresponde a un .$ext válido");
        // Generada por IA (dicho por quien sube o por su propia marca): la marca sobrevive a la optimización.
        $ai = !empty($meta['ai_generated']) || (!$isAv && media_ai_detect($tmp));
        // Fotos grandes: a 2000 px como máximo y WebP si pesa menos (y sin EXIF: el GPS del móvil no se publica)
        $opt = empty($meta['keep_original']) ? media_optimize($tmp, $ext) : null;
        if ($opt) { [$tmp, $ext, $size] = [$opt['file'], $opt['ext'], $opt['size']]; $uploaded = false; $originalName = pathinfo($originalName, PATHINFO_FILENAME) . '.' . $ext; }
        if (!$isAv && $size > MEDIA_MAX_BYTES) throw new UwError('La imagen supera los ' . intdiv(MEDIA_MAX_BYTES, 1048576) . ' MB y no se ha podido reducir');
        $rel = $this->site['slug'] . '/' . date('Y/m');
        $dir = UW_PUBLIC . '/media/' . $rel;
        if (!is_dir($dir) && !mkdir($dir, 0775, true)) throw new UwError('No se puede escribir en la carpeta media');
        $base = slugify(pathinfo($originalName, PATHINFO_FILENAME)) ?: 'archivo';
        $file = substr($base, 0, 60) . '-' . bin2hex(random_bytes(3)) . '.' . $ext;
        $ok = $uploaded ? move_uploaded_file($tmp, "$dir/$file") : rename($tmp, "$dir/$file");
        if (!$ok) throw new UwError('No se pudo guardar el archivo');
        // Los temporales se crean con permisos 600: el servidor web (otro usuario en Plesk/cPanel) no podría leerlo.
        @chmod("$dir/$file", 0644);
        if ($ai && $opt) media_add_ai_xmp("$dir/$file"); // GD la reescribió sin metadatos
        if (!$isAv && empty($meta['keep_original'])) media_variants("$dir/$file", $ai); // 480w y 960w para el móvil (srcset)
        $id = insert('media', [
            'site_id' => $this->id(), 'path' => "$rel/$file", 'original_name' => $originalName, 'mime' => MEDIA_TYPES[$ext],
            'size' => $size, 'alt' => mb_substr(trim($alt), 0, 500), 'created_at' => now(),
            'title' => mb_substr(trim((string) ($meta['title'] ?? '')), 0, 200), 'kind' => media_kind(MEDIA_TYPES[$ext]),
            'ai_generated' => $ai ? 1 : 0,
        ]);
        audit($this->id(), 'media.upload', $originalName);
        $m = row('SELECT * FROM media WHERE id = ?', [$id]);
        return $m + ['url' => '/media/' . $m['path']] + ($opt ? ['optimized' => $opt['note']] : []);
    }

    /** Descarga un archivo público (p. ej. una imagen o vídeo generado por IA) y lo guarda en la biblioteca. */
    public function storeMediaFromUrl(string $url, string $alt = '', ?string $filename = null, array $meta = []): array
    {
        $tmp = http_fetch_public($url, MEDIA_MAX_BYTES_AV);
        try {
            $name = $filename ?: basename((string) parse_url($url, PHP_URL_PATH)) ?: 'archivo';
            if (pathinfo($name, PATHINFO_EXTENSION) === '') $name .= '.' . (string) array_search(mime_content_type_safe($tmp), MEDIA_TYPES, true);
            return $this->storeMedia($tmp, $name, $alt, false, $meta);
        } finally {
            if (is_file($tmp)) unlink($tmp);
        }
    }

    public function storeMediaBase64(string $filename, string $b64, string $alt = '', array $meta = []): array
    {
        $bin = base64_decode(preg_replace('#^data:[^,]+,#', '', $b64), true);
        if ($bin === false) throw new UwError('data_base64 no es base64 válido');
        $tmp = tempnam(sys_get_temp_dir(), 'uw');
        file_put_contents($tmp, $bin);
        try {
            return $this->storeMedia($tmp, basename($filename), $alt, false, $meta);
        } finally {
            if (is_file($tmp)) unlink($tmp);
        }
    }

    public function deleteMedia(int $id): void
    {
        $m = row('SELECT * FROM media WHERE id = ? AND site_id = ?', [$id, $this->id()]) ?? throw new UwError('Archivo no encontrado');
        $path = UW_PUBLIC . '/media/' . $m['path'];
        if (is_file($path)) unlink($path);
        foreach (media_variant_files($path) as $v) @unlink($v);
        q('DELETE FROM media WHERE id = ?', [$id]);
        audit($this->id(), 'media.delete', $m['original_name']);
    }

    // ------------------------------------------------ mensajes de contacto

    public function messages(bool $unreadOnly = false, int $limit = 100): array
    {
        return rows('SELECT * FROM messages WHERE site_id = ?' . ($unreadOnly ? ' AND is_read = 0' : '') . ' ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)), [$this->id()]);
    }

    public function addMessage(array $in): int
    {
        $name = trim((string) ($in['name'] ?? ''));
        $email = trim((string) ($in['email'] ?? ''));
        $body = trim((string) ($in['message'] ?? ''));
        if ($name === '' || $body === '') throw new UwError('Nombre y mensaje son obligatorios');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UwError('Email no válido');
        if (!throttle('contact:' . $this->id() . ':' . client_ip(), 5, 600)) throw new UwError('Demasiados mensajes. Inténtalo más tarde.');
        return insert('messages', [
            'site_id' => $this->id(), 'name' => mb_substr($name, 0, 200), 'email' => mb_substr($email, 0, 200),
            'subject' => mb_substr(trim((string) ($in['subject'] ?? '')), 0, 300), 'body' => mb_substr($body, 0, 10000),
            'phone' => mb_substr(preg_replace('/[^0-9+()\s.-]/', '', (string) ($in['phone'] ?? '')), 0, 40),
            'page' => preg_match('#^/(?!/)[a-z0-9/-]{0,300}$#', (string) ($in['page'] ?? '')) ? (string) $in['page'] : '/', 'ip' => client_ip(), 'created_at' => now(),
        ]);
    }

    public function setMessageRead(int $id, bool $read = true): void
    {
        q('UPDATE messages SET is_read = ? WHERE id = ? AND site_id = ?', [$read ? 1 : 0, $id, $this->id()]);
    }

    public function deleteMessage(int $id): void
    {
        q('DELETE FROM messages WHERE id = ? AND site_id = ?', [$id, $this->id()]);
    }

    // ------------------------------------------------ SEO y estadísticas

    public function seoAudit(): array
    {
        $issues = [];
        $add = function (string $severity, string $where, string $msg) use (&$issues) {
            $issues[] = ['severity' => $severity, 'where' => $where, 'message' => $msg];
        };
        $s = $this->settings();
        if ($s['seo']['description'] === '') $add('warning', 'Ajustes', 'Falta la descripción SEO por defecto de la web');
        if ($s['tagline'] === '') $add('notice', 'Ajustes', 'Falta el eslogan de la web');
        if (!$this->page('index') || $this->page('index')['status'] !== 'published') $add('error', 'Inicio', "No hay página de inicio publicada (slug 'index')");

        $titles = [];
        $multi = count($this->langs()) > 1;
        foreach ($this->pages(true, true) as $meta) {
            $p = $this->page($meta['slug'], $meta['lang']);
            $where = 'Página ' . $p['public_path'];
            if ($p['seo']['noindex']) { $add('notice', $where, 'Marcada como noindex: no aparecerá en Google'); continue; }
            $title = $p['seo']['title'] ?: $p['title'];
            $titles[$p['lang'] . '|' . mb_strtolower($title)][] = $where;
            if (mb_strlen($title) > 60) $add('notice', $where, 'Título SEO de más de 60 caracteres (' . mb_strlen($title) . ')');
            $desc = $p['seo']['description'];
            if ($desc === '') $add('warning', $where, 'Falta la meta descripción');
            elseif (mb_strlen($desc) < 50 || mb_strlen($desc) > 160) $add('notice', $where, 'Meta descripción fuera de 50–160 caracteres (' . mb_strlen($desc) . ')');
            $heroes = array_filter($p['blocks'], fn($b) => $b['type'] === 'hero');
            if (count($heroes) > 1) $add('warning', $where, 'Más de un bloque Portada: varios H1');
            // Imágenes enlazadas desde otras webs: posibles derechos de autor y dependencia de un servidor ajeno.
            $ext = [];
            $imgBlocks = array_values(array_filter($p['blocks'], fn($b) => !in_array($b['type'], ['video', 'audio'], true)));
            array_walk_recursive($imgBlocks, function ($v, $k) use (&$ext) {
                if (in_array($k, ['image', 'src', 'poster'], true) && is_string($v) && preg_match('#^(https?:)?//#i', $v)) $ext[] = $v;
            });
            if ($ext) $add('warning', $where, count($ext) . ' imagen(es) enlazadas desde otra web (' . parse_url($ext[0], PHP_URL_HOST) . '): usa imágenes propias o generadas por IA y súbelas con media_upload (derechos de autor)');
            foreach ($p['blocks'] as $i => $b) {
                $d = $b['data'];
                if ($b['type'] === 'hero' && $d['image'] !== '' && $d['image_alt'] === '') $add('warning', $where, "Bloque #$i: imagen de portada sin texto alternativo");
                if ($b['type'] === 'image' && $d['alt'] === '') $add('warning', $where, "Bloque #$i: imagen sin texto alternativo");
                if ($b['type'] === 'gallery') {
                    foreach ($d['images'] as $img) if ($img['alt'] === '') { $add('warning', $where, "Bloque #$i: imagen de galería sin texto alternativo"); break; }
                }
            }
        }
        // Enlaces internos de menús y botón que no llevan a nada (p. ej. /en/sobre-nosotros sin traducir).
        $orig = $this->lang;
        foreach ($this->langs() as $l) {
            $this->lang = $l;
            $st = $this->settings();
            $links = array_merge(array_column($st['menu'], 'url'), array_column($st['utility_menu'], 'url'), [$st['cta']['url']]);
            foreach (array_unique(array_filter($links, fn($u) => is_string($u) && str_starts_with($u, '/') && !str_starts_with($u, '//'))) as $u) {
                $path = (string) parse_url($u, PHP_URL_PATH);
                $hit = $this->resolvePath($path);
                if (!$hit || $hit['kind'] !== 'page' || $hit['slug'] === 'cookies' || redirect_find($this->id(), $path)) continue;
                $pg = $this->page($hit['slug'], $hit['lang']);
                if (!$pg || $pg['status'] !== 'published') {
                    $add('warning', 'Menú (' . $l . ')', "El enlace $u lleva a una página que no existe o no está publicada en «{$l}»: tradúcela/publícala o quítala del menú de ese idioma (site_update translations)");
                }
            }
        }
        $this->lang = $orig;
        if ($multi) {
            // Páginas sin traducir: aviso por idioma (las IAs y Google premian la cobertura completa).
            $bySlug = [];
            foreach ($this->pages(true, true) as $m) $bySlug[$m['slug']][] = $m['lang'];
            foreach ($bySlug as $slug => $have) foreach (array_diff($this->langs(), $have) as $missing) {
                // Página que a propósito solo existe en algunos idiomas: con su 301 configurada no es un fallo.
                if (redirect_find($this->id(), $this->equivalentPath('page', $slug, $missing))) continue;
                $add('notice', "Página /$slug", "Sin versión en «{$missing}» (tradúcela o, si no tiene sentido en ese idioma, configura su redirección con page_languages)");
            }
        }
        foreach ($this->posts(['public' => true, 'limit' => 100, 'all_langs' => true]) as $p) {
            $where = 'Entrada ' . $p['public_path'];
            if ($p['seo']['noindex']) continue;
            $title = $p['seo']['title'] ?: $p['title'];
            $titles[$p['lang'] . '|' . mb_strtolower($title)][] = $where;
            if (mb_strlen($title) > 60) $add('notice', $where, 'Título de más de 60 caracteres (' . mb_strlen($title) . ')');
            if (($p['seo']['description'] ?: $p['excerpt']) === '') $add('warning', $where, 'Sin extracto ni meta descripción');
            if (mb_strlen(strip_tags(md($p['content']))) < 300) $add('notice', $where, 'Contenido corto (menos de 300 caracteres)');
            if ($p['cover_image'] === '') $add('notice', $where, 'Sin imagen de portada (se usa al compartir en redes)');
        }
        foreach ($titles as $t => $where) {
            $tt = substr($t, strpos($t, '|') + 1);
            if (count($where) > 1) $add('warning', implode(', ', $where), "Título duplicado: «{$tt}»");
        }
        $count = fn($sev) => count(array_filter($issues, fn($i) => $i['severity'] === $sev));
        $summary = ['errors' => $count('error'), 'warnings' => $count('warning'), 'notices' => $count('notice')];
        $score = max(0, 100 - $summary['errors'] * 20 - $summary['warnings'] * 6 - $summary['notices'] * 2);
        return ['score' => $score, 'summary' => $summary, 'issues' => $issues];
    }

    public function stats(): array
    {
        $c = fn(string $sql) => (int) val($sql, [$this->id()]);
        return [
            'pages' => $c('SELECT COUNT(*) FROM pages WHERE site_id = ?'),
            'posts_published' => $c("SELECT COUNT(*) FROM posts WHERE site_id = ? AND status = 'published'"),
            'posts_draft' => $c("SELECT COUNT(*) FROM posts WHERE site_id = ? AND status = 'draft'"),
            'messages_unread' => $c('SELECT COUNT(*) FROM messages WHERE site_id = ? AND is_read = 0'),
            'media' => $c('SELECT COUNT(*) FROM media WHERE site_id = ?'),
        ];
    }

    public function activity(int $limit = 50): array
    {
        return rows('SELECT * FROM audit_log WHERE site_id = ? ORDER BY id DESC LIMIT ' . max(1, min(500, $limit)), [$this->id()]);
    }
}

// ---------------------------------------------------------------- optimización de imágenes

const MEDIA_MAX_BYTES_RAW = 25 * 1048576; // fotos sin optimizar (se reducen al subirlas)
const MEDIA_MAX_SIDE = 2000;          // px: más no se ve mejor en ninguna pantalla y pesa el triple
const MEDIA_HEAVY_BYTES = 600 * 1024; // site_check avisa de imágenes por encima de esto

/**
 * Reduce una foto jpg/png/webp: lado mayor a MEDIA_MAX_SIDE, orientación EXIF aplicada y WebP (con transparencia) si
 * queda más ligera. Devuelve null si no hace falta, no hay GD o la imagen es enorme en píxeles (no cabe en memoria).
 */
function media_optimize(string $tmp, string $ext): ?array
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp') || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) return null;
    $info = @getimagesize($tmp);
    if (!$info || $info[0] * $info[1] > 40_000_000) return null;
    [$w, $h] = $info;
    $before = (int) filesize($tmp);
    $orient = $ext !== 'png' && function_exists('exif_read_data') ? (int) (@exif_read_data($tmp)['Orientation'] ?? 1) : 1;
    // Pequeña, sin girar y de tamaño razonable: se deja tal cual (logos e iconos siguen en PNG, que vale en todas partes)
    if (($before < 150 * 1024 || $ext === 'webp') && max($w, $h) <= MEDIA_MAX_SIDE && $orient === 1) return null;
    $src = match ($ext) { 'png' => @imagecreatefrompng($tmp), 'webp' => @imagecreatefromwebp($tmp), default => @imagecreatefromjpeg($tmp) };
    if (!$src) return null;
    $rot = [3 => 180, 6 => -90, 8 => 90][$orient] ?? 0;
    if ($rot) { $r = imagerotate($src, $rot, 0); if ($r) { imagedestroy($src); $src = $r; [$w, $h] = [imagesx($src), imagesy($src)]; } }
    $scale = min(1, MEDIA_MAX_SIDE / max($w, $h));
    $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    imagedestroy($src);
    $out = tempnam(sys_get_temp_dir(), 'uwo');
    $ok = imagewebp($dst, $out, 82);
    imagedestroy($dst);
    clearstatcache(true, $out);
    $after = $ok ? (int) filesize($out) : 0;
    // Solo si mejora de verdad (o había que girarla / reducirla)
    if (!$ok || $after <= 0 || ($after > $before * 0.9 && $scale === 1.0 && !$rot)) { @unlink($out); return null; }
    $kb = fn($b) => round($b / 1024) . ' KB';
    return ['file' => $out, 'ext' => 'webp', 'size' => $after,
        'note' => "Optimizada: {$info[0]}×{$info[1]} " . strtoupper($ext) . ' ' . $kb($before) . " → {$nw}×{$nh} WEBP " . $kb($after) . ' (keep_original=true para subirla tal cual)'];
}

// ---------------------------------------------------------------- marca de IA (AI Act art. 50.2)
// GD reescribe la imagen sin NINGÚN metadato: bien para el GPS del móvil, pero también se perdía la marca de IA legible
// por máquina (18-oct: las portadas del blog de una web real salían sin ella). Se vuelve a escribir SOLO el XMP de IA.

/** ¿Trae la imagen la marca de IA? (IPTC DigitalSourceType o el EXIF que pone ADRI). */
function media_ai_detect(string $file): bool
{
    $head = (string) @file_get_contents($file, false, null, 0, 512 * 1024);
    return (bool) preg_match('/trainedAlgorithmicMedia|AI-generated/', $head);
}

function media_ai_xmp(): string
{
    return '<?xpacket begin="' . "\u{FEFF}" . '" id="W5M0MpCehiHzreSzNTczkc9d"?><x:xmpmeta xmlns:x="adobe:ns:meta/">'
        . '<rdf:RDF xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"><rdf:Description rdf:about=""'
        . ' xmlns:Iptc4xmpExt="http://iptc.org/std/Iptc4xmpExt/2008-02-29/"'
        . ' Iptc4xmpExt:DigitalSourceType="http://cv.iptc.org/newscodes/digitalsourcetype/trainedAlgorithmicMedia"/>'
        . '</rdf:RDF></x:xmpmeta><?xpacket end="w"?>';
}

/** Añade el XMP de IA a un .webp o .jpg recién escrito por GD. Devuelve false si no pudo (la imagen queda igual). */
function media_add_ai_xmp(string $file): bool
{
    $b = (string) @file_get_contents($file);
    $xmp = media_ai_xmp();
    if (str_starts_with($b, "\xFF\xD8")) {
        // JPEG: segmento APP1 de XMP justo después del SOI.
        $data = "http://ns.adobe.com/xap/1.0/\0" . $xmp;
        $out = "\xFF\xD8\xFF\xE1" . pack('n', strlen($data) + 2) . $data . substr($b, 2);
    } elseif (substr($b, 0, 4) === 'RIFF' && substr($b, 8, 4) === 'WEBP') {
        // WebP: formato extendido = VP8X (con el bit de XMP) + los chunks de imagen + chunk «XMP ».
        $chunks = substr($b, 12);
        $tag = substr($chunks, 0, 4);
        if ($tag === 'VP8X') {
            $chunks[8] = chr(ord($chunks[8]) | 0x04);
        } else {
            $info = @getimagesize($file);
            if (!$info) return false;
            $alpha = $tag === 'VP8L' && ((unpack('V', substr($chunks, 9, 4))[1] >> 28) & 1);
            $w = $info[0] - 1; $h = $info[1] - 1;
            $vp8x = 'VP8X' . pack('V', 10) . chr(0x04 | ($alpha ? 0x10 : 0)) . "\0\0\0"
                . chr($w & 0xFF) . chr(($w >> 8) & 0xFF) . chr(($w >> 16) & 0xFF)
                . chr($h & 0xFF) . chr(($h >> 8) & 0xFF) . chr(($h >> 16) & 0xFF);
            $chunks = $vp8x . $chunks;
        }
        $chunks .= 'XMP ' . pack('V', strlen($xmp)) . $xmp . (strlen($xmp) % 2 ? "\0" : '');
        $out = 'RIFF' . pack('V', strlen($chunks) + 4) . 'WEBP' . $chunks;
    } else {
        return false;
    }
    return @file_put_contents($file, $out, LOCK_EX) !== false;
}

const MEDIA_VARIANT_WIDTHS = [480, 960]; // px de ancho de las variantes para móvil (srcset)

/** Rutas posibles de las variantes de una imagen: foto-abc.webp → foto-abc-480w.webp y foto-abc-960w.webp. */
function media_variant_paths(string $file): array
{
    $stem = preg_replace('/\.[A-Za-z0-9]+$/', '', $file);
    $out = [];
    foreach (MEDIA_VARIANT_WIDTHS as $w) $out[$w] = "$stem-{$w}w.webp";
    return $out;
}

/** Variantes que existen en disco para esa imagen (para borrarlas). */
function media_variant_files(string $file): array
{
    return array_values(array_filter(media_variant_paths($file), 'is_file'));
}

/**
 * Genera las variantes WebP de 480 y 960 px de ancho (solo las más estrechas que el original) junto al archivo.
 * Solo jpg/png/webp (ni gif, svg ni iconos). No hace nada si la ruta lleva «..», no hay GD, la foto tiene orientación
 * EXIF sin aplicar o es enorme en píxeles. Devuelve las variantes creadas (ancho => ruta); las que ya existen no se rehacen.
 */
/**
 * Genera, de pocas en pocas y tras responder, las variantes que les faltan a las fotos subidas antes (sin tener que
 * ejecutar bin/uw media:variants en el servidor). Cuando no queda ninguna lo apunta y deja de mirar.
 */
function media_variants_tick(): void
{
    try {
        if (val("SELECT value FROM meta WHERE key = 'media_variants_done'") === '1') return;
        if (PHP_SAPI !== 'cli') {
            if (!function_exists('fastcgi_finish_request')) return;
            fastcgi_finish_request();
        }
        $last = (int) val("SELECT value FROM meta WHERE key = 'media_variants_last'");
        $batch = rows("SELECT id, path, ai_generated FROM media WHERE kind = 'image' AND id > ? ORDER BY id LIMIT 5", [$last]);
        if (!$batch) { q("INSERT OR REPLACE INTO meta (key, value) VALUES ('media_variants_done', '1')"); return; }
        foreach ($batch as $m) {
            if (!str_contains($m['path'], '..')) media_variants(UW_PUBLIC . '/media/' . $m['path'], (bool) $m['ai_generated']);
            q("INSERT OR REPLACE INTO meta (key, value) VALUES ('media_variants_last', ?)", [(string) $m['id']]);
        }
    } catch (Throwable $e) {
        error_log('[universal-web] media_variants_tick: ' . $e->getMessage());
    }
}

function media_variants(string $file, bool $ai = false): array
{
    if (str_contains($file, "\0") || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $file) || !is_file($file)) return [];
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true) || preg_match('/-\d+w\.webp$/', $file)) return [];
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) return [];
    $info = @getimagesize($file);
    if (!$info || $info[0] * $info[1] > 40_000_000) return [];
    [$w, $h] = $info;
    if (in_array($ext, ['jpg', 'jpeg'], true) && function_exists('exif_read_data') && (int) (@exif_read_data($file)['Orientation'] ?? 1) > 1) return [];
    $todo = [];
    foreach (media_variant_paths($file) as $vw => $path) if ($vw < $w && !is_file($path)) $todo[$vw] = $path;
    if (!$todo) return [];
    $src = match ($ext) { 'png' => @imagecreatefrompng($file), 'webp' => @imagecreatefromwebp($file), default => @imagecreatefromjpeg($file) };
    if (!$src) return [];
    $made = [];
    foreach ($todo as $vw => $path) {
        $vh = max(1, (int) round($h * $vw / $w));
        $dst = imagecreatetruecolor($vw, $vh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $vw, $vh, $w, $h);
        $ok = @imagewebp($dst, $path, 80);
        imagedestroy($dst);
        if ($ok && ($ai || media_ai_detect($file))) media_add_ai_xmp($path); // la variante también va marcada
        if ($ok) { @chmod($path, 0644); $made[$vw] = $path; } else { @unlink($path); }
    }
    imagedestroy($src);
    return $made;
}

// ---------------------------------------------------------------- horario

const HOURS_DAYS = ['mo' => 'Monday', 'tu' => 'Tuesday', 'we' => 'Wednesday', 'th' => 'Thursday', 'fr' => 'Friday', 'sa' => 'Saturday', 'su' => 'Sunday'];
const HOURS_ALIASES = ['lu' => 'mo', 'ma' => 'tu', 'mi' => 'we', 'ju' => 'th', 'vi' => 'fr', 'sa' => 'sa', 'do' => 'su', 'mo' => 'mo', 'tu' => 'tu', 'we' => 'we', 'th' => 'th', 'fr' => 'fr', 'su' => 'su'];

/** «lu-vi», «sa,do», «mo-fr» → ['mo','tu','we','th','fr']. Lo que no entiende lo ignora. */
function hours_days(string $spec): array
{
    $order = array_keys(HOURS_DAYS);
    $out = [];
    // «lu - vi», «lu–vi» (guion largo de Word o de una IA) y «lunes a viernes» son rangos, no tres días sueltos.
    $spec = preg_replace(['/\s*[-\x{2010}-\x{2015}]\s*/u', '/\s+(?:a|al|to|bis|à)\s+/u'], '-', mb_strtolower(trim($spec)));
    foreach (preg_split('/[,;\s]+/', $spec) as $part) {
        if ($part === '') continue;
        $ends = array_map(fn($x) => HOURS_ALIASES[mb_substr(trim($x), 0, 2)] ?? null, explode('-', $part, 2));
        if (in_array(null, $ends, true)) continue;
        if (count($ends) === 1) { $out[] = $ends[0]; continue; }
        $a = array_search($ends[0], $order, true); $b = array_search($ends[1], $order, true);
        for ($i = $a; ; $i = ($i + 1) % 7) { $out[] = $order[$i]; if ($i === $b) break; }
    }
    return array_values(array_unique($out));
}

/** Texto legible de una fila («Lunes a viernes»), traducido al idioma de la página. */
function hours_label(array $row): string
{
    if (trim((string) $row['label']) !== '') return (string) $row['label'];
    $names = ['mo' => __('Lunes'), 'tu' => __('Martes'), 'we' => __('Miércoles'), 'th' => __('Jueves'), 'fr' => __('Viernes'), 'sa' => __('Sábado'), 'su' => __('Domingo')];
    $d = hours_days((string) $row['days']);
    if (!$d) return '';
    // En español, rumano, francés… los días van en minúscula dentro de la frase; en inglés o alemán, no.
    $low = fn(string $n) => in_array(substr(FrontLang::$code, 0, 2), ['es', 'ro', 'fr', 'it', 'pt', 'ca', 'gl', 'eu', 'pl', 'nl'], true) ? mb_strtolower($n) : $n;
    if (count($d) > 2) {
        $order = array_keys(HOURS_DAYS);
        $idx = array_map(fn($x) => array_search($x, $order, true), $d);
        $consecutive = true;
        for ($i = 1; $i < count($idx); $i++) if ($idx[$i] !== ($idx[$i - 1] + 1) % 7) $consecutive = false;
        if ($consecutive) return __('{a} a {b}', ['a' => $names[$d[0]], 'b' => $low($names[end($d)])]);
    }
    $list = array_map(fn($x) => $names[$x], $d);
    if (count($list) === 1) return $list[0];
    $last = $low(array_pop($list));
    return implode(', ', array_map(fn($n, $i) => $i ? $low($n) : $n, $list, array_keys($list))) . ' ' . __('y') . ' ' . $last;
}

/** «9», «9:30», «21h», «09:00:00», «9:00 PM» → «HH:MM». «24:00» = medianoche → «00:00» (antes salía 23:00). */
function hours_time(string $t): string
{
    if (!preg_match('/^(\d{1,2})(?:[:.h](\d{2}))?(?::\d{2})?\s*([ap])?\.?\s*m?\.?$/i', trim($t), $m) && !preg_match('/^(\d{1,2})h?$/i', trim($t), $m)) return '';
    $h = (int) $m[1];
    $ampm = strtolower($m[3] ?? '');
    if ($ampm !== '') { if ($h < 1 || $h > 12) return ''; $h = $h % 12 + ($ampm === 'p' ? 12 : 0); }
    if ($h === 24 && (int) ($m[2] ?? 0) === 0) $h = 0;
    return sprintf('%02d:%02d', min(23, $h), min(59, (int) ($m[2] ?? 0)));
}

function media_kind(string $mime): string
{
    return match (true) {
        str_starts_with($mime, 'image/') => 'image', str_starts_with($mime, 'video/') => 'video',
        str_starts_with($mime, 'audio/') => 'audio', $mime === 'application/pdf' => 'pdf', default => 'other',
    };
}

// ---------------------------------------------------------------- redirecciones 301

/** Ruta interna normalizada («/a/b», sin query ni barra final) o null si no vale. */
function redirect_norm_from(string $p): ?string
{
    $p = (string) preg_replace('/[?#].*$/', '', trim($p));
    if ($p === '' || $p[0] !== '/' || str_starts_with($p, '//') || str_contains($p, '..') || str_contains($p, '\\') || preg_match('/[\x00-\x20\x7f]/', $p)) return null;
    $p = rtrim($p, '/');
    return $p === '' ? null : $p;
}

/** Destino válido: ruta interna o URL https (esta solo si $external). Devuelve el destino limpio o null. */
function redirect_norm_to(string $to, bool $external): ?string
{
    $to = clean_url(trim($to));
    if ($to === '') return null;
    if ($to[0] === '/') return str_contains($to, '..') ? null : $to;
    return $external && preg_match('#^https://[^/]#i', $to) ? $to : null;
}

function redirect_find(int $siteId, string $path): ?array
{
    $path = rtrim($path, '/');
    return $path === '' ? null : row('SELECT * FROM redirects WHERE site_id = ? AND from_path = ?', [$siteId, $path]);
}

function redirect_list(int $siteId): array
{
    return rows('SELECT lang, from_path, to_path, hits, created_at FROM redirects WHERE site_id = ? ORDER BY from_path', [$siteId]);
}

/** Borra la redirección que sale de esta ruta (p. ej. cuando vuelve a existir una página ahí). */
function redirect_drop(int $siteId, string $from): bool
{
    return q('DELETE FROM redirects WHERE site_id = ? AND from_path = ?', [$siteId, rtrim($from, '/') ?: '/'])->rowCount() > 0;
}

/** Crea o cambia una redirección sin cadenas (A→B y luego B→C ⇒ A→C) ni bucles. */
function redirect_add(int $siteId, string $lang, string $from, string $to): ?array
{
    if ($from === $to) return null;
    q('UPDATE redirects SET to_path = ? WHERE site_id = ? AND to_path = ?', [$to, $siteId, $from]);
    q('DELETE FROM redirects WHERE site_id = ? AND from_path = to_path', [$siteId]);
    redirect_drop($siteId, $to);
    redirect_drop($siteId, $from);
    insert('redirects', ['site_id' => $siteId, 'lang' => $lang, 'from_path' => $from, 'to_path' => $to, 'created_at' => now()]);
    audit($siteId, 'redirect.add', $from, ['to' => $to]);
    return ['from' => $from, 'to' => $to];
}

/** ¿Hay una página o entrada publicada en esta ruta pública (con prefijo de idioma)? */
function redirect_page_exists(SiteService $svc, string $p): bool
{
    $hit = $svc->resolvePath($p);
    if (!$hit) return false;
    if ($hit['kind'] === 'blog') return rtrim($svc->blogPath($hit['lang']), '/') === rtrim($p, '/');
    $row = row('SELECT status, path FROM ' . ($hit['kind'] === 'post' ? 'posts' : 'pages') . ' WHERE site_id = ? AND lang = ? AND slug = ?', [$svc->id(), $hit['lang'], $hit['slug']]);
    if (!$row || $row['status'] !== 'published') return false;
    // Solo cuenta si ESTA es su dirección canónica: la automática de una página con ruta fija redirige, no la tapa.
    return rtrim($svc->itemPath($hit['kind'], $hit['slug'], $hit['lang'], (string) $row['path']), '/') === (rtrim($p, '/') ?: '/');
}

/** Alta manual (MCP o panel) con todas las validaciones. Devuelve ['from','to','warning'?]. */
function redirect_save_manual(SiteService $svc, string $from, string $to): array
{
    $f = redirect_norm_from($from) ?? throw new UwError('«from» debe ser una ruta interna como /servicio-antiguo (sin .., sin dominio)');
    $t = redirect_norm_to($to, true) ?? throw new UwError('«to» debe ser una ruta interna (/nueva) o una URL https://…');
    if ($f === rtrim($t, '/') || ($t[0] === '/' && $f === (rtrim((string) preg_replace('/[?#].*$/', '', $t), '/') ?: '/'))) throw new UwError('Origen y destino son iguales');
    if (redirect_page_exists($svc, $f)) throw new UwError("En $f hay una página publicada y taparía la redirección: cámbiale el slug (la 301 se crea sola) o despublícala antes");
    if (preg_match('#^/(sitemap\.xml|robots\.txt|feed\.xml|llms(-full)?\.txt|api/.*|admin.*)$#', $f)) throw new UwError("$f es una ruta del sistema");
    // Sin bucles: siguiendo las redirecciones desde el destino no se puede volver al origen (/a → /b?x y /b → /a?y)
    $seen = [];
    for ($hop = $t; $hop !== '' && $hop[0] === '/' && count($seen) < 20;) {
        $p = rtrim((string) preg_replace('/[?#].*$/', '', $hop), '/') ?: '/';
        if ($p === $f) throw new UwError("Eso crearía un bucle de redirecciones ($f → $t → … → $f)");
        if (isset($seen[$p])) break;
        $seen[$p] = true;
        $hop = (string) (redirect_find($svc->id(), $p)['to_path'] ?? '');
    }
    $lang = $svc->defaultLang();
    if (preg_match('#^/([a-z]{2}(?:-[a-z]{2})?)(/|$)#', $f, $m) && $m[1] !== $lang && in_array($m[1], $svc->langs(), true)) $lang = $m[1];
    $r = redirect_add($svc->id(), $lang, $f, $t) ?? throw new UwError('Origen y destino son iguales');
    if ($t[0] === '/' && !site_check_internal_exists($svc, $t)) $r['warning'] = "El destino $t no existe todavía: crea o publica esa página";
    return $r;
}

/**
 * Importa redirecciones de un texto «origen -> destino» (una por línea; también «origen destino», «origen,destino» o
 * «redir /origen /destino permanent» de Caddy). «#» empieza un comentario. Las rutas sin barra inicial se toman desde la
 * raíz (casos.html → /casos.html). Cada línea se valida por separado; con $dryRun no se guarda nada.
 * @return array{created: list<array>, errors: list<array{line:int, text:string, error:string}>, total:int}
 */
function redirect_import(SiteService $svc, string $text, bool $dryRun = false): array
{
    $created = []; $errors = []; $n = 0;
    $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
    if (count($lines) > 5000) throw new UwError('Demasiadas líneas (máximo 5000 redirecciones por importación)');
    foreach ($lines as $i => $raw) {
        $line = trim((string) preg_replace('/\s+#.*$|^#.*$/', '', $raw));
        if ($line === '') continue;
        $n++;
        $line = (string) preg_replace('/^redir\s+|\s+(permanent|301)$/i', '', $line);
        $parts = preg_split('/\s*(?:->|=>|→|,|;|\t|\s)\s*/u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if (count($parts) !== 2) { $errors[] = ['line' => $i + 1, 'text' => mb_substr($raw, 0, 200), 'error' => 'Formato: origen -> destino']; continue; }
        [$from, $to] = array_map(fn($x) => preg_match('#^(/|https?://)#i', $x) ? $x : '/' . $x, $parts);
        try {
            if ($dryRun) {
                $f = redirect_norm_from($from) ?? throw new UwError('«from» debe ser una ruta interna como /servicio-antiguo (sin .., sin dominio)');
                redirect_norm_to($to, true) ?? throw new UwError('«to» debe ser una ruta interna (/nueva) o una URL https://…');
                if (redirect_page_exists($svc, $f)) throw new UwError("En $f hay una página publicada y taparía la redirección");
                $created[] = ['from' => $f, 'to' => $to];
            } else {
                $created[] = redirect_save_manual($svc, $from, $to);
            }
        } catch (UwError $e) {
            $errors[] = ['line' => $i + 1, 'text' => mb_substr($raw, 0, 200), 'error' => $e->getMessage()];
        }
    }
    if (!$dryRun && $created) audit($svc->id(), 'redirect.import', count($created) . ' redirecciones', ['errors' => count($errors)]);
    return ['created' => $created, 'errors' => $errors, 'total' => $n, 'dry_run' => $dryRun];
}

/**
 * Idiomas de una página o entrada: en cuáles existe (estado y dirección) y, en los que no, su dirección equivalente y a
 * dónde redirige. $missing ({"ro": "/ro/otra.html", "it": ""}) fija o quita (vacío) la 301 de cada idioma que falta:
 * así una página que solo tiene sentido en algunos idiomas no deja enlaces rotos en los demás.
 */
function page_languages(SiteService $svc, string $kind, string $slug, ?array $missing = null): array
{
    $kind = $kind === 'post' ? 'post' : 'page';
    $tr = $svc->translations($kind === 'post' ? 'posts' : 'pages', $slug);
    if (!$tr) throw new UwError(($kind === 'post' ? 'No existe la entrada' : 'No existe la página') . " «{$slug}» en ningún idioma");
    $out = [];
    $applied = [];
    foreach ($svc->langs() as $l) {
        if (isset($tr[$l])) {
            $path = $svc->itemPath($kind, $slug, $l);
            $out[$l] = ['exists' => true, 'status' => $tr[$l], 'path' => $path, 'url' => $svc->url($path), 'markdown' => $svc->url($svc->mdPath($kind, $slug, $l))];
            if ($missing !== null && array_key_exists($l, $missing)) throw new UwError("La " . ($kind === 'post' ? 'entrada' : 'página') . " ya existe en «{$l}»: no se puede redirigir");
            continue;
        }
        $eq = $svc->equivalentPath($kind, $slug, $l);
        if ($missing !== null && array_key_exists($l, $missing)) {
            $to = trim((string) $missing[$l]);
            if ($to === '') { if (redirect_drop($svc->id(), $eq)) audit($svc->id(), 'redirect.delete', $eq); $applied[$l] = null; }
            else $applied[$l] = redirect_save_manual($svc, $eq, $to);
        }
        $r = redirect_find($svc->id(), $eq);
        $out[$l] = ['exists' => false, 'equivalent_path' => $eq, 'redirect_to' => $r['to_path'] ?? null,
            'note' => $r ? 'Redirige con 301; los enlaces de ese idioma van directos al destino' : 'Sin versión ni redirección: los enlaces de ese idioma llevan a la versión del idioma principal'];
    }
    if ($missing !== null) {
        foreach (array_keys($missing) as $l) if (!in_array($l, $svc->langs(), true)) throw new UwError("La web no tiene el idioma «{$l}»");
    }
    return ['kind' => $kind, 'slug' => $slug, 'languages' => $out] + ($applied ? ['changed' => $applied] : []);
}

/** Si la ruta pedida tiene redirección, responde 301 y devuelve true. */
function redirect_serve(SiteService $svc, string $path): bool
{
    $r = redirect_find($svc->id(), $path);
    if (!$r) return false;
    $to = clean_url($r['to_path']);
    if ($to === '') return false;
    $qs = (string) ($_SERVER['QUERY_STRING'] ?? '');
    if ($to[0] === '/' && $qs !== '' && !str_contains($to, '?')) $to .= '?' . $qs;
    if (!Out::$capture) q('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]);
    out_header('Location: ' . $to, true, 301);
    return true;
}
