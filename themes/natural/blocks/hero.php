<?php /* Portada natural: texto sereno y, si hay imagen, foto en forma de arco con manchas orgánicas detrás. */
$hasImg = !empty($d['image']); ?>
<section class="hero hero-natural<?= $hasImg ? ' has-image' : '' ?>">
  <span class="blob blob-1" aria-hidden="true"></span>
  <span class="blob blob-2" aria-hidden="true"></span>
  <div class="container hero-inner">
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h1><?= e($d['title']) ?></h1>
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"><?= e($d['cta_text']) ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= preg_match('#^https?://#', $d['cta2_link']) ? ' target="_blank" rel="noopener"' : '' ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($hasImg): ?>
    <figure class="hero-arch"><img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 50vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"></figure>
    <?php endif; ?>
  </div>
</section>
