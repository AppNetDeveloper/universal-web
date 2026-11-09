<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Instalador: comprobación de requisitos, instalación (web y CLI) y contenido de demostración.
 */

// ---------------------------------------------------------------- requisitos

/** @return list<array{0:string,1:bool,2:string,3:bool}> [etiqueta, ok, detalle, obligatorio] */
function install_requirements(): array
{
    $out = [];
    $add = function (string $label, bool $ok, string $detail, bool $required = true) use (&$out) {
        $out[] = [$label, $ok, $detail, $required];
    };

    $add('PHP 8.1 o superior', PHP_VERSION_ID >= 80100, 'Versión actual: ' . PHP_VERSION);
    $add('Extensión pdo_sqlite', extension_loaded('pdo_sqlite'), extension_loaded('pdo_sqlite') ? 'Disponible' : 'Falta: es la base de datos de Universal Web');
    $add('Extensión mbstring', extension_loaded('mbstring'), extension_loaded('mbstring') ? 'Disponible' : 'Falta: necesaria para el texto en UTF-8');
    $add('Extensión json', extension_loaded('json'), extension_loaded('json') ? 'Disponible' : 'Falta');
    $add('Extensión fileinfo', extension_loaded('fileinfo'), extension_loaded('fileinfo') ? 'Disponible' : 'Recomendada para validar archivos subidos', false);
    $add('Extensión gd', extension_loaded('gd'), extension_loaded('gd') ? 'Disponible (imágenes de demostración)' : 'Opcional: sin ella la demo no incluye imágenes', false);

    // json_each de SQLite (filtro por etiquetas del blog)
    $jsonOk = false;
    $jsonDetail = 'No se pudo comprobar';
    if (extension_loaded('pdo_sqlite')) {
        try {
            $pdo = new PDO('sqlite::memory:');
            $jsonOk = (bool) $pdo->query("SELECT COUNT(*) FROM json_each('[1,2]')")->fetchColumn();
            $jsonDetail = $jsonOk ? 'SQLite ' . $pdo->query('select sqlite_version()')->fetchColumn() : 'Esta versión de SQLite no incluye JSON1';
        } catch (Throwable $ex) {
            $jsonDetail = 'Tu SQLite no incluye json_each (JSON1)';
        }
    }
    $add('SQLite con soporte JSON (json_each)', $jsonOk, $jsonDetail);

    // Carpetas escribibles
    if (!is_dir(UW_DATA)) @mkdir(UW_DATA, 0775, true);
    $dataOk = is_dir(UW_DATA) && is_writable(UW_DATA);
    $add('Carpeta de datos escribible', $dataOk, $dataOk ? UW_DATA : 'No se puede escribir en ' . UW_DATA . ' (da permisos 775 a esa carpeta)');
    $media = UW_PUBLIC . '/media';
    if (!is_dir($media)) @mkdir($media, 0775, true);
    $mediaOk = is_dir($media) && is_writable($media);
    $add('Carpeta public/media escribible', $mediaOk, $mediaOk ? 'Correcto' : 'No se puede escribir en public/media (permisos 775)');

    return $out;
}

function install_requirements_ok(array $checks): bool
{
    foreach ($checks as $c) if ($c[3] && !$c[1]) return false;
    return true;
}

// ---------------------------------------------------------------- instalación

function install_write_atomic(string $file, string $content): void
{
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, $content) === false) throw new UwError('No se puede escribir en ' . dirname($file));
    if (!rename($tmp, $file)) {
        @unlink($tmp);
        throw new UwError('No se pudo guardar ' . basename($file));
    }
}

/**
 * Instalación completa. Entrada: site_name, domain, admin_name, admin_email, admin_password, timezone, demo.
 * @return array{site:array,user_id:int}
 */
