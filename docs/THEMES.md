# Universal Web · Manual de temas

Este manual explica cómo usar, crear y publicar un tema (plantilla de diseño) de Universal Web.
Va dentro de cada tema que descargas desde el panel (**Administración → Temas → Descargar con manual**).

> **Idea clave:** el *back* no cambia nunca. El contenido (páginas por bloques, entradas, medios), el SEO/GEO,
> los idiomas, el formulario, la API JSON y el MCP son los mismos con cualquier tema. Un tema solo decide
> **cómo se pinta** ese contenido.

---

## 1. Para usuarios: instalar y cambiar de tema

1. Entra en el panel → **Temas**.
2. Pulsa **Vista previa** en el tema que quieras: se abre tu web con ese tema solo para ti (2 horas, nadie más lo ve).
   Navega por todas las páginas; para salir, pulsa **Salir** en la barra superior.
3. Si te gusta, pulsa **Aplicar**. El cambio es inmediato y reversible (vuelve a aplicar el anterior).
4. Si la web se publica en estático en otro servidor, pulsa después **Publicación → Publicar ahora**.

Los colores (color de acento), el logo, los menús y los textos se cambian en **Ajustes**; el CSS y JavaScript
propios de una web en **Diseño y código**, sin tocar el tema.

### Subir un tema nuevo

Un tema contiene código PHP que se ejecuta en el servidor, así que la subida está **desactivada por defecto**.
Para activarla, un administrador del servidor añade a `data/config.php`:

```php
'allow_theme_upload' => true,
```

Además, solo un **superadmin** con **verificación en dos pasos** activa puede subir temas. Antes de instalar se
revisa: estructura, tipos de archivo, sintaxis PHP y funciones peligrosas (es una red de seguridad, no un antivirus:
sube solo temas de fuentes de confianza). Alternativa sin panel: copia la carpeta del tema en `themes/` por FTP/SSH.

---

## 2. Para desarrolladores: estructura de un tema

```
mi-tema/
├── theme.json          ← obligatorio: nombre y datos del tema
├── layout.php          ← obligatorio: el «marco» HTML de todas las páginas
├── page.php            ← opcional: página normal (bloques)
├── blog.php            ← opcional: listado del blog
├── post.php            ← opcional: una entrada
├── 404.php             ← opcional: página no encontrada
├── partials_card.php   ← opcional: tarjeta de entrada (usada por blog.php)
├── blocks/             ← opcional: una plantilla por tipo de bloque (hero.php, faq.php…)
│   └── _head.php       ← cabecera común de sección (antetítulo, título, subtítulo)
├── assets/             ← CSS, JS, imágenes y fuentes (se sirven en /theme-assets/mi-tema/…)
│   ├── style.css
│   ├── site.js
│   └── screenshot.png  ← opcional: captura para la galería del panel (16:10, p. ej. 1440×900)
└── MANUAL.md           ← este manual (opcional)
```

**Herencia:** cualquier plantilla que tu tema no tenga se toma del tema `default`. Un tema mínimo puede ser solo
`theme.json`, `layout.php` y `assets/style.css` (así está hecho el tema *Noche*). Para empezar con todo, descarga
**Default**, que trae todas las plantillas.

### theme.json

```json
{
  "slug": "mi-tema",
  "name": "Mi tema",
  "description": "Elegante para restaurantes",
  "author": "Tu nombre o empresa",
  "version": "1.0",
  "css_hints": {
    "variables_tema": ["--accent (color de acento de Ajustes)", "--ink (color oscuro)"],
    "clases_tema": [".hero", ".card", ".site-header"]
  }
}
```

- `slug`: identificador (minúsculas, números y guiones). Es el nombre de la carpeta. No puede ser `default`,
  `noche` ni `corporate` (los temas de serie no se sobrescriben).
- `css_hints`: opcional. La IA lo recibe con la herramienta MCP `code_get` para escribir CSS a medida que encaje
  con tu tema. Muy recomendable.

---

## 3. Variables disponibles en las plantillas

En **todas** las plantillas (layout, páginas y bloques):

| Variable | Qué es |
|---|---|
| `$svc` | `SiteService` de la web actual (ver métodos abajo) |
| `$site` | fila de la web: `name`, `slug`, `theme`… |
| `$s` | ajustes ya traducidos al idioma actual: `tagline`, `logo`, `accent`, `menu`, `utility_menu`, `cta`, `contact`, `social`, `seo`, `footer_text`, `language`, `business` |
| `$path` | ruta actual con prefijo de idioma (`/`, `/servicios`, `/en/blog`) |
| `$langs` | selector de idioma: lista de `[código, nombre, url, activo]` (vacía si la web tiene un solo idioma) |

