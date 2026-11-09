<?php
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#4f46e5';
$social = array_filter($s['social']);
$labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp'];
$isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
?><!doctype html>
<html lang="<?= e($s['language']) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<?= $head ?>
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body>
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>">
      <?php if ($s['logo']): ?><img src="<?= e($s['logo']) ?>" alt="<?= e($site['name']) ?>" height="36"><?php else: ?><span><?= e($site['name']) ?></span><?php endif; ?>
    </a>
    <?php if (!empty($langs)): ?><nav class="lang-switch" aria-label="<?= e(__('Idioma')) ?>"><?php foreach ($langs as [$lc, $ln, $lu, $la]): ?><a href="<?= e($lu) ?>" hreflang="<?= e($lc) ?>" lang="<?= e($lc) ?>"<?= $la ? ' aria-current="true" class="active"' : '' ?> title="<?= e($ln) ?>"><?= e(strtoupper($lc)) ?></a><?php endforeach; ?></nav><?php endif; ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e(__('Abrir menú')) ?>"><span></span><span></span><span></span></button>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(__('Principal')) ?>">
      <ul>
        <?php foreach ($s['menu'] as $m): ?>
          <li><a href="<?= e($m['url']) ?>"<?= $isActive((string) $m['url']) ? ' class="active" aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
  </div>
</header>
<main id="main" tabindex="-1"><?= $content ?></main>
<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <strong class="footer-name"><?= e($site['name']) ?></strong>
      <?php if ($s['tagline']): ?><p><?= e($s['tagline']) ?></p><?php endif; ?>
    </div>
    <?php if ($s['contact']['email'] || $s['contact']['phone'] || $s['contact']['address']): ?>
    <div>
      <strong><?= e(__('Contacto')) ?></strong>
      <ul class="plain">
        <?php if ($s['contact']['email']): ?><li><a href="mailto:<?= e($s['contact']['email']) ?>"><?= e($s['contact']['email']) ?></a></li><?php endif; ?>
        <?php if ($s['contact']['phone']): ?><li><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $s['contact']['phone'])) ?>"><?= e($s['contact']['phone']) ?></a></li><?php endif; ?>
        <?php if ($s['contact']['address']): ?><li><?= e($s['contact']['address']) ?></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($social): ?>
    <div>
      <strong><?= e(__('Síguenos')) ?></strong>
      <ul class="plain">
        <?php foreach ($social as $k => $u): ?><li><a href="<?= e($u) ?>" rel="noopener me" target="_blank"><?= e($labels[$k] ?? $k) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </div>
  <div class="container footer-bottom">
    <span>&copy; <?= date('Y') ?> <?= e($site['name']) ?><?= $s['footer_text'] ? ' · ' . e($s['footer_text']) : '' ?></span>
    <?= uw_credit() ?>
    <a href="/feed.xml">RSS</a>
  </div>
</footer>
<?= site_code_body_end($svc) ?>
</body>
</html>
