<?php /** @var array $s @var array $themes @var array $domains @var SiteService $svc */
$site = $svc->site;
$soc = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'github' => 'GitHub', 'tiktok' => 'TikTok', 'whatsapp' => 'WhatsApp (enlace wa.me)']; ?>
<div class="page-head"><h1><?= te('Ajustes') ?></h1></div>
<form method="post" action="<?= ADM ?>/settings" class="stack">
  <?= csrf_field() ?>
  <section class="card">
    <h2><?= te('General') ?></h2>
    <div class="row">
      <label class="grow"><?= te('Nombre de la web') ?><input type="text" name="name" value="<?= e($site['name']) ?>" required maxlength="100"></label>
      <label class="grow"><?= te('Eslogan') ?><input type="text" name="tagline" value="<?= e($s['tagline']) ?>"></label>
    </div>
    <div class="row">
      <label><?= te('Idioma') ?><input type="text" name="language" value="<?= e($s['language']) ?>" maxlength="10" size="6"></label>
      <label><?= te('Tema') ?><select name="theme"><?php foreach ($themes as $k => $n): ?><option value="<?= e($k) ?>" <?= $k === $site['theme'] ? 'selected' : '' ?>><?= te($n) ?></option><?php endforeach; ?></select></label>
      <label><?= te('Color de acento') ?><input type="color" name="accent" value="<?= e($s['accent']) ?>"></label>
      <label class="grow"><?= te('Logo') ?><input type="text" name="logo" value="<?= e($s['logo']) ?>" list="media-urls" placeholder="/media/..."></label>
    </div>
    <label><?= te('Texto del pie') ?><input type="text" name="footer_text" value="<?= e($s['footer_text']) ?>"></label>
  </section>

  <section class="card" id="geo">
    <h2><?= te('Visibilidad en IAs (GEO)') ?></h2>
    <p class="hint"><?= th('Ayuda a ChatGPT, Perplexity, Gemini o Claude a identificar tu empresa y citarla. Se usa en los datos estructurados y en <code>/llms.txt</code>.') ?></p>
    <div class="row">
      <label class="grow"><?= te('Tipo de negocio') ?><select name="business_type"><?php foreach (GEO_BUSINESS_TYPES as $k => $l): ?><option value="<?= e($k) ?>" <?= $s['business']['type'] === $k ? 'selected' : '' ?>><?= te($l) ?></option><?php endforeach; ?></select></label>
      <label class="grow"><?= te('Razón social') ?><input type="text" name="business_legal_name" value="<?= e($s['business']['legal_name']) ?>" placeholder="<?= te('Empresa S.L.') ?>"></label>
    </div>
    <div class="row">
      <label class="grow"><?= te('Zona de servicio') ?><input type="text" name="business_area_served" value="<?= e($s['business']['area_served']) ?>" placeholder="<?= te('Región de Murcia y toda España') ?>"></label>
      <label><?= te('Año de fundación') ?><input type="text" name="business_founded" value="<?= e($s['business']['founded']) ?>" placeholder="1985" style="width:7rem"></label>
    </div>
    <label class="check"><input type="checkbox" name="ai_markdown" value="1" <?= $s['ai_markdown'] ? 'checked' : '' ?>>
      <span><?= th('Servir cada página en <b>Markdown</b> a los agentes de IA (ChatGPT, Claude, Perplexity…). Google y Bing siempre ven la web normal. También disponible en <code>/pagina.md</code>.') ?></span></label>
    <label><?= te('Rastreadores de IA (robots.txt)') ?><select name="ai_crawlers"><?php foreach (GEO_CRAWLER_POLICIES as $k => $l): ?><option value="<?= e($k) ?>" <?= $s['ai_crawlers'] === $k ? 'selected' : '' ?>><?= te($l) ?></option><?php endforeach; ?></select></label>
  </section>

  <section class="card" id="privada">
    <h2><?= te('Acceso privado (modo demo)') ?> <?= !empty($s['private']['enabled']) ? adm_badge(t('Activo'), 'blue') : '' ?></h2>
    <label class="check"><input type="checkbox" name="private_enabled" value="1" <?= !empty($s['private']['enabled']) ? 'checked' : '' ?>>
      <span><?= th('Web privada: solo se ve con contraseña y <b>no se indexa en Google</b> (ideal para enseñar una demo a un cliente sin crear contenido duplicado)') ?></span></label>
    <label><?= te('Contraseña para ver la web') ?> <span class="hint"><?= te(!empty($s['private']['password_hash']) ? 'guardada; escribe otra para cambiarla (cambiarla cierra el acceso a quien ya entró)' : 'mínimo 6 caracteres') ?></span>
      <input type="text" name="private_password" value="" autocomplete="off" placeholder="<?= !empty($s['private']['password_hash']) ? '••••••' : te('p. ej. demo-cliente-2026') ?>"></label>
    <p class="hint"><?= te('Los usuarios con sesión en el panel la ven sin contraseña. El formulario de contacto sigue funcionando.') ?></p>
  </section>

  <section class="card">
    <h2><?= te('Dominios') ?></h2>
    <label><?= te('Un dominio por línea (el primero es el principal)') ?>
      <textarea name="domains" rows="3" class="mono" placeholder="ejemplo.com"><?= e(implode("\n", $domains)) ?></textarea></label>
  </section>

  <section class="card">
    <h2><?= te('Menú') ?></h2>
    <div id="menu-editor" data-menu='<?= e(json_enc($s['menu'])) ?>'></div>
    <button type="button" class="btn btn-sm" id="menu-add"><?= te('+ Añadir enlace') ?></button>
  </section>

  <section class="card">
    <h2><?= te('Cabecera') ?></h2>
    <p class="hint"><?= te('Los temas que lo admiten (p. ej. Corporativo) muestran un botón destacado y una barra superior con enlaces secundarios.') ?></p>
    <div class="row">
      <label class="grow"><?= te('Texto del botón destacado') ?><input type="text" name="cta_label" value="<?= e($s['cta']['label']) ?>" placeholder="<?= te('Solicitar presupuesto') ?>"></label>
      <label class="grow"><?= te('Enlace del botón') ?><input type="text" name="cta_url" value="<?= e($s['cta']['url']) ?>" placeholder="/contacto"></label>
    </div>
    <label><?= te('Enlaces secundarios') ?> <span class="hint"><?= te('uno por línea: Texto | URL') ?></span>
      <textarea name="utility_menu" rows="3" class="mono" placeholder="<?= te('Área de clientes') ?> | https://..."><?= e(implode("\n", array_map(fn($m) => $m['label'] . ' | ' . $m['url'], $s['utility_menu']))) ?></textarea></label>
  </section>

  <section class="card">
    <h2><?= te('Contacto') ?></h2>
    <div class="row">
      <label class="grow"><?= te('Email') ?><input type="email" name="contact[email]" value="<?= e($s['contact']['email']) ?>"></label>
      <label class="grow"><?= te('Teléfono') ?><input type="text" name="contact[phone]" value="<?= e($s['contact']['phone']) ?>"></label>
    </div>
    <label><?= te('Dirección') ?><input type="text" name="contact[address]" value="<?= e($s['contact']['address']) ?>"></label>
  </section>

  <section class="card">
    <h2><?= te('Redes sociales') ?></h2>
    <div class="row wrap">
    <?php foreach ($soc as $k => $label): ?>
      <label class="grow"><?= te($label) ?><input type="url" name="social[<?= e($k) ?>]" value="<?= e($s['social'][$k] ?? '') ?>" placeholder="https://"></label>
    <?php endforeach; ?>
    </div>
  </section>

  <section class="card">
    <h2><?= te('SEO por defecto') ?></h2>
    <label><?= te('Descripción') ?>
      <textarea name="seo[description]" rows="3" maxlength="500" data-counter="def-count" data-min="50" data-max="160"><?= e($s['seo']['description']) ?></textarea>
      <small class="counter"><span id="def-count">0</span> <?= te('caracteres · objetivo 50–160') ?></small></label>
    <label><?= te('Imagen para compartir') ?><input type="text" name="seo[image]" value="<?= e($s['seo']['image']) ?>" list="media-urls" placeholder="/media/..."></label>
  </section>
  <div class="savebar"><button class="btn btn-primary"><?= te('Guardar ajustes') ?></button></div>
</form>
<?= adm_media_datalist($svc) ?>
