<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Servidor MCP (Model Context Protocol) sobre HTTP, sin estado: JSON-RPC 2.0 por POST a /mcp.
 * Cada petición se autentica con un token (Authorization: Bearer uw_... o X-MCP-Token).
 * Un token pertenece a una web, o es global (solo superadmin) y entonces cada herramienta
 * recibe el parámetro "site".
 */

/** Herramientas que trabajan en un idioma concreto (parámetro opcional «lang»). */
const MCP_LANG_TOOLS = ['page_list', 'page_get', 'page_save', 'page_patch', 'page_history', 'page_restore', 'page_delete', 'post_list', 'post_get', 'post_save', 'post_history', 'post_restore', 'post_delete', 'geo_audit', 'seo_analyze'];

const MCP_PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

function mcp_instructions(): string
{
    return <<<TXT
    Universal Web es un CMS multi-web. Cada web tiene ajustes (nombre, menú, contacto, redes, SEO por defecto, tema),
    páginas formadas por bloques tipados y un blog con entradas en Markdown.

    Flujo recomendado:
    1. site_get para conocer la web y su menú. Usa block_types antes de crear o editar páginas: describe cada tipo de bloque y sus campos.
    2. Páginas: page_get y luego page_save con la lista COMPLETA de bloques (reemplaza la anterior). La página de inicio tiene el slug "index".
    3. Blog: post_save crea la entrada si el slug no existe (o si no se indica) y la actualiza si existe. content está en Markdown (sin H1: el título ya es el H1).
    4. SEO: rellena seo.description (50–160 caracteres) y textos alternativos en las imágenes. seo_audit lista problemas y da una puntuación.
       Flujo rápido: seo_autofix (el servidor rellena lo mecánico) → seo_bulk_update con lo de needs_writing (todo en una llamada)
       → seo_analyze con keyword en las páginas clave (la búsqueda por la que quieres salir). Al publicar, la web avisa sola a
       Bing/ChatGPT (IndexNow); search_ping tras cambios grandes.
       GEO (aparecer en respuestas de IAs): geo_audit; las claves son identidad clara (site_update business/contact), preguntas
       frecuentes (bloque faq), cifras concretas (bloque stats) y contenido actualizado. /llms.txt y los datos estructurados se generan solos.
    5. Imágenes PROPIAS: usa fotos del cliente o generadas por IA y súbelas con media_upload (url de tu generador o base64);
       usa siempre la URL /media/… que devuelve. NUNCA enlaces ni copies fotos de otras webs, bancos de imágenes o buscadores:
       tienen derechos de autor y dependen de un servidor ajeno (seo_audit avisa). Mientras el cliente no tenga fotos, puedes usar las
       fotos de muestra del tema (site_get → themes → sample_images: propias y libres, con su alt) o dejar el campo vacío. Pon siempre un texto alternativo descriptivo.

    6. Vídeo, audio y voz (fotos, vídeos, música y voz generados por IA): súbelos con media_upload (ai_generated=true, title y alt) y antes
       busca con media_list (kind, q) si ya existen para reutilizarlos. Bloques: "video" (propio o YouTube/Vimeo), "audio" (música, podcast),
       "gallery" (fotos y vídeos: video en cada elemento) y "listen" = «Escuchar esta página» (lectura de la página con voz/TTS, con transcript).
       En el blog, post_save audio = versión en audio del artículo. Pon la transcripción: las IAs y Google leen el texto, no el audio.
    7. Diseño a fondo: code_get / code_update (CSS, JS, <head>, favicon) con historial (code_history / code_restore). Haz backup_create antes de cambios grandes.
    8. Si la web se publica como estática en otro servidor (publish_status → mode «static»), los cambios no se ven hasta publicar: usa publish_now al terminar si no hay publicación automática.
    9. Varios idiomas: site_update languages añade idiomas (/en/…, /ro/…). Cada página o entrada existe por idioma con el MISMO slug:
       usa el parámetro lang en page_*/post_*. translation_status dice qué falta; traduce todo el contenido y los textos de la web (site_update translations).
    10. Web nueva: elige el tema por el tipo de negocio (site_get → themes, campo best_for) y pon accent con el color de la marca o el suggested_accent.
       Negocios con local (restaurante, tienda, taller, clínica…): añade el bloque hours (horario: muestra «Abierto ahora» y Google/IAs lo leen)
       y, si hay precios o carta, el bloque pricelist. Revisa al final con seo_audit y geo_audit.

    COMPROBAR: al terminar cualquier cambio usa site_check (revisa todas las páginas: enlaces rotos, recursos que faltan, avisos de PHP,
    H1, alt) y corrige lo que salga; errors_list es la consola real de la web (errores de JavaScript y archivos que no cargan en los
    navegadores de los visitantes, y errores del servidor). Tras arreglarlos, errors_clear y vuelve a mirar más tarde.
    Cada page_save / post_save / page_patch / page_restore devuelve "check" con los fallos de ESA página: corrígelos antes de seguir.
    Para cambios pequeños usa page_patch (un bloque) en vez de reenviar la página; si algo sale mal, page_restore lo deshace y lo
    borrado está 30 días en trash_list / trash_restore.

    NO INVENTES DATOS: nunca escribas datos de contacto (email, teléfono, dirección), cifras, años, certificaciones,
    ubicaciones ni opiniones de clientes que no estén ya en la web (site_get, page_get) o que no te haya dado la persona.
    Si faltan, deja el campo vacío y dilo en tu respuesta final. Un dato falso en una web es peor que uno que falta.

    WEB NUEVA Y DOMINIOS: tras site_create pasa a la persona preview_url y domain_setup.message_for_user (qué tiene que poner en
    el DNS y si hace falta algo en el panel del hosting). Si te da su dominio, domain_add; cuando diga que ya cambió el DNS,
    domain_status para comprobarlo y confirmarle que está en marcha. No le digas que está publicada hasta que ready sea true.

    DIRECCIONES: si la persona pregunta la dirección (ruta, enlace) de una web o del panel, dásela exacta: url de site_get o
    sites_list para la web y panel_url para el panel. Si la web aún no tiene dominio (url null), dale preview_url. Todas las
    webs comparten la misma instalación: no hay una carpeta por web en el servidor.

    AYUDA: si la persona pregunta cómo hacer algo en el panel, usa manual (índice) y manual con section: explica con los
    nombres reales de los menús y botones, paso a paso. Si pregunta qué es un campo o un botón de una pantalla, manual con
    screen. En cada pantalla del panel tiene el botón «Guíame» (abajo a la derecha) que se lo enseña resaltado.

    MIRAR CON NAVEGADOR: si tienes navegador, abre cada página que crees o cambies (la "url" de check o preview_url) y mírala de verdad:
    que se vea bien en móvil (≈390 px) y escritorio (≈1280 px), que no haya textos cortados, imágenes rotas o solapadas, que el menú
    funcione y que la consola del navegador no tenga errores rojos. Las comprobaciones automáticas no ven el diseño: tú sí. No des un
    trabajo por terminado sin haberlo mirado; si ves algo mal, corrígelo y vuelve a mirar.

    PLANTILLA HTML: para un diseño a medida usa template_get / template_update (HTML con marcadores, sin código en el servidor) y revisa preview_url antes de activarla; para retoques de estilo basta code_update css.

    Permisos del token: "read" para leer, "write" para crear y editar borradores, "publish" para publicar o modificar contenido ya publicado,
    "messages" para leer los mensajes de contacto (datos personales), "code" para el CSS/JS/<head> personalizado y "backup" para
    copias de seguridad (estos dos últimos exigen que el dueño del token sea admin).
    Estados: "draft" (borrador) o "published". Una entrada publicada con published_at en el futuro queda programada.
    Todas las acciones quedan registradas en el registro de actividad del panel.
    TXT;
}

// ---------------------------------------------------------------- definición de herramientas

