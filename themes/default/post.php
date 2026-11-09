<?php
$date = $post['published_at'] ? date('d/m/Y', strtotime((string) $post['published_at'])) : '';
?>
<article class="container article">
  <a class="back" href="<?= e($svc->langPath('/blog')) ?>"><?= '&larr; ' . e(__('Volver al blog')) ?></a>
  <header class="article-head">
    <?php if ($post['state'] !== 'published'): ?><p class="notice"><?= e(__('Vista previa: esta entrada no está publicada.')) ?></p><?php endif; ?>
    <h1><?= e($post['title']) ?></h1>
    <p class="meta">
      <?php if ($date): ?><time datetime="<?= e(date('c', strtotime((string) $post['published_at']))) ?>"><?= e($date) ?></time><?php endif; ?>
      <?php if ($post['author']): ?><span> · <?= e($post['author']) ?></span><?php endif; ?>
    </p>
    <?php if ($post['tags']): ?><p class="tags"><?php foreach ($post['tags'] as $t): ?><a class="tag" href="<?= e($svc->langPath('/blog')) ?>?tag=<?= e(rawurlencode($t)) ?>">#<?= e($t) ?></a><?php endforeach; ?></p><?php endif; ?>
  </header>
  <?php if ($post['cover_image']): ?><img class="article-cover"<?= img_attrs($post['cover_image'], '(max-width: 900px) 100vw, 800px') ?> alt="<?= e($post['title']) ?>" decoding="async"><?php endif; ?>
  <?php if (($post['audio'] ?? '') !== ''): $listen = ['src' => $post['audio'], 'title' => __('Escuchar este artículo')]; include theme_file($site['theme'], 'blocks/_listen.php'); endif; ?>
  <div class="prose"><?= md($post['content']) ?></div>
</article>
