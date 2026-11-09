<?php
$q = fn(int $n) => $svc->langPath('/blog') . '?' . http_build_query(array_filter(['tag' => $tag, 'p' => $n > 1 ? $n : null]));
?>
<div class="container page-head">
  <h1>Blog</h1>
  <?php if ($tag): ?><p class="meta"><?= e(__('Etiqueta')) ?>: <span class="tag">#<?= e($tag) ?></span> · <a href="<?= e($svc->langPath('/blog')) ?>"><?= e(__('Ver todas')) ?></a></p><?php endif; ?>
</div>
<section class="container section-tight">
  <?php if (!$posts): ?>
    <p class="empty"><?= e(__('Todavía no hay entradas.')) ?></p>
  <?php else: ?>
    <div class="grid cards">
      <?php foreach ($posts as $p) include __DIR__ . '/partials_card.php'; ?>
    </div>
  <?php endif; ?>
  <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="<?= e(__('Paginación')) ?>">
      <?php if ($page > 1): ?><a href="<?= e($q($page - 1)) ?>" rel="prev">&larr; <?= e(__('Anterior')) ?></a><?php endif; ?>
      <span><?= e(__('Página {n} de {total}', ['n' => (int) $page, 'total' => (int) $pages])) ?></span>
      <?php if ($page < $pages): ?><a href="<?= e($q($page + 1)) ?>" rel="next"><?= e(__('Siguiente')) ?> &rarr;</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>
