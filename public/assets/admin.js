/* Universal Web · panel. Vanilla JS, sin dependencias. */
(function () {
  'use strict';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  // Traducción: el texto en español es la clave; el diccionario lo inyecta el layout (window.UW_I18N).
  function tr(s, v) {
    let o = (window.UW_I18N || {})[s] || s;
    if (v) for (const k in v) o = o.split('{' + k + '}').join(v[k]);
    return o;
  }

  function h(tag, attrs, ...kids) {
    const n = document.createElement(tag);
    for (const [k, v] of Object.entries(attrs || {})) {
      if (v === null || v === undefined || v === false) continue;
      if (k === 'class') n.className = v;
      else if (k === 'text') n.textContent = v;
      else if (k.startsWith('on')) n.addEventListener(k.slice(2), v);
      else n.setAttribute(k, v === true ? '' : v);
    }
    kids.flat().forEach(c => { if (c !== null && c !== undefined && c !== false) n.append(c.nodeType ? c : document.createTextNode(String(c))); });
    return n;
  }

  function slugify(s) {
    return s.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ß/g, 'ss')
      .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  }

  // ------------------------------------------------ generales
  document.addEventListener('DOMContentLoaded', () => {
    const toggle = $('#nav-toggle');
    if (toggle) toggle.addEventListener('click', () => document.body.classList.toggle('nav-open'));
    document.addEventListener('click', e => {
      if (document.body.classList.contains('nav-open') && !e.target.closest('#sidebar') && !e.target.closest('#nav-toggle')) document.body.classList.remove('nav-open');
    });

    const sw = $('#site-switch');
    if (sw) sw.addEventListener('change', () => { location.href = '/admin/switch?site=' + encodeURIComponent(sw.value) + '&to=' + encodeURIComponent(sw.dataset.to || '/'); });

    document.addEventListener('submit', e => {
      const f = e.target.closest('form[data-confirm]');
      if (f && !confirm(f.dataset.confirm)) e.preventDefault();
    });
    $$('select[data-autosubmit]').forEach(s => s.addEventListener('change', () => s.form.submit()));

    // copiar
    document.addEventListener('click', e => {
      const b = e.target.closest('[data-copy],[data-copy-target]');
      if (!b) return;
      const text = b.dataset.copy !== undefined ? b.dataset.copy : ($(b.dataset.copyTarget) || {}).textContent || '';
      const done = () => { const o = b.textContent; b.textContent = tr('¡Copiado!'); setTimeout(() => { b.textContent = o; }, 1500); };
      if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, () => fallback(text, done));
      else fallback(text, done);
    });
    function fallback(text, done) {
      const t = h('textarea', { style: 'position:fixed;opacity:0' }); t.value = text; document.body.append(t); t.select();
      try { document.execCommand('copy'); done(); } catch (_) { prompt(tr('Copia manualmente:'), text); }
      t.remove();
    }

    // slug automático
    $$('input[data-slug-from]').forEach(inp => {
      const src = $(inp.dataset.slugFrom);
      if (!src) return;
      let touched = inp.value !== '';
      inp.addEventListener('input', () => { touched = inp.value !== ''; });
      src.addEventListener('input', () => { if (!touched) inp.value = slugify(src.value); });
    });

    // contadores
    $$('[data-counter]').forEach(t => {
      const out = document.getElementById(t.dataset.counter);
      const upd = () => {
        const n = t.value.length; out.textContent = n;
        out.parentElement.classList.toggle('ok', n >= +t.dataset.min && n <= +t.dataset.max);
        out.parentElement.classList.toggle('bad', n > 0 && (n < +t.dataset.min || n > +t.dataset.max));
      };
      t.addEventListener('input', upd); upd();
    });

    initMenu();
    initBlocks();
    initDesign();
  });

  // ------------------------------------------------ diseño y código
  function initDesign() {
    const form = $('#design-form'); if (!form) return;
    const tabs = $$('#design-tabs [data-tab]');
    const panels = $$('.design-panel');
    const show = name => {
      if (!tabs.some(t => t.dataset.tab === name)) name = 'css';
      tabs.forEach(t => t.classList.toggle('active', t.dataset.tab === name));
      panels.forEach(p => { p.hidden = p.dataset.panel !== name; });
    };
    tabs.forEach(t => t.addEventListener('click', e => { e.preventDefault(); show(t.dataset.tab); history.replaceState(null, '', '#' + t.dataset.tab); }));
    show(location.hash.slice(1));

    let dirty = false;
    const flag = $('#design-dirty');
    $$('textarea.code-ed', form).forEach(t => {
      const out = $('[data-for="' + t.name + '"]', form);
      const upd = () => {
        const bytes = new Blob([t.value]).size, max = +t.dataset.max;
        const lines = t.value ? t.value.split('\n').length : 0;
        out.textContent = tr('{lines} líneas · {chars} caracteres · {kb} / {max} KB', { lines: lines, chars: t.value.length.toLocaleString(document.documentElement.lang || 'es'), kb: (bytes / 1024).toFixed(1), max: max / 1024 });
        out.classList.toggle('bad', bytes > max);
      };
      t.addEventListener('input', upd); upd();
      t.addEventListener('keydown', e => {
        if (e.key !== 'Tab' || e.ctrlKey || e.metaKey || e.altKey) return;
        e.preventDefault();
        t.setRangeText('  ', t.selectionStart, t.selectionEnd, 'end');
        t.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
    form.addEventListener('input', () => { dirty = true; if (flag) flag.hidden = false; });
    form.addEventListener('submit', () => { dirty = false; });
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); dirty = false; form.requestSubmit($('#design-save')); }
    });
    form.addEventListener('click', e => {
      const b = e.target.closest('[data-confirm-btn]');
      if (b && !confirm(b.dataset.confirmBtn)) e.preventDefault();
      else if (b) dirty = false;
    });
    const fav = $('#favicon-input'), img = $('#favicon-preview');
    if (fav && img) {
      img.addEventListener('error', () => { img.hidden = true; });
      img.addEventListener('load', () => { img.hidden = false; });
      fav.addEventListener('input', () => { if (fav.value.trim()) img.src = fav.value.trim(); else img.hidden = true; });
    }
  }

  // ------------------------------------------------ editor de menú
  function initMenu() {
    const box = $('#menu-editor'); if (!box) return;
    const items = JSON.parse(box.dataset.menu || '[]');
    const row = (it) => {
      const r = h('div', { class: 'menu-row' },
        h('input', { type: 'text', name: 'menu_label[]', placeholder: tr('Texto'), value: it.label || '', 'aria-label': tr('Texto') }),
        h('input', { type: 'text', name: 'menu_url[]', placeholder: tr('/ruta o https://'), value: it.url || '', 'aria-label': 'URL' }),
        h('button', { type: 'button', class: 'btn btn-sm', title: tr('Mover arriba'), onclick: () => { if (r.previousElementSibling) r.parentNode.insertBefore(r, r.previousElementSibling); } }, '↑'),
        h('button', { type: 'button', class: 'btn btn-sm', title: tr('Mover abajo'), onclick: () => { if (r.nextElementSibling) r.parentNode.insertBefore(r.nextElementSibling, r); } }, '↓'),
        h('button', { type: 'button', class: 'btn btn-sm btn-danger', title: tr('Quitar'), onclick: () => r.remove() }, '✕'));
      return r;
    };
    items.forEach(i => box.append(row(i)));
    $('#menu-add').addEventListener('click', () => { const r = row({}); box.append(r); r.firstChild.focus(); });
  }

  // ------------------------------------------------ editor de bloques
  function initBlocks() {
    const root = $('#block-editor'); if (!root) return;
    const form = root.closest('form');
    const schema = JSON.parse($('#block-schema').textContent);
    const hidden = $('#blocks-input');
    const jsonTa = $('#blocks-json');
    const jsonPanel = $('#json-panel');
    const jsonToggle = $('#json-toggle');
    const err = $('#blocks-error');
    const addBar = $('#add-block-bar');
    let jsonMode = false, dirty = false;

    const blank = fields => { const d = {}; fields.forEach(f => { d[f.name] = f.type === 'list' ? [] : f.type === 'number' ? 0 : ''; }); return d; };
    const fill = (fields, data) => {
      const d = {};
      fields.forEach(f => {
        const v = data ? data[f.name] : undefined;
        if (f.type === 'list') d[f.name] = (Array.isArray(v) ? v : []).filter(x => x && typeof x === 'object').map(it => fill(f.fields, it));
        else if (f.type === 'number') d[f.name] = Number(v) || 0;
        else d[f.name] = (v === null || v === undefined) ? '' : String(v);
      });
      return d;
    };
    const normalize = list => {
      if (!Array.isArray(list)) throw new Error(tr('Debe ser una lista JSON de bloques.'));
      return list.map((b, i) => {
        if (!b || !schema[b.type]) throw new Error(tr('Bloque #{i}: tipo desconocido ({type}).', { i: i, type: b && b.type }));
        return { type: b.type, data: fill(schema[b.type].fields, b.data && typeof b.data === 'object' ? b.data : b), _c: false };
      });
    };
    const clone = o => JSON.parse(JSON.stringify(o));
    const serialize = () => blocks.map(b => ({ type: b.type, data: b.data }));
    const markDirty = () => { dirty = true; };
    const move = (arr, i, d) => { const j = i + d; if (j < 0 || j >= arr.length) return false; [arr[i], arr[j]] = [arr[j], arr[i]]; return true; };

    let blocks;
    try { blocks = normalize(JSON.parse($('#blocks-data').textContent || '[]')); } catch (_) { blocks = []; }

    function showErr(msg) { err.textContent = msg || ''; err.hidden = !msg; }

    function field(f, obj) {
      const lbl = (extra) => h('span', null, f.label, extra || '');
      if (f.type === 'list') {
        const wrap = h('div', { class: 'list-field' }, h('div', { class: 'lf-title', text: f.label }));
        obj[f.name].forEach((it, i) => {
          const arr = obj[f.name];
          wrap.append(h('div', { class: 'it' },
            h('div', { class: 'it-head' }, h('span', { text: '#' + (i + 1) }),
              h('span', { class: 'it-btns' },
                h('button', { type: 'button', title: tr('Mover arriba'), onclick: () => { if (move(arr, i, -1)) { markDirty(); render(); } } }, '↑'),
                h('button', { type: 'button', title: tr('Mover abajo'), onclick: () => { if (move(arr, i, 1)) { markDirty(); render(); } } }, '↓'),
                h('button', { type: 'button', class: 'del', title: tr('Quitar'), onclick: () => { arr.splice(i, 1); markDirty(); render(); } }, '✕'))),
            f.fields.map(sf => field(sf, it))));
        });
        wrap.append(h('div', null, h('button', { type: 'button', class: 'btn btn-sm', onclick: () => { obj[f.name].push(blank(f.fields)); markDirty(); render(); } }, tr('+ Añadir'))));
        return wrap;
      }
      const set = v => { obj[f.name] = v; markDirty(); };
      let input;
      if (f.type === 'textarea' || f.type === 'markdown' || f.type === 'code') {
        input = h('textarea', { rows: f.type === 'markdown' || f.type === 'code' ? 6 : 3, class: f.type === 'code' ? 'mono' : null, spellcheck: f.type === 'code' ? 'false' : null,
          oninput: e => set(e.target.value) });
        input.value = obj[f.name];
        return h('label', { class: 'fld' }, lbl(f.type === 'markdown' ? h('span', { class: 'hint', text: ' · ' + tr('Admite Markdown') }) : ''), input);
      }
      if (f.type === 'number') {
        input = h('input', { type: 'number', min: '0', value: obj[f.name], oninput: e => set(e.target.value === '' ? 0 : Number(e.target.value)) });
        return h('label', { class: 'fld' }, lbl(), input);
      }
      if (f.type === 'video' || f.type === 'audio') {
        const prev = h('span', { class: 'media-prev' });
        const upd = v => {
          prev.replaceChildren();
          v = (v || '').trim();
          if (!v) return;
          if (/^https?:\/\/(www\.|m\.)?(youtube\.com|youtu\.be|youtube-nocookie\.com)\//i.test(v)) prev.append(h('span', { class: 'tag', text: 'YouTube' }));
          else if (/^https?:\/\/(www\.)?vimeo\.com\//i.test(v)) prev.append(h('span', { class: 'tag', text: 'Vimeo' }));
          else if (f.type === 'audio') prev.append(h('audio', { controls: true, preload: 'none', src: v }));
          else prev.append(h('video', { muted: true, preload: 'metadata', src: v + '#t=0.1' }));
        };
        input = h('input', { type: 'text', list: 'media-urls', placeholder: f.type === 'video' ? tr('/media/... o enlace de YouTube/Vimeo') : '/media/...', value: obj[f.name],
          oninput: e => { set(e.target.value); upd(e.target.value); } });
        upd(obj[f.name]);
        return h('label', { class: 'fld' }, lbl(), h('div', { class: 'img-field' }, input, prev));
      }
      if (f.type === 'image') {
        const img = h('img', { alt: '', src: obj[f.name] || null, hidden: !obj[f.name] });
        img.addEventListener('error', () => { img.hidden = true; });
        img.addEventListener('load', () => { img.hidden = false; });
        input = h('input', { type: 'text', list: 'media-urls', placeholder: tr('/media/... o https://'), value: obj[f.name],
          oninput: e => { set(e.target.value); if (e.target.value) img.src = e.target.value; else img.hidden = true; } });
        return h('label', { class: 'fld' }, lbl(), h('div', { class: 'img-field' }, input, img));
      }
      input = h('input', { type: f.type === 'url' ? 'text' : 'text', value: obj[f.name], oninput: e => set(e.target.value) });
      return h('label', { class: 'fld' }, lbl(), input);
    }

    function summary(b) {
      const d = b.data;
      for (const k of ['title', 'src', 'question']) if (typeof d[k] === 'string' && d[k]) return d[k];
      return '';
    }

    function card(b, i) {
      const t = schema[b.type];
      const btn = (txt, title, fn, cls) => h('button', { type: 'button', title, 'aria-label': title, class: cls, onclick: fn }, txt);
      return h('div', { class: 'bk' + (b._c ? ' collapsed' : '') },
        h('div', { class: 'bk-head' },
          h('button', { type: 'button', class: 'bk-toggle', title: b._c ? tr('Expandir') : tr('Contraer'), onclick: () => { b._c = !b._c; render(); } }, b._c ? '▸' : '▾'),
          h('strong', { title: t.description, text: t.label }),
          h('span', { class: 'bk-sum', text: summary(b) }),
          h('span', { class: 'bk-btns' },
            btn('↑', tr('Mover arriba'), () => { if (move(blocks, i, -1)) { markDirty(); render(); } }),
            btn('↓', tr('Mover abajo'), () => { if (move(blocks, i, 1)) { markDirty(); render(); } }),
            btn('⧉', tr('Duplicar'), () => { blocks.splice(i + 1, 0, clone(b)); markDirty(); render(); }),
            btn('✕', tr('Eliminar'), () => { if (confirm(tr('¿Eliminar este bloque?'))) { blocks.splice(i, 1); markDirty(); render(); } }, 'del'))),
        h('div', { class: 'bk-body' }, t.fields.map(f => field(f, b.data))));
    }

    function render() {
      root.replaceChildren();
      if (!blocks.length) root.append(h('p', { class: 'muted', text: tr('Esta página aún no tiene bloques. Añade el primero abajo.') }));
      blocks.forEach((b, i) => root.append(card(b, i)));
    }

    $('#add-block-btn').addEventListener('click', () => {
      const type = $('#add-block-type').value;
      blocks.push({ type, data: blank(schema[type].fields), _c: false });
      markDirty(); render();
      const last = root.lastElementChild; if (last) last.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    });

    jsonToggle.addEventListener('change', () => {
      showErr('');
      if (jsonToggle.checked) {
        jsonTa.value = JSON.stringify(serialize(), null, 2);
        jsonMode = true; root.hidden = true; addBar.hidden = true; jsonPanel.hidden = false;
      } else {
        try { blocks = normalize(JSON.parse(jsonTa.value || '[]')); }
        catch (ex) { showErr(tr('JSON no válido: {msg}', { msg: ex.message })); jsonToggle.checked = true; return; }
        jsonMode = false; root.hidden = false; addBar.hidden = false; jsonPanel.hidden = true; render();
      }
    });
    jsonTa.addEventListener('input', markDirty);

    form.addEventListener('submit', e => {
      if (jsonMode) {
        try { blocks = normalize(JSON.parse(jsonTa.value || '[]')); }
        catch (ex) { e.preventDefault(); showErr(tr('JSON no válido: {msg}', { msg: ex.message })); return; }
      }
      hidden.value = JSON.stringify(serialize());
      dirty = false;
    });
    form.addEventListener('input', markDirty);
    window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

    render();
    dirty = false;
  }
})();

