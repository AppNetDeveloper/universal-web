<?php /* Portada lienzo: titular gigante a todo el ancho; debajo, a la derecha, subtítulo y enlaces subrayados; imagen a sangre sin esquinas. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-lienzo<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container hero-text">
    <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
    <h1><?= e($d['title']) ?></h1>
    <?php if ($d['subtitle'] || ($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
    <div class="hero-side">
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="hero-link hero-link-main" href="<?= e($d['cta_link']) ?>"<?= $ext((string) $d['cta_link']) ?>><span><?= e($d['cta_text']) ?></span><?= uw_icon('arrow', 'icon icon-sm') ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="hero-link" href="<?= e($d['cta2_link']) ?>"<?= $ext((string) $d['cta2_link']) ?>><span><?= e($d['cta2_text']) ?></span><?= uw_icon('arrow', 'icon icon-sm') ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <?php if ($hasImg): ?>
  <figure class="hero-photo">
    <img<?= img_attrs($d['image'], '100vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
  </figure>
  <?php endif; ?>
</section>
