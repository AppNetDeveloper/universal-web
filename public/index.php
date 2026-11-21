<?php
declare(strict_types=1);

// Servidor integrado de PHP (desarrollo): servir directamente los archivos estáticos.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file) && !str_ends_with($file, '.php')) return false;
}

// Actualización a medio terminar (el código nuevo ya está en su sitio): esta petición la termina —migraciones, caché,
// prueba de salud— o, si algo falla, vuelve sola a la versión anterior (app/update_finish.php).
if (is_file((getenv('UW_DATA') ?: dirname(__DIR__) . '/data') . '/updates/pending.json')) {
    require __DIR__ . '/../app/update_finish.php';
    uw_update_finish_entry();
}

// Modo mantenimiento (actualización en curso): responde 503 sin cargar la aplicación.
require_once __DIR__ . '/../app/maintenance.php';
if (uw_maintenance_gate()) return;

// Caché de página completa: si la página ya está generada, se envía sin cargar la aplicación ni la base de datos.
require_once __DIR__ . '/../app/cache.php';
if (page_cache_fast_serve()) {
    if (uw_cache_tick_due()) {
        if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
        require_once __DIR__ . '/../app/bootstrap.php';
        if (is_installed()) { try { uw_background_ticks(); } catch (Throwable $e) { error_log('[universal-web] ' . $e->getMessage()); } }
    }
    return;
}

require_once __DIR__ . '/../app/bootstrap.php';
uw_handle_request();
