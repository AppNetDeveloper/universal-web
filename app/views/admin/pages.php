<?php /** @var array $pages @var array $groups @var array $langs @var SiteService $svc */
$multi = count($langs) > 1;
$main = $svc->defaultLang();
// Una fila por slug: la versión del idioma principal (o la primera que exista) y sus traducciones
$rows = [];
foreach ($groups as $slug => $byLang) $rows[] = ['main' => $byLang[$main] ?? reset($byLang), 'by' => $byLang];
usort($rows, fn($a, $b) => [$a['main']['slug'] !== 'index', $a['main']['title']] <=> [$b['main']['slug'] !== 'index', $b['main']['title']]); ?>
<div class="page-head"><h1><?= te('Páginas') ?></h1><a class="btn btn-primary" href="<?= ADM ?>/pages/edit"><?= te('Nueva página') ?></a></div>
<?php if (!$rows): ?>
  <div class="empty"><p><?= th('Aún no hay páginas. Crea la primera: usa el slug <code>index</code> para la portada.') ?></p><a class="btn btn-primary" href="<?= ADM ?>/pages/edit"><?= te('Crear página') ?></a></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Título') ?></th><th><?= te('Slug') ?></th><th><?= te('Estado') ?></th><?php if ($multi): ?><th><?= te('Idiomas') ?></th><?php endif; ?><th><?= te('Actualizada') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as ['main' => $p, 'by' => $by]): ?>
    <tr>
      <td data-l="<?= te('Título') ?>"><a href="<?= ADM ?>/pages/edit?id=<?= (int) $p['id'] ?>"><b><?= e($p['title']) ?></b></a><?= $multi && $p['lang'] !== $main ? ' ' . adm_badge(strtoupper($p['lang'])) : '' ?></td>
      <td data-l="<?= te('Slug') ?>"><code>/<?= $p['slug'] === 'index' ? '' : e($p['slug']) ?></code></td>
      <td data-l="<?= te('Estado') ?>"><?= $p['status'] === 'published' ? adm_badge(t('Publicada'), 'green') : adm_badge(t('Borrador')) ?></td>
      <?php if ($multi): ?>
      <td data-l="<?= te('Idiomas') ?>" class="lang-cells">
        <?php foreach ($langs as $l): $tp = $by[$l] ?? null; ?>
          <?php if ($tp): ?><a class="badge <?= $tp['status'] === 'published' ? 'badge-green' : 'badge-gray' ?>" href="<?= ADM ?>/pages/edit?id=<?= (int) $tp['id'] ?>" title="<?= e(($tp['title']) . ' · ' . ($tp['status'] === 'published' ? t('Publicada') : t('Borrador'))) ?>"><?= e(strtoupper($l)) ?></a>
          <?php else: ?><a class="badge badge-add" href="<?= ADM ?>/pages/edit?translate=<?= (int) $p['id'] ?>&amp;lang=<?= e($l) ?>" title="<?= te('Traducir a {lang}', ['lang' => LANG_NAMES[$l] ?? $l]) ?>">+ <?= e(strtoupper($l)) ?></a><?php endif; ?>
        <?php endforeach; ?>
      </td>
      <?php endif; ?>
      <td data-l="<?= te('Actualizada') ?>"><?= e(adm_date($p['updated_at'])) ?></td>
      <td class="row-actions">
        <a class="btn btn-sm" href="<?= ADM ?>/pages/edit?id=<?= (int) $p['id'] ?>"><?= te('Editar') ?></a>
        <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e($p['status'] === 'draft' ? draft_preview_href($svc->site, $p['url']) : $p['url']) ?>"><?= te('Ver') ?></a>
        <form method="post" action="<?= ADM ?>/pages/delete" data-confirm="<?= te('¿Borrar la página «{title}»? Irá a la papelera 30 días.', ['title' => $p['title']]) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Borrar') ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($multi): ?><p class="hint"><?= te('Idiomas: verde = publicada, gris = borrador, «+» = sin traducir (crea la traducción con el contenido original para reescribirlo).') ?></p><?php endif; ?>
<?php endif; ?>
