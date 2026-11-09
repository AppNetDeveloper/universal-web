<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)
//
// Regresiones de la revisión de seguridad del 18-oct-2026. Sin base de datos:
//   php tests/security_regressions.php        → código de salida 0 si todo pasa
// Cubre: campos de bloques, campos de copias remotas (FTP/SFTP), plantilla HTML y datos de formulario en sesión.

putenv('UW_DATA=' . sys_get_temp_dir() . '/uw-test-' . getmypid());
require dirname(__DIR__) . '/app/bootstrap.php';

$fails = 0;
function check(string $name, bool $expectOk, callable $fn): void
{
    global $fails;
    try { $fn(); $ok = true; $msg = ''; } catch (Throwable $e) { $ok = false; $msg = $e->getMessage(); }
    $good = $ok === $expectOk;
    if (!$good) $fails++;
    printf("%s %-46s %s\n", $good ? 'ok  ' : 'FAIL', $name, $good ? '' : ($msg ?: 'se esperaba un error'));
}

// Bloques: un campo que el tipo no tiene se rechaza (antes se guardaba el bloque vacío sin avisar).
check('bloque plano con campo desconocido', false, fn() => sanitize_blocks([['type' => 'text', 'text' => 'hola']]));
check('bloque data con campo desconocido', false, fn() => sanitize_blocks([['type' => 'text', 'data' => ['texto' => 'x']]]));
check('lista con subcampo desconocido', false, fn() => sanitize_blocks([['type' => 'steps', 'data' => ['items' => [['title' => 'a', 'descripcion' => 'x']]]]]));
check('*_html sin su campo base', false, fn() => sanitize_blocks([['type' => 'text', 'data' => ['body_html' => '<p>x</p>']]]));
check('bloque válido', true, fn() => sanitize_blocks([['type' => 'text', 'data' => ['title' => 'T', 'body' => 'hola']]]));
check('bloque válido plano', true, fn() => sanitize_blocks([['type' => 'text', 'title' => 'T', 'body' => 'hola']]));
check('leer → guardar con *_html derivado', true, fn() => sanitize_blocks([['type' => 'text', 'data' => ['body' => 'h', 'body_html' => '<p>h</p>']]]));

// 27-oct (ADRI chat-2545): el error de un campo desconocido dice a qué se refería y que no se ha guardado nada.
function error_de(callable $fn): string { try { $fn(); return ''; } catch (Throwable $e) { return $e->getMessage(); } }
$m = error_de(fn() => sanitize_blocks([['type' => 'hero', 'data' => ['title' => 'T', 'alt' => 'x', 'buttons' => [['text' => 'a']]]]]));
check('hero: alt sugiere image_alt', true, fn() => str_contains($m, "'alt' (¿quisiste decir 'image_alt'?)") ?: throw new RuntimeException($m));
check('hero: buttons dice dónde van los botones', true, fn() => str_contains($m, "'buttons' (los botones van en cta_text, cta_link, cta2_text, cta2_link)") ?: throw new RuntimeException($m));
check('el error dice que no se guardó', true, fn() => str_contains($m, 'NO se ha guardado') ?: throw new RuntimeException($m));
$m2 = error_de(fn() => sanitize_blocks([['type' => 'text', 'data' => ['titel' => 'x']]]));
check('typo cercano sugiere el campo', true, fn() => str_contains($m2, "¿quisiste decir 'title'?") ?: throw new RuntimeException($m2));

// Copias remotas: nada de órdenes en el lote de sftp ni opciones por el usuario/servidor.
$sftp = fn(array $c) => fn() => offsite_check_fields($c + ['target' => 'sftp', 'sftp_user' => 'bob', 'sftp_host' => 'h'] + offsite_defaults());
check('sftp_path con salto de línea (!comando)', false, $sftp(['sftp_path' => "b\n!id"]));
check('sftp_user empieza por - (opción de sftp)', false, $sftp(['sftp_user' => '-D/usr/bin/touch x']));
check('sftp_host empieza por -', false, $sftp(['sftp_host' => '-oProxyCommand=id']));
check('ftp_path con CRLF (orden FTP)', false, fn() => offsite_check_fields(['target' => 'ftp', 'ftp_path' => "x\r\nDELE y"] + offsite_defaults()));
check('usuario DOMINIO\\bob y host con _', true, $sftp(['sftp_user' => 'DOM\\bob', 'sftp_host' => 'nas_backup']));
check('IPv6 con zona', true, $sftp(['sftp_host' => '[fe80::1%eth0]']));
check('campo viejo de otro destino no bloquea', true, fn() => offsite_check_fields(['target' => 'ftp', 'sftp_user' => '-viejo', 'ftp_host' => 'f', 'ftp_user' => 'u'] + offsite_defaults()));

