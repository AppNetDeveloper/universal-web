<?php
/** Reproductor «Escuchar» compacto. Recibe $listen = ['src', 'title', 'duration', 'transcript' (Markdown)]. Solo audio propio (/media/… o https). */
$lsrc = trim((string) ($listen['src'] ?? ''));
if ($lsrc === '' || !(str_starts_with($lsrc, '/media/') || str_starts_with($lsrc, 'https://')) || preg_match('/[\s"\'<>]/', $lsrc)) return;
$ltitle = trim((string) ($listen['title'] ?? '')) ?: __('Escuchar esta página');
$ltype = ['mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav'][strtolower(pathinfo((string) parse_url($lsrc, PHP_URL_PATH), PATHINFO_EXTENSION))] ?? '';
?>
<div class="listen">
  <div class="listen-head">
    <svg class="listen-icon" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><path d="M11 5 6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 0 1 0 7M18.5 5.5a9 9 0 0 1 0 13"/></svg>
    <span class="listen-title"><?= e($ltitle) ?></span>
    <?php if (trim((string) ($listen['duration'] ?? '')) !== ''): ?><span class="listen-duration"><?= e((string) $listen['duration']) ?></span><?php endif; ?>
  </div>
  <audio class="listen-audio" controls preload="none"><source src="<?= e($lsrc) ?>"<?= $ltype ? ' type="' . e($ltype) . '"' : '' ?>></audio>
  <?php if (trim((string) ($listen['transcript'] ?? '')) !== ''): ?>
  <details class="listen-transcript"><summary><?= e(__('Ver transcripción')) ?></summary><div class="prose"><?= md((string) $listen['transcript']) ?></div></details>
  <?php endif; ?>
</div>
