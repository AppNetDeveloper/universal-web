<?php /** @var array $members @var array $supers @var array $user */
$roles = ['editor' => t('Editor'), 'admin' => t('Administrador')];
$isSuper = $role === 'super';
$editLink = fn(array $m) => $isSuper ? '<a class="btn btn-sm" href="' . ADM . '/users?edit=' . (int) $m['id'] . '">' . te('Editar') . '</a>' : ''; ?>
<div class="page-head"><h1><?= te('Usuarios') ?></h1></div>
<?php if ($editing ?? null): ?>
<section class="card narrow" id="editar-usuario">
  <h2><?= te('Editar usuario') ?></h2>
  <form method="post" action="<?= ADM ?>/users/edit" class="stack">
    <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $editing['id'] ?>">
    <label><?= te('Nombre') ?><input type="text" name="name" maxlength="100" value="<?= e($editing['name']) ?>"></label>
    <label><?= te('Email') ?><input type="email" name="email" required value="<?= e($editing['email']) ?>"></label>
    <label><?= te('Contraseña nueva') ?> <small class="muted"><?= te('(opcional, mín. 8; déjala vacía para no cambiarla)') ?></small><input type="password" name="new_password" autocomplete="new-password" minlength="8"></label>
    <label><?= te('Tu contraseña') ?> <small class="muted"><?= te('(solo si cambias el email o la contraseña)') ?></small><input type="password" name="confirm_password" autocomplete="current-password"></label>
    <p class="hint"><?= te('El email es con el que entra al panel: avísale si lo cambias.') ?></p>
    <div><button class="btn btn-primary"><?= te('Guardar') ?></button> <a class="btn btn-ghost" href="<?= ADM ?>/users"><?= te('Cancelar') ?></a></div>
  </form>
</section>
<?php endif; ?>
<?php if ($members): ?>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Nombre') ?></th><th><?= te('Email') ?></th><th><?= te('Rol') ?></th><th><?= te('2 pasos') ?></th><th><?= te('Último acceso') ?></th><th></th></tr></thead>
  <tbody>
  <?php foreach ($members as $m): $me = (int) $m['id'] === (int) $user['id']; ?>
    <tr>
      <td data-l="<?= te('Nombre') ?>"><b><?= e($m['name']) ?></b><?= $me ? ' ' . adm_badge(t('Tú'), 'blue') : '' ?></td>
      <td data-l="<?= te('Email') ?>"><?= e($m['email']) ?></td>
      <td data-l="<?= te('Rol') ?>">
        <?php if ($me): echo e($roles[$m['role']] ?? $m['role']); else: ?>
        <form method="post" action="<?= ADM ?>/users/role" class="inline"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
          <select name="role" data-autosubmit><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>" <?= $m['role'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></form>
        <?php endif; ?>
      </td>
      <td data-l="<?= te('2 pasos') ?>"><?= $m['has_2fa'] ? adm_badge(t('Activa'), 'green') : adm_badge(t('No'), 'gray') ?></td>
      <td data-l="<?= te('Último acceso') ?>"><?= e(adm_date($m['last_login_at'])) ?></td>
      <td class="row-actions"><?= $editLink($m) ?><?php if (!$me): ?>
        <?php if ($role === 'super' && $m['has_2fa']): ?><form method="post" action="<?= ADM ?>/users/2fa-reset" data-confirm="<?= te('¿Quitar la verificación en dos pasos de {name}? Deberá configurarla de nuevo.', ['name' => $m['name']]) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm"><?= te('Resetear 2 pasos') ?></button></form><?php endif; ?>
        <form method="post" action="<?= ADM ?>/users/remove" data-confirm="<?= te('¿Quitar a {name} de esta web?', ['name' => $m['name']]) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Quitar') ?></button></form>
      <?php endif; ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table></div>
<?php else: ?><div class="empty"><p><?= te('Esta web aún no tiene usuarios propios.') ?></p></div><?php endif; ?>

<?php if ($supers): ?>
<h2 class="sec"><?= te('Superadmins') ?></h2>
<div class="table-wrap"><table>
  <thead><tr><th><?= te('Nombre') ?></th><th><?= te('Email') ?></th><th><?= te('Rol') ?></th><th><?= te('2 pasos') ?></th><?php if ($isSuper): ?><th></th><?php endif; ?></tr></thead>
  <tbody><?php foreach ($supers as $m): $me = (int) $m['id'] === (int) $user['id']; ?>
    <tr><td data-l="<?= te('Nombre') ?>"><b><?= e($m['name']) ?></b></td><td data-l="<?= te('Email') ?>"><?= $role === 'super' ? e($m['email']) : '<span class="muted">' . te('oculto') . '</span>' ?></td><td data-l="<?= te('Rol') ?>"><?= adm_badge(t('Superadmin'), 'blue') ?></td>
      <td data-l="<?= te('2 pasos') ?>"><?= $m['has_2fa'] ? adm_badge(t('Activa'), 'green') : adm_badge(t('No'), 'red') ?></td>
      <?php if ($isSuper): ?><td class="row-actions"><?= $editLink($m) ?><?php if (!$me): ?><form method="post" action="<?= ADM ?>/users/super-remove" data-confirm="<?= te('¿Quitar a {name} como superadmin? Perderá el acceso a todas las webs.', ['name' => $m['name']]) ?>"><?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>"><button class="btn btn-sm btn-danger"><?= te('Quitar superadmin') ?></button></form><?php endif; ?></td><?php endif; ?></tr>
  <?php endforeach; ?></tbody>
</table></div>
<?php endif; ?>

<section class="card" style="margin-top:1.5rem">
  <h2><?= te('Añadir usuario a esta web') ?></h2>
  <form method="post" action="<?= ADM ?>/users/add" class="stack">
    <?= csrf_field() ?>
    <div class="row">
      <label class="grow"><?= te('Email') ?><input type="email" name="email" required></label>
      <label class="grow"><?= te('Nombre') ?><input type="text" name="name" maxlength="100"></label>
    </div>
    <div class="row">
      <label class="grow"><?= te('Contraseña') ?> <small class="muted"><?= te('(mín. 8; no hace falta si el email ya existe)') ?></small><input type="password" name="password" autocomplete="new-password" minlength="8"></label>
      <label><?= te('Rol') ?><select name="role"><?php foreach ($roles as $k => $l): ?><option value="<?= $k ?>"><?= $l ?></option><?php endforeach; ?><?php if ($isSuper): ?><option value="super"><?= te('Superadmin (todas las webs)') ?></option><?php endif; ?></select></label>
    </div>
    <?php if ($isSuper): ?><label><?= te('Tu contraseña') ?> <small class="muted"><?= te('(solo para crear un superadmin)') ?></small><input type="password" name="confirm_password" autocomplete="current-password"></label><?php endif; ?>
    <div><button class="btn btn-primary"><?= te('Añadir') ?></button> <a class="btn btn-ghost" href="<?= ADM ?>/account"><?= te('Cambiar mi contraseña') ?></a></div>
  </form>
</section>