/** Cada herramienta: description, scope requerido, inputSchema (JSON Schema) y handler(SiteService, args). */
function mcp_tools(): array
{
    $str = fn(string $d) => ['type' => 'string', 'description' => $d];
    $seo = ['type' => 'object', 'description' => 'SEO: {title, description (50–160 caracteres), image (URL), noindex (bool)}', 'properties' => [
        'title' => ['type' => 'string'], 'description' => ['type' => 'string'], 'image' => ['type' => 'string'], 'noindex' => ['type' => 'boolean'],
    ]];
    $status = ['type' => 'string', 'enum' => ['draft', 'published'], 'description' => "'draft' (borrador) o 'published' (requiere permiso publish)"];
    $obj = fn(array $props = [], array $required = []) => ['type' => 'object', 'properties' => (object) $props] + ($required ? ['required' => $required] : []);

    return [
        'site_get' => [
            'description' => 'Devuelve la información de la web: nombre, URL pública (url), dirección del panel de administración (panel_url), dominios, tema, eslogan, idioma, menú, contacto, redes, SEO por defecto, estadísticas, lista de páginas y preview_url (enlace de 7 días para verla aunque su dominio aún no apunte aquí).',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => $s->publicInfo() + ['stats' => $s->stats(), 'pages' => $s->pages(), 'themes' => theme_guide(),
                'preview_url' => site_preview_link($s->site), 'panel_url' => panel_url()],
        ],
        'site_update' => [
            'description' => 'Actualiza ajustes de la web con un parche parcial: solo se cambian las claves enviadas. Los objetos contact/social/seo se fusionan; menu y utility_menu se reemplazan enteros; cta es el botón destacado de la cabecera; social.whatsapp acepta un enlace wa.me. Cambiar el tema cambia todo el diseño del front.',
            'scope' => 'publish', 'role' => 'admin', 'inputSchema' => $obj([
                'name' => $str('Nombre de la web'), 'tagline' => $str('Eslogan'), 'language' => $str('Código de idioma, p. ej. es'),
                'logo' => $str('URL del logo'), 'accent' => $str('Color principal #rrggbb'), 'footer_text' => $str('Texto del pie'),
                'theme' => $str('Tema según el tipo de negocio: ' . theme_guide_text() . '. Al cambiarlo, pon también accent con su color sugerido si la marca no tiene uno.'),
                'menu' => ['type' => 'array', 'description' => 'Menú completo', 'items' => $obj(['label' => ['type' => 'string'], 'url' => ['type' => 'string']], ['label', 'url'])],
                'contact' => $obj(['email' => ['type' => 'string'], 'phone' => ['type' => 'string'], 'address' => ['type' => 'string']]),
                'social' => $obj(array_fill_keys(['instagram', 'facebook', 'x', 'linkedin', 'youtube', 'github', 'tiktok', 'whatsapp'], ['type' => 'string'])),
                'cta' => $obj(['label' => ['type' => 'string'], 'url' => ['type' => 'string']]),
                'utility_menu' => ['type' => 'array', 'description' => 'Enlaces secundarios de la barra superior (p. ej. Área de clientes)', 'items' => $obj(['label' => ['type' => 'string'], 'url' => ['type' => 'string']], ['label', 'url'])],
                'seo' => $obj(['description' => $str('Descripción por defecto (50–160)'), 'image' => $str('Imagen por defecto para redes')]),
                'business' => $obj(['type' => ['type' => 'string', 'enum' => array_keys(GEO_BUSINESS_TYPES), 'description' => 'Tipo de negocio para las IAs (schema.org)'],
                    'legal_name' => $str('Razón social'), 'area_served' => $str('Zona de servicio, p. ej. «Región de Murcia y toda España»'), 'founded' => $str('Año de fundación, p. ej. 1985')]),
                'language' => $str('Idioma principal (código ISO: es, en, ro…)'),
                'languages' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Idiomas ADICIONALES de la web (p. ej. ["en", "ro"]): se publican en /en/..., /ro/... Reemplaza la lista entera.'],
                'translations' => ['type' => 'object', 'description' => 'Textos de la web traducidos por idioma: {"en": {"tagline": "...", "footer_text": "...", "seo": {"description": "..."}, "menu": [{label, url}], "utility_menu": [...], "cta": {label, url}, "cookies_text": "..."}}. Sin menú traducido se usan los enlaces del principal con el prefijo del idioma.'],
                'cookies' => $obj(['enabled' => ['type' => 'boolean', 'description' => 'Muestra el aviso de cookies. Se activa solo al configurar ga4/gtm/clarity/meta_pixel. Sin ninguna cookie no esencial déjalo en false: un aviso sin necesidad solo molesta. Incluye Aceptar / Rechazar (mismo peso) / Configurar y un botón para cambiar de opinión; el visitante guarda su elección 12 meses y se le vuelve a preguntar si cambias las herramientas'],
                    'text' => ['type' => 'string', 'description' => 'Texto propio del banner; vacío = estándar traducido. Traducción: translations.<lang>.cookies_text'],
                    'policy_url' => ['type' => 'string', 'description' => 'Tu política de cookies; vacío = /cookies generada automáticamente con las herramientas configuradas'],
                    'position' => ['type' => 'string', 'enum' => ['bottom', 'box'], 'description' => 'bottom = barra inferior; box = caja en la esquina (en móvil ocupa todo el ancho)'],
                    'ga4' => ['type' => 'string', 'description' => 'ID de Google Analytics 4 (G-XXXX); se carga SOLO tras el consentimiento, con Consent Mode v2 (denegado por defecto)'],
                    'gtm' => ['type' => 'string', 'description' => 'ID de Google Tag Manager (GTM-XXXX), con Consent Mode v2'],
                    'clarity' => ['type' => 'string', 'description' => 'ID de Microsoft Clarity'],
                    'meta_pixel' => ['type' => 'string', 'description' => 'ID del píxel de Meta (marketing)'],
                    'reset_consent' => ['type' => 'boolean', 'description' => 'true = vuelve a pedir el consentimiento a todos los visitantes (ya se vuelve a pedir solo al añadir o quitar una herramienta)']]),
                'indexnow' => ['type' => 'boolean', 'description' => 'true (por defecto) = avisa sola a Bing/Yandex (IndexNow) de cada página publicada o cambiada'],
                'ai_markdown' => ['type' => 'boolean', 'description' => 'true = los agentes de IA reciben cada página en Markdown limpio (Google y Bing siempre ven HTML)'],
                'ai_crawlers' => ['type' => 'string', 'enum' => array_keys(GEO_CRAWLER_POLICIES), 'description' => 'Rastreadores de IA en robots.txt: ' . implode(' · ', array_map(fn($k, $v) => "$k = $v", array_keys(GEO_CRAWLER_POLICIES), GEO_CRAWLER_POLICIES))],
            ]),
            'handler' => fn(SiteService $s, array $a) => ['settings' => $s->updateSettings($a), 'name' => $s->site['name'], 'theme' => $s->site['theme']],
        ],
        'block_types' => [
            'description' => 'Describe los tipos de bloque disponibles para las páginas y sus campos. Consúltalo antes de usar page_save. Campos tipo "list" son listas de objetos con los subcampos indicados; "markdown" admite Markdown.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => ['format' => 'blocks = [{"type": "<tipo>", "data": {<campos>}}]', 'blocks' => block_types()],
        ],
        'page_list' => [
            'description' => 'Lista las páginas de la web (slug, título, estado, URL).',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => ['lang' => $s->lang, 'languages' => $s->langs(),
                'pages' => array_map(fn($p) => $p + ['translations' => array_keys($s->translations('pages', $p['slug']))], $s->pages())],
        ],
        'page_get' => [
            'description' => 'Devuelve una página completa con sus bloques y SEO. La página de inicio es "index".',
            'scope' => 'read', 'inputSchema' => $obj(['slug' => $str('Slug de la página')], ['slug']),
            'handler' => fn(SiteService $s, array $a) => ['page' => $s->page((string) $a['slug']) ?? throw new UwError("No existe la página '{$a['slug']}'. Usa page_list.")],
        ],
        'page_save' => [
            'description' => 'Crea o actualiza una página por slug. Si la página existe, solo se cambian los campos enviados; blocks, si se envía, reemplaza TODOS los bloques. Para renombrar el slug usa new_slug: si la página está publicada, la redirección 301 desde la URL vieja se crea sola (la respuesta trae redirect_created). Añádela al menú con site_update si debe aparecer en la navegación.',
            'scope' => 'write', 'inputSchema' => $obj([
                'slug' => $str('Slug (identificador en la URL). "index" = inicio'), 'new_slug' => $str('Nuevo slug (opcional, para renombrar)'),
                'title' => $str('Título de la página'), 'status' => $status,
                'blocks' => ['type' => 'array', 'description' => 'Lista de bloques [{type, data}] (ver block_types)', 'items' => $obj(['type' => ['type' => 'string'], 'data' => ['type' => 'object']], ['type'])],
                'seo' => $seo,
            ], ['slug']),
            'handler' => function (SiteService $s, array $a) {
                $cur = $s->page(slugify((string) $a['slug']));
                mcp_require_publish($cur['status'] ?? null, $a['status'] ?? null);
                $in = array_intersect_key($a, array_flip(['title', 'status', 'blocks', 'seo']));
                $in['slug'] = $a['new_slug'] ?? $a['slug'];
                $page = $s->savePage($in, $cur ? (int) $cur['id'] : null);
                return ['page' => $page, 'check' => content_quick_check($s, 'page', $page)] + ($s->lastRedirect ? ['redirect_created' => $s->lastRedirect] : []);
            },
        ],
        'page_patch' => [
            'description' => 'Edita bloques CONCRETOS de una página sin reenviarla entera (más seguro que page_save para cambios pequeños). ops se aplican en orden; index empieza en 0 (mira blocks en page_get o el resumen que devuelve esta herramienta). Operaciones: update = cambia solo los campos de data indicados en el bloque index (las listas se reemplazan enteras); replace = sustituye el bloque index por block; insert = añade block en la posición index (sin index: al final); delete = quita el bloque index; move = mueve el bloque index a la posición to. Devuelve el resumen de bloques y la comprobación de la página.',
            'scope' => 'write', 'inputSchema' => $obj([
                'slug' => $str('Slug de la página'),
                'ops' => ['type' => 'array', 'description' => 'Operaciones', 'items' => $obj([
                    'op' => ['type' => 'string', 'enum' => ['update', 'replace', 'insert', 'delete', 'move']],
                    'index' => ['type' => 'integer', 'description' => 'Posición del bloque (0 = primero)'],
                    'data' => ['type' => 'object', 'description' => 'update: campos a cambiar del bloque'],
                    'block' => $obj(['type' => ['type' => 'string'], 'data' => ['type' => 'object']], ['type']),
                    'to' => ['type' => 'integer', 'description' => 'move: nueva posición'],
                ], ['op'])],
            ], ['slug', 'ops']),
            'handler' => function (SiteService $s, array $a) {
                $cur = $s->page(slugify((string) $a['slug'])) ?? throw new UwError("No existe la página '{$a['slug']}' en este idioma (page_list)");
                mcp_require_publish($cur['status'], null);
                $blocks = array_values(array_map(fn($b) => ['type' => $b['type'], 'data' => $b['data']], $cur['blocks']));
                foreach (array_values((array) $a['ops']) as $n => $op) {
                    if (!is_array($op)) throw new UwError("ops[$n] debe ser un objeto");
                    $kind = (string) ($op['op'] ?? '');
                    $i = isset($op['index']) ? (int) $op['index'] : null;
                    $need = function () use ($i, $blocks, $n) {
                        if ($i === null || $i < 0 || $i >= count($blocks)) throw new UwError("ops[$n]: index fuera de rango. La página tiene " . count($blocks) . ' bloques: ' . mcp_blocks_summary($blocks));
                    };
                    switch ($kind) {
                        case 'update':
                            $need();
                            if (!is_array($op['data'] ?? null)) throw new UwError("ops[$n]: update necesita data con los campos a cambiar");
                            $blocks[$i]['data'] = array_merge($blocks[$i]['data'], $op['data']);
                            break;
                        case 'replace':
                            $need();
                            if (!is_array($op['block'] ?? null)) throw new UwError("ops[$n]: replace necesita block {type, data}");
                            $blocks[$i] = $op['block'];
                            break;
                        case 'insert':
                            if (!is_array($op['block'] ?? null)) throw new UwError("ops[$n]: insert necesita block {type, data}");
                            $pos = $i === null ? count($blocks) : max(0, min(count($blocks), $i));
                            array_splice($blocks, $pos, 0, [$op['block']]);
                            break;
                        case 'delete':
                            $need();
                            array_splice($blocks, $i, 1);
                            break;
                        case 'move':
                            $need();
                            $to = max(0, min(count($blocks) - 1, (int) ($op['to'] ?? 0)));
                            $b = array_splice($blocks, $i, 1)[0];
                            array_splice($blocks, $to, 0, [$b]);
                            break;
                        default:
                            throw new UwError("ops[$n]: op debe ser update, replace, insert, delete o move");
                    }
                }
                // Los *_html de page_get son de solo lectura: se quitan antes de validar
                $blocks = array_map(function ($b) {
                    if (isset($b['data']) && is_array($b['data'])) foreach (array_keys($b['data']) as $k) if (str_ends_with((string) $k, '_html')) unset($b['data'][$k]);
                    return $b;
                }, $blocks);
                $page = $s->savePage(['blocks' => $blocks], (int) $cur['id']);
                return ['blocks' => mcp_blocks_summary($page['blocks']), 'check' => content_quick_check($s, 'page', $page)];
            },
        ],
        'page_history' => [
            'description' => 'Versiones anteriores de una página (se guarda una en cada cambio, hasta 30), con fecha, autor y resumen de bloques. Para deshacer usa page_restore.',
            'scope' => 'read', 'inputSchema' => $obj(['slug' => $str('Slug de la página')], ['slug']),
            'handler' => fn(SiteService $s, array $a) => mcp_history_out(history_list($s, 'page', slugify((string) $a['slug']), $s->lang)),
        ],
        'page_restore' => [
            'description' => 'Deshace cambios de una página. SIN version_id: vuelve a la versión anterior (deshacer el último cambio). Con version_id (de page_history): vuelve a esa. La versión actual queda en el historial, así que también se puede deshacer.',
            'scope' => 'write', 'inputSchema' => $obj(['slug' => $str('Slug de la página'), 'version_id' => ['type' => 'integer']], ['slug']),
            'handler' => function (SiteService $s, array $a) {
                $slug = slugify((string) $a['slug']);
                $cur = $s->page($slug);
                $v = (int) ($a['version_id'] ?? 0);
                $vd = $v ? row('SELECT data FROM content_versions WHERE id = ? AND site_id = ?', [$v, $s->id()]) : row("SELECT data FROM content_versions WHERE site_id = ? AND kind = 'page' AND lang = ? AND slug = ? ORDER BY id DESC LIMIT 1", [$s->id(), $s->lang, $slug]);
                mcp_require_publish($cur['status'] ?? null, $vd ? (string) (json_dec($vd['data'])['status'] ?? '') : null);
                $page = history_restore($s, 'page', $slug, $s->lang, $v);
                return ['page' => ['slug' => $page['slug'], 'title' => $page['title'], 'status' => $page['status'], 'blocks' => mcp_blocks_summary($page['blocks'])], 'check' => content_quick_check($s, 'page', $page)];
            },
        ],
        'trash_list' => [
            'description' => 'Papelera: páginas y entradas borradas en los últimos 30 días. Se recuperan con trash_restore.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => ['trash' => trash_list($s)],
        ],
        'trash_restore' => [
            'description' => 'Recupera de la papelera una página o entrada borrada (trash_id de trash_list), con su contenido y estado.',
            'scope' => 'publish', 'inputSchema' => $obj(['trash_id' => ['type' => 'integer']], ['trash_id']),
            'handler' => fn(SiteService $s, array $a) => (fn($r) => ['restored' => ['slug' => $r['slug'], 'title' => $r['title'], 'status' => $r['status'], 'lang' => $r['lang']],
                'note' => 'Recuperada con el mismo slug y la misma URL (el id interno es nuevo; usa siempre el slug).'])(trash_restore($s, (int) $a['trash_id'])),
        ],
        'page_delete' => [
            'description' => 'Borra una página: va a la papelera 30 días (trash_list / trash_restore para recuperarla).',
            'scope' => 'publish', 'inputSchema' => $obj(['slug' => $str('Slug de la página')], ['slug']),
            'handler' => function (SiteService $s, array $a) {
                $p = $s->page((string) $a['slug']) ?? throw new UwError("No existe la página '{$a['slug']}'");
                $s->deletePage((int) $p['id']);
                return ['deleted' => $p['slug']];
            },
        ],
        'post_list' => [
            'description' => 'Lista entradas del blog (sin el contenido completo). state: draft, published o scheduled.',
            'scope' => 'read', 'inputSchema' => $obj([
                'status' => ['type' => 'string', 'enum' => ['draft', 'published']], 'tag' => $str('Filtrar por etiqueta'),
                'limit' => ['type' => 'integer', 'description' => 'Máximo 100 (por defecto 20)'], 'offset' => ['type' => 'integer'],
            ]),
            'handler' => fn(SiteService $s, array $a) => [
                'total' => $s->countPosts($a),
                'posts' => array_map(fn($p) => array_diff_key($p, ['content' => 1]), $s->posts($a)),
            ],
        ],
        'post_get' => [
            'description' => 'Devuelve una entrada completa, incluido el contenido en Markdown.',
            'scope' => 'read', 'inputSchema' => $obj(['slug' => $str('Slug de la entrada')], ['slug']),
            'handler' => fn(SiteService $s, array $a) => ['post' => $s->post((string) $a['slug']) ?? throw new UwError("No existe la entrada '{$a['slug']}'. Usa post_list.")],
        ],
        'post_save' => [
            'description' => 'Crea o actualiza una entrada del blog. Si slug existe se actualiza (solo los campos enviados); si no, se crea (slug se genera del título si no se indica). content en Markdown, sin H1. Preguntas frecuentes en una entrada: «## ¿Pregunta?» y debajo la respuesta; se publican solas como FAQPage (no pegues JSON-LD: se elimina). Rellena excerpt y seo.description para un buen SEO.',
            'scope' => 'write', 'inputSchema' => $obj([
                'slug' => $str('Slug de la entrada (opcional al crear)'), 'new_slug' => $str('Nuevo slug (para renombrar; si está publicada se crea la 301 sola)'),
                'title' => $str('Título'), 'content' => $str('Contenido en Markdown'), 'excerpt' => $str('Resumen corto (1–2 frases)'),
                'cover_image' => $str('URL de la imagen de portada'), 'audio' => $str('Versión en audio del artículo («Escuchar»): URL /media/… de un audio propio (p. ej. voz generada por IA leyendo el texto)'), 'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
                'author' => $str('Autor'), 'status' => $status, 'published_at' => $str('Fecha de publicación AAAA-MM-DD HH:MM (futura = programada)'),
                'seo' => $seo,
            ]),
            'handler' => function (SiteService $s, array $a) {
                $cur = isset($a['slug']) ? $s->post(slugify((string) $a['slug'])) : null;
                mcp_require_publish($cur['status'] ?? null, $a['status'] ?? null);
                $in = array_intersect_key($a, array_flip(['title', 'content', 'excerpt', 'cover_image', 'audio', 'tags', 'author', 'status', 'published_at', 'seo']));
                if (isset($a['new_slug']) || isset($a['slug'])) $in['slug'] = $a['new_slug'] ?? $a['slug'];
                $post = $s->savePost($in, $cur ? (int) $cur['id'] : null);
                return ['post' => $post, 'check' => content_quick_check($s, 'post', $post)] + ($s->lastRedirect ? ['redirect_created' => $s->lastRedirect] : []);
            },
        ],
        'post_history' => [
            'description' => 'Versiones anteriores de una entrada del blog (hasta 30). Para deshacer usa post_restore.',
            'scope' => 'read', 'inputSchema' => $obj(['slug' => $str('Slug de la entrada')], ['slug']),
            'handler' => fn(SiteService $s, array $a) => mcp_history_out(history_list($s, 'post', slugify((string) $a['slug']), $s->lang)),
        ],
        'post_restore' => [
            'description' => 'Deshace cambios de una entrada. SIN version_id: deshacer el último cambio. Con version_id (de post_history): vuelve a esa versión.',
            'scope' => 'write', 'inputSchema' => $obj(['slug' => $str('Slug de la entrada'), 'version_id' => ['type' => 'integer']], ['slug']),
            'handler' => function (SiteService $s, array $a) {
                $slug = slugify((string) $a['slug']);
                $cur = $s->post($slug);
                mcp_require_publish($cur['status'] ?? null, null);
                $post = history_restore($s, 'post', $slug, $s->lang, (int) ($a['version_id'] ?? 0));
                return ['post' => ['slug' => $post['slug'], 'title' => $post['title'], 'status' => $post['status']], 'check' => content_quick_check($s, 'post', $post)];
            },
        ],
        'post_delete' => [
            'description' => 'Borra una entrada: va a la papelera 30 días (trash_list / trash_restore para recuperarla).',
            'scope' => 'publish', 'inputSchema' => $obj(['slug' => $str('Slug de la entrada')], ['slug']),
            'handler' => function (SiteService $s, array $a) {
                $p = $s->post((string) $a['slug']) ?? throw new UwError("No existe la entrada '{$a['slug']}'");
                $s->deletePost((int) $p['id']);
                return ['deleted' => $p['slug']];
            },
        ],
        'media_list' => [
            'description' => 'Biblioteca de medios de la web (fotos, vídeos, audios, PDF) con URL, título, texto alternativo, tipo y si los generó una IA. Búscala ANTES de generar o subir algo nuevo: reutiliza lo que ya existe. Filtra por kind y busca por texto con q.',
            'scope' => 'read', 'inputSchema' => $obj([
                'kind' => ['type' => 'string', 'enum' => ['image', 'video', 'audio', 'pdf'], 'description' => 'Solo este tipo'],
                'q' => $str('Texto a buscar en el título, el texto alternativo o el nombre (p. ej. "pizza", "voz portada")'),
                'limit' => ['type' => 'integer', 'description' => 'Máximo de resultados (por defecto 50)'],
            ]),
            'handler' => fn(SiteService $s, array $a) => ['media' => array_map(fn($m) => ['id' => (int) $m['id'], 'kind' => $m['kind'], 'url' => $m['url'], 'absolute_url' => $s->url($m['url']),
                'title' => $m['title'], 'alt' => $m['alt'], 'ai_generated' => (bool) $m['ai_generated'], 'name' => $m['original_name'], 'mime' => $m['mime'], 'size' => (int) $m['size'], 'created_at' => $m['created_at']],
                $s->mediaSearch((string) ($a['kind'] ?? ''), (string) ($a['q'] ?? ''), (int) ($a['limit'] ?? 50)))],
        ],
        'media_update' => [
            'description' => 'Cambia el título o el texto alternativo de un archivo de la biblioteca (por id de media_list). Útil para dejar todas las imágenes con un alt descriptivo (SEO y accesibilidad).',
            'scope' => 'write', 'inputSchema' => $obj(['id' => ['type' => 'integer'], 'alt' => $str('Texto alternativo descriptivo'), 'title' => $str('Título corto para encontrarlo luego')], ['id']),
            'handler' => fn(SiteService $s, array $a) => (fn($m) => ['id' => (int) $m['id'], 'url' => $m['url'], 'title' => $m['title'], 'alt' => $m['alt']])($s->updateMedia((int) $a['id'], array_intersect_key($a, ['alt' => 1, 'title' => 1]))),
        ],
        'media_upload' => [
            'description' => 'Sube un archivo a la biblioteca: imagen (jpg, png, gif, webp, avif, ico), PDF, vídeo (mp4, webm) o audio (mp3, ogg, m4a, wav). Usa solo archivos PROPIOS (del cliente o generados por IA): no descargues fotos de otras webs ni de bancos de imágenes (derechos de autor). Dos formas: "url" (descarga el archivo, p. ej. desde tu generador de imágenes; máx. 100 MB) o "data_base64" + "filename" (máx. 8 MB en imágenes). Devuelve la URL para usarla en bloques (image, gallery, video, audio) o como cover_image.',
            'scope' => 'write', 'inputSchema' => $obj([
                'url' => $str('URL pública http(s) del archivo a descargar'),
                'data_base64' => $str('Contenido en base64 (alternativa a url)'),
                'filename' => $str('Nombre con extensión, p. ej. portada.png (obligatorio con data_base64)'),
                'alt' => $str('Texto alternativo / descripción (recomendado para SEO)'),
                'title' => $str('Título corto para encontrarlo luego con media_list (p. ej. "Portada pizzería", "Voz página servicios")'),
                'ai_generated' => ['type' => 'boolean', 'description' => 'true si lo has generado con IA (foto, vídeo, voz o música propios)'],
                'keep_original' => ['type' => 'boolean', 'description' => 'true = no optimizar (por defecto las fotos se reducen a 2000 px y pasan a WebP si pesan menos)'],
            ]),
            'handler' => function (SiteService $s, array $a) {
                $meta = ['title' => (string) ($a['title'] ?? ''), 'ai_generated' => !empty($a['ai_generated']), 'keep_original' => !empty($a['keep_original'])];
                if (!empty($a['url'])) {
                    $m = $s->storeMediaFromUrl((string) $a['url'], (string) ($a['alt'] ?? ''), isset($a['filename']) ? (string) $a['filename'] : null, $meta);
                } elseif (!empty($a['data_base64']) && !empty($a['filename'])) {
                    $m = $s->storeMediaBase64((string) $a['filename'], (string) $a['data_base64'], (string) ($a['alt'] ?? ''), $meta);
                } else {
                    throw new UwError('Indica "url", o bien "data_base64" junto con "filename"');
                }
                return ['id' => $m['id'], 'kind' => $m['kind'], 'url' => $m['url'], 'absolute_url' => $s->url($m['url']), 'mime' => $m['mime'], 'size' => $m['size']] + (isset($m['optimized']) ? ['optimized' => $m['optimized']] : []);
            },
        ],
        'messages_list' => [
            'description' => 'Lista los mensajes recibidos por el formulario de contacto, del más reciente al más antiguo.',
            'scope' => 'messages', 'inputSchema' => $obj(['unread_only' => ['type' => 'boolean'], 'limit' => ['type' => 'integer']]),
            'handler' => fn(SiteService $s, array $a) => ['messages' => array_map(fn($m) => array_diff_key($m, ['ip' => 1, 'site_id' => 1]),
                $s->messages(!empty($a['unread_only']), (int) ($a['limit'] ?? 50)))],
        ],
        'message_mark_read' => [
            'description' => 'Marca un mensaje de contacto como leído (o no leído con read=false).',
            'scope' => 'messages', 'inputSchema' => $obj(['id' => ['type' => 'integer'], 'read' => ['type' => 'boolean']], ['id']),
            'handler' => function (SiteService $s, array $a) {
                $s->setMessageRead((int) $a['id'], ($a['read'] ?? true) !== false);
                return ['ok' => true];
            },
        ],
        'code_get' => [
            'description' => 'Devuelve el código personalizado de la web: css (se carga tras el CSS del tema), js (al final del body, defer), head_html (dentro de <head>: analítica, verificaciones, fuentes) y favicon, con su versión.',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => site_code($s->id()) + ['theme' => $s->site['theme'], 'theme_css' => $s->url('/theme-assets/' . $s->site['theme'] . '/style.css'),
                'css_hints' => theme_css_hints($s->site['theme'])],
        ],
        'code_update' => [
            'description' => 'Cambia el diseño a fondo con CSS, JavaScript o código del <head>. Solo cambian los campos enviados y cada campo se REEMPLAZA entero (lee antes code_get y envía el contenido completo). Cada cambio crea una versión: se puede deshacer con code_restore. Revisa el resultado en la web pública. No pegues código de terceros que no sea de confianza.',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj([
                'css' => $str('CSS completo personalizado (máx. 200 KB). Puedes usar las variables del tema, p. ej. --accent'),
                'js' => $str('JavaScript completo personalizado (máx. 200 KB)'),
                'head_html' => $str('HTML para <head> (máx. 20 KB)'),
                'favicon' => $str('URL del favicon (/media/... o https://)'),
                'confirm_replace' => $str('No lo pongas de entrada: solo el código que te dé un rechazo por recorte (el servidor frena los reemplazos a medias)'),
            ]),
            'handler' => function (SiteService $s, array $a) {
                mcp_guard_shrink(site_code($s->id()), $a, ['css', 'js', 'head_html']);
                return ['code' => site_code_save($s->id(), array_intersect_key($a, array_flip(['css', 'js', 'head_html', 'favicon']))), 'preview' => $s->url('/')];
            },
        ],
        'code_history' => [
            'description' => 'Historial del código personalizado: la versión actual y las ANTERIORES guardadas (la primera de la lista es la inmediatamente anterior). Para deshacer el último cambio usa code_restore sin id.',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj(),
            'handler' => function (SiteService $s, array $a) {
                $h = site_code_history($s->id());
                return ['current_version' => (int) site_code($s->id())['version'], 'previous_versions' => $h,
                    'how_to_undo' => $h ? 'Deshacer el último cambio: code_restore sin id (restaura la versión ' . $h[0]['version'] . '). Volver más atrás: code_restore con el id de esa entrada.' : 'No hay versiones anteriores: no hay nada que deshacer.'];
            },
        ],
        'code_restore' => [
            'description' => 'Deshace cambios del código personalizado. SIN id: deshace el último cambio (vuelve a la versión inmediatamente anterior). Con id (de code_history): vuelve a esa versión concreta. Crea una versión nueva, así que también se puede deshacer.',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj(['id' => ['type' => 'integer', 'description' => 'Opcional: id de una entrada de code_history. Sin id = deshacer el último cambio']]),
            'handler' => function (SiteService $s, array $a) {
                $id = (int) ($a['id'] ?? 0);
                if (!$id) {
                    $h = site_code_history($s->id(), 1);
                    if (!$h) throw new UwError('No hay versiones anteriores: no hay nada que deshacer');
                    $id = (int) $h[0]['id'];
                }
                $r = site_code_restore($s->id(), $id);
                return ['code' => $r, 'restored_from_history_id' => $id, 'now_version' => (int) site_code($s->id())['version']];
            },
        ],
        'template_get' => [
            'description' => 'Plantilla HTML propia de la web (cabecera, pie y estructura de todas las páginas) + su CSS. HTML con marcadores tipo Mustache que Universal Web rellena: nunca se ejecuta código en el servidor. Devuelve la plantilla actual (o una de partida si no hay), la guía de marcadores, el historial y un enlace de vista previa (2 h) para revisarla antes de activarla.',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj(),
            'handler' => function (SiteService $s, array $a) {
                $t = tpl_get($s->id());
                return ['enabled' => $t['enabled'], 'base_assets' => $t['base_assets'], 'version' => $t['version'], 'layout' => $t['layout'], 'css' => $t['css'],
                    'starter_layout' => $t['layout'] === '' ? tpl_starter() : null, 'placeholders' => tpl_placeholders(),
                    'history' => tpl_history($s->id(), 10), 'preview_url' => tpl_preview_link($s),
                    'tips' => 'Flujo: 1) template_update con layout+css (enabled=false) 2) revisa preview_url 3) template_update enabled=true. Deshacer: template_restore sin id. El contenido de cada página lo pintan los bloques (no lo copies en la plantilla). Pon el JavaScript con code_update js.'];
            },
        ],
        'template_update' => [
            'description' => 'Crea o cambia la plantilla HTML de la web. Solo cambian los campos enviados y layout/css se REEMPLAZAN enteros (lee antes template_get). Parte de template_get.starter_layout y conserva sus marcadores (no inventes otros): layout debe incluir {{{head}}} dentro de <head> (pone título, SEO y CSS: no los escribas a mano) y {{{content}}}. Prohibido <script>, atributos on…, javascript:, PHP. enabled=true la pone en marcha para todos los visitantes; con false solo se ve en preview_url. Cada cambio crea versión (template_restore para deshacer).',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj([
                'layout' => $str('HTML completo de la plantilla con marcadores ({{{head}}}, {{{content}}}, {{#menu}}…{{/menu}}, {{site.name}}…). Máx. 100 KB'),
                'css' => $str('CSS completo de la plantilla (máx. 200 KB). Se carga después del CSS del tema'),
                'enabled' => ['type' => 'boolean', 'description' => 'true = la plantilla se usa en la web pública; false = solo vista previa'],
                'base_assets' => ['type' => 'boolean', 'description' => 'true (por defecto) = carga también el CSS/JS del tema para los bloques; false = empiezas de cero con tu CSS'],
                'confirm_replace' => $str('No lo pongas de entrada: solo el código que te dé un rechazo por recorte (el servidor frena los reemplazos a medias)'),
            ]),
            'handler' => function (SiteService $s, array $a) {
                mcp_guard_shrink(tpl_get($s->id()), $a, ['layout', 'css']);
                $r = tpl_save($s->id(), array_intersect_key($a, array_flip(['layout', 'css', 'enabled', 'base_assets'])));
                return ['version' => $r['version'], 'enabled' => $r['enabled'], 'warnings' => $r['warnings'], 'preview_url' => tpl_preview_link($s), 'live_url' => $s->url('/')];
            },
        ],
        'template_restore' => [
            'description' => 'Deshace cambios de la plantilla HTML. SIN id: vuelve a la versión inmediatamente anterior (deshacer el último cambio). Con id (de template_get.history): vuelve a esa versión. También se puede deshacer.',
            'scope' => 'code', 'role' => 'admin', 'inputSchema' => $obj(['id' => ['type' => 'integer', 'description' => 'Opcional: id de una entrada del historial']]),
            'handler' => fn(SiteService $s, array $a) => (fn($r) => ['restored_version' => $r['restored_version'], 'now_version' => $r['version'], 'enabled' => $r['enabled']])(tpl_restore($s->id(), (int) ($a['id'] ?? 0))),
        ],
        'backup_create' => [
            'description' => 'Crea una copia de seguridad local de la web (páginas, entradas, medios, mensajes, ajustes, diseño). Con un token global y global=true copia la instalación entera. Úsala antes de cambios grandes. Si la web tiene configurada una copia fuera del servidor (panel → Copias de seguridad), se envía también allí y el resultado viene en backup.offsite. Restaurar solo se puede desde el panel o la CLI.',
            'scope' => 'backup', 'role' => 'admin', 'inputSchema' => $obj([
                'global' => ['type' => 'boolean', 'description' => 'Copia de toda la instalación (solo tokens globales de superadmin)'],
                'password' => $str('Contraseña opcional para cifrar la copia (guárdala: sin ella no se puede restaurar)'),
            ]),
            'handler' => function (SiteService $s, array $a) {
                $global = !empty($a['global']);
                if ($global && !(mcp_is_global() && McpContext::$token['owner_role'] === 'super')) throw new UwError('Las copias globales requieren un token global de superadmin');
                $pw = isset($a['password']) && $a['password'] !== '' ? (string) $a['password'] : null;
                return ['backup' => backup_create($global ? null : $s->id(), $pw)];
            },
        ],
        'backup_list' => [
            'description' => 'Lista las copias de seguridad de la web (o de toda la instalación con un token global de superadmin y global=true) y el estado de la copia fuera del servidor (destino, programación y último envío; los datos de acceso solo se configuran en el panel).',
            'scope' => 'backup', 'role' => 'admin', 'inputSchema' => $obj(['global' => ['type' => 'boolean']]),
            'handler' => function (SiteService $s, array $a) {
                $global = !empty($a['global']) && mcp_is_global() && McpContext::$token['owner_role'] === 'super';
                $scope = $global ? 0 : $s->id();
                $c = offsite_config_raw($scope);
                $r = offsite_row($scope);
                return ['backups' => backup_list($global ? null : $s->id()), 'offsite' => ['enabled' => $c['enabled'], 'target' => $c['target'], 'schedule' => $c['schedule'],
                    'keep' => $c['keep'], 'encrypted' => (string) $c['encrypt_password'] !== '', 'last_at' => $r['last_at'], 'last_status' => $r['last_status'], 'last_log' => $r['last_log']]];
            },
        ],
        'publish_status' => [
            'description' => 'Dice cómo se publica la web: «dynamic» (la sirve este servidor, los cambios se ven al instante) o «static» (HTML en otro servidor: hay que publicar para que los cambios se vean). Incluye si hay cambios pendientes y el resultado de la última publicación.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => function (SiteService $s, array $a) {
                $c = publish_config_raw($s->id());
                $st = publish_row($s->id());
                return ['mode' => $c['mode'], 'public_url' => $c['public_url'], 'target' => $c['target'], 'auto_publish' => $c['auto_publish'],
                    'pending_changes' => (bool) $st['dirty'], 'last_published_at' => $st['last_published_at'], 'last_status' => $st['last_status'], 'last_log' => $st['last_log']];
            },
        ],
        'publish_now' => [
            'description' => 'Publica YA la web estática en su servidor de destino (solo webs en modo «static»; en modo «dynamic» no hace falta). Úsalo tras terminar un grupo de cambios si publish_status indica cambios pendientes y no hay publicación automática.',
            'scope' => 'publish', 'role' => 'admin', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => ['publish' => publish_run($s)],
        ],
        'geo_audit' => [
            'description' => 'Auditoría GEO: lo preparada que está la web para que los buscadores con IA (ChatGPT, Perplexity, Gemini, Claude) la entiendan y la citen. Revisa identidad de la empresa (tipo, zona, año, contacto), preguntas frecuentes, cifras concretas, frescura y la política de rastreadores de IA. Cada problema trae «fix» con la herramienta que lo arregla. La web genera sola /llms.txt, /llms-full.txt y los datos estructurados.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => geo_audit($s),
        ],
        'redirect_list' => [
            'description' => 'Redirecciones 301 de la web (from → to, visitas). Se crean solas al cambiar el slug de una página o entrada publicada; las demás se crean con redirect_save.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => ['redirects' => redirect_list($s->id())],
        ],
        'redirect_save' => [
            'description' => 'Crea o cambia una redirección 301 para no perder SEO cuando una URL deja de existir. from = ruta interna antigua (/servicio-viejo, con prefijo de idioma si no es el principal, p. ej. /en/old); to = ruta interna (/nuevo) o URL https://… externa. No se admite si en from hay una página publicada (taparía la redirección). Evita cadenas y bucles solas.',
            'scope' => 'publish', 'inputSchema' => $obj(['from' => $str('Ruta antigua, p. ej. /servicio-viejo'), 'to' => $str('Destino: /ruta-nueva o https://…')], ['from', 'to']),
            'handler' => fn(SiteService $s, array $a) => ['redirect' => redirect_save_manual($s, (string) $a['from'], (string) $a['to'])],
        ],
        'redirect_delete' => [
            'description' => 'Borra la redirección 301 que sale de la ruta from.',
            'scope' => 'publish', 'inputSchema' => $obj(['from' => $str('Ruta antigua de la redirección')], ['from']),
            'handler' => function (SiteService $s, array $a) {
                $f = redirect_norm_from((string) $a['from']) ?? throw new UwError('«from» debe ser una ruta interna como /servicio-antiguo');
                if (!redirect_drop($s->id(), $f)) throw new UwError("No hay redirección desde $f. Usa redirect_list.");
                audit($s->id(), 'redirect.delete', $f);
                return ['deleted' => $f];
            },
        ],
        'site_check' => [
            'description' => 'Revisa la web como un navegador (sin abrirlo): genera cada página publicada y devuelve los fallos: avisos de PHP, enlaces internos rotos, imágenes/CSS/JS/audio que no existen, imágenes sin alt o copiadas de otras webs, marcadores de plantilla sin rellenar, páginas sin H1 o con varios. Úsala SIEMPRE al terminar cambios y corrige lo que salga.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => site_check($s),
        ],
        'errors_list' => [
            'description' => 'La «consola» de la web: errores reales registrados en los navegadores de los visitantes (JavaScript, archivos que no cargan) y en el servidor (PHP), agrupados con contador y fecha. source: js, resource o php. Revísalo tras tocar el código personalizado (code_update) o la plantilla.',
            'scope' => 'read', 'inputSchema' => $obj(['source' => ['type' => 'string', 'enum' => ['js', 'resource', 'php']], 'limit' => ['type' => 'integer']]),
            'handler' => fn(SiteService $s, array $a) => ['errors' => errors_list($s->id(), (string) ($a['source'] ?? ''), (int) ($a['limit'] ?? 50))],
        ],
        'errors_clear' => [
            'description' => 'Vacía el registro de errores de la web (después de corregirlos), para ver si vuelven a aparecer.',
            'scope' => 'write', 'role' => 'admin', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => ['deleted' => errors_clear($s->id())],
        ],
        'translation_status' => [
            'description' => 'Estado de las traducciones: idiomas de la web y, para cada página y entrada, en qué idiomas existe y en cuáles falta. Para traducir: page_get/post_get en el idioma principal, traduce TODO el texto (títulos, bloques, SEO) y guarda con page_save/post_save indicando lang y el MISMO slug.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => function (SiteService $s, array $a) {
                $langs = $s->langs();
                $out = ['default_language' => $s->defaultLang(), 'languages' => $langs, 'pages' => [], 'posts' => []];
                foreach (['pages', 'posts'] as $t) {
                    $rows = rows("SELECT slug, lang, status, title FROM $t WHERE site_id = ? ORDER BY slug, lang", [$s->id()]);
                    $by = [];
                    foreach ($rows as $r) $by[$r['slug']][$r['lang']] = $r['status'];
                    foreach ($by as $slug => $have) $out[$t][] = ['slug' => $slug, 'languages' => $have, 'missing' => array_values(array_diff($langs, array_keys($have)))];
                }
                $s->lang = $s->defaultLang();
                $tr = $s->baseSettings()['i18n'];
                $out['site_texts_missing'] = array_values(array_filter(array_slice($langs, 1), fn($l) => empty($tr[$l]['tagline']) || empty($tr[$l]['seo']['description'])));
                return $out;
            },
        ],
        'domain_status' => [
            'description' => 'Estado de los dominios de la web y qué falta para que se vea en ellos: IP del servidor, a dónde apunta el DNS de cada dominio, si el HTTPS funciona, si el certificado es automático o hay que darlo de alta en el panel del hosting, preview_url y message_for_user (mensaje listo para pasar a la persona). Úsalo tras crear una web, tras domain_add y cuando la persona diga que ha cambiado el DNS.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => domain_guide($s),
        ],
        'domain_add' => [
            'description' => 'Añade un dominio a la web (p. ej. el que la persona ha comprado: «mitienda.com»; con primary=true pasa a ser el principal). No cambia el DNS: devuelve qué debe configurar la persona (message_for_user) y el estado actual. '
                . (server_hosting_mode() === 'auto_tls'
                    ? 'En ESTE servidor el HTTPS es automático: si el DNS ya apunta aquí, esta misma llamada genera el certificado (puede tardar hasta un minuto); si no, se generará cuando lo compruebes con domain_status tras el cambio de DNS.'
                    : 'En ESTE servidor (Plesk/cPanel u otro panel) el dominio además hay que darlo de alta A MANO en el panel del hosting (alias o subdominio apuntando a la carpeta de Universal Web) y emitir allí el certificado: díselo a la persona; tú no puedes hacerlo.'),
            'scope' => 'publish', 'role' => 'admin', 'inputSchema' => $obj(['domain' => $str('Dominio, sin https:// ni barras'), 'primary' => ['type' => 'boolean']], ['domain']),
            'handler' => function (SiteService $s, array $a) {
                $d = normalize_domains((string) $a['domain'])[0] ?? throw new UwError('Dominio no válido');
                if (filter_var($d, FILTER_VALIDATE_IP) || $d === 'localhost' || !str_contains($d, '.')) throw new UwError("$d no es un dominio público (p. ej. mitienda.com)");
                $cur = site_domains($s->id());
                // Solo se valida el nuevo: los que ya tenía la web pudo ponerlos un superadmin (localhost, IPs)
                site_set_domains($s->id(), !empty($a['primary']) ? array_merge([$d], array_diff($cur, [$d])) : array_values(array_unique(array_merge($cur, [$d]))), true);
                audit($s->id(), 'site.domain_add', $d);
                return ['domains' => site_domains($s->id())] + domain_guide(new SiteService(site_by_id($s->id())));
            },
        ],
        'manual' => [
            'description' => 'Manual de usuario del panel (el mismo de Panel → Ayuda). Sin parámetros: índice de secciones y lista de pantallas. Con section (número o palabra: «idiomas», «cookies», «copias»…): esa sección en Markdown. Con screen (clave o título de una pantalla: «settings», «Ajustes», «Páginas»…): esa pantalla campo a campo y botón a botón, en el orden en que aparecen (lo mismo que enseña el botón «Guíame» del panel). Úsalo para explicar a la persona cómo hacer algo ella misma en el panel, con sus nombres reales de menús y botones; si se pierde, dile que pulse «Guíame» abajo a la derecha.',
            'scope' => 'read', 'inputSchema' => $obj(['section' => $str('Número o palabra de la sección (opcional)'), 'screen' => $str('Pantalla del panel a explicar paso a paso (opcional; sin valor = lista)')]),
            'handler' => fn(SiteService $s, array $a) => isset($a['screen']) ? tour_explain((string) $a['screen'])
                : (trim((string) ($a['section'] ?? '')) === '' ? manual_section('') + ['screens' => tour_explain('')['screens']] : manual_section((string) $a['section'])),
        ],
        'seo_audit' => [
            'description' => 'Audita el SEO de todo el contenido publicado: títulos, meta descripciones, H1, textos alternativos, duplicados, contenido corto. Devuelve puntuación 0–100 y la lista de problemas para corregir.',
            'scope' => 'read', 'inputSchema' => $obj(),
            'handler' => fn(SiteService $s, array $a) => $s->seoAudit() + ['sitemap' => $s->url('/sitemap.xml'), 'next' => 'seo_autofix arregla solo lo mecánico; luego seo_bulk_update para lo que hay que redactar'],
        ],
        'seo_autofix' => [
            'description' => 'Arregla SOLO el SEO mecánico de toda la web, en todos los idiomas, sin sobrescribir nada escrito: textos alternativos vacíos (de la biblioteca de medios, del contexto del bloque o del nombre del archivo), meta descripciones vacías (resumen del propio contenido), extractos y portadas de entradas, alt en el Markdown, descripción de la web por idioma y alt de la biblioteca. Devuelve changes (lo hecho), skipped y needs_writing (lo que necesita que TÚ redactes, con contexto). Todo queda en el historial (page_restore lo deshace). dry_run=true para ver sin guardar. Úsalo antes de revisar a mano.',
            'scope' => 'write', 'inputSchema' => $obj(['dry_run' => ['type' => 'boolean', 'description' => 'true = solo enseñar lo que haría']]),
            'handler' => fn(SiteService $s, array $a) => seo_autofix($s, empty($a['dry_run']), 'mcp_can_edit'),
        ],
        'seo_bulk_update' => [
            'description' => 'Cambia el SEO de muchas páginas y entradas en UNA llamada (p. ej. todo lo que devolvió needs_writing). Cada elemento: type (page|post), slug, lang (opcional), y lo que cambie: seo_title (50–60 caracteres; vacío = usa el título), description (50–160), image (para redes), noindex, excerpt (solo entradas). Cada elemento falla por separado sin parar los demás.',
            'scope' => 'write', 'inputSchema' => $obj(['items' => ['type' => 'array', 'items' => $obj([
                'type' => ['type' => 'string', 'enum' => ['page', 'post']], 'slug' => $str('Slug'), 'lang' => $str('Idioma (opcional)'),
                'seo_title' => $str('Título para Google (50–60)'), 'description' => $str('Meta descripción (50–160)'), 'image' => $str('Imagen para redes'),
                'noindex' => ['type' => 'boolean'], 'excerpt' => $str('Extracto (entradas)'),
            ], ['slug'])]], ['items']),
            'handler' => fn(SiteService $s, array $a) => seo_bulk_update($s, (array) $a['items'], 'mcp_can_edit'),
        ],
        'seo_analyze' => [
            'description' => 'Análisis SEO + GEO a fondo de UNA página o entrada, sobre el HTML real que ve Google: vista previa en el buscador, longitud de título y descripción, H1/H2, palabras, legibilidad, imágenes sin alt, enlaces internos/externos, canonical, hreflang, datos estructurados (JSON-LD), og:image y señales para IAs (preguntas, cifras). Con keyword comprueba si la palabra clave está en título, descripción, H1, H2, URL, primeras 100 palabras y alt, y su densidad. Cada problema trae fix.',
            'scope' => 'read', 'inputSchema' => $obj([
                'slug' => $str('Slug (index = portada)'), 'type' => ['type' => 'string', 'enum' => ['page', 'post'], 'description' => 'page (por defecto) o post'],
                'keyword' => $str('Búsqueda objetivo, p. ej. «taller mecánico en Getafe» (opcional)'),
            ], ['slug']),
            'handler' => fn(SiteService $s, array $a) => seo_analyze($s, ($a['type'] ?? 'page') === 'post' ? 'post' : 'page', slugify((string) $a['slug']), (string) ($a['keyword'] ?? '')),
        ],
        'search_ping' => [
            'description' => 'Avisa YA a los buscadores (IndexNow: Bing —que alimenta ChatGPT y Copilot—, Yandex, Seznam, Naver) de que hay páginas nuevas o cambiadas. Ya se hace solo al publicar o cambiar algo publicado; úsalo tras un cambio grande (menús, ajustes, tema) o con urls concretas. Sin urls envía todas las del sitemap. Google no usa IndexNow: lee el sitemap.',
            'scope' => 'publish', 'inputSchema' => $obj(['urls' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'URLs completas (opcional)']]),
            'handler' => function (SiteService $s, array $a) {
                $urls = (array) ($a['urls'] ?? []);
                if (!$urls) {
                    preg_match_all('#<loc>([^<]+)</loc>#', site_check_render($s, '/sitemap.xml')['body'], $m);
                    $urls = array_map('html_entity_decode', $m[1]);
                }
                return indexnow_ping($s, array_map('strval', $urls)) + ['sitemap' => $s->url('/sitemap.xml')];
            },
        ],
    ];
}

