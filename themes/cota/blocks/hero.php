<?php /* Portada Cota: titular a la izquierda sobre rejilla de plano; a la derecha la foto con marcas de corte y pie en mono (o una planta dibujada). */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
?>
<section class="hero hero-cota<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container hero-grid">
    <div class="hero-text">
      <?php if (!$hasImg && $d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
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
      <div class="crop"><img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 46vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"></div>
      <?php if ($d['eyebrow']): ?><figcaption><?= e($d['eyebrow']) ?></figcaption><?php endif; ?>
    </figure>
    <?php else: ?>
    <svg class="hero-plan" viewBox="0 0 360 290" width="360" height="290" aria-hidden="true" focusable="false">
      <g class="p-thin">
        <path d="M40 22H320M40 15V29M320 15V29M36 26L44 18M316 26L324 18"/>
        <path d="M18 40V240M11 40H25M11 240H25M14 244L22 236M14 44L22 36"/>
        <path d="M40 262H190M40 255V269M190 255V269M36 266L44 258M186 266L194 258"/>
        <path d="M190 100A35 35 0 0 0 155 135M190 135H155"/>
        <path d="M245 150A40 40 0 0 0 285 190M285 150V190"/>
        <path d="M85 150A35 35 0 0 0 120 185M120 150V185"/>
        <path d="M70 240A40 40 0 0 1 110 200M110 240V200"/>
        <path d="M220 40H280M220 36V44M280 36V44"/>
        <rect x="212" y="168" width="72" height="30"/><rect x="48" y="48" width="64" height="22"/><circle cx="95" cy="110" r="16"/>
        <path d="M200 205V235M230 205V235M260 205V235"/>
      </g>
      <path class="p-wall" d="M40 40H220M280 40H320V240H110M70 240H40V40M190 40V100M190 135V150H245M285 150H320M40 150H85M120 150H190"/>
      <g class="p-text"><text x="180" y="12" text-anchor="middle">5.600</text><text x="6" y="140" text-anchor="middle" transform="rotate(-90 6 140)">4.000</text><text x="115" y="284" text-anchor="middle">1:50</text></g>
    </svg>
    <?php endif; ?>
  </div>
</section>
