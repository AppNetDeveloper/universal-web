<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)
//
// SEO y GEO de la 0.4.0 sobre una base de datos temporal (y un servidor local para la caché y las cabeceras):
//   php tests/seo_i18n.php        → código de salida 0 si todo pasa
// Web de prueba: 3 idiomas, una página que solo existe en 2, rutas .html fijadas, una redirección y una FAQ.
// Cubre: rutas fijas y estilo .html, 301 a la dirección canónica, hreflang + x-default, selector de idioma, og:locale,
// canonical, JSON-LD (WebPage con dateModified real, FAQPage, BreadcrumbList, sameAs), sitemap con lastmod y xhtml:link,
// Markdown (/md/, Accept), llms.txt por idioma, noindex en .md/llms, importación de redirecciones, page_languages,
// page_faq, validación de rutas, IndexNow solo con cambios, exportación estática y caché de página completa.

$dir = sys_get_temp_dir() . '/uw-seo-' . getmypid();
@mkdir($dir, 0700, true);
putenv("UW_DATA=$dir");
exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export(dirname(__DIR__) . '/app/bootstrap.php', true) . '; migrate();'));
file_put_contents("$dir/config.php", "<?php return ['secret' => '" . bin2hex(random_bytes(16)) . "'];\n");
require dirname(__DIR__) . '/app/bootstrap.php';
$server = null;
register_shutdown_function(function () use ($dir, &$server) {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    exec('rm -rf ' . escapeshellarg($dir));
});

$fails = 0;
function ok(string $name, bool $cond, string $info = ''): void
{
    global $fails;
    if (!$cond) $fails++;
    printf("%s %-62s %s\n", $cond ? 'ok  ' : 'FAIL', $name, $cond ? '' : mb_substr($info, 0, 400));
}
function err(callable $fn): string { try { $fn(); return ''; } catch (Throwable $e) { return $e->getMessage(); } }
/** Genera una ruta del front como la vería un visitante: [status, body, cabeceras]. */
function render(SiteService $svc, string $path, array $server = []): array
{
    $saved = [$_SERVER, $_GET, $_COOKIE];
    $_SERVER = ['REQUEST_URI' => $path, 'HTTP_HOST' => 'prueba.test', 'REQUEST_METHOD' => 'GET'] + $server + $_SERVER;
    $_GET = []; $_COOKIE = [];
    Out::$capture = true;
    Out::reset();
    ob_start();
    try {
        front_handle(new SiteService(site_by_id($svc->id())), (string) parse_url($path, PHP_URL_PATH), 'GET');
        return [Out::$status, (string) ob_get_clean(), Out::$headers];
    } finally {
        Out::$capture = false;
        [$_SERVER, $_GET, $_COOKIE] = $saved;
    }
}
function jsonld(string $html): array
{
    preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
    $g = json_decode(str_replace('<\/', '</', $m[1] ?? ''), true)['@graph'] ?? [];
    $by = [];
    foreach ($g as $n) $by[$n['@type']] = $n;
    return $by;
}

Actor::set('system', null, 'test');
$site = site_create('Web Prueba', 'web-prueba', ['prueba.test']);
$svc = new SiteService($site);
$svc->updateSettings(['languages' => ['en', 'fr'], 'url_style' => 'html', 'same_as' => ['https://www.wikidata.org/wiki/Q1'],
    'social' => ['linkedin' => 'https://www.linkedin.com/company/ejemplo'],
    'menu' => [['label' => 'Inicio', 'url' => '/'], ['label' => 'Precios', 'url' => '/precios'], ['label' => 'Murcia', 'url' => '/murcia'], ['label' => 'Blog', 'url' => '/blog']]]);
