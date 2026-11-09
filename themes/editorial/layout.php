<?php
/**
 * Tema Editorial: despachos, consultoras, abogados, arquitectos y blogs.
 * Cabecera centrada tipo revista (nombre con serifa, eslogan y menú entre filetes), lectura cómoda y pie sobrio.
 */
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#9a3412';
$c = $s['contact'];
$tel = fn(string $p) => 'tel:' . preg_replace('/[^0-9+]/', '', $p);
$social = array_filter(array_diff_key($s['social'], ['whatsapp' => 1]));
$labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok'];
$cta = $s['cta'];
$isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
?><!doctype html>
<html lang="<?= e($s['language']) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className = 'js';</script>
<?= $head ?>
<link rel="preload" href="/theme-assets/editorial/fonts/fraunces-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/default/style.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body class="theme-editorial">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>

<header class="site-header masthead">
  <div class="container masthead-top">
    <span class="masthead-meta"><?= $c['phone'] ? '<a href="' . e($tel($c['phone'])) . '">' . e($c['phone']) . '</a>' : '' ?><?= $c['phone'] && $c['email'] ? ' · ' : '' ?><?= $c['email'] ? '<a href="mailto:' . e($c['email']) . '">' . e($c['email']) . '</a>' : '' ?></span>
    <?php if (!empty($langs)): ?><nav class="lang-switch" aria-label="<?= e(__('Idioma')) ?>"><?php foreach ($langs as [$lc, $ln, $lu, $la]): ?><a href="<?= e($lu) ?>" hreflang="<?= e($lc) ?>" lang="<?= e($lc) ?>"<?= $la ? ' aria-current="true" class="active"' : '' ?> title="<?= e($ln) ?>"><?= e(strtoupper($lc)) ?></a><?php endforeach; ?></nav><?php endif; ?>
  </div>
  <div class="container masthead-brand">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>">
      <?php if ($s['logo']): ?><img src="<?= e($s['logo']) ?>" alt="<?= e($site['name']) ?>" height="56"><?php else: ?><span><?= e($site['name']) ?></span><?php endif; ?>
    </a>
    <?php if ($s['tagline']): ?><p class="masthead-tagline"><?= e($s['tagline']) ?></p><?php endif; ?>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e(__('Abrir menú')) ?>"><span></span><span></span><span></span></button>
  </div>
  <nav id="site-nav" class="site-nav masthead-nav" aria-label="<?= e(__('Principal')) ?>">
    <ul class="container">
      <?php foreach ($s['menu'] as $m): ?>
      <li><a href="<?= e($m['url']) ?>"<?= $isActive((string) $m['url']) ? ' class="active" aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li>
      <?php endforeach; ?>
      <?php if ($cta['label'] && $cta['url']): ?><li class="masthead-cta"><a href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?> →</a></li><?php endif; ?>
    </ul>
  </nav>
</header>

<main id="main" tabindex="-1"><?= $content ?></main>

<footer class="site-footer">
  <div class="container footer-grid">
    <div>
      <p class="footer-name"><?= e($site['name']) ?></p>
      <?php if ($s['tagline']): ?><p class="muted"><?= e($s['tagline']) ?></p><?php endif; ?>
    </div>
    <nav aria-label="<?= e(__('Pie de página')) ?>">
      <h2 class="footer-title"><?= e(__('Navegación')) ?></h2>
      <ul class="plain"><?php foreach ($s['menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"><?= e($m['label']) ?></a></li><?php endforeach; ?>
        <?php foreach ($s['utility_menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"<?= preg_match('#^https?://#', $m['url']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?></ul>
    </nav>
    <?php if ($c['phone'] || $c['email'] || $c['address'] || $social): ?>
    <div>
      <h2 class="footer-title"><?= e(__('Contacto')) ?></h2>
      <ul class="plain">
        <?php if ($c['address']): ?><li><?= e($c['address']) ?></li><?php endif; ?>
        <?php if ($c['phone']): ?><li><a href="<?= e($tel($c['phone'])) ?>"><?= e($c['phone']) ?></a></li><?php endif; ?>
        <?php if ($c['email']): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
        <?php foreach ($social as $k => $u): ?><li><a href="<?= e($u) ?>" target="_blank" rel="noopener me"><?= e($labels[$k] ?? $k) ?></a></li><?php endforeach; ?>
      </ul>
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
