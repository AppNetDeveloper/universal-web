<?php
/** Horario de apertura. «Abierto ahora» se calcula en el navegador con la zona horaria de la web (sirve también en estático). */
$rows = array_values(array_filter($d['items'], fn($r) => hours_days((string) $r['days']) || trim((string) $r['label']) !== ''));
$tz = (string) config('timezone', 'Europe/Madrid');
$data = array_map(fn($r) => ['d' => hours_days((string) $r['days']), 'c' => uw_truthy($r['closed'] ?? ''),
    's' => array_values(array_filter([[hours_time($r['opens']), hours_time($r['closes'])], [hours_time($r['opens2']), hours_time($r['closes2'])]], fn($x) => $x[0] !== '' && $x[1] !== ''))], $rows);
?>
<?php if ($rows): ?>
<section class="section block-hours">
  <div class="container narrow">
    <div class="hours-card card">
      <div class="hours-head">
        <h2><?= e($d['title'] ?: __('Horario')) ?></h2>
        <span class="hours-now" data-hours="<?= e(json_enc(['tz' => $tz, 'rows' => $data])) ?>" data-open="<?= e(__('Abierto ahora')) ?>" data-closed="<?= e(__('Cerrado ahora')) ?>" hidden></span>
      </div>
      <dl class="hours-list">
        <?php foreach ($rows as $i => $r): $sh = $data[$i]['s']; ?>
        <div class="hours-row">
          <dt><?= e(hours_label($r)) ?></dt>
          <dd><?= $data[$i]['c'] || !$sh ? '<span class="muted">' . e(__('Cerrado')) . '</span>' : e(implode(' · ', array_map(fn($x) => $x[0] . '–' . $x[1], $sh))) ?></dd>
        </div>
        <?php endforeach; ?>
      </dl>
      <?php if ($d['note'] !== ''): ?><p class="hours-note muted"><?= e($d['note']) ?></p><?php endif; ?>
    </div>
  </div>
</section>
<script>
(function () {
  var el = document.currentScript.previousElementSibling.querySelector('.hours-now');
  if (!el || !window.Intl) return;
  try {
    var cfg = JSON.parse(el.getAttribute('data-hours'));
    var p = new Intl.DateTimeFormat('en-GB', { timeZone: cfg.tz, weekday: 'short', hour: '2-digit', minute: '2-digit', hour12: false }).formatToParts(new Date());
    var get = function (t) { return (p.find(function (x) { return x.type === t; }) || {}).value; };
    var day = (get('weekday') || '').slice(0, 2).toLowerCase(), now = get('hour') + ':' + get('minute');
    // Un turno que cierra antes de abrir cruza la medianoche: 22:00–02:00 del sábado sigue abierto el domingo a la 01:00.
    var dias = ['mo', 'tu', 'we', 'th', 'fr', 'sa', 'su'], ayer = dias[(dias.indexOf(day) + 6) % 7];
    var open = cfg.rows.some(function (r) { return !r.c && r.s.some(function (s) {
      var cruza = s[1] <= s[0];
      if (r.d.indexOf(day) !== -1 && now >= s[0] && (cruza || now < s[1])) return true;
      return cruza && r.d.indexOf(ayer) !== -1 && now < s[1];
    }); });
    el.textContent = open ? el.getAttribute('data-open') : el.getAttribute('data-closed');
    el.className = 'hours-now ' + (open ? 'is-open' : 'is-closed');
    el.hidden = false;
  } catch (e) {}
})();
</script>
<?php endif; ?>
