<?php /* Portada horizonte: con imagen, foto a sangre con velo y un panel que solapa su borde inferior; sin imagen, panel sobre arena con líneas de horizonte. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-horizonte<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <?php if ($hasImg): ?>
  <figure class="hero-photo">
    <img<?= img_attrs($d['image'], '100vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
  </figure>
  <?php else: ?>
  <svg class="hero-horizon" viewBox="0 0 1200 260" preserveAspectRatio="none" aria-hidden="true" focusable="false"><circle cx="930" cy="150" r="54" fill="currentColor" opacity=".35"/><g fill="none" stroke="currentColor" stroke-width="2"><path d="M0 170h1200" opacity=".9"/><path d="M0 192h1200" opacity=".6"/><path d="M0 216h1200" opacity=".4"/><path d="M0 240h1200" opacity=".25"/></g></svg>
  <?php endif; ?>
  <div class="container">
    <div class="hero-panel">
      <div class="hero-head">
        <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
        <h1><?= e($d['title']) ?></h1>
      </div>
      <div class="hero-side">
        <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
        <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
        <div class="actions">
          <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"<?= $ext((string) $d['cta_link']) ?>><?= e($d['cta_text']) ?></a><?php endif; ?>
          <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= $ext((string) $d['cta2_link']) ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