// Confirmación en botones concretos (p. ej. desactivar 2FA dentro de un formulario con varias acciones)
document.addEventListener('click', function (ev) {
  var b = ev.target.closest && ev.target.closest('[data-confirm-click]');
  if (b && !window.confirm(b.getAttribute('data-confirm-click'))) ev.preventDefault();
});

// Tras un error al guardar: vuelve a rellenar el formulario con lo que se había escrito (sin contraseñas ni archivos).
document.addEventListener('DOMContentLoaded', function () {
  var el = document.getElementById('uw-refill');
  if (!el) return;
  var data;
  try { data = JSON.parse(el.textContent); } catch (e) { return; }
  var form = Array.prototype.find.call(document.forms, function (f) {
    return new URL(f.action, location.href).pathname === data.action && (f.method || '').toLowerCase() === 'post';
  });
  if (!form || form.id === 'blocks-form') return;
  var post = data.post || {};
  Array.prototype.forEach.call(form.elements, function (f) {
    if (!f.name || f.type === 'password' || f.type === 'file' || f.type === 'hidden' || f.type === 'submit' || f.name === '_csrf') return;
    var key = f.name.replace(/\[\]$/, '');
    var m = f.name.match(/^([^\[]+)\[([^\]]+)\]$/);
    var v = m ? (post[m[1]] || {})[m[2]] : post[key];
    if (f.type === 'checkbox' || f.type === 'radio') {
      f.checked = Array.isArray(v) ? v.indexOf(f.value) !== -1 : (v !== undefined && String(v) === f.value);
    } else if (v !== undefined && !Array.isArray(v) && typeof v !== 'object') {
      f.value = v;
    }
  });
});

