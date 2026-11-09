<?php /* Portada a pantalla completa: foto de fondo con velo de marca, o degradado si no hay imagen. */ ?>
<section class="hero<?= $d['image'] ? ' hero-photo' : ' hero-plain' ?>">
  <?php if ($d['image']): ?><img class="hero-bg"<?= img_attrs($d['image'], '100vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"><?php endif; ?>
  <div class="container hero-inner">
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="hero-eyebrow"><span class="dot" aria-hidden="true"></span><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h1><?= e($d['title']) ?></h1>
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"><?= e($d['cta_text']) ?> <?= uw_icon('arrow', 'icon icon-sm') ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-outline-light" href="<?= e($d['cta2_link']) ?>"<?= preg_match('#^https?://#', $d['cta2_link']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
