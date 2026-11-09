<?php /* Tarjeta de entrada; espera $p. */ ?>
<article class="card post-card">
  <?php if ($p['cover_image']): ?><a href="<?= e($svc->langPath('/blog/' . $p['slug'])) ?>" tabindex="-1" aria-hidden="true"><img<?= img_attrs($p['cover_image'], '(max-width: 700px) 100vw, 33vw') ?> alt="" loading="lazy" decoding="async"></a><?php endif; ?>
  <div class="card-body">
    <p class="meta"><?= $p['published_at'] ? e(date('d/m/Y', strtotime((string) $p['published_at']))) : '' ?></p>
    <h3><a href="<?= e($svc->langPath('/blog/' . $p['slug'])) ?>"><?= e($p['title']) ?></a></h3>
    <?php if ($p['excerpt']): ?><p><?= e(str_limit($p['excerpt'], 140)) ?></p><?php endif; ?>
  </div>
</article>
