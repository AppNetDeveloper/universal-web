<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Internacionalización del panel de administración (es, ro, en).
 * El texto en español del código es la clave: t('Guardar') devuelve la traducción al idioma del panel
 * (app/lang/admin-ro.php, admin-en.php) o el propio texto si no hay traducción.
 */

const PANEL_LOCALES = ['es' => 'Español', 'ro' => 'Română', 'en' => 'English'];
const PANEL_LANG_COOKIE = 'uw_admin_lang';

/** Idioma del panel: usuario con sesión → cookie → Accept-Language → es. */
function panel_locale(?string $set = null): string
{
    static $loc = null;
    if ($set !== null && isset(PANEL_LOCALES[$set])) $loc = $set;
    if ($loc !== null) return $loc;
    $found = null;
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['uid']) && function_exists('current_user')) {
        try {
            $l = (string) (current_user()['locale'] ?? '');
            if (isset(PANEL_LOCALES[$l])) $found = $l;
        } catch (Throwable) {
        }
    }
    if ($found === null) {
        $l = (string) ($_COOKIE[PANEL_LANG_COOKIE] ?? '');
        if (isset(PANEL_LOCALES[$l])) $found = $l;
    }
    return $loc = $found ?? panel_accept_language();
}

/** Mejor coincidencia de la cabecera Accept-Language entre es, ro y en (por defecto es). */
function panel_accept_language(): string
{
    $best = 'es';
    $bestQ = 0.0;
    foreach (explode(',', strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''))) as $part) {
        $bits = explode(';', trim($part));
        $code = substr(trim($bits[0]), 0, 2);
        if (!isset(PANEL_LOCALES[$code])) continue;
        $q = 1.0;
        foreach (array_slice($bits, 1) as $b) if (preg_match('/^\s*q=([0-9.]+)/', $b, $m)) $q = (float) $m[1];
        if ($q > $bestQ) { $best = $code; $bestQ = $q; }
    }
    return $best;
}