$faq = ['type' => 'faq', 'data' => ['title' => 'Preguntas', 'items' => [['question' => '¿Cuánto cuesta?', 'answer' => 'Desde **10 €** al mes.']]]];
foreach (['es', 'en', 'fr'] as $l) {
    $svc->savePage(['slug' => 'index', 'lang' => $l, 'title' => "Inicio $l", 'status' => 'published', 'blocks' => [['type' => 'hero', 'data' => ['title' => "Inicio $l"]]]]);
    $svc->savePage(['slug' => 'precios', 'lang' => $l, 'title' => "Precios $l", 'status' => 'published', 'path' => $l === 'es' ? '/precios.html' : "/$l/precios.html",
        'seo' => ['description' => 'Planes claros para pymes, sin permanencia y con soporte incluido en todos los planes del servicio.'],
        'blocks' => [['type' => 'hero', 'data' => ['title' => "Precios $l"]], ['type' => 'text', 'data' => ['body' => 'Ver la [guía](/guias/mudanza.html) y la [vieja](/antigua.html).']], $faq]]);
}
foreach (['es', 'en'] as $l) $svc->savePage(['slug' => 'murcia', 'lang' => $l, 'title' => "Murcia $l", 'status' => 'published', 'blocks' => [['type' => 'text', 'data' => ['body' => 'Murcia']]]]);
$svc->savePage(['slug' => 'mudanza', 'title' => 'Guía de mudanza', 'status' => 'published', 'path' => '/guias/mudanza.html', 'blocks' => [['type' => 'text', 'data' => ['body' => 'Guía']]]]);
$svc->savePost(['slug' => 'hola', 'title' => 'Hola', 'status' => 'published', 'content' => "Texto.\n\n## ¿Qué es esto?\n\nUna entrada de prueba con su respuesta larga."]);
q("UPDATE pages SET content_at = '2026-01-15 10:00:00' WHERE slug = 'precios' AND lang = 'es'");

// ---------------------------------------------------------------- rutas fijas y estilo .html
$svc = new SiteService(site_by_id($site['id']));
[$st, $html] = render($svc, '/precios.html');
ok('ruta fija /precios.html responde 200', $st === 200 && str_contains($html, 'Precios es'));
[$st, , $h] = render($svc, '/precios');
ok('/precios (automática) → 301 a /precios.html', $st === 301 && ($h['location'] ?? '') === 'Location: /precios.html', json_encode($h));
ok('/guias/mudanza.html (ruta con carpeta) responde', render($svc, '/guias/mudanza.html')[0] === 200);
ok('/en/precios.html en inglés', str_contains(render($svc, '/en/precios.html')[1], 'Precios en'));
ok('/index.html → 301 a /', render($svc, '/index.html')[0] === 301);
ok('/blog/hola.html (estilo .html) responde y /blog/hola redirige', render($svc, '/blog/hola.html')[0] === 200 && render($svc, '/blog/hola')[0] === 301);
ok('/blog.html es el blog', render($svc, '/blog.html')[0] === 200);

// ---------------------------------------------------------------- hreflang, canonical, og:locale, selector
ok('canonical propio', str_contains($html, '<link rel="canonical" href="http://prueba.test/precios.html">'));
preg_match_all('#hreflang="([a-z-]+)" href="([^"]+)"#', $html, $m);
ok('hreflang es/en/fr + x-default (en ese orden)', implode(',', $m[1]) === 'es,en,fr,x-default' && $m[2][3] === 'http://prueba.test/precios.html', implode(',', $m[1]));
ok('og:locale con región y alternativas', str_contains($html, 'og:locale" content="es_ES"') && str_contains($html, 'og:locale:alternate" content="en_GB"') && str_contains($html, 'og:locale:alternate" content="fr_FR"'));
[, $mur] = render($svc, '/murcia.html');
preg_match_all('#<link rel="alternate" hreflang="([a-z-]+)"#', $mur, $mm);
ok('página solo en es/en: hreflang sin fr', implode(',', $mm[1]) === 'es,en,x-default', implode(',', $mm[1]));
preg_match('#<nav class="lang-switch".*?</nav>#s', $mur, $sw);
ok('selector de idioma sin el idioma que falta', str_contains($sw[0] ?? '', '/en/murcia.html') && !str_contains($sw[0] ?? '', 'hreflang="fr"'), $sw[0] ?? '');
ok('/fr/murcia.html sin versión ni redirección: 404', render($svc, '/fr/murcia.html')[0] === 404);
$pl = page_languages($svc, 'page', 'murcia', ['fr' => '/fr/precios.html']);
ok('page_languages fija la 301 del idioma que falta', ($pl['languages']['fr']['redirect_to'] ?? '') === '/fr/precios.html' && $pl['languages']['fr']['equivalent_path'] === '/fr/murcia.html', json_encode($pl['languages']['fr']));
[$st, , $h] = render($svc, '/fr/murcia.html');
ok('/fr/murcia.html → 301 a su equivalente configurado', $st === 301 && ($h['location'] ?? '') === 'Location: /fr/precios.html');
[, $frHtml] = render($svc, '/fr/precios.html');
ok('el menú en fr enlaza directo al destino (sin enlaces a la 301)', (bool) preg_match('#href="/fr/precios\.html"[^>]*>Murcia#', $frHtml) && !str_contains($frHtml, 'href="/fr/murcia'));
ok('el menú en es lleva a las direcciones reales', str_contains($html, 'href="/precios.html"') && str_contains($html, 'href="/blog.html"') && str_contains($html, 'href="/murcia.html"'));
ok('page_languages no deja redirigir un idioma que sí existe', err(fn() => page_languages($svc, 'page', 'murcia', ['en' => '/en/']))!== '');

