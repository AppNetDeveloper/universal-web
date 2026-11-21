<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)
//
// Actualizaciones de punta a punta, con instalaciones reales en una carpeta temporal y procesos separados:
//   php tests/update_flow.php        → código de salida 0 si todo pasa (necesita git, zip y libsodium)
// Cubre: 0.3.0 → 0.4.0 (manual, como dice el CHANGELOG) sin perder datos; 0.4.0 → 0.4.1 con paquete firmado (archivos
// borrados, tema propio y tema de serie modificado intactos); firma mala y repetición rechazadas; fallo a mitad con
// vuelta atrás verificada (código y base de datos); proceso muerto mientras cambiaba archivos; sin red; botón «volver».
// Se firma con un par de claves DESECHABLE creado aquí: la clave real nunca se usa en las pruebas.

$repo = dirname(__DIR__);
$T = sys_get_temp_dir() . '/uw-update-' . getmypid();
@mkdir($T, 0700, true);
register_shutdown_function(fn() => exec('rm -rf ' . escapeshellarg($T)));
$php = escapeshellarg(PHP_BINARY);

$fails = 0;
function ok(string $name, bool $cond, string $info = ''): void
{
    global $fails;
    if (!$cond) $fails++;
    printf("%s %-60s %s\n", $cond ? 'ok  ' : 'FAIL', $name, $cond ? '' : $info);
}
/** Ejecuta una orden; devuelve [código, salida]. */
function sh(string $cmd): array
{
    exec($cmd . ' 2>&1', $out, $rc);
    return [$rc, implode("\n", $out)];
}
/** Código PHP dentro de una instalación (con su bootstrap y su carpeta de datos). */
function in_site(string $root, string $data, string $code): array
{
    global $php;
    $f = tempnam(sys_get_temp_dir(), 'uwt');
    file_put_contents($f, "<?php\nrequire " . var_export("$root/app/bootstrap.php", true) . ";\nActor::set('system', null, 'test');\n$code\n");
    $r = sh("UW_DATA=" . escapeshellarg($data) . " $php " . escapeshellarg($f));
    unlink($f);
    return $r;
}
function tree_copy(string $from, string $to): void
{
    sh('mkdir -p ' . escapeshellarg($to) . ' && cp -a ' . escapeshellarg($from) . '/. ' . escapeshellarg($to) . '/');
}

if (!function_exists('sodium_crypto_sign_keypair') || !class_exists('ZipArchive')) { echo "Falta libsodium o zip: no se puede probar\n"; exit(1); }
$kp = sodium_crypto_sign_keypair();
$keyFile = "$T/test.key";
file_put_contents($keyFile, base64_encode(sodium_crypto_sign_secretkey($kp)) . "\n");
chmod($keyFile, 0600);
$testPub = base64_encode(sodium_crypto_sign_publickey($kp));

