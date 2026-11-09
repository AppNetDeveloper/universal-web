<?php
/**
 * Tema Cálido y local: restaurantes, cafeterías, tiendas, estética. Acogedor, cercano y con fotos grandes.
 * Usa la estructura del tema Corporativo (barra de contacto, botón destacado, pie a 4 columnas, WhatsApp)
 * con su propio estilo: carga el CSS base, el del Corporativo y encima el suyo.
 */
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#0b5cad';
$c = $s['contact'];
$tel = fn(string $p) => 'tel:' . preg_replace('/[^0-9+]/', '', $p);
$social = array_filter(array_diff_key($s['social'], ['whatsapp' => 1]));
$labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok'];
$whatsapp = (string) ($s['social']['whatsapp'] ?? '');
$cta = $s['cta'];
$isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
$brand = $s['logo']
    ? '<img src="' . e($s['logo']) . '" alt="' . e($site['name']) . '" height="44">'
    : '<span>' . e($site['name']) . '</span>';
?><!doctype html>
<html lang="<?= e($s['language']) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className = 'js';</script>
<?= $head ?>
<link rel="preload" href="/theme-assets/calido/fonts/nunito-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/default/style.css')) ?>">
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/corporate/style.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<meta name="theme-color" content="<?= e($accent) ?>">
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body class="theme-calido">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>

<?php if ($c['phone'] || $c['email'] || $s['utility_menu'] || !empty($langs)): ?>
<div class="topbar">
  <div class="container topbar-inner">
    <ul class="topbar-contact">
      <?php if ($c['phone']): ?><li><a href="<?= e($tel($c['phone'])) ?>"><?= uw_icon('phone', 'icon icon-sm') ?><?= e($c['phone']) ?></a></li><?php endif; ?>
      <?php if ($c['email']): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= uw_icon('mail', 'icon icon-sm') ?><?= e($c['email']) ?></a></li><?php endif; ?>
      <?php if ($c['address']): ?><li class="topbar-address"><?= uw_icon('pin', 'icon icon-sm') ?><?= e(str_limit($c['address'], 60)) ?></li><?php endif; ?>
    </ul>
    <?php if (!empty($langs)): ?><nav class="lang-switch" aria-label="<?= e(__('Idioma')) ?>"><?php foreach ($langs as [$lc, $ln, $lu, $la]): ?><a href="<?= e($lu) ?>" hreflang="<?= e($lc) ?>" lang="<?= e($lc) ?>"<?= $la ? ' aria-current="true" class="active"' : '' ?> title="<?= e($ln) ?>"><?= e(strtoupper($lc)) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <?php if ($s['utility_menu']): ?>
    <ul class="topbar-links">
      <?php foreach ($s['utility_menu'] as $m): $ext = (bool) preg_match('#^https?://#', $m['url']); ?>
      <li><a href="<?= e($m['url']) ?>"<?= $ext ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['label']) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>" aria-label="<?= e($site['name']) ?> — inicio"><?= $brand ?></a>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(__('Principal')) ?>">
      <ul>
        <?php foreach ($s['menu'] as $m): ?>
        <li><a href="<?= e($m['url']) ?>"<?= $isActive((string) $m['url']) ? ' class="active" aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
      <?php if ($cta['label'] && $cta['url']): ?><a class="btn nav-cta-mobile" href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?></a><?php endif; ?>
    </nav>
    <?php if ($cta['label'] && $cta['url']): ?><a class="btn header-cta" href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?> <?= uw_icon('arrow', 'icon icon-sm') ?></a><?php endif; ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e(__('Abrir menú')) ?>"><span></span><span></span><span></span></button>
  </div>
</header>

<main id="main" tabindex="-1"><?= $content ?></main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a class="brand brand-footer" href="<?= e($svc->langPath('/')) ?>"><?= $brand ?></a>
      <?php if ($s['tagline']): ?><p><?= e($s['tagline']) ?></p><?php endif; ?>
      <?php if ($social): ?>
      <ul class="footer-social">
        <?php foreach ($social as $k => $u): ?><li><a href="<?= e($u) ?>" target="_blank" rel="noopener me"><?= e($labels[$k] ?? $k) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
    </div>
    <nav aria-label="<?= e(__('Pie de página')) ?>">
      <h2 class="footer-title"><?= e(__('Navegación')) ?></h2>
      <ul class="plain">
        <?php foreach ($s['menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"><?= e($m['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </nav>
    <?php if ($s['utility_menu'] || ($cta['label'] && $cta['url'])): ?>
    <div>
      <h2 class="footer-title"><?= e(__('Accesos')) ?></h2>
      <ul class="plain">
        <?php if ($cta['label'] && $cta['url']): ?><li><a href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?></a></li><?php endif; ?>
        <?php foreach ($s['utility_menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"<?= preg_match('#^https?://#', $m['url']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($c['phone'] || $c['email'] || $c['address']): ?>
    <div>
      <h2 class="footer-title"><?= e(__('Contacto')) ?></h2>
      <ul class="plain footer-contact">
        <?php if ($c['address']): ?><li><?= uw_icon('pin', 'icon icon-sm') ?><span><?= e($c['address']) ?></span></li><?php endif; ?>
        <?php if ($c['phone']): ?><li><?= uw_icon('phone', 'icon icon-sm') ?><a href="<?= e($tel($c['phone'])) ?>"><?= e($c['phone']) ?></a></li><?php endif; ?>
        <?php if ($c['email']): ?><li><?= uw_icon('mail', 'icon icon-sm') ?><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e($site['name']) ?><?= $s['footer_text'] ? ' · ' . e($s['footer_text']) : '' ?></span>
    <?= uw_credit() ?>
  </div>
</footer>

<?php if ($whatsapp !== '' && preg_match('#^https://(wa\.me|api\.whatsapp\.com)/#', $whatsapp)): ?>
<a class="whatsapp-float" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('Escríbenos por WhatsApp')) ?>">
  <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.6c-2 0-4-.6-5.7-1.6l-.4-.2-3.9 1 1-3.8-.3-.4A10.6 10.6 0 1 1 16 26.6zm5.8-7.9c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7 0a8.7 8.7 0 0 1-4.3-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .9 2.8 1 3.9.8a3.3 3.3 0 0 0 2.1-1.5 2.7 2.7 0 0 0 .2-1.5c-.1-.2-.3-.3-.5-.4z"/></svg>
</a>
<?php endif; ?>
<?= site_code_body_end($svc) ?>
</body>
</html>
