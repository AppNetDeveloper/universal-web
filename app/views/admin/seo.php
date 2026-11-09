<?php /** @var array $audit @var array $links */
$score = (int) $audit['score'];
$kind = $score >= 85 ? 'green' : ($score >= 60 ? 'amber' : 'red');
$sev = ['error' => ['Error', 'red'], 'warning' => ['Aviso', 'amber'], 'notice' => ['Sugerencia', 'blue']]; ?>
<div class="page-head"><h1><?= te('SEO') ?></h1></div>
<div class="grid-2">
  <section class="card score-card">
    <div class="big-score score-<?= $kind ?>"><?= $score ?><small>/100</small></div>
    <div class="counts">
      <span><?= adm_badge(t('Error'), 'red') ?> <b><?= (int) $audit['summary']['errors'] ?></b></span>
      <span><?= adm_badge(t('Aviso'), 'amber') ?> <b><?= (int) $audit['summary']['warnings'] ?></b></span>
      <span><?= adm_badge(t('Sugerencia'), 'blue') ?> <b><?= (int) $audit['summary']['notices'] ?></b></span>
    </div>
  </section>
  <section class="card">
    <h2><?= te('Archivos para buscadores') ?></h2>
    <ul class="links"><?php foreach ($links as $n => $u): ?><li><a href="<?= e($u) ?>" target="_blank" rel="noopener"><?= e($n) ?> <?= adm_icon('ext') ?></a></li><?php endforeach; ?></ul>
  </section>
</div>
<?php if (!$audit['issues']): ?>
  <div class="empty"><p><?= te('No se han encontrado problemas. Buen trabajo.') ?></p></div>
<?php else: ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Gravedad') ?></th><th><?= te('Dónde') ?></th><th><?= te('Problema') ?></th></tr></thead>
  <tbody>
  <?php foreach (['error', 'warning', 'notice'] as $s): foreach ($audit['issues'] as $i): if ($i['severity'] !== $s) continue; ?>
    <tr><td data-l="<?= te('Gravedad') ?>"><?= adm_badge(t($sev[$s][0]), $sev[$s][1]) ?></td><td data-l="<?= te('Dónde') ?>"><?= e(t_msg((string) $i['where'])) ?></td><td data-l="<?= te('Problema') ?>"><?= e(t_msg((string) $i['message'])) ?></td></tr>
  <?php endforeach; endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>

<?php /** @var array $geo */ $gscore = (int) $geo['score']; $gkind = $gscore >= 85 ? 'green' : ($gscore >= 60 ? 'amber' : 'red'); ?>
<h2 class="sec"><?= te('GEO: visibilidad en buscadores con IA') ?></h2>
<div class="grid-2">
  <section class="card score-card">
    <div class="big-score score-<?= $gkind ?>"><?= $gscore ?><small>/100</small></div>
    <p class="muted"><?= te('Lo preparada que está la web para que ChatGPT, Perplexity, Gemini o Claude la entiendan y la citen.') ?>
      <?= te('{faq} preguntas frecuentes · {words} palabras publicadas.', ['faq' => (int) $geo['stats']['faq_questions'], 'words' => (int) $geo['stats']['words']]) ?></p>
  </section>
  <section class="card">
    <h2><?= te('Archivos para IAs (se generan solos)') ?></h2>
    <ul class="plain">
      <?php foreach (['llms_txt' => t('/llms.txt (resumen)'), 'llms_full' => t('/llms-full.txt (contenido completo)'), 'robots' => 'robots.txt'] as $k => $l): ?>
      <li><a href="<?= e($geo['ai_files'][$k]) ?>" target="_blank" rel="noopener"><?= e($l) ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p class="hint"><?= te('Tipo de negocio, zona de servicio y rastreadores de IA:') ?> <a href="<?= ADM ?>/settings#geo"><?= te('Ajustes → Visibilidad en IAs') ?></a>.</p>
  </section>
</div>
<?php if ($geo['issues']): ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Gravedad') ?></th><th><?= te('Dónde') ?></th><th><?= te('Qué mejorar') ?></th><th><?= te('Cómo') ?></th></tr></thead>
  <tbody>
  <?php foreach ($geo['issues'] as $i): ?>
    <tr><td data-l="<?= te('Gravedad') ?>"><?= adm_badge(t($sev[$i['severity']][0]), $sev[$i['severity']][1]) ?></td><td data-l="<?= te('Dónde') ?>"><?= e(t_msg((string) $i['where'])) ?></td>
      <td data-l="<?= te('Qué mejorar') ?>"><?= e(t_msg((string) $i['message'])) ?></td><td data-l="<?= te('Cómo') ?>" class="muted"><?= e(t_msg((string) $i['fix'])) ?></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php else: ?><div class="empty"><p><?= te('Sin problemas: la web está lista para las IAs.') ?></p></div><?php endif; ?>

<?php /** @var array $redirects */ ?>
<h2 class="sec"><?= te('Redirecciones 301') ?></h2>
<p class="muted"><?= te('Si cambias el slug de una página publicada, la redirección desde la URL antigua se crea sola. Aquí puedes añadir otras a mano.') ?></p>
<form method="post" action="<?= ADM ?>/seo/redirects" class="card" style="display:flex;gap:8px;flex-wrap:wrap;align-items:end"><?= csrf_field() ?>
  <label><?= te('Desde (ruta antigua)') ?><input name="from" placeholder="/servicio-viejo" required></label>
  <label><?= te('Hacia (ruta o https://…)') ?><input name="to" placeholder="/servicio-nuevo" required></label>
  <button class="btn"><?= te('Añadir') ?></button>
</form>
<?php if ($redirects): ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Desde') ?></th><th><?= te('Hacia') ?></th><th><?= te('Visitas') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($redirects as $r): ?>
    <tr><td data-l="<?= te('Desde') ?>"><?= e($r['from_path']) ?></td><td data-l="<?= te('Hacia') ?>"><?= e($r['to_path']) ?></td><td data-l="<?= te('Visitas') ?>"><?= (int) $r['hits'] ?></td>
      <td><form method="post" action="<?= ADM ?>/seo/redirects/delete"><?= csrf_field() ?><input type="hidden" name="from" value="<?= e($r['from_path']) ?>"><button class="btn btn-sm"><?= te('Borrar') ?></button></form></td></tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php endif; ?>
