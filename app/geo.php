<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * GEO (Generative Engine Optimization): que los buscadores con IA (ChatGPT, Perplexity, Gemini, Claude…)
 * entiendan la web y la citen. Incluye /llms.txt y /llms-full.txt, datos estructurados de la organización,
 * FAQPage y migas de pan, la política de rastreadores de IA en robots.txt y una auditoría (geo_audit).
 */

const GEO_BUSINESS_TYPES = [
    'Organization' => 'Organización / empresa (genérico)', 'LocalBusiness' => 'Negocio local (también transporte de mercancías y logística)',
    'MovingCompany' => 'Mudanzas (solo empresas de mudanzas, no logística)', 'Store' => 'Tienda', 'Restaurant' => 'Restaurante', 'CafeOrCoffeeShop' => 'Cafetería',
    'ProfessionalService' => 'Servicios profesionales', 'LegalService' => 'Servicios legales', 'MedicalBusiness' => 'Salud / clínica',
    'HomeAndConstructionBusiness' => 'Construcción y reformas', 'AutomotiveBusiness' => 'Automoción', 'EducationalOrganization' => 'Educación',
    'SoftwareApplication' => 'Software / app', 'NGO' => 'ONG / sin ánimo de lucro',
];

/** Rastreadores de IA conocidos: [agente, para qué]. «training» = entrena modelos; «search» = responde con citas. */
const GEO_AI_CRAWLERS = [
    ['GPTBot', 'training'], ['OAI-SearchBot', 'search'], ['ChatGPT-User', 'search'],
    ['ClaudeBot', 'training'], ['Claude-SearchBot', 'search'], ['Claude-User', 'search'],
    ['Google-Extended', 'training'], ['PerplexityBot', 'search'], ['Perplexity-User', 'search'],
    ['CCBot', 'training'], ['Applebot-Extended', 'training'], ['Meta-ExternalAgent', 'training'], ['Bytespider', 'training'],
];

const GEO_CRAWLER_POLICIES = [
    'allow_all' => 'Permitir todo (máxima visibilidad en IAs)',
    'search_only' => 'Solo buscadores con IA (citan y enlazan) — bloquear el entrenamiento de modelos',
    'block_all' => 'Bloquear todas las IAs (no aparecerás en sus respuestas)',
];

// ---------------------------------------------------------------- robots.txt

function geo_robots_txt(SiteService $svc): string
{
    $policy = $svc->settings()['ai_crawlers'];
    $out = "User-agent: *\nDisallow: /admin\nDisallow: /mcp\n\n";
    if ($policy !== 'allow_all') {
        $out .= '# Rastreadores de IA: ' . (GEO_CRAWLER_POLICIES[$policy] ?? $policy) . "\n";
        foreach (GEO_AI_CRAWLERS as [$agent, $kind]) {
            if ($policy === 'block_all' || $kind === 'training') $out .= "User-agent: $agent\nDisallow: /\n\n";
        }
    }
    return $out . "# Resumen para IAs: " . $svc->url('/llms.txt') . "\nSitemap: " . $svc->url('/sitemap.xml') . "\n";
}

// ---------------------------------------------------------------- bloques → Markdown (llms-full.txt)

function geo_plain(string $md): string
{
    return trim((string) preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(md($md)), ENT_QUOTES, 'UTF-8')));
}

