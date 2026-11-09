<?php
/**
 * SEO/GEO «inteligente»: lo mecánico lo hace el servidor y la IA solo escribe lo que necesita criterio.
 *  - seo_autofix(): rellena lo que está vacío (alt de imágenes, meta descripciones, extractos, portadas de entradas,
 *    descripción de la web) a partir del propio contenido y de la biblioteca de medios. Nunca sobrescribe nada escrito.
 *  - seo_bulk_update(): títulos/descripciones de muchas páginas y entradas en una sola llamada.
 *  - seo_analyze(): informe a fondo de UNA página (vista en Google, palabra clave, encabezados, enlaces, datos
 *    estructurados, legibilidad y GEO) a partir del HTML real que ve un buscador.
 *  - IndexNow: avisa a Bing/Yandex/Seznam (Bing alimenta ChatGPT y Copilot) de cada página publicada o cambiada.
 */

declare(strict_types=1);

const SEO_DESC_MAX = 155;

/** Recorta un texto a $max caracteres sin partir palabras; prefiere terminar en punto. */
function seo_snippet(string $text, int $max = SEO_DESC_MAX): string
{
    $t = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
    if (mb_strlen($t) <= $max) return $t;
    $cut = mb_substr($t, 0, $max);
    $dot = max((int) mb_strrpos($cut, '. '), (int) mb_strrpos($cut, '! '), (int) mb_strrpos($cut, '? '));
    if ($dot >= 70) return mb_substr($cut, 0, $dot + 1);
    $sp = mb_strrpos($cut, ' ');
    return rtrim(mb_substr($cut, 0, $sp ?: $max), ' ,;:-—') . '…';
}

/** Texto plano de una página (sin el título repetido), el mismo que leen las IAs. */
function seo_page_plain(SiteService $svc, array $page): string
{
    // Solo párrafos: los títulos de sección pegados al texto («Servicios Reparamos…») no sirven como resumen
    $hero = (string) (array_values(array_filter($page['blocks'], fn($b) => $b['type'] === 'hero'))[0]['data']['subtitle'] ?? '');
    $parts = $hero !== '' ? [trim($hero)] : [];
    foreach (preg_split('/\n\s*\n/', geo_page_markdown($svc, $page)) as $para) {
        $para = trim($para);
        if ($para === '' || preg_match('/^(#{1,6}\s|[-*>|]|!\[|\d+\.\s)/', $para)) continue;
        $t = geo_plain($para);
        if ($t === '' || in_array($t, $parts, true)) continue;
        $parts[] = preg_match('/[.!?…:]$/u', $t) ? $t : "$t.";
    }
    return implode(' ', $parts);
}

/** Nombre de archivo legible como texto alternativo («taller-fachada-a1b2c3.webp» → «Taller fachada»), o '' si es un nombre de cámara o aleatorio. */
function seo_alt_from_filename(string $url): string
{
    $n = pathinfo((string) parse_url($url, PHP_URL_PATH), PATHINFO_FILENAME);
    $n = preg_replace('/-[0-9a-f]{6}$/', '', $n);
    $n = trim((string) preg_replace('/[-_.]+/', ' ', $n));
    if (preg_match('/^(img|image|imagen|dsc|dscn|pxl|photo|foto|screenshot|captura|untitled|file|archivo|whatsapp|ai|grok|sample)\b/i', $n)) return '';
    $words = array_filter(explode(' ', $n), fn($w) => preg_match('/^\p{L}{2,20}$/u', $w));
    if (count($words) < 2 || count($words) < count(explode(' ', $n)) - 1) return '';
    return mb_strtoupper(mb_substr($n, 0, 1)) . mb_substr($n, 1);
}

