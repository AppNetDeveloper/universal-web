<?php /** @var array $p @var array $has @var array $state @var bool $ftpAvailable @var bool $rsyncAvailable @var bool $fpm @var string $role @var SiteService $svc */
$super = $role === 'super';
$static = $p['mode'] === 'static';
$dis = $super ? '' : ' disabled';
$opt = fn(string $field, string $v) => $p[$field] === $v ? ' selected' : ''; ?>
<div class="page-head"><h1><?= te('Publicación') ?></h1>
  <div class="actions">
    <a class="btn" href="<?= ADM ?>/publish/download"><?= te('Descargar web estática (.tar.gz)') ?></a>
    <?php if ($static && $p['target'] !== 'download'): ?>
    <form method="post" action="<?= ADM ?>/publish/run" style="display:inline"><?= csrf_field() ?><button class="btn btn-primary"><?= te('Publicar ahora') ?></button></form>
    <?php endif; ?>
  </div>
</div>

<section class="card">
  <h2><?= te('Estado') ?> <?= $static ? adm_badge(t('Estática en otro servidor'), 'blue') : adm_badge(t('Dinámica en este servidor'), 'gray') ?></h2>
  <?php if (!$static): ?>
    <p><?= th('Esta web la sirve <b>este mismo servidor</b> (modo dinámico): cada cambio se ve al instante y no hace falta publicar.') ?></p>
  <?php else: ?>
    <p><?= th('La web pública vive en <a href="{url}" target="_blank" rel="noopener">{url}</a> como HTML estático.', ['url' => $p['public_url']]) ?>
      <?= !empty($state['dirty']) ? adm_badge(t('Hay cambios sin publicar'), 'red') : adm_badge(t('Al día'), 'green') ?></p>
    <?php if ($state['last_published_at']): ?>
      <p class="muted"><?= te('Última publicación:') ?> <?= e(adm_date($state['last_published_at'])) ?> · <?= $state['last_status'] === 'ok' ? adm_badge(t('Correcta'), 'green') : adm_badge(t('Con error'), 'red') ?></p>
      <pre class="mono" style="white-space:pre-wrap"><?= e(implode("\n", array_map('t_msg', explode("\n", (string) $state['last_log'])))) ?></pre>
    <?php endif; ?>
    <?php if ($p['auto_publish'] && $p['target'] !== 'download'): ?>
      <p class="hint"><?= $fpm ? te('Publicación automática activada: tras cada cambio se publica sola en segundo plano.') : th('Publicación automática activada. Este servidor no usa PHP-FPM: programa un cron, p. ej. <code>*/5 * * * * php {root}/bin/uw publish --dirty</code>', ['root' => UW_ROOT]) ?></p>
    <?php endif; ?>
  <?php endif; ?>
  <p class="hint"><?= te('La descarga genera la web estática completa (páginas, blog, sitemap, imágenes, API JSON) para subirla a mano a cualquier hosting.') ?></p>
</section>

