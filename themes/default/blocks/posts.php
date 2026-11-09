<?php $list = $svc->posts(['public' => true, 'limit' => max(1, (int) $d['count'] ?: 3)]); ?>
<section class="section">
  <div class="container">
    <div class="section-head">
      <h2><?= e($d['title'] ?: 'Últimas entradas') ?></h2>
    </div>
    <?php if (!$list): ?>
      <p class="empty"><?= e(__('Todavía no hay entradas.')) ?></p>
    <?php else: ?>
      <div class="grid cards">
        <?php foreach ($list as $p) include dirname(__DIR__) . '/partials_card.php'; ?>
      </div>
    <?php endif; ?>
    <p class="more"><a href="<?= e($svc->langPath('/blog')) ?>"><?= e(__('Ver todo el blog')) ?>&nbsp;&rarr;</a></p>
  </div>
</section>