function uw_install(array $in): array
{
    if (is_installed()) throw new UwError('Universal Web ya está instalado.');
    if (!install_requirements_ok(install_requirements())) throw new UwError('Tu servidor no cumple los requisitos mínimos (revisa la lista).');

    $siteName = trim((string) ($in['site_name'] ?? ''));
    $adminName = trim((string) ($in['admin_name'] ?? ''));
    $email = strtolower(trim((string) ($in['admin_email'] ?? '')));
    $password = (string) ($in['admin_password'] ?? '');
    $tz = trim((string) ($in['timezone'] ?? '')) ?: 'Europe/Madrid';
    $demo = !empty($in['demo']);
    $domains = normalize_domains((string) (($in['domain'] ?? '') !== '' ? $in['domain'] : request_host()));

    if ($siteName === '') throw new UwError('Escribe el nombre de la web.');
    if (mb_strlen($siteName) > 100) throw new UwError('El nombre de la web es demasiado largo.');
    if (!$domains) throw new UwError('Escribe el dominio principal.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new UwError('El email del administrador no es válido.');
    if (mb_strlen($password) < 8) throw new UwError('La contraseña debe tener al menos 8 caracteres.');
    if (!in_array($tz, timezone_identifiers_list(), true)) throw new UwError('Zona horaria no válida.');
    $baseDomain = preg_replace('/^www\./', '', $domains[0]);

    if (!is_dir(UW_DATA) && !mkdir(UW_DATA, 0775, true) && !is_dir(UW_DATA)) throw new UwError('No se puede crear la carpeta de datos: ' . UW_DATA);
    // Cerrojo: dos instalaciones simultáneas no pueden pisarse.
    $lock = fopen(UW_DATA . '/.install.lock', 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new UwError('Ya hay una instalación en curso.');
    // Nunca instalar sobre una base de datos existente (p. ej. si falta config.php tras una restauración parcial):
    // el instalador crearía otro superadmin dentro de una instalación viva.
    if (is_file(UW_DATA . '/universal.sqlite') && filesize(UW_DATA . '/universal.sqlite') > 0) {
        throw new UwError('Ya existe una base de datos en ' . UW_DATA . '. Si es una restauración, recupera también data/config.php; el instalador no la sobrescribe.');
    }
    $htaccess = "# Universal Web: esta carpeta nunca debe servirse por HTTP\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
    if (!is_file(UW_DATA . '/.htaccess')) install_write_atomic(UW_DATA . '/.htaccess', $htaccess);
    if (!is_file(UW_DATA . '/index.html')) install_write_atomic(UW_DATA . '/index.html', '');

    date_default_timezone_set($tz);
    try {
        migrate();
        Actor::set('system', null, 'instalador');
        $main = site_create($siteName, '', $domains, 'default', [
            'tagline' => $demo ? 'Una web, un panel, un MCP' : '',
            'seo' => ['description' => ''],
        ]);
        $userId = user_create($email, $adminName ?: 'Administrador', $password, true);
        $svc = new SiteService($main);
        if ($demo) {
            seed_demo($svc, $baseDomain);
        } else {
            seed_minimal($svc);
        }
        $main = site_by_id((int) $main['id']);
        foreach (['/universal.sqlite' => 0600] as $f => $mode) @chmod(UW_DATA . $f, $mode);
    } catch (Throwable $ex) {
        foreach (['', '-wal', '-shm'] as $suffix) @unlink(UW_DATA . '/universal.sqlite' . $suffix);
        throw $ex;
    }

    // La configuración se escribe la última: is_installed() depende de este archivo.
    $cfg = [
        'secret' => bin2hex(random_bytes(32)),
        'timezone' => $tz,
        'default_site_id' => (int) $main['id'],
        'debug' => false,
        'installed_at' => date('c'),
        'version' => UW_VERSION,
    ];
    install_write_atomic(UW_DATA . '/config.php', "<?php\nreturn " . var_export($cfg, true) . ";\n");
    @chmod(UW_DATA . '/config.php', 0600); // contiene la clave que cifra los secretos
    return ['site' => $main, 'user_id' => $userId];
}

function seed_minimal(SiteService $svc): void
{
    $name = (string) $svc->site['name'];
    $svc->updateSettings([
        'menu' => [['label' => 'Inicio', 'url' => '/'], ['label' => 'Blog', 'url' => '/blog'], ['label' => 'Contacto', 'url' => '/contacto']],
        'seo' => ['description' => "Web oficial de $name. Descubre quiénes somos, lee nuestro blog y ponte en contacto con nosotros."],
        'footer_text' => '',
    ]);
    $svc->savePage([
        'slug' => 'index', 'title' => $name, 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'title' => "Bienvenido a $name",
                'subtitle' => 'Esta es tu página de inicio. Edítala desde el panel de administración o pídele a tu IA que la complete por ti.',
                'cta_text' => 'Contacto', 'cta_link' => '/contacto',
            ]],
        ],
        'seo' => ['title' => $name, 'description' => "Web oficial de $name. Descubre quiénes somos, lee nuestro blog y ponte en contacto con nosotros."],
    ]);
    $svc->savePage([
        'slug' => 'contacto', 'title' => 'Contacto', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => ['title' => 'Contacto', 'subtitle' => 'Escríbenos y te responderemos lo antes posible.']],
            ['type' => 'contact_form', 'data' => ['title' => 'Envíanos un mensaje', 'text' => 'Rellena el formulario y recibirás respuesta en tu correo.']],
        ],
        'seo' => ['title' => "Contacto | $name", 'description' => "Contacta con $name: escríbenos desde el formulario y te responderemos lo antes posible."],
    ]);
}

// ---------------------------------------------------------------- web

function install_same_origin(): bool
{
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
        $v = $_SERVER[$h] ?? '';
        if ($v === '' || $v === 'null') continue;
        $p = parse_url($v);
        $o = strtolower((string) ($p['host'] ?? '')) . (isset($p['port']) ? ':' . $p['port'] : '');
        return $o === $host;
    }
    return true;
}

// ---------------------------------------------------------------- llave de deploy/instalar.php

/**
 * Si el instalador de un fichero (deploy/instalar.php) ha preparado la instalación, deja data/.install-key con una llave
 * aleatoria: hasta que el dueño abra el enlace /install?k=… (que le da el propio instalador), nadie más puede usar el
 * asistente. Cierra la carrera típica «descomprimido y /install abierto para cualquiera». Sin ese fichero (VPS, CLI,
 * zip subido a mano) no hace nada: comportamiento de siempre.
 */
function install_key_file(): string
{
    return UW_DATA . '/.install-key';
}