function geo_block_markdown(SiteService $svc, array $b): string
{
    $d = $b['data'];
    $h = fn(string $t, int $l = 2) => $t !== '' ? str_repeat('#', $l) . " $t\n\n" : '';
    $u = fn(string $url) => abs_url($svc, $url);
    return match ($b['type']) {
        'hero' => $h($d['title'], 1) . ($d['subtitle'] ? $d['subtitle'] . "\n\n" : ''),
        'text' => $h($d['title']) . trim($d['body']) . "\n\n",
        'features', 'cards' => $h($d['title']) . ($d['subtitle'] ?? '' ? $d['subtitle'] . "\n\n" : '')
            . implode('', array_map(fn($i) => '- **' . $i['title'] . '**' . ($i['text'] ? ': ' . $i['text'] : '') . "\n", $d['items'])) . "\n",
        'stats' => implode('', array_map(fn($i) => "- {$i['value']} {$i['label']}\n", $d['items'])) . "\n",
        'steps' => $h($d['title']) . implode('', array_map(fn($k, $i) => ($k + 1) . ". **{$i['title']}**" . ($i['text'] ? ": {$i['text']}" : '') . "\n", array_keys($d['items']), $d['items'])) . "\n",
        'split' => $h($d['title']) . trim($d['body']) . "\n\n",
        'hours' => $h($d['title'] ?: __('Horario')) . implode('', array_map(fn($r) => '- ' . hours_label($r) . ': ' . (uw_truthy($r['closed'] ?? '') ? __('Cerrado')
            : implode(', ', array_filter([hours_time($r['opens']) && hours_time($r['closes']) ? hours_time($r['opens']) . '–' . hours_time($r['closes']) : '', hours_time($r['opens2']) && hours_time($r['closes2']) ? hours_time($r['opens2']) . '–' . hours_time($r['closes2']) : '']))) . "\n", $d['items']))
            . ($d['note'] !== '' ? "\n{$d['note']}\n" : '') . "\n",
        'pricelist' => $h($d['title'] ?: __('Precios')) . geo_pricelist_markdown($d),
        'faq' => $h($d['title'] ?: __('Preguntas frecuentes')) . implode('', array_map(fn($i) => "### {$i['question']}\n\n" . trim($i['answer']) . "\n\n", $d['items'])),
        'cta' => $h($d['title']) . ($d['text'] ? $d['text'] . "\n\n" : '') . ($d['button_link'] ? "[{$d['button_text']}](" . $u($d['button_link']) . ")\n\n" : ''),
        'testimonials' => implode('', array_map(fn($i) => "> {$i['quote']}\n> — {$i['name']}" . ($i['role'] ? ", {$i['role']}" : '') . "\n\n", $d['items'])),
        'image' => $d['alt'] ? "Imagen: {$d['alt']}\n\n" : '',
        'video' => $d['src'] ? 'Vídeo' . ($d['title'] ? " «{$d['title']}»" : '') . ': ' . $u($d['src']) . "\n\n" : '',
        'audio' => $d['src'] ? 'Audio' . ($d['title'] ? " «{$d['title']}»" : '') . ': ' . $u($d['src']) . "\n\n" : '',
        'listen' => $d['src'] ? __('Versión en audio') . ': ' . $u($d['src']) . "\n\n" . (trim($d['transcript']) !== '' ? trim($d['transcript']) . "\n\n" : '') : '',
        'gallery' => $h($d['title']) . implode('', array_map(fn($i) => ($i['video'] ?? '') !== '' ? '- ' . __('Vídeo') . ($i['alt'] ? ": {$i['alt']}" : '') . ' (' . $u($i['video']) . ")\n" : ($i['alt'] ? "- {$i['alt']}\n" : ''), $d['images'])) . "\n",
        'code' => $h($d['title']) . "```{$d['language']}\n{$d['code']}\n```\n\n",
        'contact_form' => $h($d['title'] ?: 'Contacto') . ($d['text'] ? $d['text'] . "\n\n" : ''),
        default => '',
    };
}

function geo_page_markdown(SiteService $svc, array $page): string
{
    $out = '';
    $hasHero = (bool) array_filter($page['blocks'], fn($b) => $b['type'] === 'hero');
    if (!$hasHero) $out .= "# {$page['title']}\n\n";
    foreach ($page['blocks'] as $b) $out .= geo_block_markdown($svc, $b);
    return trim($out) . "\n";
}

