<?php /** @var array $page @var array $schema @var SiteService $svc */
$isNew = !$page['id'];
$seoVals = $page['seo'];
$schema = t_walk($schema, ['label', 'description']); ?>
<div class="page-head"><h1><?= te($isNew ? 'Nueva página' : 'Editar página') ?></h1>
  <div class="actions">
  <?php if (!$isNew): ?><a class="btn" target="_blank" rel="noopener" href="<?= e($page['status'] === 'draft' ? draft_preview_href($svc->site, $page['url']) : $page['url']) ?>"><?= te('Ver') ?> <?= adm_icon('ext') ?></a><?php endif; ?>
  </div></div>
<form method="post" action="<?= ADM ?>/pages/save" id="blocks-form" class="stack">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $page['id'] ?>">
  <?php $item = $page; $kind = 'pages'; $from ??= null; $translations ??= []; include __DIR__ . '/_edit_lang.php'; ?>
  <section class="card">
    <div class="row">
      <label class="grow"><?= te('Título') ?><input type="text" name="title" id="f-title" value="<?= e($page['title']) ?>" required maxlength="200"></label>
      <label class="grow"><?= te('Slug') ?> <small class="muted"><?= th('(<code>index</code> = portada)') ?></small><input type="text" name="slug" data-slug-from="#f-title" value="<?= e($page['slug']) ?>" maxlength="100" pattern="[a-z0-9-]*" placeholder="<?= te('se genera del título') ?>"></label>
      <label><?= te('Estado') ?><select name="status"><option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>><?= te('Borrador') ?></option><option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>><?= te('Publicada') ?></option></select></label>
    </div>
    <?php $auto = $page['slug'] !== '' ? $svc->autoPath('page', $page['slug'], $page['lang']) : ''; ?>
    <label><?= te('Dirección (URL) fija') ?> <small class="muted"><?= te('Opcional. Para migrar una web sin cambiar sus URLs, p. ej. /precios.html o /guias/mudanza.html. Vacío = automática') ?><?= $auto !== '' ? ' (' . e($auto) . ')' : '' ?></small>
      <input type="text" name="path" value="<?= e((string) ($page['path'] ?? '')) ?>" maxlength="300" placeholder="<?= e($auto ?: '/precios.html') ?>" pattern="(/[A-Za-z0-9._~\-]+)*" <?= $page['slug'] === 'index' ? 'disabled' : '' ?>></label>
  </section>
  <?php if (!$isNew && count($svc->langs()) > 1 && role_at_least($role ?? null, 'admin')): $pl = page_languages($svc, 'page', $page['slug']); $faltan = array_filter($pl['languages'], fn($x) => !$x['exists']); ?>
  <?php if ($faltan): ?>
  <details class="card"><summary><strong><?= te('Idiomas en los que no existe esta página') ?></strong> <span class="muted">(<?= e(implode(', ', array_map('strtoupper', array_keys($faltan)))) ?>)</span></summary>
    <p class="muted"><?= te('Si una página solo tiene sentido en algunos idiomas, indica a dónde lleva su dirección en los demás (redirección 301). Los menús y enlaces de ese idioma irán directos al destino y no se anuncia en hreflang.') ?></p>
    <?php foreach ($faltan as $l => $x): ?>
      <label><?= e(LANG_NAMES[$l] ?? $l) ?> · <code><?= e($x['equivalent_path']) ?></code> → <input type="text" name="missing[<?= e($l) ?>]" value="<?= e((string) $x['redirect_to']) ?>" placeholder="/<?= e($l) ?>/" maxlength="300"></label>
    <?php endforeach; ?>
  </details>
  <?php endif; endif; ?>

  <section class="card">
    <div class="card-head"><h2><?= te('Bloques') ?></h2>
      <label class="check switch"><input type="checkbox" id="json-toggle"> <?= te('Editar JSON') ?></label></div>
    <div id="block-editor" class="blocks"></div>
    <div id="json-panel" hidden>
      <textarea id="blocks-json" class="mono" rows="18" spellcheck="false"></textarea>
      <p class="muted"><?= te('Lista JSON:') ?> <code>[{"type":"hero","data":{"title":"..."}}]</code></p>
    </div>
    <p id="blocks-error" class="field-error" hidden></p>
    <div class="add-block" id="add-block-bar">
      <select id="add-block-type"><?php foreach ($schema as $k => $t): ?><option value="<?= e($k) ?>"><?= e($t['label']) ?></option><?php endforeach; ?></select>
      <button type="button" class="btn" id="add-block-btn"><?= te('+ Añadir bloque') ?></button>
    </div>
    <input type="hidden" name="blocks" id="blocks-input" value="[]">
  </section>

  <?php include __DIR__ . '/_seo_fields.php'; ?>

  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar') ?></button> <a class="btn btn-ghost" href="<?= ADM ?>/pages"><?= te('Cancelar') ?></a></div>
</form>
<?php [$histKind, $histItem] = ['page', $page]; include __DIR__ . '/_history.php'; ?>
<?= adm_media_datalist($svc) ?>
<?= adm_json_script('block-schema', $schema) ?>
<?= adm_json_script('blocks-data', $page['blocks']) ?>
