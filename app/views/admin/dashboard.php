<?php /** @var array $stats @var array $seo @var array $activity @var string $mcpUrl */
$score = (int) $seo['score'];
$scoreKind = $score >= 85 ? 'green' : ($score >= 60 ? 'amber' : 'red'); ?>
<div class="page-head"><h1><?= te('Panel') ?></h1>
  <div class="actions"><a class="btn btn-primary" href="<?= ADM ?>/posts/edit"><?= te('Nueva entrada') ?></a> <a class="btn" href="<?= ADM ?>/pages/edit"><?= te('Nueva página') ?></a></div></div>
<div class="stats">
  <a class="stat" href="<?= ADM ?>/pages"><b><?= $stats['pages'] ?></b><span><?= te('Páginas') ?></span></a>
  <a class="stat" href="<?= ADM ?>/posts?status=published"><b><?= $stats['posts_published'] ?></b><span><?= te('Entradas publicadas') ?></span></a>
  <a class="stat" href="<?= ADM ?>/posts?status=draft"><b><?= $stats['posts_draft'] ?></b><span><?= te('Borradores') ?></span></a>
  <a class="stat <?= $stats['messages_unread'] ? 'stat-hot' : '' ?>" href="<?= ADM ?>/messages"><b><?= $stats['messages_unread'] ?></b><span><?= te('Mensajes sin leer') ?></span></a>
  <a class="stat" href="<?= ADM ?>/media"><b><?= $stats['media'] ?></b><span><?= te('Archivos') ?></span></a>
  <a class="stat" href="<?= ADM ?>/seo"><b class="score-<?= $scoreKind ?>"><?= $score ?></b><span><?= te('Puntuación SEO') ?></span></a>
</div>
<div class="grid-2">
  <section class="card">
    <h2><?= te('Actividad reciente') ?></h2>
    <?php if (!$activity): ?><p class="muted"><?= te('Todavía no hay actividad.') ?></p><?php else: ?>
    <ul class="feed">
      <?php foreach ($activity as $a): ?>
        <li><?= adm_icon($a['actor_type'] === 'token' ? 'bot' : 'user') ?>
          <div><b><?= e($a['actor_name']) ?></b> · <code><?= e($a['action']) ?></code> <?= e($a['target']) ?><small><?= e(adm_date($a['created_at'])) ?></small></div></li>
      <?php endforeach; ?>
    </ul>
    <?php if (role_at_least($role, 'admin')): ?><a href="<?= ADM ?>/activity" class="link"><?= te('Ver toda la actividad') ?></a><?php endif; ?>
    <?php endif; ?>
  </section>
  <div class="stack">
  <section class="card promo">
    <h2><?= te('¿Y si la IA llevara también tu empresa?') ?></h2>
    <p><?= th('<b>ADRI</b>, de <b>BionicAdri</b>, es un equipo de 15 especialistas de IA dentro de una plataforma de gestión para pymes: CRM, facturación con VeriFactu, RR. HH. y operaciones. Crea y entiende imágenes, audio y vídeo, diseña y programa webs, prepara contenido para redes y atiende WhatsApp, email y Telegram. Se lo pides como a una persona, por chat o por voz, y tus datos se quedan en Europa.') ?></p>
    <p><a class="btn btn-primary" href="<?= UW_DEMO_URL ?>" target="_blank" rel="noopener"><?= te('Pedir demo gratis') ?></a> <a class="btn btn-ghost" href="<?= UW_AUTHOR_URL ?>" target="_blank" rel="noopener"><?= te('Ver cómo funciona') ?></a></p>
  </section>
  <?php if (!empty($previewLink)): ?>
  <section class="card">
    <h2><?= te('Vista previa sin dominio') ?></h2>
    <p><?= te('Mira y enseña esta web antes de que su dominio apunte a este servidor. El enlace dura 7 días.') ?></p>
    <p><a class="btn" target="_blank" rel="noopener" href="<?= e($previewLink) ?>"><?= te('Vista previa sin dominio') ?> <?= adm_icon('ext') ?></a></p>
  </section>
  <?php endif; ?>
  <section class="card card-accent">
    <h2><?= te('Conectar con IA (MCP)') ?></h2>
    <p><?= te('Deja que Claude u otra IA redacte y edite contenido de esta web mediante MCP. Endpoint:') ?></p>
    <div class="copy-row"><code id="mcp-url"><?= e($mcpUrl) ?></code><button type="button" class="btn btn-sm" data-copy-target="#mcp-url"><?= te('Copiar') ?></button></div>
    <p><a class="btn btn-primary" href="<?= ADM ?>/tokens"><?= te('Crear token de acceso') ?></a></p>
  </section>
  </div>
</div>