// Plantilla HTML: sin JavaScript (lista negra reforzada).
$tpl = fn(string $p) => fn() => tpl_validate('<html lang="{{lang}}"><head>{{{head}}}</head><body>' . $p . '{{{content}}}{{{credit}}}</body></html>', '');
foreach (['<svg/onload=alert(1)>', '<img src=x"onerror=alert(1)>', '<a href="&#106;avascript:alert(1)">x</a>',
          '<a href="java&#9;script:alert(1)">x</a>', '<iframe srcdoc="x"></iframe>', '<iframe/srcdoc="x"></iframe>',
          "<p>\xff</p>"] as $bad) {
    check('plantilla bloquea ' . substr($bad, 0, 30), false, $tpl($bad));
}
foreach (['<a href="/contacto">Contacto</a>', '<a href="https://example.com/?x=on&amp;y=1">x</a>',
          '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter">'] as $okHtml) {
    check('plantilla admite ' . substr($okHtml, 0, 30), true, $tpl($okHtml));
}

// Plantilla: los marcadores obligatorios que faltan salen todos en un error y los inventados avisan (18-oct).
check('faltan head y content: un solo error con los dos', true, function () {
    try { tpl_validate('<html><head><title>x</title></head><body>hola</body></html>', ''); } catch (UwError $e) {
        if (!str_contains($e->getMessage(), '{{{head}}}') || !str_contains($e->getMessage(), '{{{content}}}') || !str_contains($e->getMessage(), 'starter_layout')) throw new RuntimeException($e->getMessage());
        return;
    }
    throw new RuntimeException('no dio error');
});
check('marcadores inventados avisan; los válidos no', true, function () {
    $w = tpl_validate('<html lang="{{lang}}"><head>{{{head}}}<title>{{page_title}}</title></head><body>{{site.name}} {{site.nombre}} {{#menu}}<a href="{{url}}">{{label}}</a>{{/menu}}{{#cta}}{{label}}{{/cta}} {{{content}}}{{{credit}}}</body></html>', '');
    $all = implode(' | ', $w);
    if (!str_contains($all, '{{page_title}}') || !str_contains($all, '{{site.nombre}}') || str_contains($all, '{{url}}') || str_contains($all, '{{site.name}}')) throw new RuntimeException($all);
});
check('starter_layout no da avisos de marcadores', true, function () {
    $w = implode(' | ', tpl_validate(tpl_starter(), ''));
    if (str_contains($w, 'no existen')) throw new RuntimeException($w);
});

// MCP: un reemplazo que deja el diseño en una fracción de lo que había se para (ADRI 18-oct: CSS 6.728 → 50 bytes).
$css = str_repeat('.a{color:red}', 200);
check('MCP rechaza CSS que encoge al 1 %', false, fn() => mcp_guard_shrink(['css' => $css], ['css' => '.b{}'], ['css', 'layout']));
check('MCP: confirm_replace=true de entrada ya NO salta el freno', false, fn() => mcp_guard_shrink(['css' => $css], ['css' => '', 'confirm_replace' => true], ['css']));
check('MCP acepta el encogido con el código del rechazo', true, function () use ($css) {
    try { mcp_guard_shrink(['css' => $css], ['css' => ''], ['css']); } catch (UwError $e) {
        preg_match("/confirm_replace='([0-9a-f]{8})'/", $e->getMessage(), $m);
        mcp_guard_shrink(['css' => $css], ['css' => '', 'confirm_replace' => $m[1] ?? 'x'], ['css']);
        return;
    }
    throw new RuntimeException('no rechazó');
});
check('MCP: el código de otro recorte no vale', false, function () use ($css) {
    try { mcp_guard_shrink(['css' => $css], ['css' => 'a'], ['css']); } catch (UwError $e) {
        preg_match("/confirm_replace='([0-9a-f]{8})'/", $e->getMessage(), $m);
        mcp_guard_shrink(['css' => $css], ['css' => '', 'confirm_replace' => $m[1] ?? 'x'], ['css']);
    }
});
check('MCP acepta CSS completo editado', true, fn() => mcp_guard_shrink(['css' => $css], ['css' => $css . '.c{}'], ['css']));
check('MCP no mira campos que no se envían', true, fn() => mcp_guard_shrink(['css' => $css], ['enabled' => true], ['css', 'layout']));
check('MCP no frena una plantilla pequeña', true, fn() => mcp_guard_shrink(['css' => '.a{}'], ['css' => ''], ['css']));

