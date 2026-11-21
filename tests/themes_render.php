<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)
//
// Todos los temas conservan la mecánica de la 0.4.0, sobre una base de datos temporal:
//   php tests/themes_render.php        → código de salida 0 si todo pasa
// Para cada tema de themes/ pinta portada, página con todos los bloques, blog, entrada, contacto y 404 en los 7 idiomas
// y exige: hreflang de los 7 + x-default, JSON-LD válido, el CSS/JS propio de la web (site_code_head / site_code_body_end),
// el crédito, selector de idioma, «Saltar al contenido», un solo <h1> y cero avisos de PHP. También los textos de
// ejemplo (sample.json): 7 idiomas, bloques válidos y sin palabras vetadas; y que cada tema pasa el validador de subida.

$dir = sys_get_temp_dir() . '/uw-themes-' . getmypid();
@mkdir($dir, 0700, true);
putenv("UW_DATA=$dir");
exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('require ' . var_export(dirname(__DIR__) . '/app/bootstrap.php', true) . '; migrate();'));
file_put_contents("$dir/config.php", "<?php return ['secret' => '" . bin2hex(random_bytes(16)) . "'];\n");
require dirname(__DIR__) . '/app/bootstrap.php';
register_shutdown_function(fn() => exec('rm -rf ' . escapeshellarg($dir)));

$fails = 0;
function ok(string $name, bool $cond, string $info = ''): void
{
    global $fails;
    if (!$cond) $fails++;
    printf("%s %-64s %s\n", $cond ? 'ok  ' : 'FAIL', $name, $cond ? '' : mb_substr($info, 0, 400));
}
/** Pinta una ruta del front como la vería un visitante; los avisos de PHP se cuentan como fallo. */
function render(SiteService $svc, string $host, string $path): array
{
    $saved = [$_SERVER, $_GET, $_COOKIE];
    $_SERVER = ['REQUEST_URI' => $path, 'HTTP_HOST' => $host, 'REQUEST_METHOD' => 'GET'] + $_SERVER;
    $_GET = []; $_COOKIE = [];
    $warnings = [];
    set_error_handler(function (int $no, string $msg, string $file, int $line) use (&$warnings) { $warnings[] = "$msg ($file:$line)"; return true; });
    Out::$capture = true;
    Out::reset();
    ob_start();
    try {
        front_handle(new SiteService(site_by_id($svc->id())), (string) parse_url($path, PHP_URL_PATH), 'GET');
        return [Out::$status, (string) ob_get_clean(), $warnings];
    } finally {
        restore_error_handler();
        Out::$capture = false;
        [$_SERVER, $_GET, $_COOKIE] = $saved;
    }
}

const LANGS = ['es', 'en', 'ro', 'fr', 'de', 'it', 'pt'];
const BANNED = ['open source', 'opensource', 'software libre', 'código abierto', 'codigo abierto', 'zerotier', 'chatgpt', 'claude', 'gemini', 'openai', 'anthropic', 'llama', 'mistral', 'qwen', 'deepseek'];
Actor::set('system', null, 'test');