/**
 * Un cambio por MCP que deja un campo de diseño en una fracción de lo que había casi siempre es un reemplazo a
 * medias: el agente cree que se fusiona y manda solo el trozo que tocó (18-oct, prueba real con ADRI: 6.728 → 50
 * bytes de CSS, la web se quedó sin cabecera ni fuentes). Se para salvo confirm_replace=true. Solo en MCP: en el
 * panel una persona que vacía el CSS lo está viendo.
 */
function mcp_guard_shrink(array $cur, array $a, array $fields): void
{
    // Solo vale el código que da ESTE rechazo (18-oct): con «true» un agente lo ponía siempre «por si
    // acaso» y el freno no frenaba nada. El código depende del campo y de los tamaños: no se adivina
    // ni sirve para otro recorte.
    $dado = is_string($a['confirm_replace'] ?? null) ? trim($a['confirm_replace']) : '';
    foreach ($fields as $f) {
        if (!array_key_exists($f, $a)) continue;
        $old = strlen((string) ($cur[$f] ?? ''));
        $new = strlen((string) $a[$f]);
        if ($old >= 1000 && $new < $old * 0.4) {
            $codigo = substr(hash('sha256', "uw-recorte|{$f}|{$old}|{$new}"), 0, 8); // prueba de haber visto el aviso, no un secreto
            if (hash_equals($codigo, $dado)) continue;
            throw new UwError("«{$f}» pasaría de {$old} a {$new} bytes. Cada campo se REEMPLAZA entero, no se fusiona: lee el actual, "
                . "edítalo y envíalo COMPLETO. Si de verdad quieres quitar ese contenido, repite la misma llamada con confirm_replace='{$codigo}'.");
        }
    }
}

