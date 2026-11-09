<?php
$m = media_embed_info((string) ($d['src'] ?? ''));
$pu = trim((string) ($d["poster"] ?? ""));
$posterUrl = ($pu !== "" && (str_starts_with($pu, "/media/") || str_starts_with($pu, "https://")) && !preg_match("/[\\s\"'<>]/", $pu)) ? $pu : "";
$auto = media_truthy($d['autoplay'] ?? '');
$title = trim((string) ($d['title'] ?? ''));
?>
<?php if ($m['kind'] !== ''): ?>
<section class="section-tight">
  <div class="container media-block">
    <?php if ($title): ?><h2><?= e($title) ?></h2><?php endif; ?>
    <figure class="media-figure">
      <?php if ($m['kind'] === 'file'): ?>
        <video class="media-video" controls playsinline preload="metadata"<?= $posterUrl ? ' poster="' . e($posterUrl) . '"' : '' ?><?= $auto ? ' autoplay muted loop' : '' ?>>
          <source src="<?= e($m['url']) ?>"<?= $m['type'] ? ' type="' . e($m['type']) . '"' : '' ?>>
          <a href="<?= e($m['url']) ?>"><?= e(__('Descargar vídeo')) ?></a>
        </video>
      <?php else: ?>
        <div class="video-embed">
          <iframe src="<?= e($m['url']) ?>" title="<?= e($title ?: 'Vídeo') ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
        </div>
      <?php endif; ?>
      <?php if (!empty($d['caption'])): ?><figcaption><?= e($d['caption']) ?></figcaption><?php endif; ?>
    </figure>
  </div>
</section>
<?php endif; ?>
