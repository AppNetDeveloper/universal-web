<section class="container notfound">
  <p class="eyebrow"><?= e(__('Error 404')) ?></p>
  <h1><?= e(__('Página no encontrada')) ?></h1>
  <p><?= e(__('Lo sentimos, la página que buscas no existe o ha cambiado de sitio.')) ?></p>
  <p><a class="btn" href="<?= e($svc->langPath('/')) ?>"><?= e(__('Volver al inicio')) ?></a></p>
</section>
