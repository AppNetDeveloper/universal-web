<?php
declare(strict_types=1);

// Servidor integrado de PHP (desarrollo): servir directamente los archivos estáticos.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) return false;
}

// Modo mantenimiento (actualización en curso): responde 503 sin cargar la aplicación.
require __DIR__ . '/../app/maintenance.php';
if (uw_maintenance_gate()) return;

// Caché de página completa: si la página ya está generada, se envía sin cargar la aplicación ni la base de datos.
require __DIR__ . '/../app/cache.php';
if (page_cache_fast_serve()) {
    if (uw_cache_tick_due()) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        require __DIR__ . '/../app/bootstrap.php';
        if (is_installed()) { try { uw_background_ticks(); } catch (Throwable $e) { error_log('[universal-web] ' . $e->getMessage()); } }
    }
    return;
}

require __DIR__ . '/../app/bootstrap.php';
uw_handle_request();
