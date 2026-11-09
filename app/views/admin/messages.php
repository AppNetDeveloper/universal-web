<?php /** @var array $messages */ ?>
<div class="page-head"><h1><?= te('Mensajes') ?></h1></div>
<?php if (!$messages): ?>
  <div class="empty"><p><?= te('No hay mensajes. Llegarán aquí cuando alguien use el formulario de contacto.') ?></p></div>
<?php else: ?>
<div class="msg-list">
<?php foreach ($messages as $m): ?>
  <details class="msg <?= $m['is_read'] ? '' : 'unread' ?>">
    <summary>
      <span class="dot"></span>
      <b><?= e($m['name']) ?></b>
      <span class="subject"><?= e($m['subject'] !== '' ? $m['subject'] : str_limit(preg_replace('/\s+/', ' ', $m['body']), 80)) ?></span>
      <?= match ($m['mail_status']) { 'sent' => adm_badge(t('Email enviado'), 'green'), 'failed' => adm_badge(t('Error de envío'), 'red'), default => '' } ?>
      <small class="muted"><?= e(adm_date($m['created_at'])) ?></small>
    </summary>
    <div class="msg-body">
      <p class="muted"><a href="mailto:<?= e($m['email']) ?>"><?= e($m['email']) ?></a><?= $m['page'] !== '' ? ' · ' . te('desde {page}', ['page' => $m['page']]) : '' ?></p>
      <div class="pre"><?= e($m['body']) ?></div>
      <?php if ($m['mail_status'] === 'failed'): ?><p class="alert-error"><?= te('No se pudo enviar el aviso por email:') ?> <?= e(t_msg((string) $m['mail_error'])) ?></p><?php endif; ?>
      <div class="actions">
        <a class="btn btn-sm btn-primary" href="mailto:<?= e($m['email']) ?>?subject=<?= e(rawurlencode('Re: ' . $m['subject'])) ?>"><?= te('Responder') ?></a>
        <form method="post" action="<?= ADM ?>/messages/mark"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="read" value="<?= $m['is_read'] ? 0 : 1 ?>"><button class="btn btn-sm"><?= te($m['is_read'] ? 'Marcar como no leído' : 'Marcar como leído') ?></button></form>
        <?php if ($m['mail_status'] === 'failed' || ($m['mail_status'] === 'off' && role_at_least($role, 'admin'))): ?>
        <form method="post" action="<?= ADM ?>/messages/resend"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm"><?= te($m['mail_status'] === 'failed' ? 'Reintentar envío' : 'Enviar aviso por email') ?></button></form>
        <?php endif; ?>
        <form method="post" action="<?= ADM ?>/messages/delete" data-confirm="<?= te('¿Borrar este mensaje?') ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Borrar') ?></button></form>
      </div>
    </div>
  </details>
<?php endforeach; ?>
</div>
<?php endif; ?>