/* «Guíame»: recorre la pantalla paso a paso resaltando cada campo o botón (pasos de app/tours.php; si la pantalla no
   tiene guía escrita, se arma sola con sus secciones, campos y botones). «Descargar PDF» del manual = imprimir. */
(function () {
  'use strict';
  const tr = s => (window.UW_I18N || {})[s] || s;
  const visible = el => !!el && el.getClientRects().length > 0 && getComputedStyle(el).visibility !== 'hidden';
  const clean = s => (s || '').replace(/\s+/g, ' ').trim();

  function resolve(sel) {
    if (!sel) return null;
    const card = sel.startsWith('card:');
    let el = null;
    try { el = Array.from(document.querySelectorAll(card ? sel.slice(5) : sel)).find(visible) || null; } catch (e) { return undefined; }
    if (el && card) el = el.closest('.card') || el;
    return el || undefined; // undefined = no está en esta pantalla: el paso se salta
  }

  // Pantalla sin guía escrita: título, cada tarjeta con sus campos y el botón principal
  function autoSteps() {
    const steps = [];
    const main = document.querySelector('.main');
    if (!main) return steps;
    const h1 = main.querySelector('h1');
    if (h1) steps.push([null, clean(h1.textContent), tr('Te enseño las partes de esta pantalla una a una.')]);
    main.querySelectorAll('.card').forEach(card => {
      if (!visible(card)) return;
      const h2 = card.querySelector('h2');
      const fields = Array.from(card.querySelectorAll('label')).filter(visible);
      if (h2) steps.push([card, clean(h2.textContent), fields.length
        ? tr('En esta sección:') + ' ' + fields.map(l => clean((l.childNodes[0] || {}).textContent || l.textContent)).filter(Boolean).slice(0, 6).join(', ') + '.'
        : clean((card.querySelector('p') || {}).textContent).slice(0, 220)]);
      fields.forEach(l => {
        const name = clean((l.childNodes[0] || {}).textContent) || clean(l.textContent).slice(0, 60);
        const hint = clean(Array.from(l.querySelectorAll('small')).map(s => s.textContent).join(' '));
        const input = l.querySelector('input,select,textarea');
        const ph = input && input.placeholder ? tr('Ejemplo:') + ' ' + input.placeholder : '';
        if (name) steps.push([l, name, hint || ph || tr('Rellena este campo si lo necesitas.')]);
      });
    });
    main.querySelectorAll('.table-wrap, .msg-list, .media-grid').forEach(list => {
      if (visible(list) && !list.closest('.card')) steps.push([list, tr('Lista'), tr('Cada fila es un elemento. Los botones de la derecha actúan sobre esa fila.')]);
    });
    const save = Array.from(main.querySelectorAll('.btn-primary')).filter(visible).pop();
    if (save) steps.push([save, clean(save.textContent), tr('Guarda o aplica lo de esta pantalla.')]);
    return steps.slice(0, 30);
  }

  let state = null;

  function close() {
    if (!state) return;
    state.hole.remove(); state.pop.remove();
    window.removeEventListener('resize', place); window.removeEventListener('scroll', place, true);
    document.removeEventListener('keydown', onKey);
    const btn = document.getElementById('tour-start'); if (btn) btn.focus();
    state = null;
  }

  function onKey(e) {
    if (e.key === 'Escape') close();
    else if (e.key === 'ArrowRight') go(state.i + 1);
    else if (e.key === 'ArrowLeft') go(state.i - 1);
  }

  function place() {
    if (!state) return;
    const { hole, pop, el } = state;
    const vw = document.documentElement.clientWidth, vh = window.innerHeight, m = 12;
    if (!el) {
      hole.style.cssText = 'left:50%;top:50%;width:0;height:0';
      pop.style.left = Math.max(m, (vw - pop.offsetWidth) / 2) + 'px';
      pop.style.top = Math.max(m, (vh - pop.offsetHeight) / 2) + 'px';
      return;
    }
    const r = el.getBoundingClientRect(), pad = 6;
    hole.style.cssText = `left:${r.left - pad}px;top:${r.top - pad}px;width:${r.width + pad * 2}px;height:${r.height + pad * 2}px`;
    const ph = pop.offsetHeight, pw = pop.offsetWidth;
    let top = r.bottom + pad + m;
    if (top + ph > vh - m) top = r.top - pad - m - ph;          // no cabe debajo: encima
    if (top < m) top = Math.min(vh - ph - m, Math.max(m, r.top + m)); // elemento enorme: dentro
    pop.style.top = top + 'px';
    pop.style.left = Math.min(vw - pw - m, Math.max(m, r.left)) + 'px';
  }

  function go(i) {
    if (!state || i === state.i) return;
    const steps = state.steps;
    const dir = i < state.i ? -1 : 1;
    if (i < 0) return;
    if (i >= steps.length) return close();
    const [sel, title, text] = steps[i];
    const el = typeof sel === 'string' ? resolve(sel) : (sel || null);
    if (el === undefined) { // no está en esta pantalla: se salta en la misma dirección
      if (dir < 0 && !steps.slice(0, i).some(s => typeof s[0] !== 'string' || resolve(s[0]) !== undefined)) return;
      return go(i + dir);
    }
    state.i = i; state.el = el;
    const { pop } = state;
    pop.querySelector('.tour-count').textContent = (i + 1) + ' / ' + steps.length;
    pop.querySelector('.tour-title').textContent = title;
    pop.querySelector('.tour-text').textContent = text;
    pop.querySelector('.tour-prev').disabled = i === 0;
    pop.querySelector('.tour-next').textContent = i === steps.length - 1 ? tr('Terminar') : tr('Siguiente') + ' →';
    if (el) {
      const d = el.closest('details'); if (d && !d.open && el.tagName !== 'SUMMARY') d.open = true;
      const r = el.getBoundingClientRect();
      if (r.top < 70 || r.bottom > window.innerHeight - 200) el.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
    }
    requestAnimationFrame(() => { place(); setTimeout(place, 350); });
    pop.querySelector('.tour-next').focus({ preventScroll: true });
  }

  function start() {
    let data = { steps: [] };
    try { data = JSON.parse(document.getElementById('uw-tour').textContent); } catch (e) { /* sin guía */ }
    // Solo los pasos que existen en esta pantalla (según permisos, idiomas, contenido…), para que el contador cuadre
    const steps = (data.steps && data.steps.length ? data.steps : autoSteps()).filter(s => typeof s[0] !== 'string' || resolve(s[0]) !== undefined);
    if (!steps.length) return;
    close();
    const hole = document.createElement('div'); hole.className = 'tour-hole';
    const pop = document.createElement('div');
    pop.className = 'tour-pop'; pop.setAttribute('role', 'dialog'); pop.setAttribute('aria-live', 'polite');
    pop.innerHTML = '<div class="tour-head"><span class="tour-count"></span><button type="button" class="tour-x" aria-label=""></button></div>'
      + '<h3 class="tour-title"></h3><p class="tour-text"></p>'
      + '<div class="tour-nav"><button type="button" class="btn btn-sm tour-prev"></button><button type="button" class="btn btn-sm btn-primary tour-next"></button></div>';
    pop.querySelector('.tour-x').textContent = '×';
    pop.querySelector('.tour-x').setAttribute('aria-label', tr('Cerrar'));
    pop.querySelector('.tour-prev').textContent = '← ' + tr('Anterior');
    pop.querySelector('.tour-x').addEventListener('click', close);
    pop.querySelector('.tour-prev').addEventListener('click', () => go(state.i - 1));
    pop.querySelector('.tour-next').addEventListener('click', () => go(state.i + 1));
    hole.addEventListener('click', close);
    document.body.append(hole, pop);
    state = { steps, i: -1, hole, pop, el: null };
    window.addEventListener('resize', place); window.addEventListener('scroll', place, true);
    document.addEventListener('keydown', onKey);
    go(0);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const b = document.getElementById('tour-start');
    if (b) b.addEventListener('click', start);
    const pdf = document.getElementById('manual-pdf');
    if (pdf) pdf.addEventListener('click', () => window.print());
  });
})();
