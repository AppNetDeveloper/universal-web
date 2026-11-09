<?php /** Destino de copias fuera del servidor. Recibe $o (offsite_view), $isSuper y $latest (nombre de la última copia del ámbito o ''). */
$cfg = $o['cfg'];
$g = $o['scope'] === 0;
$id = 'offsite-' . ($g ? 'global' : $o['scope']);
$hidden = $g ? '<input type="hidden" name="global" value="1">' : '';
$sel = fn(string $k, string $v) => $cfg[$k] === $v ? ' selected' : '';
$secret = fn(string $k) => $o['has'][$k] ? e(t('•••••••• guardada')) : ''; ?>
<section class="card offsite" id="<?= $id ?>">
  <h3 style="margin-top:0"><?= te($g ? 'Copias globales fuera del servidor' : 'Copia fuera del servidor') ?>
    <?= $cfg['enabled'] ? adm_badge(t('Activa'), 'green') : adm_badge(t('Desactivada')) ?>
    <?php if ($o['last_status']): ?><?= $o['last_status'] === 'ok' ? adm_badge(t('Último envío correcto'), 'green') : adm_badge(t('Último envío con error'), 'red') ?><?php endif; ?></h3>
  <p class="muted"><?= te($g
      ? 'Si el servidor se rompe, las copias de data/backups se pierden con él. Envía también las copias globales a otro sitio.'
      : 'Si el servidor se rompe, las copias de data/backups se pierden con él. Cada copia de esta web se envía también a este destino.') ?></p>
  <?php if ($o['last_at']): ?>
    <details><summary class="muted"><?= te('Último envío:') ?> <?= e(adm_date($o['last_at'])) ?></summary>
      <pre class="mono" style="white-space:pre-wrap"><?= e(implode("\n", array_map('t_msg', explode("\n", (string) $o['last_log'])))) ?></pre></details>
  <?php endif; ?>
  <form method="post" action="<?= ADM ?>/backups/offsite" class="stack" autocomplete="off">
    <?= csrf_field() ?><?= $hidden ?>
    <label class="check"><input type="checkbox" name="enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>> <?= te('Enviar las copias a este destino') ?></label>
    <div class="row">
      <label class="grow"><?= te('Destino') ?>
        <select name="target" class="offsite-target">
          <option value="sftp"<?= $sel('target', 'sftp') ?><?= $o['can']['sftp'] ? '' : ' disabled' ?>><?= te('SFTP / SSH (otro servidor, NAS…)') ?><?= $o['can']['sftp'] ? '' : e(' — ' . t('no disponible aquí')) ?></option>
          <option value="ftp"<?= $sel('target', 'ftp') ?><?= $o['can']['ftp'] ? '' : ' disabled' ?>><?= te('FTP / FTPS') ?></option>
          <option value="s3"<?= $sel('target', 's3') ?><?= $o['can']['s3'] ? '' : ' disabled' ?>><?= te('S3 y compatibles (Amazon, Cloudflare R2, Backblaze, Wasabi, MinIO)') ?></option>
          <?php if ($isSuper): ?><option value="directory"<?= $sel('target', 'directory') ?>><?= te('Carpeta de este servidor (disco externo, unidad montada)') ?></option><?php endif; ?>
        </select></label>
      <?php if (!$g): ?>
      <label><?= te('Copia programada') ?>
        <select name="schedule">
          <option value="off"<?= $sel('schedule', 'off') ?>><?= te('No (solo las que se creen)') ?></option>
          <option value="daily"<?= $sel('schedule', 'daily') ?>><?= te('Diaria') ?></option>
          <option value="weekly"<?= $sel('schedule', 'weekly') ?>><?= te('Semanal') ?></option>
        </select></label>
      <?php endif; ?>
      <label><?= te('Conservar') ?><input type="number" name="keep" min="1" max="365" value="<?= (int) $cfg['keep'] ?>" style="width:6rem"></label>
    </div>

    <fieldset class="offsite-fields" data-target="sftp"><legend>SFTP / SSH</legend>
      <div class="row">
        <label class="grow"><?= te('Servidor') ?><input type="text" name="sftp_host" value="<?= e($cfg['sftp_host']) ?>" placeholder="backup.midominio.com"></label>
        <label><?= te('Puerto') ?><input type="number" name="sftp_port" value="<?= (int) $cfg['sftp_port'] ?>" style="width:6rem"></label>
        <label class="grow"><?= te('Usuario') ?><input type="text" name="sftp_user" value="<?= e($cfg['sftp_user']) ?>"></label>
      </div>
      <label><?= te('Carpeta remota') ?><input type="text" name="sftp_path" value="<?= e($cfg['sftp_path']) ?>" placeholder="backups" class="mono"></label>
      <div class="row">
        <label class="grow"><?= te('Contraseña') ?><input type="password" name="sftp_password" autocomplete="new-password" placeholder="<?= $secret('sftp_password') ?>"<?= $o['can']['sftp_password'] ? '' : ' disabled' ?>></label>
        <label class="grow"><?= te('Huella del servidor (opcional)') ?><input type="text" name="sftp_fingerprint" value="<?= e($cfg['sftp_fingerprint']) ?>" placeholder="SHA256:…" class="mono"></label>
      </div>
      <label><?= te('Clave privada SSH') ?> <span class="hint"><?= te('(recomendado en vez de contraseña)') ?></span>
        <textarea name="sftp_key" rows="3" class="mono" placeholder="<?= $o['has']['sftp_key'] ? $secret('sftp_key') : '-----BEGIN OPENSSH PRIVATE KEY-----' ?>"></textarea></label>
      <p class="hint"><?= te('Lo más seguro: un usuario del servidor de destino solo para copias, con una clave SSH y permiso únicamente en su carpeta. La primera conexión guarda la huella del servidor y después se comprueba siempre.') ?></p>
    </fieldset>

    <fieldset class="offsite-fields" data-target="ftp"><legend>FTP / FTPS</legend>
      <div class="row">
        <label class="grow"><?= te('Servidor') ?><input type="text" name="ftp_host" value="<?= e($cfg['ftp_host']) ?>" placeholder="ftp.mihosting.com"></label>
        <label><?= te('Puerto') ?><input type="number" name="ftp_port" value="<?= (int) $cfg['ftp_port'] ?>" style="width:6rem"></label>
        <label class="grow"><?= te('Usuario') ?><input type="text" name="ftp_user" value="<?= e($cfg['ftp_user']) ?>"></label>
        <label class="grow"><?= te('Contraseña') ?><input type="password" name="ftp_password" autocomplete="new-password" placeholder="<?= $secret('ftp_password') ?>"></label>
      </div>
      <label><?= te('Carpeta remota') ?><input type="text" name="ftp_path" value="<?= e($cfg['ftp_path']) ?>" class="mono"></label>
      <label class="check"><input type="checkbox" name="ftp_tls" value="1" <?= $cfg['ftp_tls'] ? 'checked' : '' ?>> <?= te('Usar FTPS (cifrado, muy recomendado)') ?></label>
    </fieldset>

    <fieldset class="offsite-fields" data-target="s3"><legend>S3</legend>
      <div class="row">
        <label class="grow"><?= te('Bucket') ?><input type="text" name="s3_bucket" value="<?= e($cfg['s3_bucket']) ?>" placeholder="mis-copias"></label>
        <label><?= te('Región') ?><input type="text" name="s3_region" value="<?= e($cfg['s3_region']) ?>" placeholder="eu-west-1" style="width:9rem"></label>
        <label class="grow"><?= te('Carpeta (prefijo)') ?><input type="text" name="s3_prefix" value="<?= e($cfg['s3_prefix']) ?>" class="mono"></label>
      </div>
      <label><?= te('Endpoint') ?> <span class="hint"><?= te('(vacío = Amazon S3; p. ej. https://<cuenta>.r2.cloudflarestorage.com, https://s3.eu-central-003.backblazeb2.com o http://minio.local:9000 en tu red)') ?></span>
        <input type="text" name="s3_endpoint" value="<?= e($cfg['s3_endpoint']) ?>" placeholder="https://" class="mono"></label>
      <div class="row">
        <label class="grow"><?= te('Clave de acceso (Access Key ID)') ?><input type="text" name="s3_key" value="<?= e($cfg['s3_key']) ?>" class="mono"></label>
        <label class="grow"><?= te('Clave secreta') ?><input type="password" name="s3_secret" autocomplete="new-password" placeholder="<?= $secret('s3_secret') ?>"></label>
      </div>
      <label class="check"><input type="checkbox" name="s3_path_style" value="1" <?= $cfg['s3_path_style'] ? 'checked' : '' ?>> <?= te('Direcciones tipo ruta (necesario en MinIO y algunos compatibles)') ?></label>
      <p class="hint"><?= te('Crea una clave con permiso solo para escribir, listar y borrar en ese bucket. Activa el versionado o el bloqueo de objetos del bucket si quieres protección contra borrados.') ?></p>
    </fieldset>

    <?php if ($isSuper): ?>
    <fieldset class="offsite-fields" data-target="directory"><legend><?= te('Carpeta') ?></legend>
      <label><?= te('Ruta absoluta') ?><input type="text" name="directory_path" value="<?= e($cfg['directory_path']) ?>" placeholder="/mnt/copias/universal-web" class="mono"></label>
    </fieldset>
    <?php endif; ?>

    <label><?= te('Cifrar antes de enviar') ?> <span class="hint"><?= te('(contraseña opcional, mín. 8 caracteres: el destino nunca ve el contenido)') ?></span>
      <input type="password" name="encrypt_password" minlength="8" autocomplete="new-password" placeholder="<?= $secret('encrypt_password') ?>"></label>
    <?php if ($o['has']['encrypt_password']): ?><label class="check"><input type="checkbox" name="clear_encrypt_password" value="1"> <?= te('Dejar de cifrar (borrar la contraseña guardada)') ?></label><?php endif; ?>
    <p class="hint"><?= te('Guarda la contraseña de cifrado en un lugar seguro: sin ella no se pueden restaurar las copias enviadas.') ?></p>
    <div class="row">
      <button class="btn btn-primary"><?= te('Guardar destino') ?></button>
      <button class="btn" formaction="<?= ADM ?>/backups/offsite/test"><?= te('Guardar y probar conexión') ?></button>
    </div>
  </form>
  <?php if ($cfg['enabled'] && $latest !== ''): ?>
  <form method="post" action="<?= ADM ?>/backups/offsite/send" style="margin-top:.5rem"><?= csrf_field() ?><?= $hidden ?><input type="hidden" name="name" value="<?= e($latest) ?>">
    <button class="btn btn-sm"><?= te('Enviar ahora la última copia ({name})', ['name' => $latest]) ?></button></form>
  <?php endif; ?>
</section>