/**
 * Un campo que el esquema declara texto no puede llegar como array u objeto: `(string)` de un
 * array da «Array» con solo un warning, y eso se GUARDABA como CSS/plantilla/contenido (revisión
 * con lentes 18-oct). Solo esto: números y booleanos se convierten bien y algunos clientes los
 * mandan así.
 */
function mcp_check_string_args(array $schema, array $args): void
{
    foreach ($schema['properties'] ?? [] as $k => $p) {
        if (($p['type'] ?? null) === 'string' && isset($args[$k]) && (is_array($args[$k]) || is_object($args[$k]))) {
            throw new UwError("«{$k}» tiene que ser texto, no una lista ni un objeto");
        }
    }
}

/** ¿Puede este token tocar contenido en ese estado? (para herramientas que recorren muchas páginas) */
function mcp_can_edit(?string $status): bool
{
    return $status !== 'published' || in_array('publish', McpContext::$token['scopes'], true);
}

function mcp_require_publish(?string $current, ?string $new): void
{
    if (($current === 'published' || $new === 'published') && !in_array('publish', McpContext::$token['scopes'], true)) {
        throw new UwError("Este token no tiene permiso 'publish': solo puede crear y editar borradores (status 'draft'). El contenido publicado requiere 'publish'.");
    }
}