function geo_contact_lines(SiteService $svc): string
{
    $s = $svc->settings();
    $c = $s['contact'];
    $lines = [];
    if ($s['business']['legal_name']) $lines[] = '- ' . __('Razón social') . ": {$s['business']['legal_name']}";
    if ($s['business']['founded']) $lines[] = '- ' . __('En activo desde') . ": {$s['business']['founded']}";
    if ($s['business']['area_served']) $lines[] = '- ' . __('Zona de servicio') . ": {$s['business']['area_served']}";
    if ($c['address']) $lines[] = '- ' . __('Dirección') . ": {$c['address']}";
    if ($c['phone']) $lines[] = '- ' . __('Teléfono') . ": {$c['phone']}";
    if ($c['email']) $lines[] = "- Email: {$c['email']}";
    foreach (array_filter($s['social']) as $k => $url) $lines[] = '- ' . ucfirst($k) . ": $url";
    return $lines ? implode("\n", $lines) . "\n" : '';
}

/** /llms.txt: índice breve en Markdown pensado para modelos de lenguaje (llmstxt.org). */
function geo_llms_txt(SiteService $svc): string
{
    $s = $svc->settings();
    $desc = $s['seo']['description'] ?: $s['tagline'];
    $out = "# {$svc->site['name']}\n\n" . ($desc ? "> $desc\n\n" : '');
    if ($s['tagline'] && $s['tagline'] !== $desc) $out .= $s['tagline'] . "\n\n";
    $contact = geo_contact_lines($svc);
    if ($contact) $out .= '## ' . __('Datos de contacto') . "\n\n$contact\n";
    $out .= '## ' . __('Páginas') . "\n\n";
    foreach ($svc->pages(true) as $p) {
        $page = $svc->page($p['slug']);
        if ($page['seo']['noindex']) continue;
        $md = $svc->langUrl($page['slug'] === 'index' ? '/index.md' : "/{$page['slug']}.md");
        $out .= "- [{$page['title']}]($md)" . ($page['seo']['description'] ? ": {$page['seo']['description']}" : '') . "\n";
    }
    $posts = $svc->posts(['public' => true, 'limit' => 30]);
    if ($posts) {
        $out .= "\n## Blog\n\n";
        foreach ($posts as $p) if (!$p['seo']['noindex']) $out .= "- [{$p['title']}](" . $svc->langUrl("/blog/{$p['slug']}.md") . ')' . ($p['excerpt'] ? ": {$p['excerpt']}" : '') . "\n";
    }
    return $out . "\n## " . __('Opcional') . "\n\n- [" . __('Contenido completo en Markdown') . "](" . $svc->langUrl('/llms-full.txt') . ")\n- [" . __('Datos en JSON') . "](" . $svc->url('/api/site.json' . ($svc->lang !== $svc->defaultLang() ? '?lang=' . $svc->lang : '')) . ")\n" . geo_other_langs($svc);
}

/** Enlaces a llms.txt de los otros idiomas de la web. */
function geo_other_langs(SiteService $svc): string
{
    $others = array_diff($svc->liveLangs(), [$svc->lang]);
    if (!$others) return '';
    return "\n## " . __('Otros idiomas') . "\n\n" . implode('', array_map(fn($l) => '- [' . (LANG_NAMES[$l] ?? $l) . '](' . $svc->langUrl('/llms.txt', $l) . ")\n", $others));
}

/** /llms-full.txt: todo el contenido publicado en Markdown limpio. */
function geo_llms_full(SiteService $svc): string
{
    $s = $svc->settings();
    $out = "# {$svc->site['name']}\n\n" . ($s['seo']['description'] ? "> {$s['seo']['description']}\n\n" : '') . geo_contact_lines($svc) . "\n";
    foreach ($svc->pages(true) as $p) {
        $page = $svc->page($p['slug']);
        if ($page['seo']['noindex']) continue;
        $out .= "---\n\nURL: {$page['url']}\n\n" . geo_page_markdown($svc, $page) . "\n";
    }
    foreach ($svc->posts(['public' => true, 'limit' => 100]) as $p) {
        if ($p['seo']['noindex']) continue;
        $out .= "---\n\nURL: {$p['url']}\nPublicado: " . substr((string) $p['published_at'], 0, 10) . "\n\n# {$p['title']}\n\n" . ($p['excerpt'] ? "> {$p['excerpt']}\n\n" : '') . trim($p['content']) . "\n\n";
    }
    return $out;
}