// ---------------------------------------------------------------- redirecciones: importar
$imp = redirect_import($svc, "# comentario\nantigua.html -> precios.html\n/en/old.html /en/precios.html\nredir /x.html /guias/mudanza.html permanent\nmal\n/precios.html -> /otra\n");
ok('importar: 3 creadas, 2 con error explicado', count($imp['created']) === 3 && count($imp['errors']) === 2 && $imp['errors'][0]['line'] === 5, json_encode($imp));
ok('importar: no deja tapar una página publicada', str_contains($imp['errors'][1]['error'] ?? '', 'página publicada'));
ok('bucle /a → /a?x: rechazado', str_contains(err(fn() => redirect_save_manual($svc, '/bucle-a', '/bucle-a?x=1')), 'iguales'));
redirect_save_manual($svc, '/bucle-b', '/bucle-c?q=1');
ok('bucle /b → /c y /c → /b: rechazado', str_contains(err(fn() => redirect_save_manual($svc, '/bucle-c', '/bucle-b?q=2')), 'bucle'));
ok('dry_run no guarda nada', count(redirect_import($svc, "/zz.html -> /", true)['created']) === 1 && !redirect_find($svc->id(), '/zz.html'));
[$st, , $h] = render($svc, '/antigua.html');
ok('/antigua.html → 301 a /precios.html', $st === 301 && ($h['location'] ?? '') === 'Location: /precios.html');
ok('enlace del texto a /antigua.html sale ya hacia /precios.html', str_contains(render($svc, '/precios.html')[1], '<a href="/precios.html">vieja</a>'));

// ---------------------------------------------------------------- JSON-LD
$ld = jsonld($html);
ok('JSON-LD: Organization con sameAs (redes + same_as)', in_array('https://www.wikidata.org/wiki/Q1', $ld['Organization']['sameAs'] ?? [], true) && in_array('https://www.linkedin.com/company/ejemplo', $ld['Organization']['sameAs'] ?? [], true));
ok('JSON-LD: WebSite y WebPage enlazados', isset($ld['WebSite']) && ($ld['WebPage']['isPartOf']['@id'] ?? '') === $ld['WebSite']['@id']);
ok('JSON-LD: dateModified = último cambio real (no hoy)', str_starts_with((string) ($ld['WebPage']['dateModified'] ?? ''), '2026-01-15'), (string) ($ld['WebPage']['dateModified'] ?? ''));
ok('JSON-LD: FAQPage con la pregunta y respuesta sin Markdown', ($ld['FAQPage']['mainEntity'][0]['acceptedAnswer']['text'] ?? '') === 'Desde 10 € al mes.');
ok('JSON-LD: BreadcrumbList', ($ld['BreadcrumbList']['itemListElement'][1]['item'] ?? '') === 'http://prueba.test/precios.html');
$pld = jsonld(render($svc, '/blog/hola.html')[1]);
ok('JSON-LD: BlogPosting con inLanguage y FAQ del Markdown', ($pld['BlogPosting']['inLanguage'] ?? '') === 'es-ES' && isset($pld['FAQPage']));
$svc->savePage(['title' => 'Precios es'], (int) $svc->page('precios', 'es')['id']); // guardar sin cambios
ok('guardar sin cambios no mueve la fecha de contenido', (string) val("SELECT content_at FROM pages WHERE slug = 'precios' AND lang = 'es'") === '2026-01-15 10:00:00');
$svc->savePage(['title' => 'Precios ES'], (int) $svc->page('precios', 'es')['id']);
ok('un cambio real sí la mueve', (string) val("SELECT content_at FROM pages WHERE slug = 'precios' AND lang = 'es'") > '2026-02-01');
q("UPDATE pages SET content_at = '2026-01-15 10:00:00' WHERE slug = 'precios' AND lang = 'es'");