// Una página con todos los tipos de bloque que pinta un tema
$allBlocks = [
    ['type' => 'hero', 'data' => ['eyebrow' => 'Antetítulo', 'title' => 'Título de la portada', 'subtitle' => 'Subtítulo', 'cta_text' => 'Botón', 'cta_link' => '/contacto', 'cta2_text' => 'Otro', 'cta2_link' => '/blog']],
    ['type' => 'text', 'data' => ['title' => 'Texto', 'body' => "Un **párrafo** con [enlace](/blog).\n\n- uno\n- dos"]],
    ['type' => 'features', 'data' => ['eyebrow' => 'E', 'title' => 'Servicios', 'items' => [['icon' => 'truck', 'title' => 'Uno', 'text' => 'a', 'link' => '/blog'], ['icon' => '🙂', 'title' => 'Dos', 'text' => 'b']]]],
    ['type' => 'stats', 'data' => ['items' => [['value' => '12', 'label' => 'Ejemplo'], ['value' => '3', 'label' => 'Ejemplo']]]],
    ['type' => 'split', 'data' => ['title' => 'Imagen y texto', 'body' => "- a\n- b", 'button_text' => 'Ver', 'button_link' => '/blog']],
    ['type' => 'steps', 'data' => ['title' => 'Pasos', 'items' => [['title' => 'Uno', 'text' => 'a'], ['title' => 'Dos', 'text' => 'b']]]],
    ['type' => 'cards', 'data' => ['title' => 'Tarjetas', 'items' => [['title' => 'Una', 'text' => 'a', 'tag' => 'Nuevo', 'link' => '/blog']]]],
    ['type' => 'testimonials', 'data' => ['title' => 'Opiniones', 'items' => [['quote' => 'Ejemplo', 'name' => 'Persona de ejemplo', 'role' => 'Ejemplo']]]],
    ['type' => 'pricelist', 'data' => ['title' => 'Precios', 'items' => [['section' => 'A', 'name' => 'Uno', 'price' => '10', 'tag' => 'Nuevo']]]],
    ['type' => 'hours', 'data' => ['title' => 'Horario', 'items' => [['days' => 'lu-vi', 'opens' => '09:00', 'closes' => '14:00'], ['days' => 'do', 'closed' => 'sí']]]],
    ['type' => 'faq', 'data' => ['title' => 'Preguntas', 'items' => [['question' => '¿Una?', 'answer' => 'Sí.']]]],
    ['type' => 'cta', 'data' => ['title' => 'Llamada', 'text' => 't', 'button_text' => 'Ir', 'button_link' => '/contacto']],
    ['type' => 'code', 'data' => ['title' => 'Código', 'language' => 'bash', 'code' => 'echo hola']],
    ['type' => 'posts', 'data' => ['title' => 'Entradas', 'count' => 3]],
    ['type' => 'logos', 'data' => ['title' => 'Logos', 'items' => []]],
    ['type' => 'gallery', 'data' => ['title' => 'Galería', 'images' => []]],
];

