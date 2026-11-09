<?php /** @var array $tpl @var array $history @var string $preview @var string $starter @var array $help @var SiteService $svc */
$layout = $tpl['layout'] !== '' ? $tpl['layout'] : $starter; ?>
<div class="page-head"><h1><?= te('Plantilla HTML') ?> <?= $tpl['enabled'] ? adm_badge(t('Activa'), 'green') : adm_badge(t('Desactivada')) ?></h1>
  <div class="actions"><?php if ($tpl['layout'] !== ''): ?><a class="btn" target="_blank" rel="noopener" href="<?= e($preview) ?>"><?= te('Vista previa') ?> <?= adm_icon('ext') ?></a><?php endif; ?></div></div>
<p class="muted"><?= te('Un diseño propio para esta web: tú (o la IA por MCP) escribes el HTML de la cabecera, el pie y la estructura, y Universal Web lo rellena con el menú, el contenido, el SEO y los idiomas. Es HTML con marcadores: nunca se ejecuta código en el servidor.') ?></p>

<form method="post" action="<?= ADM ?>/template" class="stack">
  <?= csrf_field() ?>
  <section class="card">
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= $tpl['enabled'] ? 'checked' : '' ?>> <?= te('Usar esta plantilla en la web pública') ?></label>
    <label class="check"><input type="checkbox" name="base_assets" value="1" <?= $tpl['base_assets'] ? 'checked' : '' ?>> <?= te('Cargar también el CSS y JS del tema (estilos de los bloques)') ?></label>
    <?php if ($tpl['layout'] === ''): ?><p class="hint"><?= te('Aún no hay plantilla: abajo tienes una de partida. Guárdala sin activar, revísala con «Vista previa» y actívala cuando te guste.') ?></p><?php endif; ?>
  </section>
  <section class="card">
    <h2><?= te('HTML') ?> <small class="muted">v<?= (int) $tpl['version'] ?></small></h2>
    <textarea name="layout" rows="24" class="mono" spellcheck="false"><?= e($layout) ?></textarea>
  </section>
  <section class="card">
    <h2><?= te('CSS de la plantilla') ?></h2>
    <textarea name="css" rows="14" class="mono" spellcheck="false" placeholder=".site-header{background:#111;color:#fff}"><?= e($tpl['css']) ?></textarea>
  </section>
  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar') ?></button></div>
</form>

<div class="grid-2">
<section class="card">
  <h2><?= te('Marcadores') ?></h2>
  <?php foreach (['obligatorios' => 'Obligatorios', 'componentes' => 'Componentes HTML', 'datos' => 'Datos (texto)', 'listas' => 'Listas', 'condiciones' => 'Condiciones', 'no_permitido' => 'No permitido'] as $k => $label): ?>
    <h3><?= te($label) ?></h3>
    <ul class="plain"><?php foreach ($help[$k] as $h): ?><li><code><?= e($h) ?></code></li><?php endforeach; ?></ul>
  <?php endforeach; ?>
  <p class="hint"><?= e(t($help['clases_del_contenido'])) ?></p>
</section>
<section class="card">
  <h2><?= te('Historial') ?></h2>
  <?php if (!$history): ?><p class="muted"><?= te('Todavía no hay versiones anteriores.') ?></p><?php else: ?>
  <div class="table-wrap"><table>
    <thead><tr><th><?= te('Versión') ?></th><th><?= te('Fecha') ?></th><th><?= te('Autor') ?></th><th></th></tr></thead>
    <tbody><?php foreach ($history as $h): ?>
      <tr><td>v<?= (int) $h['version'] ?><?= $h['enabled'] ? ' ' . adm_badge(t('Activa'), 'green') : '' ?></td><td><?= e(adm_date($h['created_at'])) ?></td><td><?= e($h['actor_name']) ?></td>
        <td class="row-actions"><form method="post" action="<?= ADM ?>/template/restore" data-confirm="<?= te('¿Volver a la versión {v}?', ['v' => (int) $h['version']]) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $h['id'] ?>"><button class="btn btn-sm"><?= te('Restaurar') ?></button></form></td></tr>
    <?php endforeach; ?></tbody>
  </table></div>
  <?php endif; ?>
  <p class="hint"><?= th('La IA puede hacerlo todo por MCP: <code>template_get</code>, <code>template_update</code> y <code>template_restore</code>.') ?></p>
</section>
</div>