// ---------------------------------------------------------------- sitemap
[, $sm] = render($svc, '/sitemap.xml');
ok('sitemap: lastmod real de cada página', str_contains($sm, '<loc>http://prueba.test/precios.html</loc><lastmod>2026-01-15</lastmod>'), substr($sm, 0, 300));
ok('sitemap: alternativas xhtml:link + x-default', str_contains($sm, 'xmlns:xhtml') && str_contains($sm, '<xhtml:link rel="alternate" hreflang="fr" href="http://prueba.test/fr/precios.html"/>') && str_contains($sm, 'hreflang="x-default" href="http://prueba.test/precios.html"'));
ok('sitemap: la página en 2 idiomas no anuncia el tercero', !str_contains($sm, '/fr/murcia'));
ok('sitemap: XML válido', simplexml_load_string($sm) !== false);

// ---------------------------------------------------------------- Markdown y llms.txt
[$st, $md, $h] = render($svc, '/md/precios.md');
ok('/md/precios.md con noindex', $st === 200 && str_contains($md, '# Precios') && ($h['x-robots-tag'] ?? '') === 'X-Robots-Tag: noindex', json_encode($h));
ok('/en/md/precios.md en inglés', str_contains(render($svc, '/en/md/precios.md')[1], 'Precios en'));
ok('/md/blog-hola.md y /md/index.md', render($svc, '/md/blog-hola.md')[0] === 200 && render($svc, '/md/index.md')[0] === 200);
ok('/precios.md (URL de la página en .md) y /guias/mudanza.md', render($svc, '/precios.md')[0] === 200 && render($svc, '/guias/mudanza.md')[0] === 200);
ok('<link rel=alternate type=text/markdown> a /md/', str_contains($html, 'type="text/markdown" title="Markdown" href="http://prueba.test/md/precios.md"'));
ok('Accept: text/markdown → Markdown en la misma URL, sin caché', (function () use ($svc) {
    $saved = Out::$capture; // geo_wants_markdown no actúa en captura: se prueba la función directamente
    $_SERVER['HTTP_ACCEPT'] = 'text/markdown';
    $r = geo_wants_markdown($svc);
    unset($_SERVER['HTTP_ACCEPT']);
    return $r && !$saved;
})());
[$st, $llms, $h] = render($svc, '/llms.txt');
ok('llms.txt con noindex, enlaces a la página y a su Markdown', ($h['x-robots-tag'] ?? '') === 'X-Robots-Tag: noindex' && str_contains($llms, '[Precios ES](http://prueba.test/precios.html) ([Markdown](http://prueba.test/md/precios.md))'), $llms);
ok('llms.txt enlaza los otros idiomas', str_contains($llms, 'http://prueba.test/en/llms.txt') && str_contains($llms, 'http://prueba.test/fr/llms.txt'));
ok('/en/llms.txt y llms-full.txt', str_contains(render($svc, '/en/llms.txt')[1], 'Precios en') && render($svc, '/llms-full.txt')[0] === 200);

// ---------------------------------------------------------------- validación de rutas (entrada del panel y del MCP)
$bad = ['/admin/x.html', '/api/a', '/md/x.html', '/../etc/passwd', '/a b.html', '/x.php', '/x.md', 'sin-barra', '/en/x.html', '//evil.com', '/x?y=1', '/x/<b>.html', '/media/x.html'];
foreach ($bad as $b) ok("ruta rechazada: $b", err(fn() => $svc->savePage(['slug' => 'p-' . md5($b), 'title' => 'x', 'path' => $b])) !== '');
ok('ruta usada por otra página: rechazada', str_contains(err(fn() => $svc->savePage(['slug' => 'otra', 'title' => 'x', 'path' => '/guias/mudanza.html'])), 'ya la usa'));
ok('ruta en otro idioma sin su prefijo: rechazada', str_contains(err(fn() => $svc->savePage(['slug' => 'otra', 'lang' => 'en', 'title' => 'x', 'path' => '/otra.html'])), '/en/'));
ok('la portada no admite ruta fija', err(fn() => $svc->savePage(['title' => 'x', 'path' => '/home.html'], (int) $svc->page('index')['id'])) !== '');

// ---------------------------------------------------------------- cambiar la ruta de algo publicado crea su 301
$p = $svc->savePage(['path' => '/guias/mudanzas.html'], (int) $svc->page('mudanza')['id']);
ok('cambiar la ruta publicada crea la 301 sola', ($svc->lastRedirect['from'] ?? '') === '/guias/mudanza.html' && ($svc->lastRedirect['to'] ?? '') === '/guias/mudanzas.html');
ok('…y la antigua redirige', render($svc, '/guias/mudanza.html')[0] === 301);

