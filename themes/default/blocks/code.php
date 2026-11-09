<section class="section-tight">
  <div class="container narrow">
    <?php if ($d['title']): ?><h2><?= e($d['title']) ?></h2><?php endif; ?>
    <div class="codeblock">
      <div class="code-bar">
        <span><?= e($d['language']) ?></span>
        <button type="button" class="copy-btn" data-copy><?= e(__('Copiar')) ?></button>
      </div>
      <pre><code><?= e($d['code']) ?></code></pre>
    </div>
  </div>
</section>
