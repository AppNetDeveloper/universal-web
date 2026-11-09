<?php /** @var array $items */ ?>
<div class="page-head"><h1><?= te('Papelera') ?></h1></div>
<p class="muted"><?= te('Páginas y entradas borradas en los últimos 30 días, por el panel o por la IA. Al recuperarlas vuelven con su contenido y su estado.') ?></p>
<?php if (!$items): ?>
  <div class="empty"><p><?= te('La papelera está vacía.') ?></p></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Tipo') ?></th><th><?= te('Título') ?></th><th><?= te('Slug') ?></th><th><?= te('Borrada') ?></th><th><?= te('Por') ?></th><th></th></tr></thead>
  <tbody><?php foreach ($items as $t): ?>
    <tr><td><?= adm_badge($t['kind'] === 'page' ? t('Página') : t('Entrada'), 'gray') ?></td><td><b><?= e($t['title']) ?></b></td>
      <td><code><?= e($t['lang']) ?>/<?= e($t['slug']) ?></code></td><td><?= e(adm_date($t['deleted_at'])) ?></td><td><?= e($t['by']) ?></td>
      <td><form method="post" action="<?= ADM ?>/trash/restore?id=<?= (int) $t['trash_id'] ?>"><?= csrf_field() ?><button class="btn btn-sm"><?= te('Recuperar') ?></button></form></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
