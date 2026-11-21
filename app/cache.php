<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/** Caché de página completa (ver page_cache_*). */
final class PageCache
{
    public static bool $skip = false;

    /** Esta respuesta no se puede guardar en caché (vista previa, Markdown negociado, cookies…). */
    public static function skip(): void
    {
        self::$skip = true;
    }
}
