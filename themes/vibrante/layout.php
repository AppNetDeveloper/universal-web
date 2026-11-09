<?php
/**
 * Tema Vibrante: gimnasios, deporte, academias, ocio infantil y eventos.
 * Titulares Anton en mayúsculas, bloques de color intenso, sombras duras y bases en diagonal.
 */
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#e11d74';
$c = $s['contact'];
$social = array_filter(array_diff_key($s['social'], ['whatsapp' => 1]));
$labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok'];
$cta = $s['cta'];
$wa = preg_replace('/\D+/', '', (string) ($s['social']['whatsapp'] ?? ''));
$isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
?><!doctype html>
<html lang="<?= e($s['language']) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className = 'js';</script>
<?= $head ?>
<link rel="preload" href="/theme-assets/vibrante/fonts/figtree-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/default/style.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<meta name="theme-color" content="#fff7e0">
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body class="theme-vibrante">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>">
      <?php if ($s['logo']): ?><img src="<?= e($s['logo']) ?>" alt="<?= e($site['name']) ?>" height="36"><?php else: ?><span class="brand-mark" aria-hidden="true"></span><span class="brand-name"><?= e($site['name']) ?></span><?php endif; ?>
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
      <?php if ($cta['label'] && $cta['url']): ?><a class="btn btn-sm header-cta" href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?></a><?php endif; ?>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e(__('Abrir menú')) ?>"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<main id="main" tabindex="-1"><?= $content ?></main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <strong class="footer-brand"><?= e($site['name']) ?></strong>
      <?php if ($s['tagline']): ?><p><?= e($s['tagline']) ?></p><?php endif; ?>
    </div>
    <nav aria-label="<?= e(__('Navegación')) ?>">
      <strong><?= e(__('Navegación')) ?></strong>
      <ul class="plain"><?php foreach ($s['menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
    </nav>
    <?php if ($c['email'] || $c['phone'] || $c['address'] || $wa): ?>
    <div>
      <strong><?= e(__('Contacto')) ?></strong>
      <ul class="plain">
        <?php if ($c['email']): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
        <?php if ($c['phone']): ?><li><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string) $c['phone'])) ?>"><?= e($c['phone']) ?></a></li><?php endif; ?>
        <?php if ($wa): ?><li><a href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= e(__('Escríbenos por WhatsApp')) ?></a></li><?php endif; ?>
        <?php if ($c['address']): ?><li><?= e($c['address']) ?></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($social): ?>
    <div>
      <strong><?= e(__('Síguenos')) ?></strong>
      <ul class="plain"><?php foreach ($social as $k => $u): ?><li><a href="<?= e($u) ?>" target="_blank" rel="noopener me"><?= e($labels[$k] ?? $k) ?></a></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
    <?php if ($s['utility_menu']): ?>
    <div>
      <strong><?= e(__('Accesos')) ?></strong>
      <ul class="plain"><?php foreach ($s['utility_menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"<?= preg_match('#^https?://#', $m['url']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>
  </div>
  <div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e($site['name']) ?><?= $s['footer_text'] ? ' · ' . e($s['footer_text']) : '' ?></span>
    <?= uw_credit() ?>
  </div>
</footer>
<?= site_code_body_end($svc) ?>
</body>
</html>
