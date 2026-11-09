<?php /** @var array $flashes @var string $siteName */ ?><!doctype html>
<html lang="<?= panel_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= te('Acceso') ?> · <?= e($siteName) ?></title>
<link rel="stylesheet" href="<?= e(asset_v('/assets/admin.css')) ?>">
</head>
<body class="login-body">
<form method="post" action="<?= ADM ?>/login" class="login-card">
  <div class="brand"><span class="logo">U</span> Universal Web</div>
  <h1><?= te('Acceder al panel') ?></h1>
  <?php foreach ($flashes as $f): ?><div class="flash flash-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>"><?= e(t_msg((string) $f['msg'])) ?></div><?php endforeach; ?>
  <?= csrf_field() ?>
  <label><?= te('Email') ?><input type="email" name="email" required autofocus autocomplete="username"></label>
  <label><?= te('Contraseña') ?><input type="password" name="password" required autocomplete="current-password"></label>
  <button class="btn btn-primary btn-block"><?= te('Entrar') ?></button>
  <p class="hint" style="text-align:center"><a href="<?= ADM ?>/forgot"><?= te('¿Has olvidado tu contraseña?') ?></a></p>
</form>
<?php include __DIR__ . '/_lang_links.php'; ?>
<p class="login-credit">Powered by <a href="<?= UW_AUTHOR_URL ?>" target="_blank" rel="noopener">bionicadri.com</a> · <a href="<?= UW_DEMO_URL ?>" target="_blank" rel="noopener"><?= te('Pide una demo de ADRI') ?></a></p>
</body>
</html>
