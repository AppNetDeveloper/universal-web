# Cambios de Universal Web

## [0.5.0] — 2026-11

Diez temas nuevos, todos con modo claro y oscuro, y una ficha por tema para que la IA elija el diseño adecuado sin
abrirlos.

### Temas nuevos
- **Sobrio** (despachos: abogados, asesorías, gestorías), **Pulcro** (clínicas y consultas), **Horizonte**
  (inmobiliarias), **Rotundo** (tiendas y comercio local), **Orbital** (tecnología y SaaS), **Cuaderno** (academias y
  formación), **Trayecto** (transporte y logística), **Cota** (construcción y reformas), **Sutil** (belleza y estética) y
  **Lienzo** (portfolio y freelance).
- Siguen el **modo claro u oscuro del dispositivo** (`prefers-color-scheme`), con contraste AA en los dos.
- Misma mecánica que los temas de la 0.4.0: 7 idiomas, `hreflang`, JSON-LD, caché, CSP, CSS/JS propio de cada web,
  cookies y WhatsApp. Fuentes alojadas en el servidor (máximo 2 familias por tema, con su licencia OFL), sin recursos de
  terceros ni librerías JS.
- **Textos de ejemplo** de cada tema nuevo en español, inglés, rumano, francés, alemán, italiano y portugués
  (`sample.json`): 5 páginas y 2 entradas de una empresa ficticia, con las cifras y opiniones marcadas como ejemplo.

### Elegir tema
- **Ficha de cada tema** en `theme.json` (los 20): para qué empresa es y cuándo elegirlo en vez del parecido, sectores,
  estilo, colores, tipografía y modo oscuro.
- MCP **`theme_list`** (lectura): las fichas, filtrables por sector (`sector: "clínica"`). Con un token global no pide
  `site`, para elegir antes de `site_create`.
- MCP **`theme_sample`**: los textos de ejemplo de un tema en un idioma, como modelo de páginas.
- **Crear páginas iniciales** de una web nueva usa los textos de ejemplo del tema (si los trae) y su color sugerido.
- Panel → Temas: estilo de cada tema y aviso «Claro y oscuro».
- Una web sin favicon propio ya no deja un 404 de `/favicon.ico` en la consola de cada visita (icono vacío por defecto).

### Actualizador
- Un tema propio con el mismo nombre de carpeta que un tema nuevo de serie no se sobrescribe al actualizar.

### Pruebas
- `tests/themes_render.php`: cada tema pinta portada, página con todos los bloques, blog, entrada, contacto y 404 en los
  7 idiomas sin avisos de PHP y con `hreflang` + `x-default`, JSON-LD, CSS/JS propio, crédito y un solo `<h1>`; revisa
  sus textos de ejemplo y que pase el validador de subida de temas.

### Riesgos al actualizar desde la 0.4.0
- Si tienes un **tema propio** con el mismo nombre de carpeta que uno nuevo (`sobrio`, `pulcro`, `horizonte`,
  `rotundo`, `orbital`, `cuaderno`, `trayecto`, `cota`, `sutil`, `lienzo`), el actualizador ya no lo pisa: lo deja como
  está, lo avisa entre los temas no tocados y ese tema nuevo no se instala. Si lo quieres, cambia antes el nombre del tuyo.
  (Si actualizas a mano descomprimiendo el paquete encima, sí se sustituye.)
- Los temas de la 0.4.0 no cambian de aspecto: solo ganan su ficha en `theme.json`.

## [0.4.0] — 2026-11

Universal Web ya puede alojar una web grande y en varios idiomas sin perder nada de SEO ni de GEO, y se actualiza sola
desde el panel.

### Idiomas
- Textos de los temas en **7 idiomas**: español, inglés, rumano, francés, alemán, italiano y portugués. Se pueden activar
  más por configuración (cualquier código ISO; sus textos fijos salen en inglés hasta que exista su diccionario).
- Cada página en su dirección por idioma, con `hreflang` de los idiomas en que existe + `x-default`, canonical propio,
  `og:locale` con región (`es_ES`, `en_GB`…; se cambia con `locales`) y `og:locale:alternate`.
- **Páginas que solo existen en algunos idiomas**: sin `hreflang`, selector ni sitemap en los demás, y una **301
  configurable** a su equivalente (ficha de la página o `page_languages` por MCP). Los menús y enlaces de ese idioma van
  directos al destino.
- El selector de idioma solo muestra los idiomas en los que existe la página.

### Direcciones exactas al migrar
- **Dirección fija** por página y entrada (`/precios.html`, `/guias/mudanza.html`, `/en/pricing.html`), validada (sin `..`,
  sin pisar rutas del sistema, con el prefijo de su idioma y sin repetirse).
- Estilo de direcciones **`.html`** por web (`url_style`): `/servicios.html`, `/blog/x.html`, `/blog.html`.
- La dirección pedida que no es la canónica responde **301** a la buena; cambiar la dirección de algo publicado crea la 301.
- **Importar redirecciones** de un texto o archivo «origen -> destino» (panel, MCP `redirect_import`), con el error de
  cada línea que no vale.
- Los enlaces internos a una dirección con 301 se escriben ya hacia su destino.

### GEO
- Copia en Markdown de cada página por idioma en `/md/<página>.md` y `/<xx>/md/…` (también `/<ruta>.md` y
  `Accept: text/markdown`); se mantienen las direcciones de la 0.3 (`/servicios.md`).
- `llms.txt` por idioma con enlace a cada página y a su Markdown; `llms-full.txt`.
- `.md` y `llms*.txt` con `X-Robots-Tag: noindex`.

