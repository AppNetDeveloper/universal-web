<?php
$c = $svc->settings()['contact'];
$back = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
?>
<section class="section" id="contacto">
  <div class="container contact">
    <div>
      <h2><?= e($d['title'] ?: __('Contacto')) ?></h2>
      <?php if ($d['text']): ?><p class="lead"><?= e($d['text']) ?></p><?php endif; ?>
      <?php if ($c['email'] || $c['phone'] || $c['address']): ?>
      <ul class="plain contact-info">
        <?php if ($c['email']): ?><li><strong><?= e(__('Email')) ?></strong> <a href="mailto:<?= e($c['email']) ?>"><?= e($c['email']) ?></a></li><?php endif; ?>
        <?php if ($c['phone']): ?><li><strong><?= e(__('Teléfono')) ?></strong> <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $c['phone'])) ?>"><?= e($c['phone']) ?></a></li><?php endif; ?>
        <?php if ($c['address']): ?><li><strong><?= e(__('Dirección')) ?></strong> <?= e($c['address']) ?></li><?php endif; ?>
      </ul>
      <?php endif; ?>
    </div>
    <form class="form card" method="post" action="/api/contact">
      <?php if (isset($_GET['enviado'])): ?><p class="alert ok" role="status"><?= e(__('¡Gracias! Hemos recibido tu mensaje.')) ?></p><?php endif; ?>
      <?php if (isset($_GET['error'])): ?><p class="alert err" role="alert"><?= e(__((string) $_GET['error'])) ?></p><?php endif; ?>
      <label><?= e(__('Nombre')) ?> <input name="name" required maxlength="200" autocomplete="name"></label>
      <label><?= e(__('Email')) ?> <input type="email" name="email" required maxlength="200" autocomplete="email"></label>
      <?php if (uw_truthy($d['show_phone'] ?? '')): ?><label><?= e(__('Teléfono')) ?> <input type="tel" name="phone" maxlength="40" autocomplete="tel"></label><?php endif; ?>
      <label><?= e(__('Asunto')) ?> <input name="subject" maxlength="200"></label>
      <label><?= e(__('Mensaje')) ?> <textarea name="message" rows="5" required></textarea></label>
      <input type="hidden" name="page" value="<?= e($back) ?>">
      <div class="hp" aria-hidden="true"><label>Web <input name="website" tabindex="-1" autocomplete="off"></label></div>
      <?= form_protection_fields($svc) ?>
      <?php if (($d['privacy_url'] ?? '') !== ''): ?>
      <input type="hidden" name="_privacy" value="1">
      <label class="check"><input type="checkbox" name="privacy" value="1" required> <span><?= e(__('He leído y acepto la')) ?> <a href="<?= e($d['privacy_url']) ?>" target="_blank" rel="noopener"><?= e(__('política de privacidad')) ?></a></span></label>
      <?php endif; ?>
      <button class="btn" type="submit"><?= e(($d['button_text'] ?? '') ?: __('Enviar mensaje')) ?></button>
    </form>
  </div>
</section>
