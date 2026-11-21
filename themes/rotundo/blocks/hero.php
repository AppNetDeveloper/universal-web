<?php /* Portada rotundo: titular gigante con rotulador amarillo, eyebrow como pegatina y, si hay eyebrow, cinta bajo la portada. Con imagen: marco grueso girado; sin imagen: formas decorativas. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-rotundo<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container hero-inner">
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= uw_icon('star', 'icon icon-sm') ?><span><?= e($d['eyebrow']) ?></span></p><?php endif; ?>
      <h1><span class="mark"><?= e($d['title']) ?></span></h1>
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
    </figure>
    <?php else: ?>
    <svg class="hero-art" viewBox="0 0 380 340" width="380" height="340" aria-hidden="true" focusable="false">
      <g stroke="currentColor" stroke-width="3" stroke-linejoin="round">
        <rect class="art-mint" x="34" y="74" width="190" height="190" rx="16" transform="rotate(-8 129 169)"/>
        <circle class="art-yellow" cx="260" cy="113" r="78"/>
        <polygon class="art-accent" points="250,146 267.1,166.2 292,157.3 296.7,183.3 322.7,188 313.8,212.9 334,230 313.8,247.1 322.7,272 296.7,276.7 292,302.7 267.1,293.8 250,314 232.9,293.8 208,302.7 203.3,276.7 177.3,272 186.2,247.1 166,230 186.2,212.9 177.3,188 203.3,183.3 208,157.3 232.9,166.2"/>
      </g>
    </svg>
    <?php endif; ?>
  </div>
</section>
<?php if ($d['eyebrow']): ?>
<div class="ribbon-wrap" aria-hidden="true"><div class="ribbon"><?php for ($i = 0; $i < 8; $i++): ?><span><?= e($d['eyebrow']) ?></span><?= uw_icon('star', 'icon icon-sm') ?><?php endfor; ?></div></div>
<?php endif; ?>
