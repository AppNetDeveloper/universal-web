<?php
declare(strict_types=1);
// Universal Web · Copyright (c) 2026 BionicAdri · Licencia BionicAdri 2.0 (ver LICENSE)

/**
 * Historial y papelera de páginas y entradas.
 *
 * Cada vez que se cambia una página o una entrada, la versión anterior se guarda (las últimas HISTORY_KEEP por
 * idioma y slug). Borrar no destruye: va a la papelera durante TRASH_DAYS días. Así, si una IA (o una persona)
 * estropea o borra algo, se recupera. El historial va por idioma + slug, así que sobrevive a borrar y recuperar.
 * Restaurar pasa por savePage/savePost (mismas validaciones) y guarda a su vez la versión actual: también se deshace.
 */

const HISTORY_KEEP = 30;
const TRASH_DAYS = 30;
const HISTORY_POST_FIELDS = ['title', 'slug', 'excerpt', 'content', 'cover_image', 'audio', 'tags', 'author', 'status', 'published_at', 'seo'];

/** Guarda la versión actual (tal cual está en la BD) antes de cambiarla. */
function history_snapshot(int $siteId, string $kind, array $row): void
{
    $data = array_intersect_key($row, array_flip($kind === 'page' ? ['title', 'slug', 'lang', 'status', 'blocks', 'seo'] : array_merge(HISTORY_POST_FIELDS, ['lang'])));
    insert('content_versions', ['site_id' => $siteId, 'kind' => $kind, 'lang' => (string) $row['lang'], 'slug' => (string) $row['slug'],
        'data' => json_enc($data), 'actor_name' => Actor::$name, 'created_at' => now()]);
    q('DELETE FROM content_versions WHERE site_id = ? AND kind = ? AND lang = ? AND slug = ? AND id NOT IN
        (SELECT id FROM content_versions WHERE site_id = ? AND kind = ? AND lang = ? AND slug = ? ORDER BY id DESC LIMIT ' . HISTORY_KEEP . ')',
        [$siteId, $kind, $row['lang'], $row['slug'], $siteId, $kind, $row['lang'], $row['slug']]);
}

/** Versiones anteriores, de la más reciente a la más antigua, con un resumen para elegir sin abrirlas. */
function history_list(SiteService $svc, string $kind, string $slug, string $lang): array
{
    return array_map(function ($v) use ($kind) {
        $d = json_dec($v['data']);
        $summary = $kind === 'page'
            ? count(json_dec((string) ($d['blocks'] ?? '[]'))) . ' bloques: ' . implode(', ', array_slice(array_column(json_dec((string) ($d['blocks'] ?? '[]')), 'type'), 0, 8))
            : mb_strlen((string) ($d['content'] ?? '')) . ' caracteres';
        return ['version_id' => (int) $v['id'], 'saved_at' => $v['created_at'], 'by' => $v['actor_name'], 'title' => (string) ($d['title'] ?? ''),
            'status' => (string) ($d['status'] ?? ''), 'summary' => $summary];
    }, rows('SELECT * FROM content_versions WHERE site_id = ? AND kind = ? AND lang = ? AND slug = ? ORDER BY id DESC LIMIT ' . HISTORY_KEEP,
        [$svc->id(), $kind, $lang, $slug]));
}

/** Vuelve a una versión (sin id: la inmediatamente anterior = deshacer el último cambio). Devuelve la página/entrada. */
function history_restore(SiteService $svc, string $kind, string $slug, string $lang, int $versionId = 0): array
{
    $v = $versionId
        ? row('SELECT * FROM content_versions WHERE id = ? AND site_id = ? AND kind = ?', [$versionId, $svc->id(), $kind])
        : row('SELECT * FROM content_versions WHERE site_id = ? AND kind = ? AND lang = ? AND slug = ? ORDER BY id DESC LIMIT 1', [$svc->id(), $kind, $lang, $slug]);
    if (!$v) throw new UwError($versionId ? 'Versión no encontrada' : "No hay versiones anteriores de «{$slug}»: no hay nada que deshacer");
    $d = json_dec($v['data']);
    $cur = $kind === 'page' ? $svc->page((string) $v['slug'], (string) $v['lang']) : $svc->post((string) $v['slug'], (string) $v['lang']);
    if ($kind === 'page') {
        $res = $svc->savePage(['title' => $d['title'], 'slug' => $d['slug'], 'lang' => $d['lang'], 'status' => $d['status'],
            'blocks' => json_dec((string) $d['blocks']), 'seo' => json_dec((string) $d['seo'])], $cur ? (int) $cur['id'] : null);
    } else {
        $in = array_intersect_key($d, array_flip(HISTORY_POST_FIELDS)) + ['lang' => $d['lang']];
        $in['tags'] = json_dec((string) ($in['tags'] ?? '[]'));
        $in['seo'] = json_dec((string) ($in['seo'] ?? '{}'));
        $res = $svc->savePost($in, $cur ? (int) $cur['id'] : null);
    }
    audit($svc->id(), "$kind.restore", $v['slug'], ['version' => (int) $v['id']]);
    return $res;
}

