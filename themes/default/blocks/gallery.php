<section class="section">
  <div class="container">
    <?php if ($d['title']): ?><div class="section-head"><h2><?= e($d['title']) ?></h2></div><?php endif; ?>
    <div class="gallery">
      <?php foreach ($d['images'] as $im):
        $vid = media_embed_info((string) ($im['video'] ?? ''));
        if (!$im['src'] && $vid['kind'] === '') continue; ?>
      <?php if ($vid['kind'] === 'file'): ?>
        <figure class="gallery-video"><video controls playsinline preload="none"<?= $im['src'] ? ' poster="' . e($im['src']) . '"' : '' ?> aria-label="<?= e($im['alt']) ?>"><source src="<?= e($vid['url']) ?>"<?= $vid['type'] ? ' type="' . e($vid['type']) . '"' : '' ?>></video></figure>
      <?php elseif ($vid['kind'] !== ''): ?>
        <figure class="gallery-video"><div class="video-embed"><iframe src="<?= e($vid['url']) ?>" title="<?= e($im['alt'] ?: 'Vídeo') ?>" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe></div></figure>
      <?php else: ?>
        <a href="<?= e($im['src']) ?>" target="_blank" rel="noopener"><img<?= img_attrs($im['src'], '(max-width: 700px) 50vw, 33vw') ?> alt="<?= e($im['alt']) ?>" loading="lazy" decoding="async"></a>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</section>
