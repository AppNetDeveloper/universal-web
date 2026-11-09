<?php /** @var array $seoVals */ ?>
<section class="card">
  <h2><?= te('SEO') ?></h2>
  <label><?= te('Título SEO') ?> <small class="muted"><?= te('(opcional; si está vacío se usa el título)') ?></small>
    <input type="text" name="seo_title" value="<?= e($seoVals['title']) ?>" maxlength="200"></label>
  <label><?= te('Meta descripción') ?>
    <textarea name="seo_description" rows="3" maxlength="500" data-counter="seo-count" data-min="50" data-max="160"><?= e($seoVals['description']) ?></textarea>
    <small class="counter"><span id="seo-count">0</span> <?= te('caracteres · objetivo 50–160') ?></small></label>
  <label><?= te('Imagen para compartir') ?>
    <input type="text" name="seo_image" value="<?= e($seoVals['image']) ?>" list="media-urls" placeholder="/media/..."></label>
  <label class="check"><input type="checkbox" name="seo_noindex" value="1" <?= $seoVals['noindex'] ? 'checked' : '' ?>> <?= te('No indexar (noindex)') ?></label>
</section>
