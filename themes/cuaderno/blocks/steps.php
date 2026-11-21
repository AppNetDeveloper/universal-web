<section class="section block-steps">
  <div class="container">
    <?php include theme_file($site['theme'], 'blocks/_head.php'); ?>
    <ol class="steps">
      <?php foreach ($d['items'] as $i => $it): ?>
      <li class="step reveal">
        <span class="step-num" aria-hidden="true"><?= $i + 1 ?></span>
        <h3><?= e($it['title']) ?></h3>
        <?php if ($it['text']): ?><p><?= e($it['text']) ?></p><?php endif; ?>
      </li>
      <?php endforeach; ?>
    </ol>
  </div>
</section>
