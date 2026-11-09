<?php /** @var array $p @var string $ip @var string $remote @var bool $viaProxy @var bool $https @var ?array $old */
$allow = $old['admin_allowlist'] ?? implode("\n", $p['admin_allowlist']);
$proxies = $old['trusted_proxies'] ?? implode("\n", $p['trusted_proxies']);
$idle = (int) ($old['session_idle_minutes'] ?? $p['session_idle_minutes']);
$adminHost = $old['admin_host'] ?? $p['admin_host'];
$outbound = $old['outbound_allowlist'] ?? implode("\n", $p['outbound_allowlist']); ?>
<div class="page-head"><h1><?= te('Seguridad y red') ?></h1></div>
<form method="post" action="<?= ADM ?>/security" class="stack">
  <?= csrf_field() ?>
  <section class="card">
    <h2><?= te('Tu conexión actual') ?></h2>
    <div class="row wrap">
      <div class="grow"><small class="muted"><?= te('IP detectada') ?></small><br><strong class="mono"><?= e($ip) ?></strong></div>
      <div class="grow"><small class="muted">REMOTE_ADDR</small><br><span class="mono"><?= e($remote) ?></span></div>
      <div class="grow"><small class="muted"><?= te('Vía proxy de confianza') ?></small><br><?= $viaProxy ? adm_badge(t('Sí'), 'green') : adm_badge(t('No'), 'gray') ?></div>
      <div class="grow"><small class="muted">HTTPS</small><br><?= $https ? adm_badge(t('Detectado'), 'green') : adm_badge(t('No detectado'), 'red') ?></div>
    </div>
    <?php if (!$https): ?><p class="muted"><small><?= te('Sin HTTPS no se envía la cabecera HSTS ni la cookie de sesión «secure». Si estás detrás de un proxy con HTTPS, añádelo a «Proxies de confianza».') ?></small></p><?php endif; ?>
  </section>

  <section class="card">
    <h2><?= te('Acceso al panel y al MCP por IP') ?></h2>
    <label><?= te('IPs o redes permitidas (una por línea). Vacío = cualquiera puede intentar entrar.') ?>
      <textarea name="admin_allowlist" rows="4" class="mono" placeholder="10.147.17.0/24&#10;203.0.113.7"><?= e($allow) ?></textarea></label>
    <p class="muted"><small><?= th('Solo afecta a <span class="mono">/admin</span> y <span class="mono">/mcp</span>; la web pública sigue abierta. Si guardas una lista, tu IP actual (<span class="mono">{ip}</span>) debe estar incluida. Recuperación desde el servidor: <span class="mono">php bin/uw security:reset</span>.', ['ip' => $ip]) ?></small></p>
  </section>

  <section class="card">
    <h2><?= te('Dominio exclusivo para el panel') ?> <?= $adminHost ? adm_badge(t('Activo'), 'green') : adm_badge(t('Recomendado'), 'blue') ?></h2>
    <label><?= te('Dominio del panel y del MCP (vacío = responden en todos los dominios)') ?>
      <input type="text" name="admin_host" value="<?= e($adminHost) ?>" placeholder="panel.tudominio.com" class="mono"></label>
    <p class="muted"><small><?= th('Con un dominio propio, <span class="mono">/admin</span> y <span class="mono">/mcp</span> solo existen ahí: el CSS/JS personalizado de cualquier web queda en otro origen y no puede tocar tu sesión. Para activarlo, apunta el DNS de ese dominio a este servidor, entra en el panel <b>desde ese dominio</b> y guarda. Si te equivocas: <span class="mono">php bin/uw security:reset</span>.') ?></small></p>
  </section>

  <section class="card">
    <h2><?= te('Destinos privados permitidos') ?></h2>
    <label><span><?= te('Redes privadas a las que pueden conectar los webhooks y el SMTP de las webs (una por línea)') ?></span>
      <textarea name="outbound_allowlist" rows="2" class="mono" placeholder="127.0.0.1&#10;10.147.17.0/24"><?= e($outbound) ?></textarea></label>
    <p class="muted"><small><?= te('Por defecto solo se permiten destinos públicos de internet, para que el admin de una web no pueda usar el servidor para explorar tu red interna. Añade aquí, por ejemplo, tu servidor de correo local (127.0.0.1) o el CRM en tu red ZeroTier.') ?></small></p>
  </section>

  <section class="card">
    <h2><?= te('Proxies de confianza') ?></h2>
    <label><span><?= te('IPs de proxies cuyas cabeceras X-Forwarded-For y X-Forwarded-Proto se aceptan (una por línea)') ?></span>
      <textarea name="trusted_proxies" rows="3" class="mono" placeholder="10.147.17.1"><?= e($proxies) ?></textarea></label>
    <p class="muted"><small><?= te('Añade aquí solo servidores que controlas (un VPS frontal, Cloudflare Tunnel local…). Cualquier otra IP podría falsificar su dirección y saltarse la lista de arriba.') ?></small></p>
  </section>

  <section class="card">
    <h2><?= te('Verificación en dos pasos') ?></h2>
    <label class="check"><input type="checkbox" name="require_2fa" value="1" <?= !empty($old) ? (!empty($old['require_2fa']) ? 'checked' : '') : (!empty($p['require_2fa']) ? 'checked' : '') ?>>
      <span><?= th('Obligar a <b>todos</b> los usuarios del panel a usar una app de autenticación (Google Authenticator, Microsoft Authenticator, Authy, 1Password…)') ?></span></label>
    <p class="hint"><?= th('Quien aún no la tenga deberá activarla al entrar, antes de poder usar el panel. Muy recomendado. Si alguien pierde el móvil y sus códigos: Usuarios → «Resetear 2 pasos», o <code>php bin/uw user:2fa-reset --email=…</code>') ?></p>
  </section>

  <section class="card">
    <h2><?= te('Sesión') ?></h2>
    <label><?= te('Minutos de inactividad antes de cerrar la sesión') ?>
      <input type="number" name="session_idle_minutes" value="<?= $idle ?>" min="15" max="10080" style="width:7rem">
      <span class="hint"><?= te('entre 15 y 10080 (7 días)') ?></span></label>
  </section>
  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar seguridad') ?></button></div>
