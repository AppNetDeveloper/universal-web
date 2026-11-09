// node tests/hours_now.js themes/default/blocks/hours.php → ejecuta el <script> real del bloque con día/hora simulados (18-oct).
const fs = require('fs');
const php = fs.readFileSync(process.argv[2], 'utf8');
const js = php.slice(php.indexOf('<script>') + 8, php.indexOf('</script>'));
function prueba(rows, weekday, hora) {
  const el = { attrs: { 'data-hours': JSON.stringify({ tz: 'Europe/Madrid', rows }), 'data-open': 'ABIERTO', 'data-closed': 'CERRADO' },
    getAttribute(k) { return this.attrs[k]; }, textContent: '', className: '', hidden: true };
  const [h, m] = hora.split(':');
  const window = { Intl: true };
  const Intl = { DateTimeFormat: function () { return { formatToParts: () => [
    { type: 'weekday', value: weekday }, { type: 'hour', value: h }, { type: 'minute', value: m }] }; } };
  const document = { currentScript: { previousElementSibling: { querySelector: () => el } } };
  new Function('window', 'Intl', 'document', js)(window, Intl, document);
  return el.textContent;
}
const bar = [{ d: ['fr', 'sa'], c: false, s: [['22:00', '02:00']] }];
const normal = [{ d: ['mo', 'tu', 'we', 'th', 'fr'], c: false, s: [['09:00', '14:00'], ['17:00', '00:00']] }];
const casos = [
  ['bar sábado 23:00', prueba(bar, 'Sat', '23:00'), 'ABIERTO'],
  ['bar domingo 01:00 (turno del sábado)', prueba(bar, 'Sun', '01:00'), 'ABIERTO'],
  ['bar domingo 03:00', prueba(bar, 'Sun', '03:00'), 'CERRADO'],
  ['bar viernes 01:00 (jueves no abre)', prueba(bar, 'Fri', '01:00'), 'CERRADO'],
  ['normal martes 10:00', prueba(normal, 'Tue', '10:00'), 'ABIERTO'],
  ['normal martes 15:00', prueba(normal, 'Tue', '15:00'), 'CERRADO'],
  ['normal martes 23:30 (cierra 00:00)', prueba(normal, 'Tue', '23:30'), 'ABIERTO'],
  ['normal sábado 00:30 (viernes cierra 00:00)', prueba(normal, 'Sat', '00:30'), 'CERRADO'],
];
let mal = 0;
for (const [n, got, want] of casos) { const ok = got === want; if (!ok) mal++; console.log((ok ? 'ok  ' : 'FAIL') + ' ' + n + ' → ' + got); }
process.exit(mal ? 1 : 0);
