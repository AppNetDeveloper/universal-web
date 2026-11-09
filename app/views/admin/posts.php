<?php /** @var array $posts @var string $status @var int $page @var int $pages @var int $total @var string $lang @var array $langs @var SiteService $svc */
$multi = count($langs) > 1;
$langQ = $lang !== $svc->defaultLang() ? $lang : '';
$qs = fn(array $a) => '?' . http_build_query(array_filter($a + ['lang' => $langQ])); ?>
<div class="page-head"><h1><?= te('Entradas') ?></h1><a class="btn btn-primary" href="<?= ADM ?>/posts/edit<?= $langQ ? '?lang=' . e($langQ) : '' ?>"><?= te('Nueva entrada') ?></a></div>
<?php if ($multi): ?>
<div class="tabs">
  <?php foreach ($langs as $l): ?><a href="<?= ADM ?>/posts<?= $l !== $svc->defaultLang() ? '?lang=' . e($l) : '' ?>" class="<?= $l === $lang ? 'active' : '' ?>"><?= e(LANG_NAMES[$l] ?? $l) ?></a><?php endforeach; ?>
</div>
<?php endif; ?>
<div class="tabs">
  <a href="<?= ADM ?>/posts<?= e($qs([])) ?>" class="<?= $status === '' ? 'active' : '' ?>"><?= te('Todas') ?></a>
  <a href="<?= ADM ?>/posts<?= e($qs(['status' => 'published'])) ?>" class="<?= $status === 'published' ? 'active' : '' ?>"><?= te('Publicadas') ?></a>
  <a href="<?= ADM ?>/posts<?= e($qs(['status' => 'draft'])) ?>" class="<?= $status === 'draft' ? 'active' : '' ?>"><?= te('Borradores') ?></a>
</div>
<?php if (!$posts): ?>
  <div class="empty"><p><?= te($status ? 'No hay entradas con este estado.' : 'No hay entradas todavía.') ?></p><a class="btn btn-primary" href="<?= ADM ?>/posts/edit"><?= te('Escribir la primera') ?></a></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Título') ?></th><th><?= te('Estado') ?></th><th><?= te('Fecha') ?></th><th><?= te('Autor') ?></th><?php if ($multi): ?><th><?= te('Idiomas') ?></th><?php endif; ?><th></th></tr></thead>
  <tbody>
  <?php foreach ($posts as $p): ?>
    <tr>
      <td data-l="<?= te('Título') ?>"><a href="<?= ADM ?>/posts/edit?id=<?= (int) $p['id'] ?>"><b><?= e($p['title']) ?></b></a><br><code class="muted">/blog/<?= e($p['slug']) ?></code></td>
      <td data-l="<?= te('Estado') ?>"><?= adm_state_badge($p['state']) ?></td>
      <td data-l="<?= te('Fecha') ?>"><?= e(adm_date($p['published_at'] ?: $p['created_at'])) ?></td>
      <td data-l="<?= te('Autor') ?>"><?= e($p['author']) ?></td>
      <?php if ($multi): $tr = $svc->translations('posts', $p['slug']); ?>
      <td data-l="<?= te('Idiomas') ?>" class="lang-cells">
        <?php foreach ($langs as $l): if ($l === $lang) continue; ?>
          <?php if (isset($tr[$l])): ?><a class="badge <?= $tr[$l] === 'published' ? 'badge-green' : 'badge-gray' ?>" href="<?= ADM ?>/posts?lang=<?= e($l) ?>"><?= e(strtoupper($l)) ?></a>
          <?php else: ?><a class="badge badge-add" href="<?= ADM ?>/posts/edit?translate=<?= (int) $p['id'] ?>&amp;lang=<?= e($l) ?>" title="<?= te('Traducir a {lang}', ['lang' => LANG_NAMES[$l] ?? $l]) ?>">+ <?= e(strtoupper($l)) ?></a><?php endif; ?>
        <?php endforeach; ?>
      </td>
      <?php endif; ?>
      <td class="row-actions">
        <a class="btn btn-sm" href="<?= ADM ?>/posts/edit?id=<?= (int) $p['id'] ?>"><?= te('Editar') ?></a>
        <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e($p['state'] !== 'published' ? draft_preview_href($svc->site, $p['url']) : $p['url']) ?>"><?= te('Ver') ?></a>
        <form method="post" action="<?= ADM ?>/posts/delete" data-confirm="<?= te('¿Borrar la entrada «{title}»? Irá a la papelera 30 días.', ['title' => $p['title']]) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Borrar') ?></button></form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php if ($pages > 1): ?>
<div class="pager">
  <?php if ($page > 1): ?><a class="btn btn-sm" href="<?= ADM ?>/posts<?= e($qs(['status' => $status, 'p' => $page - 1])) ?>"><?= te('← Anterior') ?></a><?php endif; ?>
  <span class="muted"><?= te('Página {page} de {pages} ({total})', ['page' => $page, 'pages' => $pages, 'total' => $total]) ?></span>
  <?php if ($page < $pages): ?><a class="btn btn-sm" href="<?= ADM ?>/posts<?= e($qs(['status' => $status, 'p' => $page + 1])) ?>"><?= te('Siguiente →') ?></a><?php endif; ?>
</div>
<?php endif; endif; ?>