### Datos estructurados, sitemap e IndexNow
- JSON-LD: `WebSite` y `WebPage` / `CollectionPage` con **`dateModified` real** (fecha del último cambio de contenido;
  guardar sin cambios no la mueve), `BlogPosting` con `inLanguage`, `BreadcrumbList` y `FAQPage` con `@id`.
- `sameAs` ampliable con la lista `same_as` (Wikidata, Wikipedia, LinkedIn…), además de las redes sociales.
- `sitemap.xml` con `lastmod` real y alternativas `xhtml:link` por idioma + `x-default`.
- IndexNow solo de lo que cambia (publicar, cambiar, despublicar, borrar).

### Velocidad
- **Caché de página completa**: la página generada se sirve sin cargar la aplicación (con `ETag` / 304). Se vacía sola
  con cada cambio y al cambiar el código, caduca en 1 h y nunca se usa con sesión, vista previa, Markdown, agentes de IA,
  consultas ni webs privadas. En una prueba local: de 2,6 ms a 0,18 ms por página (un archivo estático: 0,12 ms).
- Ajustes calculados una vez por petición.

### Seguridad
- Web pública con CSP de base (`base-uri`, `object-src 'none'`, `frame-ancestors`, `form-action`) y
  `Cross-Origin-Opener-Policy`.
- `.htaccess` de la raíz bloquea también `docs/`, `dist/`, `CHANGELOG.md` y `CONTRIBUTING.md`.
- La clave de la caché decide el esquema como `is_https()`: un `X-Forwarded-Proto` falso no envenena la copia https.
- `install.sh` ya no borra los temas propios al reinstalar (`rsync --delete` los protege).

### Actualizaciones desde el panel
- **Aviso** diario de versión nueva (y al entrar el superadmin), con su resumen de cambios. Sin red no se rompe nada.
- **Actualizar** con un botón (superadmin): firma ed25519 comprobada, copia completa, mantenimiento, cambio de código sin
  tocar datos, configuración, subidas ni temas propios, migraciones, caché y prueba de salud. **Si algo falla, vuelve
  sola a la versión anterior.** Botón **Volver a la versión anterior**.
- **Actualización automática** opcional (apagada de serie): solo correcciones (0.4.x) o todas; aviso por correo.
- CLI: `update:check`, `update --yes`, `update:rollback --yes`. `bin/release` empaqueta y firma.
- Por MCP solo se consulta: `update_status`.
- Lo que se enseña de una versión («qué cambia») sale de su sobre firmado, no de las notas de la release.
- Una versión que falló en automático no se reintenta sola; de un intento fallido no quedan copias en disco.

### MCP
- `page_save` / `post_save` con `path`; `site_update` con `url_style`, `same_as` y `locales`.
- Nuevas: `page_languages`, `redirect_import`, `page_faq`, `cache_clear`, `update_status`.

### Cómo actualizar desde la 0.3.0 (una sola vez, a mano)

La 0.3.0 no trae el actualizador: este paso es manual. Desde la 0.4.0 ya se hace con el botón del panel.

1. **Copia de seguridad**: Panel → Copias de seguridad → copia global (o `php bin/uw backup`). Guarda además una copia de
   `data/` y de `public/media/` fuera del servidor.
2. **Descarga** `universal-web-0.4.0.zip` y su `.sha256` de la release v0.4.0 y comprueba la huella:
   `sha256sum -c universal-web-0.4.0.zip.sha256`.
3. **Sustituye el código** descomprimiendo el zip **encima** de la instalación (por ejemplo `unzip -o
   universal-web-0.4.0.zip` en la carpeta de Universal Web, o subiendo los archivos por FTP y aceptando reemplazarlos).
   No borres ni sustituyas `data/` ni `public/media/` (el paquete no los trae). Si modificaste a mano algún tema de serie,
   guárdalo antes: este paso lo sobrescribe (desde la 0.4.0 el actualizador ya no toca los temas modificados).
   - VPS instalado con `install.sh`: basta con ejecutar el `install.sh` de la 0.4.0 (actualiza el código y conserva los datos).
   - Plesk con despliegue desde Git: haz `git pull` / despliega la etiqueta v0.4.0.
4. Si PHP-FPM tiene OPcache sin revalidación (`opcache.validate_timestamps=0`), reinícialo.
5. **Abre la web**: en la primera petición la base de datos pasa sola al esquema 15 (o ejecuta `php bin/uw site:list`).
   No se pierde nada: las fechas de «último cambio» salen de las que ya tenías.
6. Entra en **Plataforma → Actualizaciones**: debe decir 0.4.0. A partir de aquí, las siguientes versiones se instalan
   desde ahí.

### Riesgos al actualizar desde la 0.3.0
- En el paso manual, los `.htaccess` del paquete sustituyen a los tuyos: si los cambiaste (p. ej. la versión de PHP en
  cPanel), guárdalos antes. Desde la 0.4.0 el actualizador conserva los modificados y lo avisa.
- Si PHP-FPM tiene `opcache.validate_timestamps=0`, recárgalo tras actualizar desde la terminal.
- Los enlaces internos que apuntaban a una dirección con 301 ahora se escriben directos a su destino (es lo que quiere
  Google, pero cambia el HTML).
- La web pública envía una CSP de base: si cargas `<object>`/`<embed>`, o enmarcas tu web desde otro dominio, revísalo.
- La caché de página se activa sola: si tocas archivos del tema a mano sin pasar por el panel, vacíala (`cache_clear` o
  desactivar y activar en Seguridad y red) o espera una hora.

## [0.3.0] — 2026-11

Primera versión pública.
