<?php
/** @var bool $isSuper @var array $siteBackups @var array $globalBackups @var array $otherBackups @var array $allSiteBackups @var array $platform
 *  @var int $lastAuto @var bool $canEncrypt @var string $uploadMax @var string $cliPath @var array $old @var SiteService $svc */
$slug = $svc->site['slug'];

$typeBadge = function (array $b): string {
    $o = $b['type'] === 'global' ? adm_badge('Global', 'blue') : adm_badge(t('Web'), 'green');
    if ($b['auto']) $o .= ' ' . adm_badge('Auto', 'amber');
    if ($b['encrypted']) $o .= ' ' . adm_badge('🔒 ' . t('Cifrada'), 'gray');
    return $o;
};
$counts = function (array $b): string {
    $c = $b['counts'] ?? [];
    $p = [];
    foreach (['sites' => t('webs'), 'pages' => t('páginas'), 'posts' => t('entradas'), 'media' => t('medios')] as $k => $l) if (isset($c[$k])) $p[] = (int) $c[$k] . ' ' . $l;
    return implode(' · ', $p);
};

/** Tabla de copias. $restorable: mostrar «Restaurar» en la web actual. */
$table = function (array $list, bool $restorable) use ($typeBadge, $counts, $slug) {
    if (!$list) { echo '<div class="empty"><p>' . te('Todavía no hay copias aquí.') . '</p></div>'; return; } ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Copia') ?></th><th><?= te('Tipo') ?></th><th><?= te('Fecha') ?></th><th><?= te('Creada por') ?></th><th><?= te('Tamaño') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($list as $b): ?>
    <tr>
      <td data-l="<?= te('Copia') ?>"><code><?= e($b['name']) ?></code><?php if ($b['type'] === 'site' && !empty($b['site_name'])): ?><br><small class="muted"><?= e($b['site_name']) ?></small><?php endif; ?>
        <?php if ($counts($b)): ?><br><small class="muted"><?= e($counts($b)) ?></small><?php endif; ?></td>
      <td data-l="<?= te('Tipo') ?>"><?= $typeBadge($b) ?></td>
      <td data-l="<?= te('Fecha') ?>"><?= e(adm_date($b['created_at'])) ?></td>
      <td data-l="<?= te('Creada por') ?>"><?= e($b['created_by']) ?></td>
      <td data-l="<?= te('Tamaño') ?>"><?= e(bk_human_size($b['size'])) ?></td>
      <td class="row-actions">
        <a class="btn btn-sm" href="<?= ADM ?>/backups/download?name=<?= e(rawurlencode($b['name'])) ?>"><?= te('Descargar') ?></a>
        <form method="post" action="<?= ADM ?>/backups/delete" data-confirm="<?= te('¿Eliminar definitivamente esta copia?') ?>"><?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>"><button class="btn btn-sm btn-danger"><?= te('Eliminar') ?></button></form>
      </td>
    </tr>
    <?php if ($restorable && $b['type'] === 'site'): ?>
    <tr>
      <td colspan="6" style="border-top:0;padding-top:0">
        <details>
          <summary class="muted" style="cursor:pointer;font-size:.85rem"><?= te('Restaurar esta copia en «{slug}»…', ['slug' => $slug]) ?></summary>
          <form method="post" action="<?= ADM ?>/backups/restore" class="stack" style="margin-top:.6rem" data-confirm="<?= te('Se sustituirá TODO el contenido de esta web (páginas, entradas, medios, mensajes y formularios) por el de la copia. Antes se guarda una copia del estado actual. ¿Continuar?') ?>">
            <?= csrf_field() ?><input type="hidden" name="name" value="<?= e($b['name']) ?>">
            <div class="row">
              <label class="grow"><?= th('Escribe <b>{slug}</b> para confirmar', ['slug' => $slug]) ?><input type="text" name="confirm" required autocomplete="off" placeholder="<?= e($slug) ?>"></label>
              <?php if ($b['encrypted']): ?><label class="grow"><?= te('Contraseña de la copia') ?><input type="password" name="password" required autocomplete="off"></label><?php endif; ?>
            </div>
            <div><button class="btn btn-sm btn-primary"><?= te('Restaurar') ?></button></div>
          </form>
        </details>
      </td>
    </tr>
    <?php endif; ?>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php };
