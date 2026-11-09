<?php /** @var array $list @var array $themes @var array $old @var SiteService|null $svc */ ?>
<div class="page-head"><h1><?= te('Webs') ?></h1></div>
<?php if (!$list): ?><div class="empty"><p><?= te('Todavía no hay ninguna web. Crea la primera abajo.') ?></p></div><?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Web') ?></th><th><?= te('Dominios') ?></th><th><?= te('Tema') ?></th><th><?= te('Contenido') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $s): $cur = $svc && $svc->id() === (int) $s['id']; ?>
    <tr>
      <td data-l="<?= te('Web') ?>"><b><?= e($s['name']) ?></b> <?= $cur ? adm_badge(t('Actual'), 'blue') : '' ?><br><code class="muted"><?= e($s['slug']) ?></code></td>
      <td data-l="<?= te('Dominios') ?>"><?= e(implode(', ', $s['domains'])) ?></td>
      <td data-l="<?= te('Tema') ?>"><?= e($s['theme']) ?></td>
      <td data-l="<?= te('Contenido') ?>"><?= te('{pages} páginas · {posts} entradas', ['pages' => (int) $s['n_pages'], 'posts' => (int) $s['n_posts']]) ?></td>
      <td class="row-actions"><a class="btn btn-sm" href="<?= ADM ?>/switch?site=<?= (int) $s['id'] ?>"><?= te('Gestionar') ?></a>
        <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(site_preview_link($s)) ?>"><?= te('Vista previa sin dominio') ?> <?= adm_icon('ext') ?></a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<p class="muted"><?= te('«Vista previa sin dominio» abre la web en este mismo servidor, sin que su dominio apunte aquí todavía: sirve para verla y enseñársela al cliente antes de publicarla. El enlace dura 7 días.') ?></p>
<?php endif; ?>

<section class="card" style="margin-top:1.5rem">
  <h2><?= te('Crear web') ?></h2>
  <form method="post" action="<?= ADM ?>/sites/create" class="stack">
    <?= csrf_field() ?>
    <div class="row">
      <label class="grow"><?= te('Nombre') ?><input type="text" name="name" id="f-title" required maxlength="100" value="<?= e($old['name'] ?? '') ?>"></label>
      <label class="grow"><?= te('Identificador (slug)') ?><input type="text" name="slug" data-slug-from="#f-title" pattern="[a-z0-9-]*" value="<?= e($old['slug'] ?? '') ?>"></label>
      <label><?= te('Tema') ?><select name="theme"><?php foreach ($themes as $k => $n): ?><option value="<?= e($k) ?>" <?= ($old['theme'] ?? 'default') === $k ? 'selected' : '' ?>><?= te($n) ?></option><?php endforeach; ?></select></label>
    </div>
    <label><?= te('Dominios (uno por línea; el primero es el principal). Opcional: puedes crearla sin dominio y verla con «Vista previa sin dominio».') ?><textarea name="domains" rows="3" class="mono" placeholder="ejemplo.com"><?= e($old['domains'] ?? '') ?></textarea></label>
    <label class="check"><input type="checkbox" name="starter" value="1" checked> <?= te('Crear páginas iniciales (Inicio y Contacto)') ?></label>
    <div><button class="btn btn-primary"><?= te('Crear web') ?></button></div>
  </form>
</section>
