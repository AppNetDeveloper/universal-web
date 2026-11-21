<?php /* Portada sobrio: titular a 8 columnas y ficha de contacto a 4, separada por filetes; la imagen, debajo como banda panorámica. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
$ct = $svc->settings()['contact'] ?? [];
$phone = (string) ($ct['phone'] ?? '');
$mail = (string) ($ct['email'] ?? '');
$addr = (string) ($ct['address'] ?? '');
$hasFicha = $phone !== '' || $mail !== '' || $addr !== '';
?>
<section class="hero hero-sobrio<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container">
    <div class="hero-grid<?= $hasFicha ? ' has-ficha' : '' ?>">
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
      <?php if ($hasFicha): ?>
      <aside class="hero-ficha" aria-label="<?= e(__('Contacto')) ?>">
        <dl>
          <?php if ($phone !== ''): ?><div><dt><?= e(__('Teléfono')) ?></dt><dd><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $phone)) ?>"><?= e($phone) ?></a></dd></div><?php endif; ?>
          <?php if ($mail !== ''): ?><div><dt><?= e(__('Email')) ?></dt><dd><a href="mailto:<?= e($mail) ?>"><?= e($mail) ?></a></dd></div><?php endif; ?>
          <?php if ($addr !== ''): ?><div><dt><?= e(__('Dirección')) ?></dt><dd><?= e($addr) ?></dd></div><?php endif; ?>
        </dl>
      </aside>
      <?php endif; ?>
    </div>
    <?php if ($hasImg): ?>
    <figure class="hero-band">
      <img<?= img_attrs($d['image'], '(max-width: 1240px) 100vw, 1240px') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async">
    </figure>
    <?php endif; ?>
  </div>
</section>
