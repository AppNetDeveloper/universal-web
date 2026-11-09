<?php
/**
 * Tema Natural: salud, bienestar, ecológico y agricultura.
 * Cabecera cálida con teléfono visible, formas orgánicas redondeadas y pie con curva superior.
 */
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#3d7a55';
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
<script>document.documentElement.className = 'js';</script>
<?= $head ?>
<link rel="preload" href="/theme-assets/natural/fonts/source-sans-3-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/default/style.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<meta name="theme-color" content="#fbfaf5">
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body class="theme-natural">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>">
      <?php if ($s['logo']): ?><img src="<?= e($s['logo']) ?>" alt="<?= e($site['name']) ?>" height="40"><?php else: ?><svg class="brand-leaf" viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M27 4C13 4 5 11 5 20c0 2 .4 3.800 1.200 5.300C8.500 22 12 19 17 17c-4 3-6.500 6-8 9.500 1.700 1 3.600 1.500 5.800 1.500C23 28 28 18 27 4z"/></svg><span><?= e($site['name']) ?></span><?php endif; ?>
    </a>
    <nav id="site-nav" class="site-nav" aria-label="<?= e(__('Principal')) ?>">
      <ul>
        <?php foreach ($s['menu'] as $m): ?>
        <li><a href="<?= e($m['url']) ?>"<?= $isActive((string) $m['url']) ? ' class="active" aria-current="page"' : '' ?>><?= e($m['label']) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </nav>
    <div class="header-actions">
      <?php if ($tel !== ''): ?><a class="header-phone" href="tel:<?= e($tel) ?>" aria-label="<?= e(__('Contacto')) ?>: <?= e($c['phone']) ?>"><svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M6.600 10.800a15.100 15.100 0 0 0 6.600 6.600l2.200-2.200a1 1 0 0 1 1-.25c1.100.37 2.300.57 3.600.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.500a1 1 0 0 1 1 1c0 1.300.2 2.500.57 3.600a1 1 0 0 1-.25 1z"/></svg><span class="header-phone-num"><?= e($c['phone']) ?></span></a><?php endif; ?>
      <?php if (!empty($langs)): ?><nav class="lang-switch" aria-label="<?= e(__('Idioma')) ?>"><?php foreach ($langs as [$lc, $ln, $lu, $la]): ?><a href="<?= e($lu) ?>" hreflang="<?= e($lc) ?>" lang="<?= e($lc) ?>"<?= $la ? ' aria-current="true" class="active"' : '' ?> title="<?= e($ln) ?>"><?= e(strtoupper($lc)) ?></a><?php endforeach; ?></nav><?php endif; ?>
      <?php if ($cta['label'] && $cta['url']): ?><a class="btn btn-sm header-cta" href="<?= e($cta['url']) ?>"><?= e($cta['label']) ?></a><?php endif; ?>
      <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="site-nav" aria-label="<?= e(__('Abrir menú')) ?>"><span></span><span></span><span></span></button>
    </div>
  </div>
</header>

<main id="main" tabindex="-1"><?= $content ?></main>

<footer class="site-footer">
  <svg class="footer-wave" viewBox="0 0 1200 60" preserveAspectRatio="none" aria-hidden="true"><path d="M0 60V28C150 -4 300 -4 450 24s300 36 450 8 220-30 300-6v36z" fill="currentColor"/></svg>
  <div class="container footer-grid">
    <div>
      <strong class="footer-name"><?= e($site['name']) ?></strong>
      <?php if ($s['tagline']): ?><p><?= e($s['tagline']) ?></p><?php endif; ?>
    </div>
    <nav aria-label="<?= e(__('Navegación')) ?>">
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
    <?php if ($social || $s['utility_menu']): ?>
    <div>
      <?php if ($social): ?>
      <strong><?= e(__('Síguenos')) ?></strong>
      <ul class="plain">
        <?php foreach ($social as $k => $u): ?><li><a href="<?= e($u) ?>" target="_blank" rel="noopener me"><?= e($labels[$k] ?? $k) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
      <?php if ($s['utility_menu']): ?>
      <strong><?= e(__('Accesos')) ?></strong>
      <ul class="plain">
        <?php foreach ($s['utility_menu'] as $m): ?><li><a href="<?= e($m['url']) ?>"<?= preg_match('#^https?://#', $m['url']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($m['label']) ?></a></li><?php endforeach; ?>
      </ul>
      <?php endif; ?>
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
  <svg viewBox="0 0 32 32" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M16 3a13 13 0 0 0-11.200 19.600L3 29l6.600-1.700A13 13 0 1 0 16 3zm0 23.600c-2 0-4-.6-5.700-1.600l-.4-.2-3.900 1 1-3.800-.3-.4A10.600 10.600 0 1 1 16 26.600zm5.800-7.900c-.3-.2-1.900-.9-2.200-1s-.5-.2-.7.200-.8 1-1 1.200-.4.200-.7 0a8.700 8.700 0 0 1-4.300-3.800c-.3-.6.3-.5.900-1.700.1-.2 0-.4 0-.5l-1-2.400c-.3-.6-.5-.5-.7-.5h-.6a1.200 1.200 0 0 0-.9.400 3.600 3.600 0 0 0-1.100 2.700 6.300 6.300 0 0 0 1.300 3.300 14.400 14.400 0 0 0 5.500 4.900c2 .9 2.800 1 3.900.8a3.300 3.300 0 0 0 2.100-1.500 2.700 2.700 0 0 0 .2-1.500c-.1-.2-.3-.3-.5-.4z"/></svg>
</a>
<?php endif; ?>
<?= site_code_body_end($svc) ?>
</body>
</html>
