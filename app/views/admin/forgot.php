<?php /** @var array $flashes @var bool $ready */ ?><!doctype html>
<html lang="<?= panel_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= te('Recuperar contraseña') ?></title>
<link rel="stylesheet" href="<?= e(asset_v('/assets/admin.css')) ?>">
</head>
<body class="login-body">
<form method="post" action="<?= ADM ?>/forgot" class="login-card">
  <div class="brand"><span class="logo">U</span> Universal Web</div>
  <h1><?= te('Recuperar contraseña') ?></h1>
  <?php foreach ($flashes as $f): ?><div class="flash flash-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= e(t_msg((string) $f['msg'])) ?></div><?php endforeach; ?>
  <?php if ($ready): ?>
    <p class="muted"><?= te('Escribe el email de tu cuenta y te enviaremos un enlace para elegir una contraseña nueva.') ?></p>
    <?= csrf_field() ?>
    <label><?= te('Email') ?><input type="email" name="email" required autofocus autocomplete="username"></label>
    <button class="btn btn-primary btn-block"><?= te('Enviar enlace') ?></button>
  <?php else: ?>
    <p><?= te('Este panel todavía no tiene configurado el envío de correos, así que no puede mandarte un enlace.') ?></p>
    <p class="hint"><?= th('Pide a un administrador que te ponga una contraseña nueva, o en el servidor: <code>php bin/uw user:password --email=… --password=…</code>') ?></p>
  <?php endif; ?>
  <p class="hint" style="text-align:center"><a href="<?= ADM ?>/login"><?= te('Volver al acceso') ?></a></p>
</form>
<?php include __DIR__ . '/_lang_links.php'; ?>
</body>
</html>