/** Mejor texto alternativo disponible sin inventar: biblioteca de medios → contexto del bloque → nombre del archivo. */
function seo_alt_for(SiteService $svc, string $url, string $context = ''): string
{
    if ($url === '') return '';
    if (preg_match('#^/media/(.+)$#', (string) parse_url($url, PHP_URL_PATH), $m)) {
        $row = row('SELECT alt, title FROM media WHERE site_id = ? AND path = ?', [$svc->id(), $m[1]]);
        if ($row && trim((string) $row['alt']) !== '') return trim((string) $row['alt']);
        if ($row && trim((string) $row['title']) !== '') return trim((string) $row['title']);
    }
    if (trim($context) !== '') return mb_substr(trim(strip_tags($context)), 0, 120);
    return seo_alt_from_filename($url);
}

/** Rellena los alt vacíos de una lista de bloques. Devuelve [bloques, cambios, imágenes sin alt posible]. */
function seo_fill_alts(SiteService $svc, array $blocks): array
{
    $changes = []; $missing = [];
    foreach ($blocks as $i => &$b) {
        $d = &$b['data'];
        $pairs = $b['type'] === 'image' ? [['src', 'alt', $d['caption'] ?? '']] : [['image', 'image_alt', $b['type'] === 'hero' ? ($d['title'] ?? '') : '']];
        foreach ($pairs as [$img, $alt, $ctx]) {
            if (!array_key_exists($alt, $d) || trim((string) ($d[$img] ?? '')) === '' || trim((string) $d[$alt]) !== '') continue;
            $v = seo_alt_for($svc, (string) $d[$img], (string) $ctx) ?: ($b['type'] === 'split' ? (string) ($d['title'] ?? '') : '');
            if ($v !== '') { $d[$alt] = $v; $changes[] = "bloque #$i ($b[type]): alt «{$v}»"; }
            else $missing[] = ['block' => $i, 'type' => $b['type'], 'image' => $d[$img]];
        }
        foreach (['images', 'items'] as $list) {
            if (!is_array($d[$list] ?? null)) continue;
            foreach ($d[$list] as $j => &$it) {
                if (!is_array($it) || !array_key_exists('alt', $it) || trim((string) $it['alt']) !== '') continue;
                $src = (string) ($it['src'] ?? $it['image'] ?? '');
                if ($src === '') continue;
                $v = seo_alt_for($svc, $src, (string) ($it['title'] ?? $it['name'] ?? $it['caption'] ?? ''));
                if ($v !== '') { $it['alt'] = $v; $changes[] = "bloque #$i ($b[type]) elemento $j: alt «{$v}»"; }
                else $missing[] = ['block' => $i, 'type' => $b['type'], 'item' => $j, 'image' => $src];
            }
            unset($it);
        }
        unset($d);
    }
    unset($b);
    return [$blocks, $changes, $missing];
}

/**
 * Arregla solo lo mecánico y vacío. $canEdit(status) dice si este token puede tocar contenido en ese estado.
 * Todo se guarda con savePage/savePost: queda en el historial y se deshace con page_restore / post_restore.
 */
