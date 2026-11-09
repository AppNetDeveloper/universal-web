<section class="section block-features">
  <div class="container">
    <?php include theme_file($site['theme'], 'blocks/_head.php'); ?>
    <div class="grid cards">
      <?php foreach ($d['items'] as $it): $tag = $it['link'] !== '' ? 'a' : 'div'; ?>
      <<?= $tag ?> class="card feature reveal"<?= $tag === 'a' ? ' href="' . e($it['link']) . '"' : '' ?>>
        <?php if ($it['icon']): ?><span class="feature-icon"><?= uw_icon($it['icon']) ?></span><?php endif; ?>
        <?php if ($it['title']): ?><h3><?= e($it['title']) ?></h3><?php endif; ?>
        <?php if ($it['text']): ?><p><?= e($it['text']) ?></p><?php endif; ?>
        <?php if ($tag === 'a'): ?><span class="more"><?= e(__('Saber más')) ?> <?= uw_icon('arrow', 'icon icon-sm') ?></span><?php endif; ?>
      </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
