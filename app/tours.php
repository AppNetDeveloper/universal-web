<?php
declare(strict_types=1);
// Universal Web · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Guías paso a paso del panel («Guíame»): por pantalla, qué es cada campo y cada botón. Una sola fuente para dos usos:
 * el panel las recorre resaltando cada elemento (admin.js) y el MCP `manual` con `screen` se las da a la IA para que
 * explique la pantalla con las mismas palabras.
 *
 * Cada paso: [selector, título, texto]. «card:SELECTOR» resalta la tarjeta que contiene ese elemento; null = paso sin
 * elemento (introducción). Si un selector no está en la pantalla (permisos, web sin idiomas…), el paso se salta solo.
 * El texto es la clave de traducción (español), como en el resto del panel.
 */
function tours(): array
{
    return [
        'dashboard' => ['title' => 'Panel', 'path' => '/', 'steps' => [
            [null, 'Bienvenida', 'Este es el resumen de la web que tienes elegida arriba a la izquierda. Desde aquí llegas a todo lo demás.'],
            ['#site-switch', 'Web actual', 'Si gestionas varias webs, aquí cambias de una a otra. Todo lo que hagas en el panel se aplica a la web elegida.'],
            ['.site-link', 'Ver web', 'Abre la web pública en otra pestaña, tal como la ven los visitantes.'],
            ['.page-head .actions', 'Crear contenido', '«Nueva entrada» es para noticias o artículos del blog; «Nueva página» para páginas fijas como Servicios o Contacto.'],
            ['.stats', 'Cifras', 'Páginas, entradas, mensajes sin leer, archivos y la puntuación SEO. Pulsa cualquiera para ir a esa sección.'],
            ['card:.feed', 'Actividad reciente', 'Quién ha cambiado qué y cuándo, tanto personas como la IA. Sirve para saber qué ha hecho ADRI.'],
            ['card:#mcp-url', 'Conectar con IA', 'La dirección MCP para conectar una IA (ADRI, Claude…). Necesita un token: créalo con el botón de abajo.'],
            ['a[href$="/help"]', 'Ayuda', 'El manual completo. En cada pantalla tienes además el botón «Guíame» para ver esta explicación.'],
        ]],
        'pages' => ['title' => 'Páginas', 'path' => '/pages', 'steps' => [
            [null, 'Páginas', 'Las páginas fijas de la web: portada, servicios, contacto, aviso legal… La portada es la que tiene el slug «index».'],
            ['.page-head .btn-primary', 'Nueva página', 'Crea una página vacía y te lleva al editor de bloques.'],
            ['table', 'Lista', 'Cada fila es una página. «Estado» dice si está publicada o es un borrador que solo ves tú.'],
            ['.lang-cells', 'Idiomas', 'Verde = traducción publicada, gris = borrador, «+» = sin traducir. Pulsa «+» para crear la traducción.'],
            ['.row-actions', 'Acciones', '«Editar» abre el editor; «Ver» la abre en la web (los borradores con un enlace temporal); «Borrar» la manda a la papelera 30 días.'],
        ]],
        'page_edit' => ['title' => 'Editar página', 'path' => '/pages/edit', 'steps' => [
            [null, 'Editor de páginas', 'Una página se construye con bloques: portada, texto, tarjetas, preguntas, contacto… Cada bloque tiene sus propios campos.'],
            ['[name=title]', 'Título', 'El nombre de la página. Sale en la pestaña del navegador y en Google si no pones un título SEO.'],
            ['[name=slug]', 'Slug', 'La dirección de la página (/servicios). Se genera sola del título. «index» es la portada. Si lo cambias, la dirección antigua redirige sola a la nueva.'],
            ['[name=status]', 'Estado', 'Borrador: solo la ves tú. Publicada: la ve todo el mundo.'],
            ['#block-editor', 'Bloques', 'Cada caja es un bloque de la página, en el orden en que sale. Con las flechas los reordenas y con la papelera los quitas.'],
            ['#add-block-bar', 'Añadir bloque', 'Elige el tipo de bloque y pulsa «+ Añadir bloque»: aparece al final, listo para rellenar.'],
            ['#json-toggle', 'Editar JSON', 'Para usuarios avanzados: muestra la página como código. Normalmente no hace falta.'],
            ['card:[name=seo_title]', 'SEO', 'Cómo sale la página en Google y al compartirla: título, descripción de 50 a 160 caracteres e imagen. «No indexar» la oculta de los buscadores.'],
            ['.savebar', 'Guardar', 'Guarda los cambios. Cada guardado crea una versión: si te equivocas, la recuperas en «Historial».'],
            ['details summary', 'Historial', 'Las versiones anteriores de esta página. «Restaurar» vuelve a una de ellas sin perder la actual.'],
        ]],
        'posts' => ['title' => 'Entradas', 'path' => '/posts', 'steps' => [
            [null, 'Entradas', 'Las entradas son las noticias o artículos del blog, ordenados por fecha.'],
            ['.page-head .btn-primary', 'Nueva entrada', 'Abre el editor para escribir un artículo nuevo.'],
            ['.tabs', 'Filtros', 'Todas, solo publicadas o solo borradores. Si la web tiene varios idiomas, también eliges el idioma.'],
            ['table', 'Lista', 'El estado indica si está publicada, programada para una fecha futura o en borrador.'],
            ['.row-actions', 'Acciones', 'Editar, ver en la web o borrar (va a la papelera 30 días).'],
        ]],
        'post_edit' => ['title' => 'Editar entrada', 'path' => '/posts/edit', 'steps' => [
            ['[name=title]', 'Título', 'El titular del artículo.'],
            ['[name=slug]', 'Slug', 'La dirección del artículo (/blog/mi-articulo). Se genera sola del título.'],
            ['[name=excerpt]', 'Extracto', 'Un resumen de una o dos frases. Sale en la lista del blog.'],
            ['[name=content]', 'Contenido', 'El texto del artículo. Admite Markdown: ## para subtítulos, **negrita**, listas con guion. Un subtítulo en forma de pregunta («## ¿…?») cuenta como pregunta frecuente para Google y las IAs.'],
            ['[name=status]', 'Estado', 'Borrador o publicada.'],
            ['[name=published_at]', 'Fecha de publicación', 'Con una fecha futura, la entrada se publica sola ese día. Vacía = ahora.'],
            ['[name=author]', 'Autor', 'El nombre que firma el artículo.'],
            ['[name=tags]', 'Etiquetas', 'Temas del artículo, separados por comas.'],
            ['[name=cover_image]', 'Imagen de portada', 'La imagen principal. Escribe /media/… o elige una de las sugerencias (las de Medios).'],
            ['[name=audio]', 'Audio', 'Opcional: un MP3 para el botón «Escuchar este artículo».'],
            ['card:[name=seo_title]', 'SEO', 'Título y descripción para Google y para compartir en redes.'],
            ['.savebar', 'Guardar', 'Guarda y crea una versión en el historial. Para ver el resultado, guarda y pulsa «Vista previa».'],
        ]],
        'media' => ['title' => 'Medios', 'path' => '/media', 'steps' => [
            [null, 'Medios', 'Aquí están las imágenes, PDF, vídeos y audios de la web. Las imágenes grandes se reducen y se pasan a WebP solas, y se les quitan datos privados como la ubicación GPS.'],
            ['[name="files[]"]', 'Subir archivos', 'Elige uno o varios archivos de tu ordenador o del móvil.'],
            ['.upload [name=alt]', 'Texto alternativo', 'Describe la imagen en una frase («Equipo instalando placas solares»). Lo leen Google y las personas ciegas.'],
            ['.upload .btn-primary', 'Subir', 'Sube los archivos elegidos.'],
            ['card:[name=url]', 'Añadir desde URL', 'Descarga una imagen de una dirección web y la guarda aquí. Usa solo imágenes tuyas o con permiso.'],
            ['.media-grid', 'Biblioteca', 'Tus archivos. La etiqueta «IA» marca imágenes generadas con inteligencia artificial.'],
            ['.media-actions', 'Acciones', '«Copiar URL» copia la dirección para pegarla en una página; «Borrar» lo elimina (las páginas que lo usen mostrarán un hueco).'],
        ]],
        'messages' => ['title' => 'Mensajes', 'path' => '/messages', 'steps' => [
            [null, 'Mensajes', 'Lo que envían los visitantes desde el formulario de contacto de la web. Los no leídos salen marcados.'],
            ['.msg summary', 'Abrir un mensaje', 'Pulsa la fila para leerlo entero, con el email y la página desde la que se envió.'],
            ['.msg .actions', 'Acciones', '«Responder» abre tu correo; también puedes marcarlo como leído, reenviar el aviso por email o borrarlo.'],
        ]],
        'seo' => ['title' => 'SEO', 'path' => '/seo', 'steps' => [
            [null, 'SEO y GEO', 'SEO es lo bien preparada que está la web para Google. GEO, lo mismo para las IAs que responden preguntas (ChatGPT, Perplexity, Gemini, Claude).'],
            ['.score-card', 'Puntuación', 'De 0 a 100. Debajo, cuántos errores, avisos y sugerencias hay.'],
            ['table', 'Problemas', 'Cada fila dice qué pasa, dónde y cómo arreglarlo. Empieza por los errores en rojo.'],
            ['[name=from]', 'Redirecciones 301', 'Si una página cambió de dirección o vienes de otra web, pon aquí la ruta antigua y la nueva para no perder visitas ni posiciones en Google.'],
        ]],
        'errors' => ['title' => 'Errores', 'path' => '/errors', 'steps' => [
            [null, 'Errores', 'Revisa la web entera: enlaces rotos, imágenes que faltan, páginas lentas o fallos de JavaScript que ven los visitantes.'],
            ['.page-head .btn-primary, a[href*="check=1"]', 'Revisar ahora', 'Recorre todas las páginas y te dice qué falla y dónde.'],
        ]],
        'trash' => ['title' => 'Papelera', 'path' => '/trash', 'steps' => [
            [null, 'Papelera', 'Lo que se borra (páginas y entradas) se queda aquí 30 días. Después desaparece.'],
            ['table', 'Restaurar', 'Pulsa «Restaurar» para devolverlo a su sitio, con su contenido y su dirección.'],
        ]],
        'tokens' => ['title' => 'Tokens de IA', 'path' => '/tokens', 'steps' => [
            [null, 'Tokens de IA', 'Un token es la llave que deja a una IA (ADRI, Claude…) trabajar en la web. Cada IA o persona debería tener el suyo, para poder quitárselo sin afectar a las demás.'],
            ['card:[name=name]', 'Crear token', 'Ponle un nombre que diga de quién es («ADRI de Marta»).'],
            ['[name="scopes[]"]', 'Permisos', 'Leer = solo mirar. Escribir = crear y cambiar borradores. Publicar = poner cosas en la web pública.'],
            ['[name=target]', 'Alcance', '«Solo esta web» limita la llave a esta web. «Todas» (solo superadmin) sirve para crear webs nuevas.'],
            ['#tok-raw', 'Copiar el token', 'Cópialo en cuanto lo crees: no se vuelve a mostrar. Si lo pierdes, crea otro y revoca el viejo.'],
            ['table', 'Tokens existentes', 'Cuándo se usó cada uno por última vez. «Revocar» lo desactiva al momento.'],
        ]],
        'settings' => ['title' => 'Ajustes', 'path' => '/settings', 'steps' => [
            [null, 'Ajustes de la web', 'Los datos generales de la web. Nada se guarda hasta que pulsas «Guardar ajustes», abajo del todo.'],
            ['[name=name]', 'Nombre de la web', 'El nombre de la empresa o del proyecto. Sale en la cabecera y en Google.'],
            ['[name=tagline]', 'Eslogan', 'Una frase corta que dice a qué os dedicáis.'],
            ['[name=language]', 'Idioma', 'El idioma principal (es, en, ro…). Los demás idiomas se añaden en «Idiomas».'],
            ['[name=theme]', 'Tema', 'El diseño de la web. Puedes ver cada tema antes de aplicarlo en «Temas».'],
            ['[name=accent]', 'Color de acento', 'El color de los botones y los detalles.'],
            ['[name=logo]', 'Logo', 'La dirección del logo (/media/…). Súbelo antes en Medios.'],
            ['[name=footer_text]', 'Texto del pie', 'Lo que sale abajo del todo en cada página.'],
            ['card:[name=business_type]', 'Visibilidad en IAs', 'Tipo de negocio, razón social, zona de servicio y año de fundación. Con esto las IAs entienden quiénes sois y dónde trabajáis. No pongas datos que no sean ciertos.'],
            ['[name=ai_crawlers]', 'Rastreadores de IA', 'Si dejas que las IAs lean la web. «Permitir todo» da más visibilidad.'],
            ['card:[name=private_enabled]', 'Acceso privado', 'Para enseñar una web a un cliente antes de publicarla: solo se ve con contraseña y Google no la indexa.'],
            ['[name=domains]', 'Dominios', 'Un dominio por línea; el primero es el principal. El dominio tiene que apuntar a este servidor (en Plesk, darlo de alta con la raíz httpdocs/public).'],
            ['#menu-editor', 'Menú', 'Los enlaces del menú principal. «+ Añadir enlace» añade uno; con las flechas cambias el orden.'],
            ['[name=cta_label]', 'Botón destacado', 'El botón de color de la cabecera («Pedir presupuesto») y a dónde lleva.'],
            ['[name=utility_menu]', 'Enlaces secundarios', 'La barra pequeña de arriba. Uno por línea: Texto | URL.'],
            ['card:[name="contact[email]"]', 'Contacto', 'Email, teléfono y dirección. Salen en el pie, en el bloque de contacto y en los datos para Google.'],
            ['card:[name="seo[description]"]', 'SEO por defecto', 'La descripción e imagen que se usan en las páginas que no tienen las suyas.'],
            ['.savebar', 'Guardar ajustes', 'Guarda todo lo de esta pantalla.'],
        ]],
        'languages' => ['title' => 'Idiomas', 'path' => '/languages', 'steps' => [
            [null, 'Idiomas', 'Para tener la web en varios idiomas: cada idioma va en su propia dirección (/en/, /ro/…).'],
            ['card:[name="languages[]"]', 'Idiomas de la web', 'Marca los idiomas adicionales y pulsa «Guardar idiomas».'],
            ['table', 'Estado de las traducciones', 'Cuántas páginas y entradas están traducidas en cada idioma.'],
            ['card:[name=menu]', 'Textos por idioma', 'Eslogan, pie, menú y botón traducidos a cada idioma. Cada idioma se guarda con su propio botón.'],
        ]],
        'themes' => ['title' => 'Temas', 'path' => '/themes', 'steps' => [
            [null, 'Temas', 'El tema es el diseño de la web: colores, tipografías y forma de los bloques. Cambiarlo no toca el contenido.'],
            ['.theme-card', 'Cada tema', '«Ideal para» dice a qué tipo de negocio encaja. «Vista previa» lo enseña con tu contenido, solo a ti.'],
            ['.theme-card .btn-primary', 'Aplicar', 'Pone el tema en la web pública al momento.'],
        ]],
        'design' => ['title' => 'Diseño y código', 'path' => '/design', 'steps' => [
            [null, 'Diseño y código', 'Retoques a medida sobre el tema: estilos, código y favicon. Si no sabes CSS, pídeselo a ADRI.'],
            ['#design-tabs', 'Pestañas', 'CSS (estilos), JavaScript, código del <head> (por ejemplo verificaciones de Google), favicon e historial.'],
            ['#design-preview', 'Vista previa', 'Mira el cambio antes de guardarlo.'],
            ['#design-save', 'Guardar', 'Aplica los cambios en la web. Cada guardado crea una versión; en «Historial» puedes volver atrás.'],
        ]],
        'template' => ['title' => 'Plantilla HTML', 'path' => '/template', 'steps' => [
            [null, 'Plantilla HTML', 'Un diseño completamente a medida (cabecera, pie, estructura). Es avanzado: normalmente lo prepara ADRI.'],
            ['[name=enabled]', 'Usar esta plantilla', 'Si está desmarcado, la plantilla solo se ve en «Vista previa» y la web sigue con su tema.'],
            ['[name=layout]', 'HTML', 'La estructura de la página, con marcadores como {{{content}}} donde va el contenido.'],
            ['card:table', 'Historial', 'Versiones anteriores para volver atrás.'],
        ]],
        'cookies' => ['title' => 'Cookies', 'path' => '/cookies', 'steps' => [
            [null, 'Cookies', 'El aviso de cookies que exige la ley cuando usas analítica o publicidad. Las herramientas solo se cargan si el visitante acepta.'],
            ['[name=enabled]', 'Mostrar el aviso', 'Actívalo si usas Google Analytics, Tag Manager, Clarity o el píxel de Meta.'],
            ['[name=position]', 'Formato', 'Barra abajo o caja en una esquina. Aceptar y Rechazar salen igual de visibles, como pide la ley.'],
            ['card:[name=gtm]', 'Analítica y marketing', 'Pega aquí los identificadores de tus herramientas (GTM-…, G-…, el de Clarity o el de Meta).'],
            ['[name=reset_consent]', 'Volver a preguntar', 'Si añades herramientas nuevas, márcalo para que todos los visitantes vuelvan a elegir.'],
        ]],
        'forms' => ['title' => 'Formularios', 'path' => '/forms', 'steps' => [
            [null, 'Formularios', 'Qué pasa cuando alguien escribe desde el formulario de contacto. El mensaje siempre se guarda en «Mensajes»; aquí decides además si te llega por email.'],
            ['[name=notify_enabled]', 'Aviso por email', 'Te manda un email con cada mensaje nuevo.'],
            ['[name=recipients]', 'Destinatarios', 'A quién llega el aviso. Una dirección por línea.'],
            ['#transport', 'Método de envío', 'SMTP (recomendado) usa una cuenta de correo real y llega mejor; mail() usa el del hosting.'],
            ['#smtp-fields', 'Datos SMTP', 'Servidor, puerto, usuario y contraseña de la cuenta de correo que envía. Te los da tu proveedor de correo.'],
            ['[name=autoreply_enabled]', 'Respuesta automática', 'Manda una confirmación a quien escribe («Hemos recibido tu mensaje»).'],
            ['[name=turnstile_enabled]', 'Antispam', 'Cloudflare Turnstile frena los mensajes de robots sin pedir nada a las personas.'],
        ]],
        'backups' => ['title' => 'Copias de seguridad', 'path' => '/backups', 'steps' => [
            [null, 'Copias de seguridad', 'Una copia guarda la web entera (contenido, imágenes y ajustes) en un archivo. Con ella puedes recuperar la web si algo sale mal.'],
            ['table', 'Copias guardadas', 'Descarga una copia a tu ordenador o bórrala.'],
            ['[name=auto_backup]', 'Copias automáticas', 'Hace una copia sola cada día y guarda las últimas que indiques.'],
            ['[name=file]', 'Restaurar', 'Sube una copia para volver a ese momento. Pide tu contraseña por seguridad.'],
        ]],
        'users' => ['title' => 'Usuarios', 'path' => '/users', 'steps' => [
            [null, 'Usuarios', 'Las personas que pueden entrar al panel de esta web.'],
            ['table', 'Lista', 'Editor: crea y cambia contenido. Administrador: además ajustes, diseño y usuarios.'],
            ['card:[name=email]', 'Añadir usuario', 'Email, nombre, contraseña inicial y rol. Pídele que la cambie en «Mi cuenta» al entrar. Un superadmin también puede crear otro superadmin confirmando su contraseña.'],
        ]],
        'publish' => ['title' => 'Publicación', 'path' => '/publish', 'steps' => [
            [null, 'Publicación', 'Para publicar la web como archivos estáticos en otro servidor o hosting. Si la web se sirve desde aquí mismo, no hace falta.'],
        ]],
        'sites' => ['title' => 'Webs', 'path' => '/sites', 'steps' => [
            [null, 'Webs', 'Todas las webs de esta instalación. Cada una tiene su contenido, su diseño y sus dominios.'],
            ['table', 'Lista', '«Gestionar» la elige en el panel. «Vista previa sin dominio» da un enlace de 7 días para enseñarla antes de tener el dominio.'],
            ['card:[name=slug]', 'Crear web', 'Nombre, identificador corto (slug), tema y dominios (opcional). Con «páginas iniciales» crea Inicio y Contacto.'],
        ]],
        'help' => ['title' => 'Ayuda', 'path' => '/help', 'steps' => [
            ['.manual-toc', 'Índice', 'Las secciones del manual. Pulsa una para saltar a ella.'],
            ['#manual-pdf', 'Descargar PDF', 'Guarda el manual completo como PDF o imprímelo.'],
            ['#tour-start', 'Guíame', 'En cada pantalla del panel este botón te explica, paso a paso, cada campo y cada botón.'],
        ]],
    ];
}

