<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Modo mantenimiento (actualización en curso). Lo comprueba public/index.php ANTES de cargar la aplicación, porque
 * durante una actualización el código está a medio cambiar. Archivo: data/maintenance.json {since, until, reason}.
 * Caduca solo (until): si una actualización muere a mitad, la web no se queda cerrada para siempre.
 * La segunda mitad de la actualización la hace public/index.php antes de esta comprobación (app/update_finish.php).
 */

function uw_maintenance_file(): string
{
    return (getenv('UW_DATA') ?: dirname(__DIR__) . '/data') . '/maintenance.json';
}

/** Activa el modo mantenimiento durante $minutes como máximo. */
function uw_maintenance_on(string $reason, int $minutes = 20): void
{
    $f = uw_maintenance_file();
    @file_put_contents($f, json_encode(['since' => time(), 'until' => time() + $minutes * 60, 'reason' => $reason]) . "\n", LOCK_EX);
}

function uw_maintenance_off(): void
{
    @unlink(uw_maintenance_file());
}

/** Datos del modo mantenimiento si está activo y no ha caducado; null si no. */
function uw_maintenance_state(): ?array
{
    $raw = @file_get_contents(uw_maintenance_file());
    if ($raw === false) return null;
    $m = json_decode($raw, true);
    if (!is_array($m) || (int) ($m['until'] ?? 0) < time()) return null;
    return $m;
}

/** true = la petición ya se ha respondido con 503. */
function uw_maintenance_gate(): bool
{
    if (PHP_SAPI === 'cli') return false;
    $m = uw_maintenance_state();
    if ($m === null) return false;
    http_response_code(503);
    header('Retry-After: 120');
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex');
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex">'
        . '<title>En mantenimiento</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;font:16px/1.6 system-ui,sans-serif;background:#f6f7f9;color:#1c1f26}'
        . 'main{max-width:30rem;padding:2rem 1.5rem;text-align:center}p{color:#4b5563}@media (prefers-color-scheme:dark){body{background:#0f1115;color:#e6e8ee}p{color:#9aa3b2}}</style></head>'
        . '<body><main><h1>Volvemos en un momento</h1><p>Estamos actualizando la web. Vuelve a cargar la página en un par de minutos.</p>'
        . '<p lang="en">We are updating the site. Please reload in a couple of minutes.</p></main></body></html>';
    return true;
}