// ---------------------------------------------------------------- 0.3.0 instalada, con contenido
$app = "$T/app";
$data = "$T/data";
@mkdir($app, 0755, true);
[$rc] = sh('git -C ' . escapeshellarg($repo) . ' archive v0.3.0 | tar -x -C ' . escapeshellarg($app));
ok('extraer v0.3.0 con git archive', $rc === 0 && is_file("$app/app/core.php"));
[$rc, $o] = sh("UW_DATA=" . escapeshellarg($data) . " $php " . escapeshellarg("$app/bin/uw") . ' install --name="Prueba" --domain=localhost --email=a@example.com --password=secreta123 --demo');
ok('instalar 0.3.0 por CLI', $rc === 0, $o);
[$rc, $o] = in_site($app, $data, '$s = new SiteService(site_by_id(1)); $s->updateSettings(["languages" => ["en"]]);
    $s->savePage(["slug" => "precios", "title" => "Precios", "status" => "published", "blocks" => [["type" => "text", "data" => ["body" => "hola"]]]]);
    $s->savePost(["slug" => "nota", "title" => "Nota", "status" => "published", "content" => "Texto"]);
    redirect_save_manual($s, "/viejo", "/precios");
    echo json_encode(["pages" => (int) val("SELECT COUNT(*) FROM pages"), "posts" => (int) val("SELECT COUNT(*) FROM posts"), "redirects" => (int) val("SELECT COUNT(*) FROM redirects"), "users" => (int) val("SELECT COUNT(*) FROM users"), "v" => UW_VERSION, "updated" => val("SELECT updated_at FROM pages WHERE slug = \'precios\'")]);');
$before = json_decode((string) substr($o, (int) strrpos($o, '{')), true);
ok('contenido creado en 0.3.0', $rc === 0 && ($before['v'] ?? '') === '0.3.0' && $before['pages'] > 0, $o);
@mkdir("$app/themes/mitema", 0755, true);
file_put_contents("$app/themes/mitema/theme.json", '{"name":"Mío"}');
file_put_contents("$app/public/media/subida-de-prueba.txt", 'imagen');

// ---------------------------------------------------------------- 0.4.0: paquete del código actual (firmado con la clave de prueba)
$dist040 = "$T/dist040";
[$rc, $o] = sh("$php " . escapeshellarg("$repo/bin/release") . ' --src=' . escapeshellarg($repo) . ' --key=' . escapeshellarg($keyFile) . ' --other-key --out=' . escapeshellarg($dist040));
$v040 = trim((string) preg_replace("/.*const UW_VERSION = '([^']+)'.*/s", '$1', (string) file_get_contents("$repo/app/core.php")));
ok("bin/release construye y firma la $v040", $rc === 0 && is_file("$dist040/universal-web-$v040.zip") && is_file("$dist040/universal-web-$v040.update.json"), $o);
ok('el paquete trae la lista de archivos y no trae tests ni datos', (function () use ($dist040, $v040) {
    $z = new ZipArchive(); $z->open("$dist040/universal-web-$v040.zip");
    $ok = $z->locateName('app/release-files.json') !== false && $z->locateName('tests/update_flow.php') === false && $z->locateName('bin/release') === false;
    $z->close(); return $ok;
})());

// Actualización manual 0.3.0 → 0.4.0 (los pasos del CHANGELOG): copia de seguridad, descomprimir encima sin tocar data/ ni media/
[$rc, $o] = sh("UW_DATA=" . escapeshellarg($data) . " $php " . escapeshellarg("$app/bin/uw") . ' backup');
ok('0.3.0: copia de seguridad antes de la actualización manual', $rc === 0, $o);
[$rc, $o] = sh('cd ' . escapeshellarg($app) . ' && unzip -oq ' . escapeshellarg("$dist040/universal-web-$v040.zip"));
ok('0.3.0 → 0.4.0: descomprimir el paquete encima', $rc === 0, $o);
[$rc, $o] = in_site($app, $data, 'echo json_encode(["pages" => (int) val("SELECT COUNT(*) FROM pages"), "posts" => (int) val("SELECT COUNT(*) FROM posts"), "redirects" => (int) val("SELECT COUNT(*) FROM redirects"), "users" => (int) val("SELECT COUNT(*) FROM users"),
    "v" => UW_VERSION, "schema" => (int) val("SELECT value FROM meta WHERE key = \'schema_version\'"), "content_at" => val("SELECT content_at FROM pages WHERE slug = \'precios\'"),
    "page" => publish_render(new SiteService(site_by_id(1)), "/precios")["status"], "old" => publish_render(new SiteService(site_by_id(1)), "/viejo")["status"], "md" => publish_render(new SiteService(site_by_id(1)), "/precios.md")["status"]]);');
$after = json_decode((string) substr($o, (int) strrpos($o, '{')), true);
ok('0.4.0: la migración pasa la base de datos al esquema 15', ($after['schema'] ?? 0) === 15 && ($after['v'] ?? '') === $v040, $o);
ok('0.4.0: no se pierde nada (páginas, entradas, redirecciones, usuarios)', $after && $after['pages'] === $before['pages'] && $after['posts'] === $before['posts'] && $after['redirects'] === $before['redirects'] && $after['users'] === $before['users'], $o);
ok('0.4.0: content_at sale de updated_at (no de «hoy»)', ($after['content_at'] ?? '') === $before['updated'], $o);
ok('0.4.0: URLs de la 0.3 siguen vivas (/precios, /viejo 301, /precios.md)', ($after['page'] ?? 0) === 200 && ($after['old'] ?? 0) === 301 && ($after['md'] ?? 0) === 200, $o);
ok('0.4.0: migrar otra vez no rompe nada', in_site($app, $data, 'migrate(); echo "ok";')[0] === 0);
ok('0.4.0: subidas y tema propio intactos', is_file("$app/public/media/subida-de-prueba.txt") && is_file("$app/themes/mitema/theme.json"));

