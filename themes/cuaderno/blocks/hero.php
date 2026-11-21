<?php /* Portada cuaderno: papel cuadriculado, antetítulo a mano con flecha, última parte del título subrayada con rotulador y foto pegada con cinta. */
$hasImg = !empty($d['image']);
$ext = fn(string $u) => preg_match('#^https?://#', $u) ? ' target="_blank" rel="noopener"' : '';
$words = preg_split('/\s+/u', trim((string) $d['title']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
$n = count($words);
$k = $n >= 7 ? 3 : ($n >= 3 ? 2 : 1);
$head = implode(' ', array_slice($words, 0, $n - $k));
$mark = implode(' ', array_slice($words, $n - $k));
?>
<section class="hero hero-cuaderno<?= $hasImg ? ' has-image' : ' hero-plain' ?>">
  <div class="container hero-inner">
    <div class="hero-text">
      <?php if ($d['eyebrow']): ?><p class="eyebrow eyebrow-hand"><span><?= e($d['eyebrow']) ?></span><svg class="doodle-arrow" viewBox="0 0 64 52" width="56" height="46" aria-hidden="true" focusable="false"><path d="M54 4c4 20-12 33-40 36" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round"/><path d="M25 29 11 40l16 8" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"/></svg></p><?php endif; ?>
      <h1><?= $head !== '' ? e($head) . ' ' : '' ?><span class="marker"><?= e($mark) ?></span></h1>
      <?php if ($d['subtitle']): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
      <?php if (($d['cta_text'] && $d['cta_link']) || ($d['cta2_text'] && $d['cta2_link'])): ?>
      <div class="actions">
        <?php if ($d['cta_text'] && $d['cta_link']): ?><a class="btn btn-lg" href="<?= e($d['cta_link']) ?>"<?= $ext((string) $d['cta_link']) ?>><?= e($d['cta_text']) ?></a><?php endif; ?>
        <?php if ($d['cta2_text'] && $d['cta2_link']): ?><a class="btn btn-lg btn-ghost" href="<?= e($d['cta2_link']) ?>"<?= $ext((string) $d['cta2_link']) ?>><?= e($d['cta2_text']) ?></a><?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php if ($hasImg): ?>
    <figure class="hero-photo"><img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 46vw') ?> alt="<?= e($d['image_alt']) ?>" fetchpriority="high" decoding="async"></figure>
    <?php else: ?>
    <svg class="hero-pencil" viewBox="0 0 120 320" width="120" height="320" aria-hidden="true" focusable="false"><path d="M40 20h40v200H40z" fill="currentColor" opacity=".55"/><path d="M40 20a20 14 0 0 1 40 0z" fill="currentColor" opacity=".9"/><path d="M40 220h40L60 300z" fill="currentColor" opacity=".3"/><path d="M52 276h16L60 300z" fill="currentColor"/><path d="M52 20v200M68 20v200" stroke="#fff" stroke-opacity=".35" stroke-width="2"/></svg>
    <?php endif; ?>
  </div>
</section>
