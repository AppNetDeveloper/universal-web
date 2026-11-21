<?php /* Portada trayecto: panel azul marino a todo el ancho con el borde inferior en diagonal; con imagen, foto a la derecha cortada en diagonal; línea de ruta decorativa abajo. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-trayecto<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="hero-panel">
    <?php if ($hasImg): ?>
    <figure class="hero-photo">
      <img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 46vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
    </figure>
    <?php else: ?>
    <div class="hero-art" aria-hidden="true">
      <svg viewBox="0 0 220 360" width="220" height="360" focusable="false"><path d="M20 20l90 160-90 160M90 20l90 160-90 160" fill="none" stroke="currentColor" stroke-width="22" stroke-linejoin="miter"/></svg>
    </div>
    <?php endif; ?>
    <div class="container hero-inner">
      <div class="hero-text">
        <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= uw_icon('route', 'icon icon-sm') ?><span><?= e($d['eyebrow']) ?></span></p><?php endif; ?>
        <h1><?= e($d['title']) ?></h1>
        <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
        <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
        <div class="actions">
          <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"<?= $ext((string) $d['cta_link']) ?>><?= e($d['cta_text']) ?></a><?php endif; ?>
          <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= $ext((string) $d['cta2_link']) ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <svg class="hero-route" viewBox="0 0 1440 150" preserveAspectRatio="xMidYMax slice" aria-hidden="true" focusable="false">
      <path class="route-line" d="M-10 124 C 150 124, 230 86, 400 86 S 640 104, 820 89 S 1100 35, 1250 35 S 1400 28, 1450 25" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round" stroke-dasharray="3 16"/>
      <g class="route-dot"><circle cx="400" cy="86" r="11"/><circle cx="820" cy="89" r="11"/><circle cx="1250" cy="35" r="11"/></g>
      <g class="route-pin"><circle cx="70" cy="121" r="15"/><circle cx="70" cy="121" r="5.5" class="pin-eye"/></g>
    </svg>
  </div>
</section>