/** Guarda el idioma elegido en una cookie de un año. */
function panel_set_locale_cookie(string $loc): void
{
    if (!isset(PANEL_LOCALES[$loc]) || headers_sent()) return;
    setcookie(PANEL_LANG_COOKIE, $loc, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax', 'secure' => is_https(), 'httponly' => true]);
    $_COOKIE[PANEL_LANG_COOKIE] = $loc;
}

/** Si la URL trae ?lang=xx válido (login, instalador): fija cookie e idioma y devuelve true. */
function panel_lang_switch(): bool
{
    $l = (string) ($_GET['lang'] ?? '');
    if (!isset(PANEL_LOCALES[$l])) return false;
    panel_set_locale_cookie($l);
    panel_locale($l);
    return true;
}

/** Tras iniciar sesión: si el visitante eligió idioma en el login (cookie), pasa a ser el de su cuenta. */
function panel_adopt_cookie_locale(int $userId): void
{
    $l = (string) ($_COOKIE[PANEL_LANG_COOKIE] ?? '');
    if (!isset(PANEL_LOCALES[$l])) return;
    q('UPDATE users SET locale = ? WHERE id = ? AND locale != ?', [$l, $userId, $l]);
    panel_locale($l);
}

/** Login: si la URL trae ?lang=xx válido, fija la cookie y vuelve a $to (sin el parámetro). */
function panel_lang_redirect(string $to): void
{
    $l = (string) ($_GET['lang'] ?? '');
    if (!isset(PANEL_LOCALES[$l])) return;
    panel_set_locale_cookie($l);
    redirect($to);
}

/** Diccionario (español => traducción) del idioma indicado; vacío para español. */
function panel_dict(string $loc): array
{
    static $cache = [];
    if ($loc === 'es' || !isset(PANEL_LOCALES[$loc])) return [];
    if (!isset($cache[$loc])) {
        $f = __DIR__ . '/lang/admin-' . $loc . '.php';
        $d = is_file($f) ? include $f : [];
        $cache[$loc] = is_array($d) ? $d : [];
    }
    return $cache[$loc];
}

/** Traduce un texto en español y sustituye los marcadores {nombre}. La salida NO se escapa. */
function t(string $es, array $vars = []): string
{
    $out = panel_dict(panel_locale())[$es] ?? $es;
    if ($vars) {
        $map = [];
        foreach ($vars as $k => $v) $map['{' . $k . '}'] = (string) $v;
        $out = strtr($out, $map);
    }
    return $out;
}

/** t() escapado para HTML (texto plano). */
function te(string $es, array $vars = []): string
{
    return e(t($es, $vars));
}

/** t() para textos con HTML propio (<b>, <code>…): los valores de $vars se escapan, el texto no. */
function th(string $es, array $vars = []): string
{
    return t($es, array_map(fn($v) => e($v), $vars));
}

/**
 * Traduce un mensaje ya construido (flash, error de código compartido): coincidencia exacta o,
 * si no, por plantilla con marcadores {x} del diccionario (p. ej. «No existe el archivo {path}»).
 */
function t_msg(string $msg): string
{
    $loc = panel_locale();
    $dict = panel_dict($loc);
    if ($loc === 'es' || $msg === '') return $msg;
    if (isset($dict[$msg])) return $dict[$msg];
    static $pat = [];
    if (!isset($pat[$loc])) {
        $pat[$loc] = [];
        foreach ($dict as $k => $v) {
            if (!str_contains((string) $k, '{')) continue;
            $seen = [];
            $re = preg_replace_callback('/\\\\\{([a-z_0-9]+)\\\\\}/i', function ($m) use (&$seen) {
                // Un marcador repetido ({url} dos veces) debe coincidir con el mismo valor.
                return isset($seen[$m[1]]) ? '(?P=' . $m[1] . ')' : '(?P<' . ($seen[$m[1]] = $m[1]) . '>.+?)';
            }, preg_quote((string) $k, '/'));
            $pat[$loc][] = ['/^' . $re . '$/su', (string) $v];
        }
    }
    foreach ($pat[$loc] as [$re, $v]) {
        if (@preg_match($re, $msg, $m) === 1) {
            $map = [];
            foreach ($m as $name => $val) if (is_string($name)) $map['{' . $name . '}'] = t_msg($val);
            return strtr($v, $map);
        }
    }
    return $msg;
}

/** Claves de admin.js (texto en español de tr('...')). */
const PANEL_JS_KEYS = [
    '¡Copiado!',
    'Copia manualmente:',
    '{lines} líneas · {chars} caracteres · {kb} / {max} KB',
    'Texto',
    '/ruta o https://',
    'Mover arriba',
    'Mover abajo',
    'Quitar',
    'Debe ser una lista JSON de bloques.',
    'Bloque #{i}: tipo desconocido ({type}).',
    '+ Añadir',
    'Admite Markdown',
    '/media/... o enlace de YouTube/Vimeo',
    '/media/... o https://',
    'Expandir',
    'Contraer',
    'Duplicar',
    'Eliminar',
    '¿Eliminar este bloque?',
    'Esta página aún no tiene bloques. Añade el primero abajo.',
    'JSON no válido: {msg}',
    'Siguiente', 'Anterior', 'Terminar', 'Cerrar', 'En esta sección:', 'Ejemplo:',
    'Te enseño las partes de esta pantalla una a una.', 'Rellena este campo si lo necesitas.', 'Guarda o aplica lo de esta pantalla.', 'Lista', 'Cada fila es un elemento. Los botones de la derecha actúan sobre esa fila.',
];

/** Subconjunto del diccionario que usa admin.js (vacío en español). */
function t_js(): array
{
    $dict = panel_dict(panel_locale());
    $out = [];
    foreach (PANEL_JS_KEYS as $k) if (isset($dict[$k])) $out[$k] = $dict[$k];
    return $out;
}

/** Traduce recursivamente los valores de texto de $arr cuyas claves estén en $keys. */
function t_walk(array $arr, array $keys): array
{
    foreach ($arr as $k => $v) {
        if (is_array($v)) $arr[$k] = t_walk($v, $keys);
        elseif (is_string($v) && in_array($k, $keys, true)) $arr[$k] = t_msg($v);
    }
    return $arr;
}