?>
<div class="page-head"><h1><?= te('Copias de seguridad') ?></h1></div>
<p class="muted"><?= th('Las copias se guardan en <code>data/backups/</code>, fuera de la carpeta pública del servidor. Una copia de web incluye su contenido, medios, mensajes, formularios y código personalizado; <b>nunca</b> contraseñas ni tokens.') ?></p>

<h2 class="sec"><?= te('Copias de «{name}»', ['name' => $svc->site['name']]) ?></h2>
<section class="card">
  <h3 style="margin-top:0"><?= te('Crear copia de esta web') ?></h3>
  <form method="post" action="<?= ADM ?>/backups/create" class="stack">
    <?= csrf_field() ?>
    <?php if ($canEncrypt): ?>
    <div class="row">
      <label class="grow"><?= te('Contraseña') ?> <small class="muted"><?= te('(opcional: cifra la copia con AES-256)') ?></small><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
      <label class="grow"><?= te('Repetir contraseña') ?><input type="password" name="password2" minlength="8" autocomplete="new-password"></label>
    </div>
    <p class="muted" style="margin:0"><small><?= te('Si pierdes la contraseña no hay forma de recuperar una copia cifrada.') ?></small></p>
    <?php endif; ?>
    <div><button class="btn btn-primary"><?= te('Crear copia ahora') ?></button></div>
  </form>
</section>
<?php $table($siteBackups, true); ?>

<section class="card" style="margin-top:1rem">
  <h3 style="margin-top:0"><?= te('Restaurar desde un archivo') ?></h3>
  <p class="muted"><?= th('Sube una copia (<code>.tar.gz</code> o <code>.tar.gz.enc</code>) de esta web, p. ej. descargada antes o de otra instalación. Máximo del servidor: {max}. Antes de restaurar se guarda el estado actual.', ['max' => $uploadMax]) ?></p>
  <form method="post" action="<?= ADM ?>/backups/restore" enctype="multipart/form-data" class="stack" data-confirm="<?= te('Se sustituirá TODO el contenido de esta web por el de la copia subida. Antes se guarda una copia del estado actual. ¿Continuar?') ?>">
    <?= csrf_field() ?>
    <div class="row">
      <label class="grow"><?= te('Archivo') ?><input type="file" name="file" required accept=".gz,.enc"></label>
      <label class="grow"><?= te('Contraseña') ?> <small class="muted"><?= te('(si está cifrada)') ?></small><input type="password" name="password" autocomplete="off"></label>
      <label class="grow"><?= th('Escribe <b>{slug}</b> para confirmar', ['slug' => $slug]) ?><input type="text" name="confirm" required autocomplete="off" placeholder="<?= e($slug) ?>"></label>
    </div>
    <div><button class="btn btn-danger"><?= te('Subir y restaurar') ?></button></div>
  </form>
</section>

<?php $o = $offsite[$svc->id()]; $latest = (string) ($siteBackups[0]['name'] ?? ''); include __DIR__ . '/_offsite.php'; ?>

<?php if ($isSuper): ?>
<h2 class="sec"><?= te('Copias globales (toda la instalación)') ?></h2>
<section class="card">
  <h3 style="margin-top:0"><?= te('Crear copia global') ?></h3>
  <p class="muted"><?= th('Base de datos completa, <code>config.php</code> (con la clave secreta, por eso es sensible) y todos los medios de todas las webs.') ?></p>
  <form method="post" action="<?= ADM ?>/backups/create" class="stack">
    <?= csrf_field() ?><input type="hidden" name="global" value="1">
    <?php if ($canEncrypt): ?>
    <div class="row">
      <label class="grow"><?= te('Contraseña') ?> <small class="muted"><?= te('(muy recomendable)') ?></small><input type="password" name="password" minlength="8" autocomplete="new-password"></label>
      <label class="grow"><?= te('Repetir contraseña') ?><input type="password" name="password2" minlength="8" autocomplete="new-password"></label>
    </div>
    <?php endif; ?>
    <div><button class="btn btn-primary"><?= te('Crear copia global') ?></button></div>
  </form>
