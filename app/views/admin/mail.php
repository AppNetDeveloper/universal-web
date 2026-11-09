<?php /** @var array $cfg @var bool $hasPassword @var bool $ready */
$sel = fn(string $k, string $v) => $cfg[$k] === $v ? ' selected' : ''; ?>
<div class="page-head"><h1><?= te('Correo del panel') ?> <?= $ready ? adm_badge(t('Activo'), 'green') : adm_badge(t('Desactivado')) ?></h1></div>
<p class="muted"><?= te('Servidor de correo con el que el panel envía los enlaces de «¿Has olvidado tu contraseña?». Es independiente del correo de los formularios de cada web.') ?></p>
<form method="post" action="<?= ADM ?>/mail" class="stack" autocomplete="off">
  <?= csrf_field() ?>
  <section class="card">
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>> <?= te('Activar la recuperación de contraseña por correo') ?></label>
    <label><?= te('URL pública del panel') ?> <span class="hint"><?= te('(la de los enlaces del correo; nunca se toma de la petición, para que nadie pueda falsificarla)') ?></span>
      <input type="url" name="panel_url" value="<?= e($cfg['panel_url']) ?>" placeholder="https://panel.midominio.com" class="mono"></label>
    <div class="row">
      <label class="grow"><?= te('Email remitente') ?><input type="email" name="from_email" value="<?= e($cfg['from_email']) ?>" placeholder="no-responder@midominio.com"></label>
      <label class="grow"><?= te('Nombre remitente') ?><input type="text" name="from_name" value="<?= e($cfg['from_name']) ?>"></label>
    </div>
  </section>
  <section class="card">
    <h2><?= te('Servidor SMTP') ?></h2>
    <div class="row">
      <label><?= te('Envío') ?><select name="transport"><option value="smtp"<?= $sel('transport', 'smtp') ?>>SMTP</option><option value="mail"<?= $sel('transport', 'mail') ?>><?= te('mail() de PHP (no recomendado)') ?></option></select></label>
      <label class="grow"><?= te('Servidor') ?><input type="text" name="smtp_host" value="<?= e($cfg['smtp_host']) ?>" placeholder="smtp.midominio.com"></label>
      <label><?= te('Puerto') ?><input type="number" name="smtp_port" value="<?= (int) $cfg['smtp_port'] ?>" style="width:6rem"></label>
      <label><?= te('Cifrado') ?><select name="smtp_encryption"><option value="tls"<?= $sel('smtp_encryption', 'tls') ?>>STARTTLS (587)</option><option value="ssl"<?= $sel('smtp_encryption', 'ssl') ?>>SSL/TLS (465)</option><option value="none"<?= $sel('smtp_encryption', 'none') ?>><?= te('Ninguno') ?></option></select></label>
    </div>
    <div class="row">
      <label class="grow"><?= te('Usuario') ?><input type="text" name="smtp_username" value="<?= e($cfg['smtp_username']) ?>" autocomplete="off"></label>
      <label class="grow"><?= te('Contraseña') ?><input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= $hasPassword ? e(t('•••••••• guardada')) : '' ?>"></label>
    </div>
    <label class="check"><input type="checkbox" name="smtp_verify_cert" value="1" <?= $cfg['smtp_verify_cert'] ? 'checked' : '' ?>> <?= te('Verificar el certificado del servidor (recomendado)') ?></label>
  </section>
  <div class="savebar">
    <button class="btn btn-primary"><?= te('Guardar') ?></button>
    <button class="btn" formaction="<?= ADM ?>/mail/test"><?= te('Guardar y enviarme una prueba') ?></button>
  </div>
</form>
