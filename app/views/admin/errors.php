<?php /** @var array $errors @var ?array $check @var string $source @var string $role */
$labels = ['js' => 'JavaScript', 'resource' => t('Archivo que no carga'), 'php' => 'PHP'];
$sev = ['error' => 'red', 'warning' => 'amber', 'notice' => 'gray']; ?>
<div class="page-head"><h1><?= te('Errores') ?></h1>
  <div class="actions">
    <a class="btn btn-primary" href="<?= ADM ?>/errors?check=1"><?= te('Revisar la web ahora') ?></a>
    <?php if ($errors && role_at_least($role, 'admin')): ?><form method="post" action="<?= ADM ?>/errors/clear" style="display:inline" data-confirm="<?= te('¿Vaciar el registro de errores?') ?>"><?= csrf_field() ?><button class="btn"><?= te('Vaciar registro') ?></button></form><?php endif; ?>
  </div></div>
<p class="muted"><?= te('La «consola» de la web: errores de JavaScript y archivos que no cargan en los navegadores de los visitantes, y avisos del servidor. Se agrupan (mismo error = una fila con contador) y no guardan datos personales. La IA los ve con errors_list y revisa toda la web con site_check.') ?></p>

<?php if ($check !== null): ?>
<section class="card">
  <h2><?= te('Revisión de la web') ?> <?= adm_badge($check['issues'] ? t('{n} aviso(s)', ['n' => count($check['issues'])]) : t('Sin fallos'), $check['issues'] ? 'amber' : 'green') ?></h2>
  <p class="muted"><?= te('{n} página(s) revisadas.', ['n' => $check['checked']]) ?></p>
  <?php if ($check['issues']): ?>
  <div class="table-wrap"><table>
    <thead><tr><th><?= te('Nivel') ?></th><th><?= te('Dónde') ?></th><th><?= te('Problema') ?></th><th><?= te('Cómo arreglarlo') ?></th></tr></thead>
    <tbody><?php foreach ($check['issues'] as $i): ?>
      <tr><td><?= adm_badge(t(['error' => 'Grave', 'warning' => 'Aviso', 'notice' => 'Nota'][$i['severity']] ?? $i['severity']), $sev[$i['severity']] ?? 'gray') ?></td>
        <td><code><?= e($i['where']) ?></code></td><td><?= e(t_msg($i['message'])) ?></td><td class="muted"><?= e(t_msg($i['fix'])) ?></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
</section>
<?php endif; ?>

<h2 class="sec"><?= te('Errores registrados') ?></h2>
<div class="tabs">
  <a href="<?= ADM ?>/errors" class="<?= $source === '' ? 'active' : '' ?>"><?= te('Todos') ?></a>
  <?php foreach ($labels as $k => $l): ?><a href="<?= ADM ?>/errors?source=<?= e($k) ?>" class="<?= $source === $k ? 'active' : '' ?>"><?= e($l) ?></a><?php endforeach; ?>
</div>
<?php if (!$errors): ?>
  <div class="empty"><p><?= te('No hay errores registrados. ¡Bien!') ?></p></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Tipo') ?></th><th><?= te('Error') ?></th><th><?= te('Veces') ?></th><th><?= te('Último') ?></th></tr></thead>
  <tbody><?php foreach ($errors as $er): ?>
    <tr><td><?= adm_badge($labels[$er['source']] ?? $er['source'], $er['source'] === 'php' ? 'red' : 'amber') ?></td>
      <td><b><?= e($er['message']) ?></b><?php if ($er['url'] !== ''): ?><br><code class="muted"><?= e(str_limit($er['url'], 90)) ?></code><?php endif; ?>
        <?php if ($er['detail'] !== ''): ?><details><summary class="muted"><?= te('Detalle') ?></summary><pre class="mono" style="white-space:pre-wrap"><?= e($er['detail']) ?></pre></details><?php endif; ?></td>
      <td><?= (int) $er['count'] ?></td><td><?= e(adm_date($er['last_at'])) ?></td></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>
