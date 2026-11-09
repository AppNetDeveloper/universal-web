<?php
$m = media_embed_info((string) ($d['src'] ?? ''));
$title = trim((string) ($d['title'] ?? ''));
?>
<?php if ($m['kind'] === 'file'): ?>
<section class="section-tight">
  <div class="container narrow media-block">
    <div class="audio-card">
      <?php if ($title): ?><h3><?= e($title) ?></h3><?php endif; ?>
      <audio class="media-audio" controls preload="metadata">
        <source src="<?= e($m['url']) ?>"<?= $m['type'] ? ' type="' . e($m['type']) . '"' : '' ?>>
        <a href="<?= e($m['url']) ?>"><?= e(__('Descargar audio')) ?></a>
      </audio>
      <?php if (!empty($d['text'])): ?><p class="audio-text"><?= nl2br(e($d['text'])) ?></p><?php endif; ?>
    </div>
  </div>
</section>
<?php endif; ?>
