<?php if ($d['src']): ?>
<section class="section-tight">
  <figure class="container figure">
    <img<?= img_attrs($d['src'], '(max-width: 1100px) 100vw, 1100px') ?> alt="<?= e($d['alt']) ?>" loading="lazy" decoding="async">
    <?php if ($d['caption']): ?><figcaption><?= e($d['caption']) ?></figcaption><?php endif; ?>
  </figure>
</section>
<?php endif; ?>