function install_key_gate(): bool
{
    $file = install_key_file();
    if (!is_file($file)) return true;
    $key = trim((string) @file_get_contents($file));
    if (strlen($key) < 32) return false; // llave ilegible o corrupta: cerrado (bórrala a mano para usar /install sin ella)
    $given = (string) ($_GET['k'] ?? '');
    if ($given !== '' && hash_equals($key, $given)) {
        // Llave en cookie y a una URL limpia: así el selector de idioma y los formularios no la arrastran.
        setcookie('uw_install_key', $key, ['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => is_https()]);
        redirect('/install');
    }
    return hash_equals($key, (string) ($_COOKIE['uw_install_key'] ?? ''));
}

// ---------------------------------------------------------------- «Conecta ADRI» (último paso)

function install_connect_file(): string
{
    return UW_DATA . '/.connect-adri.json';
}

/** Deja preparado un permiso de UN solo uso (30 min) para crear el token MCP; devuelve el código para el formulario. */
function install_connect_prepare(int $userId, int $siteId): string
{
    $nonce = bin2hex(random_bytes(24));
    install_write_atomic(install_connect_file(), json_encode(['hash' => hash('sha256', $nonce), 'user' => $userId, 'site' => $siteId, 'exp' => time() + 1800]));
    @chmod(install_connect_file(), 0600);
    return $nonce;
}

/** POST /install/conectar-adri: crea el token (lectura, escritura y publicación) y lo muestra una sola vez. */
function install_connect_handle(string $method): void
{
    header('Cache-Control: no-store');
    if ($method !== 'POST') redirect('/admin');
    $file = install_connect_file();
    $d = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $nonce = (string) ($_POST['nonce'] ?? '');
    $errors = [];
    $token = null;
    if (!install_same_origin()) {
        $errors[] = 'La petición no procede de esta misma web. Recarga la página e inténtalo de nuevo.';
    } elseif (!is_array($d) || (int) ($d['exp'] ?? 0) < time() || !hash_equals((string) ($d['hash'] ?? ''), hash('sha256', $nonce))) {
        $errors[] = 'Este paso ya no está disponible (solo se puede hacer justo al terminar la instalación). Crea el token desde el panel: Tokens de IA.';
    } else {
        @unlink($file); // un solo uso
        try {
            Actor::set('system', null, 'instalador');
            $token = token_create((int) $d['site'], 'ADRI', ['read', 'write', 'publish'], (int) $d['user']);
        } catch (Throwable $ex) {
            error_log('[universal-web] conectar-adri: ' . $ex);
            $errors[] = 'No se pudo crear el token: ' . $ex->getMessage();
        }
    }
    $done = null; $values = ['admin_email' => '']; $base = (is_https() ? 'https://' : 'http://') . request_host() . request_port_suffix();
    $mcpUrl = $base . '/mcp';
    $checks = []; $ok = true; $timezones = [];
    header('Content-Type: text/html; charset=utf-8');
    include UW_ROOT . '/app/views/install.php';
}

function install_handle(string $path, string $method): void
{
    if ($path !== '/install') redirect('/install');
    if (!install_key_gate()) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        exit('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Universal Web</title>'
            . '<body style="font:16px/1.5 system-ui,sans-serif;max-width:34rem;margin:15vh auto;padding:0 1rem"><h1>Instalación protegida</h1>'
            . '<p>Esta instalación solo la puede continuar quien subió el instalador. Abre el enlace que te mostró <code>instalar.php</code> '
            . '(termina en <code>/install?k=…</code>).</p></body>');
    }

    $values = [
        'site_name' => '', 'domain' => request_host(), 'admin_name' => '', 'admin_email' => '',
        'timezone' => 'Europe/Madrid', 'demo' => true,
    ];
    $errors = [];
    $done = null;
    $token = null;
    $mcpUrl = '';

    if ($method === 'POST') {
        foreach (['site_name', 'domain', 'admin_name', 'admin_email', 'timezone'] as $k) $values[$k] = trim((string) ($_POST[$k] ?? ''));
        $values['demo'] = !empty($_POST['demo']);
        if (!install_same_origin()) {
            $errors[] = 'La petición no procede de esta misma web. Recarga la página e inténtalo de nuevo.';
        } else {
            try {
                $done = uw_install([
                    'site_name' => $values['site_name'], 'domain' => $values['domain'], 'admin_name' => $values['admin_name'],
                    'admin_email' => $values['admin_email'], 'admin_password' => (string) ($_POST['admin_password'] ?? ''),
                    'timezone' => $values['timezone'], 'demo' => $values['demo'],
                ]);
                @unlink(install_key_file()); // la llave de instalar.php ya cumplió
                $done['connect_nonce'] = install_connect_prepare((int) $done['user_id'], (int) $done['site']['id']);
            } catch (UwError $ex) {
                $errors[] = $ex->getMessage();
            } catch (Throwable $ex) {
                error_log('[universal-web] install: ' . $ex);
                $errors[] = 'Error inesperado durante la instalación: ' . $ex->getMessage();
            }
        }
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $checks = install_requirements();
    $ok = install_requirements_ok($checks);
    $base = (is_https() ? 'https://' : 'http://') . request_host() . request_port_suffix();
    $mcpUrl = $base . '/mcp';
    $timezones = [
        'Europe/Madrid' => 'España peninsular (Europe/Madrid)', 'Atlantic/Canary' => 'Canarias (Atlantic/Canary)',
        'Europe/Bucharest' => 'Rumanía (Europe/Bucharest)', 'Europe/London' => 'Londres (Europe/London)',
        'Europe/Paris' => 'París (Europe/Paris)', 'America/Mexico_City' => 'Ciudad de México (America/Mexico_City)',
        'America/Bogota' => 'Bogotá (America/Bogota)', 'America/Lima' => 'Lima (America/Lima)',
        'America/Santiago' => 'Santiago (America/Santiago)',
        'America/Argentina/Buenos_Aires' => 'Buenos Aires (America/Argentina/Buenos_Aires)',
        'America/New_York' => 'Nueva York (America/New_York)', 'UTC' => 'UTC',
    ];
    if (!isset($timezones[$values['timezone']])) $timezones[$values['timezone']] = $values['timezone'];
    include UW_ROOT . '/app/views/install.php';
}

// ---------------------------------------------------------------- demo

/** Genera una imagen degradada 1200x630 con formas suaves. Devuelve la ruta temporal o null. */
function demo_image(array $from, array $to, int $seed): ?string
{
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagepng')) return null;
    $w = 1200;
    $h = 630;
    $img = imagecreatetruecolor($w, $h);
    if (!$img) return null;
    for ($y = 0; $y < $h; $y++) {
        $t = $y / ($h - 1);
        $c = imagecolorallocate($img, (int) ($from[0] + ($to[0] - $from[0]) * $t), (int) ($from[1] + ($to[1] - $from[1]) * $t), (int) ($from[2] + ($to[2] - $from[2]) * $t));
        imageline($img, 0, $y, $w, $y, $c);
    }
    imagealphablending($img, true);
    mt_srand($seed);
    for ($i = 0; $i < 7; $i++) {
        $col = imagecolorallocatealpha($img, 255, 255, 255, mt_rand(100, 120));
        $r = mt_rand(80, 340);
        imagefilledellipse($img, mt_rand(0, $w), mt_rand(0, $h), $r * 2, $r * 2, $col);
    }
    $col = imagecolorallocatealpha($img, 255, 255, 255, 90);
    imagefilledpolygon($img, [0, $h, 380, $h, 0, $h - 240], $col);
    imagefilledrectangle($img, 80, 80, 90, 190, imagecolorallocatealpha($img, 255, 255, 255, 60));
    $tmp = tempnam(sys_get_temp_dir(), 'uwimg');
    $ok = $tmp !== false && imagepng($img, $tmp, 6);
    imagedestroy($img);
    return $ok ? $tmp : null;
}

/** Guarda una imagen de demostración y devuelve su URL ('' si no hay GD). */
function demo_media(SiteService $svc, string $file, string $alt, array $from, array $to, int $seed): string
{
    try {
        $tmp = demo_image($from, $to, $seed);
        if ($tmp === null) return '';
        return $svc->storeMedia($tmp, $file, $alt, false)['url'];
    } catch (Throwable $ex) {
        return '';
    }
}

function demo_date(int $daysAgo, string $time = '10:00:00'): string
{
    return date('Y-m-d', strtotime("-$daysAgo days")) . ' ' . $time;
}

function seed_demo(SiteService $main, string $baseDomain): void
{
    $name = (string) $main->site['name'];
    $author = 'Equipo Universal Web';

    // ---------------- imágenes
    $cover1 = demo_media($main, 'portada-bienvenida.png', 'Degradado índigo con formas luminosas', [79, 70, 229], [14, 165, 233], 11);
    $cover2 = demo_media($main, 'portada-mcp.png', 'Degradado violeta y rosa con círculos suaves', [124, 58, 237], [236, 72, 153], 22);
    $cover3 = demo_media($main, 'portada-contrato-json.png', 'Degradado verde azulado con formas geométricas', [13, 148, 136], [37, 99, 235], 33);
    $ogHome = demo_media($main, 'og-universal-web.png', 'Imagen para compartir Universal Web en redes', [67, 56, 202], [168, 85, 247], 44);

    // ---------------- ajustes
    $main->updateSettings([
        'tagline' => 'Una web, un panel, un MCP',
        'accent' => '#4f46e5',
        'menu' => [
            ['label' => 'Inicio', 'url' => '/'], ['label' => 'Cómo funciona', 'url' => '/como-funciona'],
            ['label' => 'Blog', 'url' => '/blog'], ['label' => 'Nosotros', 'url' => '/nosotros'], ['label' => 'Contacto', 'url' => '/contacto'],
        ],
        'contact' => ['email' => 'demo@universal-web.local'],
        'social' => ['github' => 'https://github.com/universal-web'],
        'seo' => [
            'description' => 'CMS ligero y multi-web en PHP: cada web se publica como JSON, con panel sencillo y acceso MCP para que una IA gestione tu SEO, páginas y blog.',
            'image' => $ogHome,
        ],
        'footer_text' => 'Simple, rápido y listo para la IA.',
    ]);

    // ---------------- páginas
    $mcpCmd = 'claude mcp add --transport http universal-web https://tu-dominio/mcp --header "Authorization: Bearer uw_TU_TOKEN"';

    $main->savePage([
        'slug' => 'index', 'title' => 'Tu web, gestionada por IA', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'title' => 'Tu web, gestionada por IA',
                'subtitle' => 'Un CMS ligero y multi-web: cada web se publica como JSON, con un panel simple y acceso MCP para que Claude u otra IA gestione tu SEO, tus páginas y tu blog.',
                'cta_text' => 'Cómo funciona', 'cta_link' => '/como-funciona',
                'cta2_text' => 'Ver el JSON', 'cta2_link' => '/api/site.json',
            ]],
            ['type' => 'stats', 'data' => ['items' => [
                ['value' => '1', 'label' => 'back para todas tus webs'],
                ['value' => '∞', 'label' => 'fronts posibles'],
                ['value' => '0', 'label' => 'dependencias'],
                ['value' => '24', 'label' => 'herramientas MCP'],
            ]]],
            ['type' => 'features', 'data' => [
                'title' => 'Todo lo que necesitas, nada de lo que sobra',
                'subtitle' => 'Pensado para que una persona o una IA publiquen contenido de calidad sin pelearse con plugins.',
                'items' => [
                    ['icon' => '🌐', 'title' => 'Multi-web por dominio', 'text' => 'Un solo back sirve a todas tus webs. Apunta un dominio y listo: cada web tiene su contenido, su tema y sus usuarios.'],
                    ['icon' => '🤖', 'title' => 'MCP nativo', 'text' => 'Conecta Claude con tokens por web y permisos de lectura, escritura y publicación. Todo queda en el registro de actividad.'],
                    ['icon' => '🔍', 'title' => 'SEO automático', 'text' => 'Sitemap, robots.txt, Open Graph, JSON-LD y una auditoría que te dice qué mejorar antes de que lo haga Google.'],
                    ['icon' => '🧩', 'title' => 'Front intercambiable', 'text' => 'Usa los temas incluidos o crea tu propio front: la API JSON entrega cada página y cada entrada lista para pintar.'],
                    ['icon' => '📦', 'title' => 'Funciona en hosting compartido', 'text' => 'PHP 8.1 y SQLite. Sin Docker, sin Composer, sin terminal: sube el zip, abre /install y ya está.'],
                    ['icon' => '🖥️', 'title' => 'Panel simple', 'text' => 'Edita páginas por bloques, escribe en Markdown, sube imágenes y lee los mensajes de contacto desde un panel claro.'],
                ],
            ]],
            ['type' => 'code', 'data' => [
                'title' => 'Conecta Claude en 10 segundos',
                'language' => 'bash',
                'code' => $mcpCmd,
            ]],
            ['type' => 'faq', 'data' => [
                'title' => 'Preguntas frecuentes',
                'items' => [
                    ['question' => '¿Necesito Docker?', 'answer' => 'No. Universal Web es PHP puro: no usa Docker, Composer ni Node. Lo subes y funciona.'],
                    ['question' => '¿Funciona en hosting compartido?', 'answer' => 'Sí. Solo necesita **PHP 8.1** con SQLite, algo que ofrece casi cualquier hosting con Apache y mod_rewrite.'],
                    ['question' => '¿Puedo hacer mi propio front?', 'answer' => 'Sí. Toda la información está en `/api/*.json`. Tienes un ejemplo en [/examples/headless.html](/examples/headless.html).'],
                    ['question' => '¿Qué puede hacer la IA?', 'answer' => 'Lo que le permitas: leer y editar páginas, escribir entradas del blog, subir imágenes, revisar mensajes y ejecutar la auditoría SEO.'],
                    ['question' => '¿Es seguro?', 'answer' => 'Cada token pertenece a una sola web y tiene permisos `read`, `write` o `publish`. Además, todo queda en el registro de actividad.'],
                ],
            ]],
            ['type' => 'posts', 'data' => ['title' => 'Últimas entradas del blog', 'count' => 3]],
            ['type' => 'cta', 'data' => [
                'title' => '¿Hablamos?',
                'text' => 'Cuéntanos qué web quieres gestionar con IA y te ayudamos a ponerla en marcha.',
                'button_text' => 'Escríbenos', 'button_link' => '/contacto',
            ]],
        ],
        'seo' => [
            'title' => 'Universal Web: tu web gestionada por IA',
            'description' => 'CMS ligero y multi-web en PHP con API JSON y acceso MCP: Claude gestiona tu SEO, tus páginas y tu blog sin plugins ni complicaciones.',
            'image' => $ogHome,
        ],
    ]);

    $main->savePage([
        'slug' => 'como-funciona', 'title' => 'Cómo funciona', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'title' => 'Cómo funciona Universal Web',
                'subtitle' => 'Un back fijo, un contrato JSON y temas intercambiables. Así de simple.',
            ]],
            ['type' => 'text', 'data' => [
                'title' => 'La arquitectura en una pantalla',
                'body' => "Universal Web separa **qué dices** de **cómo se ve**:\n\n- **Back fijo**: guarda páginas, entradas, imágenes, usuarios y mensajes en SQLite. No cambia nunca.\n- **Contrato JSON**: cada página es una lista de bloques (`hero`, `text`, `features`, `faq`...) con campos conocidos.\n- **Temas**: plantillas PHP que pintan esos bloques. Puedes usar el que quieras o hacer tu propio front.\n\nAdemás es **multi-web**: el dominio de cada petición decide qué web se sirve. Una sola instalación puede alojar tu web principal, la de un cliente y un blog personal, cada una con su tema y su contenido.",
            ]],
            ['type' => 'features', 'data' => [
                'title' => 'En tres pasos',
                'items' => [
                    ['icon' => '1️⃣', 'title' => 'Instala', 'text' => 'Sube el zip a tu hosting y abre /install en el navegador. Tardarás menos de un minuto.'],
                    ['icon' => '2️⃣', 'title' => 'Crea un token MCP', 'text' => 'En el panel, elige tu web, los permisos y copia el token. Solo se muestra una vez.'],
                    ['icon' => '3️⃣', 'title' => 'Pídeselo a Claude', 'text' => 'Escribe: «crea una entrada sobre X y optimiza el SEO». Claude hace el resto con las herramientas MCP.'],
                ],
            ]],
            ['type' => 'code', 'data' => [
                'title' => 'Así responde /api/pages/index.json',
                'language' => 'json',
                'code' => "{\n  \"page\": {\n    \"slug\": \"index\",\n    \"title\": \"Tu web, gestionada por IA\",\n    \"url\": \"https://tu-dominio/\",\n    \"seo\": { \"title\": \"...\", \"description\": \"...\" },\n    \"blocks\": [\n      { \"type\": \"hero\", \"data\": { \"title\": \"...\", \"subtitle\": \"...\" } },\n      { \"type\": \"features\", \"data\": { \"items\": [ { \"icon\": \"🌐\", \"title\": \"...\" } ] } }\n    ]\n  }\n}",
            ]],
            ['type' => 'code', 'data' => [
                'title' => 'Las herramientas MCP principales',
                'language' => 'text',
                'code' => "site_get          Datos y ajustes de la web\nsite_update       Nombre, menú, cabecera, contacto, SEO y tema\nblock_types       Tipos de bloque y sus campos\npage_list         Lista las páginas\npage_get          Lee una página con sus bloques\npage_save         Crea o edita una página\npage_delete       Elimina una página\npost_list         Lista las entradas del blog\npost_get          Lee una entrada\npost_save         Crea o edita una entrada\npost_delete       Elimina una entrada\nmedia_list        Lista imágenes, vídeos y audios\nmedia_upload      Sube un archivo por URL o base64\nmessages_list     Lee los mensajes de contacto\nmessage_mark_read Marca un mensaje como leído\ncode_get          Lee el CSS/JS/<head> personalizado\ncode_update       Cambia el diseño con CSS/JS (con versiones)\ncode_history      Versiones anteriores del diseño\ncode_restore      Deshace un cambio de diseño\nbackup_create     Crea una copia de seguridad\nbackup_list       Lista las copias\nseo_audit         Audita el SEO y da una puntuación\nsites_list        Lista las webs (token global)\nsite_create       Crea una web nueva (token global)",
            ]],
            ['type' => 'text', 'data' => [
                'title' => 'Prueba un front alternativo',
                'body' => "¿Quieres ver el contrato JSON en acción? Abre [/examples/headless.html](/examples/headless.html): un front alternativo hecho solo con HTML y JavaScript que lee la API de esta misma web.\n\nTambién hay una **segunda web de demostración**, *Café Aurora*, en [cafe.$baseDomain](http://cafe.$baseDomain): mismo back, otro dominio y otro tema.",
            ]],
        ],
        'seo' => [
            'title' => 'Cómo funciona Universal Web | Back, JSON y MCP',
            'description' => 'Back fijo, contrato JSON y temas intercambiables: así funciona Universal Web y cómo conectar Claude por MCP para gestionar tu web.',
            'image' => $ogHome,
        ],
    ]);

    $main->savePage([
        'slug' => 'nosotros', 'title' => 'Nosotros', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'title' => 'Simple, pro y pensado para la IA',
                'subtitle' => 'Creemos que publicar una web no debería requerir un máster en plugins.',
            ]],
            ['type' => 'text', 'data' => [
                'title' => 'Creado por BionicAdri',
                'body' => "Universal Web es un proyecto de **[BionicAdri](https://www.bionicadri.com)**, la plataforma de gestión para pymes con sede en Murcia que reúne CRM, facturación, RR. HH., operaciones y comunicación en un solo sitio.\n\nEl corazón de BionicAdri es **ADRI**: una IA a la que le pides las cosas como se las pedirías a una persona, por chat o por voz; cuando el encargo es grande se clona, hasta 20 copias a la vez. ADRI **crea y entiende imágenes, audio y vídeo**, hace la dirección de arte, prepara contenido para redes y **crea y mantiene webs**; para eso necesitábamos un motor a la altura: ligero, multi-web, seguro y pensado para que una IA lo maneje sin riesgos.\n\nAsí nació Universal Web: **simple pro, no un WordPress**. Un solo programa sin dependencias que sirve a todas tus webs, se aloja en cualquier hosting y se controla por MCP con permisos claros. Lo publicamos con el código a la vista para que cualquiera pueda usarlo gratis sin fines comerciales.",
            ]],
            ['type' => 'features', 'data' => [
                'title' => 'Nuestros valores',
                'items' => [
                    ['icon' => '🪶', 'title' => 'Simplicidad', 'text' => 'Menos piezas, menos fallos. Una carpeta, un archivo de base de datos y un panel que se entiende a la primera.'],
                    ['icon' => '🔐', 'title' => 'Tus datos son tuyos', 'text' => 'Todo vive en tu servidor, en un archivo SQLite que puedes copiar. Sin servicios de terceros ni bloqueos.'],
                    ['icon' => '✨', 'title' => 'Pensado para IA', 'text' => 'Contrato JSON claro, herramientas MCP y permisos por token para que la IA trabaje rápido y sin riesgos.'],
                ],
            ]],
            ['type' => 'cta', 'data' => [
                'title' => '¿Quieres que la IA lleve también tu empresa?',
                'text' => 'Conoce ADRI y la plataforma de BionicAdri: gestión completa para pymes con IA, datos en Europa y demo gratuita.',
                'button_text' => 'Conocer BionicAdri', 'button_link' => 'https://www.bionicadri.com',
            ]],
        ],
        'seo' => [
            'title' => 'Nosotros | Universal Web',
            'description' => 'Universal Web es un proyecto de BionicAdri: un motor de webs simple y pensado para que la IA, como ADRI, gestione tus webs sin plugins.',
            'image' => $ogHome,
        ],
    ]);

    $main->savePage([
        'slug' => 'contacto', 'title' => 'Contacto', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => ['title' => 'Hablemos', 'subtitle' => 'Cuéntanos tu proyecto y te respondemos en un día laborable.']],
            ['type' => 'contact_form', 'data' => ['title' => 'Envíanos un mensaje', 'text' => 'Los mensajes llegan al panel de administración, en la sección Mensajes.']],
            ['type' => 'faq', 'data' => [
                'title' => 'Antes de escribir',
                'items' => [
                    ['question' => '¿Cuánto tardáis en responder?', 'answer' => 'Normalmente en menos de 24 horas laborables.'],
                    ['question' => '¿Puedo probar Universal Web antes de usarlo en producción?', 'answer' => 'Sí. Puedes instalarlo en local con `php bin/uw serve` y con el contenido de demostración.'],
                ],
            ]],
        ],
        'seo' => [
            'title' => 'Contacto | Universal Web',
            'description' => 'Escríbenos para probar Universal Web en tu proyecto. Respondemos a todos los mensajes del formulario en menos de un día laborable.',
            'image' => $ogHome,
        ],
    ]);

    // ---------------- entradas
    $main->savePost([
        'slug' => 'bienvenido-a-universal-web', 'title' => 'Bienvenido a Universal Web', 'status' => 'published',
        'published_at' => demo_date(24), 'author' => $author, 'tags' => ['novedades', 'inicio'], 'cover_image' => $cover1,
        'excerpt' => 'Qué es Universal Web, para quién está pensado y por qué creemos que gestionar una web puede ser mucho más sencillo.',
        'content' => <<<'MD'
## Una web sin fricción

**Universal Web** es un CMS ligero que sirve varias webs desde una sola instalación. Lo hemos diseñado para tres tipos de personas:

- Quien quiere una web **sin aprender un CMS gigante**.
- Quien gestiona **varias webs** y está harto de actualizarlas una a una.
- Quien quiere que **una IA** se encargue del contenido y del SEO.

### Qué incluye

1. Un panel sencillo para páginas, entradas, imágenes y mensajes.
2. Una API JSON pública para que cualquier front pueda leer tu contenido.
3. Un servidor MCP para que Claude trabaje con tu web.

## Empezar es fácil

Descarga el proyecto, súbelo a tu hosting y abre `/install`. En local basta con ejecutar:

```bash
php bin/uw serve
```

Después abre `http://localhost:8080` y listo. Si quieres saber cómo funciona por dentro, lee la página [Cómo funciona](/como-funciona) o conecta tu IA con la guía de [Claude por MCP](/blog/como-conectar-claude-a-tu-web-por-mcp).
MD,
        'seo' => ['title' => 'Bienvenido a Universal Web', 'description' => 'Descubre qué es Universal Web, para quién está pensado y cómo puedes empezar a gestionar tus webs de forma simple en minutos.', 'image' => $cover1],
    ]);

    $main->savePost([
        'slug' => 'como-conectar-claude-a-tu-web-por-mcp', 'title' => 'Cómo conectar Claude a tu web por MCP', 'status' => 'published',
        'published_at' => demo_date(12, '09:30:00'), 'author' => $author, 'tags' => ['mcp', 'claude', 'guias'], 'cover_image' => $cover2,
        'excerpt' => 'Guía paso a paso para crear un token, elegir permisos y pedirle a Claude que escriba entradas y optimice el SEO de tu web.',
        'content' => <<<'MD'
## Qué es MCP

El *Model Context Protocol* permite que una IA use herramientas de tu servidor. Universal Web expone un conjunto de herramientas para leer y editar páginas, entradas, imágenes, vídeos, mensajes, diseño y copias de seguridad.

## Paso a paso

1. Entra en el panel y abre **Tokens de IA**.
2. Crea un token para tu web y elige los permisos.
3. Copia el token: **solo se muestra una vez**.
4. Ejecuta este comando en tu terminal:

```bash
claude mcp add --transport http universal-web https://tu-dominio/mcp --header "Authorization: Bearer uw_TU_TOKEN"
```

## Permisos

- `read`: consultar contenido y ejecutar la auditoría SEO.
- `write`: crear y editar borradores.
- `publish`: publicar y modificar contenido ya publicado.

> Empieza con `read` y `write` si quieres revisar siempre lo que la IA propone antes de publicarlo.

## Qué puedes pedirle

- «Crea una entrada sobre nuestras novedades y déjala en borrador.»
- «Revisa el SEO de toda la web y corrige lo que falte.»
- «Cambia el título de la portada y añade tres preguntas frecuentes.»

Todo lo que haga queda en el **registro de actividad**. Más detalles en [Cómo funciona](/como-funciona).
MD,
        'seo' => ['title' => 'Cómo conectar Claude a tu web por MCP', 'description' => 'Guía paso a paso: crea un token MCP, elige permisos y pide a Claude que escriba entradas y mejore el SEO de tu web en minutos.', 'image' => $cover2],
    ]);

    $main->savePost([
        'slug' => 'un-back-muchos-fronts-el-contrato-json', 'title' => 'Un back, muchos fronts: el contrato JSON', 'status' => 'published',
        'published_at' => demo_date(4, '17:15:00'), 'author' => $author, 'tags' => ['api', 'json', 'arquitectura'], 'cover_image' => $cover3,
        'excerpt' => 'Cada página es una lista de bloques y cada entrada un objeto JSON: así puedes montar el front que quieras sobre el mismo back.',
        'content' => <<<'MD'
## El contrato

Una página de Universal Web no es HTML: es una **lista de bloques**. Cada bloque tiene un `type` y unos `data` con campos conocidos, descritos en `/api/schema.json`.

```json
{ "type": "hero", "data": { "title": "Hola", "subtitle": "Mundo" } }
```

## Endpoints públicos

- `/api/site.json`: datos de la web y lista de páginas.
- `/api/pages/{slug}.json`: una página con sus bloques.
- `/api/posts.json`: entradas del blog, con paginación y filtro por etiqueta.
- `/api/posts/{slug}.json`: una entrada completa, con su HTML ya generado.
- `POST /api/contact`: recibe mensajes del formulario.

## Un front con HTML y JS

Para demostrar que no hay magia, incluimos un ejemplo mínimo en [/examples/headless.html](/examples/headless.html): un front alternativo que lee la API y pinta los bloques en el navegador.

Gracias a esto puedes usar los temas PHP incluidos, un front en React o Astro, o incluso una app móvil, y **todos comparten el mismo panel y el mismo MCP**.
MD,
        'seo' => ['title' => 'Un back, muchos fronts: el contrato JSON', 'description' => 'Cómo funciona el contrato JSON de Universal Web: bloques, endpoints de la API y un front alternativo hecho solo con HTML y JavaScript.', 'image' => $cover3],
    ]);

    $main->savePost([
        'slug' => 'proximamente-multi-idioma', 'title' => 'Próximamente: multi-idioma', 'status' => 'draft',
        'author' => $author, 'tags' => ['novedades', 'roadmap'],
        'excerpt' => 'Estamos trabajando en traducciones por página. Este borrador está aquí para que veas cómo se ven los borradores en el panel.',
        'content' => "## Borrador\n\nEstamos preparando el soporte para **varios idiomas** por web. Cuando esté listo, podrás publicar cada página en español, inglés o rumano con un solo panel.\n\n- Selector de idioma en el front.\n- Traducciones asistidas por IA mediante MCP.\n- Etiquetas `hreflang` automáticas.",
    ]);

    // ---------------- mensajes de contacto
    insert('messages', [
        'site_id' => $main->id(), 'name' => 'Laura Gómez', 'email' => 'laura@example.com', 'subject' => 'Probar Universal Web',
        'body' => "Hola, me interesa Universal Web para las tres webs de mi estudio. ¿Se puede instalar en un hosting compartido?\n\nGracias.",
        'page' => '/contacto', 'ip' => '127.0.0.1', 'is_read' => 1, 'created_at' => demo_date(3, '11:20:00'),
    ]);
    insert('messages', [
        'site_id' => $main->id(), 'name' => 'Andrei Popescu', 'email' => 'andrei@example.com', 'subject' => 'Idea para un tema',
        'body' => "Buenas, he hecho un tema oscuro para mi blog y me gustaría compartirlo. ¿Dónde lo puedo enviar?",
        'page' => '/contacto', 'ip' => '127.0.0.1', 'is_read' => 0, 'created_at' => demo_date(0, '08:05:00'),
    ]);

    seed_demo_cafe($main, $baseDomain);
}