function seo_autofix(SiteService $svc, bool $apply, callable $canEdit): array
{
    $changes = []; $needs = []; $skipped = [];
    $orig = $svc->lang;
    foreach ($svc->langs() as $lang) {
        $svc->setLang($lang);
        foreach ($svc->pages(false) as $meta) {
            $p = $svc->page($meta['slug']);
            if (!$p || $p['seo']['noindex']) continue;
            $where = 'Página ' . $svc->langPath($p['slug'] === 'index' ? '/' : '/' . $p['slug'], $lang);
            $blocks = array_map(fn($b) => ['type' => $b['type'], 'data' => array_filter($b['data'], fn($k) => !str_ends_with((string) $k, '_html'), ARRAY_FILTER_USE_KEY)], $p['blocks']);
            [$blocks, $alts, $missing] = seo_fill_alts($svc, $blocks);
            $in = [];
            $mine = [];
            if ($alts) { $in['blocks'] = $blocks; $mine = array_merge($mine, $alts); }
            foreach ($missing as $m) $needs[] = ['where' => $where, 'what' => 'alt', 'detail' => $m, 'how' => 'page_patch update con el alt (describe la imagen en 5–12 palabras)'];
            if ($p['seo']['description'] === '') {
                $plain = seo_page_plain($svc, $p);
                if (mb_strlen($plain) >= 50) {
                    $in['seo']['description'] = seo_snippet($plain);
                    $mine[] = 'meta descripción «' . $in['seo']['description'] . '»';
                } else {
                    $needs[] = ['where' => $where, 'what' => 'description', 'how' => 'seo_bulk_update: escribe 120–155 caracteres (qué ofrece, dónde, para quién)', 'context' => mb_substr($p['title'] . '. ' . $plain, 0, 300)];
                }
            }
            $title = $p['seo']['title'] ?: $p['title'];
            if (mb_strlen($title) > 60) $needs[] = ['where' => $where, 'what' => 'title', 'how' => 'seo_bulk_update seo_title de 50–60 caracteres (lo importante al principio)', 'context' => $title];
            if (!$in) continue;
            if (!$canEdit($p['status'])) { $skipped[] = "$where (publicada: el token necesita 'publish')"; continue; }
            if ($apply) $svc->savePage($in, (int) $p['id']);
            $changes[] = ['where' => $where, 'changes' => $mine];
        }
        foreach (rows('SELECT slug FROM posts WHERE site_id = ? AND lang = ? ORDER BY id', [$svc->id(), $lang]) as $meta) {
            $p = $svc->post($meta['slug']);
            if (!$p || $p['seo']['noindex']) continue;
            $where = 'Entrada ' . $svc->langPath('/blog/' . $p['slug'], $lang);
            $in = []; $mine = [];
            $plain = trim((string) preg_replace('/\s+/u', ' ', strip_tags(md($p['content']))));
            if ($p['excerpt'] === '' && $p['seo']['description'] === '') {
                if (mb_strlen($plain) >= 50) { $in['excerpt'] = seo_snippet($plain); $mine[] = 'extracto «' . $in['excerpt'] . '»'; }
                else $needs[] = ['where' => $where, 'what' => 'excerpt', 'how' => 'post_save excerpt', 'context' => $p['title']];
            }
            if ($p['cover_image'] === '' && preg_match('/!\[[^\]]*\]\((\/media\/[^)\s]+)/', $p['content'], $m)) {
                $in['cover_image'] = $m[1]; $mine[] = 'portada ' . $m[1] . ' (primera imagen del texto)';
            } elseif ($p['cover_image'] === '') {
                $needs[] = ['where' => $where, 'what' => 'cover_image', 'how' => 'genera una imagen propia, media_upload y post_save cover_image (se usa al compartir y en Discover)'];
            }
            // Imágenes del Markdown sin alt: ![](/media/x.webp)
            $content = preg_replace_callback('/!\[\]\(([^)\s]+)\)/', function ($m) use ($svc, &$mine, &$needs, $where) {
                $alt = seo_alt_for($svc, $m[1]);
                if ($alt === '') { $needs[] = ['where' => $where, 'what' => 'alt', 'detail' => ['image' => $m[1]], 'how' => 'post_save content con ![descripción](url)']; return $m[0]; }
                $mine[] = "alt «{$alt}» en el texto";
                return '![' . str_replace([']', '['], '', $alt) . '](' . $m[1] . ')';
            }, $p['content']);
            if ($content !== $p['content']) $in['content'] = $content;
            $title = $p['seo']['title'] ?: $p['title'];
            if (mb_strlen($title) > 60) $needs[] = ['where' => $where, 'what' => 'title', 'how' => 'seo_bulk_update seo_title de 50–60 caracteres', 'context' => $title];
            if (mb_strlen($plain) < 300) $needs[] = ['where' => $where, 'what' => 'content', 'how' => 'amplía el texto (mín. 300 caracteres; mejor 600+ palabras con datos concretos y subtítulos ##)'];
            if (!$in) continue;
            if (!$canEdit($p['status'])) { $skipped[] = "$where (publicada: el token necesita 'publish')"; continue; }
            if ($apply) $svc->savePost($in, (int) $p['id']);
            $changes[] = ['where' => $where, 'changes' => $mine];
        }
    }
    $svc->setLang($orig);

    // Descripción de la web (por idioma) a partir de la portada de ese idioma
    $base = $svc->baseSettings();
    foreach ($svc->langs() as $i => $lang) {
        $cur = $i === 0 ? $base['seo']['description'] : (string) ($base['i18n'][$lang]['seo']['description'] ?? '');
        if ($cur !== '') continue;
        $svc->setLang($lang);
        $home = $svc->page('index');
        $desc = $home ? ($home['seo']['description'] ?: seo_snippet(seo_page_plain($svc, $home))) : '';
        $svc->setLang($orig);
        if (mb_strlen($desc) < 50) { $needs[] = ['where' => "Ajustes ($lang)", 'what' => 'site_description', 'how' => $i === 0 ? 'site_update seo.description' : "site_update translations.$lang.seo.description"]; continue; }
        if (!$canEdit('published')) { $skipped[] = "Ajustes ($lang) (necesita 'publish')"; continue; }
        if ($apply) $svc->updateSettings($i === 0 ? ['seo' => ['description' => $desc]] : ['translations' => [$lang => ['seo' => ['description' => $desc]]]]);
        $changes[] = ['where' => "Ajustes ($lang)", 'changes' => ["descripción de la web «{$desc}»"]];
    }
    // Biblioteca: alt de los archivos que no lo tienen (sirve para todas las páginas que los usen después)
    foreach (rows("SELECT id, path, title FROM media WHERE site_id = ? AND alt = '' AND kind = 'image'", [$svc->id()]) as $m) {
        $alt = trim((string) $m['title']) ?: seo_alt_from_filename($m['path']);
        if ($alt === '') continue;
        if ($apply) q('UPDATE media SET alt = ? WHERE id = ?', [mb_substr($alt, 0, 500), $m['id']]);
        $changes[] = ['where' => 'Biblioteca', 'changes' => ["/media/{$m['path']}: alt «{$alt}»"]];
    }
    $dup = array_values(array_filter($svc->seoAudit()['issues'], fn($x) => str_starts_with($x['message'], 'Título duplicado')));
    foreach ($dup as $d) $needs[] = ['where' => $d['where'], 'what' => 'duplicate_title', 'how' => 'seo_bulk_update: un seo_title distinto en cada una', 'context' => $d['message']];
    return [
        'applied' => $apply, 'changes' => $changes, 'skipped' => $skipped, 'needs_writing' => $needs,
        'hint' => $needs ? 'No inventes datos (contacto, cifras, años, ubicaciones): lo que no esté en la web, pídeselo a la persona. Lo de needs_writing necesita criterio: escríbelo y guárdalo todo de una vez con seo_bulk_update (o page_patch para un alt). Las descripciones automáticas son un punto de partida: mejóralas si puedes.' : 'Nada pendiente que necesite redacción.',
    ];
}

