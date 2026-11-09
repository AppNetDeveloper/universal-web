<section class="section block-cards">
  <div class="container">
    <?php include theme_file($site['theme'], 'blocks/_head.php'); ?>
    <div class="grid cards">
      <?php foreach ($d['items'] as $it): $tag = $it['link'] !== '' ? 'a' : 'article'; ?>
      <<?= $tag ?> class="card media-card reveal"<?= $tag === 'a' ? ' href="' . e($it['link']) . '"' : '' ?>>
        <?php if ($it['image']): ?><div class="media-card-img"><img<?= img_attrs($it['image'], '(max-width: 700px) 100vw, 33vw') ?> alt="<?= e($it['alt'] ?: $it['title']) ?>" loading="lazy" decoding="async">
          <?php if ($it['tag']): ?><span class="chip"><?= e($it['tag']) ?></span><?php endif; ?></div><?php endif; ?>
        <div class="card-body">
          <?php if ($it['title']): ?><h3><?= e($it['title']) ?></h3><?php endif; ?>
          <?php if ($it['text']): ?><p><?= e($it['text']) ?></p><?php endif; ?>
        </div>
      </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