// ---------------------------------------------------------------- paquetes 0.4.1 y 0.4.2 (fallará al arrancar)
$mk = function (string $ver, callable $tweak) use ($repo, $T, $php, $keyFile): string {
    $src = "$T/src-$ver";
    sh('mkdir -p ' . escapeshellarg($src) . ' && git -C ' . escapeshellarg($repo) . ' ls-files --cached --others --exclude-standard -z | (cd ' . escapeshellarg($repo) . ' && xargs -0 cp -a --parents -t ' . escapeshellarg($src) . ')');
    $core = (string) file_get_contents("$src/app/core.php");
    file_put_contents("$src/app/core.php", (string) preg_replace("/const UW_VERSION = '[^']+';/", "const UW_VERSION = '$ver';", $core));
    $tweak($src);
    [$rc, $o] = sh("$php " . escapeshellarg("$src/bin/release") . ' --src=' . escapeshellarg($src) . ' --key=' . escapeshellarg($keyFile) . ' --other-key --out=' . escapeshellarg("$T/dist-$ver"));
    if ($rc !== 0) echo $o, "\n";
    return "$T/dist-$ver";
};
$d041 = $mk('0.4.1', function (string $src) {
    unlink("$src/docs/THEMES.md"); // un archivo que la 0.4.1 ya no trae: debe desaparecer
    file_put_contents("$src/app/nuevo-041.txt", 'nuevo');
});
$d042 = $mk('0.4.2', function (string $src) {
    file_put_contents("$src/app/bootstrap.php", file_get_contents("$src/app/bootstrap.php") . "\nif (getenv('UW_DATA')) throw new RuntimeException('arranque roto a propósito');\n");
});
$install = fn(string $dist, string $ver, ?string $pub, string $extra = '') => in_site($app, $data, '$r = update_install_package((string) file_get_contents(' . var_export("$dist/universal-web-$ver.update.json", true) . '), '
    . var_export("$dist/universal-web-$ver.zip", true) . ', "test", false, ' . var_export($pub, true) . '); ' . $extra . ' echo json_encode($r);');
$finish = fn() => sh("UW_DATA=" . escapeshellarg($data) . " $php " . escapeshellarg("$app/bin/uw") . ' update:finish');
$version = fn() => trim((string) preg_replace("/.*const UW_VERSION = '([^']+)'.*/s", '$1', (string) file_get_contents("$app/app/core.php")));

// Firma mala: con la clave del código (no es la de prueba) y con el sobre manipulado
[$rc, $o] = $install($d041, '0.4.1', null);
ok('firma de otra clave: rechazada', $rc !== 0 && str_contains($o, 'firma') && $version() === $v040, $o);
$env = json_decode((string) file_get_contents("$d041/universal-web-0.4.1.update.json"), true);
$p = json_decode(base64_decode($env['payload']), true);
$p['sha256'] = str_repeat('0', 64);
$env['payload'] = base64_encode(json_encode($p));
file_put_contents("$T/tampered.json", json_encode($env));
[$rc, $o] = in_site($app, $data, 'update_install_package((string) file_get_contents(' . var_export("$T/tampered.json", true) . '), ' . var_export("$d041/universal-web-0.4.1.zip", true) . ', "t", false, ' . var_export($testPub, true) . ');');
ok('sobre manipulado (huella cambiada): rechazado', $rc !== 0 && str_contains($o, 'firma') && $version() === $v040, $o);
copy("$d041/universal-web-0.4.1.zip", "$T/corrupt.zip");
file_put_contents("$T/corrupt.zip", 'x', FILE_APPEND);
[$rc, $o] = in_site($app, $data, 'update_install_package((string) file_get_contents(' . var_export("$d041/universal-web-0.4.1.update.json", true) . '), ' . var_export("$T/corrupt.zip", true) . ', "t", false, ' . var_export($testPub, true) . ');');
ok('zip que no coincide con su huella firmada: rechazado', $rc !== 0 && str_contains($o, 'sha256') && $version() === $v040 && !is_file("$data/maintenance.json"), $o);

