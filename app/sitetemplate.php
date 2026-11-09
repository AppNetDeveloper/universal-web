<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Plantilla HTML segura por web.
 *
 * Cada web puede tener su propio marco HTML (cabecera, pie, estructura) y su CSS, escritos a mano o por una IA vía MCP,
 * sin tocar el servidor: la plantilla es HTML con marcadores tipo Mustache que Universal Web rellena; nunca se ejecuta
 * como código. El contenido sigue saliendo de los bloques del tema, así que SEO, GEO, idiomas, cookies, formularios,
 * Markdown para IAs y publicación estática funcionan igual. Cada cambio guarda versión (se puede deshacer).
 *
 * Marcadores:
 *   {{nombre}}     texto escapado (site.name, page.title, lang, year…)
 *   {{{nombre}}}   HTML generado por Universal Web (solo los componentes de TPL_RAW; el resto se escapa igual)
 *   {{#lista}}…{{/lista}}  repite para cada elemento (menu, social, langs…) o muestra si existe
 *   {{^x}}…{{/x}}  muestra si x está vacío
 */

const TPL_LIMITS = ['layout' => 100 * 1024, 'css' => 200 * 1024];
const TPL_HISTORY_KEEP = 30;
// Componentes HTML que se pueden insertar con {{{…}}}: todos los genera Universal Web a partir de datos limpios.
const TPL_RAW = ['head', 'content', 'menu_html', 'utility_menu_html', 'lang_switch', 'logo', 'cta_html', 'contact_html', 'social_html', 'credit', 'skip_link'];

function tpl_get(int $siteId): array
{
    $r = row('SELECT * FROM site_template WHERE site_id = ?', [$siteId]);
    return $r ? ['enabled' => (bool) $r['enabled'], 'base_assets' => (bool) $r['base_assets'], 'version' => (int) $r['version']] + $r
        : ['site_id' => $siteId, 'enabled' => false, 'base_assets' => true, 'layout' => '', 'css' => '', 'version' => 0, 'updated_at' => ''];
}

function tpl_history(int $siteId, int $limit = 30): array
{
    return rows('SELECT id, version, enabled, actor_name, created_at, LENGTH(layout) AS layout_size, LENGTH(css) AS css_size FROM site_template_history
        WHERE site_id = ? ORDER BY id DESC LIMIT ' . max(1, min(100, $limit)), [$siteId]);
}

/** Comprueba una plantilla. Devuelve la lista de avisos (no bloqueantes). */
function tpl_validate(string $layout, string $css): array
{
    if (strlen($layout) > TPL_LIMITS['layout']) throw new UwError(t('La plantilla supera el máximo de {kb} KB', ['kb' => TPL_LIMITS['layout'] / 1024]));
    if (strlen($css) > TPL_LIMITS['css']) throw new UwError(t('El CSS de la plantilla supera el máximo de {kb} KB', ['kb' => TPL_LIMITS['css'] / 1024]));
    if (str_contains($layout . $css, "\0")) throw new UwError(t('El código contiene caracteres no válidos'));
    if ($layout === '') return [];
    // Solo HTML declarativo: el JavaScript va en code_update (JS de la web) y nada se interpreta en el servidor.
    $checks = [
        '/<\s*script\b/i' => t('La plantilla no puede llevar <script>: pon el JavaScript en «Diseño y código» (code_update js)'),
        '/<\?(php|=)?/i' => t('La plantilla no puede llevar código PHP'),
        '/\son[a-z]+\s*=/i' => t('La plantilla no puede llevar atributos on… (onclick, onload…): usa el JavaScript de la web'),
        '/(href|src|action|formaction)\s*=\s*["\']?\s*(javascript|vbscript|data):/i' => t('La plantilla no puede llevar enlaces javascript: ni data:'),
        '/<\s*(base|object|embed|applet)\b/i' => t('La plantilla no puede usar <base>, <object>, <embed> ni <applet>'),
        '/<\s*meta[^>]+http-equiv/i' => t('La plantilla no puede llevar <meta http-equiv>'),
    ];
    $checks['/[\s\/"\'`]on[a-z]+\s*=/i'] = $checks['/\son[a-z]+\s*=/i']; // <svg/onload=…>, x"onerror=…
    $checks['/[\s\/"\'`]srcdoc\s*=/i'] = t('La plantilla no puede llevar iframes con srcdoc');
    // Las comprobaciones se hacen también con las entidades decodificadas (&#106;avascript:) y, la de
    // esquemas, sin espacios ni caracteres de control (java&#9;script:).
    if (!preg_match('//u', $layout)) throw new UwError(t('La plantilla tiene caracteres no válidos (UTF-8)'));
    $decoded = html_entity_decode($layout, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $compact = preg_replace('/[\x00-\x20]+/', '', $decoded);
    foreach ($checks as $re => $msg) if (preg_match($re, $layout) || preg_match($re, $decoded)) throw new UwError($msg);
    if (preg_match('/(href|src|action|formaction|xlink:href)=["\']?(javascript|vbscript|data):/i', $compact)) {
        throw new UwError(t('La plantilla no puede llevar enlaces javascript: ni data:'));
    }
    if (preg_match('/<\/?\s*style\b[^>]*>.*(expression\s*\(|javascript:)/is', $layout) || preg_match('/expression\s*\(|javascript:|@import\s+url\(\s*["\']?(?!https?:|\/)/i', $css)) {
        throw new UwError(t('El CSS no puede usar expression() ni javascript:'));
    }
    if (preg_match('#</\s*style#i', $css)) throw new UwError(t('El CSS no puede contener </style>'));
    tpl_parse($layout); // errores de sintaxis de los marcadores
    // Todos los que faltan de una vez: de uno en uno, un agente necesitaba un intento por marcador (18-oct).
    $missing = [];
    foreach (['head' => '{{{head}}}', 'content' => '{{{content}}}'] as $k => $tag) {
        if (!preg_match('/\{\{\{\s*' . $k . '\s*\}\}\}/', $layout)) $missing[] = $tag;
    }
    if ($missing) throw new UwError(t('Faltan los marcadores obligatorios {tags}. Parte de starter_layout (template_get), que ya los trae, y cambia el HTML y las clases de alrededor', ['tags' => implode(' y ', $missing)]));
    $warn = [];
    $unknown = tpl_unknown_markers(tpl_parse($layout));
    if ($unknown) $warn[] = t('Estos marcadores no existen y saldrán vacíos: {list}. Los válidos están en template_get (placeholders)', ['list' => implode(', ', $unknown)]);
    if (!preg_match('/\{\{\{\s*credit\s*\}\}\}/', $layout)) $warn[] = t('No hay {{{credit}}}: el crédito se añade al final de la página (lo exige la licencia)');
    if (!preg_match('/<h1|\{\{\{\s*content/i', $layout)) $warn[] = t('Revisa que cada página tenga un solo <h1> (lo pone el contenido)');
    if (!preg_match('/\{\{\{\s*(menu_html|utility_menu_html)\s*\}\}\}|\{\{#\s*menu\s*\}\}/', $layout)) $warn[] = t('La plantilla no muestra el menú de la web');
    if (!preg_match('/<html[^>]*lang=/i', $layout)) $warn[] = t('Pon <html lang="{{lang}}"> para que buscadores y lectores de pantalla sepan el idioma');
    return $warn;
}

function tpl_save(int $siteId, array $in): array
{
    $cur = tpl_get($siteId);
    $new = $cur;
    foreach (['layout', 'css'] as $k) if (array_key_exists($k, $in)) $new[$k] = str_replace(["\r\n", "\r"], "\n", (string) $in[$k]);
    foreach (['enabled', 'base_assets'] as $k) if (array_key_exists($k, $in)) $new[$k] = filter_var($in[$k], FILTER_VALIDATE_BOOLEAN);
    $warnings = tpl_validate($new['layout'], $new['css']);
    if ($new['enabled'] && trim($new['layout']) === '') throw new UwError(t('No se puede activar una plantilla vacía: escribe primero el HTML (template_get trae un ejemplo)'));
    if ([$new['layout'], $new['css'], $new['enabled'], $new['base_assets']] === [$cur['layout'], $cur['css'], $cur['enabled'], $cur['base_assets']]) return $cur + ['warnings' => $warnings];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // También el estado inicial (sin plantilla): así «deshacer» el primer cambio vuelve a no tener plantilla.
        insert('site_template_history', ['site_id' => $siteId, 'version' => $cur['version'], 'layout' => $cur['layout'], 'css' => $cur['css'],
            'enabled' => (int) $cur['enabled'], 'base_assets' => (int) $cur['base_assets'], 'actor_name' => Actor::$name, 'created_at' => now()]);
        q('DELETE FROM site_template_history WHERE site_id = ? AND id NOT IN (SELECT id FROM site_template_history WHERE site_id = ? ORDER BY id DESC LIMIT ' . TPL_HISTORY_KEEP . ')', [$siteId, $siteId]);
        q('INSERT INTO site_template (site_id, enabled, base_assets, layout, css, version, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(site_id) DO UPDATE SET enabled = excluded.enabled, base_assets = excluded.base_assets, layout = excluded.layout, css = excluded.css,
            version = excluded.version, updated_at = excluded.updated_at',
            [$siteId, (int) $new['enabled'], (int) $new['base_assets'], $new['layout'], $new['css'], $cur['version'] + 1, now()]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
    audit($siteId, 'design.template', 'v' . ($cur['version'] + 1), ['enabled' => $new['enabled']]);
    return tpl_get($siteId) + ['warnings' => $warnings];
}

/** Deshace: sin id vuelve a la versión inmediatamente anterior. */
function tpl_restore(int $siteId, int $historyId = 0): array
{
    $h = $historyId
        ? row('SELECT * FROM site_template_history WHERE id = ? AND site_id = ?', [$historyId, $siteId])
        : row('SELECT * FROM site_template_history WHERE site_id = ? ORDER BY id DESC LIMIT 1', [$siteId]);
    if (!$h) throw new UwError(t($historyId ? 'Versión no encontrada' : 'No hay versiones anteriores: no hay nada que deshacer'));
    $r = tpl_save($siteId, ['layout' => $h['layout'], 'css' => $h['css'], 'enabled' => (bool) $h['enabled'], 'base_assets' => (bool) $h['base_assets']]);
    audit($siteId, 'design.template_restore', 'v' . $h['version']);
    return $r + ['restored_version' => (int) $h['version']];
}

// ---------------------------------------------------------------- motor de marcadores

/** Convierte la plantilla en un árbol de nodos. Lanza UwError con la línea si los marcadores están mal. */
function tpl_parse(string $src): array
{
    static $cache = [];
    $key = md5($src);
    if (isset($cache[$key])) return $cache[$key];
    preg_match_all('/\{\{\{\s*([a-z0-9_.]+)\s*\}\}\}|\{\{\s*([#^\/]?)\s*([a-z0-9_.]+)\s*\}\}/i', $src, $m, PREG_OFFSET_CAPTURE | PREG_SET_ORDER);
    $tokens = [];
    $pos = 0;
    foreach ($m as $t) {
        $at = $t[0][1];
        if ($at > $pos) $tokens[] = ['text', substr($src, $pos, $at - $pos)];
        $pos = $at + strlen($t[0][0]);
        $line = substr_count($src, "\n", 0, $at) + 1;
        if (isset($t[1]) && $t[1][1] >= 0 && $t[1][0] !== '') $tokens[] = ['raw', strtolower($t[1][0])];
        else $tokens[] = [['#' => 'open', '^' => 'openinv', '/' => 'close', '' => 'var'][$t[2][0]], strtolower($t[3][0]), $line];
    }
    if ($pos < strlen($src)) $tokens[] = ['text', substr($src, $pos)];
    $i = 0;
    $build = function (?string $until, int $openLine) use (&$build, &$i, $tokens): array {
        $nodes = [];
        while ($i < count($tokens)) {
            $t = $tokens[$i++];
            if ($t[0] === 'close') {
                if ($t[1] !== $until) throw new UwError(t('Plantilla: {{/{name}}} sin abrir en la línea {line}', ['name' => $t[1], 'line' => $t[2]]));
                return $nodes;
            }
            if ($t[0] === 'open' || $t[0] === 'openinv') { $nodes[] = [$t[0] === 'open' ? 'section' : 'inverted', $t[1], $build($t[1], $t[2])]; continue; }
            $nodes[] = $t[0] === 'var' ? ['var', $t[1]] : $t;
        }
        if ($until !== null) throw new UwError(t('Plantilla: falta cerrar {{#{name}}} (abierto en la línea {line})', ['name' => $until, 'line' => $openLine]));
        return $nodes;
    };
    return $cache[$key] = $build(null, 0);
}

/**
 * Marcadores del nivel superior que la plantilla no conoce: se pintarían vacíos sin avisar
 * ({{page_title}}, {{canonical}}…). Dentro de {{#menu}}… los campos son del elemento y no se miran.
 */
function tpl_unknown_markers(array $nodes): array
{
    $known = ['site' => ['name', 'tagline', 'logo', 'accent', 'footer_text', 'home'], 'contact' => ['email', 'phone', 'phone_link', 'address'],
        'cta' => ['label', 'url'], 'lang' => [], 'year' => [], 'path' => [], 'menu' => [], 'utility_menu' => [], 'social' => [], 'langs' => []];
    $bad = [];
    foreach ($nodes as $n) {
        if ($n[0] === 'text' || ($n[0] === 'raw' && in_array($n[1], TPL_RAW, true))) continue;
        $p = explode('.', $n[1], 2);
        if (!array_key_exists($p[0], $known) || (isset($p[1]) && !in_array($p[1], $known[$p[0]], true))) {
            $bad[] = match ($n[0]) { 'raw' => '{{{' . $n[1] . '}}}', 'section' => '{{#' . $n[1] . '}}', 'inverted' => '{{^' . $n[1] . '}}', default => '{{' . $n[1] . '}}' };
        }
    }
    return array_values(array_unique($bad));
}

function tpl_lookup(array $ctx, string $name): mixed
{
    if ($name === '.') return end($ctx);
    $parts = explode('.', $name);
    for ($i = count($ctx) - 1; $i >= 0; $i--) {
        $v = $ctx[$i];
        if (!is_array($v) || !array_key_exists($parts[0], $v)) continue;
        $v = $v[$parts[0]];
        foreach (array_slice($parts, 1) as $p) $v = is_array($v) && array_key_exists($p, $v) ? $v[$p] : null;
        return $v;
    }
    return null;
}

function tpl_render_nodes(array $nodes, array $ctx, array $raw): string
{
    $out = '';
    foreach ($nodes as $n) {
        switch ($n[0]) {
            case 'text': $out .= $n[1]; break;
            case 'raw': $out .= in_array($n[1], TPL_RAW, true) && isset($raw[$n[1]]) ? $raw[$n[1]] : e((string) (is_scalar($v = tpl_lookup($ctx, $n[1])) ? $v : '')); break;
            case 'var': $v = tpl_lookup($ctx, $n[1]); $out .= is_scalar($v) ? e((string) $v) : ''; break;
            case 'section':
                $v = tpl_lookup($ctx, $n[1]);
                if (is_array($v) && array_is_list($v)) { foreach ($v as $item) $out .= tpl_render_nodes($n[2], [...$ctx, is_array($item) ? $item : ['.' => $item]], $raw); }
                elseif (is_array($v) ? $v : ($v !== null && $v !== false && $v !== '' && $v !== 0)) $out .= tpl_render_nodes($n[2], is_array($v) ? [...$ctx, $v] : $ctx, $raw);
                break;
            case 'inverted':
                $v = tpl_lookup($ctx, $n[1]);
                if (empty($v)) $out .= tpl_render_nodes($n[2], $ctx, $raw);
                break;
        }
    }
    return $out;
}

// ---------------------------------------------------------------- render

/** Plantilla que toca usar en esta petición: la activa, o la guardada si hay vista previa firmada. */
function tpl_active(SiteService $svc): ?array
{
    $t = tpl_get($svc->id());
    if ($t['layout'] === '') return null;
    if ($t['enabled']) return $t;
    return tpl_preview_ok($svc) ? $t : null;
}

/** Arma la página completa con la plantilla HTML de la web. */
function tpl_render_page(SiteService $svc, array $tpl, string $content, string $seoHead, string $path, array $langs): string
{
    $s = $svc->settings();
    $site = $svc->site;
    $isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
    $links = fn(array $items) => array_map(fn($m) => ['label' => $m['label'], 'url' => $m['url'], 'active' => $isActive((string) $m['url']), 'active_class' => $isActive((string) $m['url']) ? 'active' : ''], $items);
    $labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp'];
    $social = array_values(array_map(fn($k, $u) => ['name' => $k, 'label' => $labels[$k] ?? $k, 'url' => $u], array_keys(array_filter($s['social'])), array_filter($s['social'])));
    $c = $s['contact'];
    $menu = $links($s['menu']);
    $util = $links($s['utility_menu']);
    $ul = fn(array $items, string $cls) => $items ? '<ul class="' . $cls . '">' . implode('', array_map(fn($m) => '<li><a href="' . e($m['url']) . '"' . ($m['active'] ? ' class="active" aria-current="page"' : '') . '>' . e($m['label']) . '</a></li>', $items)) . '</ul>' : '';
    $accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#4f46e5';

    $head = $seoHead;
    if ($tpl['base_assets']) foreach (theme_css_chain($site) as $css) $head .= '<link rel="stylesheet" href="' . e($css) . "\">\n";
    $head .= '<style>:root{--accent:' . e($accent) . "}</style>\n";
    if (trim($tpl['css']) !== '') $head .= '<link rel="stylesheet" href="/site-assets/template.css?v=' . (int) $tpl['version'] . "\">\n";
    $head .= site_code_head($svc);
    $bodyEnd = ($tpl['base_assets'] ? '<script src="' . e(theme_asset($site, 'site.js')) . "\" defer></script>\n" : '') . site_code_body_end($svc);

    $raw = [
        'head' => $head, 'content' => $content, 'credit' => uw_credit(),
        'skip_link' => '<a class="skip" href="#main">' . e(__('Saltar al contenido')) . '</a>',
        'logo' => '<a class="brand" href="' . e($svc->langPath('/')) . '">' . ($s['logo'] ? '<img src="' . e($s['logo']) . '" alt="' . e($site['name']) . '" height="40">' : '<span>' . e($site['name']) . '</span>') . '</a>',
        'menu_html' => $menu ? '<nav class="site-nav" aria-label="' . e(__('Principal')) . '">' . $ul($menu, 'menu') . '</nav>' : '',
        'utility_menu_html' => $util ? '<nav class="utility-nav">' . $ul($util, 'menu') . '</nav>' : '',
        'lang_switch' => $langs ? '<nav class="lang-switch" aria-label="' . e(__('Idioma')) . '">' . implode('', array_map(fn($l) => '<a href="' . e($l[2]) . '" hreflang="' . e($l[0]) . '" lang="' . e($l[0]) . '"' . ($l[3] ? ' aria-current="true" class="active"' : '') . ' title="' . e($l[1]) . '">' . e(strtoupper($l[0])) . '</a>', $langs)) . '</nav>' : '',
        'cta_html' => $s['cta']['label'] !== '' && $s['cta']['url'] !== '' ? '<a class="btn header-cta" href="' . e($s['cta']['url']) . '">' . e($s['cta']['label']) . '</a>' : '',
        'contact_html' => ($c['email'] || $c['phone'] || $c['address']) ? '<ul class="contact-list">'
            . ($c['email'] ? '<li><a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a></li>' : '')
            . ($c['phone'] ? '<li><a href="tel:' . e(preg_replace('/[^0-9+]/', '', $c['phone'])) . '">' . e($c['phone']) . '</a></li>' : '')
            . ($c['address'] ? '<li>' . e($c['address']) . '</li>' : '') . '</ul>' : '',
        'social_html' => $social ? '<ul class="social-list">' . implode('', array_map(fn($x) => '<li><a href="' . e($x['url']) . '" rel="noopener me" target="_blank">' . e($x['label']) . '</a></li>', $social)) . '</ul>' : '',
    ];
    $data = [
        'site' => ['name' => $site['name'], 'tagline' => $s['tagline'], 'logo' => $s['logo'], 'accent' => $accent, 'footer_text' => $s['footer_text'], 'home' => $svc->langPath('/')],
        'lang' => $svc->lang, 'year' => date('Y'), 'path' => $path,
        'menu' => $menu, 'utility_menu' => $util, 'social' => $social, 'cta' => $s['cta']['label'] !== '' ? $s['cta'] : null,
        'contact' => ['email' => $c['email'], 'phone' => $c['phone'], 'phone_link' => preg_replace('/[^0-9+]/', '', $c['phone']), 'address' => $c['address']],
        'langs' => array_map(fn($l) => ['code' => $l[0], 'name' => $l[1], 'url' => $l[2], 'active' => $l[3], 'label' => strtoupper($l[0])], $langs),
    ];
    $html = tpl_render_nodes(tpl_parse($tpl['layout']), [$data], $raw);
    // El crédito (licencia) y el JS de la web/tema/cookies van siempre, aunque la plantilla no los ponga.
    if (!preg_match('/\{\{\{\s*credit\s*\}\}\}/', $tpl['layout'])) $bodyEnd = '<footer class="uw-credit-bar">' . uw_credit() . "</footer>\n" . $bodyEnd;
    $html = stripos($html, '</body>') !== false ? (string) preg_replace('#</body>#i', $bodyEnd . '</body>', $html, 1) : $html . $bodyEnd;
    return $html;
}

// ---------------------------------------------------------------- vista previa firmada (sin activar)

function tpl_preview_link(SiteService $svc): string
{
    $exp = time() + 7200;
    return $svc->url('/?_tpl=' . $exp . '.' . substr(hash_hmac('sha256', $svc->id() . "|tpl|$exp", secret_key()), 0, 32));
}

function tpl_preview_ok(SiteService $svc): bool
{
    static $ok = [];
    if (isset($ok[$svc->id()])) return $ok[$svc->id()];
    if (Out::$capture) return $ok[$svc->id()] = false; // la publicación estática nunca usa la vista previa
    $tok = isset($_GET['_tpl']) ? (string) $_GET['_tpl'] : (string) ($_COOKIE['uw_tpl_preview'] ?? '');
    if ($tok === '' || !preg_match('/^(\d{10})\.([a-f0-9]{32})$/', $tok, $m) || (int) $m[1] < time()
        || !hash_equals(substr(hash_hmac('sha256', $svc->id() . "|tpl|{$m[1]}", secret_key()), 0, 32), $m[2])) {
        if (isset($_GET['_tpl'])) out_header('Set-Cookie: uw_tpl_preview=; Path=/; Max-Age=0; HttpOnly; SameSite=Lax', false);
        return $ok[$svc->id()] = false;
    }
    if (isset($_GET['_tpl'])) out_header('Set-Cookie: uw_tpl_preview=' . $tok . '; Path=/; Max-Age=' . ((int) $m[1] - time()) . '; HttpOnly; SameSite=Lax' . (is_https() ? '; Secure' : ''), false);
    $GLOBALS['uw_tpl_preview'] = true;
    return $ok[$svc->id()] = true;
}

/** Plantilla de partida (estructura limpia que usa las clases del tema base). */
function tpl_starter(): string
{
    return <<<'HTML'
<!doctype html>
<html lang="{{lang}}">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{{{head}}}
</head>
<body>
{{{skip_link}}}
<header class="site-header">
  <div class="container header-inner">
    {{{logo}}}
    {{{lang_switch}}}
    <nav class="site-nav" aria-label="Menú">
      <ul>
        {{#menu}}<li><a href="{{url}}" class="{{active_class}}">{{label}}</a></li>{{/menu}}
      </ul>
    </nav>
    {{{cta_html}}}
  </div>
</header>
<main id="main">{{{content}}}</main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div><strong>{{site.name}}</strong>{{#site.tagline}}<p>{{site.tagline}}</p>{{/site.tagline}}</div>
    <div>{{{contact_html}}}</div>
    <div>{{{social_html}}}</div>
  </div>
  <div class="container footer-bottom">
    <span>&copy; {{year}} {{site.name}}</span> {{{credit}}}
  </div>
</footer>
</body>
</html>
HTML;
}

/** Guía corta de marcadores (para el panel y para la IA). */
function tpl_placeholders(): array
{
    return [
        'obligatorios' => ['{{{head}}} dentro de <head> (SEO, CSS, cookies)', '{{{content}}} donde va el contenido de cada página'],
        'componentes' => ['{{{logo}}}', '{{{menu_html}}}', '{{{utility_menu_html}}}', '{{{lang_switch}}}', '{{{cta_html}}}', '{{{contact_html}}}', '{{{social_html}}}', '{{{skip_link}}}', '{{{credit}}} (obligatorio por licencia; si falta se añade al final)'],
        'datos' => ['{{site.name}}', '{{site.tagline}}', '{{site.logo}}', '{{site.home}}', '{{site.footer_text}}', '{{lang}}', '{{year}}', '{{path}}', '{{contact.email}}', '{{contact.phone}}', '{{contact.phone_link}}', '{{contact.address}}', '{{cta.label}} {{cta.url}}'],
        'listas' => ['{{#menu}}<a href="{{url}}" class="{{active_class}}">{{label}}</a>{{/menu}}', '{{#utility_menu}}…{{/utility_menu}}', '{{#social}}<a href="{{url}}">{{label}}</a>{{/social}}', '{{#langs}}<a href="{{url}}" hreflang="{{code}}">{{label}}</a>{{/langs}}'],
        'condiciones' => ['{{#site.tagline}}…{{/site.tagline}} solo si hay eslogan', '{{^site.logo}}…{{/site.logo}} solo si NO hay logo'],
        'no_permitido' => ['<script> (usa code_update js)', 'atributos on…', 'javascript: / data:', 'PHP', '<base>, <object>, <embed>, <meta http-equiv>'],
        'clases_del_contenido' => 'El contenido usa las clases del tema (section, container, card, btn, grid…). Con base_assets=true se carga el CSS/JS del tema; ponlo a false para empezar de cero con tu CSS.',
    ];
}

// ---------------------------------------------------------------- panel

function adm_template(array $c): void
{
    $svc = $c['svc'];
    $t = tpl_get($svc->id());
    if ($old = adm_old('/template')) foreach (['layout', 'css'] as $k) if (isset($old[$k])) $t[$k] = (string) $old[$k];
    adm_render('template', $c, ['title' => 'Plantilla HTML', 'tpl' => $t, 'history' => tpl_history($svc->id()), 'preview' => tpl_preview_link($svc),
        'starter' => tpl_starter(), 'help' => tpl_placeholders()]);
}

function adm_template_save(array $c): void
{
    $r = tpl_save($c['svc']->id(), ['layout' => (string) ($_POST['layout'] ?? ''), 'css' => (string) ($_POST['css'] ?? ''),
        'enabled' => !empty($_POST['enabled']), 'base_assets' => !empty($_POST['base_assets'])]);
    flash(t('Plantilla guardada (versión {v}).', ['v' => $r['version']]));
    foreach ($r['warnings'] as $w) flash($w, 'error');
    redirect(ADM . '/template');
}

function adm_template_restore(array $c): void
{
    $r = tpl_restore($c['svc']->id(), adm_id());
    flash(t('Plantilla restaurada a la versión {v}.', ['v' => $r['restored_version']]));
    redirect(ADM . '/template');
}
