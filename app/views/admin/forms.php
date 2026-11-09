<?php /** @var array $f @var array $has @var bool $mailAvailable @var SiteService $svc @var array $user */
$chk = fn(string $k) => !empty($f[$k]) ? 'checked' : '';
$recipients = is_array($f['recipients']) ? implode("\n", $f['recipients']) : (string) $f['recipients']; ?>
<div class="page-head"><h1><?= te('Formularios') ?></h1></div>
<p class="muted"><?= th('Configuración del formulario de contacto de <b>{name}</b>. Cada web tiene la suya. Los mensajes se guardan <b>siempre</b> en <a href="{url}">Mensajes</a>; el correo es un aviso adicional.', ['name' => $svc->site['name'], 'url' => ADM . '/messages']) ?></p>

<form method="post" action="<?= ADM ?>/forms" class="stack" id="forms-form">
  <?= csrf_field() ?>

  <section class="card">
    <h2><?= te('Aviso por email') ?></h2>
    <label class="check"><input type="checkbox" name="notify_enabled" value="1" <?= $chk('notify_enabled') ?>> <?= te('Enviar un email cada vez que llegue un mensaje') ?></label>
    <div class="row">
      <label class="grow"><?= te('Destinatarios') ?> <span class="hint"><?= te('uno por línea') ?></span>
        <textarea name="recipients" rows="2" class="mono" placeholder="hola@miweb.com"><?= e($recipients) ?></textarea></label>
      <label class="grow"><?= te('Prefijo del asunto') ?> <span class="hint"><?= te('por defecto: [{name}]', ['name' => $svc->site['name']]) ?></span>
        <input type="text" name="subject_prefix" value="<?= e($f['subject_prefix']) ?>" maxlength="100"></label>
    </div>
    <p class="hint"><?= te('El aviso lleva el email del visitante como «Responder a»: contestas con un clic desde tu correo.') ?></p>
  </section>

  <section class="card">
    <h2><?= te('Envío') ?></h2>
    <div class="row">
      <label><?= te('Método') ?>
        <select name="transport" id="transport">
          <option value="smtp" <?= $f['transport'] === 'smtp' ? 'selected' : '' ?>><?= te('Servidor SMTP (recomendado)') ?></option>
          <option value="mail" <?= $f['transport'] === 'mail' ? 'selected' : '' ?> <?= $mailAvailable ? '' : 'disabled' ?>><?= te('mail() de PHP (hosting)') ?></option>
        </select></label>
      <label class="grow"><?= te('Email del remitente') ?><input type="email" name="from_email" value="<?= e($f['from_email']) ?>" placeholder="web@miweb.com"></label>
      <label class="grow"><?= te('Nombre del remitente') ?><input type="text" name="from_name" value="<?= e($f['from_name']) ?>" placeholder="<?= e($svc->site['name']) ?>"></label>
    </div>
    <div id="smtp-fields" class="stack">
      <div class="row">
        <label class="grow"><?= te('Servidor SMTP') ?><input type="text" name="smtp_host" value="<?= e($f['smtp_host']) ?>" placeholder="smtp.miproveedor.com" autocomplete="off"></label>
        <label><?= te('Puerto') ?><input type="number" name="smtp_port" id="smtp_port" value="<?= (int) $f['smtp_port'] ?>" min="1" max="65535" style="width:7rem"></label>
        <label><?= te('Cifrado') ?>
          <select name="smtp_encryption" id="smtp_encryption">
            <option value="tls" <?= $f['smtp_encryption'] === 'tls' ? 'selected' : '' ?>><?= te('STARTTLS (587)') ?></option>
            <option value="ssl" <?= $f['smtp_encryption'] === 'ssl' ? 'selected' : '' ?>><?= te('SSL/TLS (465)') ?></option>
            <option value="none" <?= $f['smtp_encryption'] === 'none' ? 'selected' : '' ?>><?= te('Ninguno (25)') ?></option>
          </select></label>
      </div>
      <div class="row">
        <label class="grow"><?= te('Usuario') ?><input type="text" name="smtp_username" value="<?= e($f['smtp_username']) ?>" autocomplete="off"></label>
        <div class="grow stack">
          <label><?= te('Contraseña') ?>
            <input type="password" name="smtp_password" value="" autocomplete="new-password" placeholder="<?= $has['smtp_password'] ? e(t('•••••••• guardada (escribe para cambiarla)')) : '' ?>"></label>
          <?php if ($has['smtp_password']): ?><label class="check hint"><input type="checkbox" name="clear_smtp_password" value="1"> <?= te('Borrar la contraseña guardada') ?></label><?php endif; ?>
        </div>
      </div>
      <label class="check"><input type="checkbox" name="smtp_verify_cert" value="1" <?= $chk('smtp_verify_cert') ?>> <?= te('Verificar el certificado del servidor') ?> <span class="hint"><?= te('(desmárcalo solo si tu servidor usa un certificado propio)') ?></span></label>
      <p class="hint"><?= te('La contraseña se guarda cifrada y nunca se vuelve a mostrar. La IA (MCP) no tiene acceso a esta configuración.') ?></p>
    </div>
  </section>

  <section class="card">
    <h2><?= te('Respuesta automática al visitante') ?></h2>
    <label class="check"><input type="checkbox" name="autoreply_enabled" value="1" <?= $chk('autoreply_enabled') ?>> <?= te('Enviar una confirmación a quien escribe') ?></label>
    <label><?= te('Asunto') ?><input type="text" name="autoreply_subject" value="<?= e($f['autoreply_subject']) ?>" maxlength="200"></label>
    <label><?= te('Texto') ?> <span class="hint"><?= te('variables: {nombre} {web}. Por seguridad, el texto del visitante nunca se incluye. Exige Turnstile activo.') ?></span>
      <textarea name="autoreply_body" rows="6"><?= e($f['autoreply_body']) ?></textarea></label>
    <p class="hint"><?= te('Recomendado solo con Turnstile activo: así nadie puede usar tu formulario para enviar correos a terceros.') ?></p>
  </section>

  <section class="card">
    <h2><?= te('Antispam: Cloudflare Turnstile') ?></h2>
    <p class="muted"><?= th('Verificación gratuita y casi invisible. Crea un widget en <a href="https://dash.cloudflare.com/?to=/:account/turnstile" target="_blank" rel="noopener">Cloudflare → Turnstile</a> con el dominio de esta web y pega aquí las dos claves. Siempre activos además: campo trampa, tiempo mínimo de envío y límite de mensajes por IP.') ?></p>
    <label class="check"><input type="checkbox" name="turnstile_enabled" value="1" <?= $chk('turnstile_enabled') ?>> <?= te('Activar Turnstile en el formulario') ?></label>
    <div class="row">
      <label class="grow"><?= te('Clave del sitio (site key)') ?><input type="text" name="turnstile_site_key" value="<?= e($f['turnstile_site_key']) ?>" class="mono" autocomplete="off"></label>
      <div class="grow stack">
        <label><?= te('Clave secreta (secret key)') ?>
          <input type="password" name="turnstile_secret" value="" autocomplete="new-password" placeholder="<?= $has['turnstile_secret'] ? e(t('•••••••• guardada')) : '' ?>"></label>
        <?php if ($has['turnstile_secret']): ?><label class="check hint"><input type="checkbox" name="clear_turnstile_secret" value="1"> <?= te('Borrar la clave guardada') ?></label><?php endif; ?>
      </div>
    </div>
  </section>

  <section class="card">
    <h2><?= te('Webhook: enviar los mensajes a tu CRM') ?></h2>
    <p class="muted"><?= th('Cada mensaje nuevo se envía al instante como JSON a la URL que indiques: por ejemplo al CRM de <a href="{url}" target="_blank" rel="noopener">BionicAdri</a>, para que ADRI lo convierta en contacto y lo puntúe. Va firmado con <code>X-UW-Signature: sha256=…</code> (HMAC del cuerpo con el secreto) para que el CRM compruebe que viene de tu web.', ['url' => UW_AUTHOR_URL]) ?></p>
    <label class="check"><input type="checkbox" name="webhook_enabled" value="1" <?= $chk('webhook_enabled') ?>> <?= te('Enviar los mensajes al webhook') ?></label>
    <label><?= te('URL del webhook') ?><input type="url" name="webhook_url" value="<?= e($f['webhook_url']) ?>" placeholder="https://crm.miempresa.com/webhooks/universal-web" class="mono"></label>
    <?php if ($webhookSecret !== ''): ?>
      <div class="stack">
        <label><?= te('Secreto para verificar la firma') ?>
          <span class="copy-row"><code id="wh-secret"><?= e($webhookSecret) ?></code><button type="button" class="btn btn-sm" data-copy-target="#wh-secret"><?= te('Copiar') ?></button></span></label>
        <label class="check hint"><input type="checkbox" name="clear_webhook_secret" value="1"> <?= te('Generar un secreto nuevo al guardar') ?></label>
      </div>
    <?php else: ?><p class="hint"><?= te('El secreto de firma se genera automáticamente al activar el webhook.') ?></p><?php endif; ?>
  </section>

  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar') ?></button></div>
