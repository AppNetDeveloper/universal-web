<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)
//
// Herramientas «smart» del harness sobre una base de datos temporal:
//   php tests/content_tools.php        → código de salida 0 si todo pasa
// Cubre: historial y deshacer, papelera, page_patch, comprobación al guardar, seo_autofix / seo_bulk_update /
// seo_analyze, permisos de borrador frente a publicado y optimización de imágenes al subirlas.

$dir = sys_get_temp_dir() . '/uw-tools-' . getmypid();
@mkdir($dir, 0700, true);
putenv("UW_DATA=$dir");
// La base se crea en otro proceso (sin config.php aún no hay «instalación»); luego la config con su secreto
exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export(dirname(__DIR__) . '/app/bootstrap.php', true) . '; migrate();'));
file_put_contents("$dir/config.php", "<?php return ['secret' => '" . bin2hex(random_bytes(16)) . "'];\n");
require dirname(__DIR__) . '/app/bootstrap.php';
register_shutdown_function(fn() => exec('rm -rf ' . escapeshellarg($dir) . ' ' . escapeshellarg(UW_PUBLIC . '/media/taller-prueba')));

$fails = 0;
function ok(string $name, bool $cond, string $info = ''): void
{
    global $fails;
    if (!$cond) $fails++;
    printf("%s %-52s %s\n", $cond ? 'ok  ' : 'FAIL', $name, $cond ? '' : $info);
}

$site = site_create('Taller Prueba', 'taller-prueba', ['taller.test']);
$tok = fn(array $scopes) => McpContext::$token = ['id' => 0, 'site_id' => $site['id'], 'scopes' => $scopes, 'owner_role' => 'admin', 'name' => 't', 'owner_name' => ''];
$tok(['read', 'write', 'publish']);
Actor::set('token', 0, 'test');
$call = function (string $n, array $a = []) {
    $r = mcp_call($n, $a);
    return $r['isError'] ? ['_error' => $r['content'][0]['text']] : json_decode($r['content'][0]['text'], true);
};

$long = 'Reparamos coches de todas las marcas en Getafe desde 1998, con presupuesto cerrado y garantía de doce meses en cada trabajo que hacemos.';
$r = $call('page_save', ['slug' => 'servicios', 'title' => 'Servicios', 'status' => 'published', 'blocks' => [
    ['type' => 'hero', 'data' => ['title' => 'Taller en Getafe', 'subtitle' => $long, 'image' => '/media/taller-prueba/x/fachada-del-taller-a1b2c3.webp']],
    ['type' => 'text', 'data' => ['title' => 'Qué hacemos', 'body' => 'Mecánica, chapa y pintura.']],
]]);
ok('page_save devuelve check', isset($r['check']['url'], $r['check']['issues']), json_encode($r));

$r = $call('page_patch', ['slug' => 'servicios', 'ops' => [
    ['op' => 'update', 'index' => 1, 'data' => ['body' => 'Mecánica, chapa, pintura y diagnosis.']],
    ['op' => 'insert', 'block' => ['type' => 'text', 'data' => ['title' => 'Horario', 'body' => 'De lunes a viernes.']]],
    ['op' => 'move', 'index' => 2, 'to' => 1],
]]);
$p = (new SiteService($site))->page('servicios');
ok('page_patch update/insert/move', count($p['blocks']) === 3 && $p['blocks'][1]['data']['title'] === 'Horario' && str_contains($p['blocks'][2]['data']['body'], 'diagnosis'), json_encode($r));
ok('page_patch conserva los demás campos', $p['blocks'][0]['data']['subtitle'] === $long);
$r = $call('page_patch', ['slug' => 'servicios', 'ops' => [['op' => 'delete', 'index' => 9]]]);
ok('page_patch index fuera de rango explica los bloques', str_contains($r['_error'] ?? '', '0: hero'), json_encode($r));

$h = $call('page_history', ['slug' => 'servicios']);
ok('historial guarda la versión anterior', count($h['versions'] ?? []) === 1, json_encode($h));
$call('page_restore', ['slug' => 'servicios']);
ok('page_restore deshace el último cambio', count((new SiteService($site))->page('servicios')['blocks']) === 2);
$call('page_restore', ['slug' => 'servicios']);
ok('deshacer el deshacer', count((new SiteService($site))->page('servicios')['blocks']) === 3);