Solo en `layout.php`:

| Variable | Qué es |
|---|---|
| `$head` | etiquetas SEO del `<head>` (title, description, canonical, Open Graph, hreflang, JSON-LD). **Imprímelo siempre.** |
| `$content` | HTML de la página ya renderizada |

En `page.php`: `$page` (`title`, `slug`, `blocks`, `seo`, `lang`…). En `post.php`: `$post` (`title`, `content` en
Markdown, `excerpt`, `cover_image`, `tags`, `author`, `published_at`…). En `blog.php`: `$posts`, `$page`, `$pages`, `$tag`.
En cada bloque (`blocks/x.php`): `$d` con los campos del bloque, ya validados.

### Funciones útiles

| Función | Uso |
|---|---|
| `e($texto)` | escapa HTML. **Úsala en todo lo que imprimas.** |
| `md($markdown)` | convierte Markdown seguro a HTML (cuerpo de entradas y bloques de texto) |
| `__('Texto en español')` | traduce textos fijos del tema al idioma de la página (diccionarios `app/lang/front-xx.php`) |
| `uw_icon('truck')` | icono SVG de la colección propia (truck, clock, bolt, globe, shield, mail, phone, map, arrow, check…) |
| `render_blocks($svc, $blocks)` | pinta una lista de bloques con las plantillas del tema |
| `theme_asset($site, 'style.css')` | URL de un archivo de `assets/` con versión anticaché |
| `theme_file($site['theme'], 'blocks/_head.php')` | ruta de otra plantilla (con herencia de `default`) para `include` |
| `site_code_head($svc)` | CSS/`<head>` propios de la web y consentimiento de cookies. **Va antes de `</head>`.** |
| `site_code_body_end($svc)` | JS propio de la web y aviso de cookies. **Va antes de `</body>`.** |
| `uw_credit()` | crédito «Powered by bionicadri.com» (obligatorio por licencia, ver LICENSE) |
| `form_protection_fields($svc)` | campos antispam del formulario de contacto (Turnstile, marca de tiempo) |

Métodos de `$svc` más usados: `langPath('/contacto')` (enlace interno con el prefijo del idioma actual),
`langUrl('/')` (URL absoluta), `posts(['public' => true, 'limit' => 3])`, `page('slug')`, `url('/ruta')`,
`defaultLang()`, `langs()`.

---

## 4. layout.php mínimo

```php
<?php $accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#4f46e5'; ?><!doctype html>
<html lang="<?= e($s['language']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= $head ?>
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<?= site_code_head($svc) ?>
</head>
<body>
<header>
  <a href="<?= e($svc->langPath('/')) ?>"><?= e($site['name']) ?></a>
  <nav><?php foreach ($s['menu'] as $m): ?><a href="<?= e($m['url']) ?>"><?= e($m['label']) ?></a><?php endforeach; ?></nav>
  <?php if ($langs): ?><nav class="lang-switch"><?php foreach ($langs as [$lc, $ln, $lu, $la]): ?>
    <a href="<?= e($lu) ?>" hreflang="<?= e($lc) ?>"<?= $la ? ' aria-current="true"' : '' ?>><?= e(strtoupper($lc)) ?></a>
  <?php endforeach; ?></nav><?php endif; ?>
</header>
<main id="main"><?= $content ?></main>
<footer>&copy; <?= date('Y') ?> <?= e($site['name']) ?> · <?= uw_credit() ?></footer>
<?= site_code_body_end($svc) ?>
</body>
</html>
```

Imprescindible: `$head`, `$content`, `site_code_head()`, `site_code_body_end()` y `uw_credit()`.

---

## 5. Bloques

Cada página es una lista de bloques `{"type": "hero", "data": {...}}`. Tipos: `hero`, `text`, `features`, `stats`,
`image`, `gallery`, `split`, `steps`, `cards`, `logos`, `testimonials`, `cta`, `faq`, `code`, `posts`, `video`,
`audio`, `contact_form`. Los campos exactos de cada uno están en `/api/schema.json` de cualquier web (o con la
herramienta MCP `block_types`).

Para cambiar cómo se ve un bloque, crea `blocks/<tipo>.php` en tu tema. Ejemplo `blocks/faq.php`:

```php
<section class="section faq">
  <div class="container">
    <?php include theme_file($site['theme'], 'blocks/_head.php'); ?>
    <?php foreach ($d['items'] as $it): ?>
      <details><summary><?= e($it['question']) ?></summary><div><?= md($it['answer']) ?></div></details>
    <?php endforeach; ?>
  </div>
</section>
```

Los datos de `$d` ya vienen limpios (URLs filtradas, tipos correctos), pero **escapa siempre con `e()`** al imprimir.

---

## 6. Reglas de seguridad (se comprueban al subir)

- Solo estos archivos: `php css js json md txt svg png jpg jpeg webp avif gif ico woff woff2`.
- Los `.php` solo en la raíz del tema o en `blocks/`; nada de PHP dentro de `assets/` ni escondido en otros archivos.
- Sin archivos ocultos (`.htaccess`…), sin `..` en rutas, máximo 600 archivos y 40 MB descomprimido.
- `include`/`require` solo con `theme_file(...)` o `__DIR__`.
- Prohibido: `eval`, comillas invertidas, `exec`/`system`/`shell_exec`…, escribir o borrar archivos, red (`curl`,
  `fsockopen`), acceso directo a la base de datos, `Reflection`, llamadas dinámicas (`$fn()` salvo funciones anónimas
  propias) y callbacks por nombre (`array_map('x', …)`; usa `fn($v) => …`).
- SVG sin `<script>` ni atributos `on…`.

Un tema solo debe **pintar**. Si necesitas datos, usa los métodos de lectura de `$svc`.

---

## 7. Buenas prácticas

- **Accesible:** enlace «Saltar al contenido», `alt` en imágenes, foco visible, contraste AA.
- **Rápido:** CSS y JS propios y ligeros, fuentes alojadas en `assets/` (sin Google Fonts: RGPD), `loading="lazy"`.
- **Multi-idioma:** textos fijos con `__()`, enlaces internos con `$svc->langPath()`, selector con `$langs`.
- **SEO/GEO:** un solo `<h1>` por página (el bloque `hero` o el título), no quites `$head`.
- **Variables CSS:** usa `var(--accent)` para el color que el cliente elige en Ajustes.
- **Acento como texto:** el acento del cliente puede ser claro (amarillo) y no llega a contraste AA sobre blanco. Para **texto, enlaces y bordes finos** usa `var(--accent-text)` (el acento oscurecido; un tema oscuro lo redefine aclarado) y reserva `var(--accent)` para fondos de botón y adornos. El texto sobre un fondo de acento va en `var(--on-accent)` (blanco u oscuro según la luminosidad del acento) y el anillo de foco en `var(--focus)`.
- **Táctil y móvil:** botones, enlaces del menú y selector de idioma de ≥44 px de alto; el texto largo (alemán, rumano) se parte solo (`overflow-wrap`), sin desbordar. El menú móvil usa `aria-expanded`, se cierra con Escape (devuelve el foco al botón) y al pulsar un enlace.
- Documenta tus clases y variables en `css_hints` de `theme.json` para que la IA pueda personalizar sin romper.

## 8. ¿Solo quieres otro diseño? Plantilla HTML (sin PHP)

Si no necesitas tocar los bloques, no hace falta un tema: cada web puede tener su **Plantilla HTML** propia
(*Administración → Plantilla HTML*, o por MCP con `template_get` / `template_update` / `template_restore`).
Es HTML con marcadores (`{{{head}}}`, `{{{content}}}`, `{{#menu}}<a href="{{url}}">{{label}}</a>{{/menu}}`,
`{{site.name}}`, `{{{lang_switch}}}`, `{{{contact_html}}}`…) más su CSS. No se ejecuta código en el servidor, así que
la puede crear una IA o cualquier persona sin riesgo, con vista previa antes de activarla e historial para deshacer.
El contenido de las páginas lo siguen pintando los bloques del tema elegido.

## 9. ¿Front totalmente distinto?

Si quieres React, Vue, Astro, Next… no necesitas un tema: lee la API JSON pública de la web
(`/api/site.json`, `/api/pages.json`, `/api/pages/<slug>.json`, `/api/posts.json`, `/api/schema.json`, con `?lang=en`
para otros idiomas) y pinta tú. El panel, el MCP y el contenido siguen funcionando igual.

---

Universal Web · © BionicAdri · [bionicadri.com](https://bionicadri.com) · Licencia BionicAdri 2.0 (ver LICENSE)