// ---------------------------------------------------------------- page_faq (MCP)
McpContext::$token = ['id' => 0, 'site_id' => $site['id'], 'scopes' => ['read', 'write', 'publish'], 'owner_role' => 'admin', 'name' => 't', 'owner_name' => ''];
$call = function (string $n, array $a) { $r = mcp_call($n, $a); return $r['isError'] ? ['_error' => $r['content'][0]['text']] : json_decode($r['content'][0]['text'], true); };
$r = $call('page_faq', ['slug' => 'murcia', 'items' => [['question' => '¿Dónde estáis?', 'answer' => 'En Murcia.']]]);
ok('page_faq crea el bloque y sale FAQPage', ($r['items'] ?? 0) === 1 && isset(jsonld(render($svc, '/murcia.html')[1])['FAQPage']), json_encode($r));
ok('page_faq lee', ($call('page_faq', ['slug' => 'murcia'])['items'][0]['question'] ?? '') === '¿Dónde estáis?');
McpContext::$token['scopes'] = ['read'];
ok('page_faq sin permiso de escritura: error', isset($call('page_faq', ['slug' => 'murcia', 'items' => [['question' => 'a', 'answer' => 'b']]])['_error']));
ok('page_languages con missing sin publish: error', isset($call('page_languages', ['slug' => 'murcia', 'missing' => ['fr' => '']])['_error']));
ok('redirect_import exige publish', isset($call('redirect_import', ['lines' => '/a -> /b'])['_error']));
McpContext::$token['scopes'] = ['read', 'write', 'publish'];
$r = $call('page_languages', ['slug' => 'murcia']);
ok('MCP page_languages', ($r['languages']['fr']['redirect_to'] ?? '') === '/fr/precios.html', json_encode($r));
$r = $call('site_update', ['same_as' => ['javascript:alert(1)']]);
ok('site_update same_as rechaza lo que no es https', isset($r['_error']));
$ann = array_column(array_map(fn($t) => ['n' => $t['name'], 'ro' => $t['annotations']['readOnlyHint']], mcp_tools_for_token()), 'ro', 'n');
ok('page_faq y page_languages no se anuncian como solo lectura', $ann['page_faq'] === false && $ann['page_languages'] === false && $ann['page_get'] === true);
ok('audit registra lo hecho por MCP', (int) val("SELECT COUNT(*) FROM audit_log WHERE action LIKE 'redirect.%'") > 0);

// ---------------------------------------------------------------- exportación estática
[$out] = publish_build($svc);
ok('estático: precios.html, en/precios.html, guias/…, md/', is_file("$out/precios.html") && is_file("$out/en/precios.html") && is_file("$out/guias/mudanzas.html") && is_file("$out/md/precios.md") && is_file("$out/en/md/precios.md") && is_file("$out/blog/hola.html") && is_file("$out/blog.html"));
ok('estático: la redirección de /antigua.html es un archivo .html', is_file("$out/antigua.html") && str_contains((string) file_get_contents("$out/antigua.html"), 'refresh'));

// ---------------------------------------------------------------- IndexNow: solo lo cambiado
$GLOBALS['uw_indexnow_seen'] = [];
ok('IndexNow: guardar sin cambios no avisa', (function () use ($svc) {
    $before = (int) val("SELECT COUNT(*) FROM throttle WHERE key LIKE 'indexnow:%'");
    $p = $svc->page('precios', 'fr');
    $svc->savePage(['title' => $p['title']], (int) $p['id']);
    return (int) val("SELECT COUNT(*) FROM throttle WHERE key LIKE 'indexnow:%'") === $before;
})());
ok('IndexNow: clave en /<clave>.txt', render($svc, '/' . indexnow_key($svc) . '.txt')[1] === indexnow_key($svc));