/** Aplica títulos/descripciones SEO a muchas páginas y entradas. Cada elemento falla por separado. */
function seo_bulk_update(SiteService $svc, array $items, callable $canEdit): array
{
    $out = [];
    $orig = $svc->lang;
    foreach (array_values($items) as $n => $it) {
        try {
            if (!is_array($it)) throw new UwError('debe ser un objeto');
            $kind = ($it['type'] ?? 'page') === 'post' ? 'post' : 'page';
            $lang = (string) ($it['lang'] ?? $orig);
            if (!in_array($lang, $svc->langs(), true)) throw new UwError("idioma «{$lang}» no existe en la web");
            $svc->setLang($lang);
            $slug = slugify((string) ($it['slug'] ?? ''));
            $cur = $kind === 'post' ? $svc->post($slug) : $svc->page($slug);
            if (!$cur) throw new UwError("no existe " . ($kind === 'post' ? 'la entrada' : 'la página') . " «{$slug}» en «{$lang}»");
            if (!$canEdit($cur['status'])) throw new UwError("está publicada y el token no tiene 'publish'");
            $seo = [];
            foreach (['seo_title' => 'title', 'description' => 'description', 'image' => 'image', 'noindex' => 'noindex'] as $k => $f) if (array_key_exists($k, $it)) $seo[$f] = $it[$k];
            if (isset($seo['description']) && (mb_strlen(trim((string) $seo['description'])) < 50 || mb_strlen(trim((string) $seo['description'])) > 160)) throw new UwError('description debe tener 50–160 caracteres (tiene ' . mb_strlen(trim((string) $seo['description'])) . ')');
            $in = $seo ? ['seo' => $seo] : [];
            if ($kind === 'post' && array_key_exists('excerpt', $it)) $in['excerpt'] = $it['excerpt'];
            if (!$in) throw new UwError('nada que cambiar (seo_title, description, image, noindex' . ($kind === 'post' ? ', excerpt' : '') . ')');
            $kind === 'post' ? $svc->savePost($in, (int) $cur['id']) : $svc->savePage($in, (int) $cur['id']);
            $out[] = ['item' => $n, 'ok' => true, 'where' => "$kind $lang/$slug"];
        } catch (UwError $e) {
            $out[] = ['item' => $n, 'ok' => false, 'error' => $e->getMessage()];
        }
    }
    $svc->setLang($orig);
    return ['results' => $out, 'ok' => count(array_filter($out, fn($r) => $r['ok'])), 'failed' => count(array_filter($out, fn($r) => !$r['ok']))];
}

