<?php /** @var array $flashes @var string $token @var bool $valid */ ?><!doctype html>
<html lang="<?= panel_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<meta name="referrer" content="no-referrer">
<title><?= te('Nueva contraseña') ?></title>
<link rel="stylesheet" href="<?= e(asset_v('/assets/admin.css')) ?>">
</head>
<body class="login-body">
<form method="post" action="<?= ADM ?>/reset" class="login-card">
  <div class="brand"><span class="logo">U</span> Universal Web</div>
  <h1><?= te('Nueva contraseña') ?></h1>
  <?php foreach ($flashes as $f): ?><div class="flash flash-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= e(t_msg((string) $f['msg'])) ?></div><?php endforeach; ?>
  <?php if ($valid): ?>
    <?= csrf_field() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <label><?= te('Contraseña nueva') ?> <small class="muted"><?= te('(mín. 8 caracteres)') ?></small><input type="password" name="new" required minlength="8" autofocus autocomplete="new-password"></label>
    <label><?= te('Repite la contraseña nueva') ?><input type="password" name="confirm" required minlength="8" autocomplete="new-password"></label>
    <button class="btn btn-primary btn-block"><?= te('Guardar contraseña') ?></button>
    <p class="hint"><?= te('Si tienes activa la verificación en dos pasos, te la seguiremos pidiendo al entrar.') ?></p>
  <?php else: ?>
    <p><?= te('El enlace no es válido o ha caducado. Pide otro.') ?></p>
    <p><a class="btn btn-primary btn-block" href="<?= ADM ?>/forgot"><?= te('Pedir un enlace nuevo') ?></a></p>
  <?php endif; ?>
  <p class="hint" style="text-align:center"><a href="<?= ADM ?>/login"><?= te('Volver al acceso') ?></a></p>
</form>
</body>
</html>
