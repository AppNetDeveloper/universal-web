<?php /** @var array $post @var SiteService $svc */
$isNew = !$post['id'];
$seoVals = $post['seo'];
$pub = $post['published_at'] ? str_replace(' ', 'T', substr((string) $post['published_at'], 0, 16)) : ''; ?>
<div class="page-head"><h1><?= te($isNew ? 'Nueva entrada' : 'Editar entrada') ?> <?= $isNew ? '' : adm_state_badge($post['state']) ?></h1>
  <div class="actions">
  <?php if (!$isNew): ?><a class="btn" target="_blank" rel="noopener" href="<?= e($post['state'] !== 'published' ? draft_preview_href($svc->site, $post['url']) : $post['url']) ?>"><?= te('Vista previa') ?> <?= adm_icon('ext') ?></a><?php endif; ?>
  </div></div>
<form method="post" action="<?= ADM ?>/posts/save" class="stack">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int) $post['id'] ?>">
  <?php $item = $post; $kind = 'posts'; $from ??= null; $translations ??= []; include __DIR__ . '/_edit_lang.php'; ?>
  <section class="card">
    <div class="row">
      <label class="grow"><?= te('Título') ?><input type="text" name="title" id="f-title" value="<?= e($post['title']) ?>" required maxlength="200"></label>
      <label class="grow"><?= te('Slug') ?><input type="text" name="slug" data-slug-from="#f-title" value="<?= e($post['slug']) ?>" maxlength="100" pattern="[a-z0-9-]*" placeholder="<?= te('se genera del título') ?>"></label>
    </div>
    <label><?= te('Extracto') ?><textarea name="excerpt" rows="2" maxlength="500"><?= e($post['excerpt']) ?></textarea></label>
    <label><?= te('Contenido') ?> <small class="muted"><?= te('Admite Markdown') ?></small>
      <textarea name="content" rows="22" class="mono big"><?= e($post['content']) ?></textarea></label>
    <small class="muted"><?= te('Para ver el resultado, guarda y pulsa «Vista previa».') ?></small>
  </section>
  <section class="card">
    <h2><?= te('Publicación') ?></h2>
    <div class="row">
      <label><?= te('Estado') ?><select name="status"><option value="draft" <?= $post['status'] === 'draft' ? 'selected' : '' ?>><?= te('Borrador') ?></option><option value="published" <?= $post['status'] === 'published' ? 'selected' : '' ?>><?= te('Publicada') ?></option></select></label>
      <label><?= te('Fecha de publicación') ?><input type="datetime-local" name="published_at" value="<?= e($pub) ?>"><small class="muted"><?= te('Una fecha futura = programada. Vacía = ahora al publicar.') ?></small></label>
      <label class="grow"><?= te('Autor') ?><input type="text" name="author" value="<?= e($post['author']) ?>" maxlength="100"></label>
    </div>
    <div class="row">
      <label class="grow"><?= te('Etiquetas') ?> <small class="muted"><?= te('(separadas por comas)') ?></small><input type="text" name="tags" value="<?= e(implode(', ', (array) $post['tags'])) ?>"></label>
      <label class="grow"><?= te('Imagen de portada') ?><input type="text" name="cover_image" value="<?= e($post['cover_image']) ?>" list="media-urls" placeholder="/media/..."></label>
      <label class="grow"><?= te('Audio «Escuchar este artículo»') ?><input type="text" name="audio" value="<?= e($post['audio'] ?? '') ?>" list="media-urls" placeholder="/media/....mp3"></label>
    </div>
  </section>
  <?php include __DIR__ . '/_seo_fields.php'; ?>
  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar') ?></button> <a class="btn btn-ghost" href="<?= ADM ?>/posts"><?= te('Cancelar') ?></a></div>
</form>
<?php [$histKind, $histItem] = ['post', $post]; include __DIR__ . '/_history.php'; ?>
<?= adm_media_datalist($svc) ?>
