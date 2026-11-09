<?php /** @var array $flashes @var string $email */ ?><!doctype html>
<html lang="<?= panel_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= te('Verificación en dos pasos') ?></title>
<link rel="stylesheet" href="<?= e(asset_v('/assets/admin.css')) ?>">
</head>
<body class="login-body">
<form method="post" action="<?= ADM ?>/login/2fa" class="login-card">
  <div class="brand"><span class="logo">U</span> Universal Web</div>
  <h1><?= te('Verificación en dos pasos') ?></h1>
  <p class="muted"><?= th('Abre tu app de autenticación y escribe el código de 6 dígitos de <b>{email}</b>.', ['email' => $email]) ?></p>
  <?php foreach ($flashes as $f): ?><div class="flash flash-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= e(t_msg((string) $f['msg'])) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <label><?= te('Código') ?><input type="text" name="code" required autofocus inputmode="numeric" autocomplete="one-time-code" maxlength="11" pattern="[0-9 ]{6,7}|[a-zA-Z2-7]{5}-?[a-zA-Z2-7]{5}" placeholder="123 456" class="code-input"></label>
  <button class="btn btn-primary btn-block"><?= te('Verificar') ?></button>
  <p class="hint"><?= th('¿Has perdido el móvil? Escribe uno de tus <b>códigos de recuperación</b> (formato <code>abcde-fghij</code>).') ?></p>
  <p class="hint"><a href="<?= ADM ?>/login"><?= te('Volver a empezar') ?></a></p>
</form>
<?php $langBase = ADM . '/login/2fa'; include __DIR__ . '/_lang_links.php'; ?>
<p class="login-credit">Powered by <a href="<?= UW_AUTHOR_URL ?>" target="_blank" rel="noopener">bionicadri.com</a> · <a href="<?= UW_DEMO_URL ?>" target="_blank" rel="noopener"><?= te('Pide una demo de ADRI') ?></a></p>
</body>
</html>
