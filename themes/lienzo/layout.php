<?php
/**
 * Tema Lienzo: portfolios y profesionales independientes.
 * Editorial y tipográfico: papel y tinta con una sola nota de color, titulares gigantes, esquinas rectas y sin sombras.
 * Sigue el modo claro u oscuro del dispositivo.
 */
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#e5420b';
$c = $s['contact'];
$social = array_filter(array_diff_key($s['social'], ['whatsapp' => 1]));
$whatsapp = (string) ($s['social']['whatsapp'] ?? '');
$labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok'];
$cta = $s['cta'];
$tel = $c['phone'] ? preg_replace('/[^0-9+]/', '', (string) $c['phone']) : '';
$isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
?><!doctype html>
<html lang="<?= e($s['language']) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<script>document.documentElement.className = 'js';</script>
<?= $head ?>
<link rel="preload" href="/theme-assets/lienzo/fonts/syne-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/default/style.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<meta name="theme-color" content="#f2efe9" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#0d0d0d" media="(prefers-color-scheme: dark)">
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body class="theme-lienzo">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<?php if ($s['utility_menu']): ?>
<div class="utility">
  <div class="container utility-inner">
    <nav class="utility-links" aria-label="<?= e(__('Accesos')) ?>"><?php foreach ($s['utility_menu'] as $m): ?><a href="<?= e($m['url']) ?>"<?= preg_match('#^https?://#', $m['url']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['label']) ?></a><?php endforeach; ?></nav>
  </div>
</div>
<?php endif; ?>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>">
      <?php if ($s['logo']): ?><img src="<?= e($s['logo']) ?>" alt="<?= e($site['name']) ?>" height="40"><?php else: ?><span class="brand-name"><?= e($site['name']) ?></span><?php endif; ?>
    </a>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(__('Principal')) ?>">
      <ul>
        <?php foreach ($s['menu'] as $m): ?>
        <li><a href="<?= e($m['url']) ?>"<?= $isActive((string) $m['url']) ? ' class="active" aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="header-actions">
      <?php if (!empty($langs)): ?><nav class="lang-switch" aria-label="<?= e(__('Idioma')) ?>"><?php foreach ($langs as [$lc, $ln, $lu, $la]): ?><a href="<?= e($lu) ?>" hreflang="<?= e($lc) ?>" lang="<?= e($lc) ?>"<?= $la ? ' aria-current="true" class="active"' : '' ?> title="<?= e($ln) ?>"><?= e(strtoupper($lc)) ?></a><?php endforeach; ?></nav><?php endif; ?>
      <?php if ($cta['label'] && $cta['url']): ?><a class="header-cta" href="<?= e($cta['url']) ?>"><span><?= e($cta['label']) ?></span><?= uw_icon('arrow', 'icon icon-sm') ?></a><?php endif; ?>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e(__('Abrir menú')) ?>"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<main id="main" tabindex="-1"><?= $content ?></main>

<footer class="site-footer">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-about">
        <?php if ($s['tagline']): ?><p class="footer-tagline"><?= e($s['tagline']) ?></p><?php endif; ?>
        <?php if ($cta['label'] && $cta['url']): ?><p><a class="hero-link" href="<?= e($cta['url']) ?>"><span><?= e($cta['label']) ?></span><?= uw_icon('arrow', 'icon icon-sm') ?></a></p><?php endif; ?>
      </div>
      <nav aria-label="<?= e(__('Pie de página')) ?>">
        <strong><?= e(__('Navegación')) ?></strong>
        <ul class="plain">
          <?php foreach ($s['menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"><?= e($m['label']) ?></a></li><?php endforeach; ?>
        </ul>
      </nav>
      <?php if ($c['email'] || $c['phone'] || $c['address']): ?>
      <div>
        <strong><?= e(__('Contacto')) ?></strong>
        <ul class="plain">
          <?php if ($tel !== ''): ?><li><a href="tel:<?= e($tel) ?>"><?= e($c['phone']) ?></a></li><?php endif; ?>
          <?php if ($c['email']): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
          <?php if ($c['address']): ?><li><?= e($c['address']) ?></li><?php endif; ?>
        </ul>
      </div>
      <?php endif; ?>
      <?php if ($social): ?>
      <div>
        <strong><?= e(__('Síguenos')) ?></strong>
        <ul class="plain">
          <?php foreach ($social as $k => $u): ?><li><a href="<?= e($u) ?>" target="_blank" rel="noopener me"><?= e($labels[$k] ?? $k) ?></a></li><?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>
    <p class="footer-name" aria-hidden="true"><?= e($site['name']) ?></p>
    <div class="footer-bottom">
      <span>&copy; <?= date('Y') ?> <?= e($site['name']) ?><?= $s['footer_text'] ? ' · ' . e($s['footer_text']) : '' ?></span>
      <?= uw_credit() ?>
    </div>
  </div>
</footer>

<?php if ($whatsapp !== '' && preg_match('#^https://(wa\.me|api\.whatsapp\.com)/#', $whatsapp)): ?>
<a class="whatsapp-float" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('Escríbenos por WhatsApp')) ?>">
  <svg viewBox="0 0 32 32" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.6c-2 0-4-.6-5.7-1.6l-.4-.2-3.9 1 1-3.8-.3-.4A10.6 10.6 0 1 1 16 26.6zm5.8-7.9c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7 0a8.7 8.7 0 0 1-4.3-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .9 2.8 1 3.9.8a3.3 3.3 0 0 0 2.1-1.5 2.7 2.7 0 0 0 .2-1.5c-.1-.2-.3-.3-.5-.4z"/></svg>
</a>
<?php endif; ?>
<?= site_code_body_end($svc) ?>
</body>
</html>
