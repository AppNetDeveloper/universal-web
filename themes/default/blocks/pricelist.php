<?php
/** Carta o lista de precios agrupada por secciones. */
$items = array_values(array_filter($d['items'], fn($it) => trim((string) $it['name']) !== ''));
$cur = trim((string) $d['currency']) !== '' ? (string) $d['currency'] : '€';
$groups = [];
foreach ($items as $it) $groups[trim((string) $it['section'])][] = $it;
$price = fn(string $p) => $p === '' ? '' : (preg_match('/^[\d.,\s]+$/', $p) ? trim($p) . ' ' . $cur : $p);
?>
<?php if ($items): ?>
<section class="section block-pricelist">
  <div class="container narrow">
    <?php include theme_file($site['theme'], 'blocks/_head.php'); ?>
    <?php foreach ($groups as $section => $list): ?>
    <div class="price-group">
      <?php if ($section !== ''): ?><h3 class="price-section"><?= e($section) ?></h3><?php endif; ?>
      <ul class="price-list">
        <?php foreach ($list as $it): ?>
        <li class="price-item">
          <div class="price-line"><span class="price-name"><?= e($it['name']) ?><?php if ($it['tag'] !== ''): ?> <span class="price-tag"><?= e($it['tag']) ?></span><?php endif; ?></span><span class="price-dots" aria-hidden="true"></span><span class="price-value"><?= e($price((string) $it['price'])) ?></span></div>
          <?php if ($it['description'] !== ''): ?><p class="price-desc"><?= e($it['description']) ?></p><?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endforeach; ?>
    <?php if ($d['note'] !== ''): ?><p class="price-note muted"><?= e($d['note']) ?></p><?php endif; ?>
  </div>
</section>
<?php endif; ?>
