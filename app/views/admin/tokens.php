<?php /** @var array $tokens @var ?array $newToken @var string $mcpUrl */
// Lista generada desde mcp_tools(): nombre + primera frase de la descripción (la que ve la IA, en español).
$tools = [];
foreach (mcp_tools() as $toolName => $tool) {
    $first = trim((string) preg_split('/(?<=[.:])\s/u', (string) $tool['description'], 2)[0]);
    $tools[$toolName] = mb_strlen($first) > 140 ? rtrim(mb_substr($first, 0, 137)) . '…' : $first;
} ?>
<div class="page-head"><h1><?= te('Tokens de IA') ?></h1></div>

<?php if ($newToken):
    $cmd = 'claude mcp add --transport http universal-web ' . $mcpUrl . ' --header "Authorization: Bearer ' . $newToken['raw'] . '"';
    $cfg = json_enc(['mcpServers' => ['universal-web' => ['type' => 'http', 'url' => $mcpUrl, 'headers' => ['Authorization' => 'Bearer ' . $newToken['raw']]]]]); ?>
<section class="card token-new">
  <h2><?= te('Token «{name}» creado', ['name' => $newToken['name']]) ?><?= $newToken['global'] ? ' ' . te('(global)') : '' ?></h2>
  <p><b><?= te('Cópialo ahora: no se volverá a mostrar.') ?></b></p>
  <div class="copy-row"><code id="tok-raw"><?= e($newToken['raw']) ?></code><button type="button" class="btn btn-sm btn-primary" data-copy-target="#tok-raw"><?= te('Copiar') ?></button></div>
  <h3><?= te('Claude Code') ?></h3>
  <div class="copy-row"><pre id="tok-cmd"><?= e($cmd) ?></pre><button type="button" class="btn btn-sm" data-copy-target="#tok-cmd"><?= te('Copiar') ?></button></div>
  <h3><?= te('Configuración JSON genérica') ?></h3>
  <div class="copy-row"><pre id="tok-cfg"><?= e($cfg) ?></pre><button type="button" class="btn btn-sm" data-copy-target="#tok-cfg"><?= te('Copiar') ?></button></div>
</section>
<?php endif; ?>

<div class="grid-2">
<section class="card">
  <h2><?= te('Crear token') ?></h2>
  <form method="post" action="<?= ADM ?>/tokens/create" class="stack">
    <?= csrf_field() ?>
    <label><?= te('Nombre') ?><input type="text" name="name" required maxlength="80" placeholder="<?= te('Claude Code de Marta') ?>"></label>
    <fieldset><legend><?= te('Permisos') ?></legend>
      <?php foreach (TOKEN_SCOPES as $k => $label): ?>
        <label class="check"><input type="checkbox" name="scopes[]" value="<?= e($k) ?>" <?= $k === 'read' ? 'checked' : '' ?>> <b><?= e($k) ?></b> — <?= te($label) ?></label>
      <?php endforeach; ?>
    </fieldset>
    <?php if ($role === 'super'): ?>
    <fieldset><legend><?= te('Alcance') ?></legend>
      <label class="check"><input type="radio" name="target" value="site" checked> <b><?= te('Solo esta web') ?></b> — <?= e($svc->site['name']) ?></label>
      <label class="check"><input type="radio" name="target" value="global"> <?= th('<b>Global</b> — todas las webs (la IA elige la web con el parámetro <code>site</code>)') ?></label>
    </fieldset>
    <?php else: ?>
    <p class="muted"><?= th('El token servirá solo para <b>{name}</b>.', ['name' => $svc->site['name']]) ?></p>
    <?php endif; ?>
    <div><button class="btn btn-primary"><?= te('Crear token') ?></button></div>
  </form>
</section>
<section class="card">
  <h2><?= te('Qué puede hacer la IA') ?></h2>
  <p><?= te('Endpoint MCP:') ?> <code><?= e($mcpUrl) ?></code></p>
  <details>
    <summary><?= te('Herramientas disponibles: {n}', ['n' => count($tools)]) ?></summary>
    <ul class="plain">
      <?php foreach ($tools as $toolName => $short): ?><li><code><?= e($toolName) ?></code> <span class="muted"><?= e($short) ?></span></li><?php endforeach; ?>
    </ul>
    <?php if ($role === 'super'): ?><p class="muted"><?= th('Los tokens globales añaden <code>sites_list</code> y <code>site_create</code>.') ?></p><?php endif; ?>
  </details>
  <ul class="plain">
    <li><?= th('El token actúa <b>con tus permisos</b>: nunca puede hacer más que tú en el panel. Cambiar los ajustes de la web (<code>site_update</code>) exige ser admin.') ?></li>
    <li><?= te('Si pierdes el acceso a la web, tus tokens dejan de funcionar al instante.') ?></li>
    <li><?= th('Con <b>write</b> la IA solo crea y edita borradores; publicar o tocar lo publicado exige <b>publish</b>.') ?></li>
    <li><?= role_at_least($role, 'admin') ? th('Todo queda registrado en el registro de actividad (<a href="{url}">ver</a>).', ['url' => ADM . '/activity']) : te('Todo queda registrado en el registro de actividad.') ?></li>
  </ul>
</section>
</div>

<h2 class="sec"><?= te(role_at_least($role, 'admin') ? 'Tokens de esta web' : 'Tus tokens') ?></h2>
<?php if (!$tokens): ?>
  <div class="empty"><p><?= te('No hay tokens todavía.') ?></p></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Nombre') ?></th><th><?= te('Token') ?></th><th><?= te('Permisos') ?></th><th><?= te('Creado por') ?></th><th><?= te('Creado') ?></th><th><?= te('Último uso') ?></th><th><?= te('Estado') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($tokens as $t): ?>
    <tr>
      <td data-l="<?= te('Nombre') ?>"><b><?= e($t['name']) ?></b><?= $t['site_id'] === null ? ' ' . adm_badge(t('Global'), 'blue') : '' ?></td>
      <td data-l="<?= te('Token') ?>"><code><?= e($t['prefix']) ?>…</code></td>
      <td data-l="<?= te('Permisos') ?>"><?php foreach (explode(',', $t['scopes']) as $sc) echo adm_badge($sc) . ' '; ?></td>
      <td data-l="<?= te('Creado por') ?>"><?= $t['created_by'] === null ? '<span class="muted">CLI</span>' : e($t['owner_name'] ?? t('Usuario borrado')) ?></td>
      <td data-l="<?= te('Creado') ?>"><?= e(adm_date($t['created_at'])) ?></td>
      <td data-l="<?= te('Último uso') ?>"><?= e(adm_date($t['last_used_at'])) ?></td>
      <td data-l="<?= te('Estado') ?>"><?= $t['revoked_at'] ? adm_badge(t('Revocado'), 'red') : adm_badge(t('Activo'), 'green') ?></td>
      <td class="row-actions"><?php if (!$t['revoked_at']): ?>
        <form method="post" action="<?= ADM ?>/tokens/revoke" data-confirm="<?= te('¿Revocar el token «{name}»? Dejará de funcionar de inmediato.', ['name' => $t['name']]) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $t['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Revocar') ?></button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
