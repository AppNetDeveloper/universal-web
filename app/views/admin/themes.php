<?php /** @var array $themes @var string $current @var bool $uploadAllowed @var bool $has2fa @var string $role @var SiteService $svc */ ?>
<div class="page-head"><h1><?= te('Temas') ?></h1></div>
<p class="muted"><?= te('El tema decide cómo se ve la web; el contenido, el SEO, la API JSON y el MCP no cambian. Prueba cualquier tema con «Vista previa» sin tocar la web publicada.') ?></p>

<div class="theme-grid">
<?php foreach ($themes as $t): $active = $t['slug'] === $current; ?>
  <section class="card theme-card<?= $active ? ' is-active' : '' ?>" id="theme-<?= e($t['slug']) ?>">
    <div class="theme-shot"><?php if ($t['screenshot']): ?><img src="<?= e($t['screenshot']) ?>" alt="" loading="lazy"><?php else: ?><span><?= e(mb_substr($t['name'], 0, 1)) ?></span><?php endif; ?></div>
    <h2><?= e($t['name']) ?> <?= $active ? adm_badge(t('En uso'), 'green') : '' ?> <?= $t['builtin'] ? adm_badge(t('De serie'), 'blue') : adm_badge(t('Subido')) ?></h2>
    <?php if ($t['description']): ?><p><?= e(t($t['description'])) ?></p><?php endif; ?>
    <?php if ($t['best_for']): ?><p class="hint"><?= te('Ideal para:') ?> <?= e(implode(', ', array_map('t', array_slice($t['best_for'], 0, 5)))) ?>
      <?php if ($t['suggested_accent']): ?> · <span class="swatch" style="background:<?= e($t['suggested_accent']) ?>" title="<?= e($t['suggested_accent']) ?>"></span><?php endif; ?></p><?php endif; ?>
    <p class="hint"><?= te('Versión {v}', ['v' => $t['version']]) ?><?= $t['author'] ? ' · ' . e($t['author']) : '' ?> · <code><?= e($t['slug']) ?></code>
      <?php if ($t['sites']): ?><br><?= te('Lo usan: {list}', ['list' => implode(', ', $t['sites'])]) ?><?php endif; ?></p>
    <div class="row-actions">
      <?php if (!$active): ?>
        <a class="btn btn-sm" target="_blank" rel="noopener" href="<?= e(theme_preview_link($svc, $t['slug'])) ?>"><?= te('Vista previa') ?> <?= adm_icon('ext') ?></a>
        <form method="post" action="<?= ADM ?>/themes/apply" data-confirm="<?= te('¿Aplicar el tema «{name}» a esta web? Se verá al instante.', ['name' => $t['name']]) ?>"><?= csrf_field() ?><input type="hidden" name="theme" value="<?= e($t['slug']) ?>"><button class="btn btn-sm btn-primary"><?= te('Aplicar') ?></button></form>
      <?php endif; ?>
      <a class="btn btn-sm" href="<?= ADM ?>/themes/download?theme=<?= e(rawurlencode($t['slug'])) ?>"><?= te('Descargar con manual') ?></a>
      <?php if ($role === 'super' && !$t['builtin'] && !$t['sites']): ?>
        <form method="post" action="<?= ADM ?>/themes/delete" data-confirm="<?= te('¿Borrar el tema «{name}»? Se guarda una copia en data/theme-backups.', ['name' => $t['name']]) ?>"><?= csrf_field() ?><input type="hidden" name="theme" value="<?= e($t['slug']) ?>"><button class="btn btn-sm btn-danger"><?= te('Borrar') ?></button></form>
      <?php endif; ?>
    </div>
  </section>
<?php endforeach; ?>
</div>

<div class="grid-2">
<section class="card">
  <h2><?= te('Crear un tema propio') ?></h2>
  <ol class="plain-ol">
    <li><?= th('Descarga el tema más parecido a lo que quieres (<b>Default</b> trae todas las plantillas). El .zip incluye <code>MANUAL.md</code>.') ?></li>
    <li><?= th('Cambia el <code>"slug"</code> y el <code>"name"</code> de <code>theme.json</code> y edita <code>layout.php</code>, los bloques de <code>blocks/</code> y <code>assets/style.css</code>.') ?></li>
    <li><?= te('Súbelo aquí, pruébalo con «Vista previa» y aplícalo. El back (contenido, SEO, MCP, API) sigue igual.') ?></li>
  </ol>
  <p class="hint"><?= th('¿Solo otro diseño, sin programar? Usa la <a href="{url}">Plantilla HTML</a> de la web: HTML con marcadores, sin código en el servidor (también por MCP).', ['url' => ADM . '/template']) ?></p>
  <p class="hint"><?= th('¿Front totalmente distinto (React, Astro, Next…)? No hace falta tema: lee la API JSON pública (<code>/api/site.json</code>, <code>/api/pages/…</code>, <code>/api/schema.json</code>).') ?></p>
</section>

<section class="card">
  <h2><?= te('Subir un tema') ?></h2>
  <?php if ($role !== 'super'): ?>
    <p class="muted"><?= te('Solo un superadmin puede instalar temas.') ?></p>
  <?php elseif (!$uploadAllowed): ?>
    <p><?= te('La subida de temas está desactivada en este servidor (un tema es código PHP que se ejecuta en él).') ?></p>
    <p class="hint"><?= th('Para activarla, añade <code>\'allow_theme_upload\' => true,</code> en <code>data/config.php</code>. También puedes copiar la carpeta del tema a <code>themes/</code> por FTP/SSH.') ?></p>
  <?php elseif (!$has2fa): ?>
    <p><?= th('Para subir temas necesitas la <a href="{url}">verificación en dos pasos</a> activa.', ['url' => ADM . '/account#dos-pasos']) ?></p>
  <?php else: ?>
    <form method="post" action="<?= ADM ?>/themes/upload" enctype="multipart/form-data" class="stack">
      <?= csrf_field() ?>
      <label><?= te('Archivo .zip o .tar.gz') ?><input type="file" name="file" accept=".zip,.gz,.tgz,application/zip,application/gzip" required></label>
      <label class="check"><input type="checkbox" name="replace" value="1"> <?= te('Sustituir si ya existe un tema subido con ese slug (se guarda copia del anterior)') ?></label>
      <label class="check"><input type="checkbox" name="understand" value="1" required> <?= te('Entiendo que un tema ejecuta código en el servidor y que solo debo subir temas de fuentes de confianza.') ?></label>
      <p class="hint"><?= te('Se revisa antes de instalar: estructura, tipos de archivo, sintaxis PHP y funciones peligrosas. Es una red de seguridad, no un antivirus.') ?></p>
      <div><button class="btn btn-primary"><?= te('Revisar e instalar') ?></button></div>
    </form>
  <?php endif; ?>
</section>
</div>