final class McpContext
{
    public static array $token = [];
}

// ---------------------------------------------------------------- transporte HTTP

function mcp_handle(string $method): void
{
    header('Cache-Control: no-store');
    if ($method === 'OPTIONS') {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Authorization, Content-Type, X-MCP-Token, Mcp-Protocol-Version, Mcp-Session-Id');
        http_response_code(204);
        return;
    }
    if ($method !== 'POST') {
        // Servidor sin estado: no ofrece stream SSE por GET.
        header('Allow: POST');
        json_response(['error' => 'Usa POST con JSON-RPC 2.0. Endpoint MCP de Universal Web.'], 405);
        return;
    }

    $raw = request_header('Authorization') ?? '';
    $secret = preg_match('/^Bearer\s+(\S+)$/i', $raw, $m) ? $m[1] : (request_header('X-MCP-Token') ?? '');
    $token = $secret !== '' ? token_authenticate($secret) : null;
    if (!$token) {
        // Frena la fuerza bruta: 20 intentos fallidos por IP cada 15 minutos.
        if (!throttle('mcp-fail:' . client_ip(), 20, 900)) { json_response(['error' => 'Demasiados intentos'], 429); return; }
        header('WWW-Authenticate: Bearer realm="universal-web"');
        json_response(['error' => 'Token MCP no válido o revocado. Crea uno en el panel: Tokens de IA.'], 401);
        return;
    }
    McpContext::$token = $token;
    Actor::set('token', (int) $token['id'], 'MCP: ' . $token['name'] . ($token['owner_name'] ? " ({$token['owner_name']})" : ''));

    $body = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($body)) { json_response(mcp_error(null, -32700, 'JSON no válido'), 400); return; }

    $batch = array_is_list($body) && $body !== [];
    if ($batch && count($body) > 20) { json_response(mcp_error(null, -32600, 'Máximo 20 peticiones por lote'), 400); return; }
    $responses = [];
    foreach ($batch ? $body : [$body] as $msg) {
        $r = is_array($msg) ? mcp_dispatch($msg) : mcp_error(null, -32600, 'Petición no válida');
        if ($r !== null) $responses[] = $r;
    }
    if (!$responses) { http_response_code(202); return; } // solo notificaciones
    json_response($batch ? $responses : $responses[0]);
}

