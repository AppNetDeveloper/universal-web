<?php /* Cota: cifras como medidas, con la unidad en mono. */ ?>
<?php if ($d['items']): ?>
<section class="section-tight block-stats">
  <div class="container">
    <dl class="stats">
      <?php foreach ($d['items'] as $it):
        $v = trim((string) $it['value']);
        $num = $v; $unit = '';
        if (preg_match('/^([\d][\d.,]*)\s*(\S.*)?$/u', $v, $mm)) { $num = $mm[1]; $unit = trim((string) ($mm[2] ?? '')); }
      ?>
      <div class="stat"><dt><?= e($it['label']) ?></dt><dd><span class="stat-num"><?= e($num) ?></span><?php if ($unit !== ''): ?> <span class="stat-unit"><?= e($unit) ?></span><?php endif; ?></dd></div>
      <?php endforeach; ?>
    </dl>
  </div>
</section>
<?php endif; ?>
