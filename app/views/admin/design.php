<?php /** @var array $cfg @var array $history @var array $limits @var string $mcpUrl @var SiteService $svc */
$sizeKb = fn(int $n) => number_format($n / 1024, 1, ',', '.') . ' KB';
$vars = [
    ['--accent', 'Color de acento (el de Ajustes)'], ['--on-accent', 'Texto sobre el acento'], ['--bg', 'Fondo de la página'], ['--bg-soft', 'Fondo suave (secciones alternas)'],
    ['--text', 'Color del texto'], ['--muted', 'Texto secundario'], ['--border', 'Bordes'], ['--card', 'Fondo de tarjetas'],
    ['--radius', 'Redondeo de esquinas'], ['--shadow', 'Sombra de tarjetas'], ['--font', 'Fuente del texto'], ['--font-head', 'Fuente de los títulos'],
    ['--mono', 'Fuente monoespaciada'], ['--max', 'Ancho máximo del contenido'],
];
$example = <<<'CSS'
:root {
  --radius: 4px;
  --font-head: Georgia, serif;
}
.site-header { border-bottom: 3px solid var(--accent); }
.btn { text-transform: uppercase; letter-spacing: .05em; }
.hero h1 { font-size: clamp(2.2rem, 6vw, 4rem); }
CSS;
?>
<div class="page-head">
  <h1><?= te('Diseño y código') ?></h1>
  <div class="actions">
    <a class="btn" href="<?= e($svc->url('/')) ?>" target="_blank" rel="noopener" id="design-preview"><?= te('Vista previa') ?> <?= adm_icon('ext') ?></a>
  </div>
</div>

<div class="alert-warn" role="note"><?= th('<strong>Atención.</strong> El JavaScript y el código del &lt;head&gt; se ejecutan en tu web pública. Pega solo código de fuentes de confianza.') ?></div>