$call('page_save', ['slug' => 'borrar', 'title' => 'Para borrar', 'status' => 'draft', 'blocks' => []]);
$call('page_delete', ['slug' => 'borrar']);
$t = $call('trash_list');
ok('borrar manda a la papelera', ($t['trash'][0]['slug'] ?? '') === 'borrar', json_encode($t));
$call('trash_restore', ['trash_id' => $t['trash'][0]['trash_id']]);
ok('trash_restore la recupera', (new SiteService($site))->page('borrar') !== null && !$call('trash_list')['trash']);

$tok(['read', 'write']);
$r = $call('page_patch', ['slug' => 'servicios', 'ops' => [['op' => 'delete', 'index' => 0]]]);
ok('sin publish no se toca lo publicado (patch)', str_contains($r['_error'] ?? '', 'publish'));
$r = $call('page_restore', ['slug' => 'servicios']);
ok('sin publish no se restaura lo publicado', str_contains($r['_error'] ?? '', 'publish'));
$r = $call('seo_autofix');
ok('autofix sin publish salta lo publicado', !empty($r['skipped']) && !(new SiteService($site))->page('servicios')['seo']['description'], json_encode($r));
$tok(['read', 'write', 'publish']);

$r = $call('seo_autofix', ['dry_run' => true]);
ok('autofix dry_run no guarda', (new SiteService($site))->page('servicios')['seo']['description'] === '' && $r['changes']);
$r = $call('seo_autofix');
$p = (new SiteService($site))->page('servicios');
ok('autofix: meta descripción del contenido', str_starts_with($p['seo']['description'], 'Reparamos coches') && mb_strlen($p['seo']['description']) <= SEO_DESC_MAX + 1, $p['seo']['description']);
ok('autofix: alt desde el nombre del archivo', $p['blocks'][0]['data']['image_alt'] === 'Taller en Getafe' || $p['blocks'][0]['data']['image_alt'] === 'Fachada del taller', $p['blocks'][0]['data']['image_alt']);
ok('autofix: no sobrescribe en la segunda pasada', !array_filter($call('seo_autofix')['changes'], fn($c) => str_contains($c['where'], 'servicios')));
ok('autofix queda en el historial', count($call('page_history', ['slug' => 'servicios'])['versions']) >= 4);

$r = $call('seo_bulk_update', ['items' => [
    ['slug' => 'servicios', 'seo_title' => 'Taller mecánico en Getafe | Servicios'],
    ['slug' => 'no-existe', 'description' => str_repeat('a', 80)],
    ['slug' => 'servicios', 'description' => 'corta'],
]]);
ok('bulk: uno bien y dos fallan por separado', $r['ok'] === 1 && $r['failed'] === 2, json_encode($r));
ok('bulk: guarda el título SEO', (new SiteService($site))->page('servicios')['seo']['title'] === 'Taller mecánico en Getafe | Servicios');

$a = $call('seo_analyze', ['slug' => 'servicios', 'keyword' => 'taller']);
ok('analyze: vista previa y palabra clave', ($a['serp_preview']['title'] ?? '') !== '' && $a['keyword']['in_title'] && $a['keyword']['in_h1'], json_encode($a['keyword'] ?? $a));
ok('analyze: datos estructurados', in_array('WebSite', $a['facts']['structured_data'], true) || in_array('Organization', $a['facts']['structured_data'], true), json_encode($a['facts']['structured_data']));

$svc = new SiteService($site);
ok('IndexNow: no avisa desde dominios .test', !indexnow_enabled($svc));
$r = site_check_render($svc, '/' . indexnow_key($svc) . '.txt');
ok('IndexNow: sirve el archivo de la clave', $r['status'] === 200 && trim($r['body']) === indexnow_key($svc));

