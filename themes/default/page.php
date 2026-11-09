<?php
$hasHero = false;
foreach ($page['blocks'] as $b) if ($b['type'] === 'hero') { $hasHero = true; break; }
if (!$hasHero): ?>
<div class="container page-head"><h1><?= e($page['title']) ?></h1></div>
<?php endif;
echo render_blocks($svc, $page['blocks']);
