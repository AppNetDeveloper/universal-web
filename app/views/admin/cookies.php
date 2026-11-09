<?php /** @var array $ck @var SiteService $svc */
$trackers = cookies_trackers($ck); ?>
<div class="page-head"><h1><?= te('Cookies') ?></h1>
  <div class="actions"><a class="btn" target="_blank" rel="noopener" href="<?= e(cookies_policy_href($svc, $ck)) ?>"><?= te('Ver política de cookies') ?> <?= adm_icon('ext') ?></a></div></div>

<form method="post" action="<?= ADM ?>/cookies" class="stack">
  <?= csrf_field() ?>
  <section class="card">
    <h2><?= te('Aviso de cookies') ?> <?= $ck['enabled'] ? adm_badge(t('Activo'), 'green') : adm_badge(t('Desactivado')) ?></h2>
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= $ck['enabled'] ? 'checked' : '' ?>> <?= te('Mostrar el aviso de cookies en la web') ?></label>
    <p class="hint"><?= te('Con analítica o marketing configurados el aviso es obligatorio (RGPD y LSSI) y se activa solo. Las herramientas no se cargan hasta que el visitante acepta.') ?></p>
    <div class="row">
      <label><?= te('Formato') ?><select name="position">
        <option value="bottom" <?= $ck['position'] === 'bottom' ? 'selected' : '' ?>><?= te('Barra inferior') ?></option>
        <option value="box" <?= $ck['position'] === 'box' ? 'selected' : '' ?>><?= te('Caja en la esquina') ?></option></select></label>
      <label class="grow"><?= te('Política de cookies propia') ?> <small class="muted"><?= th('(vacío = <code>/cookies</code> generada automáticamente)') ?></small>
        <input type="text" name="policy_url" value="<?= e($ck['policy_url']) ?>" placeholder="/politica-de-cookies"></label>
    </div>
    <label><?= te('Texto del aviso') ?> <small class="muted"><?= te('(vacío = texto estándar, traducido a cada idioma)') ?></small>
      <textarea name="text" rows="2" maxlength="1000" placeholder="<?= e(__('Usamos cookies propias necesarias para que la web funcione y, si nos dejas, cookies de analítica y marketing para mejorarla. Puedes aceptarlas, rechazarlas o elegir.')) ?>"><?= e($ck['text']) ?></textarea></label>
  </section>

  <section class="card">
    <h2><?= te('Analítica y marketing') ?></h2>
    <p class="hint"><?= te('Pega solo el identificador. Universal Web carga cada herramienta únicamente después del consentimiento y la retira si el visitante lo revoca.') ?></p>
    <div class="row">
      <label class="grow">Google Analytics 4 <small class="muted">(<?= te('analítica') ?>)</small><input type="text" name="ga4" value="<?= e($ck['ga4']) ?>" placeholder="G-ABC123XYZ" class="mono"></label>
      <label class="grow">Google Tag Manager <small class="muted">(Consent Mode v2)</small><input type="text" name="gtm" value="<?= e($ck['gtm']) ?>" placeholder="GTM-ABCD12" class="mono"></label>
    </div>
    <div class="row">
      <label class="grow">Microsoft Clarity <small class="muted">(<?= te('analítica') ?>)</small><input type="text" name="clarity" value="<?= e($ck['clarity']) ?>" placeholder="abc123def4" class="mono"></label>
      <label class="grow">Meta Pixel <small class="muted">(<?= te('marketing') ?>)</small><input type="text" name="meta_pixel" value="<?= e($ck['meta_pixel']) ?>" placeholder="1234567890123" class="mono"></label>
    </div>
    <p class="hint"><?= th('¿Otra herramienta? En <a href="{url}">Diseño y código</a> añade su script como <code>&lt;script type="text/plain" data-consent="analytics"&gt;…&lt;/script&gt;</code> (o <code>marketing</code>): solo se ejecutará con el consentimiento.', ['url' => ADM . '/design']) ?></p>
  </section>

  <section class="card">
    <h2><?= te('Consentimientos') ?></h2>
    <p><?= te('Versión actual del aviso: {n}. Los visitantes guardan su elección 12 meses; si cambias las herramientas, se les vuelve a preguntar.', ['n' => (int) $ck['revision']]) ?></p>
    <label class="check"><input type="checkbox" name="reset_consent" value="1"> <?= te('Volver a pedir el consentimiento a todos (por ejemplo, tras añadir una herramienta nueva)') ?></label>
  </section>
  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar') ?></button></div>
</form>
<?php if ($trackers): ?><p class="hint"><?= te('Herramientas activas: {list}', ['list' => implode(', ', array_map(fn($k) => COOKIE_TRACKERS[$k][3], array_keys($trackers)))]) ?></p><?php endif; ?>