if (function_exists('imagewebp')) {
    $im = imagecreatetruecolor(3200, 2400);
    for ($i = 0; $i < 3200; $i += 5) imageline($im, $i, 0, 3200 - $i, 2400, imagecolorallocate($im, $i % 255, ($i * 7) % 255, 90));
    ob_start(); imagejpeg($im, null, 95); $jpg = (string) ob_get_clean();
    $m = $call('media_upload', ['filename' => 'grande.jpg', 'data_base64' => base64_encode($jpg)]);
    $size = getimagesize(UW_PUBLIC . ($m['url'] ?? '/x'));
    ok('foto grande → WebP de 2000 px', str_ends_with($m['url'] ?? '', '.webp') && $size && max($size[0], $size[1]) === MEDIA_MAX_SIDE, json_encode($m));
    $m2 = $call('media_upload', ['filename' => 'original.jpg', 'data_base64' => base64_encode($jpg), 'keep_original' => true]);
    ok('keep_original la deja tal cual', str_ends_with($m2['url'] ?? '', '.jpg') || str_contains($m2['_error'] ?? '', 'MB'), json_encode($m2));
    $png = imagecreatetruecolor(64, 64); ob_start(); imagepng($png); $small = (string) ob_get_clean();
    $m3 = $call('media_upload', ['filename' => 'logo.png', 'data_base64' => base64_encode($small)]);
    ok('PNG pequeño no se convierte', str_ends_with($m3['url'] ?? '', '.png'), json_encode($m3));
    foreach ([$m, $m2, $m3] as $x) if (isset($x['url'])) @unlink(UW_PUBLIC . $x['url']);

    // Variantes para móvil (srcset)
    $mv = $call('media_upload', ['filename' => 'movil.jpg', 'data_base64' => base64_encode($jpg)]);
    $f = UW_PUBLIC . ($mv['url'] ?? '/x');
    [$v480, $v960] = array_values(media_variant_paths($f));
    ok('subir foto grande genera -480w y -960w', is_file($v480) && is_file($v960) && (getimagesize($v480)[0] ?? 0) === 480 && (getimagesize($v960)[0] ?? 0) === 960);
    $a = img_attrs($mv['url'] ?? '/x', '50vw');
    ok('img_attrs: srcset con las existentes', str_contains($a, '-480w.webp 480w') && str_contains($a, '-960w.webp 960w') && str_contains($a, ' 2000w') && str_contains($a, 'sizes="50vw"') && str_contains($a, 'width="2000" height="1500"'), $a);
    unlink($v960);
    ok('img_attrs: solo las que existen', !str_contains(img_attrs($mv['url']), '960w') && str_contains(img_attrs($mv['url']), '480w'));
    ok('img_attrs: ruta externa o con .. solo src', img_attrs('https://x.test/a.jpg') === ' src="https://x.test/a.jpg"' && !str_contains(img_attrs('/media/../app/core.php'), 'width='));
    ok('media_variants rechaza ..', media_variants(dirname($f) . '/../' . basename(dirname($f)) . '/' . basename($f)) === []);
    $tiny = $call('media_upload', ['filename' => 'logo2.png', 'data_base64' => base64_encode($small)]);
    ok('imagen pequeña: sin variantes', is_file(UW_PUBLIC . $tiny['url']) && !media_variant_files(UW_PUBLIC . $tiny['url']));
    ok('media_variants rehace las que faltan', count(media_variants($f)) === 1 && is_file($v960));
    (new SiteService($site))->deleteMedia((int) $mv['id']);
    ok('borrar el medio borra las variantes', !is_file($f) && !is_file($v480) && !is_file($v960));
    if (isset($tiny['url'])) @unlink(UW_PUBLIC . $tiny['url']);
}

