<?php
// Aviso de cookies por web: banner (aceptar / rechazar / configurar), carga de analítica y marketing
// SOLO tras el consentimiento, enlace para cambiar de opinión y política de cookies generada sola.
// El consentimiento se guarda en una cookie técnica propia (uw_consent) durante 12 meses (y se vuelve a preguntar si cambian las herramientas).

declare(strict_types=1);

const COOKIE_TRACKERS = [
    // clave => [categoría, patrón del ID, ejemplo, nombre visible]
    'ga4' => ['analytics', '/^G-[A-Z0-9]{4,15}$/', 'G-ABC123XYZ', 'Google Analytics 4'],
    'gtm' => ['analytics', '/^GTM-[A-Z0-9]{4,10}$/', 'GTM-ABCD12', 'Google Tag Manager'],
    'clarity' => ['analytics', '/^[a-z0-9]{6,20}$/', 'abc123def4', 'Microsoft Clarity'],
    'meta_pixel' => ['marketing', '/^[0-9]{6,20}$/', '1234567890123', 'Meta Pixel'],
];

function cookies_defaults(): array
{
    return [
        'enabled' => false,     // muestra el banner
        'text' => '',           // texto propio del banner (vacío = texto estándar traducido)
        'policy_url' => '',     // enlace a tu política; vacío = /cookies generada automáticamente
        'position' => 'bottom', // bottom (barra) | box (caja en la esquina)
        'revision' => 1,        // súbelo para volver a pedir el consentimiento a todos
        'ga4' => '', 'gtm' => '', 'clarity' => '', 'meta_pixel' => '',
    ];
}

/** Valida un parche de la configuración de cookies y devuelve la configuración completa. */
function cookies_merge(array $cur, mixed $patch): array
{
    if (!is_array($patch)) throw new UwError('cookies debe ser un objeto {enabled, text, policy_url, position, ga4, gtm, clarity, meta_pixel}');
    $c = array_replace(cookies_defaults(), $cur);
    if (array_key_exists('enabled', $patch)) $c['enabled'] = filter_var($patch['enabled'], FILTER_VALIDATE_BOOLEAN);
    if (array_key_exists('text', $patch)) $c['text'] = mb_substr(trim((string) $patch['text']), 0, 1000);
    if (array_key_exists('position', $patch)) {
        if (!in_array($patch['position'], ['bottom', 'box'], true)) throw new UwError('cookies.position debe ser bottom o box');
        $c['position'] = $patch['position'];
    }
    if (array_key_exists('policy_url', $patch)) {
        $u = trim((string) $patch['policy_url']);
        if ($u !== '' && clean_url($u) === '') throw new UwError('cookies.policy_url no permitido: usa una ruta (/politica-de-cookies) o una URL https://');
        $c['policy_url'] = $u;
    }
    foreach (COOKIE_TRACKERS as $k => [, $re, $example, $name]) {
        if (!array_key_exists($k, $patch)) continue;
        $v = trim((string) $patch[$k]);
        if ($k === 'ga4' || $k === 'gtm') $v = strtoupper($v);
        if ($k === 'clarity') $v = strtolower($v);
        if ($v !== '' && !preg_match($re, $v)) throw new UwError("cookies.$k no válido para $name (ejemplo: $example)");
        $c[$k] = $v;
    }
    if (!empty($patch['reset_consent'])) $c['revision'] = (int) $c['revision'] + 1;
    // Si hay herramientas de seguimiento, el aviso es obligatorio: se activa solo.
    if (cookies_trackers($c)) $c['enabled'] = true;
    return $c;
}

/** Herramientas configuradas: ['ga4' => 'G-…', …] */
function cookies_trackers(array $c): array
{
    return array_filter(array_intersect_key($c, COOKIE_TRACKERS), fn($v) => (string) $v !== '');
}

function cookies_config(SiteService $svc): array
{
    return array_replace(cookies_defaults(), (array) ($svc->baseSettings()['cookies'] ?? []));
}

function cookies_policy_href(SiteService $svc, array $c): string
{
    return $c['policy_url'] !== '' ? clean_url($c['policy_url']) : $svc->langPath('/cookies');
}