function seed_demo_cafe(SiteService $main, string $baseDomain): void
{
    $site = site_create('Café Aurora', 'cafe', ['cafe.' . $baseDomain], theme_exists('noche') ? 'noche' : 'default', [
        'tagline' => 'Café de especialidad en el corazón del barrio',
        'accent' => '#d4a373',
        'menu' => [
            ['label' => 'Inicio', 'url' => '/'], ['label' => 'Carta', 'url' => '/carta'],
            ['label' => 'Blog', 'url' => '/blog'], ['label' => 'Contacto', 'url' => '/contacto'],
        ],
        'contact' => ['email' => 'hola@cafe-aurora.local', 'phone' => '+34 600 000 000', 'address' => 'Calle Ficticia 12, 28000 Madrid'],
        'social' => ['instagram' => 'https://instagram.com/cafeaurora'],
        'seo' => ['description' => 'Café Aurora: cafetería de especialidad con granos de origen, repostería casera y un rincón tranquilo para empezar bien el día.'],
        'footer_text' => 'Calle Ficticia 12 · Abierto de 8:00 a 20:00',
    ]);
    $svc = new SiteService($site);

    $ogCafe = demo_media($svc, 'og-cafe-aurora.png', 'Degradado marrón cálido con círculos suaves', [120, 72, 40], [214, 163, 115], 55);
    $coverCafe = demo_media($svc, 'portada-cafe-etiopia.png', 'Degradado ámbar y café con formas suaves', [92, 51, 23], [212, 163, 115], 66);
    if ($ogCafe !== '') $svc->updateSettings(['seo' => ['image' => $ogCafe]]);

    $svc->savePage([
        'slug' => 'index', 'title' => 'Café Aurora', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => [
                'title' => 'Café Aurora', 'subtitle' => 'Café de especialidad, repostería casera y buena conversación. Empieza el día con calma.',
                'cta_text' => 'Ver la carta', 'cta_link' => '/carta', 'cta2_text' => 'Cómo llegar', 'cta2_link' => '/contacto',
            ]],
            ['type' => 'features', 'data' => [
                'title' => 'Lo más pedido',
                'items' => [
                    ['icon' => '☕', 'title' => 'Flat white', 'text' => 'Doble ristretto con leche texturizada. Aterciopelado e intenso.'],
                    ['icon' => '🥐', 'title' => 'Croissant de mantequilla', 'text' => 'Horneado cada mañana, hojaldrado y todavía caliente.'],
                    ['icon' => '🍰', 'title' => 'Tarta de queso', 'text' => 'Cremosa, al horno y con base de galleta. La favorita del barrio.'],
                ],
            ]],
            ['type' => 'text', 'data' => [
                'title' => 'Un rincón con historia',
                'body' => "En **Café Aurora** tostamos en pequeños lotes y trabajamos con productores que conocemos por su nombre. Nuestro objetivo es sencillo: que cada taza sepa a lo que debe saber.\n\nTrabajamos con granos de origen único, leche de proximidad y repostería hecha en casa. Ven con amigos, con tu portátil o con un buen libro.",
            ]],
            ['type' => 'cta', 'data' => [
                'title' => 'Te esperamos',
                'text' => 'Abrimos todos los días de 8:00 a 20:00 en Calle Ficticia 12.',
                'button_text' => 'Contacta con nosotros', 'button_link' => '/contacto',
            ]],
        ],
        'seo' => ['title' => 'Café Aurora | Café de especialidad', 'description' => 'Café Aurora: cafetería de especialidad con granos de origen, repostería casera y un rincón tranquilo para empezar bien el día.', 'image' => $ogCafe],
    ]);

    $svc->savePage([
        'slug' => 'carta', 'title' => 'Carta', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => ['title' => 'Nuestra carta', 'subtitle' => 'Café de especialidad, dulces caseros y desayunos para todos los gustos.']],
            ['type' => 'features', 'data' => [
                'title' => 'Cafés',
                'items' => [
                    ['icon' => '☕', 'title' => 'Espresso · 1,50 €', 'text' => 'Café de origen único, intenso y con crema.'],
                    ['icon' => '🥛', 'title' => 'Flat white · 2,80 €', 'text' => 'Doble espresso con leche texturizada.'],
                    ['icon' => '🧊', 'title' => 'Cold brew · 3,20 €', 'text' => 'Infusión en frío durante 18 horas, servida con hielo.'],
                ],
            ]],
            ['type' => 'features', 'data' => [
                'title' => 'Para acompañar',
                'items' => [
                    ['icon' => '🥐', 'title' => 'Croissant · 1,90 €', 'text' => 'De mantequilla, recién horneado.'],
                    ['icon' => '🍰', 'title' => 'Tarta de queso · 4,50 €', 'text' => 'Receta de la casa con base de galleta.'],
                    ['icon' => '🥑', 'title' => 'Tostada de aguacate · 6,50 €', 'text' => 'Pan de masa madre, aguacate y huevo poché.'],
                ],
            ]],
        ],
        'seo' => ['title' => 'Carta | Café Aurora', 'description' => 'Consulta la carta de Café Aurora: espresso, flat white, cold brew, croissants, tarta de queso y tostadas con precios actualizados.', 'image' => $ogCafe],
    ]);

    $svc->savePage([
        'slug' => 'contacto', 'title' => 'Contacto', 'status' => 'published',
        'blocks' => [
            ['type' => 'hero', 'data' => ['title' => 'Visítanos', 'subtitle' => 'Calle Ficticia 12, Madrid. Abierto todos los días de 8:00 a 20:00.']],
            ['type' => 'contact_form', 'data' => ['title' => 'Escríbenos', 'text' => '¿Reservas para grupos, eventos o dudas? Cuéntanos y te respondemos enseguida.']],
        ],
        'seo' => ['title' => 'Contacto | Café Aurora', 'description' => 'Contacta con Café Aurora: dirección, horario y formulario para reservas de grupos, eventos o cualquier duda sobre nuestra carta.', 'image' => $ogCafe],
    ]);

    $svc->savePost([
        'slug' => 'nuevo-cafe-de-especialidad-de-etiopia', 'title' => 'Nuevo café de especialidad de Etiopía', 'status' => 'published',
        'published_at' => demo_date(6, '09:00:00'), 'author' => 'Equipo Café Aurora', 'tags' => ['cafe', 'novedades'], 'cover_image' => $coverCafe,
        'excerpt' => 'Llega a la barra un lote lavado de Yirgacheffe con notas florales y de cítricos. Te contamos cómo prepararlo.',
        'content' => <<<'MD'
## Llega Yirgacheffe a nuestra barra

Esta semana estrenamos un **café de especialidad de Etiopía**, de la región de Yirgacheffe. Es un lote lavado, tostado en pequeñas tandas, con una acidez brillante y mucha personalidad.

### Notas de cata

- Jazmín y flores blancas.
- Bergamota y limón.
- Final dulce, como de té negro.

### Cómo pedirlo

1. En espresso, para notar su intensidad.
2. En **filtro**, la forma más delicada de disfrutar sus aromas.
3. En bolsa de 250 g, para prepararlo en casa.

> Consejo: usa agua a 92 grados y una molienda media para el filtro.

Pasa a probarlo y cuéntanos qué notas descubres. Encontrarás el resto de productos en nuestra [carta](/carta).
MD,
        'seo' => ['title' => 'Nuevo café de especialidad de Etiopía', 'description' => 'Probamos un lote lavado de Yirgacheffe, en Etiopía, con notas florales y cítricas. Descubre cómo pedirlo y prepararlo en casa.', 'image' => $coverCafe],
    ]);
}
