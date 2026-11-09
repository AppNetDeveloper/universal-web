<section class="section">
  <div class="container narrow">
    <?php if ($d['title']): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
    <div class="prose"><?= md($d['body']) ?></div>
  </div>
</section>