// ---------------------------------------------------------------- versión Markdown de cada página (para agentes de IA)

/** Agentes de IA que leen páginas (búsqueda y entrenamiento). Los buscadores clásicos (Googlebot, Bingbot) nunca están aquí. */
function geo_is_ai_agent(): bool
{
    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') return false;
    foreach (GEO_AI_CRAWLERS as [$agent]) if (stripos($ua, $agent) !== false) return true;
    return false;
}

function geo_wants_markdown(SiteService $svc): bool
{
    if (str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'text/markdown')) return true;
    return $svc->settings()['ai_markdown'] && geo_is_ai_agent();
}

/** Markdown de una ruta del front ('/', '/servicios', '/blog/x'), o null si no existe o no es pública. */
function geo_markdown_for(SiteService $svc, string $path): ?string
{
    $s = $svc->settings();
    $head = fn(string $url) => "<!-- {$svc->site['name']} · versión Markdown para agentes de IA · HTML: $url -->\n\n";
    if (preg_match('#^/blog/([a-z0-9-]+)$#', $path, $m)) {
        $p = $svc->post($m[1]);
        if (!$p || $p['state'] !== 'published') return null;
        return $head($p['url']) . "# {$p['title']}\n\n" . __('Publicado') . ': ' . substr((string) $p['published_at'], 0, 10) . ($p['author'] ? " · {$p['author']}" : '') . "\n\n"
            . (($p['audio'] ?? '') !== '' ? __('Versión en audio') . ': ' . (str_starts_with($p['audio'], '/') ? $svc->url($p['audio']) : $p['audio']) . "\n\n" : '')
            . ($p['excerpt'] ? "> {$p['excerpt']}\n\n" : '') . trim($p['content']) . "\n";
    }
    if ($path === '/blog') {
        $out = $head($svc->langUrl('/blog')) . "# Blog · {$svc->site['name']}\n\n";
        foreach ($svc->posts(['public' => true, 'limit' => 100]) as $p) $out .= "- [{$p['title']}](" . $svc->langUrl("/blog/{$p['slug']}.md") . ')' . ($p['excerpt'] ? ": {$p['excerpt']}" : '') . "\n";
        return $out;
    }
    $slug = $path === '/' ? 'index' : ltrim($path, '/');
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) return null;
    $page = $svc->page($slug);
    if (!$page || $page['status'] !== 'published') return null;
    $out = $head($page['url']) . geo_page_markdown($svc, $page);
    if ($slug === 'index' && ($contact = geo_contact_lines($svc))) $out .= "\n## Contacto\n\n$contact";
    return $out;
}

function geo_serve_markdown(string $md): void
{
    out_header('Content-Type: text/markdown; charset=utf-8');
    out_header('Vary: Accept, User-Agent');
    echo $md;
}

// ---------------------------------------------------------------- datos estructurados (JSON-LD)

function geo_org_ld(SiteService $svc): array
{
    $s = $svc->settings();
    $type = array_key_exists($s['business']['type'], GEO_BUSINESS_TYPES) ? $s['business']['type'] : 'Organization';
    $c = $s['contact'];
    return array_filter([
        '@type' => $type, '@id' => $svc->url('/') . '#org', 'name' => $svc->site['name'], 'legalName' => $s['business']['legal_name'],
        'url' => $svc->url('/'), 'logo' => abs_url($svc, $s['logo']), 'image' => abs_url($svc, $s['seo']['image'] ?: $s['logo']),
        'description' => $s['seo']['description'] ?: $s['tagline'], 'email' => $c['email'], 'telephone' => $c['phone'],
        'address' => $c['address'] !== '' ? ['@type' => 'PostalAddress', 'streetAddress' => $c['address']] : null,
        'areaServed' => $s['business']['area_served'], 'foundingDate' => $s['business']['founded'],
        'sameAs' => array_values(array_filter($s['social'], fn($u) => $u !== '' && !str_contains((string) $u, 'wa.me'))) ?: null,
        'openingHoursSpecification' => geo_opening_hours($svc) ?: null,
    ], fn($v) => $v !== '' && $v !== null);
}

