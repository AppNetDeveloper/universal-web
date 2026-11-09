<?php /** @var SiteService $svc @var string $histKind @var array $histItem — historial de versiones de una página o entrada */
if (empty($histItem['id'])) return;
$versions = history_list($svc, $histKind, $histItem['slug'], $histItem['lang']);
if (!$versions) return; ?>
<section class="card">
  <details>
    <summary><b><?= te('Historial') ?></b> <span class="muted"><?= te('{n} versión(es) anteriores. Restaurar no borra nada: la versión actual pasa al historial.', ['n' => count($versions)]) ?></span></summary>
    <div class="table-wrap"><table>
      <thead><tr><th><?= te('Guardada') ?></th><th><?= te('Por') ?></th><th><?= te('Título') ?></th><th><?= te('Contenido') ?></th><th></th></tr></thead>
      <tbody><?php foreach ($versions as $v): ?>
        <tr><td><?= e(adm_date($v['saved_at'])) ?></td><td><?= e($v['by']) ?></td><td><?= e($v['title']) ?> <?= adm_badge($v['status'] === 'published' ? t('Publicada') : t('Borrador'), $v['status'] === 'published' ? 'green' : 'gray') ?></td>
          <td class="muted"><?= e($v['summary']) ?></td>
          <td><form method="post" action="<?= ADM ?>/<?= $histKind === 'page' ? 'pages' : 'posts' ?>/restore?id=<?= (int) $histItem['id'] ?>" data-confirm="<?= te('¿Volver a esta versión?') ?>"><?= csrf_field() ?><input type="hidden" name="version" value="<?= (int) $v['version_id'] ?>"><button class="btn btn-sm"><?= te('Restaurar') ?></button></form></td></tr>
      <?php endforeach; ?></tbody>
    </table></div>
  </details>
</section>