foreach (array_keys(themes_available()) as $theme) {
    $host = "$theme.test";
    $site = site_create("Prueba $theme", "p-$theme", [$host], $theme);
    $svc = new SiteService($site);
    $svc->updateSettings(['languages' => array_values(array_diff(LANGS, ['es'])), 'contact' => ['email' => 'hola@ejemplo.test', 'phone' => '+34 900 000 000', 'address' => 'Calle Ejemplo 1'],
        'cta' => ['label' => 'Cita', 'url' => '/contacto'], 'utility_menu' => [['label' => 'Clientes', 'url' => '/contacto']], 'tagline' => 'Eslogan',
        'social' => ['instagram' => 'https://instagram.com/ejemplo', 'whatsapp' => 'https://wa.me/34900000000'],
        'menu' => [['label' => 'Inicio', 'url' => '/'], ['label' => 'Todo', 'url' => '/todo'], ['label' => 'Blog', 'url' => '/blog'], ['label' => 'Contacto', 'url' => '/contacto']]]);
    site_code_save((int) $site['id'], ['css' => '/* css-propio */', 'js' => '/* js-propio */']);
    foreach (LANGS as $l) {
        $svc->savePage(['slug' => 'index', 'lang' => $l, 'title' => "Inicio $l", 'status' => 'published', 'blocks' => [$allBlocks[0], $allBlocks[2]]]);
        $svc->savePage(['slug' => 'todo', 'lang' => $l, 'title' => "Todo $l", 'status' => 'published', 'blocks' => $allBlocks]);
        $svc->savePage(['slug' => 'contacto', 'lang' => $l, 'title' => "Contacto $l", 'status' => 'published', 'blocks' => [['type' => 'contact_form', 'data' => ['title' => 'Escríbenos', 'show_phone' => 'sí', 'privacy_url' => '/todo']]]]);
        $svc->savePost(['slug' => 'hola', 'lang' => $l, 'title' => "Hola $l", 'status' => 'published', 'excerpt' => 'Resumen', 'content' => "Texto.\n\n## Sección\n\nMás texto.", 'tags' => ['prueba']]);
    }
    $bad = [];
    foreach (LANGS as $l) {
        $p = $l === 'es' ? '' : "/$l";
        foreach (["$p/" => 200, "$p/todo" => 200, "$p/contacto" => 200, "$p/blog" => 200, "$p/blog/hola" => 200, "$p/no-existe" => 404] as $path => $want) {
            [$st, $html, $warn] = render($svc, $host, $path);
            $why = [];
            if ($st !== $want) $why[] = "estado $st";
            if ($warn) $why[] = 'aviso PHP: ' . $warn[0];
            if (!str_contains($html, '/site-assets/custom.css')) $why[] = 'sin site_code_head';
            if (!str_contains($html, '/site-assets/custom.js')) $why[] = 'sin site_code_body_end';
            if (!str_contains($html, 'bionicadri.com')) $why[] = 'sin crédito';
            if (!str_contains($html, '<link rel="icon" href="data:,">')) $why[] = 'sin icono (el navegador pediría /favicon.ico: 404 en consola)';
            if (!preg_match('#<a class="skip" href="\#main"#', $html) || !str_contains($html, 'id="main"')) $why[] = 'sin «Saltar al contenido»';
            if (preg_match_all('#<h1[\s>]#', $html) !== 1) $why[] = preg_match_all('#<h1[\s>]#', $html) . ' h1';
            if (!preg_match('#<html lang="' . $l . '"#', $html)) $why[] = 'lang del html';
            if ($want === 200) {
                preg_match_all('#<link rel="alternate" hreflang="([^"]+)"#', $html, $m);
                if (count(array_intersect(LANGS, $m[1])) !== 7 || !in_array('x-default', $m[1], true)) $why[] = 'hreflang: ' . implode(',', $m[1]);
                preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $j);
                if (!is_array(json_decode(str_replace('<\/', '</', $j[1] ?? ''), true)['@graph'] ?? null)) $why[] = 'JSON-LD';
                if (substr_count($html, 'hreflang="') < 14) $why[] = 'selector de idioma';
            }
            if ($why) $bad[] = "$path: " . implode(', ', $why);
        }
    }
    ok("$theme: 6 vistas × 7 idiomas con la mecánica de la 0.4.0", !$bad, implode(' | ', array_slice($bad, 0, 4)));

    // Textos de ejemplo
    if (is_file(UW_ROOT . "/themes/$theme/sample.json")) {
        $all = theme_sample_all($theme);
        ok("$theme: sample.json en los 7 idiomas", array_values(array_intersect(LANGS, array_keys($all))) === LANGS, implode(',', array_keys($all)));
        $errs = [];
        foreach ($all as $l => $smp) {
            $txt = mb_strtolower(json_encode($smp, JSON_UNESCAPED_UNICODE));
            foreach (BANNED as $w) if (preg_match('/\b' . preg_quote($w, '/') . '\b/u', $txt)) $errs[] = "$l: «{$w}»";
            if (count($smp['pages']) < 5 || count($smp['posts'] ?? []) < 2) $errs[] = "$l: faltan páginas o entradas";
            $slugs = array_column($smp['pages'], 'slug');
            if (($slugs[0] ?? '') !== 'index') $errs[] = "$l: la primera página no es index";
            foreach ($smp['menu'] ?? [] as $m) if ($m['url'] !== '/' && $m['url'] !== '/blog' && !in_array(ltrim($m['url'], '/'), $slugs, true)) $errs[] = "$l: menú a {$m['url']} sin página";
            foreach ($smp['pages'] as $pg) {
                try { sanitize_blocks($pg['blocks']); } catch (Throwable $e) { $errs[] = "$l/{$pg['slug']}: " . $e->getMessage(); }
                $len = mb_strlen((string) ($pg['seo']['description'] ?? ''));
                if ($len < 50 || $len > 160) $errs[] = "$l/{$pg['slug']}: descripción SEO de $len caracteres";
                $heroes = count(array_filter($pg['blocks'], fn($b) => $b['type'] === 'hero'));
                if ($heroes > 1) $errs[] = "$l/{$pg['slug']}: $heroes portadas";
            }
            foreach ($smp['pages'] as $pg) foreach ($pg['blocks'] as $b) if ($b['type'] === 'testimonials') foreach ($b['data']['items'] as $it) {
                if (!preg_match('/ejempl|exampl|sample|exempl|beispiel|esempi/i', $it['name'] . ' ' . $it['role'])) $errs[] = "$l: opinión sin marcar como ejemplo";
            }
        }
        $shape = array_unique(array_map(fn($smp) => json_encode([array_map(fn($p) => array_column($p['blocks'], 'type'), $smp['pages']), count($smp['posts'] ?? [])]), $all));
        if (count($shape) !== 1) $errs[] = 'los idiomas no tienen las mismas páginas, bloques y entradas en el mismo orden (theme_sample los empareja por posición)';
        ok("$theme: textos de ejemplo válidos y sin palabras vetadas", !$errs, implode(' | ', array_slice($errs, 0, 5)));
        // Web nueva con los textos de ejemplo: cada página y entrada se pinta sin avisos
        $ns = site_create("Muestra $theme", "m-$theme", ["m-$theme.test"], $theme);
        $nsvc = new SiteService($ns);
        site_apply_theme_sample($nsvc);
        $nsvc = new SiteService(site_by_id((int) $ns['id']));
        $bad = [];
        foreach (array_merge(array_map(fn($p) => $p['slug'] === 'index' ? '/' : '/' . $p['slug'], $nsvc->pages()), ['/blog'], array_map(fn($p) => '/blog/' . $p['slug'], $nsvc->posts(['public' => true]))) as $path) {
            [$st, $html, $warn] = render($nsvc, "m-$theme.test", $path);
            if ($st !== 200 || $warn || preg_match_all('#<h1[\s>]#', $html) !== 1) $bad[] = "$path: $st " . ($warn[0] ?? '') . ' h1=' . preg_match_all('#<h1[\s>]#', $html);
        }
        ok("$theme: web nueva con sus textos de ejemplo", !$bad && strtolower($nsvc->settings()['accent']) === strtolower(theme_guide()[$theme]['suggested_accent']), implode(' | ', $bad));
    }

    // Validador de subida (por si alguien lo descarga, lo cambia y lo vuelve a subir)
    $err = '';
    try { $zip = theme_package($theme); theme_inspect($zip); } catch (Throwable $e) { $err = $e->getMessage(); } finally { if (isset($zip)) @unlink($zip); }
    ok("$theme: pasa el validador de subida", $err === '' || ($err !== '' && in_array($theme, THEMES_BUILTIN, true) && str_contains($err, 'de serie')), $err);
}