</form>

<section class="card">
  <h2><?= te('Protecciones activas') ?></h2>
  <ul>
    <li><?= th('El panel solo acepta navegación normal: el JavaScript de las webs públicas no puede leerlo con <span class="mono">fetch</span>, abrirlo en un iframe ni controlar una ventana abierta (Fetch Metadata, COOP, CSP, <span class="mono">X-Frame-Options</span>).') ?></li>
    <li><?= te('Caducidad de sesión por inactividad, rotación del identificador cada 30 min y vínculo al navegador.') ?></li>
    <li><?= te('Límite de intentos de acceso y tokens MCP con permisos por usuario.') ?></li>
  </ul>
</section>

<section class="card">
  <h2><?= te('Guía ZeroTier') ?></h2>
  <p><?= th('<strong>ZeroTier</strong> crea una red privada virtual entre tus equipos (gratis hasta 25 nodos). Creas una red en <a href="https://my.zerotier.com" target="_blank" rel="noopener">my.zerotier.com</a>, unes tu servidor y tu portátil con <span class="mono">zerotier-cli join &lt;ID de red&gt;</span> y los autorizas. Allí ves el rango de la red (rutas gestionadas), normalmente algo como <span class="mono">10.147.17.0/24</span> o <span class="mono">172.2x.0.0/16</span>.') ?></p>
  <h3><?= te('1. Panel y MCP solo por ZeroTier') ?></h3>
  <p><?= th('Pon el rango de tu red ZeroTier en «IPs o redes permitidas» (por ejemplo <span class="mono">10.147.17.0/24</span>) y entra al panel desde un equipo conectado a ZeroTier. La web pública sigue siendo pública. Si usas Caddy directamente en el servidor, el panel verá tu IP de ZeroTier (no necesitas proxies de confianza).') ?></p>
  <h3><?= te('2. Servidor sin IP pública detrás de un VPS') ?></h3>
  <p><?= th('Un VPS con IP pública ejecuta Caddy (<span class="mono">deploy/Caddyfile.edge.example</span>) y reenvía todo con <span class="mono">reverse_proxy</span> a la IP ZeroTier del servidor real. En el servidor real añade la IP ZeroTier del VPS a «Proxies de confianza»; así Universal Web verá la IP real del visitante y HTTPS. Combínalo con la lista de IPs permitidas para el panel.') ?></p>
  <p class="muted"><?= th('<small>Si te quedas fuera por error, ejecuta en el servidor: <span class="mono">php bin/uw security:reset</span>. Más detalles en <span class="mono">deploy/ZEROTIER.md</span>.</small>') ?></p>
</section>