/** Lo que va en el <head>: modo de consentimiento de Google denegado por defecto (antes de cualquier etiqueta). */
function cookies_head(SiteService $svc): string
{
    $c = cookies_config($svc);
    if (!$c['enabled'] || ($c['ga4'] === '' && $c['gtm'] === '')) return '';
    return "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments)}"
        . "gtag('consent','default',{ad_storage:'denied',ad_user_data:'denied',ad_personalization:'denied',analytics_storage:'denied',wait_for_update:500});gtag('set','ads_data_redaction',true);</script>\n";
}

/** Banner + panel de preferencias + cargador. Va al final del <body> en todos los temas. */
function cookies_banner(SiteService $svc): string
{
    $c = cookies_config($svc);
    if (!$c['enabled']) return '';
    $tr = $svc->baseSettings()['i18n'][$svc->lang]['cookies_text'] ?? '';
    $text = $svc->lang !== $svc->defaultLang() && $tr !== '' ? $tr : ($c['text'] !== '' && $svc->lang === $svc->defaultLang() ? $c['text']
        : __('Usamos cookies propias necesarias para que la web funcione y, si nos dejas, cookies de analítica y marketing para mejorarla. Puedes aceptarlas, rechazarlas o elegir.'));
    // «ver» cambia si se añade o quita una herramienta: quien ya eligió vuelve a ver el aviso.
    $ids = cookies_trackers($c);
    ksort($ids);
    $cfg = ['rev' => (int) $c['revision'], 'ver' => substr(md5(json_encode($ids)), 0, 8), 'ids' => $ids, 'days' => 365];
    $hasMk = $c['meta_pixel'] !== '';
    $hasAn = $c['ga4'] !== '' || $c['gtm'] !== '' || $c['clarity'] !== '';
    $cls = $c['position'] === 'box' ? ' uwc-box' : '';
    ob_start(); ?>
<div id="uwc" class="uwc<?= $cls ?>" role="dialog" aria-labelledby="uwc-h" aria-describedby="uwc-d" tabindex="-1" hidden>
  <div class="uwc-in">
    <h2 id="uwc-h" class="uwc-sr"><?= e(__('Aviso de cookies')) ?></h2>
    <p class="uwc-t" id="uwc-d"><?= e($text) ?> <a href="<?= e(cookies_policy_href($svc, $c)) ?>"><?= e(__('Más información')) ?></a></p>
    <div class="uwc-prefs" hidden>
      <label><input type="checkbox" checked disabled> <span><b><?= e(__('Necesarias')) ?></b> — <?= e(__('imprescindibles para que la web funcione (siempre activas)')) ?></span></label>
      <label><input type="checkbox" data-cat="analytics"> <span><b><?= e(__('Analítica')) ?></b> — <?= e(__('nos ayudan a saber qué páginas se visitan')) ?><?= $hasAn ? '' : ' · ' . e(__('no se usan ahora')) ?></span></label>
      <label><input type="checkbox" data-cat="marketing"> <span><b><?= e(__('Marketing')) ?></b> — <?= e(__('miden campañas y muestran anuncios relevantes')) ?><?= $hasMk ? '' : ' · ' . e(__('no se usan ahora')) ?></span></label>
    </div>
    <div class="uwc-b">
      <button type="button" data-uwc="accept" class="uwc-main"><?= e(__('Aceptar todas')) ?></button>
      <button type="button" data-uwc="reject" class="uwc-main"><?= e(__('Rechazar')) ?></button>
      <button type="button" data-uwc="config" class="uwc-alt"><?= e(__('Configurar')) ?></button>
      <button type="button" data-uwc="save" class="uwc-alt" hidden><?= e(__('Guardar selección')) ?></button>
    </div>
  </div>
</div>
<button type="button" id="uwc-re" class="uwc-re" aria-label="<?= e(__('Configurar cookies')) ?>" title="<?= e(__('Configurar cookies')) ?>" hidden><svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3a9 9 0 1 0 9 9 3 3 0 0 1-3-3 3 3 0 0 1-3-3 3 3 0 0 1-3-3z"/><circle cx="8.5" cy="11" r="1"/><circle cx="12" cy="16" r="1"/><circle cx="15.5" cy="13" r="1"/></svg></button>
<style>
/* Hereda colores, fuente y radio del tema (--card, --text, --border, --radius, --font, --accent); con valores de reserva si no existen. */
.uwc{position:fixed;z-index:9990;left:0;right:0;bottom:0;padding:8px;font:15px/1.45 var(--font,system-ui,-apple-system,"Segoe UI",sans-serif);color:var(--text,#1f2937)}
.uwc:focus{outline:none}
.uwc-in{max-width:1080px;margin:0 auto;box-sizing:border-box;max-height:40vh;overflow-y:auto;background:var(--card,#fff);color:var(--text,#1f2937);border:1px solid var(--border,#e5e7eb);border-radius:var(--radius,14px);box-shadow:0 10px 40px rgba(0,0,0,.2);padding:14px 16px;display:flex;flex-wrap:wrap;gap:10px 20px;align-items:center}
.uwc-box{left:auto;right:12px;bottom:12px;max-width:400px;padding:0}.uwc-box .uwc-in{display:block}.uwc-box .uwc-b{margin-top:10px}
.uwc-sr{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap;margin:0;padding:0;border:0}
.uwc-t{margin:0;flex:1 1 420px;font-size:14px}.uwc-t a{color:inherit;text-decoration:underline;text-underline-offset:2px}
.uwc-prefs{flex:1 1 100%;display:grid;gap:4px;font-size:14px}.uwc-prefs label{display:flex;gap:10px;align-items:flex-start;min-height:40px;padding:4px 0;cursor:pointer}
.uwc-prefs input{accent-color:var(--accent,#4f46e5);flex:none;width:20px;height:20px;margin:1px 0 0}
.uwc-b{display:flex;flex-wrap:wrap;gap:8px;position:sticky;bottom:-14px;background:var(--card,#fff);padding:6px 0 2px}
.uwc-b button{font:inherit;font-size:14px;font-weight:600;min-height:44px;padding:8px 18px;border-radius:var(--radius,14px);border:2px solid var(--text,#1f2937);cursor:pointer;line-height:1.2}
.uwc-b button[data-uwc="accept"],.uwc-b button[data-uwc="reject"]{background:var(--text,#1f2937);color:var(--card,#fff)}
.uwc-b .uwc-alt{background:transparent;color:var(--text,#1f2937)}
.uwc-b button:hover{opacity:.88}
.uwc-b button:focus-visible,.uwc-re:focus-visible,.uwc-t a:focus-visible,.uwc-prefs input:focus-visible{outline:3px solid var(--accent,#4f46e5);outline-offset:2px}
.uwc-re{position:fixed;z-index:9989;left:12px;bottom:12px;width:44px;height:44px;border-radius:50%;border:1px solid var(--border,#e5e7eb);background:var(--card,#fff);color:var(--text,#374151);box-shadow:0 4px 14px rgba(0,0,0,.18);cursor:pointer;display:grid;place-items:center;padding:0}
.uwc-re[hidden],.uwc[hidden],.uwc [hidden]{display:none}
@media (max-width:640px){.uwc-box{left:0;right:0;max-width:none;padding:8px}.uwc-in{padding:12px;gap:8px}
 .uwc-b{display:grid;grid-template-columns:1fr 1fr;width:100%}.uwc-b .uwc-alt{grid-column:1/-1}.uwc-b button{padding:8px 10px}.uwc-t{flex-basis:100%}}
@media (prefers-reduced-motion:no-preference){.uwc-b button{transition:opacity .15s}}
@media print{.uwc,.uwc-re{display:none}}
</style>
<script>
(function () {
  var C = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var box = document.getElementById('uwc'), re = document.getElementById('uwc-re'), loaded = {};
  // Consentimiento guardado: {rev, ver, at, analytics, marketing}. Vale si coincide la versión de ajustes y no pasó un año.
  function read() {
    var m = document.cookie.match(/(?:^|; )uw_consent=([^;]*)/);
    if (!m) return null;
    try {
      var v = JSON.parse(decodeURIComponent(m[1]));
      if (v.rev !== C.rev || v.ver !== C.ver) return null;
      var t = Date.parse(v.at);
      if (!t || Date.now() - t > C.days * 864e5) return null;
      return v;
    } catch (e) { return null; }
  }
  function write(v) {
    v.rev = C.rev; v.ver = C.ver; v.at = new Date().toISOString().slice(0, 10);
    document.cookie = 'uw_consent=' + encodeURIComponent(JSON.stringify(v)) + ';path=/;max-age=' + C.days * 86400 + ';SameSite=Lax' + (location.protocol === 'https:' ? ';Secure' : '');
  }
  function js(src, attrs) { var s = document.createElement('script'); s.async = true; s.src = src; for (var k in attrs || {}) s.setAttribute(k, attrs[k]); document.head.appendChild(s); }
  function apply(v) {
    var ids = C.ids;
    window.dataLayer = window.dataLayer || [];
    if (!window.gtag) window.gtag = function () { dataLayer.push(arguments); };
    if (ids.ga4 || ids.gtm) gtag('consent', 'update', {
      analytics_storage: v.analytics ? 'granted' : 'denied', ad_storage: v.marketing ? 'granted' : 'denied',
      ad_user_data: v.marketing ? 'granted' : 'denied', ad_personalization: v.marketing ? 'granted' : 'denied' });
    if (v.analytics && !loaded.an) {
      loaded.an = 1;
      if (ids.ga4) { js('https://www.googletagmanager.com/gtag/js?id=' + ids.ga4); gtag('js', new Date()); gtag('config', ids.ga4); }
      if (ids.gtm) { dataLayer.push({ 'gtm.start': Date.now(), event: 'gtm.js' }); js('https://www.googletagmanager.com/gtm.js?id=' + ids.gtm); }
      if (ids.clarity) { window.clarity = window.clarity || function () { (window.clarity.q = window.clarity.q || []).push(arguments); }; js('https://www.clarity.ms/tag/' + ids.clarity); }
    }
    if (v.marketing && !loaded.mk && ids.meta_pixel) {
      loaded.mk = 1;
      var f = window.fbq = function () { f.callMethod ? f.callMethod.apply(f, arguments) : f.queue.push(arguments); };
      if (!window._fbq) window._fbq = f; f.push = f; f.loaded = true; f.version = '2.0'; f.queue = [];
      js('https://connect.facebook.net/en_US/fbevents.js'); fbq('init', ids.meta_pixel); fbq('track', 'PageView');
    }
    // Scripts propios marcados con type="text/plain" y data-consent="analytics" o "marketing"
    document.querySelectorAll('script[type="text/plain"][data-consent]').forEach(function (old) {
      if (!v[old.getAttribute('data-consent')]) return;
      var s = document.createElement('script');
      for (var i = 0; i < old.attributes.length; i++) { var a = old.attributes[i]; if (a.name !== 'type' && a.name !== 'data-consent') s.setAttribute(a.name, a.value); }
      s.text = old.text; old.parentNode.replaceChild(s, old);
    });
    try { document.dispatchEvent(new CustomEvent('uw:consent', { detail: v })); } catch (e) {}
  }
  function prefs(on) {
    box.querySelector('.uwc-prefs').hidden = !on;
    box.querySelector('[data-uwc="save"]').hidden = !on;
    box.querySelector('[data-uwc="config"]').hidden = on;
    if (on) { var f = box.querySelector('[data-cat]'); if (f) f.focus(); }
  }
  function open(cfg) {
    var v = read() || {};
    box.querySelectorAll('[data-cat]').forEach(function (i) { i.checked = !!v[i.getAttribute('data-cat')]; });
    box.hidden = false; re.hidden = true;
    prefs(!!cfg);
  }
  // Retirar el consentimiento borra también las cookies que ya hubieran puesto esas herramientas.
  function wipe(v) {
    var host = location.hostname.replace(/^www\./, '');
    document.cookie.split('; ').forEach(function (c) {
      var n = c.split('=')[0];
      if ((!v.analytics && /^(_ga|_gid|_gcl|_clck|_clsk|CLID)/.test(n)) || (!v.marketing && /^(_fbp|_fbc)$/.test(n))) {
        document.cookie = n + '=;path=/;max-age=0'; document.cookie = n + '=;path=/;max-age=0;domain=.' + host;
      }
    });
  }
  function done(v) { write(v); wipe(v); apply(v); box.hidden = true; re.hidden = false; re.focus(); }
  box.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-uwc]'); if (!b) return;
    var a = b.getAttribute('data-uwc');
    if (a === 'accept') done({ analytics: true, marketing: true });
    else if (a === 'reject') done({ analytics: false, marketing: false });
    else if (a === 'config') prefs(true);
    else if (a === 'save') { var v = {}; box.querySelectorAll('[data-cat]').forEach(function (i) { v[i.getAttribute('data-cat')] = i.checked; }); done(v); }
  });
  // Escape cierra las preferencias: con elección guardada oculta el panel; sin ella vuelve a la primera capa.
  box.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Escape' || box.querySelector('.uwc-prefs').hidden) return;
    if (read()) { box.hidden = true; re.hidden = false; re.focus(); } else { prefs(false); box.querySelector('[data-uwc="config"]').focus(); }
  });
  re.addEventListener('click', function () { open(true); });
  // Cualquier enlace a #cookies (menú, pie, política) abre las preferencias.
  document.addEventListener('click', function (ev) { var a = ev.target.closest('a[href$="#cookies"]'); if (a) { ev.preventDefault(); open(true); } });
  window.uwCookies = { open: function () { open(true); }, get: read };
  var v = read();
  if (v) { apply(v); re.hidden = false; } else open(false);
})();
</script>
<?php
    return (string) ob_get_clean();
}

/** Página /cookies generada a partir de la configuración real de la web. */
function cookies_policy_page(SiteService $svc): array
{
    $c = cookies_config($svc);
    $site = $svc->site['name'];
    $rows = ['- **uw_consent** (' . $site . ') — ' . __('Recuerda tu elección sobre las cookies') . ' · ' . __('12 meses') . ' · ' . __('necesaria')];
    $known = [
        'ga4' => [['_ga, _ga_' . substr((string) $c['ga4'], 2), 'Google', __('Analítica: distingue visitantes y sesiones'), __('2 años')]],
        'gtm' => [['_gcl_au', 'Google', __('Gestor de etiquetas; puede cargar otras herramientas de medición'), __('3 meses')]],
        'clarity' => [['_clck, _clsk, CLID', 'Microsoft', __('Analítica: mapas de calor y grabación anónima de sesiones'), __('1 año')]],
        'meta_pixel' => [['_fbp, fr', 'Meta', __('Marketing: mide campañas y muestra anuncios'), __('3 meses')]],
    ];
    foreach (cookies_trackers($c) as $k => $_) foreach ($known[$k] as [$name, $who, $why, $life]) $rows[] = "- **$name** ($who) — $why · $life";
    $contact = $svc->settings()['contact']['email'];
    $md = __('Esta página explica qué cookies usa **{site}**, para qué sirven y cómo puedes cambiar tu elección.', ['site' => $site]) . "\n\n"
        . '## ' . __('¿Qué son las cookies?') . "\n\n" . __('Pequeños archivos que la web guarda en tu navegador para recordar información entre visitas.') . "\n\n"
        . '## ' . __('Cookies que usamos') . "\n\n" . implode("\n", $rows) . "\n\n"
        . __('Las cookies de analítica y marketing solo se activan si das tu consentimiento. Las necesarias no requieren consentimiento.') . "\n\n"
        . '## ' . __('Cómo cambiar tu elección') . "\n\n" . __('Puedes cambiarla cuando quieras desde el botón redondo de la esquina inferior o [aquí](#cookies). También puedes borrar las cookies desde la configuración de tu navegador.') . "\n\n"
        . ($contact ? '## ' . __('Contacto') . "\n\n" . __('Para cualquier duda escribe a {email}.', ['email' => $contact]) . "\n\n" : '')
        . '_' . __('Última actualización: {date}', ['date' => date('Y-m-d')]) . '_';
    return ['id' => 0, 'slug' => 'cookies', 'lang' => $svc->lang, 'title' => __('Política de cookies'), 'status' => 'published',
        'blocks' => [['type' => 'text', 'data' => ['title' => __('Política de cookies'), 'body' => $md]]],
        'seo' => ['title' => '', 'description' => __('Qué cookies usa {site} y cómo cambiar tu elección.', ['site' => $site]), 'image' => '', 'noindex' => false],
        'updated_at' => '', 'created_at' => ''];
}