function mcp_error(mixed $id, int $code, string $message): array
{
    return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
}

function mcp_dispatch(array $msg): ?array
{
    $id = $msg['id'] ?? null;
    $method = $msg['method'] ?? null;
    if (($msg['jsonrpc'] ?? null) !== '2.0' || !is_string($method)) {
        return array_key_exists('id', $msg) ? mcp_error($id, -32600, 'Petición JSON-RPC no válida') : null;
    }
    if (!array_key_exists('id', $msg)) return null; // notificación (p. ej. notifications/initialized)
    $params = is_array($msg['params'] ?? null) ? $msg['params'] : [];
    $ok = fn(array $result) => ['jsonrpc' => '2.0', 'id' => $id, 'result' => (object) $result];

    switch ($method) {
        case 'initialize':
            $asked = (string) ($params['protocolVersion'] ?? '');
            return $ok([
                'protocolVersion' => in_array($asked, MCP_PROTOCOL_VERSIONS, true) ? $asked : MCP_PROTOCOL_VERSIONS[0],
                'capabilities' => ['tools' => ['listChanged' => false], 'resources' => ['listChanged' => false]],
                'serverInfo' => ['name' => 'universal-web', 'title' => 'Universal Web', 'version' => UW_VERSION],
                'instructions' => mcp_instructions() . mcp_token_note(),
            ]);
        case 'ping':
            return $ok([]);
        case 'tools/list':
            return $ok(['tools' => mcp_tools_for_token()]);
        case 'tools/call':
            return $ok(mcp_call((string) ($params['name'] ?? ''), is_array($params['arguments'] ?? null) ? $params['arguments'] : []));
        case 'resources/list':
            return $ok(['resources' => [['uri' => 'uw://manual', 'name' => 'Manual de usuario', 'mimeType' => 'text/markdown',
                'description' => 'Cómo se usa el panel de Universal Web, sección a sección (también con la herramienta manual)']]]);
        case 'resources/read':
            if (($params['uri'] ?? '') !== 'uw://manual') return mcp_error($id, -32602, 'Recurso desconocido');
            return $ok(['contents' => [['uri' => 'uw://manual', 'mimeType' => 'text/markdown', 'text' => (string) @file_get_contents(UW_ROOT . '/docs/MANUAL.md')]]]);
        case 'prompts/list':
            return $ok(['prompts' => []]);
        default:
            return mcp_error($id, -32601, "Método no soportado: $method");
    }
}