check('MCP rechaza texto que llega como array', false, fn() => mcp_check_string_args(['properties' => ['css' => ['type' => 'string']]], ['css' => ['a']]));
check('MCP acepta texto y números en campo texto', true, fn() => mcp_check_string_args(['properties' => ['css' => ['type' => 'string'], 'n' => ['type' => 'string']]], ['css' => 'a', 'n' => 3]));
check('aviso de sección desconocida dice {{#…}}', true, function () {
    $w = implode(' | ', tpl_validate('<html lang="{{lang}}"><head>{{{head}}}</head><body>{{#cosas}}x{{/cosas}}{{{content}}}{{{credit}}}</body></html>', ''));
    if (!str_contains($w, '{{#cosas}}')) throw new RuntimeException($w);
});

// Horario (revisión con lentes 18-oct): medianoche, rangos con espacios o guion largo, segundos y AM/PM.
check('24:00 es medianoche (00:00), no 23:00', true, fn() => hours_time('24:00') === '00:00' || throw new RuntimeException(hours_time('24:00')));
check('09:00:00 y 9:00 PM', true, fn() => [hours_time('09:00:00'), hours_time('9:00 PM')] === ['09:00', '21:00'] || throw new RuntimeException('x'));
check('«lu - vi», «lu–vi» y «lunes a viernes» son rangos', true, function () {
    foreach (['lu - vi', "lu\u{2013}vi", 'lunes a viernes'] as $d) if (count(hours_days($d)) !== 5) throw new RuntimeException($d);
});

// Plantilla propia: carga la MISMA cadena de CSS que el layout del tema (sin la base, rejillas apiladas; 18-oct).
check('theme_css_chain: industrial = default → corporate → industrial', true, function () {
    $c = array_map(fn($u) => explode('?', $u)[0], theme_css_chain(['theme' => 'industrial']));
    if ($c !== ['/theme-assets/default/style.css', '/theme-assets/corporate/style.css', '/theme-assets/industrial/style.css']) throw new RuntimeException(json_encode($c));
});
check('theme_css_chain: corporate lleva la base y default solo una vez', true, function () {
    $c = array_map(fn($u) => explode('?', $u)[0], theme_css_chain(['theme' => 'corporate']));
    $d = array_map(fn($u) => explode('?', $u)[0], theme_css_chain(['theme' => 'default']));
    if ($c !== ['/theme-assets/default/style.css', '/theme-assets/corporate/style.css'] || $d !== ['/theme-assets/default/style.css']) throw new RuntimeException(json_encode([$c, $d]));
});
check('theme_css_chain: tema inexistente cae a default', true, fn() => count(theme_css_chain(['theme' => '../../etc'])) === 1 || throw new RuntimeException('x'));

// Marca de IA (AI Act art. 50.2) al optimizar: GD quita TODOS los metadatos (bien: GPS del móvil) y con ellos la marca
// de IA; se vuelve a escribir SOLO el XMP de IA en la imagen y sus variantes (18-oct, visto en el blog de una web real).
$uwi = function (string $kind, bool $alpha = false): string {
    $im = imagecreatetruecolor(1200, 700);
    if ($alpha) { imagealphablending($im, false); imagesavealpha($im, true); imagefill($im, 0, 0, imagecolorallocatealpha($im, 10, 20, 30, 60)); }
    $f = tempnam(sys_get_temp_dir(), 'uwt') . ".$kind";
    $kind === 'webp' ? imagewebp($im, $f, 80) : imagejpeg($im, $f, 85);
    return $f;
};
check('detecta la marca de IA de ADRI (EXIF/IPTC)', true, function () use ($uwi) {
    $f = $uwi('jpg');
    if (media_ai_detect($f)) throw new RuntimeException('falso positivo');
    file_put_contents($f, substr_replace(file_get_contents($f), "\xFF\xE1\x00\x20Exif\0\0AI-generated padding....", 2, 0));
    if (!media_ai_detect($f)) throw new RuntimeException('no la detecta');
});
foreach (['webp' => false, 'webp alfa' => true, 'jpg' => false] as $n => $alpha) {
    check("XMP de IA en $n sigue siendo una imagen válida", true, function () use ($uwi, $n, $alpha) {
        $f = $uwi(str_starts_with($n, 'webp') ? 'webp' : 'jpg', $alpha);
        if (!media_add_ai_xmp($f)) throw new RuntimeException('no lo añadió');
        $b = file_get_contents($f);
        if (!str_contains($b, 'trainedAlgorithmicMedia')) throw new RuntimeException('sin XMP');
        $i = @getimagesize($f);
        if (!$i || $i[0] !== 1200 || $i[1] !== 700) throw new RuntimeException('imagen rota: ' . json_encode($i));
        $img = str_ends_with($f, '.webp') ? @imagecreatefromwebp($f) : @imagecreatefromjpeg($f);
        if (!$img) throw new RuntimeException('GD no la abre');
        if (str_ends_with($f, '.webp') && substr($b, 4, 4) !== pack('V', strlen($b) - 8)) throw new RuntimeException('tamaño RIFF mal');
    });
}

