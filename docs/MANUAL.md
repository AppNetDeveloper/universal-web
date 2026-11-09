# Manual de usuario de Universal Web

Guía del panel para quien lleva la web: el dueño del negocio, la persona de diseño o la agencia.
No hace falta saber programar. Si ves un término raro, lo explicamos en media línea.

> Los nombres de menús, botones y campos están escritos **tal como aparecen en el panel** (en español).
> Cada persona puede cambiar el idioma del panel en *Mi cuenta* (español, rumano o inglés).

## Índice

1. [Qué es Universal Web y cómo entrar](#1-qué-es-universal-web-y-cómo-entrar)
2. [Tu primera web en 10 minutos](#2-tu-primera-web-en-10-minutos)
3. [Páginas y bloques](#3-páginas-y-bloques)
4. [Blog](#4-blog)
5. [Imágenes y archivos (Medios)](#5-imágenes-y-archivos-medios)
6. [Diseño: temas, CSS/JS propio y plantilla HTML](#6-diseño-temas-cssjs-propio-y-plantilla-html)
7. [Idiomas y traducciones](#7-idiomas-y-traducciones)
8. [Formularios, mensajes, horario y carta](#8-formularios-mensajes-horario-y-carta)
9. [SEO y GEO: las auditorías y sus avisos](#9-seo-y-geo-las-auditorías-y-sus-avisos)
10. [Ver tu web antes de tener dominio y poner tu dominio](#10-ver-tu-web-antes-de-tener-dominio-y-poner-tu-dominio)
11. [Copias de seguridad](#11-copias-de-seguridad)
12. [Usuarios, permisos y seguridad del panel](#12-usuarios-permisos-y-seguridad-del-panel)
13. [Tu web con ADRI (la IA de BionicAdri)](#13-tu-web-con-adri-la-ia-de-bionicadri)
14. [Preguntas frecuentes y «si algo va mal»](#14-preguntas-frecuentes-y-si-algo-va-mal)
15. [Anexo: comandos de servidor (opcional)](#anexo-comandos-de-servidor-opcional)

---

## 1. Qué es Universal Web y cómo entrar

**Universal Web** es un gestor de webs ligero. Con una sola instalación puede servir **varias webs**
(cada una con su dominio, su contenido, su diseño y sus usuarios). Todo se maneja desde un **panel**
sencillo: páginas hechas con bloques, un blog, imágenes, mensajes de contacto, SEO y copias de seguridad.
Además tiene una «puerta» pensada para asistentes de IA (**MCP**, lo explicamos en el [apartado 13](#13-tu-web-con-adri-la-ia-de-bionicadri))
para que, si quieres, **ADRI** o Claude te ayuden a montar y mantener la web.

### Entrar al panel

1. Abre `https://tu-dominio.com/admin` (si tu administrador configuró un dominio exclusivo para el panel,
   será ese dominio; pregúntale cuál es).
2. Escribe tu **Email** y tu **Contraseña** y pulsa **Entrar**.
3. Si tienes la verificación en dos pasos activada, el panel te pide el **código de 6 dígitos** de tu app
   de autenticación (o uno de tus códigos de recuperación).

Tras demasiados intentos fallidos el panel te hace esperar unos minutos. Es normal: protege tu cuenta.

### Si olvidas la contraseña

En la pantalla de acceso pulsa **¿Has olvidado tu contraseña?**, escribe tu email y pulsa **Enviar enlace**.
Te llega un enlace de un solo uso que **caduca en 1 hora**. Revisa también el spam.

> Si en esa pantalla aparece «Este panel todavía no tiene configurado el envío de correos», no hay recuperación
> automática: pide a un administrador que te ponga una contraseña nueva (o mira el [anexo](#anexo-comandos-de-servidor-opcional)).
> El superadmin activa el envío en **Plataforma → Correo del panel**.

### Doble factor (verificación en dos pasos)

Es un segundo candado: además de la contraseña hace falta un código que cambia cada pocos segundos y
sale de una app en tu móvil (Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden…).
Se activa en **Mi cuenta → Verificación en dos pasos → Activar verificación en dos pasos**:
escaneas el QR, escribes el código de 6 dígitos y pulsas **Confirmar y activar**.
**Guarda los códigos de recuperación** que te muestra: se ven una sola vez y cada uno sirve una vez, por si pierdes el móvil.

### Cómo es el panel

- **Menú de la izquierda**, en tres grupos: **Contenido** (Panel, Páginas, Entradas, Medios, Mensajes, SEO,
  Errores, Papelera, Tokens de IA), **Administración** (Ajustes, Temas, Plantilla HTML, Diseño y código, Idiomas,
  Cookies, Formularios, Publicación, Copias de seguridad, Usuarios, Actividad) y **Plataforma** (Webs,
  Seguridad y red, Correo del panel). Cada persona ve solo lo que su rol permite (mira el [apartado 12](#12-usuarios-permisos-y-seguridad-del-panel)).
- Arriba del menú aparece el **nombre de la web actual** y el enlace **Ver web**. Si gestionas varias webs,
  es un desplegable para cambiar de una a otra.
- **Panel** (la portada del panel) muestra contadores (páginas, entradas publicadas, borradores, mensajes sin leer,
  archivos, puntuación SEO), la actividad reciente, los botones **Nueva entrada** y **Nueva página** y una tarjeta
  **Conectar con IA (MCP)** con la dirección para tu asistente.
- Abajo a la izquierda, tu nombre abre **Mi cuenta** y el icono de salida cierra la sesión.
- En el móvil, el botón de las tres rayas abre el menú.
- **Guíame** (botón redondo abajo a la derecha, en todas las pantallas): te explica la pantalla en la que estás paso a paso. Resalta cada campo y cada botón y te dice para qué sirve; avanzas con **Siguiente** y vuelves con **Anterior** (también con las flechas del teclado; **Esc** lo cierra).
- **Ayuda** abre este manual. Con **Descargar PDF** (arriba del índice) lo guardas en PDF o lo imprimes: sale con portada y cada capítulo en una página nueva. En el móvil: **Descargar PDF → Guardar como PDF** en la ventana de impresión.

---

## 2. Tu primera web en 10 minutos

Recorrido recomendado. Todo se hace en **Administración → Ajustes** salvo que se diga otra cosa.

### Paso 1. Elige el tema (el «aspecto» de la web)

Ve a **Administración → Temas**. Verás una galería con una captura de cada uno y el texto «Ideal para…».
Puedes probar cualquiera con **Vista previa** sin tocar tu web publicada y, cuando te guste, pulsar **Aplicar**.

| Tema | Aspecto | Pensado para |
|---|---|---|
| **Default** | Claro, limpio y moderno | Web genérica, profesional autónomo, asociación, proyecto personal |
| **Noche** | Oscuro y elegante | Cafetería de especialidad, coctelería y bar de noche, música y eventos, fotografía, marca premium |
| **Corporativo** | Profesional, con barra superior de contacto y botón destacado en la cabecera | Empresas de servicios, industria y logística |
| **Industrial** | Oscuro y robusto | Taller mecánico, reformas y construcción, carpintería metálica, fontanería y electricidad, logística pesada, maquinaria |
| **Editorial** | Tipográfico y sereno | Despachos de abogados, consultoras, asesorías y gestorías, arquitectura, psicología y terapias, blogs o revistas, escritores y formadores |
| **Cálido y local** | Acogedor y cercano | Restaurantes, cafeterías y panaderías, bares de tapas, tiendas locales, peluquerías y barberías, estética, floristerías, alojamiento rural |
| **Minimal** | Limpio y moderno | Startups, apps y SaaS, empresas tecnológicas, estudios digitales o agencias, fotógrafos y portfolios |
| **Vibrante** | Colorido, enérgico y atrevido | Gimnasios, centros deportivos, escuelas de baile, academias, ocio infantil, eventos, campamentos, clubes deportivos |
| **Natural** | Sereno y orgánico, verdes suaves | Clínicas y centros médicos, fisioterapia, psicología, dentistas, yoga y pilates, nutrición, productos ecológicos, agricultura |
| **Prestigio** | Lujo discreto: marfil y bronce, fotos grandes | Inmobiliaria premium, hotel boutique, bodegas y enoturismo, joyería, bodas, interiorismo, galerías de arte, restaurante de autor |

Todos funcionan en español, inglés y rumano, y se ven bien en móvil, tablet y ordenador.
Cambiar de tema **no toca tu contenido**: solo cambia cómo se ve.

### Paso 2. Datos generales (**Ajustes → General**)

| Campo | Qué poner |
|---|---|
| **Nombre de la web** | El nombre de tu negocio. Sale en la cabecera y en Google. |
| **Eslogan** | Una frase corta que diga qué haces. |
| **Idioma** | El código del idioma principal (`es`, `en`, `ro`…). |
| **Tema** | El mismo desplegable que en Temas. |
| **Color de acento** | El color de tu marca: se usa en botones, enlaces y detalles. Cada tema trae uno sugerido (verás un cuadrito de color en su ficha de Temas). |
| **Logo** | La dirección de tu logo (empieza por `/media/`). Súbelo antes en **Medios**, pulsa **Copiar URL** y pégala aquí. Si empiezas a escribir `/media/…` te sugiere los archivos que ya subiste. |
| **Texto del pie** | La línea que aparece abajo del todo (derechos, razón social…). |

### Paso 3. Menú

En la tarjeta **Menú** pulsa **+ Añadir enlace** por cada opción: un texto (por ejemplo «Servicios») y la
dirección a la que lleva (por ejemplo `/servicios`). Las direcciones de tus páginas son `/` + el *slug*
de la página (la portada es `/`). Puedes reordenar y quitar enlaces. No olvides **Guardar ajustes** al final.

### Paso 4. Contacto y redes

- **Contacto**: **Email**, **Teléfono** y **Dirección**. Se usan en el pie, en la barra superior de algunos
  temas y en los datos que leen Google y las IAs. Pon solo datos reales.
- **Redes sociales**: pega la dirección completa (`https://…`) de Instagram, Facebook, X, LinkedIn, YouTube,
  GitHub, TikTok y el enlace de WhatsApp (`wa.me`).

### Paso 5. Botón destacado de la cabecera

En **Cabecera**: **Texto del botón destacado** (por ejemplo «Solicitar presupuesto») y **Enlace del botón**
(por ejemplo `/contacto`). Lo muestran los temas que lo admiten, como **Corporativo**. En **Enlaces secundarios**
puedes añadir, uno por línea, enlaces para la barra superior con el formato `Texto | dirección`
(por ejemplo `Área de clientes | https://…`).

### Paso 6. SEO por defecto

En **SEO por defecto** escribe una **Descripción** de tu negocio de 50 a 160 caracteres (el contador te guía)
y elige una **Imagen para compartir** (la que se verá cuando alguien comparta tu web en redes).

### Paso 7. Crea tus páginas y publica

Mira el [apartado 3](#3-páginas-y-bloques): necesitas al menos una página con *slug* `index` (la portada) en
estado **Publicada**. Después pulsa **Ver web** en el menú para comprobar el resultado.

Otras tarjetas de Ajustes que verás más adelante: **Visibilidad en IAs (GEO)** ([apartado 9](#9-seo-y-geo-las-auditorías-y-sus-avisos)),
**Acceso privado (modo demo)** y **Dominios** ([apartado 10](#10-ver-tu-web-antes-de-tener-dominio-y-poner-tu-dominio)).

---

## 3. Páginas y bloques

Una **página** se construye apilando **bloques** (como piezas de Lego): una portada, un texto, una galería,
unas preguntas frecuentes… Tú rellenas los campos de cada bloque y el tema se encarga de que quede bonito.

### Crear una página

1. **Contenido → Páginas → Nueva página**.
2. Rellena:
   - **Título**.
   - **Slug**: la parte final de la dirección. `servicios` → `tu-dominio.com/servicios`. Se genera solo desde el título;
     usa minúsculas, números y guiones. **`index` es la portada.**
   - **Estado**: **Borrador** (solo la ves tú desde el panel) o **Publicada** (la ve todo el mundo).
3. En **Bloques**, elige un tipo en el desplegable y pulsa **+ Añadir bloque**.
4. Pulsa **Guardar**.

Cada bloque tiene botones para **subir** (↑), **bajar** (↓), **duplicar** (⧉), plegar y **eliminar** (✕).
Los campos de imagen son una caja de texto con la dirección (`/media/…`): empieza a escribir y te sugiere
los archivos de **Medios**; al lado ves una miniatura. Si eres técnico, el interruptor **Editar JSON** muestra
los bloques como texto; no hace falta usarlo.

En la lista de páginas verás **Editar**, **Ver** y **Borrar** por fila. Los textos largos admiten **Markdown**
(mira el recuadro de abajo).

> **Markdown en dos minutos.** `## Título` hace un subtítulo · `**negrita**` · `*cursiva*` · `- elemento` hace una lista ·
> `1. paso` una lista numerada · `[texto](https://…)` un enlace · `> cita` una cita. No hay tablas.

### Los bloques disponibles

| Bloque | Para qué sirve |
|---|---|
| **Portada** | La cabecera grande de la página. Úsala **una sola vez y al principio**: su título es el título principal (H1). Admite antetítulo, subtítulo, imagen y dos botones. |
| **Texto** | Una sección de texto con título opcional. El contenido admite Markdown. |
| **Características** | Una rejilla de tarjetas (servicios, ventajas…). Cada tarjeta lleva icono (un nombre como `truck`, `clock`, `shield`, `heart`, `star`, `phone`… o un emoji), título, texto y enlace opcional. |
| **Cifras** | Una fila de números destacados («25 años», «+300 clientes»). Muy citadas por las IAs. |
| **Imagen** | Una imagen con pie de foto. El **texto alternativo** es obligatorio para SEO y accesibilidad. |
| **Galería** | Una rejilla de fotos y vídeos; cada elemento lleva imagen, texto alternativo y, si quieres, un vídeo propio o de YouTube/Vimeo. |
| **Escuchar esta página** | Un reproductor con la versión en audio de la página (voz grabada o generada por IA) y su transcripción desplegable. Colócalo justo después de la portada. |
| **Llamada a la acción** | Una banda destacada con un título, un texto y un botón. |
| **Preguntas frecuentes** | Lista de preguntas y respuestas (las respuestas admiten Markdown). Son lo que más citan los buscadores con IA. |
| **Código** | Un bloque de código o configuración (para webs técnicas). |
| **Últimas entradas** | Muestra las últimas entradas publicadas del blog; tú eliges la **Cantidad**. |
| **Vídeo** | Un vídeo propio (archivo subido en Medios) o de YouTube/Vimeo (pega el enlace normal), con imagen de portada y pie opcionales. |
| **Audio** | Reproductor de audio (podcast, música, locución). |
| **Imagen + texto** | Dos columnas: imagen a un lado y texto al otro, con botón opcional. Puedes poner la imagen a la derecha. |
| **Proceso por pasos** | Pasos numerados («Cómo trabajamos», «Cómo contratar»). |
| **Tarjetas con imagen** | Una rejilla de tarjetas con foto: productos, tipos de servicio, equipo, proyectos. |
| **Logos** | Una fila de logos de clientes, partners o certificaciones. |
| **Testimonios** | Citas de clientes o del equipo, con nombre, cargo y foto. |
| **Horario** | El horario de apertura. Muestra «Abierto ahora» y lo leen Google y las IAs. ([apartado 8](#8-formularios-mensajes-horario-y-carta)) |
| **Carta / Precios** | Lista de productos o servicios con precio: carta de restaurante, tarifas de peluquería, precios de taller. ([apartado 8](#8-formularios-mensajes-horario-y-carta)) |
| **Formulario de contacto** | Un formulario; los mensajes llegan a **Mensajes**. Puede pedir teléfono y exigir la casilla de privacidad. ([apartado 8](#8-formularios-mensajes-horario-y-carta)) |

Consejo: una página que funciona suele ser **Portada → Texto o Características → Cifras → Testimonios →
Preguntas frecuentes → Llamada a la acción**.

### Borrador y publicado

- **Borrador**: la página existe pero **nadie fuera del panel puede verla**. Desde la lista, **Ver** la abre
  en una pestaña nueva (necesitas tener la sesión del panel abierta).
- **Publicada**: visible para todos.
- Las páginas no se programan; las **entradas del blog** sí ([apartado 4](#4-blog)).

### SEO de la página

Al final de cada página hay una tarjeta **SEO**:

| Campo | Para qué |
|---|---|
| **Título SEO** | El título que sale en Google. Opcional: si lo dejas vacío se usa el título de la página. Mejor por debajo de 60 caracteres. |
| **Meta descripción** | El resumen que sale bajo el título en Google. Objetivo: **50–160 caracteres** (hay contador). |
| **Imagen para compartir** | La imagen que se ve al compartir la página en redes. |
| **No indexar (noindex)** | Marca esta casilla si NO quieres que la página aparezca en Google. |

### Historial y papelera: tu botón de «deshacer»

- **Historial**: al final de la edición de una página (o entrada) hay un desplegable **Historial** con las
  **30 últimas versiones anteriores**: quién la guardó, cuándo y un resumen. **Restaurar** vuelve a esa
  versión; **no borra nada**: la versión actual pasa al historial, así que también puedes deshacer el «deshacer».
- **Papelera** (menú **Contenido → Papelera**): al **Borrar** una página o entrada va aquí durante **30 días**.
  Pulsa **Recuperar** y vuelve con su contenido y su estado. Vale tanto para lo que borras tú como para lo que
  borre una IA.
- Si cambias el *slug* de una página publicada, la dirección antigua se redirige sola a la nueva
  (ver **SEO → Redirecciones 301**).

---

## 4. Blog

**Contenido → Entradas → Nueva entrada**. Las entradas viven en `tu-dominio.com/blog/su-slug`.

| Campo | Qué es |
|---|---|
| **Título** y **Slug** | Como en las páginas. El título ya es el título principal: no pongas otro `#` dentro del texto. |
| **Extracto** | Un resumen corto, para listados y buscadores. |
| **Contenido** | El artículo, en **Markdown**. Para ver el resultado: **Guardar** y luego **Vista previa**. |
| **Estado** | **Borrador** o **Publicada**. |
| **Fecha de publicación** | Vacía = se publica «ahora». Si pones una **fecha futura**, la entrada queda **Programada** y se publica sola cuando llega. |
| **Autor** | Quien firma. |
| **Etiquetas** | Palabras separadas por comas (`cocina, consejos`). |
| **Imagen de portada** | La foto de cabecera; también se usa al compartir en redes. |
| **Audio «Escuchar este artículo»** | Opcional: un audio (`/media/….mp3`) con el artículo leído. |
| Tarjeta **SEO** | Igual que en las páginas. |

La lista de entradas tiene pestañas **Todas / Publicadas / Borradores** y una insignia de estado
(**Publicada**, **Programada**, **Borrador**). Las entradas también tienen **Historial** y, si las borras, van a la **Papelera** igual que las páginas.

Para que las últimas entradas salgan en tu portada, añade el bloque **Últimas entradas**.

---

## 5. Imágenes y archivos (Medios)

**Contenido → Medios** es tu biblioteca. Desde aquí subes todo lo que luego usas en páginas, blog, logo y favicon.

### Subir

- **Archivos**: elige uno o varios. Tipos admitidos: imágenes (JPG, PNG, GIF, WebP, AVIF, ICO), PDF, y audio y
  vídeo (MP4, WebM, MP3, OGG, M4A, WAV). Audio y vídeo hasta 100 MB (aunque el límite de tu hosting puede ser menor).
  Las fotos JPG, PNG y WebP se aceptan hasta 25 MB y se reducen solas al subirlas; PDF y el resto de imágenes (GIF, AVIF, ICO), hasta 8 MB. (La etiqueta del formulario muestra estos mismos límites.)
- **Texto alternativo**: describe lo que se ve en la imagen («Fachada de la panadería al amanecer»). Se aplica
  a todos los archivos que subas a la vez. Sirve para personas que usan lector de pantalla y para Google. Es
  uno de los avisos más frecuentes de la auditoría SEO.
- **Añadir desde URL**: pega la dirección pública de un archivo y pulsa **Descargar y añadir**: se guarda en tu biblioteca.
  Úsalo solo con archivos que sean tuyos o tengas permiso para usar.

Cada archivo muestra su tamaño y los botones **Copiar URL** (para pegarla donde la necesites) y **Borrar**.
Ojo: si borras un archivo que usa una página, esa página mostrará una imagen rota.

### Optimización automática

Las fotos (JPG, PNG, WebP) se **reducen solas a un máximo de 2000 px** de lado mayor y se pasan a **WebP**
si así pesan menos. Se **elimina el EXIF** (por ejemplo, el lugar donde hiciste la foto con el móvil) y
se crean además versiones pequeñas para móvil. No tienes que hacer nada.

### Marca de IA (Ley de IA de la UE)

La Ley de IA de la Unión Europea (art. 50) exige que el contenido generado por IA sea **reconocible por
máquinas**. Universal Web lo cumple así:

- Las imágenes generadas por IA llevan una **marca legible por máquina** (un dato invisible dentro del archivo).
  En **Medios** se distinguen por la insignia azul **IA**.
- La marca **sobrevive a la optimización**: aunque el servidor reduzca y convierta la imagen, la marca se vuelve
  a escribir en la imagen y en sus versiones para móvil.
- Si subes a mano en el panel una imagen que **ya trae la marca** (por ejemplo, creada por ADRI), se detecta y se conserva.
  Una imagen de IA sin marca subida a mano **no se marca sola**: en el panel no hay casilla para declararlo.
- Cuando una IA sube un archivo por MCP, tiene que declarar si lo ha generado ella (`ai_generated`); entonces se marca.
- **El panel no ofrece ningún botón para quitar la marca.** No intentes borrarla: es una obligación legal.

---

## 6. Diseño: temas, CSS/JS propio y plantilla HTML

Hay tres niveles, de menos a más libertad. Empieza siempre por el primero.

### Nivel 1. Cambiar de tema (**Administración → Temas**)

- **Vista previa**: abre la web con ese tema en una pestaña nueva, con un enlace temporal (2 horas) que solo
  vale para quien lo abre. **No cambia nada de lo publicado.**
- **Aplicar**: cambia el tema de la web actual (pide confirmación) y se ve al instante.
- **Descargar con manual**: baja el tema en `.zip` junto con la guía para desarrolladores. Para tu diseñador.
- **Deshacer**: vuelve a pulsar **Aplicar** en el tema anterior. Tus textos e imágenes no se tocan.
- Los temas «De serie» no se pueden borrar. **Subir** o **borrar** temas propios solo lo hace un superadmin y
  está desactivado por defecto (un tema es código que se ejecuta en el servidor): es cosa de tu informático.

### Nivel 2. Retoques propios (**Administración → Diseño y código**)

Para quien sabe un poco de CSS (o para pedírselo a tu IA). Tiene cinco pestañas:

| Pestaña | Qué hace |
|---|---|
| **CSS** | Tus estilos, que se cargan **después** del tema y pueden sobrescribirlo. Dentro de **Variables del tema y ejemplo** tienes la lista de colores, fuentes y redondeos que puedes cambiar (`--accent`, `--bg`, `--radius`, `--font-head`…). |
| **JavaScript** | Código que se ejecuta en todas las páginas (con `defer`, al final). |
| **`<head>`** | HTML que se añade en la cabecera: analítica, verificación de dominio, fuentes… |
| **Favicon** | El iconito de la pestaña del navegador. Sube una imagen en Medios y pega aquí su dirección. |
| **Historial** | Las **30 últimas versiones**. **Restaurar** vuelve a una; se guarda como versión nueva. |

> **Cuidado.** El JavaScript y el código del `<head>` **se ejecutan en tu web pública**. Pega solo código de
> fuentes de confianza. Cada **Guardar** (o Ctrl/Cmd+S) **aplica el cambio al momento** en la web pública: aquí no
> hay vista previa previa (el botón **Vista previa** de arriba abre tu web tal cual está publicada). Si algo se rompe,
> ve a **Historial → Restaurar**. Antes de un cambio grande, haz una [copia de seguridad](#11-copias-de-seguridad).

### Nivel 3. Plantilla HTML propia (**Administración → Plantilla HTML**)

Para darle a una web una estructura totalmente distinta (cabecera, pie, maquetación) **sin programar en el servidor**.
Escribes HTML normal con **marcadores** que Universal Web rellena (`{{{content}}}` para el contenido de la página,
`{{site.name}}` para el nombre, `{{#menu}}…{{/menu}}` para el menú…). El contenido sigue saliendo de los bloques,
así que SEO, idiomas, cookies y formularios siguen funcionando.

Cómo trabajar sin riesgo:

1. Con la plantilla **desactivada** (insignia **Desactivada**), edita el **HTML** (si está vacío, el panel te
   pone una **plantilla de partida**) y el **CSS de la plantilla**. Pulsa **Guardar**.
2. Tras guardar aparece el botón **Vista previa** (arriba a la derecha): abre tu web con la plantilla, con un
   enlace temporal de 2 horas. Nadie más la ve todavía.
3. Cuando te guste, marca **Usar esta plantilla en la web pública** y pulsa **Guardar**. Pasa a **Activa**.
4. **Cargar también el CSS y JS del tema (estilos de los bloques)** conviene dejarlo marcado: así los bloques
   conservan su aspecto. Desmárcalo solo si vas a escribir todo el CSS tú.

Qué NO se admite (por seguridad): `<script>`, atributos `on…` (como `onclick`), enlaces `javascript:`, PHP, `<base>`,
`<object>`, `<embed>` y `<meta http-equiv>`. El JavaScript va en **Diseño y código**. A la izquierda de la pantalla,
bajo el editor, tienes la lista completa de **Marcadores**. Si el panel avisa de que falta algo obligatorio (`{{{head}}}` y
`{{{content}}}`), añádelo y vuelve a guardar. El crédito «Hecho con Universal Web» se añade solo si falta.

**Deshacer**: el recuadro **Historial** guarda cada versión; **Restaurar** vuelve a una anterior. Para volver al tema normal,
desmarca **Usar esta plantilla en la web pública** y guarda.

---

## 7. Idiomas y traducciones

### Activar idiomas (**Administración → Idiomas**)

1. El **idioma principal** se elige en **Ajustes → Idioma**. Va sin prefijo en la dirección.
2. En **Idiomas adicionales** marca los que quieras (inglés, rumano, francés, alemán, italiano, portugués,
   catalán, euskera, gallego, neerlandés, polaco, ucraniano, ruso, árabe, chino…) y pulsa **Guardar idiomas**.
   Cada uno vive bajo su prefijo: `/en/`, `/ro/`…
3. Quitar un idioma **no borra** su contenido.

### Traducir páginas y entradas

- En **Páginas**, al lado de cada una aparecen insignias por idioma: verde = publicada, gris = borrador,
  **«+ EN»** (por ejemplo) = sin traducir. Pulsa **+ EN** para crear la traducción: se abre con una **copia** del original.
- **Reescribe** todos los textos (y los enlaces internos con el prefijo, por ejemplo `/en/…`), escribe el
  **Título SEO** y la **Meta descripción** en ese idioma y guarda. **Mantén el mismo slug** para que se enlacen como traducciones.
- En **Entradas** hay una pestaña por idioma y el mismo «+ EN».
- Un idioma **no aparece** en la web pública (selector de idioma, Google) hasta que tiene su **portada publicada**.

### Textos generales de cada idioma

En **Idiomas**, bajo cada idioma adicional, la tarjeta **Textos de la web en…** permite traducir el **Eslogan**, el
**Texto del pie**, la **Descripción SEO por defecto**, los menús, el **botón destacado** y el **texto del aviso de
cookies**. Lo que dejes vacío se muestra en el idioma principal. La tabla **Estado de las traducciones** te dice qué
falta (páginas, entradas y portada por idioma).

> Atajo con IA: «Traduce toda la web al inglés». Mira el [apartado 13](#13-tu-web-con-adri-la-ia-de-bionicadri).

---

## 8. Formularios, mensajes, horario y carta

### Recibir mensajes

1. Añade el bloque **Formulario de contacto** a una página (normalmente `contacto`). Opciones: **Título**, **Texto**,
   **Texto del botón**, **Pedir teléfono (sí/no)** y **Enlace a la política de privacidad** (si lo rellenas, aparece una
   casilla obligatoria de privacidad, necesaria por el RGPD).
2. Cada mensaje queda **siempre** guardado en **Contenido → Mensajes** (con un contador de «sin leer» en el menú).
   Despliega uno para leerlo y usa **Responder** (abre tu programa de correo), **Marcar como leído / no leído** o **Borrar**.
3. El aviso por email es un extra: si falla, el mensaje no se pierde; verás la insignia **Error de envío** y el botón
   **Reintentar envío**.

### Configurar el aviso por correo (**Administración → Formularios**, solo administradores)

Cada web tiene su propia configuración:

| Tarjeta | Qué hace |
|---|---|
| **Aviso por email** | Marca **Enviar un email cada vez que llegue un mensaje** y escribe los **Destinatarios** (uno por línea). El correo lleva el email del visitante como «Responder a». |
| **Envío** | Cómo sale el correo: **Servidor SMTP (recomendado)** — pide los datos que te da tu proveedor de correo (servidor, puerto, cifrado, usuario, contraseña) — o **mail() de PHP (hosting)** si tu hosting lo permite. La contraseña se guarda cifrada y no se vuelve a mostrar. |
| **Respuesta automática al visitante** | Confirma al que escribe con un texto tuyo (`{nombre}` y `{web}` se sustituyen). Solo se puede activar con Turnstile activo (evita que usen tu formulario para mandar correo a terceros). |
| **Antispam: Cloudflare Turnstile** | Verificación gratuita y casi invisible. Creas un widget en Cloudflare y pegas las dos claves. Siempre hay además un campo trampa, un tiempo mínimo y un límite por IP. |
| **Webhook** | Envía cada mensaje a otro sistema (por ejemplo tu CRM) en formato JSON firmado. Para equipos técnicos. |
| **Probar el envío** | **Enviar correo de prueba**: guarda antes los cambios; si algo falla verás el error exacto. |

### Horario (bloque **Horario**)

Cada fila es un tramo:

- **Días (lu-vi, sa, do…)**: usa `lu, ma, mi, ju, vi, sa, do`. Rangos con guion (`lu-vi`) y listas con comas (`lu,mi`).
- **Abre** y **Cierra** en formato `09:00` / `14:00`. Si hay turno de tarde: **Abre tarde** y **Cierra tarde**.
- **Cerrado (sí/no)**: pon `sí` para los días cerrados.
- **Texto (opcional)**: si quieres que aparezca algo como «Lunes a viernes»; si lo dejas vacío se genera solo.
- **Nota**: festivos, cita previa…

La web mostrará **«Abierto ahora»** según la hora actual, y Google y las IAs podrán contestar «¿está abierto?».

### Carta y precios (bloque **Carta / Precios**)

Pensado para cartas de restaurante, tarifas de peluquería o estética y precios de taller:

- Campos generales: **Antetítulo**, **Título**, **Subtítulo**, **Moneda** (€ por defecto) y **Nota** (IVA incluido, alérgenos…).
- Por cada elemento: **Sección** (agrupa: «Entrantes», «Principales»…), **Nombre**, **Descripción**, **Precio**
  (texto libre: `12`, `12,50`, `desde 30`) y **Etiqueta** (Nuevo, Vegano…).

---

## 9. SEO y GEO: las auditorías y sus avisos

**SEO** es salir bien en buscadores clásicos (Google, Bing). **GEO** es aparecer y ser citado en las respuestas
de buscadores con IA (ChatGPT, Perplexity, Gemini, Claude…). Ambos se miran en **Contenido → SEO**.

### La puntuación

- Cada aviso resta puntos a un 100: **Error** (-20), **Aviso** (-6), **Sugerencia** (-2) en SEO; (-25 / -8 / -3) en GEO.
- Verde a partir de 85, ámbar de 60 a 84, rojo por debajo. La puntuación SEO también aparece en el **Panel**.
- No persigas el 100 a toda costa: arregla primero los **Error** y luego los **Aviso**.

### SEO: qué significa cada aviso y qué hacer

| Aviso | Qué hacer |
|---|---|
| No hay página de inicio publicada | Crea (o publica) una página con slug `index`. |
| Falta la descripción SEO por defecto | **Ajustes → SEO por defecto → Descripción** (50–160 caracteres). |
| Falta el eslogan | **Ajustes → General → Eslogan**. |
| Falta la meta descripción | En la página (o entrada), tarjeta **SEO → Meta descripción**. En entradas basta también un **Extracto**. |
| Meta descripción fuera de 50–160 caracteres / título de más de 60 | Acórtalo o alárgalo. |
| Título duplicado | Dos páginas tienen el mismo título: cambia el **Título SEO** de una. |
| Más de un bloque Portada | Deja una sola **Portada** por página (se usa como título principal). |
| Imagen sin texto alternativo | Rellena el **Texto alternativo** del bloque (o en **Medios**). |
| Imágenes enlazadas desde otra web | Usa imágenes tuyas o generadas por IA: sube el archivo a **Medios** y usa su dirección `/media/…`. Las de otras webs tienen derechos de autor. |
| El enlace del menú lleva a una página que no existe o no está publicada | Publícala/tradúcela o quita el enlace del menú de ese idioma. |
| Marcada como noindex | Es solo una nota: la página no saldrá en Google. Si no era tu intención, desmarca la casilla. |
| Sin versión en «xx» | Falta traducir esa página a ese idioma ([apartado 7](#7-idiomas-y-traducciones)). |
| Entrada con contenido corto, sin portada o sin extracto | Amplía el texto, añade **Imagen de portada** o **Extracto**. |

> En la columna **Cómo** de la auditoría GEO (y en algunos mensajes) aparecen frases técnicas como `site_update contact.phone`:
> son instrucciones pensadas para una IA. Tradúcelas así: `site_update contact.*` = **Ajustes → Contacto**,
> `site_update seo.description` = **Ajustes → SEO por defecto**, `site_update business.*` = **Ajustes → Visibilidad en IAs (GEO)**,
> `site_update social` = **Ajustes → Redes sociales**, `site_update logo` = **Ajustes → Logo**, `page_save seo.description` = **SEO de la página**,
> `media_upload` = **Medios → Subir**.

### GEO: qué mira y qué hacer

- **Identidad clara**: descripción, dirección, teléfono, email, logo, **Tipo de negocio**, **Zona de servicio**, **Año de fundación**
  y perfiles sociales. Se rellenan en **Ajustes** (tarjeta **Visibilidad en IAs (GEO)** y **Contacto**).
- **Contenido citable**: preguntas frecuentes (bloque **Preguntas frecuentes**, 4–8 preguntas reales de clientes),
  cifras concretas (bloque **Cifras**), páginas con suficiente texto y horario si tienes local.
- **Frescura**: avisa si nada se actualizó en 6 meses o si el blog lleva 90 días sin entradas.
- **Rastreadores de IA (robots.txt)**: en **Ajustes → Visibilidad en IAs (GEO)**. Si eliges bloquearlos del todo, no aparecerás en sus respuestas
  (la auditoría lo marca como **Error**).
- **Servir cada página en Markdown a los agentes de IA**: casilla en la misma tarjeta. Google y Bing siempre ven la web normal.
- Los archivos para IAs (`/llms.txt`, `/llms-full.txt`, `robots.txt`) **se generan solos**; en la pantalla SEO tienes los enlaces.

### Redirecciones 301

En **SEO → Redirecciones 301** (solo administradores) puedes añadir redirecciones a mano: **Desde (ruta antigua)** →
**Hacia (ruta o https://…)**. Sirve cuando mueves o renombras una página que ya estaba en Google. La tabla muestra cuántas visitas ha recibido cada una.

---

## 10. Ver tu web antes de tener dominio y poner tu dominio

### Ver una web sin dominio: la vista previa `/_ver/…`

Una web nueva no se ve en internet hasta que su dominio apunta al servidor (puede tardar horas). Para
no trabajar a ciegas y poder **enseñársela al cliente antes**, Universal Web ofrece un **enlace de vista previa**:

- Tiene la forma `https://panel-o-servidor/_ver/nombre-de-la-web?t=…`, es **privado** (lleva una firma) y **dura 7 días**.
- Al abrirlo, ese navegador ve la web como si ya tuviera dominio, con todos sus enlaces. Verás una barra
  abajo: «**Vista previa de «Nombre» (aún sin dominio)**» con un enlace **Salir**.
- No se indexa en Google ni se guarda en caché.
- **Dónde se pide en el panel:** el botón **Vista previa sin dominio** está en **Plataforma → Webs** (una fila por web,
  solo superadmin) y en el **Panel** de la web actual (para quien la administra: admin o superadmin). Se abre en una pestaña nueva.
- **Por IA:** también se lo puedes pedir a tu asistente (*«Dame el enlace de vista previa de la web»*). Lo devuelve al crear la web
  y cuando consulta los datos de la web (por MCP: `preview_url`). Mira el [apartado 13](#13-tu-web-con-adri-la-ia-de-bionicadri).
- **Ver un borrador:** el botón **Ver** de un borrador (página o entrada) lleva un enlace firmado de **1 hora**, así que funciona
  también en el dominio de la web aunque el panel tenga un dominio exclusivo distinto.

Otras formas de revisar sin publicar:

| Qué quieres ver | Cómo |
|---|---|
| Un tema distinto | **Temas → Vista previa** (enlace de 2 horas). |
| Una plantilla HTML sin activarla | **Plantilla HTML → Vista previa** (enlace de 2 horas). |
| Una página o entrada en borrador | **Ver** en la lista (con la sesión del panel abierta). |
| Toda la web, privada, con el dominio ya puesto | **Ajustes → Acceso privado (modo demo)**: marca **Web privada**, pon una **Contraseña para ver la web** (mínimo 6 caracteres) y comparte la clave. Solo se ve con contraseña y **no se indexa en Google**. Quienes tengan sesión en el panel la ven sin contraseña. El formulario de contacto sigue funcionando. Desmárcalo cuando quieras publicar de verdad. |

### Poner tu dominio

1. **Compra o localiza tu dominio** (`mitienda.com`) en tu registrador (donde lo compraste).
2. **Apúntalo al servidor.** Eso se hace en el **DNS** del dominio (la «agenda» que dice a internet en qué servidor vive
   cada dominio). Normalmente es crear un registro `A` con la **IP del servidor** que te dé tu informático o tu hosting. Los cambios pueden tardar de minutos a unas horas.
3. **Añade el dominio en el panel:** **Administración → Ajustes → Dominios**. Escribe **un dominio por línea**; el
   **primero es el principal**. Pulsa **Guardar ajustes**.
   - No puedes usar el dominio exclusivo del panel, ni uno que ya use otra web.
   - Los administradores de una web no pueden asignar IPs ni nombres locales (como `localhost`): solo el superadmin.
4. **El candado (HTTPS) lo pone el servidor, no el panel.** Si tu instalación se hizo con el instalador para VPS,
   el servidor pide el certificado él solo en cuanto el dominio apunta bien. En un hosting compartido (Plesk,
   cPanel…) normalmente hay que añadir también el dominio allí como alias o dominio adicional, y es el propio hosting quien emite el certificado.
   Si te sale un aviso de «conexión no segura», pregunta a quien administra el servidor.
5. Prueba `https://tu-dominio.com`. Tu web ya está en internet. Si tienes más de un dominio (con `www`, por ejemplo), añádelos también.

> **Con ADRI:** al crear una web, ADRI te da el enlace de vista previa y te dice exactamente qué registro DNS poner, con la IP del servidor, y si hace falta algo en el hosting. Si le das tu dominio, lo añade ella. Cuando le digas que ya cambiaste el DNS, lo comprueba (DNS y candado HTTPS) y te confirma cuándo está en marcha.

Si eres superadmin y quieres crear una web nueva entera: **Plataforma → Webs → Crear web** (nombre, identificador, tema,
dominios y la casilla **Crear páginas iniciales (Inicio y Contacto)**). El dominio es **opcional**: puedes crear la web sin dominio y revisarla con **Vista previa sin dominio** hasta que el cliente la apruebe; después añade el dominio en **Ajustes → Dominios**. Una web que ya tiene dominios no puede quedarse sin ninguno.

---

### Apuntar tu dominio paso a paso (DNS)

El **DNS** es la «agenda» de internet: le dice al mundo en qué servidor vive tu dominio. Se cambia en la web donde
compraste el dominio (el **registrador**: IONOS, GoDaddy, DonDominio, Arsys, Hostinger, Namecheap, OVH, Cloudflare…).
No hace falta mover el dominio ni cambiar de registrador.

**Qué registros crear.** La **IP del servidor** te la da ADRI (o quien administra el servidor). Con `mitienda.com` y la
IP `203.0.113.10` quedaría así:

| Tipo | Nombre | Valor | Para qué |
|---|---|---|---|
| A | `@` | `203.0.113.10` | El dominio sin «www» (`mitienda.com`) |
| CNAME | `www` | `mitienda.com` | La versión con «www» (`www.mitienda.com`) |
| AAAA | `@` | la IPv6 del servidor | Solo si el servidor tiene IPv6 (ADRI te lo dice) |

Para un **subdominio** (`tienda.mitienda.com`) basta un registro **A** con nombre `tienda` y la IP del servidor.

**Paso a paso:**

1. Entra en tu registrador y abre **DNS**, **Zona DNS** o **Gestionar DNS** del dominio.
2. **Borra** los registros A, AAAA y CNAME que ya existan para `@` y `www`: suelen apuntar a una página de «dominio aparcado» o a tu web antigua. Si los dejas, unas visitas irán a la web nueva y otras a la vieja.
3. **No toques** los registros **MX** ni **TXT** si usas el correo de ese dominio: tu correo seguirá funcionando igual.
4. **Crea** los registros de la tabla. En algunos paneles el nombre `@` se deja vacío o se escribe el dominio completo. El TTL déjalo en automático o en 3600.
5. **Guarda.** El cambio suele verse entre 5 minutos y 2 horas (como mucho, 48 horas).
6. **Avisa a ADRI:** «ya he cambiado el DNS». Ella comprueba que tu dominio apunta bien y que tiene el candado HTTPS, y te confirma cuándo está todo en marcha.

**Casos especiales:**

- **Cloudflare:** deja los registros en **«Solo DNS» (nube gris)** al menos hasta que el candado HTTPS funcione. Con la nube naranja, el servidor no puede comprobar tu dominio ni crear su certificado.
- **Tu registrador solo deja cambiar los «servidores de nombres» (nameservers):** cambia los registros en el servicio al que apuntan esos nameservers (por ejemplo, Cloudflare o tu hosting anterior).
- **El candado HTTPS:** en un servidor instalado con nuestro instalador se crea **solo** en cuanto el dominio apunta bien (puede tardar un minuto). En Plesk o cPanel, quien administra el hosting da de alta el dominio allí y emite el certificado gratuito de Let's Encrypt.
- **«La conexión no es segura»** justo después del cambio: espera unos minutos y vuelve a probar; el certificado puede estar creándose. Si sigue igual tras una hora, díselo a ADRI o a quien administra el servidor.

### Dar de alta el dominio en Plesk (hosting compartido)

Si Universal Web está instalado en un hosting con **Plesk**, todas las webs salen de **una sola instalación**, la de la suscripción del panel, en su carpeta `httpdocs/public`. Cada dominio nuevo se añade **dentro de esa misma suscripción**. No crees un cliente ni una suscripción nueva: otro usuario de Plesk no puede usar la carpeta de esta instalación.

1. Entra en Plesk como **administrador** (un cliente normal puede no tener permiso) y abre **Suscripciones →** la suscripción donde está el panel de Universal Web.
2. Pulsa **Añadir dominio** (no «Añadir alias de dominio»: con un alias no se pueden crear buzones de correo propios) y elige **Sitio web en blanco**.
3. En **Nombre de dominio registrado** escribe el dominio sin `www`. Deja marcados **Activar el servicio DNS** y **Activar el servicio de correo**.
4. Abre **Configuración de hosting** y en **Raíz del documento** borra lo que propone Plesk y escribe `httpdocs/public`. Es el paso clave: si lo dejas como estaba, se verá la página por defecto de Plesk. En **Dominio preferido** elige el que va sin `www`. Pulsa **Añadir dominio**.
5. Si el dominio usa los servidores de nombres del propio hosting, Plesk crea la zona DNS él solo y en unos minutos el dominio ya apunta al servidor. Si no, crea los registros de la tabla de arriba en tu registrador.
6. **Candado HTTPS:** entra en el dominio → **SSL/TLS**. Arriba salen certificados de pago: no hace falta comprar nada. Baja hasta **Instalar un certificado básico gratuito de Let's Encrypt → Instalar**, marca el dominio, **www**, **webmail**, **correo** y **Asigne el certificado al dominio de correo**, y pulsa **Obténgalo gratis**. Se renueva solo.
7. En el panel de Universal Web: elige la web → **Ajustes → Dominios**, escribe el dominio y su versión con `www` (uno por línea, el principal primero) y pulsa **Guardar ajustes**. Hasta este paso el servidor recibe el dominio pero no sabe de qué web es, y enseña una página neutra: «Esta web todavía no está disponible» (nunca otra de tus webs). Con ADRI basta decirle «añade el dominio X a la web Y».
8. Abre `https://tu-dominio.com`: tiene que salir tu web con el candado. `www` y `http://` llevan solos a la dirección principal.

Los **buzones de correo** del dominio se crean en Plesk, en el dominio → **Correo → Crear dirección de correo**.


## 11. Copias de seguridad

**Administración → Copias de seguridad**. Una copia es un archivo que guarda el estado de tu web para poder volver atrás.

### Qué incluye una copia de web

Contenido (páginas y entradas), medios, mensajes, formularios y código personalizado. **Nunca** incluye
contraseñas ni tokens. Se guardan en el servidor, en una carpeta que no es pública.

### Copias locales

- **Crear copia ahora**: puedes ponerle una **Contraseña** opcional para cifrarla. **Si pierdes esa contraseña no hay forma de recuperar la copia.**
- Cada copia de la lista tiene **Descargar** (bájate una copia a tu ordenador, ¡buena costumbre!) y **Eliminar**.
- **Antes de cambios grandes** (cambiar de tema, tocar CSS, un cambio masivo de una IA) crea una copia.

### Restaurar

Hay dos formas, las dos dentro de la tarjeta de tu web:

1. En la fila de la copia, despliega **Restaurar esta copia en «tu-web»…**
2. O sube un archivo en **Restaurar desde un archivo** (`.tar.gz`, o `.enc` si está cifrada), con su contraseña si la tiene.

En ambos casos escribe el identificador de tu web para confirmar. **Sustituye todo el contenido de la web** por el de la copia; antes se
guarda automáticamente otra copia del estado actual, así que puedes arrepentirte.

### Copias fuera del servidor (la importante)

Si el servidor se rompe, las copias que están dentro de él se pierden con él. Por eso existe la tarjeta
**Copia fuera del servidor**: cada copia se envía también a otro sitio. Eliges el **Destino**:

| Destino | Qué es |
|---|---|
| **SFTP / SSH** | Otro servidor, un NAS… |
| **FTP / FTPS** | El FTP de un hosting (usa FTPS, cifrado, si puedes). |
| **S3 y compatibles** | Amazon, Cloudflare R2, Backblaze B2, Wasabi, MinIO. |
| **Carpeta de este servidor** | Un disco externo o unidad montada (solo superadmin). |

Más opciones: **Copia programada** (**Diaria** o **Semanal**), **Conservar** (cuántas copias guardar en el destino) y **Cifrar antes de enviar**
(el destino nunca ve el contenido; guarda la contraseña en un lugar seguro, sin ella no se pueden restaurar). Pulsa **Guardar y probar conexión** para comprobar que todo funciona, y
**Enviar ahora la última copia** para un envío inmediato. Los datos de acceso se guardan cifrados.

### Solo para superadmin

**Copias globales** (toda la instalación), **Copias automáticas** (una global al día, sin cifrar, con cuántas conservar) e **Importar una web como nueva**
desde una copia (para mover o clonar webs entre servidores). Las copias globales incluyen la configuración del servidor: trátalas como confidenciales.
**Una copia global no se restaura desde el panel**: se hace desde el servidor (mira el [anexo](#anexo-comandos-de-servidor-opcional)).

---

## 12. Usuarios, permisos y seguridad del panel

### Roles

| Rol | Qué puede hacer |
|---|---|
| **Editor** | Contenido del día a día: **Panel, Páginas, Entradas, Medios, Mensajes, SEO** (ver), **Errores** (ver y revisar), **Papelera** y **Tokens de IA** (los suyos). Puede publicar. No ve Administración. |
| **Administrador** | Todo lo de editor y todo **Administración** de su web: Ajustes, Temas (aplicar y descargar), Plantilla HTML, Diseño y código, Idiomas, Cookies, Formularios, Publicación (publicar ahora y descargar la web estática), Copias de seguridad, Usuarios, Actividad, redirecciones y vaciar el registro de errores. |
| **Superadmin** | Todo, en todas las webs, más **Plataforma**: **Webs**, **Seguridad y red**, **Correo del panel**; además subir/borrar temas, cambiar el modo de **Publicación**, copias globales e importar webs. |

### Gestionar usuarios (**Administración → Usuarios**)

- La tabla muestra nombre, email, **Rol** (se cambia con el desplegable), **2 pasos**, último acceso y **Quitar** (le retira el acceso a esta web).
- **Añadir usuario a esta web**: **Email**, **Nombre**, **Contraseña** (mínimo 8 caracteres; no hace falta si el email ya existe en la instalación) y **Rol** (**Editor** o **Administrador**).
- **Crear otro superadmin** (solo un superadmin): en el mismo formulario elige el rol **Superadmin (todas las webs)** y escribe **Tu contraseña**. Si el email ya tiene cuenta, esa cuenta pasa a ser superadmin y **conserva su contraseña** (la que escribas no se usa; cámbiala con **Editar**). **Quitar superadmin** se lo retira; nadie puede quitárselo a sí mismo.
- **Editar** (solo superadmin, en usuarios y superadmins): cambia nombre, email y, si hace falta, pone una **Contraseña nueva**. Para cambiar el email o la contraseña hay que escribir **Tu contraseña**. Un administrador de una web no puede editar a otros, porque una misma cuenta puede entrar en varias webs.
- Los superadmins aparecen aparte y solo otro superadmin ve su email.
- Si alguien pierde el móvil: el superadmin pulsa **Resetear 2 pasos** en su fila.
- **Actividad** (solo administradores) es el registro de quién hizo qué y cuándo, incluidas las acciones de las IAs (con un icono de robot y «MCP»).

### Seguridad: lo que ya hace el panel solo

Sesión que se cierra por inactividad, límite de intentos de acceso, protección contra formularios falsos, y un panel aislado
de la web pública para que ni siquiera un JavaScript personalizado pueda usar tu sesión.

### Seguridad: lo que decide el superadmin (**Plataforma → Seguridad y red**)

En lenguaje llano:

| Opción | Para qué sirve |
|---|---|
| **IPs o redes permitidas** | Una «lista de invitados»: solo esas direcciones de internet pueden entrar al panel y al canal de IA. La web pública sigue abierta a todos. El panel no te deja guardar una lista que te deje fuera a ti. |
| **Dominio exclusivo para el panel** | Que el panel solo exista en un dominio concreto (`panel.tudominio.com`). Recomendado cuando hay varias webs. |
| **Destinos privados permitidos** | A qué servidores internos pueden conectar los webhooks y el correo de las webs. Por defecto, solo internet público. |
| **Proxies de confianza** | Para instalaciones con un servidor intermedio delante. Solo servidores que controlas. |
| **Verificación en dos pasos → Obligar a todos** | Los usuarios que aún no la tengan deberán activarla antes de usar el panel. **Muy recomendado.** |
| **Sesión** | Minutos de inactividad antes de cerrar la sesión (de 15 a 10080). |

Cada persona, en **Mi cuenta**, puede activar su **Verificación en dos pasos**, cambiar el **Idioma del panel** y su **contraseña**
En **Mis datos** cada persona cambia su **Nombre** y su **Email** (con el que entra al panel). Para cambiar el email pide su contraseña actual, y los enlaces de recuperación pendientes dejan de valer.
(mínimo 8 caracteres).

---

## 13. Tu web con ADRI (la IA de BionicAdri)

**ADRI** es la IA de BionicAdri, que se clona hasta 20 veces cuando el encargo es grande. Universal Web tiene una puerta de entrada pensada para
asistentes como ADRI (o Claude): el **MCP** (un estándar para que una IA use herramientas de otro programa).
Con ella, ADRI puede **crear páginas, escribir entradas, revisar el SEO, traducir, subir imágenes y preparar webs enteras**,
con los permisos que tú le des y dejando todo registrado.

### Paso 1. Crea un token de acceso (**Contenido → Tokens de IA**)

Un **token** es una «llave» (una clave larga que empieza por `uw_`) que identifica a la IA y limita lo que puede hacer.
También puedes llegar desde el botón **Crear token de acceso** del **Panel**.

1. En la tarjeta **Crear token**, escribe un **Nombre** (por ejemplo «ADRI de Marta»).
2. Elige los **Permisos**:

| Permiso | Qué permite | Cuándo marcarlo |
|---|---|---|
| **read** — Leer | Consultar la web, el SEO, los errores… | Siempre. |
| **write** — Crear y editar borradores | Crear y editar páginas y entradas **en borrador**. | Para que te prepare contenido que tú revisas. |
| **publish** — Publicar y editar contenido publicado | Publicar y tocar lo que ya está en vivo; cambiar ajustes (si eres administrador). | Cuando confíes en ella para publicar. |
| **messages** — Leer y gestionar los mensajes de contacto (datos personales) | Leer los mensajes de tu formulario. | Solo si quieres que te los resuma o clasifique: son datos personales. |
| **code** — Editar CSS, JavaScript y código del `<head>` (exige ser admin) | Tocar el diseño avanzado y la plantilla HTML. | Solo si necesitas que cambie el diseño. |
| **backup** — Crear y listar copias de seguridad (exige ser admin) | Hacer copias y ver cuáles hay. | Recomendado: que haga una copia antes de cambios grandes. |

   Una combinación prudente para empezar: **read + write** (te prepara borradores y tú publicas). Una de confianza: **read + write + publish**.
3. Si eres superadmin verás **Alcance**: **Solo esta web** o **Global** (todas las webs; hace falta para crear webs nuevas).
4. Pulsa **Crear token**.

**Cópialo ahora: solo se muestra una vez.** Si lo pierdes, revócalo y crea otro. El panel te enseña también la
**dirección MCP** (`https://tu-dominio.com/mcp`) y, para quien use Claude Code, el comando listo para copiar.

Importante:

- El token **actúa con tus permisos**: nunca puede hacer más que tú en el panel. Si pierdes el acceso a la web,
  tus tokens dejan de funcionar al instante.
- En la lista **Tus tokens / Tokens de esta web** ves quién lo creó, cuándo lo usó por última vez y su estado. **Revocar** lo mata
  de inmediato. Revoca cualquier token que sospeches que se ha filtrado.
- Si instalaste con el asistente, el último paso (**Conecta ADRI**) pudo crearte uno con **read, write y publish**.

### Paso 2. Conéctalo en BionicAdri

1. En BionicAdri ve a **MCP → Universal Web** y pega la **dirección MCP** y el **token**.
2. ¿Todavía no tienes acceso? Escribe a **info@bionicadri.com**.
3. Con otro asistente (Claude Code u otro cliente MCP), usa la dirección y el token con la cabecera `Authorization: Bearer uw_…`. El panel te da el comando exacto al crear el token.

### Qué pedirle: ejemplos listos para copiar

- «**Crea la página de servicios** con una portada, tres servicios (reformas, mantenimiento y urgencias) y una llamada a la acción para pedir presupuesto. Déjala en borrador.»
- «**Sube la nota SEO por encima de 90.** Rellena lo que falte y dime qué has cambiado.» *(Rellena sola textos alternativos y descripciones vacías y redacta el resto.)*
- «**Revisa cómo se ve en móvil y en ordenador** la portada y la página de contacto, y arregla lo que veas mal.» *(ADRI la abre en un navegador real, en móvil y en ordenador, y un modelo con visión le dice qué se ve mal.)*
- «**Tradúcela al inglés.**» *(Mantiene el mismo slug; antes hay que tener el inglés activado en **Idiomas**.)*
- «**Traduce toda la web al inglés** y dime qué páginas faltan.»
- «**Prepárame una web nueva en vista previa para el cliente Talleres Ruiz**: tema industrial, páginas de inicio, servicios y contacto, y dame el enlace para enseñársela.» *(Necesita un token **global** de superadmin con permiso publish, y que le indiques el dominio previsto del cliente.)*
- «**Cambia el tema** por uno adecuado para una clínica de fisioterapia y ajusta el color a nuestro azul.»
- «**Añade el horario** (lunes a viernes de 9 a 14 y de 17 a 20) y la carta de precios a la página de contacto.»
- «**Escribe una entrada de blog** sobre cómo elegir una caldera, para publicar el próximo lunes, con imagen de portada y SEO.»
- «**Revisa la web** y dime si hay enlaces rotos o errores.»
- «**Haz una copia de seguridad** antes de tocar nada.» *(Con el permiso **backup**.)*
- «**Deshaz el último cambio** de la página de inicio.»

### Qué hace ADRI por sí sola

- **Guarda versiones**: cada cambio conserva la versión anterior (30 por página, por entrada y por diseño), así que se puede deshacer.
- **Papelera**: lo que borre va a la papelera 30 días.
- **Se autocorrige**: tras guardar una página, el sistema le devuelve los fallos de esa página (enlaces rotos, imágenes que faltan, SEO) para que los arregle.
- **Deja rastro**: todas sus acciones aparecen en **Actividad** con el nombre del token.
- **Avisa a buscadores** al publicar (IndexNow, que alimenta a Bing y, con él, a ChatGPT y Copilot). Se puede desactivar pidiéndoselo a un administrador con acceso por MCP.
- **Te explica el panel**: tiene este mismo manual. Pregúntale «¿cómo cambio el menú?» y te lo explica paso a paso con los nombres reales de los botones.
- **Te guía con el dominio**: te dice qué poner en el DNS y comprueba ella misma cuándo tu dominio y su candado HTTPS ya funcionan.
- **No inventa datos** (teléfonos, direcciones, cifras): sus instrucciones le dicen que deje el campo vacío y te avise.
- Los **cambios de CSS, JavaScript o plantilla** que recortarían casi todo lo que había se frenan y se le pide confirmación.

### Límites: lo que ADRI NO puede hacer por MCP

- **No toca el servidor ni el DNS**: no apunta dominios ni pone certificados. Eso lo hace una persona.
- **No ejecuta código en el servidor**: la plantilla HTML admite solo marcadores, sin PHP ni `<script>`. El JavaScript solo va en **Diseño y código** y solo con el permiso **code**.
- **No restaura copias de seguridad**: eso solo se hace desde el panel (o desde el servidor).
- **No gestiona usuarios, tokens, seguridad, correo ni formularios** (la configuración de correo y webhooks queda fuera de su alcance), ni activa el **modo privado**.
- **No cambia los dominios** de una web ya creada ni sube temas nuevos.
- Con un token de **editor**, no puede cambiar ajustes de la web, diseño ni copias: esas herramientas exigen que el dueño del token sea administrador.

---

## 14. Preguntas frecuentes y «si algo va mal»

**1. He guardado una página y no se ve en la web.**
Comprueba que el **Estado** es **Publicada**. Si la web se publica como estática en otro servidor, mira **Administración → Publicación**
y pulsa **Publicar ahora**. Prueba también a recargar sin caché (Ctrl/Cmd+Shift+R).

**2. ¿Cómo hago que una página sea la portada?**
Ponle el slug `index` y estado **Publicada**.

**3. ¿Puedo ver una página sin publicarla?**
Sí: déjala en **Borrador** y usa **Ver** desde la lista de páginas (con la sesión del panel abierta).

**4. He borrado una página (o una imagen) sin querer.**
Las páginas y entradas están 30 días en **Papelera → Recuperar**. Una imagen borrada de **Medios** no se recupera desde ahí: restaura una [copia de seguridad](#11-copias-de-seguridad) o vuelve a subirla.

**5. He estropeado el diseño con CSS o con la plantilla HTML.**
**Diseño y código → Historial → Restaurar**, o **Plantilla HTML → Historial → Restaurar** (o desmarca **Usar esta plantilla en la web pública**). Si no se arregla, vuelve a **Aplicar** el tema anterior.

**6. ¿Por qué no me deja guardar el CSS, la plantilla o la lista de IPs?**
Los mensajes explican la razón (por ejemplo, la plantilla no admite `<script>`, y la lista de IPs permitidas no puede dejar fuera tu IP actual).

**7. No llegan los avisos de los formularios.**
Los mensajes **siempre** están en **Mensajes**. Para el email, revisa **Formularios**: destinatarios y envío por SMTP, y pulsa **Enviar correo de prueba**: te dirá el error exacto. Mira también la carpeta de spam.

**8. La auditoría SEO sigue baja.**
Casi siempre son descripciones vacías o imágenes sin texto alternativo. Recorre la tabla del [apartado 9](#9-seo-y-geo-las-auditorías-y-sus-avisos) de arriba abajo.

**9. ¿Puedo tener más de una web?**
Sí. Un superadmin las crea en **Plataforma → Webs**. Cada una tiene su contenido, tema, usuarios y tokens.

**10. He perdido el móvil de la verificación en dos pasos.**
Entra con un **código de recuperación**. Si también los perdiste, un superadmin pulsa **Resetear 2 pasos** en **Usuarios**.

**11. ¿Mi web cumple la normativa de cookies?**
**Administración → Cookies** ayuda: aviso con **Aceptar todas**, **Rechazar** y **Configurar**; Google Analytics 4, Tag Manager,
Microsoft Clarity y Meta Pixel (pega solo el identificador) no se cargan hasta que el visitante acepta, y la política `/cookies` se genera sola. Revisa el texto
con tu asesor legal: el programa te da las herramientas, no una garantía jurídica.

**12. ¿Puedo instalar mi propia analítica?**
Sí, en **Diseño y código → `<head>`**. Para que respete el consentimiento, añade el script como
`<script type="text/plain" data-consent="analytics">…</script>` (o `marketing`).

### Si algo va mal: dónde mirar

1. **Contenido → Errores** es la «consola» de la web: errores de JavaScript, archivos que no cargan y avisos del servidor, agrupados con un contador de veces.
   Pulsa **Revisar la web ahora** para que repase todas las páginas (enlaces rotos, recursos que faltan, títulos, textos alternativos…) y te diga **cómo arreglarlo**.
   Los administradores pueden **Vaciar registro** una vez arreglado.
2. **Administración → Actividad** (administradores) dice quién cambió qué y cuándo, incluidas las IAs.
3. **Deshacer** según el caso: Historial de la página · Papelera · Historial de Diseño y código · Historial de la Plantilla HTML · reaplicar el tema anterior · restaurar una copia.
4. ¿Sigue sin funcionar? Reúne la dirección exacta, qué esperabas y qué ha pasado, y avisa a quien administra tu servidor. Para dudas del producto: **info@bionicadri.com** · [www.bionicadri.com](https://www.bionicadri.com).

---


### ¿Quién me da soporte? ¿Quién responde si algo falla?

Universal Web se ofrece **gratis, «tal cual», sin garantía y sin soporte técnico** de BionicAdri (lo dice su licencia, el archivo `LICENSE`). Puedes usarlo libremente para tus propias webs, también las de tu negocio, pero **tú eres responsable** de tu servidor, de tus copias de seguridad y de lo que publicas. Por eso: activa la **copia fuera del servidor** (apartado 11), revisa lo que publica la IA y guarda bien tus contraseñas. Si necesitas ayuda, pregúntale a ADRI (tiene este manual) o a quien te instaló el servidor.

## Anexo: comandos de servidor (opcional)

Solo para quien administra el servidor. Se ejecutan en la carpeta de la instalación con `php bin/uw …`.

| Situación | Comando |
|---|---|
| Cambiar la contraseña de alguien | `php bin/uw user:password --email=… --password=…` |
| Quitar la verificación en dos pasos a alguien | `php bin/uw user:2fa-reset --email=…` |
| Te quedaste fuera por la lista de IPs permitidas | `php bin/uw security:reset` |
| Crear una copia de seguridad | `php bin/uw backup` |
| Restaurar una copia global | `php bin/uw backup:restore --file=NOMBRE --yes [--password=…] [--with-config]` |
| Restaurar una copia de una web | `php bin/uw backup:restore-site --site=slug --file=NOMBRE --yes` |
| Crear un token de IA | `php bin/uw token:create --site=<slug\|global> --name=… --scopes=read,write,publish` |
| Publicar webs estáticas pendientes | `php bin/uw publish --dirty` |

Guía técnica completa en el [README](../README.md); guía para desarrolladores de temas en [docs/THEMES.md](THEMES.md).
