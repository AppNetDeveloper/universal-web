<?php /** @var array $status @var array $state @var array $result @var array $last */
$fin = (string) ($_GET['fin'] ?? ''); ?>
<div class="page-head"><h1><?= te('Actualizaciones') ?></h1></div>
<p class="muted"><?= te('Versión instalada: {v}. Universal Web mira una vez al día si hay versión nueva. Cada versión va firmada por BionicAdri: un paquete sin firma válida no se instala nunca.', ['v' => UW_VERSION]) ?></p>

<?php if ($result): ?>
  <div class="flash flash-<?= !empty($result['ok']) ? 'ok' : 'error' ?>" role="status">
    <b><?= te(!empty($result['ok']) ? 'Última actualización' : 'La última actualización ha fallado') ?></b> (<?= e(adm_date(str_replace('T', ' ', substr((string) ($result['at'] ?? ''), 0, 19)))) ?>):
    <?= e(t_msg((string) ($result['message'] ?? ''))) ?>
  </div>
<?php elseif ($fin !== ''): ?>
  <div class="flash flash-ok" role="status"><?= te('Terminando la actualización… vuelve a cargar esta página en unos segundos.') ?></div>
<?php endif; ?>

<section class="card">
  <h2><?= $status['available'] ? te('Hay una versión nueva: {v}', ['v' => $status['latest']]) : te('Tienes la última versión') ?></h2>
  <?php if ($status['check_error']): ?><p class="field-error"><?= te('No se ha podido comprobar: {e}', ['e' => t_msg((string) $status['check_error'])]) ?></p><?php endif; ?>
  <p class="muted"><?= $status['checked_at'] ? te('Última comprobación: {d}', ['d' => adm_date(date('Y-m-d H:i:s', strtotime((string) $status['checked_at'])))]) : te('Aún no se ha comprobado') ?></p>
  <?php if ($status['available'] && $status['changelog'] !== ''): ?>
    <details open><summary><?= te('Qué cambia') ?></summary><div class="prose"><?= md(mb_substr((string) $status['changelog'], 0, 8000)) ?></div></details>
  <?php endif; ?>
  <form method="post" action="<?= ADM ?>/update/check" class="inline"><?= csrf_field() ?><button class="btn"><?= te('Comprobar ahora') ?></button></form>
</section>

<?php if ($status['available']): ?>
<section class="card">
  <h2><?= te('Actualizar a la {v}', ['v' => $status['latest']]) ?></h2>
  <?php if (!$status['can_update_from_panel']): ?>
    <p class="field-error"><?= e(t_msg((string) $status['reason'])) ?></p>
  <?php else: ?>
    <ol class="muted">
      <li><?= te('Descarga y comprobación de la firma y de la huella del paquete.') ?></li>
      <li><?= te('Copia de seguridad completa (base de datos, configuración e imágenes) y copia del código actual.') ?></li>
      <li><?= te('Modo mantenimiento: los visitantes ven «Volvemos en un momento» durante uno o dos minutos.') ?></li>
      <li><?= te('Cambio del código sin tocar tus datos, la configuración, las imágenes ni los temas que hayas modificado.') ?></li>
      <li><?= te('Migraciones de la base de datos, vaciado de la caché y prueba de que las webs se generan bien.') ?></li>
    </ol>
    <p><?= te('Si algo falla, la web vuelve sola a la versión {v} con sus datos tal cual.', ['v' => UW_VERSION]) ?></p>
    <form method="post" action="<?= ADM ?>/update/run" onsubmit="return confirm(this.dataset.q)" data-q="<?= te('¿Actualizar ahora a la {v}?', ['v' => $status['latest']]) ?>"><?= csrf_field() ?>
      <label class="check"><input type="checkbox" name="confirm" value="1" required> <?= te('Entiendo que la web estará en mantenimiento un momento') ?></label>
      <button class="btn btn-primary"><?= te('Actualizar') ?></button>
    </form>
  <?php endif; ?>
</section>
<?php endif; ?>

<section class="card">
  <h2><?= te('Actualización automática') ?></h2>
  <form method="post" action="<?= ADM ?>/update/settings"><?= csrf_field() ?>
    <?php $pol = $status['auto_update']; ?>
    <label class="check"><input type="radio" name="auto_update" value="off" <?= $pol === 'off' ? 'checked' : '' ?>> <span><?= te('Desactivada: solo aviso (recomendado si no tienes copias fuera del servidor)') ?></span></label>
    <label class="check"><input type="radio" name="auto_update" value="patch" <?= $pol === 'patch' ? 'checked' : '' ?>> <span><?= te('Solo correcciones ({v}.x)', ['v' => implode('.', array_slice(explode('.', UW_VERSION), 0, 2))]) ?></span></label>
    <label class="check"><input type="radio" name="auto_update" value="all" <?= $pol === 'all' ? 'checked' : '' ?>> <span><?= te('Todas las versiones') ?></span></label>
    <p class="hint"><?= te('Se hace en la comprobación diaria, con los mismos pasos y la misma vuelta atrás. Si el correo del panel está configurado, te avisa del resultado.') ?></p>
    <button class="btn"><?= te('Guardar') ?></button>
  </form>
</section>

<?php if ($last && ($last['to'] ?? '') === UW_VERSION): ?>
<section class="card">
  <h2><?= te('Volver a la versión anterior ({v})', ['v' => $last['from']]) ?></h2>
  <p class="muted"><?= te('Repone el código de la {v} guardado al actualizar. Tus datos de después se conservan, salvo que marques también la base de datos.', ['v' => $last['from']]) ?></p>
  <form method="post" action="<?= ADM ?>/update/rollback" onsubmit="return confirm(this.dataset.q)" data-q="<?= te('¿Volver a la versión {v}?', ['v' => $last['from']]) ?>"><?= csrf_field() ?>
    <label class="check"><input type="checkbox" name="with_db" value="1"> <span><?= te('Reponer también la base de datos de antes de actualizar (se pierde lo hecho desde entonces)') ?></span></label>
    <label class="check"><input type="checkbox" name="confirm" value="1" required> <?= te('Confirmo que quiero volver a la versión anterior') ?></label>
    <button class="btn"><?= te('Volver a la versión anterior') ?></button>
  </form>
</section>
<?php endif; ?>

<p class="muted"><small><?= th('También desde la terminal: <code>php bin/uw update:check</code>, <code>php bin/uw update --yes</code> y <code>php bin/uw update:rollback --yes</code>.') ?></small></p>
