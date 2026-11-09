<?php if ($d['items']): ?>
<section class="section-tight block-logos">
  <div class="container">
    <?php if ($d['title']): ?><p class="logos-title"><?= e($d['title']) ?></p><?php endif; ?>
    <ul class="logos">
      <?php foreach ($d['items'] as $it): if (!$it['image']) continue; $img = '<img' . img_attrs($it['image'], '160px') . ' alt="' . e($it['alt']) . '" loading="lazy" decoding="async">'; ?>
      <li><?= $it['link'] ? '<a href="' . e($it['link']) . '" target="_blank" rel="noopener">' . $img . '</a>' : $img ?></li>
      <?php endforeach; ?>
    </ul>
  </div>
</section>
<?php endif; ?>