function mcp_is_global(): bool
{
    return McpContext::$token['site_id'] === null;
}

function mcp_token_note(): string
{
    $t = McpContext::$token;
    $missing = ['publish' => 'publicar o editar contenido ya publicado', 'messages' => 'leer los mensajes de contacto', 'code' => 'cambiar el CSS/JS', 'backup' => 'hacer copias de seguridad'];
    $lacks = '';
    foreach ($missing as $sc => $what) if (!in_array($sc, $t['scopes'], true)) $lacks .= " Sin el permiso «{$sc}» no puedes {$what} (esas herramientas no aparecen).";
    $end = ' Si algo no se puede hacer, dilo claramente y explica qué permiso o rol hace falta; nunca digas que lo has hecho.';
    if (mcp_is_global()) return "\n\nEste token es GLOBAL: usa sites_list y pasa el parámetro \"site\" (slug de la web) en cada herramienta. Permisos: " . implode(', ', $t['scopes']) . '.' . $lacks . $end;
    $site = site_by_id((int) $t['site_id']);
    $role = ['super' => 'superadmin', 'admin' => 'administrador', 'editor' => 'editor'][$t['owner_role']] ?? $t['owner_role'];
    $note = "\n\nEste token gestiona la web «{$site['name']}». Actúas con el rol de {$role} de su dueño. Permisos del token: " . implode(', ', $t['scopes']) . '.';
    if (!role_at_least($t['owner_role'], 'admin')) {
        $note .= ' Como editor NO puedes cambiar los ajustes de la web (nombre, teléfono, email, dirección, menú, colores, tema, logo, redes) ni el CSS/JS: si te lo piden, explica que debe hacerlo un administrador desde el panel (Ajustes) o con un token suyo.';
    }
    return $note . $lacks . $end;
}