// ---------------------------------------------------------------- servidor local: caché de página y cabeceras
platform_save(['page_cache' => true]);
page_cache_sync_flag();
$port = 18000 + getmypid() % 2000;
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', dirname(__DIR__) . '/public', dirname(__DIR__) . '/public/index.php'], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, null, ['UW_DATA' => $dir] + getenv());
$get = function (string $path, array $headers = []) use ($port): array {
    for ($i = 0; $i < 30; $i++) {
        $ctx = stream_context_create(['http' => ['ignore_errors' => true, 'follow_location' => 0, 'header' => implode("\r\n", array_merge(['Host: prueba.test'], $headers))]]);
        $body = @file_get_contents("http://127.0.0.1:$port$path", false, $ctx);
        if ($body !== false) {
            $h = [];
            foreach ($http_response_header as $l) if (str_contains($l, ':')) $h[strtolower(strtok($l, ':'))] = trim(substr($l, strpos($l, ':') + 1));
            return [(int) substr($http_response_header[0], 9, 3), $body, $h];
        }
        usleep(100000);
    }
    return [0, '', []];
};
[$st, $b1, $h1] = $get('/precios.html');
[$st2, $b2, $h2] = $get('/precios.html');
ok('caché: primera visita MISS, segunda HIT, mismo HTML', ($h1['x-uw-cache'] ?? '') === 'MISS' && ($h2['x-uw-cache'] ?? '') === 'HIT' && $b1 === $b2, json_encode([$h1['x-uw-cache'] ?? null, $h2['x-uw-cache'] ?? null]));
ok('caché: mismas cabeceras de seguridad en el acierto', ($h2['content-security-policy'] ?? '') === ($h1['content-security-policy'] ?? 'x') && ($h2['x-content-type-options'] ?? '') === 'nosniff' && isset($h2['referrer-policy']));
ok('CSP de la web pública (base-uri, object-src, frame-ancestors)', str_contains($h1['content-security-policy'] ?? '', "object-src 'none'") && str_contains($h1['content-security-policy'] ?? '', "frame-ancestors 'self'"));
ok('caché: If-None-Match → 304', $get('/precios.html', ['If-None-Match: ' . ($h2['etag'] ?? '')])[0] === 304);
ok('caché: con cookie del panel no se usa', !isset($get('/precios.html', ['Cookie: uw_session=abc'])[2]['x-uw-cache']));
ok('caché: con ?consulta no se usa', !isset($get('/precios.html?x=1')[2]['x-uw-cache']));
[$st, $bm, $hm] = $get('/precios.html', ['Accept: text/markdown']);
ok('Accept: text/markdown → Markdown, sin caché, noindex', str_starts_with(trim($bm), '<!--') && str_contains($hm['content-type'] ?? '', 'text/markdown') && ($hm['x-robots-tag'] ?? '') === 'noindex' && !isset($hm['x-uw-cache']));
ok('agente de IA → Markdown (nunca la copia HTML)', str_contains($get('/precios.html', ['User-Agent: GPTBot/1.0'])[2]['content-type'] ?? '', 'markdown'));
ok('caché: un X-Forwarded-Proto falso no envenena la copia', !str_contains($get('/precios.html', ['X-Forwarded-Proto: https'])[1], 'https://prueba.test'));
$svc->savePage(['title' => 'Precios cambiado'], (int) $svc->page('precios', 'es')['id']);
[, $b3, $h3] = $get('/precios.html');
ok('caché: se vacía sola al guardar', ($h3['x-uw-cache'] ?? '') === 'MISS' && str_contains($b3, 'Precios cambiado'));
$svc->savePage(['blocks' => [['type' => 'contact_form', 'data' => ['title' => 'Escríbenos']]]], (int) $svc->page('murcia', 'es')['id']);
$get('/murcia.html');
ok('caché: página con formulario guardada con el refresco de _ts', str_contains($get('/murcia.html')[1], 'data-uw-ts'));
$before = count(glob("$dir/cache/pages/*/*.html") ?: []);
$get('/precios.html', ['Host: otro-host-inventado.test']);
$get('/precios.html', ['Host: prueba.test:4321']);
ok('caché: un Host inventado o un puerto raro no crean copias', count(glob("$dir/cache/pages/*/*.html") ?: []) === $before);
ok('robots.txt, sitemap y 404 no rompen con caché', $get('/robots.txt')[0] === 200 && $get('/sitemap.xml')[0] === 200 && $get('/no-existe.html')[0] === 404);
ok('/data, /app y archivos internos no se sirven', $get('/../data/config.php')[0] !== 200 && $get('/app/core.php')[0] === 404);
platform_save(['page_cache' => false]);
page_cache_sync_flag();
ok('caché desactivada: sin copia', !isset($get('/precios.html')[2]['x-uw-cache']));

echo $fails ? "\n$fails fallos\n" : "\nTodo bien\n";
exit($fails ? 1 : 0);