// Un tema de serie y el .htaccess modificados a mano, y la 0.4.1 de verdad
file_put_contents("$app/themes/noche/assets/style.css", "/* mío */\n", FILE_APPEND);
file_put_contents("$app/.htaccess", "# AddHandler de mi hosting (versión de PHP)\n", FILE_APPEND);
$htMine = hash_file('sha256', "$app/.htaccess");
$nocheMine = hash_file('sha256', "$app/themes/noche/assets/style.css");
[$rc, $o] = $install($d041, '0.4.1', $testPub);
ok('0.4.0 → 0.4.1: fase 1 (copia, mantenimiento, verificación, cambio de código)', $rc === 0 && is_file("$data/updates/pending.json") && is_file("$data/maintenance.json"), $o);
ok('durante la actualización la web responde 503', (function () use ($app, $data, $php) {
    [, $out] = sh("cd " . escapeshellarg("$app/public") . " && UW_DATA=" . escapeshellarg($data) . " REQUEST_URI=/ $php -r " . escapeshellarg('$_SERVER["REQUEST_URI"]="/"; require "../app/maintenance.php"; var_export(uw_maintenance_state() !== null);'));
    return str_contains($out, 'true');
})());
[$rc, $o] = $finish();
ok('0.4.1: fase 2 en un proceso nuevo (migraciones, caché, salud)', $rc === 0 && $version() === '0.4.1' && !is_file("$data/updates/pending.json") && !is_file("$data/maintenance.json"), $o);
$res = json_decode((string) @file_get_contents("$data/updates/last-result.json"), true);
ok('0.4.1: resultado guardado como correcto', !empty($res['ok']) && ($res['to'] ?? '') === '0.4.1', json_encode($res));
ok('0.4.1: el archivo retirado desaparece y el nuevo aparece', !is_file("$app/docs/THEMES.md") && is_file("$app/app/nuevo-041.txt"));
ok('0.4.1: tema de serie modificado a mano: no se toca', hash_file('sha256', "$app/themes/noche/assets/style.css") === $nocheMine && in_array('noche', (array) ($res['skipped_themes'] ?? []), true), json_encode($res['skipped_themes'] ?? null));
ok('0.4.1: .htaccess cambiado a mano: se conserva y se avisa', hash_file('sha256', "$app/.htaccess") === $htMine && in_array('.htaccess', (array) ($res['kept_files'] ?? []), true), json_encode($res['kept_files'] ?? null));
ok('0.4.1: tema propio, subidas y datos intactos', is_file("$app/themes/mitema/theme.json") && is_file("$app/public/media/subida-de-prueba.txt")
    && in_site($app, $data, 'echo (int) val("SELECT COUNT(*) FROM pages");')[1] === (string) $before['pages']);
ok('0.4.1: copia de seguridad completa creada', (bool) glob("$data/backups/uw-global-*"));

// Repetición: volver a instalar la misma versión (o una anterior) firmada
[$rc, $o] = $install($d041, '0.4.1', $testPub);
ok('repetición del mismo paquete firmado: rechazada', $rc !== 0 && str_contains($o, 'no es más nueva'), $o);

// Fallo a mitad (la 0.4.2 no arranca): vuelta atrás sola, código y base de datos
in_site($app, $data, 'q("INSERT INTO meta (key, value) VALUES (\'marca\', \'antes\')");');
[$rc, $o] = $install($d042, '0.4.2', $testPub, 'q("UPDATE meta SET value = \'durante\' WHERE key = \'marca\'");');
ok('0.4.2: fase 1 correcta (el fallo llega al arrancar)', $rc === 0 && is_file("$data/updates/pending.json"), $o);
[$rc, $o] = $finish();
$res = json_decode((string) @file_get_contents("$data/updates/last-result.json"), true);
ok('0.4.2 falla al arrancar → vuelve sola a la 0.4.1', $rc !== 0 && $version() === '0.4.1' && empty($res['ok']) && !empty($res['rolled_back']) && str_contains((string) ($res['error'] ?? ''), 'arranque roto'), $o . ' ' . json_encode($res));
ok('vuelta atrás: base de datos de antes de actualizar, sin mantenimiento', in_site($app, $data, 'echo val("SELECT value FROM meta WHERE key = \'marca\'");')[1] === 'antes' && !is_file("$data/maintenance.json") && !is_file("$data/updates/pending.json"));
ok('vuelta atrás: la web vuelve a funcionar', in_site($app, $data, 'echo publish_render(new SiteService(site_by_id(1)), "/")["status"];')[1] === '200');

// Proceso que muere mientras cambia los archivos: la siguiente petición deshace lo cambiado
[$rc, $o] = $install($d042, '0.4.2', $testPub, '$pf = UW_DATA . "/updates/pending.json"; $pd = json_decode(file_get_contents($pf), true); $pd["stage"] = "swapping"; file_put_contents($pf, json_encode($pd));');
[$rc2, $o2] = $finish();
ok('interrumpida a mitad del cambio de archivos → se deshace', $version() === '0.4.1' && !is_file("$data/updates/pending.json") && !is_file("$data/maintenance.json"), $o . $o2);