</form>

<section class="card">
  <h2><?= te('Probar el envío') ?></h2>
  <p class="muted"><?= te('Guarda primero los cambios. Si algo falla verás el error exacto del servidor.') ?></p>
  <form method="post" action="<?= ADM ?>/forms/test" class="row">
    <?= csrf_field() ?>
    <label class="grow"><?= te('Enviar a') ?><input type="email" name="to" value="<?= e($user['email']) ?>"></label>
    <div><button class="btn"><?= te('Enviar correo de prueba') ?></button></div>
  </form>
</section>

<?php if ($f['webhook_url'] !== ''): ?>
<section class="card">
  <h2><?= te('Probar el webhook') ?></h2>
  <form method="post" action="<?= ADM ?>/forms/webhook-test" class="row"><?= csrf_field() ?>
    <p class="grow muted"><?= th('Envía un mensaje de prueba (evento <code>test</code>) a <code>{url}</code>.', ['url' => $f['webhook_url']]) ?></p>
    <div><button class="btn"><?= te('Enviar webhook de prueba') ?></button></div></form>
</section>
<?php endif; ?>

<script>
(function () {
  var t = document.getElementById('transport'), box = document.getElementById('smtp-fields');
  var enc = document.getElementById('smtp_encryption'), port = document.getElementById('smtp_port');
  function sync() { box.hidden = t.value !== 'smtp'; }
  t.addEventListener('change', sync); sync();
  // Puerto típico al cambiar el cifrado (solo si tenía uno de los habituales).
  enc.addEventListener('change', function () {
    if (['25', '465', '587'].indexOf(port.value) !== -1) port.value = { tls: 587, ssl: 465, none: 25 }[enc.value];
  });
})();
</script>
