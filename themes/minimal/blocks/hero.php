<?php /* Portada minimal: titular centrado con degradado y, si hay imagen, captura enmarcada debajo. */ ?>
<section class="hero hero-minimal">
  <div class="container hero-min-text">
    <?php if ($d['eyebrow']): ?><p class="pill"><span class="pill-dot" aria-hidden="true"></span><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <h1><?= e($d['title']) ?></h1>
    <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
    <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
    <div class="actions">
      <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"><?= e($d['cta_text']) ?></a><?php endif; ?>
      <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= preg_match('#^https?://#', $d['cta2_link']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($d['cta2_text']) ?> →</a><?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($d['image']): ?>
  <div class="container"><figure class="hero-shot"><img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 50vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"></figure></div>
  <?php endif; ?>
</section>