/** Horario (bloque «hours» de la portada o, si no, de cualquier página publicada) en formato schema.org. */
function geo_opening_hours(SiteService $svc): array
{
    static $cache = [];
    if (isset($cache[$svc->id()])) return $cache[$svc->id()];
    $pages = $svc->pages(true);
    usort($pages, fn($a, $b) => ($b['slug'] === 'index') <=> ($a['slug'] === 'index'));
    foreach ($pages as $meta) {
        $p = $svc->page($meta['slug']);
        foreach ($p['blocks'] ?? [] as $b) {
            if ($b['type'] !== 'hours') continue;
            $out = [];
            foreach ($b['data']['items'] as $r) {
                $days = array_map(fn($x) => 'https://schema.org/' . HOURS_DAYS[$x], hours_days((string) $r['days']));
                if (!$days || uw_truthy($r['closed'] ?? '')) continue;
                foreach ([[$r['opens'], $r['closes']], [$r['opens2'], $r['closes2']]] as [$o, $c]) {
                    if (hours_time($o) !== '' && hours_time($c) !== '') $out[] = ['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => $days, 'opens' => hours_time($o), 'closes' => hours_time($c)];
                }
            }
            if ($out) return $cache[$svc->id()] = $out;
        }
    }
    return $cache[$svc->id()] = [];
}

function geo_faq_items(array $blocks): array
{
    $out = [];
    foreach ($blocks as $b) {
        if ($b['type'] !== 'faq') continue;
        foreach ($b['data']['items'] as $i) if ($i['question'] !== '' && $i['answer'] !== '') $out[] = [$i['question'], geo_plain($i['answer'])];
    }
    return $out;
}

/**
 * Preguntas frecuentes escritas en Markdown (entradas del blog): un subtítulo ## o ### que termina en «?» y el texto que
 * le sigue hasta el siguiente subtítulo es su respuesta. Así una entrada también emite FAQPage sin bloques.
 */
function geo_faq_markdown(string $md): array
{
    $out = [];
    $parts = preg_split('/^(#{2,4})\s+(.+?)\s*#*\s*$/m', $md, -1, PREG_SPLIT_DELIM_CAPTURE);
    for ($i = 1; $i + 2 < count($parts); $i += 3) {
        $q = trim(strip_tags($parts[$i + 1]));
        $a = geo_plain(trim($parts[$i + 2]));
        if (preg_match('/\?$/u', $q) && mb_strlen($a) >= 20) $out[] = [$q, mb_substr($a, 0, 1000)];
    }
    return $out;
}

/** Grafo JSON-LD de una página: organización siempre, WebSite en la portada, FAQPage y migas de pan. */
function geo_jsonld(SiteService $svc, array $o): array
{
    $graph = [geo_org_ld($svc)];
    if (!empty($o['is_home'])) {
        $graph[] = ['@type' => 'WebSite', '@id' => $svc->url('/') . '#web', 'name' => $svc->site['name'], 'url' => $svc->url('/'),
            'inLanguage' => $svc->settings()['language'], 'publisher' => ['@id' => $svc->url('/') . '#org']];
    }
    if (!empty($o['jsonld'])) $graph[] = array_diff_key($o['jsonld'], ['@context' => 1]) + ['publisher' => ['@id' => $svc->url('/') . '#org']];
    if (!empty($o['faq'])) {
        $graph[] = ['@type' => 'FAQPage', 'mainEntity' => array_map(fn($qa) => ['@type' => 'Question', 'name' => $qa[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $qa[1]]], $o['faq'])];
    }
    if (!empty($o['breadcrumb'])) {
        $graph[] = ['@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn($k, $c) => ['@type' => 'ListItem', 'position' => $k + 1,
            'name' => $c[0], 'item' => $svc->url($c[1])], array_keys($o['breadcrumb']), $o['breadcrumb'])];
    }
    return ['@context' => 'https://schema.org', '@graph' => $graph];
}