// ---- Redirecciones 301
$svc = new SiteService($site);
$call('page_save', ['slug' => 'precios', 'title' => 'Precios', 'status' => 'published', 'blocks' => []]);
$r = $call('page_save', ['slug' => 'precios', 'new_slug' => 'tarifas', 'status' => 'published']);
ok('cambiar slug crea la 301', ($r['redirect_created']['from'] ?? '') === '/precios' && $r['redirect_created']['to'] === '/tarifas', json_encode($r['redirect_created'] ?? $r));
$loc = function (string $path) use ($svc) { $x = site_check_render($svc, $path); return [$x['status'], Out::$headers['location'] ?? '']; };
ok('301 con Location a /tarifas', $loc('/precios') === [301, 'Location: /tarifas']);
$_SERVER['QUERY_STRING'] = 'utm=1';
ok('301 conserva la query', $loc('/precios')[1] === 'Location: /tarifas?utm=1');
unset($_SERVER['QUERY_STRING']);
$call('page_save', ['slug' => 'tarifas', 'new_slug' => 'precios-2026']);
$rl = $call('redirect_list')['redirects'];
$m = array_column($rl, 'to_path', 'from_path');
ok('cadena A→B→C se aplana a A→C', ($m['/precios'] ?? '') === '/precios-2026' && ($m['/tarifas'] ?? '') === '/precios-2026', json_encode($m));
$call('page_save', ['slug' => 'precios-2026', 'new_slug' => 'precios']);
$m = array_column($call('redirect_list')['redirects'], 'to_path', 'from_path');
ok('volver al slug viejo quita el bucle', !isset($m['/precios']) && ($m['/tarifas'] ?? '') === '/precios' && ($m['/precios-2026'] ?? '') === '/precios', json_encode($m));
ok('la página viva no se redirige', site_check_render($svc, '/precios')['status'] === 200);
$r = $call('redirect_save', ['from' => '/precios', 'to' => '/tarifas']);
ok('redirect_save rechaza from con página publicada', str_contains($r['_error'] ?? '', 'taparía'), json_encode($r));
ok('redirect_save rechaza ..', isset($call('redirect_save', ['from' => '/a/../b', 'to' => '/precios'])['_error']));
ok('redirect_save rechaza http externo', isset($call('redirect_save', ['from' => '/viejo', 'to' => 'javascript:alert(1)'])['_error']));
$r = $call('redirect_save', ['from' => '/viejo/', 'to' => 'https://ejemplo.com/x']);
ok('redirect_save externo https', ($r['redirect']['from'] ?? '') === '/viejo' && $loc('/viejo') === [301, 'Location: https://ejemplo.com/x'], json_encode($r));
$call('page_save', ['slug' => 'enlaza', 'title' => 'Enlaza', 'status' => 'published', 'blocks' => [['type' => 'text', 'data' => ['title' => 'x', 'body' => 'Ver [tarifas](/tarifas) y [rota](/no-hay).']]]]);
$sc = site_check($svc);
$msgs = array_map(fn($i) => $i['severity'] . ':' . $i['message'], $sc['issues']);
ok('sitecheck: enlace a redirigida es notice', (bool) array_filter($msgs, fn($x) => str_starts_with($x, 'notice:Enlace a una URL redirigida: /tarifas')), json_encode($msgs));
ok('sitecheck: no la marca como rota', !array_filter($msgs, fn($x) => str_contains($x, 'Enlace roto: /tarifas')) && (bool) array_filter($msgs, fn($x) => str_contains($x, 'Enlace roto: /no-hay')));
$call('page_delete', ['slug' => 'enlaza']);
$call('page_save', ['slug' => 'borrame', 'title' => 'Borrame', 'status' => 'published', 'blocks' => []]);
$n0 = count($call('redirect_list')['redirects']);
$call('page_delete', ['slug' => 'borrame']);
ok('borrar página no crea redirección', count($call('redirect_list')['redirects']) === $n0);
$call('post_save', ['slug' => 'noticia', 'title' => 'Noticia', 'status' => 'published', 'content' => 'Hola']);
$r = $call('post_save', ['slug' => 'noticia', 'new_slug' => 'noticia-nueva']);
ok('post: cambiar slug crea 301 /blog', ($r['redirect_created']['from'] ?? '') === '/blog/noticia' && $loc('/blog/noticia') === [301, 'Location: /blog/noticia-nueva'], json_encode($r['redirect_created'] ?? $r));
$call('page_save', ['slug' => 'borrador', 'title' => 'Borrador', 'status' => 'draft', 'blocks' => []]);
$call('page_save', ['slug' => 'borrador', 'new_slug' => 'borrador-2']);
ok('borrador renombrado no crea 301', !isset(array_column($call('redirect_list')['redirects'], 'to_path', 'from_path')['/borrador']));
$r = $call('redirect_delete', ['from' => '/viejo']);
ok('redirect_delete borra y deja de redirigir', ($r['deleted'] ?? '') === '/viejo' && site_check_render($svc, '/viejo')['status'] === 404);
$tok(['read', 'write']);
ok('redirect_save exige publish', isset($call('redirect_save', ['from' => '/z', 'to' => '/precios'])['_error']));
$tok(['read', 'write', 'publish']);
// Banner de cookies: solo si la web usa algo no esencial, Rechazar con el mismo peso, versión y sin cierre de script.
{
    $svcOf = fn() => new SiteService(row('SELECT * FROM sites WHERE id = ?', [$site['id']]));
    $svc = $svcOf();
    ok('cookies: sin ajustes no hay banner', cookies_banner($svc) === '' && cookies_head($svc) === '');
    $r = $call('site_update', ['cookies' => ['ga4' => 'G-TEST12345']]);
    $svc = $svcOf();
    $b = cookies_banner($svc);
    ok('cookies: con GA4 se activa solo y sale banner', cookies_config($svc)['enabled'] && str_contains($b, 'id="uwc"'), json_encode($r));
    ok('cookies: Rechazar igual que Aceptar, en la 1.ª capa', str_contains($b, 'data-uwc="accept" class="uwc-main"') && str_contains($b, 'data-uwc="reject" class="uwc-main"'));
    ok('cookies: sin casillas premarcadas', !preg_match('/data-cat="[a-z]+"[^>]*checked/', $b));
    ok('cookies: role dialog + aria-labelledby', str_contains($b, 'role="dialog"') && str_contains($b, 'aria-labelledby="uwc-h"'));
    ok('cookies: Consent Mode v2 denegado por defecto', str_contains(cookies_head($svc), "analytics_storage:'denied'"));
    preg_match('#<script>(.*)</script>#s', $b, $m);
    ok('cookies: el script no lleva la cadena de cierre', isset($m[1]) && !str_contains(strtolower($m[1]), '</script'));
    ok('cookies: el consentimiento guarda versión y caduca', str_contains($b, '"ver":') && str_contains($b, '"days":365'));
    $ver1 = preg_match('/"ver":"(\w+)"/', $b, $v1) ? $v1[1] : '';
    $call('site_update', ['cookies' => ['clarity' => 'abc123def4']]);
    $b2 = cookies_banner($svcOf());
    ok('cookies: añadir una herramienta cambia la versión (se vuelve a preguntar)', preg_match('/"ver":"(\w+)"/', $b2, $v2) && $v2[1] !== $ver1);
}