/** Guía de una pantalla con los textos traducidos al idioma del panel (para admin.js). */
function tour_for(string $view): ?array
{
    $t = tours()[$view] ?? null;
    if (!$t) return null;
    return ['title' => t($t['title']), 'steps' => array_map(fn($s) => [$s[0], t($s[1]), t($s[2])], $t['steps'])];
}

/** Para el MCP: lista de pantallas o la explicación de una (por clave, título o ruta). */
function tour_explain(string $screen): array
{
    $all = tours();
    $q = mb_strtolower(trim($screen));
    if ($q !== '') foreach ($all as $k => $t) {
        if ($q === $k || $q === mb_strtolower($t['title']) || $q === $t['path'] || $q === ltrim($t['path'], '/')) {
            return ['screen' => $k, 'title' => $t['title'], 'where' => 'Panel → ' . $t['title'] . ' (' . ADM . ($t['path'] === '/' ? '/' : $t['path']) . ')',
                'steps' => array_map(fn($s) => ['step' => $s[1], 'explain' => $s[2]], $t['steps'])];
        }
    }
    return ['screens' => array_map(fn($k, $t) => ['screen' => $k, 'title' => $t['title']], array_keys($all), $all),
        'how' => 'Pide una con screen (clave o título), p. ej. screen="settings" o screen="Ajustes".'];
}
