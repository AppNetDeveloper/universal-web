<?php /* Cota: llamada a la acción como cajetín de plano (tabla con bordes y regla graduada). */ ?>
<section class="section-tight block-cta">
  <div class="container">
    <div class="cta">
      <div class="cta-main">
        <?php if ($d['title']): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
        <?php if ($d['text']): ?><p><?= e($d['text']) ?></p><?php endif; ?>
      </div>
      <?php if ($d['button_text'] && $d['button_link']): ?><div class="cta-act"><a class="btn" href="<?= e($d['button_link']) ?>"><?= e($d['button_text']) ?></a></div><?php endif; ?>
    </div>
  </div>
</section>
