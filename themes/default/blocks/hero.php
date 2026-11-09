<section class="hero<?= $d['image'] ? ' has-image' : '' ?>">
  <div class="container hero-inner">
    <div class="hero-text">
      <?php if ($d['eyebrow'] ?? ''): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h1><?= e($d['title']) ?></h1>
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn" href="<?= e($d['cta_link']) ?>"><?= e($d['cta_text']) ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-ghost" href="<?= e($d['cta2_link']) ?>"><?= e($d['cta2_text']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($d['image']): ?>
    <div class="hero-media"><img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 50vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"></div>
    <?php endif; ?>
  </div>
</section>
