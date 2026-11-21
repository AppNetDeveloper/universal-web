<?php if ($d['items']): ?>
<section class="stats-band">
  <div class="container">
    <dl class="stats">
      <?php foreach ($d['items'] as $it): ?>
      <div class="stat reveal"><dt><?= e($it['label']) ?></dt><dd><?= e($it['value']) ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
<?php endif; ?>
