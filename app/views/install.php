<?php
/** @var array $checks @var bool $ok @var array $values @var array $errors @var ?array $done @var string $base @var array $timezones */
panel_lang_switch(); // ?lang=es|ro|en fija la cookie del idioma antes de emitir HTML
?><!doctype html>
<html lang="<?= panel_locale() ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= te('Instalar Universal Web') ?></title>
<style>
:root{--bg:#f4f5fb;--card:#fff;--ink:#1c1d2b;--mut:#6b6f85;--line:#e3e5f0;--acc:#4f46e5;--acc2:#7c3aed;--ok:#059669;--bad:#dc2626;--warn:#d97706}
@media (prefers-color-scheme:dark){:root{--bg:#10111a;--card:#191b28;--ink:#eceefb;--mut:#9a9eb8;--line:#2a2d42}}
*{box-sizing:border-box}
body{margin:0;background:var(--bg);color:var(--ink);font:16px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.wrap{max-width:720px;margin:0 auto;padding:32px 16px 64px}
header{text-align:center;margin-bottom:24px}
.logo{display:inline-grid;place-items:center;width:56px;height:56px;border-radius:16px;background:linear-gradient(135deg,var(--acc),var(--acc2));color:#fff;font-size:28px;font-weight:700;box-shadow:0 8px 24px rgba(79,70,229,.35)}
h1{margin:14px 0 4px;font-size:1.9rem}
header p{margin:0;color:var(--mut)}
.card{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:22px;margin-bottom:18px;box-shadow:0 1px 2px rgba(0,0,0,.04)}
h2{margin:0 0 12px;font-size:1.1rem}
ul.checks{list-style:none;margin:0;padding:0}
ul.checks li{display:flex;gap:10px;padding:7px 0;border-top:1px solid var(--line);align-items:baseline}
ul.checks li:first-child{border-top:0}
.mark{font-weight:700;width:1.2em;flex:none}
.pass .mark{color:var(--ok)}.fail .mark{color:var(--bad)}.opt .mark{color:var(--warn)}
.det{color:var(--mut);font-size:.88rem;display:block;word-break:break-all}
label{display:block;font-weight:600;margin:14px 0 5px;font-size:.93rem}
input[type=text],input[type=email],input[type=password],select{width:100%;padding:11px 12px;border:1px solid var(--line);border-radius:10px;background:var(--bg);color:var(--ink);font:inherit}
input:focus,select:focus{outline:2px solid var(--acc);border-color:transparent}
.hint{color:var(--mut);font-size:.84rem;margin-top:4px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:0 16px}
@media (max-width:560px){.row{grid-template-columns:1fr}}
.check{display:flex;gap:10px;align-items:flex-start;margin-top:18px;font-weight:500}
.check input{margin-top:5px;width:18px;height:18px;accent-color:var(--acc)}
button,.btn{display:inline-block;border:0;border-radius:10px;padding:13px 22px;font:inherit;font-weight:600;color:#fff;background:linear-gradient(135deg,var(--acc),var(--acc2));cursor:pointer;text-decoration:none}
button:disabled{opacity:.45;cursor:not-allowed}
.btn.sec{background:var(--card);color:var(--ink);border:1px solid var(--line)}
.errors{background:rgba(220,38,38,.1);border:1px solid var(--bad);color:var(--bad);padding:12px 16px;border-radius:12px;margin-bottom:18px}
.errors p{margin:2px 0}
.blocked{background:rgba(217,119,6,.12);border:1px solid var(--warn);padding:12px 16px;border-radius:12px;margin-top:14px;font-size:.92rem}
.done{text-align:center}.done .big{font-size:3rem}
.actions{display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin:18px 0}
.note{color:var(--mut);font-size:.9rem}
footer{text-align:center;color:var(--mut);font-size:.82rem;margin-top:24px}
.lang{margin:0 0 10px;font-size:.82rem}.lang a{color:var(--mut);text-decoration:none;font-weight:600;padding:0 6px}.lang a.on,.lang a:hover{color:var(--acc);text-decoration:underline}
code{background:var(--bg);padding:2px 6px;border-radius:6px;font-size:.88em}
</style>
</head>
<body>
<div class="wrap">
<header>
  <p class="lang"><?php foreach (PANEL_LOCALES as $code => $name): ?><a href="/install?lang=<?= e($code) ?>"<?= panel_locale() === $code ? ' class="on"' : '' ?> hreflang="<?= e($code) ?>"><?= e(strtoupper($code)) ?></a> <?php endforeach; ?></p>
  <div class="logo">U</div>
  <h1><?= te('Instalar Universal Web') ?></h1>
  <p><?= te('Una web, un panel, un MCP. Tardarás menos de un minuto.') ?></p>
</header>

<?php if ($token !== null || ($errors && $mcpUrl !== '' && !$done && !$checks)): ?>
  <?php if ($errors): ?><div class="errors" role="alert"><?php foreach ($errors as $err): ?><p>✗ <?= e(t_msg((string) $err)) ?></p><?php endforeach; ?></div><?php endif; ?>
  <?php if ($token !== null): ?>
  <div class="card">
    <h2><?= te('Conecta ADRI') ?></h2>
    <p><?= te('Este es el token de acceso de tu web. Cópialo ahora: no se volverá a mostrar.') ?></p>
    <label for="tok"><?= te('Token') ?></label>
    <input type="text" id="tok" value="<?= e($token) ?>" readonly onfocus="this.select()">
    <p><button type="button" id="copytok" data-ok="<?= te('¡Copiado!') ?>"><?= te('Copiar token') ?></button></p>
    <label for="mcpurl"><?= te('Dirección MCP') ?></label>
    <input type="text" id="mcpurl" value="<?= e($mcpUrl) ?>" readonly onfocus="this.select()">
    <p class="note"><?= te('Pégalo en BionicAdri → MCP → Universal Web, o pide acceso en info@bionicadri.com') ?></p>
    <div class="actions"><a class="btn" href="/admin"><?= te('Ir al panel de administración') ?></a></div>
  </div>
  <script>
  document.getElementById('copytok').addEventListener('click',function(){var b=this,i=document.getElementById('tok');i.select();
    (navigator.clipboard?navigator.clipboard.writeText(i.value):Promise.reject()).catch(function(){document.execCommand('copy')}).then(function(){b.textContent=b.dataset.ok})});
  </script>
  <?php else: ?>
  <div class="card done"><div class="actions"><a class="btn" href="/admin"><?= te('Ir al panel de administración') ?></a></div></div>
  <?php endif; ?>
<?php elseif ($done): ?>
  <div class="card done">
    <div class="big">🎉</div>
    <h2><?= te('¡Instalación completada!') ?></h2>
    <p><?= th('Tu web <strong>{name}</strong> ya está lista. Entra en el panel con el email <strong>{email}</strong> y la contraseña que acabas de elegir.', ['name' => $done['site']['name'], 'email' => $values['admin_email']]) ?></p>
    <div class="actions">
      <a class="btn" href="/admin"><?= te('Ir al panel de administración') ?></a>
      <a class="btn sec" href="/"><?= te('Ver la web') ?></a>
    </div>
    <?php if (!empty($done['connect_nonce'])): ?>
    <form method="post" action="/install/conectar-adri" class="card" style="text-align:left;margin-top:18px">
      <h2><?= te('Último paso (opcional): conecta ADRI') ?></h2>
      <p class="note"><?= te('Crea un token para que ADRI, tu asistente de IA, pueda manejar esta web (leer, escribir y publicar). Se muestra una sola vez.') ?></p>
      <input type="hidden" name="nonce" value="<?= e($done['connect_nonce']) ?>">
      <button type="submit"><?= te('Crear token y conectar ADRI') ?></button>
      <span class="note"> <?= te('Puedes saltarte este paso y hacerlo luego en el panel.') ?></span>
    </form>
    <?php endif; ?>
    <p class="note">🔒 <?= th('El instalador ya está bloqueado: <code>/install</code> dejará de responder porque la web ya está instalada.<br>Para tener más webs, añade dominios desde <em>Ajustes</em> en el panel y apúntalos a esta misma carpeta.') ?></p>
  </div>
<?php else: ?>

<?php if ($errors): ?>
  <div class="errors" role="alert"><?php foreach ($errors as $err): ?><p>✗ <?= e(t_msg((string) $err)) ?></p><?php endforeach; ?></div>
<?php endif; ?>

<div class="card">
  <h2><?= te('1. Requisitos del servidor') ?></h2>
  <ul class="checks">
  <?php foreach ($checks as [$label, $pass, $detail, $required]): ?>
    <li class="<?= $pass ? 'pass' : ($required ? 'fail' : 'opt') ?>">
      <span class="mark"><?= $pass ? '✓' : ($required ? '✗' : '!') ?></span>
      <span><?= e(t_msg((string) $label)) ?><?= $required ? '' : ' <small>(' . te('opcional') . ')</small>' ?><span class="det"><?= e(t_msg((string) $detail)) ?></span></span>
    </li>
  <?php endforeach; ?>
  </ul>
  <?php if (!$ok): ?>
    <div class="blocked"><?= te('Corrige los requisitos marcados con ✗ y recarga esta página. Si usas hosting compartido, activa las extensiones desde el selector de versión de PHP de tu panel.') ?></div>
  <?php endif; ?>
</div>

<form method="post" action="/install" class="card" autocomplete="off">
  <h2><?= te('2. Datos de tu web') ?></h2>
  <label for="site_name"><?= te('Nombre de la web') ?></label>
  <input type="text" id="site_name" name="site_name" value="<?= e($values['site_name']) ?>" placeholder="<?= te('Universal Web') ?>" required maxlength="100">

  <label for="domain"><?= te('Dominio principal') ?></label>
  <input type="text" id="domain" name="domain" value="<?= e($values['domain']) ?>" placeholder="mi-dominio.com" required>
  <div class="hint"><?= te('Sin https:// ni barras. Después podrás añadir más dominios y más webs.') ?></div>

  <h2 style="margin-top:26px"><?= te('3. Administrador') ?></h2>
  <div class="row">
    <div><label for="admin_name"><?= te('Tu nombre') ?></label>
    <input type="text" id="admin_name" name="admin_name" value="<?= e($values['admin_name']) ?>" placeholder="<?= te('Ana García') ?>" required></div>
    <div><label for="admin_email"><?= te('Email') ?></label>
    <input type="email" id="admin_email" name="admin_email" value="<?= e($values['admin_email']) ?>" placeholder="tu@email.com" required></div>
  </div>
  <label for="admin_password"><?= te('Contraseña') ?></label>
  <input type="password" id="admin_password" name="admin_password" minlength="8" required autocomplete="new-password">
  <div class="hint"><?= te('Mínimo 8 caracteres.') ?></div>

  <label for="timezone"><?= te('Zona horaria') ?></label>
  <select id="timezone" name="timezone">
    <?php foreach ($timezones as $tz => $tzLabel): ?>
      <option value="<?= e($tz) ?>"<?= $tz === $values['timezone'] ? ' selected' : '' ?>><?= e(t_msg((string) $tzLabel)) ?></option>
    <?php endforeach; ?>
  </select>

  <label class="check"><input type="checkbox" name="demo" value="1"<?= $values['demo'] ? ' checked' : '' ?>>
    <span><?= te('Instalar contenido de demostración') ?><br><span class="hint"><?= te('Una web completa con páginas, blog y mensajes, y una segunda web («Café Aurora») con otro tema para ver el multi-web en acción.') ?></span></span></label>

  <p class="note" style="margin:20px 0 0"><?= te('Al instalar aceptas la Licencia BionicAdri 2.0 (archivo LICENSE): uso gratuito para tus propias webs, sin reventa ni servicio a terceros. Se ofrece tal cual, sin garantía, sin responsabilidad y sin soporte técnico.') ?></p>
  <p style="margin:24px 0 0"><button type="submit"<?= $ok ? '' : ' disabled' ?>><?= te('Instalar Universal Web') ?></button></p>
</form>
<?php endif; ?>

<footer>Universal Web v<?= e(UW_VERSION) ?> · PHP <?= e(PHP_VERSION) ?> · Powered by <a href="<?= UW_AUTHOR_URL ?>" target="_blank" rel="noopener">bionicadri.com</a> · <a href="<?= UW_DEMO_URL ?>" target="_blank" rel="noopener"><?= te('Pedir demo') ?></a></footer>
</div>
</body>
</html>