<form method="post" action="<?= ADM ?>/publish" class="stack">
  <?= csrf_field() ?>
  <?php if (!$super): ?><p class="muted"><?= te('Solo un superadmin puede cambiar el modo de publicación y los datos del servidor de destino.') ?></p><?php endif; ?>
  <section class="card">
    <h2><?= te('Modo') ?></h2>
    <label class="check"><input type="radio" name="mode" value="dynamic"<?= !$static ? ' checked' : '' ?><?= $dis ?>> <span><?= th('<b>Dinámico</b>: la web la sirve este servidor (lo más sencillo).') ?></span></label>
    <label class="check"><input type="radio" name="mode" value="static"<?= $static ? ' checked' : '' ?><?= $dis ?>> <span><?= th('<b>Estático en otro servidor</b>: se genera HTML y se sube al destino. Máxima seguridad y velocidad; este panel puede quedar en una red privada (ZeroTier).') ?></span></label>
    <div class="row">
      <label class="grow"><?= te('URL pública de la web') ?><input type="url" name="public_url" value="<?= e($p['public_url']) ?>" placeholder="https://www.miweb.com"<?= $dis ?>></label>
      <label class="grow"><?= te('URL pública de este servidor para el formulario') ?>
        <input type="url" name="api_url" value="<?= e($p['api_url']) ?>" placeholder="https://api.miweb.com"<?= $dis ?>></label>
    </div>
    <p class="hint"><?= th('El formulario de contacto de la web estática envía los mensajes a <code>/api/contact</code> de esta URL. Si el panel está oculto (ZeroTier), expón solo <code>/api/contact</code> y <code>/api/form-token</code> con el proxy frontal (ver <code>deploy/ZEROTIER.md</code>). Sin esta URL el formulario no funcionará.') ?></p>
  </section>

  <section class="card">
    <h2><?= te('Destino') ?></h2>
    <label><?= te('Cómo llega la web al otro servidor') ?>
      <select name="target" id="pub-target"<?= $dis ?>>
        <option value="download"<?= $opt('target', 'download') ?>><?= te('Manual: descargo el .tar.gz y lo subo yo') ?></option>
        <option value="rsync"<?= $opt('target', 'rsync') ?><?= $rsyncAvailable ? '' : ' disabled' ?>><?= te('SSH / rsync (VPS, ZeroTier…)') ?><?= $rsyncAvailable ? '' : e(' — ' . t('falta rsync aquí')) ?></option>
        <option value="ftp"<?= $opt('target', 'ftp') ?><?= $ftpAvailable ? '' : ' disabled' ?>><?= te('FTP / FTPS (hosting compartido)') ?><?= $ftpAvailable ? '' : e(' — ' . t('falta la extensión FTP')) ?></option>
        <option value="directory"<?= $opt('target', 'directory') ?>><?= te('Carpeta en este servidor (otro servidor web o disco compartido)') ?></option>
      </select></label>
    <label class="check"><input type="checkbox" name="auto_publish" value="1"<?= $p['auto_publish'] ? ' checked' : '' ?><?= $dis ?>> <?= te('Publicar automáticamente tras cada cambio') ?></label>

    <fieldset class="pub-fields" data-target="rsync"><legend><?= te('SSH / rsync') ?></legend>
      <div class="row">
        <label class="grow"><?= te('Servidor') ?><input type="text" name="ssh_host" value="<?= e($p['ssh_host']) ?>" placeholder="<?= te('10.147.17.20 o web.midominio.com') ?>"<?= $dis ?>></label>
        <label><?= te('Puerto') ?><input type="number" name="ssh_port" value="<?= (int) $p['ssh_port'] ?>" style="width:6rem"<?= $dis ?>></label>
        <label class="grow"><?= te('Usuario') ?><input type="text" name="ssh_user" value="<?= e($p['ssh_user']) ?>" placeholder="deploy"<?= $dis ?>></label>
      </div>
      <label><?= te('Carpeta remota') ?><input type="text" name="ssh_path" value="<?= e($p['ssh_path']) ?>" placeholder="/var/www/miweb" class="mono"<?= $dis ?>></label>
      <label><?= te('Clave privada SSH') ?> <span class="hint"><?= te($has['ssh_key'] ? 'guardada (cifrada); pega otra para cambiarla' : 'pega la clave privada de un usuario con permiso solo sobre esa carpeta') ?></span>
        <textarea name="ssh_key" rows="3" class="mono" autocomplete="off" placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"<?= $dis ?>></textarea></label>
      <p class="hint"><?= th('Recomendado: un usuario dedicado (p. ej. <code>deploy</code>) en el servidor web, con su clave pública en <code>~/.ssh/authorized_keys</code> y permiso de escritura solo en la carpeta de la web. La primera conexión guarda la huella del servidor.') ?></p>
    </fieldset>

    <fieldset class="pub-fields" data-target="ftp"><legend><?= te('FTP / FTPS') ?></legend>
      <div class="row">
        <label class="grow"><?= te('Servidor') ?><input type="text" name="ftp_host" value="<?= e($p['ftp_host']) ?>" placeholder="ftp.mihosting.com"<?= $dis ?>></label>
        <label><?= te('Puerto') ?><input type="number" name="ftp_port" value="<?= (int) $p['ftp_port'] ?>" style="width:6rem"<?= $dis ?>></label>
        <label class="grow"><?= te('Usuario') ?><input type="text" name="ftp_user" value="<?= e($p['ftp_user']) ?>"<?= $dis ?>></label>
        <label class="grow"><?= te('Contraseña') ?><input type="password" name="ftp_password" value="" autocomplete="new-password" placeholder="<?= $has['ftp_password'] ? e(t('•••••••• guardada')) : '' ?>"<?= $dis ?>></label>
      </div>
      <label><?= te('Carpeta remota') ?><input type="text" name="ftp_path" value="<?= e($p['ftp_path']) ?>" placeholder="/public_html" class="mono"<?= $dis ?>></label>
      <label class="check"><input type="checkbox" name="ftp_tls" value="1"<?= $p['ftp_tls'] ? ' checked' : '' ?><?= $dis ?>> <?= te('Usar FTPS (cifrado, muy recomendado)') ?></label>
    </fieldset>

    <fieldset class="pub-fields" data-target="directory"><legend><?= te('Carpeta') ?></legend>
      <label><?= te('Ruta absoluta') ?><input type="text" name="directory_path" value="<?= e($p['directory_path']) ?>" placeholder="/var/www/miweb-estatica" class="mono"<?= $dis ?>></label>
      <p class="hint"><?= te('Debe ser una carpeta exclusiva para esta web (vacía o publicada antes por Universal Web).') ?></p>
    </fieldset>
  </section>
  <?php if ($super): ?><div class="savebar"><button class="btn btn-primary"><?= te('Guardar') ?></button></div><?php endif; ?>
</form>
<script>
(function () {
  var sel = document.getElementById('pub-target');
  function sync() { document.querySelectorAll('.pub-fields').forEach(function (f) { f.hidden = f.getAttribute('data-target') !== sel.value; }); }
  sel.addEventListener('change', sync); sync();
})();
</script>