// FAQ en entradas: «## ¿Pregunta?» + respuesta → FAQPage en el JSON-LD de la entrada
$call('post_save', ['slug' => 'faq-taller', 'title' => 'Dudas del taller', 'status' => 'published', 'content' => "Intro.\n\n## ¿Cuánto cuesta una revisión?\n\nDesde 49 € con mano de obra incluida.\n\n## ¿Hacéis ITV?\n\nSí, llevamos el coche a la ITV por ti."]);
$html = site_check_render(new SiteService($site), '/blog/faq-taller')['body'];
ok('entrada con ## ¿Pregunta? emite FAQPage', str_contains($html, '"FAQPage"') && str_contains($html, 'Hacéis ITV'));
$a = $call('seo_analyze', ['slug' => 'faq-taller', 'type' => 'post']);
ok('seo_analyze cuenta las FAQ de la entrada', ($a['facts']['faq_questions'] ?? 0) === 2, json_encode($a['facts'] ?? $a));

// Dominios y manual por MCP: lo que la IA necesita para explicarlo a la persona
$r = $call('domain_add', ['domain' => 'mi-taller.es']);
ok('domain_add añade y da el mensaje para la persona', in_array('mi-taller.es', $r['domains'] ?? [], true) && str_contains($r['message_for_user'] ?? '', 'mi-taller.es'), json_encode($r));
ok('domain_add rechaza nombres locales', str_contains($call('domain_add', ['domain' => 'localhost'])['_error'] ?? '', 'no es un dominio'));
$r = $call('domain_status');
ok('domain_status: vista previa y pasos', str_contains($r['preview_url'] ?? '', '/_ver/') && $r['ready'] === false && $r['steps'], json_encode($r));
$m = $call('manual');
ok('manual: índice de secciones', count($m['sections'] ?? []) >= 12 && str_starts_with($m['sections'][0], '1.'), json_encode($m));
ok('manual: sección por palabra', str_contains($call('manual', ['section' => 'copias'])['markdown'] ?? '', 'fuera del servidor'));