<form method="post" action="<?= ADM ?>/design" id="design-form" class="stack" data-limits='<?= e(json_enc($limits)) ?>'>
  <?= csrf_field() ?>
  <div class="tabs" role="tablist" id="design-tabs">
    <a href="#css" role="tab" class="active" data-tab="css">CSS</a>
    <a href="#js" role="tab" data-tab="js">JavaScript</a>
    <a href="#head" role="tab" data-tab="head">&lt;head&gt;</a>
    <a href="#favicon" role="tab" data-tab="favicon">Favicon</a>
    <a href="#history" role="tab" data-tab="history"><?= te('Historial') ?></a>
  </div>

  <section class="card design-panel" data-panel="css">
    <h2><?= te('CSS personalizado') ?></h2>
    <p class="muted"><?= te('Se carga después del tema, así que puedes sobrescribir cualquier estilo. Máx. {kb} KB.', ['kb' => (int) ($limits['css'] / 1024)]) ?></p>
    <textarea name="css" class="code-ed mono" rows="22" spellcheck="false" autocapitalize="off" autocomplete="off" data-max="<?= (int) $limits['css'] ?>"><?= e($cfg['css']) ?></textarea>
    <div class="code-meta"><span class="counter" data-for="css"></span></div>
    <details class="cheat">
      <summary><?= te('Variables del tema y ejemplo') ?></summary>
      <p><?= th('Cambia estas variables en <code>:root</code> y afectarás a toda la web:') ?></p>
      <ul class="varlist">
        <?php foreach ($vars as [$n, $d]): ?><li><code><?= e($n) ?></code> <span class="muted"><?= te($d) ?></span></li><?php endforeach; ?>
      </ul>
      <p><?= th('Clases útiles:') ?> <code>.container</code> <code>.section</code> <code>.btn</code> <code>.btn-ghost</code> <code>.card</code> <code>.hero</code> <code>.site-header</code> <code>.site-nav</code> <code>.site-footer</code>.</p>
      <div class="copy-row"><pre id="css-example"><?= e($example) ?></pre><button type="button" class="btn btn-sm" data-copy-target="#css-example"><?= te('Copiar') ?></button></div>
    </details>
  </section>

  <section class="card design-panel" data-panel="js" hidden>
    <h2><?= te('JavaScript personalizado') ?></h2>
    <p class="muted"><?= th('Se carga al final de cada página con <code>defer</code>. Máx. {kb} KB.', ['kb' => (int) ($limits['js'] / 1024)]) ?></p>
    <textarea name="js" class="code-ed mono" rows="22" spellcheck="false" autocapitalize="off" autocomplete="off" data-max="<?= (int) $limits['js'] ?>"><?= e($cfg['js']) ?></textarea>
    <div class="code-meta"><span class="counter" data-for="js"></span></div>
  </section>

  <section class="card design-panel" data-panel="head" hidden>
    <h2><?= th('Código del &lt;head&gt;') ?></h2>
    <p class="muted"><?= th('HTML que se añade al final de <code>&lt;head&gt;</code>: analítica, verificación de dominio, fuentes, metaetiquetas… Máx. {kb} KB.', ['kb' => (int) ($limits['head_html'] / 1024)]) ?></p>
    <textarea name="head_html" class="code-ed mono" rows="14" spellcheck="false" autocapitalize="off" autocomplete="off" data-max="<?= (int) $limits['head_html'] ?>" placeholder="<link rel=&quot;preconnect&quot; href=&quot;https://fonts.googleapis.com&quot;>"><?= e($cfg['head_html']) ?></textarea>
    <div class="code-meta"><span class="counter" data-for="head_html"></span></div>
  </section>

  <section class="card design-panel" data-panel="favicon" hidden>
    <h2><?= te('Favicon') ?></h2>
    <p class="muted"><?= th('Icono de la pestaña del navegador. Sube una imagen en Medios (PNG, ICO o WebP) y elígela aquí, o pega una URL <code>https://</code>. Déjalo vacío para no usar ninguno.') ?></p>
    <div class="img-field">
      <label class="grow" style="flex:1"><?= te('URL del favicon') ?><input type="text" name="favicon" id="favicon-input" value="<?= e($cfg['favicon']) ?>" list="media-urls" placeholder="/media/..." maxlength="500"></label>
      <img id="favicon-preview" alt="<?= te('Vista previa del favicon') ?>" width="44" height="44" <?= $cfg['favicon'] !== '' ? 'src="' . e($cfg['favicon']) . '"' : 'hidden' ?>>
    </div>
  </section>

  <section class="card design-panel" data-panel="history" hidden>
    <h2><?= te('Historial de versiones') ?></h2>
    <p class="muted"><?= th('Versión actual: <b>{v}</b>{saved}. Se conservan las últimas 30 versiones anteriores.', ['v' => (int) $cfg['version'], 'saved' => $cfg['updated_at'] ? t(' · guardada el {date}', ['date' => adm_date($cfg['updated_at'])]) : '']) ?></p>
    <?php if (!$history): ?>
      <p class="muted"><?= te('Aún no hay versiones anteriores.') ?></p>
    <?php else: ?>
    <div class="table-wrap"><table>
      <thead><tr><th><?= te('Versión') ?></th><th><?= te('Fecha') ?></th><th><?= te('Autor') ?></th><th><?= te('CSS') ?></th><th><?= te('JS') ?></th><th>&lt;head&gt;</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($history as $h): ?>
        <tr>
          <td data-l="<?= te('Versión') ?>">v<?= (int) $h['version'] ?></td>
          <td data-l="<?= te('Fecha') ?>"><?= e(adm_date($h['created_at'])) ?></td>
          <td data-l="<?= te('Autor') ?>"><?= e($h['actor_name']) ?></td>
          <td data-l="<?= te('CSS') ?>"><?= e($sizeKb((int) $h['css_size'])) ?></td>
          <td data-l="JS"><?= e($sizeKb((int) $h['js_size'])) ?></td>
          <td data-l="head"><?= e($sizeKb((int) $h['head_size'])) ?></td>
          <td class="row-actions"><button type="submit" class="btn btn-sm" formaction="<?= ADM ?>/design/restore" formmethod="post" name="id" value="<?= (int) $h['id'] ?>" formnovalidate data-confirm-btn="<?= te('¿Restaurar la versión {v}? Se guardará como una versión nueva y los cambios sin guardar se perderán.', ['v' => (int) $h['version']]) ?>"><?= te('Restaurar') ?></button></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </section>

  <div class="savebar">
    <button class="btn btn-primary" id="design-save"><?= te('Guardar') ?> <small>(Ctrl/Cmd+S)</small></button>
    <span class="muted" id="design-dirty" hidden><?= te('Cambios sin guardar') ?></span>
  </div>
</form>

<section class="card card-accent">
  <h2><?= te('¿Prefieres que lo haga tu IA?') ?></h2>
  <p><?= th('Conecta tu asistente por MCP (<code>{url}</code>, token en <a href="{tokens}">Tokens de IA</a>) y pídele que ajuste el CSS, el JS o el favicon de esta web. Cada cambio queda en el historial y puedes volver atrás cuando quieras.', ['url' => $mcpUrl, 'tokens' => ADM . '/tokens']) ?></p>
</section>
<?= adm_media_datalist($svc) ?>