function mcp_tools_for_token(): array
{
    $scopes = McpContext::$token['scopes'];
    $out = [];
    $tools = mcp_tools();
    if (mcp_is_global()) {
        $tools['site_create'] = ['description' => 'Crea una web nueva (solo tokens globales de superadmin). Después configúrala con site_update (pasando su slug en "site") y crea sus páginas con page_save. Sin dominio (domains vacío) se revisa con preview_url; con dominios, queda accesible en cuanto su DNS apunte al servidor.',
            'scope' => 'publish', 'role' => 'super', 'inputSchema' => ['type' => 'object', 'properties' => (object) [
                'name' => ['type' => 'string', 'description' => 'Nombre de la web'], 'slug' => ['type' => 'string', 'description' => 'Identificador corto (opcional)'],
                'domains' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Dominios; el primero es el principal. Opcional: [] crea la web sin dominio (se ve con preview_url)'],
                'theme' => ['type' => 'string', 'description' => 'Elige el tema según el negocio: ' . theme_guide_text() . '. Después pon accent con el color de la marca o el sugerido (site_update).'],
            ], 'required' => ['name']]];
        $tools['sites_list'] = ['description' => 'Lista todas las webs (slug, nombre, dominios, url pública o null si aún no tiene dominio) y panel_url (dirección del panel). Usa el slug como parámetro "site" en las demás herramientas.',
            'scope' => 'read', 'inputSchema' => ['type' => 'object', 'properties' => (object) []]];
    }
    foreach ($tools as $name => $t) {
        if (!mcp_tool_allowed($t)) continue;
        $schema = $t['inputSchema'];
        if (in_array($name, MCP_LANG_TOOLS, true)) {
            $schema['properties'] = (object) ((array) $schema['properties'] + ['lang' => ['type' => 'string', 'description' => 'Idioma (es, en, ro…). Por defecto, el principal de la web. Ver languages en site_get.']]);
        }
        if (mcp_is_global() && !in_array($name, ['sites_list', 'site_create'], true)) {
            $schema['properties'] = (object) (['site' => ['type' => 'string', 'description' => 'Slug de la web (ver sites_list)']] + (array) $schema['properties']);
            $schema['required'] = array_merge(['site'], $schema['required'] ?? []);
        }
        $out[] = ['name' => $name, 'description' => $t['description'], 'inputSchema' => $schema,
            'annotations' => ['readOnlyHint' => $t['scope'] === 'read', 'destructiveHint' => str_ends_with($name, '_delete')]];
    }
    return $out;
}

/** Permiso del token (scope) y rol de su creador (p. ej. site_update exige admin). */
function mcp_tool_allowed(array $tool): bool
{
    $scopes = McpContext::$token['scopes'];
    // Las herramientas "write" también valen para tokens con "publish".
    $scopeOk = in_array($tool['scope'], $scopes, true) || ($tool['scope'] === 'write' && in_array('publish', $scopes, true));
    return $scopeOk && role_at_least(McpContext::$token['owner_role'], $tool['role'] ?? 'editor');
}

function mcp_call(string $name, array $args): array
{
    try {
        if ($name === 'site_create' && mcp_is_global() && McpContext::$token['owner_role'] === 'super' && in_array('publish', McpContext::$token['scopes'], true)) {
            $site = site_create((string) ($args['name'] ?? ''), (string) ($args['slug'] ?? ''), (array) ($args['domains'] ?? []), (string) ($args['theme'] ?? 'default'));
            $result = ['site' => ['slug' => $site['slug'], 'name' => $site['name'], 'theme' => $site['theme'], 'domains' => site_domains((int) $site['id'])],
                'preview_url' => site_preview_link($site),
                'domain_setup' => array_intersect_key(domain_guide(new SiteService($site), false), array_flip(['server_ip', 'server_ipv6', 'hosting', 'dns_records', 'dns_howto', 'steps', 'message_for_user'])),
                'next' => 'Pasa a la persona domain_setup.message_for_user: con preview_url ve la web ya (7 días) y ahí tiene lo que falta para su dominio. Cuando diga que ha cambiado el DNS, comprueba con domain_status. Si aún no sabes su dominio, pregúntaselo y añádelo con domain_add.'];
        } elseif ($name === 'sites_list' && mcp_is_global()) {
            $result = ['sites' => array_map(fn($s) => ['slug' => $s['slug'], 'name' => $s['name'], 'theme' => $s['theme'], 'domains' => $d = site_domains((int) $s['id']),
                'url' => $d ? site_base_url($s) : null], sites_all()), 'panel_url' => panel_url()];
        } else {
            $tool = mcp_tools()[$name] ?? throw new UwError("Herramienta desconocida: $name");
            if (!mcp_tool_allowed($tool)) {
                throw new UwError(isset($tool['role']) && !role_at_least(McpContext::$token['owner_role'], $tool['role'])
                    ? "$name requiere que el dueño del token sea {$tool['role']} de la web"
                    : "Este token no tiene el permiso '{$tool['scope']}' necesario para $name");
            }
            foreach ($tool['inputSchema']['required'] ?? [] as $req) {
                if (!isset($args[$req]) || $args[$req] === '') throw new UwError("Falta el parámetro obligatorio '$req'");
            }
            mcp_check_string_args($tool['inputSchema'], $args);
            if (mcp_is_global()) {
                $site = site_by_slug((string) ($args['site'] ?? '')) ?? throw new UwError("Falta 'site' o no existe esa web. Usa sites_list.");
                unset($args['site']);
            } else {
                $site = site_by_id((int) McpContext::$token['site_id']);
            }
            $svc = new SiteService($site);
            if (isset($args['lang']) && $args['lang'] !== '' && in_array($name, MCP_LANG_TOOLS, true)) $svc->setLang((string) $args['lang']);
            unset($args['lang']);
            $result = ($tool['handler'])($svc, $args);
        }
        return ['content' => [['type' => 'text', 'text' => json_enc($result, true)]], 'structuredContent' => (object) $result, 'isError' => false];
    } catch (UwError $ex) {
        return ['content' => [['type' => 'text', 'text' => 'Error: ' . $ex->getMessage()]], 'isError' => true];
    } catch (Throwable $ex) {
        error_log('[universal-web mcp] ' . $ex);
        return ['content' => [['type' => 'text', 'text' => 'Error interno al ejecutar la herramienta.']], 'isError' => true];
    }
}

/** Resumen compacto de bloques para que la IA ubique índices: «0: hero — Título · 1: features — …». */
function mcp_blocks_summary(array $blocks): string
{
    return implode(' · ', array_map(function ($i, $b) {
        $d = (array) ($b['data'] ?? []);
        $label = (string) ($d['title'] ?? $d['eyebrow'] ?? $d['alt'] ?? '');
        if ($label === '' && isset($d['body'])) $label = mb_substr(strip_tags((string) $d['body']), 0, 40);
        return "$i: {$b['type']}" . ($label !== '' ? ' — ' . mb_substr($label, 0, 50) : '');
    }, array_keys(array_values($blocks)), array_values($blocks)));
}

function mcp_history_out(array $versions): array
{
    return ['versions' => $versions] + ($versions ? [] : ['note' => 'Aún no hay versiones: se guardan al editar. Si la borraste, búscala con trash_list.']);
}

/** Manual de usuario (docs/MANUAL.md) para la IA: índice o una sección por número o palabra. */
function manual_section(string $q): array
{
    $md = (string) @file_get_contents(UW_ROOT . '/docs/MANUAL.md');
    $parts = preg_split('/^(?=## (?:\d+\. |Anexo))/m', $md);
    $sections = array_values(array_filter($parts, fn($p) => str_starts_with($p, '## ')));
    $titles = array_map(fn($p) => trim(substr(strtok($p, "\n"), 3)), $sections);
    if (trim($q) === '') return ['sections' => $titles, 'how' => 'Pide una con section (número o palabra).', 'panel_url' => '/admin/help'];
    $q = mb_strtolower(trim($q));
    foreach ($sections as $i => $sec) {
        if (preg_match('/^' . preg_quote($q, '/') . '\./', $titles[$i]) || str_contains(mb_strtolower($titles[$i]), $q)) return ['section' => $titles[$i], 'markdown' => trim($sec)];
    }
    foreach ($sections as $i => $sec) if (str_contains(mb_strtolower($sec), $q)) return ['section' => $titles[$i], 'markdown' => trim($sec)];
    return ['error' => "No hay ninguna sección sobre «{$q}»", 'sections' => $titles];
}