/** Tipos de schema.org presentes en los JSON-LD de un HTML. */
function seo_jsonld_types(string $html): array
{
    $types = [];
    preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $m);
    foreach ($m[1] as $json) {
        $data = json_decode(html_entity_decode($json), true);
        if (!is_array($data)) { $types[] = 'JSON-LD NO VÁLIDO'; continue; }
        array_walk_recursive($data, function ($v, $k) use (&$types) { if ($k === '@type') $types[] = $v; });
    }
    return array_values(array_unique($types));
}

/** Informe SEO + GEO de una página o entrada, con lo que ve de verdad un buscador. */
function seo_analyze(SiteService $svc, string $kind, string $slug, string $keyword = ''): array
{
    $item = $kind === 'post' ? $svc->post($slug) : $svc->page($slug);
    if (!$item) throw new UwError(($kind === 'post' ? 'No existe la entrada' : 'No existe la página') . " «{$slug}» en «{$svc->lang}»");
    $path = $svc->langPath($kind === 'post' ? '/blog/' . $item['slug'] : ($item['slug'] === 'index' ? '/' : '/' . $item['slug']), $svc->lang);
    $lang = $svc->lang;
    $r = site_check_render($svc, $path . ($item['status'] === 'published' ? '' : '?preview=1'));
    $svc->setLang($lang);
    $html = $r['body'];
    $issues = [];
    $add = function (string $sev, string $msg, string $fix = '') use (&$issues) { $issues[] = ['severity' => $sev, 'message' => $msg, 'fix' => $fix]; };

    $meta = fn(string $re) => preg_match($re, $html, $m) ? html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5) : '';
    $title = $meta('#<title>(.*?)</title>#is');
    $desc = $meta('#<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)#i');
    $canonical = $meta('#<link\s+rel=["\']canonical["\']\s+href=["\']([^"\']*)#i');
    $ogImage = $meta('#<meta\s+property=["\']og:image["\']\s+content=["\']([^"\']*)#i');
    $robots = $meta('#<meta\s+name=["\']robots["\']\s+content=["\']([^"\']*)#i');
    $hreflang = preg_match_all('#<link\s+rel=["\']alternate["\']\s+hreflang=#i', $html);
    $main = preg_match('#<main\b[^>]*>(.*)</main>#is', $html, $mm) ? $mm[1] : $html;
    $main = preg_replace('#<(script|style|noscript)\b.*?</\1>#is', '', $main);
    preg_match_all('#<h([1-3])\b[^>]*>(.*?)</h\1>#is', $main, $hm, PREG_SET_ORDER);
    $headings = array_map(fn($h) => ['level' => (int) $h[1], 'text' => trim(html_entity_decode(strip_tags($h[2])))], $hm);
    $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('#<(br|p|li|h\d|div)\b#i', ' <$1', $main)))));
    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    preg_match_all('#<img\b[^>]*>#i', $main, $im);
    $noAlt = count(array_filter($im[0], fn($t) => !preg_match('/\balt=["\'][^"\']+/i', $t)));
    preg_match_all('#<a\b[^>]*href=["\']([^"\']+)#i', $main, $am);
    $internal = 0; $external = 0;
    foreach ($am[1] as $u) {
        if (str_starts_with($u, '#') || str_starts_with($u, 'mailto:') || str_starts_with($u, 'tel:')) continue;
        (str_starts_with($u, '/') && !str_starts_with($u, '//')) || site_check_same_host($svc, $u) ? $internal++ : $external++;
    }
    $types = seo_jsonld_types($html);
    $sentences = array_filter(preg_split('/[.!?]+\s/u', $text), fn($s) => trim($s) !== '');
    $avgSentence = $sentences ? round(count($words) / count($sentences), 1) : 0;

    // Básico
    if ($r['status'] !== 200) $add('error', "La página responde HTTP {$r['status']}");
    if ($robots !== '' && str_contains($robots, 'noindex')) $add('warning', 'Tiene noindex: Google no la mostrará', 'seo_bulk_update noindex=false si debe aparecer');
    $tl = mb_strlen($title);
    if ($tl < 30) $add('notice', "Título corto ($tl caracteres): hay sitio para la palabra clave y la zona", 'seo_bulk_update seo_title (50–60)');
    if ($tl > 60) $add('warning', "Título largo ($tl caracteres): Google lo cortará", 'seo_bulk_update seo_title (50–60)');
    $dl = mb_strlen($desc);
    if ($dl === 0) $add('warning', 'Sin meta descripción', 'seo_autofix o seo_bulk_update description');
    elseif ($dl < 70 || $dl > 160) $add('notice', "Meta descripción de $dl caracteres (ideal 120–155)", 'seo_bulk_update description');
    $h1 = array_values(array_filter($headings, fn($h) => $h['level'] === 1));
    if (count($h1) !== 1) $add('warning', count($h1) . ' encabezados H1 (debe haber exactamente uno: el título del bloque Portada o de la entrada)');
    if (!array_filter($headings, fn($h) => $h['level'] === 2) && count($words) > 250) $add('notice', 'Texto largo sin subtítulos H2: cuesta leerlo y que las IAs extraigan respuestas', 'añade title a los bloques o ## en el texto');
    if (count($words) < 300) $add('notice', 'Solo ' . count($words) . ' palabras: las páginas que posicionan suelen tener 300+ con datos concretos', 'amplía con un bloque text, faq o features');
    if ($noAlt) $add('warning', "$noAlt imagen(es) sin texto alternativo", 'seo_autofix (rellena lo que pueda) o page_patch');
    if ($internal < 2) $add('notice', "Pocos enlaces internos ($internal): enlaza a servicios, contacto o entradas relacionadas", 'botones cta_link o enlaces en el texto');
    if ($ogImage === '') $add('notice', 'Sin imagen para compartir (og:image)', 'seo_bulk_update image o una imagen en la portada');
    if ($avgSentence > 25) $add('notice', "Frases largas (media $avgSentence palabras): mejor menos de 20", 'divide frases');
    if (count($svc->langs()) > 1 && $hreflang === 0) $add('notice', 'Sin versiones en otros idiomas (hreflang)', 'translation_status');

    // Palabra clave
    $kw = null;
    if (($k = mb_strtolower(trim($keyword))) !== '') {
        $low = mb_strtolower($text);
        $count = mb_substr_count($low, $k);
        $first = mb_strtolower(implode(' ', array_slice($words, 0, 100)));
        $kw = [
            'keyword' => $keyword,
            'in_title' => str_contains(mb_strtolower($title), $k),
            'in_description' => str_contains(mb_strtolower($desc), $k),
            'in_h1' => $h1 && str_contains(mb_strtolower($h1[0]['text']), $k),
            'in_h2' => (bool) array_filter($headings, fn($h) => $h['level'] === 2 && str_contains(mb_strtolower($h['text']), $k)),
            'in_first_100_words' => str_contains($first, $k),
            'in_url' => str_contains($item['slug'], slugify($keyword)),
            'in_image_alt' => (bool) array_filter($im[0], fn($t) => str_contains(mb_strtolower(html_entity_decode($t)), $k)),
            'count' => $count,
            'density_pct' => $words ? round($count * count(preg_split('/\s+/u', $k)) / count($words) * 100, 2) : 0,
        ];
        foreach (['in_title' => 'el título SEO', 'in_description' => 'la meta descripción', 'in_h1' => 'el H1', 'in_first_100_words' => 'las primeras 100 palabras'] as $f => $label) {
            if (!$kw[$f]) $add('warning', "La palabra clave «{$keyword}» no aparece en $label");
        }
        if (!$kw['in_h2']) $add('notice', "La palabra clave no aparece en ningún H2");
        if ($kw['density_pct'] > 3) $add('warning', "Palabra clave repetida en exceso ({$kw['density_pct']} %): suena forzado y penaliza", 'usa sinónimos');
        elseif ($count < 2 && count($words) > 200) $add('notice', 'La palabra clave aparece muy poco en el texto');
    }

    // GEO: lo que hace que una IA cite la página
    $faq = $kind === 'page' ? count(geo_faq_items($item['blocks'])) : count(geo_faq_markdown($item['content']));
    $questions = count(array_filter($headings, fn($h) => str_ends_with($h['text'], '?')));
    $figures = preg_match_all('/\b\d[\d.,]*\s*(%|años|years|ani|h\b|km|m²|kg|€|\$|\+|clientes|clients|proyectos)/iu', $text);
    if ($faq === 0) $add('notice', 'Sin preguntas y respuestas: es lo que más citan ChatGPT, Perplexity y Google AI Overviews', $kind === 'page' ? 'bloque faq con 4–8 preguntas reales' : 'en el texto: «## ¿Pregunta?» y debajo la respuesta (se convierte solo en FAQPage)');
    if ($figures === 0) $add('notice', 'Sin cifras concretas (años, plazos, precios, clientes): los datos verificables se citan más');
    if (!array_intersect($types, ['Organization', 'LocalBusiness', 'WebSite', 'WebPage', 'BlogPosting', 'Article']) && !preg_grep('/Business|Service|Store|Restaurant|Organization/', $types)) $add('warning', 'Sin datos estructurados de la entidad (JSON-LD)');
    if (in_array('JSON-LD NO VÁLIDO', $types, true)) $add('error', 'Hay un JSON-LD que no se puede leer (código personalizado o plantilla)');

    $sev = fn($s) => count(array_filter($issues, fn($i) => $i['severity'] === $s));
    $url = $svc->url($path);
    return [
        'url' => $url,
        'score' => max(0, 100 - $sev('error') * 25 - $sev('warning') * 8 - $sev('notice') * 3),
        'serp_preview' => [
            'title' => mb_strlen($title) > 60 ? mb_substr($title, 0, 57) . '…' : $title,
            'url' => preg_replace('#^https?://#', '', rtrim($url, '/')),
            'description' => mb_strlen($desc) > 158 ? mb_substr($desc, 0, 155) . '…' : $desc,
        ],
        'facts' => [
            'title_length' => $tl, 'description_length' => $dl, 'words' => count($words), 'avg_sentence_words' => $avgSentence,
            'headings' => array_slice($headings, 0, 30), 'images' => count($im[0]), 'images_without_alt' => $noAlt,
            'links_internal' => $internal, 'links_external' => $external, 'canonical' => $canonical, 'hreflang_versions' => $hreflang,
            'structured_data' => $types, 'og_image' => $ogImage, 'faq_questions' => $faq, 'question_headings' => $questions, 'figures' => $figures,
            'markdown_for_ai' => $svc->url(($path === '/' ? '/index' : rtrim($path, '/')) . '.md'),
        ],
        'keyword' => $kw,
        'issues' => $issues,
    ];
}

