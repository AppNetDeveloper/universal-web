<?php
/**
 * Tema Prestigio: lujo discreto (inmobiliaria premium, hoteles boutique, bodegas, joyerías, bodas, interiorismo).
 * Marfil, detalles bronce, serifa fina en mayúsculas espaciadas, fotos a sangre y filetes finos.
 */
$accent = preg_match('/^#[0-9a-fA-F]{3,8}$/', (string) $s['accent']) ? $s['accent'] : '#8a6a34';
$c = $s['contact'];
$social = array_filter(array_diff_key($s['social'], ['whatsapp' => 1]));
$labels = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok'];
$whatsapp = (string) ($s['social']['whatsapp'] ?? '');
$cta = $s['cta'];
$tel = fn(string $p) => 'tel:' . preg_replace('/[^\d+]/', '', $p);
$isActive = fn(string $u) => rtrim($u, '/') === rtrim($path, '/') || ($u !== '/' && $u !== '' && str_starts_with($path, rtrim($u, '/') . '/'));
?><!doctype html>
<html lang="<?= e($s['language']) ?>" class="no-js">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<script>document.documentElement.className = 'js';</script>
<?= $head ?>
<link rel="preload" href="/theme-assets/prestigio/fonts/cormorant-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
<link rel="stylesheet" href="<?= e(asset_v('/theme-assets/default/style.css')) ?>">
<link rel="stylesheet" href="<?= e(theme_asset($site, 'style.css')) ?>">
<style>:root{--accent:<?= e($accent) ?>}</style>
<meta name="theme-color" content="#fbf8f1">
<script src="<?= e(theme_asset($site, 'site.js')) ?>" defer></script>
<?= site_code_head($svc) ?>
</head>
<body class="theme-prestigio">
<a class="skip" href="#main"><?= e(__('Saltar al contenido')) ?></a>
<header class="site-header">
  <div class="container header-inner">
    <a class="brand" href="<?= e($svc->langPath('/')) ?>">
      <?php if ($s['logo']): ?><img src="<?= e($s['logo']) ?>" alt="<?= e($site['name']) ?>" height="40"><?php else: ?><span><?= e($site['name']) ?></span><?php endif; ?>
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
  <div class="container footer-top">
    <a class="brand brand-footer" href="<?= e($svc->langPath('/')) ?>"><span><?= e($site['name']) ?></span></a>
    <?php if ($s['tagline']): ?><p class="footer-tagline"><?= e($s['tagline']) ?></p><?php endif; ?>
    <span class="hero-orn" aria-hidden="true"><svg viewBox="0 0 120 12" width="120" height="12" focusable="false"><path d="M0 6h50M70 6h50" stroke="currentColor" stroke-width="1" fill="none"/><path d="M60 1l5 5-5 5-5-5z" fill="none" stroke="currentColor" stroke-width="1"/></svg></span>
  </div>
  <div class="container footer-grid">
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
      <ul class="plain">
        <?php if ($c['address']): ?><li><?= e($c['address']) ?></li><?php endif; ?>
        <?php if ($c['phone']): ?><li><a href="<?= e($tel($c['phone'])) ?>"><?= e($c['phone']) ?></a></li><?php endif; ?>
        <?php if ($c['email']): ?><li><a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
      </ul>
    </div>
    <?php endif; ?>
    <?php if ($social): ?>
    <div>
      <h2 class="footer-title"><?= e(__('Síguenos')) ?></h2>
      <ul class="plain">
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

<?php if ($whatsapp !== '' && preg_match('#^https://(wa\.me|api\.whatsapp\.com)/#', $whatsapp)): ?>
<a class="whatsapp-float" href="<?= e($whatsapp) ?>" target="_blank" rel="noopener" aria-label="<?= e(__('Escríbenos por WhatsApp')) ?>">
  <svg viewBox="0 0 32 32" width="26" height="26" aria-hidden="true"><path fill="currentColor" d="M16 3a13 13 0 0 0-11.2 19.6L3 29l6.6-1.7A13 13 0 1 0 16 3zm0 23.6c-2 0-4-.6-5.7-1.6l-.4-.2-3.9 1 1-3.8-.3-.4A10.6 10.6 0 1 1 16 26.6zm5.8-7.9c-.3-.2-1.9-.9-2.2-1s-.5-.2-.7.2-.8 1-1 1.2-.4.2-.7 0a8.7 8.7 0 0 1-4.3-3.8c-.3-.6.3-.5.9-1.7.1-.2 0-.4 0-.5l-1-2.4c-.3-.6-.5-.5-.7-.5h-.6a1.2 1.2 0 0 0-.9.4 3.6 3.6 0 0 0-1.1 2.7 6.3 6.3 0 0 0 1.3 3.3 14.4 14.4 0 0 0 5.5 4.9c2 .9 2.8 1 3.9.8a3.3 3.3 0 0 0 2.1-1.5 2.7 2.7 0 0 0 .2-1.5c-.1-.2-.3-.3-.5-.4z"/></svg>
</a>
<?php endif; ?>
<?= site_code_body_end($svc) ?>
</body>
</html>