// Registros DNS exactos para la persona (raíz con www, subdominio y .com.es)
ok('DNS: raíz con A @ y CNAME www', array_column(domain_dns_records('mitienda.es', '203.0.113.10', ''), 'name') === ['@', 'www']);
ok('DNS: subdominio de .com.es solo con su nombre', array_column(domain_dns_records('tienda.mi.com.es', '203.0.113.10', ''), 'name') === ['tienda']);
ok('DNS: IPv6 añade AAAA', in_array('AAAA', array_column(domain_dns_records('mitienda.es', '203.0.113.10', '2001:db8::1'), 'type'), true));
ok('DNS: sslip.io no necesita registros', domain_dns_records('x.1-2-3-4.sslip.io', '1.2.3.4', '') === []);

// Guías «Guíame» y su versión MCP
$g = $call('manual', ['screen' => 'Ajustes']);
ok('manual screen: Ajustes paso a paso', ($g['screen'] ?? '') === 'settings' && in_array('Dominios', array_column($g['steps'] ?? [], 'step'), true), json_encode($g));
ok('manual screen vacío: lista de pantallas', count($call('manual', ['screen' => ''])['screens'] ?? []) >= 20);

// Un dominio sin web no enseña la web de otro cliente (con una sola web, sí la por defecto)
ok('host desconocido con una web: la por defecto', (site_for_public_host('otro.example.org')['id'] ?? 0) > 0 || (int) val('SELECT COUNT(*) FROM sites') > 1);
site_create('Otra Web', 'otra-web', ['otra.test']);
ok('host desconocido con varias webs: ninguna', site_for_public_host('otro.example.org') === null);
ok('host conocido y su www', (site_for_public_host('www.otra.test')['slug'] ?? '') === 'otra-web');
ok('IP o local: web por defecto', site_for_public_host('127.0.0.1') !== null && site_for_public_host('demo.localhost') !== null);

// Cuentas: cambiar nombre y email
$ua = user_create('ana@example.com', 'Ana', 'clave-de-prueba-1');
$ub = user_create('beto@example.com', 'Beto', 'clave-de-prueba-2');
insert('password_resets', ['user_id' => $ua, 'token_hash' => 'x', 'created_at' => now(), 'expires_at' => time() + 600, 'ip' => '']);
ok('perfil: solo nombre no anula la recuperación', user_update_profile($ua, 'Ana Ruiz', 'ANA@example.com') === false && user_by_id($ua)['name'] === 'Ana Ruiz' && (int) val('SELECT COUNT(*) FROM password_resets WHERE user_id = ?', [$ua]) === 1);
ok('perfil: cambiar email anula la recuperación', user_update_profile($ua, 'Ana Ruiz', 'nueva@example.com') === true && user_by_id($ua)['email'] === 'nueva@example.com' && (int) val('SELECT COUNT(*) FROM password_resets WHERE user_id = ?', [$ua]) === 0);
$err = function (callable $f) { try { $f(); return ''; } catch (UwError $e) { return $e->getMessage(); } };
ok('perfil: email de otra cuenta rechazado', $err(fn() => user_update_profile($ua, 'A', 'beto@example.com')) !== '' && user_by_id($ua)['email'] === 'nueva@example.com');
ok('perfil: email no válido rechazado', $err(fn() => user_update_profile($ua, 'A', 'no-es-email')) !== '');
ok('sesión fallida: no guarda contraseñas', adm_safe_post(['current' => 'a', 'new' => 'b', 'confirm' => 'c', 'confirm_password' => 'd', 'email' => 'e']) === ['email' => 'e']);

echo $fails ? "\n$fails fallo(s)\n" : "\nTodo bien\n";
exit($fails ? 1 : 0);