/** Mueve a la papelera (copia completa) antes de borrar. */
function trash_put(int $siteId, string $kind, array $row): void
{
    insert('content_trash', ['site_id' => $siteId, 'kind' => $kind, 'lang' => (string) $row['lang'], 'slug' => (string) $row['slug'],
        'title' => (string) $row['title'], 'data' => json_enc($row), 'actor_name' => Actor::$name, 'deleted_at' => now()]);
}

function trash_list(SiteService $svc): array
{
    q("DELETE FROM content_trash WHERE deleted_at < datetime('now', ?)", ['-' . TRASH_DAYS . ' days']);
    return array_map(fn($t) => ['trash_id' => (int) $t['id'], 'kind' => $t['kind'], 'lang' => $t['lang'], 'slug' => $t['slug'], 'title' => $t['title'],
        'deleted_at' => $t['deleted_at'], 'by' => $t['actor_name']],
        rows('SELECT id, kind, lang, slug, title, deleted_at, actor_name FROM content_trash WHERE site_id = ? ORDER BY id DESC LIMIT 100', [$svc->id()]));
}

/** Recupera de la papelera (como borrador si se pide, o con su estado). Si el slug ya está ocupado, avisa. */
function trash_restore(SiteService $svc, int $trashId): array
{
    $t = row('SELECT * FROM content_trash WHERE id = ? AND site_id = ?', [$trashId, $svc->id()]) ?? throw new UwError('No está en la papelera');
    $d = json_dec($t['data']);
    $exists = $t['kind'] === 'page' ? $svc->page($t['slug'], $t['lang']) : $svc->post($t['slug'], $t['lang']);
    if ($exists) throw new UwError("Ya existe «{$t['slug']}» en «{$t['lang']}»: cámbiale el slug o bórrala antes de recuperar esta");
    if ($t['kind'] === 'page') {
        $res = $svc->savePage(['title' => $d['title'], 'slug' => $d['slug'], 'lang' => $d['lang'], 'status' => $d['status'],
            'blocks' => json_dec((string) $d['blocks']), 'seo' => json_dec((string) $d['seo'])]);
    } else {
        $in = array_intersect_key($d, array_flip(HISTORY_POST_FIELDS)) + ['lang' => $d['lang']];
        $in['tags'] = json_dec((string) ($in['tags'] ?? '[]'));
        $in['seo'] = json_dec((string) ($in['seo'] ?? '{}'));
        $res = $svc->savePost($in);
    }
    q('DELETE FROM content_trash WHERE id = ?', [$trashId]);
    audit($svc->id(), "{$t['kind']}.untrash", $t['slug']);
    return $res;
}

// ---------------------------------------------------------------- panel

function adm_trash(array $c): void
{
    adm_render('trash', $c, ['title' => 'Papelera', 'items' => trash_list($c['svc'])]);
}

function adm_trash_restore(array $c): void
{
    $r = trash_restore($c['svc'], adm_id());
    flash(t('«{title}» recuperado.', ['title' => $r['title']]));
    redirect(ADM . '/trash');
}

function adm_page_restore(array $c): void
{
    $svc = $c['svc'];
    $p = $svc->pageById(adm_id()) ?? throw new UwError(t('Página no encontrada'));
    $r = history_restore($svc, 'page', $p['slug'], $p['lang'], (int) ($_POST['version'] ?? 0));
    flash(t('Página restaurada a la versión elegida (la anterior queda en el historial).'));
    redirect(ADM . '/pages/edit?id=' . $r['id']);
}

function adm_post_restore(array $c): void
{
    $svc = $c['svc'];
    $p = $svc->postById(adm_id()) ?? throw new UwError(t('Entrada no encontrada'));
    $r = history_restore($svc, 'post', $p['slug'], $p['lang'], (int) ($_POST['version'] ?? 0));
    flash(t('Entrada restaurada a la versión elegida (la anterior queda en el historial).'));
    redirect(ADM . '/posts/edit?id=' . $r['id']);
}
