<section class="section-tight">
  <div class="container">
    <div class="cta">
      <?php if ($d['title']): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
      <?php if ($d['text']): ?><p><?= e($d['text']) ?></p><?php endif; ?>
      <?php if ($d['button_text'] && $d['button_link']): ?><a class="btn btn-light" href="<?= e($d['button_link']) ?>"><?= e($d['button_text']) ?></a><?php endif; ?>
    </div>
  </div>
</section>
