<section class="section-tight block-cta">
  <div class="container">
    <div class="cta">
      <?php if ($d['text']): ?><p class="cta-text"><?= e($d['text']) ?></p><?php endif; ?>
      <?php if ($d['title'] && $d['button_text'] && $d['button_link']): ?>
      <h2><a class="cta-giant" href="<?= e($d['button_link']) ?>"><span class="cta-title"><?= e($d['title']) ?></span> <span class="cta-go"><span><?= e($d['button_text']) ?></span><?= uw_icon('arrow', 'icon') ?></span></a></h2>
      <?php else: ?>
      <?php if ($d['title']): ?><h2><span class="cta-title"><?= e($d['title']) ?></span></h2><?php endif; ?>
      <?php if ($d['button_text'] && $d['button_link']): ?><a class="hero-link" href="<?= e($d['button_link']) ?>"><span><?= e($d['button_text']) ?></span><?= uw_icon('arrow', 'icon icon-sm') ?></a><?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
