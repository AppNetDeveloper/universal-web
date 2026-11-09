<section class="section block-testimonials">
  <div class="container">
    <?php if ($d['title']): ?><div class="section-head"><h2><?= e($d['title']) ?></h2></div><?php endif; ?>
    <div class="grid testimonials<?= count($d['items']) === 1 ? ' single' : '' ?>">
      <?php foreach ($d['items'] as $it): ?>
      <figure class="testimonial reveal">
        <blockquote><p><?= e($it['quote']) ?></p></blockquote>
        <figcaption>
          <?php if ($it['image']): ?><img<?= img_attrs($it['image'], '48px') ?> alt="" loading="lazy" decoding="async"><?php endif; ?>
          <span><strong><?= e($it['name']) ?></strong><?php if ($it['role']): ?><small><?= e($it['role']) ?></small><?php endif; ?></span>
        </figcaption>
      </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
