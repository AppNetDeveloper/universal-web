<?php
declare(strict_types=1);

// Servidor integrado de PHP (desarrollo): servir directamente los archivos estáticos.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) return false;
}

require __DIR__ . '/../app/bootstrap.php';
uw_handle_request();
