<?php /* Cabecera común de sección: antetítulo, título (H2) y subtítulo. Recibe $d. */ ?>
<?php if (($d['eyebrow'] ?? '') !== '' || ($d['title'] ?? '') !== '' || ($d['subtitle'] ?? '') !== ''): ?>
<div class="section-head">
  <?php if (($d['eyebrow'] ?? '') !== ''): ?><p class="eyebrow"><?= e($d['eyebrow']) ?></p><?php endif; ?>
  <?php if (($d['title'] ?? '') !== ''): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
  <?php if (($d['subtitle'] ?? '') !== ''): ?><p class="lead"><?= e($d['subtitle']) ?></p><?php endif; ?>
</div>
<?php endif; ?>
