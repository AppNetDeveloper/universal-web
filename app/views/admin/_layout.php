<?php
/** @var array $user @var ?SiteService $svc @var string $role @var array $sites @var array $flashes @var string $content @var string $title @var string $path */
$unread = $svc ? (int) val('SELECT COUNT(*) FROM messages WHERE site_id = ? AND is_read = 0', [$svc->id()]) : 0;
$nav = [
    ['Contenido', [
        ['/', 'Panel', 'home', 'editor'], ['/pages', 'Páginas', 'file', 'editor'], ['/posts', 'Entradas', 'edit', 'editor'],
        ['/media', 'Medios', 'image', 'editor'], ['/messages', 'Mensajes', 'mail', 'editor'], ['/seo', 'SEO', 'search', 'editor'], ['/errors', 'Errores', 'activity', 'editor'], ['/trash', 'Papelera', 'trash', 'editor'],
        ['/tokens', 'Tokens de IA', 'key', 'editor'], ['/help', 'Ayuda', 'help', 'editor'],
    ]],
    ['Administración', [
        ['/settings', 'Ajustes', 'settings', 'admin'], ['/themes', 'Temas', 'layout', 'admin'], ['/template', 'Plantilla HTML', 'palette', 'admin'], ['/design', 'Diseño y código', 'palette', 'admin'], ['/languages', 'Idiomas', 'lang', 'admin'], ['/cookies', 'Cookies', 'cookie', 'admin'], ['/forms', 'Formularios', 'send', 'admin'],
        ['/publish', 'Publicación', 'upload', 'admin'], ['/backups', 'Copias de seguridad', 'archive', 'admin'],
        ['/users', 'Usuarios', 'users', 'admin'], ['/activity', 'Actividad', 'activity', 'admin'],
    ]],
    ['Plataforma', [['/sites', 'Webs', 'globe', 'super'], ['/security', 'Seguridad y red', 'shield', 'super'], ['/mail', 'Correo del panel', 'mail', 'super']]],
];
$isActive = function (string $p) use ($path): bool {
    if ($p === '/') return $path === '/';
    return $path === $p || str_starts_with($path, $p . '/');
};
?><!doctype html>
<html lang="<?= panel_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?= te($title ?? 'Panel') ?> · <?= e($svc ? $svc->site['name'] : 'Universal Web') ?></title>
<link rel="stylesheet" href="<?= e(asset_v('/assets/admin.css')) ?>">
<script>window.UW_I18N = <?= json_enc(t_js()) ?>;</script>
<script src="<?= e(asset_v('/assets/admin.js')) ?>" defer></script>
<?php if (!empty($refill)) echo adm_json_script('uw-refill', ['action' => ADM . $refill['path'], 'post' => $refill['post']]); ?>
</head>
<body>
<header class="topbar">
  <button type="button" class="btn-icon" id="nav-toggle" aria-label="<?= te('Menú') ?>"><?= adm_icon('menu') ?></button>
  <strong class="brand-sm">Universal Web</strong>
</header>
<aside class="sidebar" id="sidebar">
  <div class="brand"><span class="logo">U</span> Universal Web</div>
  <?php if ($svc): ?>
  <div class="site-box">
    <?php if (count($sites) > 1): ?>
      <select id="site-switch" aria-label="<?= te('Web actual') ?>" data-to="<?= e($path) ?>">
        <?php foreach ($sites as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) $s['id'] === $svc->id() ? 'selected' : '' ?>><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    <?php else: ?>
      <div class="site-name"><?= e($svc->site['name']) ?></div>
    <?php endif; ?>
    <a class="site-link" href="<?= e($svc->url('/')) ?>" target="_blank" rel="noopener"><?= te('Ver web') ?> <?= adm_icon('ext') ?></a>
  </div>
  <?php endif; ?>
  <nav>
    <?php foreach ($nav as [$group, $items]):
        $vis = array_filter($items, fn($i) => ($i[3] === 'super' || $svc) && role_at_least($role, $i[3]));
        if (!$vis) continue; ?>
      <div class="nav-group"><?= te($group) ?></div>
      <?php foreach ($vis as [$href, $label, $icon]): ?>
        <a href="<?= ADM . ($href === '/' ? '/' : $href) ?>" class="nav-item <?= $isActive($href) ? 'active' : '' ?>">
          <?= adm_icon($icon) ?><span><?= te($label) ?></span>
          <?php if ($href === '/messages' && $unread): ?><span class="count"><?= $unread ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>
  <div class="side-credit">
    <a class="side-demo" href="<?= UW_DEMO_URL ?>" target="_blank" rel="noopener"><?= th('¿IA para tu empresa? <b>Pedir demo</b>') ?></a>
    <a href="<?= UW_AUTHOR_URL ?>" target="_blank" rel="noopener">Powered by <b>bionicadri.com</b></a>
  </div>
  <div class="side-foot">
    <a href="<?= ADM ?>/account" class="who <?= $isActive('/account') ? 'active' : '' ?>"><?= adm_icon('user') ?><span><?= e($user['name']) ?><small><?= te(['super' => 'Superadmin', 'admin' => 'Administrador', 'editor' => 'Editor'][$role] ?? '') ?></small></span></a>
    <form method="post" action="<?= ADM ?>/logout"><?= csrf_field() ?><button class="btn-icon" title="<?= te('Salir') ?>" aria-label="<?= te('Salir') ?>"><?= adm_icon('out') ?></button></form>
  </div>
</aside>
<main class="main">
  <button type="button" class="tour-fab" id="tour-start" title="<?= te('Te explico esta pantalla paso a paso') ?>"><?= adm_icon('help') ?><span><?= te('Guíame') ?></span></button>
  <?= adm_json_script('uw-tour', tour_for((string) ($view ?? '')) ?? ['title' => t($title ?? 'Panel'), 'steps' => []]) ?>
  <?php foreach ($flashes as $f): ?>
    <div class="flash flash-<?= $f['type'] === 'error' ? 'error' : 'ok' ?>" role="status"><?= e(t_msg((string) $f['msg'])) ?></div>
  <?php endforeach; ?>
  <?= $content ?>
</main>
</body>
</html>
