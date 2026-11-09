# Universal Web

**Una web, un panel, un MCP.** CMS ligero y multi-web en PHP puro: cada web se publica como JSON, se gestiona desde un panel sencillo y se puede dejar en manos de Claude (u otra IA) mediante MCP para el SEO, las páginas y el blog.

Sin frameworks, sin Composer, sin Docker, sin Node. Funciona en hosting compartido barato y en un VPS.

> **Licencia:** software propiedad de **BionicAdri** ([Licencia BionicAdri 2.0](LICENSE)). **Gratis para usarlo en tus propias webs, también las de tu empresa**; no se puede revender ni ofrecer como servicio o hosting a terceros. Se ofrece **tal cual, sin garantía, sin responsabilidad y sin soporte**.

> **Sin soporte en GitHub.** Este repositorio es solo para descargar el código: no hay Issues, no se aceptan contribuciones y aquí no se responden preguntas. ¿Dudas? Escribe a **info@bionicadri.com**.

## Creado por BionicAdri

<p align="center"><a href="https://www.bionicadri.com"><b>www.bionicadri.com</b></a></p>

Universal Web es un proyecto de **[BionicAdri](https://www.bionicadri.com)** (BIONICADRI SOLUTIONS S.L., Murcia),
la plataforma de gestión para pymes que reúne **CRM, ERP y facturación (VeriFactu), RR. HH., operaciones,
comunicación y soporte** en un solo sitio.

Su corazón es **ADRI**: una IA a la que le pides las cosas como se las pedirías a una persona, por chat o por voz.
Cuando el encargo es grande se clona: **hasta 20 copias trabajando a la vez**, cada una con las herramientas de su oficio.
Lleva clientes, facturas, correo, WhatsApp, documentos, redes… y **crea webs**. Para eso nació Universal Web: un motor de
webs ligero, multi-web y seguro, pensado
para que una IA como ADRI lo maneje por MCP con permisos claros. Datos en Europa, cumplimiento RGPD y Ley de IA de la UE.

👉 **¿Quieres que la IA lleve también tu empresa?** [Pide una demo gratuita de BionicAdri](https://www.bionicadri.com) ·
✉️ info@bionicadri.com · [LinkedIn](https://www.linkedin.com/company/bionicadri) · [YouTube](https://www.youtube.com/@bionicadri)

> 📖 **¿Usas el panel, no el servidor?** Lee el [Manual de usuario](docs/MANUAL.md): primera web, páginas, blog, SEO, dominio, copias y cómo trabajar con ADRI.

## Características

- **Multi-web por dominio**: una instalación sirve a todas tus webs; el dominio de la petición decide cuál se muestra.
- **Contenido por bloques** (portada, texto, características, cifras, FAQ, código, formulario de contacto...) y blog en Markdown.
- **API JSON pública** (`/api/*.json`): usa los temas incluidos o crea tu propio front.
- **MCP nativo** (`/mcp`): tokens por web con permisos `read`, `write` y `publish` y registro de actividad.
- **SEO automático**: sitemap, robots.txt, Open Graph, JSON-LD, RSS y auditoría con puntuación.
- **Temas intercambiables** por web, con gestor en el panel: galería, vista previa, descarga con manual y subida validada.
- **Webs multi-idioma**: `/en/`, `/ro/`…, hreflang, selector de idioma, sitemap, `llms.txt` y Markdown por idioma; traducción desde el panel o por MCP.
- **Aviso de cookies** por web (aceptar / rechazar / configurar) con GA4, Tag Manager (Consent Mode v2), Clarity y Meta Pixel cargados solo tras el consentimiento, y política `/cookies` automática.
- **Panel simple** en español, rumano e inglés: páginas, entradas, imágenes, mensajes, ajustes, usuarios y tokens.

## Requisitos

- PHP 8.1 o superior con `pdo_sqlite` (con JSON1) y `mbstring`. Recomendadas: `fileinfo`, `gd`.
- Apache con `mod_rewrite` (hosting compartido), o Nginx / Caddy con PHP-FPM.
- Carpetas `data/` y `public/media/` con permiso de escritura.

## Instalación

### Lo fácil

**Hosting compartido (cPanel, Plesk, FTP): un solo fichero.**
1. Descarga [`instalar.php`](deploy/instalar.php) y súbelo a la carpeta del dominio (normalmente `public_html`).
2. Abre `https://tu-dominio.com/instalar.php`. Comprueba tu hosting, te pide crear un fichero vacío con un nombre que te indica
   (así demuestras que el hosting es tuyo y nadie más puede instalar), descarga el paquete oficial, **verifica su SHA-256**,
   lo descomprime y te lleva al asistente `/install` para crear tu administrador. El instalador se borra solo.
3. Último paso opcional: «Conecta ADRI» te da el token MCP y la URL `/mcp`.

**VPS Ubuntu/Debian: una línea.**
```bash
curl -fsSL https://github.com/AppNetDeveloper/universal-web/releases/latest/download/instalar-vps.sh | sudo bash
```
Pregunta dominio y email, genera una contraseña fuerte (la ves una vez al final; no pasa por la línea de comandos), verifica el
paquete e instala PHP-FPM + Caddy con HTTPS automático.

**Publicar una versión** (mantenedores): `deploy/hacer_release.sh` crea `dist/universal-web.zip` (+ `-<versión>.zip`) y sus `.sha256`;
sube a la release de GitHub esos ficheros más `deploy/instalar-vps.sh`.

### Avanzado

### A. Hosting compartido a mano (zip)

1. Sube el zip y descomprímelo en la carpeta del dominio (normalmente `public_html`).
2. Abre `https://tu-dominio.com/install` y rellena el formulario.
3. Listo. El `.htaccess` de la raíz bloquea `app/`, `data/`, `themes/`, `bin/` y redirige todo a `public/`.

Si tu hosting permite elegir la carpeta raíz del dominio, apúntala a `public/`: es más seguro y rápido.

### B. VPS con `install.sh` (Ubuntu/Debian)

```bash
sudo ./install.sh --domain=miweb.com --email=yo@miweb.com --name="Mi web" [--demo]   # pide la contraseña
printf '%s' "$PW" | sudo ./install.sh --domain=miweb.com --email=yo@miweb.com --password-stdin   # sin pasarla por argumentos
```

Instala PHP-FPM y Caddy, copia la app a `/var/www/universal-web`, crea el administrador y configura HTTPS.
Caddy usa TLS bajo demanda: **cualquier dominio que añadas en el panel recibe su certificado automáticamente**
(solo hay que apuntar su DNS al servidor). Volver a ejecutar `install.sh` actualiza el código sin tocar los datos.
Deja el atajo `sudo uw <comando>` para la CLI. (`--password='…'` y la variable `UW_PASSWORD` también valen.)

### B2. VPS manual (Nginx o Caddy + PHP-FPM)

1. Copia el proyecto a `/var/www/universal-web` y da permisos de escritura a `data/` y `public/media/` al usuario de PHP-FPM.
2. Usa un ejemplo de `deploy/`: `nginx.conf.example`, `Caddyfile.example` (HTTPS automático, con ejemplo de TLS bajo demanda) o `apache-vhost.conf.example`.
3. Abre `/install` en el navegador o instala por CLI (ver más abajo).

### B3. Plesk (o cPanel) con despliegue automático desde Git

1. Crea la suscripción con la raíz del documento en `httpdocs/public` y PHP 8.1+ (FPM).
2. En **Git** de la suscripción: *Añadir repositorio → Repositorio remoto*, URL `git@github.com:TU_USUARIO/universal-web.git`,
   modo **Automático**, ruta `/httpdocs`. Copia la clave pública que muestra Plesk y añádela en GitHub como
   **Deploy key de solo lectura** (*Settings → Deploy keys*).
3. Copia la *URL de Webhook* (en *Configuración del repositorio*) y añádela en GitHub (*Settings → Webhooks*, evento `push`).
4. Abre la web y sigue el instalador. Desde entonces, cada `git push` a `main` se publica solo en unos segundos; `data/`
   y `public/media/` no están en Git, así que el contenido nunca se toca. Al desplegar una versión con cambios en la base de
   datos, la migración se aplica sola en la primera petición.

### C. En local

```bash
php bin/uw serve
# abre http://localhost:8080/install
```

O instala directamente por línea de comandos:

```bash
php bin/uw install --name="Mi web" --domain=localhost --email=tu@email.com --password=secreta123 --demo
php bin/uw serve
```

Con `--demo` se crean una web completa de ejemplo y una segunda web («Café Aurora», dominio `cafe.localhost`, tema `noche`) para ver el multi-web.

## CLI (`php bin/uw`)

| Comando | Descripción |
|---|---|
| `install --name= --domain= --email= --password= [--admin-name=] [--timezone=] [--demo]` | Instala |
| `serve [--port=8080] [--host=127.0.0.1]` | Servidor de desarrollo |
| `token:create --site=<slug\|global> --name= [--scopes=read,write,publish]` | Crea un token MCP |
| `user:password --email= --password=` | Cambia una contraseña |
| `site:list` | Lista webs y dominios |
| `backup [--out=copia.zip\|copia.tar.gz]` | Copia de seguridad (base de datos + imágenes) |

La variable de entorno `UW_DATA` cambia la carpeta de datos (por defecto `data/`).

## Varias webs

1. Apunta los dominios (o subdominios) a la **misma carpeta** del servidor.
2. En el panel, entra en *Ajustes* de la web (o crea una web nueva) y añade el dominio.
3. Cada web tiene su contenido, tema, menú, usuarios y tokens. Un dominio desconocido muestra la web por defecto.

## MCP: conectar Claude

1. En el panel abre **Tokens de IA**, elige los permisos y copia el token (solo se muestra una vez).
   Cualquier usuario puede crear tokens **para su web**; solo el superadmin puede crear tokens **globales** (todas las webs).
2. Claude Code:

```bash
claude mcp add --transport http universal-web https://tu-dominio/mcp --header "Authorization: Bearer uw_TU_TOKEN"
```

3. Otros clientes (JSON):

```json
{
  "mcpServers": {
    "universal-web": {
      "type": "http",
      "url": "https://tu-dominio/mcp",
      "headers": { "Authorization": "Bearer uw_TU_TOKEN" }
    }
  }
}
```

**Permisos**: `read` (consultar), `write` (crear y editar borradores), `publish` (publicar y editar lo publicado).

**Tokens ligados a su creador**: un token actúa con el rol que su creador tiene *hoy* en esa web y nunca puede hacer
más que él en el panel (p. ej. `site_update` exige ser admin). Si al usuario se le quita el acceso a la web, sus tokens
dejan de funcionar al instante (y se revocan); un token global deja de valer si su creador deja de ser superadmin.
Los editores solo ven y revocan sus propios tokens; los admins, todos los de su web. Cada acción queda en Actividad
como «MCP: nombre del token (usuario)». Los tokens creados con la CLI (`bin/uw token:create`) no tienen creador y actúan como admin.

**Herramientas** (la lista completa y al día está en el panel, Tokens de IA, o en `tools/list` del endpoint): contenido `site_get`, `site_update`, `block_types`, `page_list`, `page_get`, `page_save`, `page_patch` (editar un solo bloque), `page_delete`, `post_list`, `post_get`, `post_save`, `post_delete`, `translation_status`; deshacer `page_history`, `page_restore`, `post_history`, `post_restore`, `trash_list`, `trash_restore`; medios `media_list`, `media_update`, `media_upload` (por **URL** o base64: imágenes, PDF, vídeo y audio; las fotos se reducen a 2000 px y pasan a WebP solas); diseño `code_get`, `code_update`, `code_history`, `code_restore`, `template_get`, `template_update`, `template_restore`; SEO/GEO `seo_audit`, `seo_autofix`, `seo_bulk_update`, `seo_analyze`, `geo_audit`, `search_ping`; comprobar `site_check`, `errors_list`, `errors_clear`; redirecciones 301 `redirect_list`, `redirect_save`, `redirect_delete`; y `messages_list`, `message_mark_read`, `backup_create`, `backup_list`, `publish_status`, `publish_now`. Con token global además `sites_list` y `site_create`.

**Dominios y ayuda por MCP**: `site_create` y `domain_status` devuelven la vista previa, la IP del servidor, los registros DNS
que faltan, si el HTTPS es automático (Caddy) o hay que dar de alta el dominio en Plesk/cPanel, y `message_for_user` (texto listo
para que la IA se lo pase a la persona); `domain_add` añade su dominio. `manual` (y el recurso `uw://manual`) da a la IA el
**manual de usuario** ([`docs/MANUAL.md`](docs/MANUAL.md), también en **Panel → Ayuda**) para explicar el panel paso a paso.

**Harness «smart»**: cada `page_save` / `post_save` / `page_patch` / `page_restore` devuelve `check` con los fallos de
esa página (enlaces rotos, imágenes que faltan, SEO). Cada cambio guarda la versión anterior (30 por página) y lo borrado
pasa 30 días en la papelera (Panel → Papelera). `seo_autofix` rellena lo mecánico (alt, meta descripciones, extractos,
portadas, descripción de la web por idioma) sin sobrescribir nada y devuelve `needs_writing` para que la IA redacte el
resto de una vez con `seo_bulk_update`; `seo_analyze` analiza una página con palabra clave sobre el HTML real. Al
publicar, la web avisa sola a Bing/Yandex por **IndexNow** (Bing alimenta ChatGPT y Copilot; desactivable con
`site_update indexnow=false`). Las instrucciones MCP piden a la IA que, si tiene navegador, mire cada página en móvil y
escritorio antes de dar el trabajo por terminado.

**Permisos especiales**: `messages` (leer mensajes de contacto: datos personales), `code` (CSS/JS/`<head>` personalizados) y `backup` (copias de seguridad); estos dos últimos exigen que el dueño del token sea admin. Restaurar copias **no** se puede por MCP: solo desde el panel o la CLI.

Prueba con: *«Crea una entrada sobre X, déjala en borrador y optimiza el SEO.»*

## GEO: visibilidad en buscadores con IA

Cada web genera sola **`/llms.txt`** (resumen para modelos de lenguaje) y **`/llms-full.txt`** (todo el contenido en Markdown),
datos estructurados completos (organización o negocio local con dirección, zona de servicio y año de fundación, `FAQPage`
a partir de los bloques de preguntas, migas de pan y artículos) y una **política de rastreadores de IA** en `robots.txt`
(permitir todo, solo buscadores con IA o bloquear). Cada página tiene además su **versión Markdown** (`/servicios.md`,
`/index.md`, `/blog/<entrada>.md`, enlazada con `rel="alternate"`), que se sirve también con `Accept: text/markdown` y, si se
activa, automáticamente a los agentes de IA conocidos (Google y Bing siempre reciben HTML). **`geo_audit`** (MCP y Panel → SEO) puntúa lo citable que es la web y
dice qué mejorar y con qué herramienta.

## Modo demo (web privada)

En **Ajustes → Acceso privado** una web puede quedar privada: solo se ve con contraseña (o con sesión del panel), envía
`X-Robots-Tag: noindex`, su `robots.txt` bloquea todo y su API JSON se cierra. Ideal para enseñar una demo a un cliente
en un dominio temporal sin crear contenido duplicado en Google. El MCP no puede activarlo ni desactivarlo.

## Publicación: la web en otro servidor (opcional)

Cada web elige en **Panel → Publicación** cómo se sirve:

| Modo | Dónde vive la web pública |
|---|---|
| **Dinámico** (por defecto) | En este mismo servidor, junto al panel |
| **Estático en otro servidor** | HTML generado con los mismos temas y subido a otro servidor |
| **Headless** | Tu propio front lee la API JSON (`/api/*.json`, con URLs absolutas) |

En modo estático se genera la web completa (páginas, blog, sitemap, robots, RSS, imágenes, CSS/JS y una copia de la API
JSON) y se sube **solo lo que cambia** a: **SSH/rsync** (VPS o red privada), **FTP/FTPS** (hosting compartido), una **carpeta**
de este servidor, o se **descarga en .tar.gz** para subirla a mano. Puede publicarse sola tras cada cambio (PHP-FPM) o con
cron `php bin/uw publish --dirty`; también por la IA (`publish_status`, `publish_now`).

Así el panel y la base de datos pueden quedar en una red privada (por ejemplo una VPN) y en internet solo hay archivos estáticos.
El formulario de contacto de la web estática envía a `/api/contact` de la «URL pública de este servidor» (expón solo
`/api/contact` y `/api/form-token` si el panel está oculto) y vuelve a la web estática al terminar. Los datos del servidor
de destino (rutas, claves SSH, contraseñas FTP, cifradas) solo los configura el superadmin. Ejemplo de servidor web para la
parte estática: `deploy/static-server.nginx.example`.

## Formularios y correo (por web)

En **Panel → Formularios** (solo admins) cada web configura su formulario de contacto:

- **Aviso por email** a uno o varios destinatarios, con el email del visitante como «Responder a».
- **Envío por SMTP** (STARTTLS, SSL o sin cifrado; usuario y contraseña) o con `mail()` de PHP si el hosting lo permite.
  Botón **«Enviar correo de prueba»** que muestra el error exacto del servidor.
- **Respuesta automática** opcional al visitante, con variables `{nombre}` y `{web}` (el asunto y el mensaje del visitante nunca se incluyen, para que el formulario no sirva de vía de spam).
- **Antispam**: campo trampa, tiempo mínimo de envío (firmado), límite por IP y, opcionalmente, **Cloudflare Turnstile**.

**Webhook hacia tu CRM** (por ejemplo el de BionicAdri): cada mensaje nuevo se envía como JSON firmado
(`X-UW-Signature: sha256=…`, HMAC con un secreto que muestra el panel), con botón de prueba.

Los mensajes se guardan **siempre** en el panel: si el correo falla, el mensaje queda marcado con el error y se puede
reintentar. La contraseña SMTP y la clave secreta de Turnstile se guardan **cifradas** (libsodium u OpenSSL con la
clave de `data/config.php`) y nunca se vuelven a mostrar. El MCP y la API JSON no tienen acceso a esta configuración.

## API JSON

| Endpoint | Contenido |
|---|---|
| `/api/site.json` | Datos de la web y lista de páginas |
| `/api/schema.json` | Tipos de bloque y sus campos |
| `/api/pages.json` · `/api/pages/{slug}.json` | Páginas publicadas |
| `/api/posts.json?tag=&limit=&offset=` | Entradas del blog |
| `/api/posts/{slug}.json` | Entrada completa (con HTML generado) |
| `POST /api/contact` | Envía un mensaje de contacto |

Las respuestas incluyen CORS abierto para poder crear fronts externos.

## Temas

Gestiónalos en **Administración → Temas**: galería con captura, **vista previa** (enlace firmado de 2 horas, solo para
quien lo abre; funciona aunque el panel esté en otro dominio), **aplicar** a la web actual y **descargar con manual**
(`.zip` con el tema y [`docs/THEMES.md`](docs/THEMES.md), la guía para desarrolladores).

Un tema es una carpeta dentro de `themes/`:

```
themes/mi-tema/
  theme.json        {"slug": "mi-tema", "name": "Mi tema", "description": "...", "version": "1.0"}
  layout.php        estructura HTML (usa $head, $content, site_code_head(), site_code_body_end())
  blocks/hero.php   una plantilla por tipo de bloque (recibe $d, $svc, $site)
  assets/           CSS/JS/imágenes (y screenshot.webp para la galería) -> /theme-assets/mi-tema/...
```

Lo que tu tema no defina se toma del tema `default`, así que puedes empezar sobrescribiendo solo `layout.php` y unos pocos bloques.

**Subir temas desde el panel** está desactivado por defecto, porque un tema es código PHP. Para activarlo añade
`'allow_theme_upload' => true` a `data/config.php`; aun así solo puede subirlos un **superadmin con 2FA**. Antes de
instalar se revisa la estructura, los tipos de archivo, la sintaxis PHP y las funciones peligrosas (`exec`, `eval`,
escritura de archivos, red, llamadas dinámicas…); al sustituir un tema se guarda copia en `data/theme-backups/`.
Sin panel: copia la carpeta a `themes/` por FTP/SSH.

**Temas incluidos**:
- `default` — claro, limpio y moderno.
- `noche` — oscuro y elegante (solo cambia `layout.php` y el CSS).
- `corporate` (**Corporativo**) — para empresas de servicios, industria y logística: barra superior de contacto, botón
  destacado en la cabecera, portada a pantalla completa con foto, cifras superpuestas, pie a 4 columnas y WhatsApp flotante.
  Tipografía Inter alojada en el propio servidor (sin Google Fonts, amigable con el RGPD) y animaciones que respetan
  «reducir movimiento». Se personaliza con `--accent` (Ajustes), y `--accent-2` / `--ink` desde el CSS personalizado.
- `industrial` — oscuro y robusto: talleres, reformas, construcción, metal (Oswald + Inter).
- `editorial` — tipográfico y sereno: despachos, consultoras, abogados, arquitectos, blogs (Fraunces + Literata).
- `calido` (**Cálido y local**) — restaurantes, cafeterías, tiendas, estética (DM Serif Display + Nunito).
- `minimal` — tecnología, startups, apps y SaaS (Manrope).
- `vibrante` — gimnasios, deporte, academias, ocio infantil, eventos (Anton + Figtree).
- `natural` — clínicas, fisioterapia, psicología, yoga, productos ecológicos (Young Serif + Source Sans 3).
- `prestigio` — inmobiliaria premium, hoteles boutique, bodegas, joyería, bodas (Cormorant + Jost).

Todos son **multi-idioma** (selector de idioma y textos traducidos a español, inglés y rumano), se han probado en
móvil (390 px), tablet (820 px) y PC (1440 px) sin desbordes, usan fuentes OFL alojadas en el servidor y traen
**fotos de muestra propias** (generadas por IA, sin derechos de terceros) en `assets/samples/`. Cada `theme.json`
indica para qué negocios es (`best_for`), su color sugerido y sus fotos de muestra: la IA lo lee en `site_get → themes`
para elegir tema. Bloques pensados para negocios con local: **Horario** (`hours`, con «Abierto ahora» y datos para
Google/IAs) y **Carta / Precios** (`pricelist`).

## Plantilla HTML segura (por web)

En **Administración → Plantilla HTML** (o por MCP con `template_get`, `template_update` y `template_restore`) cada web
puede tener su propio diseño: el HTML de la cabecera, el pie y la estructura, más su CSS. Es **HTML con marcadores**
(`{{{head}}}`, `{{{content}}}`, `{{#menu}}<a href="{{url}}">{{label}}</a>{{/menu}}`, `{{site.name}}`, `{{{lang_switch}}}`…)
que Universal Web rellena: nunca se ejecuta código en el servidor, los datos se escapan y no se permiten `<script>`,
atributos `on…`, `javascript:` ni PHP (el JavaScript va en *Diseño y código*). El contenido sigue saliendo de los bloques,
así que SEO, GEO, idiomas, cookies, formularios, Markdown para IAs y publicación estática funcionan igual. Se revisa con
un enlace de **vista previa** firmado antes de activarla, cada cambio guarda versión y «deshacer» vuelve atrás.

## Idiomas

**Webs multi-idioma** (**Administración → Idiomas**): el idioma principal va sin prefijo y los demás en `/en/`, `/ro/`…
Cada página y entrada existe por idioma con el **mismo slug**; en la lista de páginas, «+ EN» crea la traducción con una
copia del original para reescribirla. Los textos de la web (eslogan, menús, botón, pie, aviso de cookies) se traducen en
la misma pantalla. Un idioma no se publica (selector, `hreflang`, sitemap, `llms.txt`) hasta que tiene portada publicada.
Por MCP: `translation_status` y el parámetro `lang` en `page_*`/`post_*`; la API admite `?lang=en`.

**Panel en tres idiomas**: español, rumano e inglés. Cada usuario elige el suyo en *Mi cuenta* (o en el login).

## Cookies

**Administración → Cookies**: banner con *Aceptar todas*, *Rechazar* y *Configurar* (necesarias / analítica / marketing),
traducido a cada idioma y con un botón redondo para cambiar de opinión. Pega solo los IDs de **Google Analytics 4**,
**Tag Manager** (con Consent Mode v2), **Microsoft Clarity** o **Meta Pixel**: no se cargan hasta que el visitante acepta,
y al retirar el consentimiento se borran sus cookies. Si configuras alguno, el aviso se activa solo. La política
`/cookies` se genera con las herramientas reales de la web (o enlaza la tuya). Para otros scripts:
`<script type="text/plain" data-consent="analytics">…</script>`. También por MCP: `site_update` → `cookies`.

## Estructura

```
app/        núcleo, contenido, front, MCP, panel e instalador
bin/uw      línea de comandos
data/       base de datos SQLite y config (se crea al instalar, nunca público)
deploy/     ejemplos de Nginx, Caddy y Apache
public/     única carpeta pública (index.php, assets, media)
themes/     temas
```

## Diseño y código personalizado

En **Panel → Diseño y código** (admins) cada web puede añadir su propio **CSS**, **JavaScript**, código del `<head>`
(analítica, verificación de Search Console, fuentes) y **favicon**. Cada cambio guarda una versión: el historial permite
volver atrás con un clic, también por MCP (`code_history` / `code_restore`). El CSS se sirve en `/site-assets/custom.css`
con caché larga e invalidación por versión.

**Bloques disponibles**: portada, texto, características (con iconos SVG por nombre: `truck`, `clock`, `globe`, `shield`…),
cifras, imagen, galería, **imagen + texto**, **proceso por pasos**, **tarjetas con imagen**, **logos**, **testimonios**,
llamada a la acción, preguntas frecuentes, código, últimas entradas, **vídeo** (archivo propio, YouTube o Vimeo), **audio** y
formulario de contacto (con teléfono y casilla de privacidad RGPD opcionales).

## Copias de seguridad y actualización

En **Panel → Copias de seguridad**:
- **Por web** (admin de esa web): crear, descargar, borrar y **restaurar** (antes se hace una copia de seguridad automática del estado actual).
- **Globales** (superadmin): toda la instalación, **importar una web** desde una copia (para mover o clonar webs entre servidores) y **copias automáticas diarias** con retención.
- **Cifrado opcional** con contraseña (AES-256-GCM, PBKDF2 de 200.000 iteraciones). Sin la contraseña no se puede restaurar.
- Se guardan en `data/backups/` (fuera de la web pública) en formato `.tar.gz` (`.enc` si van cifradas). Las globales incluyen `config.php`: trátalas como sensibles.
- También por la IA (`backup_create`, `backup_list`) y por CLI: `php bin/uw backup [--site=slug] [--password=…]`, `backup:list`,
  `backup:restore-site`, y `backup:restore` (restauración global, **solo por CLI**).
- Recomendado en VPS: cron diario `0 3 * * * php /ruta/bin/uw backup --auto`.
- **Copias fuera del servidor** (cada web la suya; el superadmin, las globales): si el servidor se rompe, las copias de
  `data/backups/` se pierden con él. En *Copias de seguridad → Copia fuera del servidor* elige **SFTP/SSH** (clave o
  contraseña; la huella del servidor se recuerda o se fija), **FTP/FTPS**, **S3 y compatibles** (Amazon, Cloudflare R2,
  Backblaze B2, Wasabi, MinIO) o una carpeta del servidor (solo superadmin). Cada copia creada (panel, IA, CLI o automática)
  se envía sola; las webs pueden programar una copia **diaria o semanal**; se conservan las **últimas N** en el destino y,
  con contraseña, se **cifran antes de salir** (el destino nunca ve el contenido). «Guardar y probar conexión» sube, lista y
  borra un archivo de prueba. Contraseñas y claves se guardan cifradas y nunca se conecta a redes privadas salvo las
  permitidas en *Seguridad y red*. Sin PHP-FPM, las copias programadas las lanza el cron `php bin/uw backup --auto`.
- **Copia manual**: también vale copiar `data/universal.sqlite`, `data/config.php` y `public/media/`.
- **Actualizar**: sustituye `app/`, `bin/`, `docs/`, los temas de serie (`themes/default`, `themes/noche`, `themes/corporate`) y `public/index.php` / `public/assets/` por los de la nueva versión. No toques `data/`, `public/media/` ni los temas que hayas subido tú.

## Seguridad

- `data/` lleva `.htaccess` que lo bloquea y en Nginx/Caddy debe quedar fuera del root (`public/`).
- `public/media/` no ejecuta scripts; solo se aceptan imágenes y PDF validados.
- Contraseñas con `password_hash`; tokens MCP guardados como hash SHA-256 (no se pueden recuperar).
- Formularios del panel con protección CSRF; el formulario de contacto tiene límite de envíos y campo trampa anti-spam.
- Una vez instalado, `/install` deja de funcionar. Usa HTTPS siempre en producción.
- **Panel aislado de la web pública**: Fetch Metadata + `Cross-Origin-Opener-Policy` + CSP en `/admin`, para que ni siquiera un
  JavaScript personalizado de la web pueda usar la sesión de un administrador. Sesiones con caducidad por inactividad.
- **Subidas por URL con protección SSRF**: solo IPs públicas, sin redirecciones a redes privadas, tamaño máximo.
- **Secretos cifrados** (SMTP, Turnstile, webhook) con la clave de `data/config.php`.
- **Dominio exclusivo para el panel** (recomendado en multi-web): `/admin` y `/mcp` solo responden en ese dominio, así el
  CSS/JS de ninguna web comparte origen con el panel. Los admins de una web no pueden asignarse IPs ni nombres locales.
- **Enlaces saneados** al guardar y al mostrar (nada de `javascript:` en menús, botones o bloques), tipos de bloque y temas validados.
- **Conexiones salientes** (webhooks, SMTP) solo a internet público; redes privadas únicamente si el superadmin las permite.
- **Login** con tiempo constante (no revela qué emails existen), límite por IP y por email; sesión en modo estricto y
  cookie `__Host-` en HTTPS. El instalador nunca se ejecuta sobre una base de datos existente.
- **Permiso `messages`** aparte para que un token MCP lea mensajes de contacto (datos personales).
- Recomendado: auditoría externa antes de usarlo en producción crítica.

## Recuperación de contraseña

En **Plataforma → Correo del panel** (superadmin) configura el servidor SMTP del panel, el remitente y la URL pública
del panel; es independiente del correo de los formularios de cada web. Con eso, el login muestra *¿Has olvidado tu
contraseña?*: el panel envía un enlace de un solo uso que caduca en 1 hora (solo se guarda su hash), responde igual
exista o no el email, limita los intentos y construye el enlace con la URL configurada (nunca con la cabecera `Host`).
Al cambiar la contraseña se cierran las demás sesiones abiertas y la verificación en dos pasos se sigue pidiendo.
Sin correo configurado: `php bin/uw user:password --email=… --password=…`.

## Verificación en dos pasos (2FA)

Cada usuario la activa en **Mi cuenta** escaneando un QR con cualquier app de autenticación (Google Authenticator,
Microsoft Authenticator, Authy, 1Password, Bitwarden…). Estándar TOTP (RFC 6238), secreto cifrado en la base de datos,
un código no se puede usar dos veces, 10 códigos de recuperación de un solo uso y límite de intentos.
El superadmin puede **obligarla** para todos (Seguridad y red) y resetearla a quien pierda el móvil (Usuarios, o
`php bin/uw user:2fa-reset --email=…`). Los tokens MCP son credenciales aparte: revócalos si se filtran.

## Seguridad y red

En **Panel → Seguridad y red** (superadmin):
- **Lista de IPs/redes permitidas** para `/admin` y `/mcp` (por ejemplo solo tu VPN o la red de tu oficina); la web pública sigue abierta.
  Protección anti-bloqueo: no deja guardar una lista que excluya tu IP actual. Recuperación: `php bin/uw security:reset`.
- **Proxies de confianza**: para un servidor sin IP pública detrás de un VPS frontal conectado por una red privada (modo «edge»).
- Ejemplo de VPS frontal en `deploy/Caddyfile.edge.example`.

## Licencia

Copyright (c) 2026 BionicAdri. El software se rige por la [Licencia BionicAdri 2.0](LICENSE) (código fuente disponible, no es «open source»):

- ✅ Puedes **usarlo gratis** e instalarlo en tus servidores para **tus propias webs**, también las de tu empresa o negocio.
- ✅ Puedes conectarlo con **ADRI** u otra IA por MCP o por su API.
- ✅ Puedes **adaptarlo en privado** para tu propio uso y compartir copias **íntegras y sin modificar**, gratis y con la licencia.
- ❌ No puedes **venderlo**, ofrecerlo como **servicio, hosting o plataforma** a terceros, ni **instalarlo o mantenerlo para otros a cambio de dinero** sin una licencia escrita de BionicAdri (pídela: puede ser gratuita).
- ❌ No puedes publicar ni distribuir **versiones modificadas**.
- ❌ No puedes quitar el crédito «Hecho con Universal Web · Powered by bionicadri.com» del pie de las webs, del panel ni del instalador.

**Sin garantía, sin responsabilidad y sin soporte.** Universal Web se ofrece «tal cual». BionicAdri no se hace responsable
de pérdidas de datos, caídas, fallos de seguridad, incumplimientos normativos ni de los contenidos creados por personas o
por IA, y **no ofrece soporte técnico, instalación ni mantenimiento**. Tú eres responsable de tu servidor, tus copias de
seguridad, tu configuración y tus contenidos.

El contenido que crees con Universal Web (textos, imágenes, páginas) es tuyo.