// Ficha: los temas con fuentes propias las tienen en assets/fonts con su licencia, y ninguna se pide a terceros
foreach (array_keys(themes_available()) as $theme) {
    $files = glob(UW_ROOT . "/themes/$theme/{*.php,blocks/*.php,assets/*.css}", GLOB_BRACE) ?: [];
    $ext = [];
    // Recursos que carga el navegador (src=, url(), <link href>, @import); los enlaces <a href> a redes no cuentan
    foreach ($files as $f) if (preg_match_all('#(?:\bsrc\s*=\s*|url\(\s*|@import\s+|<link\b[^>]*\bhref\s*=\s*)["\']?(?:https?:)?//(?!www\.w3\.org/2000/svg)[^"\')\s]+#i', (string) file_get_contents($f), $m)) $ext = array_merge($ext, $m[0]);
    $fonts = glob(UW_ROOT . "/themes/$theme/assets/fonts/*.woff2") ?: [];
    ok("$theme: sin recursos de terceros y fuentes con licencia", !$ext && (!$fonts || is_file(UW_ROOT . "/themes/$theme/assets/fonts/OFL.txt")), implode(' ', $ext));
}

echo $fails ? "\n$fails fallo(s)\n" : "\nTodo bien\n";
exit($fails ? 1 : 0);