// ---------------------------------------------------------------- IndexNow

/** Clave IndexNow de la web: fija y derivada del secreto (no hay que guardarla). Se sirve en /<clave>.txt. */
function indexnow_key(SiteService $svc): string
{
    return substr(hash_hmac('sha256', 'indexnow|' . $svc->id(), (string) config('secret', 'uw')), 0, 32);
}

/** ¿Merece la pena avisar? No en local, IPs, dominios de pruebas ni webs privadas o sin indexar. */
function indexnow_enabled(SiteService $svc): bool
{
    $s = $svc->settings();
    if (empty($s['indexnow']) || !empty($s['private']['enabled'])) return false;
    $host = strtolower((string) parse_url($svc->url('/'), PHP_URL_HOST));
    return $host !== '' && !filter_var($host, FILTER_VALIDATE_IP) && !preg_match('/(^|\.)(localhost|local|test|example|invalid|internal|lan)$/', $host) && str_contains($host, '.');
}

/** Envía URLs a IndexNow (api.indexnow.org reparte a Bing, Yandex, Seznam, Naver…). */
function indexnow_ping(SiteService $svc, array $urls): array
{
    if (!indexnow_enabled($svc)) return ['sent' => 0, 'skipped' => 'IndexNow desactivado, web privada o dominio local/de pruebas'];
    $host = (string) parse_url($svc->url('/'), PHP_URL_HOST);
    $urls = array_values(array_unique(array_filter($urls, fn($u) => strtolower((string) parse_url($u, PHP_URL_HOST)) === strtolower($host))));
    if (!$urls) return ['sent' => 0];
    $key = indexnow_key($svc);
    $body = json_encode(['host' => $host, 'key' => $key, 'keyLocation' => $svc->url("/$key.txt"), 'urlList' => array_slice($urls, 0, 10000)], JSON_UNESCAPED_SLASHES);
    $code = 0;
    if (function_exists('curl_init')) {
        $ch = curl_init('https://api.indexnow.org/indexnow');
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => ['Content-Type: application/json; charset=utf-8']]);
        curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    } else {
        $ctx = stream_context_create(['http' => ['method' => 'POST', 'timeout' => 8, 'ignore_errors' => true, 'header' => "Content-Type: application/json; charset=utf-8\r\n", 'content' => $body]]);
        @file_get_contents('https://api.indexnow.org/indexnow', false, $ctx);
        $code = isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m) ? (int) $m[1] : 0;
    }
    audit($svc->id(), 'seo.indexnow', count($urls) . " URL(s), HTTP $code");
    return ['sent' => count($urls), 'http' => $code, 'ok' => in_array($code, [200, 202], true), 'urls' => array_slice($urls, 0, 20)];
}

/** Avisa al terminar la petición (sin hacer esperar a nadie) de una URL publicada. Máx. una vez cada 10 min por URL. */
function indexnow_queue(SiteService $svc, string $path): void
{
    if (Out::$capture || PHP_SAPI === 'cli' || !indexnow_enabled($svc)) return;
    $url = $svc->url($path);
    if (!throttle('indexnow:' . $url, 1, 600)) return;
    static $queue = [];
    $first = !$queue;
    $queue[$svc->id()]['svc'] = $svc;
    $queue[$svc->id()]['urls'][] = $url;
    if (!$first) return;
    register_shutdown_function(function () use (&$queue) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        foreach ($queue as $q) { try { indexnow_ping($q['svc'], $q['urls']); } catch (Throwable) {} }
    });
}
