<?php /** @var string $manual @var array $toc — manual de usuario (docs/MANUAL.md) */ ?>
<div class="manual-wrap">
  <nav class="manual-toc" aria-label="<?= te('Índice') ?>">
    <button type="button" class="btn btn-primary manual-pdf" id="manual-pdf"><?= adm_icon('pdf') ?> <?= te('Descargar PDF') ?></button>
    <p class="manual-toc-title"><?= te('Índice') ?></p>
    <ol><?php foreach ($toc as [$id, $label]): ?><li><a href="#<?= e($id) ?>"><?= e(preg_replace('/^\d+\.\s*/', '', $label)) ?></a></li><?php endforeach; ?></ol>
  </nav>
  <article class="manual">
    <div class="manual-cover" aria-hidden="true"><p>Universal Web</p><h1><?= te('Manual de usuario') ?></h1><p><?= e(date('d/m/Y')) ?> · v<?= e(UW_VERSION) ?></p></div><?= $manual ?></article>
</div>
