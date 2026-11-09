<?php $reverse = uw_truthy($d['reverse']); ?>
<section class="section block-split">
  <div class="container split<?= $reverse ? ' split-reverse' : '' ?><?= $d['image'] ? '' : ' split-noimg' ?>">
    <?php if ($d['image']): ?>
    <figure class="split-media reveal"><img<?= img_attrs($d['image'], '(max-width: 900px) 100vw, 50vw') ?> alt="<?= e($d['image_alt']) ?>" loading="lazy" decoding="async"></figure>
    <?php endif; ?>
    <div class="split-text reveal">
      <?php if ($d['eyebrow']): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
      <?php if ($d['title']): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
      <?php if ($d['body']): ?><div class="prose checklist"><?= md($d['body']) ?></div><?php endif; ?>
      <?php if ($d['button_text'] && $d['button_link']): ?><p><a class="btn" href="<?= e($d['button_link']) ?>"><?= e($d['button_text']) ?></a></p><?php endif; ?>
    </div>
  </div>
</section>
