<?php /** @var array $media */ ?>
<div class="page-head"><h1><?= te('Medios') ?></h1></div>
<form method="post" action="<?= ADM ?>/media/upload" enctype="multipart/form-data" class="card upload">
  <?= csrf_field() ?>
  <label><?= te('Archivos') ?> <small class="muted"><?= te('(JPG, PNG y WebP · máx. {raw} MB, se reducen solos; resto de imágenes y PDF · máx. {max} MB; MP4, WebM, MP3, OGG, M4A, WAV · máx. {av} MB)', ['raw' => intdiv(MEDIA_MAX_BYTES_RAW, 1048576), 'max' => intdiv(MEDIA_MAX_BYTES, 1048576), 'av' => intdiv(MEDIA_MAX_BYTES_AV, 1048576)]) ?></small>
    <input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.gif,.webp,.avif,.ico,.pdf,.mp4,.webm,.mp3,.ogg,.m4a,.wav"></label>
  <label><?= te('Texto alternativo') ?> <small class="muted"><?= te('(describe la imagen; se aplica a todas)') ?></small><input type="text" name="alt" maxlength="200"></label>
  <div><button class="btn btn-primary"><?= te('Subir') ?></button></div>
</form>
<form method="post" action="<?= ADM ?>/media/upload-url" class="card upload-url">
  <?= csrf_field() ?>
  <label><?= te('Añadir desde URL') ?> <small class="muted"><?= te('(se descarga y se guarda en tu biblioteca)') ?></small>
    <input type="url" name="url" required placeholder="https://..." maxlength="1000"></label>
  <label><?= te('Texto alternativo') ?><input type="text" name="alt" maxlength="200"></label>
  <div><button class="btn"><?= te('Descargar y añadir') ?></button></div>
</form>
<?php if (!$media): ?>
  <div class="empty"><p><?= te('Aún no has subido nada.') ?></p></div>
<?php else: ?>
<div class="media-grid">
  <?php foreach ($media as $m): $isPdf = $m['mime'] === 'application/pdf'; $isVideo = str_starts_with($m['mime'], 'video/'); $isAudio = str_starts_with($m['mime'], 'audio/'); ?>
  <figure class="media-item">
    <a class="thumb" href="<?= e($m['url']) ?>" target="_blank" rel="noopener">
      <?php if ($isPdf): ?><span class="pdf"><?= adm_icon('pdf') ?>PDF</span><?php elseif ($isVideo): ?><video src="<?= e($m['url']) ?>#t=0.1" preload="metadata" muted playsinline></video><?php elseif ($isAudio): ?><span class="audio-ph" aria-hidden="true">♪</span><?php else: ?><img src="<?= e($m['url']) ?>" alt="<?= e($m['alt']) ?>" loading="lazy"><?php endif; ?>
    </a>
    <figcaption>
      <span class="name" title="<?= e($m['original_name']) ?>"><?= e(($m['title'] ?? '') !== '' ? $m['title'] : $m['original_name']) ?><?= !empty($m['ai_generated']) ? ' ' . adm_badge(t('IA'), 'blue') : '' ?></span>
      <small class="muted"><?= e(number_format($m['size'] / 1024, 0, ',', '.')) ?> KB<?= $m['alt'] !== '' ? ' · ' . e(str_limit($m['alt'], 30)) : '' ?></small>
      <?php if ($isAudio): ?><audio controls preload="none" src="<?= e($m['url']) ?>"></audio><?php endif; ?>
      <div class="media-actions">
        <button type="button" class="btn btn-sm" data-copy="<?= e($m['url']) ?>"><?= te('Copiar URL') ?></button>
        <form method="post" action="<?= ADM ?>/media/delete" data-confirm="<?= te('¿Borrar este archivo? Las páginas que lo usen mostrarán un enlace roto.') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Borrar') ?></button></form>
      </div>
    </figcaption>
  </figure>
  <?php endforeach; ?>
</div>
<?php endif; ?>
