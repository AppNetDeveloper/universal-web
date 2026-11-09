<?php /** Selector de idioma ES · RO · EN (login). Fija la cookie uw_admin_lang. */ ?>
<p class="login-lang" aria-label="<?= te('Idioma') ?>">
<?php foreach (['es' => 'ES', 'ro' => 'RO', 'en' => 'EN'] as $code => $label): ?>
  <?= $code !== 'es' ? '<span aria-hidden="true">·</span>' : '' ?><a href="<?= e($langBase ?? ADM . '/login') ?>?lang=<?= $code ?>" hreflang="<?= $code ?>" title="<?= e(PANEL_LOCALES[$code]) ?>"<?= panel_locale() === $code ? ' class="on" aria-current="true"' : '' ?>><?= $label ?></a>
<?php endforeach; ?>
</p>
