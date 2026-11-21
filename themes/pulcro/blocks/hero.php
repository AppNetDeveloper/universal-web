<?php /* Portada pulcro: panel redondeado de color suave; con imagen, foto a la derecha con una ficha flotante del teléfono. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
$phone = (string) ($svc->settings()['contact']['phone'] ?? '');
?>
<section class="hero hero-pulcro<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container">
    <div class="hero-panel">
      <div class="hero-text">
        <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= uw_icon('shield', 'icon icon-sm') ?><span><?= e($d['eyebrow']) ?></span></p><?php endif; ?>
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
      <figure class="hero-photo">
        <img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 46vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
        <?php if ($phone !== ''): ?><a class="hero-badge" href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><span class="hero-badge-icon"><?= uw_icon('phone') ?></span><span><small><?= e(__('Teléfono')) ?></small><strong><?= e($phone) ?></strong></span></a><?php endif; ?>
      </figure>
      <?php else: ?>
      <svg class="hero-cross" viewBox="0 0 200 200" width="200" height="200" aria-hidden="true" focusable="false"><path d="M75 10h50v65h65v50h-65v65H75v-65H10V75h65z" fill="currentColor"/></svg>
      <?php endif; ?>
    </div>
  </div>
</section>
