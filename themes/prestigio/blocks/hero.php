<?php /* Portada prestigio: con imagen, foto a pantalla completa con velo suave; sin imagen, composición tipográfica sobre marfil. */
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
$hasCta = ($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link']);
?>
<section class="hero hero-prestigio <?= $d['image'] ? 'has-image' : 'hero-plain' ?>">
  <?php if ($d['image']): ?>
  <img class="hero-bg"<?= img_attrs($d['image'], '100vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
  <?php endif; ?>
  <div class="container hero-inner">
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <h1><?= e($d['title']) ?></h1>
      <span class="hero-orn" aria-hidden="true"><svg viewBox="0 0 120 12" width="120" height="12" focusable="false"><path d="M0 6h50M70 6h50" stroke="currentColor" stroke-width="1" fill="none"/><path d="M60 1l5 5-5 5-5-5z" fill="none" stroke="currentColor" stroke-width="1"/></svg></span>
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if ($hasCta): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn" href="<?= e($d['cta_link']) ?>"<?= $ext((string) $d['cta_link']) ?>><?= e($d['cta_text']) ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= $ext((string) $d['cta2_link']) ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