// Sesión: un formulario que falla no deja secretos en el fichero de sesión.
check('adm_safe_post quita secretos (también anidados)', true, function () {
    $s = adm_safe_post(['title' => 'x', 'keywords' => 'a', 'sftp_key' => 'PRIV', 'settings' => ['api_key' => 'K', 'name' => 'n'], '_csrf' => 'c', 'password' => 'p']);
    if ($s !== ['title' => 'x', 'keywords' => 'a', 'settings' => ['name' => 'n']]) throw new RuntimeException(json_encode($s));
});

// Vista previa de borradores (enlace «Ver» firmado) y webs sin dominio. K = clave de prueba (sin config.php no hay secret_key()).
const K = 'clave-de-prueba';
check('draft_preview: el enlace firmado vale para su web y su ruta', true, function () {
    $u = draft_preview_href(['id' => 3], 'https://web.test/servicios/', K);
    parse_str((string) parse_url($u, PHP_URL_QUERY), $q);
    if (($q['preview'] ?? '') !== '1' || !draft_preview_valid(3, '/servicios', (string) ($q['pt'] ?? ''), K)) throw new RuntimeException($u);
});
check('draft_preview: no vale en otra web', false, function () {
    parse_str((string) parse_url(draft_preview_href(['id' => 3], '/a', K), PHP_URL_QUERY), $q);
    if (!draft_preview_valid(4, '/a', $q['pt'], K)) throw new RuntimeException('rechazado');
});
check('draft_preview: no vale en otra ruta', false, function () {
    parse_str((string) parse_url(draft_preview_href(['id' => 3], '/a', K), PHP_URL_QUERY), $q);
    if (!draft_preview_valid(3, '/b', $q['pt'], K)) throw new RuntimeException('rechazado');
});
check('draft_preview: firma alterada', false, function () {
    parse_str((string) parse_url(draft_preview_href(['id' => 3], '/a', K), PHP_URL_QUERY), $q);
    if (!draft_preview_valid(3, '/a', substr($q['pt'], 0, -1) . (substr($q['pt'], -1) === '0' ? '1' : '0'), K)) throw new RuntimeException('rechazada');
});
check('draft_preview: caducado', false, function () {
    $exp = time() - 5;
    if (!draft_preview_valid(3, '/a', $exp . '.' . draft_preview_sig(3, '/a', $exp, K), K)) throw new RuntimeException('caducado');
});
check('draft_preview: basura no pasa', false, fn() => draft_preview_valid(3, '/a', 'x', K) ?: throw new RuntimeException('rechazado'));
check('draft_preview: conserva la query previa de la URL', true, function () {
    if (!str_contains(draft_preview_href(['id' => 1], '/p?l=en', K), '/p?l=en&preview=1&pt=')) throw new RuntimeException('mal');
});
check('site_set_domains: sin dominios solo si se permite', false, fn() => site_set_domains(1, [], true));
check('normalize_domains vacío = lista vacía (web sin dominio)', true, function () {
    if (normalize_domains('') !== [] || normalize_domains("  \n ") !== []) throw new RuntimeException('no vacío');
});
check('límites de medios coherentes con la etiqueta', true, function () {
    if (MEDIA_MAX_BYTES_RAW <= MEDIA_MAX_BYTES || MEDIA_MAX_BYTES_AV <= MEDIA_MAX_BYTES_RAW) throw new RuntimeException('orden');
});

echo $fails ? "\n$fails fallo(s)\n" : "\nTodo bien\n";
exit($fails ? 1 : 0);