</section>
<?php $table($globalBackups, false); ?>
<p class="muted"><small><?= th('Una copia global <b>no se restaura desde el panel</b> (la instalación está en marcha). Se hace por línea de comandos: <code>php {cli} backup:restore --file=NOMBRE --yes [--password=…] [--with-config]</code>', ['cli' => $cliPath]) ?></small></p>

<?php $o = $offsite[0]; $latest = (string) ($globalBackups[0]['name'] ?? ''); include __DIR__ . '/_offsite.php'; ?>

<?php if ($otherBackups): ?>
<h2 class="sec"><?= te('Copias de otras webs') ?></h2>
<?php $table($otherBackups, false); ?>
<?php endif; ?>

<h2 class="sec"><?= te('Copias automáticas') ?></h2>
<section class="card">
  <form method="post" action="<?= ADM ?>/backups/settings" class="stack">
    <?= csrf_field() ?>
    <label class="check"><input type="checkbox" name="auto_backup" value="1" <?= !empty($platform['auto_backup']) ? 'checked' : '' ?>> <?= te('Hacer una copia global diaria automática (sin cifrar)') ?></label>
    <div class="row">
      <label><?= te('Copias automáticas a conservar') ?><input type="number" name="backup_keep" min="1" max="60" value="<?= (int) $platform['backup_keep'] ?>" style="width:7rem"></label>
    </div>
    <p class="muted" style="margin:0"><small><?= te('Última copia automática: {date}. Se ejecuta al servir una visita (con PHP-FPM) cuando han pasado 24 h.', ['date' => $lastAuto > 0 ? adm_date(date('Y-m-d H:i:s', $lastAuto)) : t('ninguna todavía')]) ?></small></p>
    <div><button class="btn btn-primary"><?= te('Guardar') ?></button></div>
  </form>
  <h3><?= te('Con cron (recomendado en hosting)') ?></h3>
  <p class="muted"><?= te('Añade esta línea al cron del servidor para hacer la copia cada día a las 3:00:') ?></p>
  <div class="copy-row"><pre id="bk-cron">0 3 * * * php <?= e($cliPath) ?> backup --auto</pre><button type="button" class="btn btn-sm" data-copy-target="#bk-cron"><?= te('Copiar') ?></button></div>
</section>

<h2 class="sec"><?= te('Importar una web como nueva') ?></h2>
<section class="card">
  <p class="muted"><?= te('Crea una web nueva a partir de una copia de web: útil para migrar o clonar entre instalaciones. Los usuarios se reasignan solo si ya existen aquí (por email).') ?></p>
  <form method="post" action="<?= ADM ?>/backups/import" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>
    <div class="row">
      <label class="grow"><?= te('Copia existente') ?>
        <select name="name"><option value=""><?= te('— elegir —') ?></option>
          <?php foreach ($allSiteBackups as $b): ?><option value="<?= e($b['name']) ?>"><?= e($b['name']) ?> (<?= e(bk_human_size($b['size'])) ?>)</option><?php endforeach; ?>
        </select></label>
      <label class="grow"><?= te('…o subir un archivo') ?><input type="file" name="file" accept=".gz,.enc"></label>
    </div>
    <div class="row">
      <label class="grow"><?= te('Identificador (slug) nuevo') ?><input type="text" name="slug" required pattern="[a-z0-9-]+" value="<?= e($old['slug'] ?? '') ?>"></label>
      <label class="grow"><?= te('Contraseña') ?> <small class="muted"><?= te('(si está cifrada)') ?></small><input type="password" name="password" autocomplete="off"></label>
    </div>
    <label><?= te('Dominios de la web nueva (uno por línea)') ?><textarea name="domains" rows="2" class="mono" required placeholder="nueva.ejemplo.com"><?= e($old['domains'] ?? '') ?></textarea></label>
    <div><button class="btn btn-primary"><?= te('Importar como web nueva') ?></button></div>
  </form>
</section>
<?php endif; ?>
<script>
document.querySelectorAll('.offsite').forEach(function (card) {
  var sel = card.querySelector('.offsite-target');
  function sync() { card.querySelectorAll('.offsite-fields').forEach(function (f) { f.hidden = f.getAttribute('data-target') !== sel.value; }); }
  sel.addEventListener('change', sync); sync();
});
</script>
