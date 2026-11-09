<?php /** @var array $base @var array $langs @var array $have @var SiteService $svc */
$main = $svc->defaultLang();
$extra = array_values(array_diff($langs, [$main]));
$lines = fn(array $items) => implode("\n", array_map(fn($m) => $m['label'] . ' | ' . $m['url'], $items));
$count = fn(string $t, string $l) => count(array_filter($have[$t] ?? [], fn($ls) => isset($ls[$l])));
$totalPages = count($have['pages'] ?? []);
$totalPosts = count($have['posts'] ?? []); ?>
<div class="page-head"><h1><?= te('Idiomas') ?></h1></div>

<section class="card">
  <h2><?= te('Idiomas de la web') ?></h2>
  <p><?= th('Idioma principal: <b>{name}</b> (sin prefijo en la URL). Se cambia en <a href="{url}">Ajustes</a>.', ['name' => LANG_NAMES[$main] ?? $main, 'url' => ADM . '/settings']) ?></p>
  <form method="post" action="<?= ADM ?>/languages" class="stack">
    <?= csrf_field() ?>
    <fieldset><legend><?= te('Idiomas adicionales') ?></legend>
      <div class="check-grid">
      <?php foreach (LANG_NAMES as $code => $name): if ($code === $main) continue; ?>
        <label class="check"><input type="checkbox" name="languages[]" value="<?= e($code) ?>" <?= in_array($code, $extra, true) ? 'checked' : '' ?>> <?= e($name) ?> <code>/<?= e($code) ?>/</code></label>
      <?php endforeach; ?>
      </div>
    </fieldset>
    <p class="hint"><?= te('Un idioma no aparece en la web pública (selector, hreflang, sitemap) hasta que tenga su portada publicada. Quitar un idioma no borra su contenido.') ?></p>
    <div><button class="btn btn-primary"><?= te('Guardar idiomas') ?></button></div>
  </form>
</section>

<?php if ($extra): ?>
<section class="card">
  <h2><?= te('Estado de las traducciones') ?></h2>
  <div class="table-wrap"><table>
    <thead><tr><th><?= te('Idioma') ?></th><th><?= te('Páginas') ?></th><th><?= te('Entradas') ?></th><th><?= te('Portada') ?></th><th></th></tr></thead>
    <tbody>
    <?php foreach ($langs as $l): $p = $count('pages', $l); $b = $count('posts', $l); $home = isset($have['pages']['index'][$l]); ?>
      <tr>
        <td data-l="<?= te('Idioma') ?>"><b><?= e(LANG_NAMES[$l] ?? $l) ?></b><?= $l === $main ? ' ' . adm_badge(t('Principal'), 'blue') : '' ?></td>
        <td data-l="<?= te('Páginas') ?>"><?= $p ?> / <?= $totalPages ?></td>
        <td data-l="<?= te('Entradas') ?>"><?= $b ?> / <?= $totalPosts ?></td>
        <td data-l="<?= te('Portada') ?>"><?= $home ? adm_badge(t('Sí'), 'green') : adm_badge(t('Falta'), 'red') ?></td>
        <td class="row-actions"><a class="btn btn-sm" href="<?= ADM ?>/pages"><?= te('Traducir páginas') ?></a> <a class="btn btn-sm" href="<?= ADM ?>/posts?lang=<?= e($l) ?>"><?= te('Entradas') ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="hint"><?= th('Truco: con un token de IA (MCP) puedes pedir «traduce toda la web al inglés»; la IA usa <code>translation_status</code> y guarda cada página con el mismo slug.') ?></p>
</section>

<?php foreach ($extra as $l): $tr = is_array($base['i18n'][$l] ?? null) ? $base['i18n'][$l] : []; ?>
<section class="card" id="lang-<?= e($l) ?>">
  <h2><?= te('Textos de la web en {lang}', ['lang' => LANG_NAMES[$l] ?? $l]) ?></h2>
  <p class="hint"><?= te('Lo que dejes vacío se muestra en el idioma principal. Menús vacíos = los del idioma principal con el prefijo /{code}/.', ['code' => $l]) ?></p>
  <form method="post" action="<?= ADM ?>/languages/texts" class="stack">
    <?= csrf_field() ?>
    <input type="hidden" name="lang" value="<?= e($l) ?>">
    <div class="row">
      <label class="grow"><?= te('Eslogan') ?><input type="text" name="tagline" value="<?= e($tr['tagline'] ?? '') ?>" placeholder="<?= e($base['tagline']) ?>"></label>
      <label class="grow"><?= te('Texto del pie') ?><input type="text" name="footer_text" value="<?= e($tr['footer_text'] ?? '') ?>" placeholder="<?= e($base['footer_text']) ?>"></label>
    </div>
    <label><?= te('Descripción SEO por defecto') ?><textarea name="seo_description" rows="2" maxlength="500" placeholder="<?= e($base['seo']['description']) ?>"><?= e($tr['seo']['description'] ?? '') ?></textarea></label>
    <div class="row">
      <label class="grow"><?= te('Menú principal') ?> <small class="muted"><?= te('(una línea por enlace: Texto | /ruta)') ?></small>
        <textarea name="menu" rows="5" class="mono" placeholder="<?= e($lines(array_map(fn($m) => ['label' => $m['label'], 'url' => str_starts_with($m['url'], '/') ? $svc->langPath($m['url'], $l) : $m['url']], $base['menu']))) ?>"><?= e($lines($tr['menu'] ?? [])) ?></textarea></label>
      <label class="grow"><?= te('Menú secundario') ?> <small class="muted"><?= te('(barra superior)') ?></small>
        <textarea name="utility_menu" rows="5" class="mono" placeholder="<?= e($lines($base['utility_menu'])) ?>"><?= e($lines($tr['utility_menu'] ?? [])) ?></textarea></label>
    </div>
    <div class="row">
      <label class="grow"><?= te('Botón destacado: texto') ?><input type="text" name="cta_label" value="<?= e($tr['cta']['label'] ?? '') ?>" placeholder="<?= e($base['cta']['label']) ?>"></label>
      <label class="grow"><?= te('Botón destacado: enlace') ?><input type="text" name="cta_url" value="<?= e($tr['cta']['url'] ?? '') ?>" placeholder="/<?= e($l) ?>/contacto"></label>
    </div>
    <label><?= te('Texto del aviso de cookies') ?> <small class="muted"><?= te('(vacío = texto estándar traducido)') ?></small><textarea name="cookies_text" rows="2" maxlength="1000"><?= e($tr['cookies_text'] ?? '') ?></textarea></label>
    <div><button class="btn btn-primary"><?= te('Guardar textos en {lang}', ['lang' => LANG_NAMES[$l] ?? $l]) ?></button></div>
  </form>
</section>
<?php endforeach; endif; ?>
