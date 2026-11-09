<?php /** Idioma del contenido que se edita y aviso de traducción. Recibe $item (página o entrada), $from, $translations, $kind ('pages'|'posts'). */
$langs = $svc->langs();
$lang = (string) ($item['lang'] ?? $svc->defaultLang()); ?>
<input type="hidden" name="lang" value="<?= e($lang) ?>">
<?php if (count($langs) > 1): ?>
<div class="lang-bar">
  <span><?= te('Idioma:') ?> <b><?= e(LANG_NAMES[$lang] ?? $lang) ?></b></span>
  <?php foreach ($langs as $l): if ($l === $lang) continue; $st = $translations[$l] ?? null; ?>
    <?php if ($st && $item['slug'] !== ''): $other = row("SELECT id FROM $kind WHERE site_id = ? AND lang = ? AND slug = ?", [$svc->id(), $l, $item['slug']]); ?>
      <a class="badge <?= $st === 'published' ? 'badge-green' : 'badge-gray' ?>" href="<?= ADM ?>/<?= $kind ?>/edit?id=<?= (int) $other['id'] ?>"><?= e(strtoupper($l)) ?></a>
    <?php elseif ($item['id']): ?>
      <a class="badge badge-add" href="<?= ADM ?>/<?= $kind ?>/edit?translate=<?= (int) $item['id'] ?>&amp;lang=<?= e($l) ?>" title="<?= te('Traducir a {lang}', ['lang' => LANG_NAMES[$l] ?? $l]) ?>">+ <?= e(strtoupper($l)) ?></a>
    <?php endif; ?>
  <?php endforeach; ?>
</div>
<?php if ($from): ?>
<p class="flash flash-ok"><?= th('Traducción a <b>{lang}</b> de «{title}»: el contenido es una copia del original. Reescribe los textos (y los enlaces internos con <code>/{code}/</code>), escribe el título y la descripción SEO en este idioma y guarda. Mantén el mismo slug para que se enlacen como traducciones.', ['lang' => LANG_NAMES[$lang] ?? $lang, 'title' => $from['title'], 'code' => $lang]) ?></p>
<?php endif; endif; ?>
