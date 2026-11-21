<?php /* Portada orbital: centrada, antetítulo mono con «//», y la imagen dentro de un marco de ventana con brillo. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-orbital<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container">
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
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
    <figure class="hero-window">
      <div class="window-bar" aria-hidden="true"><i></i><i></i><i></i></div>
      <img<?= img_attrs($d['image'], '(max-width: 1100px) 100vw, 1000px') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
    </figure>
    <?php endif; ?>
  </div>
</section>