// ---------------------------------------------------------------- auditoría GEO

function geo_audit(SiteService $svc): array
{
    $issues = [];
    $add = function (string $sev, string $where, string $msg, string $fix = '') use (&$issues) {
        $issues[] = ['severity' => $sev, 'where' => $where, 'message' => $msg, 'fix' => $fix];
    };
    $s = $svc->settings();
    $c = $s['contact'];

    // Horario: con local físico, Google y las IAs responden «¿está abierto?» con él.
    $local = $c['address'] !== '' || !in_array($s['business']['type'], ['Organization', 'SoftwareApplication', 'NGO', 'EducationalOrganization'], true);
    if ($local && !geo_opening_hours($svc)) $add('notice', 'Horario', 'No hay horario publicado: las IAs y Google no pueden decir cuándo abres', 'añade el bloque hours en la portada o en contacto (page_save)');

    // 1) Identidad de la entidad: las IAs citan a quien identifican con claridad.
    if (mb_strlen($s['seo']['description']) < 50) $add('warning', 'Ajustes', 'Falta una descripción clara de la empresa (50–160 caracteres)', 'site_update seo.description: qué hace, dónde y para quién');
    foreach (['address' => 'dirección', 'phone' => 'teléfono', 'email' => 'email'] as $k => $label) {
        if ($c[$k] === '') $add('warning', 'Ajustes', "Falta el $label de contacto", "site_update contact.$k");
    }
    if ($s['logo'] === '') $add('notice', 'Ajustes', 'Sin logo', 'site_update logo');
    if ($s['business']['type'] === 'Organization' && $c['address'] !== '') $add('notice', 'Ajustes', 'Tipo de negocio genérico: indicar uno concreto ayuda a las IAs a clasificarte', 'site_update business.type (p. ej. LocalBusiness, ProfessionalService, Restaurant; MovingCompany solo para mudanzas)');
    if ($s['business']['area_served'] === '') $add('notice', 'Ajustes', 'Sin zona de servicio (las preguntas tipo «empresa de X en Y» dependen de ella)', 'site_update business.area_served');
    if ($s['business']['founded'] === '') $add('notice', 'Ajustes', 'Sin año de fundación (dato de confianza muy citado)', 'site_update business.founded');
    if (!array_filter($s['social'])) $add('notice', 'Ajustes', 'Sin perfiles sociales (sameAs): ayudan a confirmar la identidad', 'site_update social');
    if ($s['ai_crawlers'] === 'block_all') $add('error', 'Rastreadores de IA', 'Las IAs están bloqueadas en robots.txt: no aparecerás en sus respuestas', 'site_update ai_crawlers = allow_all o search_only');

    // 2) Contenido citable: respuestas directas, datos concretos y estructura clara.
    $pages = array_filter(array_map(fn($p) => $svc->page($p['slug']), $svc->pages(true)), fn($p) => !$p['seo']['noindex']);
    $faqCount = 0; $hasFigures = false; $questionHeads = 0; $words = 0;
    foreach ($pages as $p) {
        $where = 'Página /' . ($p['slug'] === 'index' ? '' : $p['slug']);
        $md = geo_page_markdown($svc, $p);
        $w = str_word_count(strip_tags(md($md)), 0, 'áéíóúñüÁÉÍÓÚÑÜ');
        $words += $w;
        $faqCount += count(geo_faq_items($p['blocks']));
        if (preg_match('/\b\d[\d.,]*\s*(%|años|h\b|km|m²|kg|€|\+|países|clientes|delegaciones|palés)/iu', $md) || array_filter($p['blocks'], fn($b) => $b['type'] === 'stats')) $hasFigures = true;
        $questionHeads += preg_match_all('/^#{1,3} .*\?\s*$/m', $md);
        if ($w < 120) $add('notice', $where, "Poco texto ($w palabras): a las IAs les cuesta citar páginas casi vacías", 'añade un bloque de texto con detalles concretos');
        if ($p['seo']['description'] === '') $add('warning', $where, 'Sin meta descripción (resumen que muchas IAs usan)', 'page_save seo.description');
    }
    foreach (rows("SELECT content FROM posts WHERE site_id = ? AND lang = ? AND status = 'published'", [$svc->id(), $svc->lang]) as $p) $faqCount += count(geo_faq_markdown((string) $p['content']));
    if ($faqCount === 0) $add('warning', 'Contenido', 'No hay preguntas frecuentes: son el formato que más citan los buscadores con IA', 'añade un bloque «faq» con 4–8 preguntas reales de clientes');
    elseif ($faqCount < 4) $add('notice', 'Contenido', "Solo $faqCount preguntas frecuentes en toda la web", 'amplía a 4–8 preguntas por página clave');
    if (!$hasFigures) $add('notice', 'Contenido', 'No hay cifras concretas (años, clientes, plazos…): los datos verificables se citan más', 'añade un bloque «stats» o datos en el texto');

    // 3) Frescura: las IAs priorizan información actual.
    $last = (string) val('SELECT MAX(updated_at) FROM (SELECT updated_at FROM pages WHERE site_id = ? UNION ALL SELECT updated_at FROM posts WHERE site_id = ?)', [$svc->id(), $svc->id()]);
    if ($last !== '' && strtotime($last) < strtotime('-180 days')) $add('notice', 'Contenido', 'Nada actualizado en 6 meses', 'actualiza datos o publica una entrada');
    $recent = (int) val("SELECT COUNT(*) FROM posts WHERE site_id = ? AND status = 'published' AND published_at >= ?", [$svc->id(), date('Y-m-d', strtotime('-90 days'))]);
    if ($recent === 0) $add('notice', 'Blog', 'Sin entradas en los últimos 90 días', 'publica artículos que respondan dudas reales de clientes');

    $count = fn($sev) => count(array_filter($issues, fn($i) => $i['severity'] === $sev));
    $summary = ['errors' => $count('error'), 'warnings' => $count('warning'), 'notices' => $count('notice')];
    return [
        'score' => max(0, 100 - $summary['errors'] * 25 - $summary['warnings'] * 8 - $summary['notices'] * 3),
        'summary' => $summary, 'issues' => $issues,
        'stats' => ['pages' => count($pages), 'faq_questions' => $faqCount, 'question_headings' => $questionHeads, 'words' => $words, 'ai_crawlers' => $s['ai_crawlers']],
        'ai_files' => ['llms_txt' => $svc->url('/llms.txt'), 'llms_full' => $svc->url('/llms-full.txt'), 'robots' => $svc->url('/robots.txt')],
    ];
}

/** Carta / precios en Markdown, agrupada por secciones. */
function geo_pricelist_markdown(array $d): string
{
    $out = '';
    $prev = null;
    $cur = trim((string) $d['currency']) !== '' ? (string) $d['currency'] : '€';
    foreach ($d['items'] as $i) {
        if (trim((string) $i['name']) === '') continue;
        if ($i['section'] !== $prev && $i['section'] !== '') $out .= "\n### {$i['section']}\n\n";
        $prev = $i['section'];
        $price = $i['price'] === '' ? '' : ' — ' . $i['price'] . (preg_match('/^[\d.,\s]+$/', $i['price']) ? " $cur" : '');
        $out .= "- **{$i['name']}**$price" . ($i['tag'] !== '' ? " ({$i['tag']})" : '') . ($i['description'] !== '' ? ": {$i['description']}" : '') . "\n";
    }
    return $out . ($d['note'] !== '' ? "\n{$d['note']}\n" : '') . "\n";
}
