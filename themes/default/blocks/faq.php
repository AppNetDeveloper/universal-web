<section class="section">
  <div class="container narrow">
    <?php if ($d['title']): ?><div class="section-head"><h2><?= e($d['title']) ?></h2></div><?php endif; ?>
    <div class="faq">
      <?php foreach ($d['items'] as $it): ?>
      <details>
        <summary><?= e($it['question']) ?></summary>
        <div class="prose"><?= md($it['answer']) ?></div>
      </details>
      <?php endforeach; ?>
    </div>
  </div>
</section>
