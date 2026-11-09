<?php /** @var array $user */
$setup = (string) ($_SESSION['2fa_setup'] ?? '');
$codes = $_SESSION['2fa_codes_once'] ?? null;
unset($_SESSION['2fa_codes_once']);
$enabled = twofa_enabled($user);
$left = count(json_dec((string) ($user['recovery_codes'] ?? '[]')));
$required = !empty(platform()['require_2fa']); ?>
<div class="page-head"><h1><?= te('Mi cuenta') ?></h1></div>

<section class="card narrow" id="dos-pasos">
  <h2><?= te('Verificación en dos pasos') ?> <?= $enabled ? adm_badge(t('Activa'), 'green') : adm_badge(t('Desactivada'), $required ? 'red' : 'gray') ?></h2>

  <?php if ($codes): ?>
    <div class="token-new">
      <p><?= th('<b>Guarda estos códigos de recuperación ahora: no se volverán a mostrar.</b> Cada uno sirve una sola vez para entrar si pierdes el móvil.') ?></p>
      <pre id="rec-codes" class="mono"><?= e(implode("\n", $codes)) ?></pre>
      <button type="button" class="btn btn-sm btn-primary" data-copy-target="#rec-codes"><?= te('Copiar códigos') ?></button>
    </div>
  <?php endif; ?>

  <?php if (!$enabled && $setup === ''): ?>
    <p><?= te('Protege tu cuenta con un código temporal de una app de autenticación: aunque alguien robe tu contraseña, no podrá entrar sin tu móvil.') ?></p>
    <p class="hint"><?= te('Apps compatibles: Google Authenticator, Microsoft Authenticator, Authy, 1Password, Bitwarden…') ?></p>
    <form method="post" action="<?= ADM ?>/account/2fa/start"><?= csrf_field() ?><button class="btn btn-primary"><?= te('Activar verificación en dos pasos') ?></button></form>

  <?php elseif (!$enabled): $uri = twofa_uri($user, $setup); ?>
    <ol class="steps-2fa">
      <li><?= th('Abre tu app de autenticación y pulsa <b>Añadir cuenta → Escanear código QR</b>.') ?></li>
      <li><?= te('Escanea este código:') ?>
        <div id="qr" class="qr" data-uri="<?= e($uri) ?>" role="img" aria-label="<?= te('Código QR para la app de autenticación') ?>"></div>
        <p class="hint"><?= te('¿No puedes escanear? Introduce esta clave a mano (tipo «basada en tiempo»):') ?><br>
          <code id="secret" class="mono"><?= e(trim(chunk_split($setup, 4, ' '))) ?></code></p></li>
      <li><?= te('Escribe el código de 6 dígitos que muestra la app:') ?>
        <form method="post" action="<?= ADM ?>/account/2fa/enable" class="row">
          <?= csrf_field() ?>
          <input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="7" pattern="[0-9 ]{6,7}" placeholder="123 456" class="code-input">
          <button class="btn btn-primary"><?= te('Confirmar y activar') ?></button>
        </form></li>
    </ol>
    <form method="post" action="<?= ADM ?>/account/2fa/cancel"><?= csrf_field() ?><button class="btn btn-ghost btn-sm"><?= te('Cancelar') ?></button></form>
    <script src="<?= e(asset_v('/assets/vendor/qrcode.js')) ?>"></script>
    <script>
    (function () {
      var el = document.getElementById('qr');
      if (!window.qrcode || !el) return;
      var qr = qrcode(0, 'M'); qr.addData(el.getAttribute('data-uri')); qr.make();
      el.innerHTML = qr.createSvgTag({ cellSize: 5, margin: 4, scalable: true });
    })();
    </script>

  <?php else: ?>
    <p><?= th('Al entrar se te pedirá el código de tu app. Te quedan <b>{n}</b> códigos de recuperación.', ['n' => $left]) ?></p>
    <form method="post" action="<?= ADM ?>/account/2fa/manage" class="stack">
      <?= csrf_field() ?>
      <div class="row">
        <label class="grow"><?= te('Contraseña') ?><input type="password" name="password" required autocomplete="current-password"></label>
        <label class="grow"><?= te('Código de la app') ?><input type="text" name="code" required inputmode="numeric" autocomplete="one-time-code" maxlength="11" placeholder="123 456" class="code-input"></label>
      </div>
      <div class="row">
        <button class="btn" name="action" value="regen"><?= te('Generar códigos de recuperación nuevos') ?></button>
        <?php if (!$required): ?><button class="btn btn-danger" name="action" value="disable" data-confirm-click="<?= te('¿Desactivar la verificación en dos pasos? Tu cuenta quedará menos protegida.') ?>"><?= te('Desactivar') ?></button><?php endif; ?>
      </div>
      <?php if ($required): ?><p class="hint"><?= te('En esta instalación es obligatoria: no se puede desactivar.') ?></p><?php endif; ?>
    </form>
  <?php endif; ?>
</section>

<section class="card narrow">
  <h2><?= te('Idioma del panel') ?></h2>
  <form method="post" action="<?= ADM ?>/account/locale" class="row">
    <?= csrf_field() ?>
    <label class="grow"><?= te('Idioma') ?>
      <select name="locale"><?php foreach (PANEL_LOCALES as $code => $name): ?><option value="<?= e($code) ?>" <?= panel_locale() === $code ? 'selected' : '' ?>><?= e($name) ?></option><?php endforeach; ?></select></label>
    <div><button class="btn btn-primary"><?= te('Guardar') ?></button></div>
  </form>
</section>

<section class="card narrow" id="mis-datos">
  <h2><?= te('Mis datos') ?></h2>
  <form method="post" action="<?= ADM ?>/account/profile" class="stack">
    <?= csrf_field() ?>
    <label><?= te('Nombre') ?><input type="text" name="name" maxlength="100" value="<?= e($user['name']) ?>"></label>
    <label><?= te('Email') ?> <small class="muted"><?= te('(con él entras al panel)') ?></small><input type="email" name="email" required value="<?= e($user['email']) ?>"></label>
    <label><?= te('Tu contraseña') ?> <small class="muted"><?= te('(solo si cambias el email)') ?></small><input type="password" name="confirm_password" autocomplete="current-password"></label>
    <div><button class="btn btn-primary"><?= te('Guardar') ?></button></div>
  </form>
</section>

<section class="card narrow">
  <h2><?= te('Cambiar contraseña') ?></h2>
  <form method="post" action="<?= ADM ?>/account/password" class="stack">
    <?= csrf_field() ?>
    <label><?= te('Contraseña actual') ?><input type="password" name="current" required autocomplete="current-password"></label>
    <label><?= te('Contraseña nueva') ?> <small class="muted"><?= te('(mín. 8 caracteres)') ?></small><input type="password" name="new" required minlength="8" autocomplete="new-password"></label>
    <label><?= te('Repite la contraseña nueva') ?><input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
    <div><button class="btn btn-primary"><?= te('Actualizar contraseña') ?></button></div>
  </form>
</section>
