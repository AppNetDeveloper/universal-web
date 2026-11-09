<?php /** @var array $entries */ ?>
<div class="page-head"><h1><?= te('Actividad') ?></h1></div>
<?php if (!$entries): ?><div class="empty"><p><?= te('Sin actividad registrada.') ?></p></div><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Fecha') ?></th><th><?= te('Actor') ?></th><th><?= te('Acción') ?></th><th><?= te('Objetivo') ?></th><th><?= te('Detalles') ?></th></tr></thead>
  <tbody>
  <?php foreach ($entries as $a):
      $d = json_dec($a['details'], []);
      $det = [];
      foreach ((array) $d as $k => $v) $det[] = $k . ': ' . (is_scalar($v) || $v === null ? (string) $v : json_enc($v)); ?>
    <tr>
      <td data-l="<?= te('Fecha') ?>"><?= e(adm_date($a['created_at'])) ?></td>
      <td data-l="<?= te('Actor') ?>"><?= $a['actor_type'] === 'token' ? adm_icon('bot') . ' MCP · ' : ($a['actor_type'] === 'user' ? adm_icon('user') . ' ' : '') ?><?= e($a['actor_name']) ?></td>
      <td data-l="<?= te('Acción') ?>"><code><?= e($a['action']) ?></code></td>
      <td data-l="<?= te('Objetivo') ?>"><?= e($a['target']) ?></td>
      <td data-l="<?= te('Detalles') ?>" class="muted"><?= e(implode(' · ', $det)) ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
