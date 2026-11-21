<?php /* Fichas de inmueble: foto 4:3 con la etiqueta como píldora sólida; sin foto, un marcador con líneas de horizonte. */ ?>
<section class="section block-cards">
  <div class="container">
    <?php include theme_file($site['theme'], 'blocks/_head.php'); ?>
    <div class="grid cards">
      <?php foreach ($d['items'] as $it): $tag = $it['link'] !== '' ? 'a' : 'article'; ?>
      <<?= $tag ?> class="card media-card property reveal"<?= $tag === 'a' ? ' href="' . e($it['link']) . '"' : '' ?>>
        <div class="media-card-img<?= $it['image'] ? '' : ' is-ph' ?>">
          <?php if ($it['image']): ?><img<?= img_attrs($it['image'], '(max-width: 700px) 100vw, 33vw') ?> alt="<?= e($it['alt'] ?: $it['title']) ?>" loading="lazy" decoding="async">
          <?php else: ?><svg class="ph" viewBox="0 0 320 240" preserveAspectRatio="xMidYMid slice" aria-hidden="true" focusable="false"><circle cx="236" cy="122" r="30" fill="currentColor" opacity=".3"/><g fill="none" stroke="currentColor" stroke-width="2"><path d="M0 138h320" opacity=".8"/><path d="M0 158h320" opacity=".55"/><path d="M0 178h320" opacity=".35"/><path d="M0 198h320" opacity=".2"/></g><path d="M64 138v-34l30-24 30 24v34z" fill="currentColor" opacity=".45"/></svg><?php endif; ?>
          <?php if ($it['tag']): ?><span class="chip"><?= e($it['tag']) ?></span><?php endif; ?>
        </div>
        <div class="card-body">
          <?php if ($it['title']): ?><h3><?= e($it['title']) ?></h3><?php endif; ?>
          <?php if ($it['text']): ?><p><?= e($it['text']) ?></p><?php endif; ?>
        </div>
      </<?= $tag ?>>
      <?php endforeach; ?>
    </div>
  </div>
</section>
