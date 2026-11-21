<?php /* Portada sutil: composición centrada. Con imagen, una cápsula vertical a cada lado (la misma foto recortada distinta); sin imagen, círculos suaves detrás. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-sutil<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <?php if (!$hasImg): ?><div class="orbs" aria-hidden="true"><i></i><i></i><i></i></div><?php endif; ?>
  <div class="container hero-stage">
    <?php if ($hasImg): ?>
    <figure class="hero-cap hero-cap-l"><img<?= img_attrs($d['image'], '(max-width: 900px) 78vw, 260px') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"></figure>
    <?php endif; ?>
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="eyebrow"><span><?= e($d['eyebrow']) ?></span></p><?php endif; ?>
      <h1><?= e($d['title']) ?></h1>
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"<?= $ext((string) $d['cta_link']) ?>><?= e($d['cta_text']) ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= $ext((string) $d['cta2_link']) ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($hasImg): ?>
    <figure class="hero-cap hero-cap-r" aria-hidden="true"><img<?= img_attrs($d['image'], '260px') ?> alt="" loading="lazy" decoding="async"></figure>
    <?php endif; ?>
  </div>
</section>