ok('de los intentos fallidos no quedan carpetas con copias de la BD', count(glob("$data/updates/2*", GLOB_ONLYDIR) ?: []) === 1);

// Quién puede terminar una actualización pendiente: quien la lanzó, o root (sudo uw update:finish); un visitante no la de root
[$rc, $o] = in_site($app, $data, 'echo json_encode([uw_update_may_finish(["uid" => 0], 33), uw_update_may_finish(["uid" => 33], 0), uw_update_may_finish(["uid" => 33], 33), uw_update_may_finish([], 33)]);');
ok('pendiente de root: el servidor web no la toca; root termina cualquiera', trim($o) === '[false,true,true,true]', $o);
file_put_contents("$data/updates/pending.json", json_encode(['id' => 'x', 'stage' => 'swapped']));
file_put_contents("$data/maintenance.json", json_encode(['since' => time(), 'until' => time() - 10]));
[, $mo] = in_site($app, $data, 'var_export(uw_maintenance_state() !== null);');
ok('con una actualización a medias el mantenimiento no caduca', trim($mo) === 'true', $mo);
unlink("$data/updates/pending.json");
unlink("$data/maintenance.json");

// Sin red: la comprobación no se cuelga ni rompe
$t0 = microtime(true);
[$rc, $o] = in_site($app, $data, '$s = update_check(true, 0, "https://no-existe.invalid/releases.json"); echo json_encode(["e" => $s["error"], "latest" => $s["latest"]]);');
$st = json_decode((string) substr($o, (int) strrpos($o, '{')), true);
ok('sin red: comprobar no falla, guarda el motivo y tarda poco', $rc === 0 && ($st['e'] ?? '') !== '' && microtime(true) - $t0 < 15, $o);
[$rc, $o] = in_site($app, $data, 'try { update_run_remote("t", false, null, "https://no-existe.invalid/releases.json"); } catch (UwError $e) { echo "ERR:" . $e->getMessage(); }');
ok('sin red: «Actualizar» explica el problema y no toca nada', str_contains($o, 'ERR:') && $version() === '0.4.1' && !is_file("$data/maintenance.json"), $o);

// Elegir versión: política «solo correcciones» y versiones sin sobre firmado
[$rc, $o] = in_site($app, $data, '$rel = fn($v, $assets = true) => ["tag_name" => "v$v", "assets" => $assets ? [["name" => "universal-web-$v.update.json", "browser_download_url" => "u"], ["name" => "universal-web-$v.zip", "browser_download_url" => "z"]] : []];
    $l = [$rel("0.4.3"), $rel("0.5.0"), $rel("0.6.0", false), ["tag_name" => "v0.9.0", "prerelease" => true] + $rel("0.9.0")];
    echo update_pick($l, "0.4.1", "patch")["version"], "|", update_pick($l, "0.4.1", "all")["version"], "|", var_export(update_pick($l, "0.5.0", "patch"), true);');
ok('update_pick: patch → 0.4.3, all → 0.5.0, sin sobre o prerelease → no', trim($o) === '0.4.3|0.5.0|NULL', $o);

// MCP: solo consulta
[$rc, $o] = in_site($app, $data, 'McpContext::$token = ["id" => 0, "site_id" => 1, "scopes" => ["read", "write", "publish"], "owner_role" => "admin", "name" => "t", "owner_name" => ""];
    $names = array_column(mcp_tools_for_token(), "name"); $r = mcp_call("update_status", []);
    echo in_array("update_status", $names, true) ? "S" : "-", preg_grep("/^update_(run|apply|install)/", $names) ? "X" : "-", $r["isError"] ? "E" : "-";');
ok('MCP: update_status existe y no hay herramienta para lanzar', trim($o) === 'S--', $o);

// Volver a la versión anterior a mano (desde la copia de la 0.4.1)
[$rc, $o] = sh("UW_DATA=" . escapeshellarg($data) . " $php " . escapeshellarg("$app/bin/uw") . ' update:rollback --yes');
ok('«Volver a la versión anterior» repone la 0.4.0 con los datos', $rc === 0 && $version() === $v040 && is_file("$app/docs/THEMES.md") && !is_file("$app/app/nuevo-041.txt")
    && in_site($app, $data, 'echo (int) val("SELECT COUNT(*) FROM pages");')[1] === (string) $before['pages'], $o);

echo $fails ? "\n$fails fallos\n" : "\nTodo bien\n";
exit($fails ? 1 : 0);
