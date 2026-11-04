/**
 * Utilitários comuns das telas (painel e vitrine): chamada à API, formatação,
 * avisos (toast) e diálogo de confirmação — no lugar de alert()/confirm(),
 * que travam a página e não seguem o visual do sistema.
 */
(function () {
  'use strict';

  var base = ((document.querySelector('meta[name="base-url"]') || {}).content || '').replace(/\/$/, '');

  function api(path, opts) {
    opts = opts || {};
    var init = { method: opts.method || 'GET', headers: { Accept: 'application/json' }, credentials: 'same-origin' };
    if (opts.body !== undefined) {
      init.headers['Content-Type'] = 'application/json';
      init.body = typeof opts.body === 'string' ? opts.body : JSON.stringify(opts.body);
    }
    var url = /^https?:/.test(path) ? path : base + '/' + String(path).replace(/^\//, '');
    return fetch(url, init).then(function (r) {
      return r.text().then(function (t) {
        var j;
        try { j = t ? JSON.parse(t) : {}; } catch (e) { j = { error: 'Resposta inesperada do servidor.' }; }
        if (!r.ok && !j.error) j.error = 'Erro ' + r.status;
        j._status = r.status;
        return j;
      });
    }, function () {
      return { error: 'Sem conexão com o servidor.', _status: 0 };
    });
  }

  function money(v) {
    var n = Number(v) || 0;
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  /** "2026-11-04 15:42:43" → Date local (o servidor grava no horário local). */
  function parseDate(s) {
    if (!s) return null;
    var m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
    if (!m) return null;
    return new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], +(m[6] || 0));
  }

  function dateTime(s) {
    var d = parseDate(s);
    if (!d) return s || '';
    return d.toLocaleDateString('pt-BR') + ' ' + d.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  }

  var ICONS = {
    check: '<path d="M20 6L9 17l-5-5"/>',
    alert: '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/>',
    info: '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
    x: '<path d="M18 6L6 18M6 6l12 12"/>',
    clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
    percent: '<path d="M19 5L5 19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>'
  };
  function icon(name, size) {
    size = size || 18;
    return '<svg class="ui-icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || ICONS.info) + '</svg>';
  }

  function toastHost() {
    var h = document.getElementById('ui-toasts');
    if (!h) {
      h = document.createElement('div');
      h.id = 'ui-toasts';
      h.className = 'ui-toasts';
      h.setAttribute('role', 'status');
      h.setAttribute('aria-live', 'polite');
      document.body.appendChild(h);
    }
    return h;
  }

  /** type: ok | error | info */
  function toast(msg, type, ms) {
    var t = document.createElement('div');
    t.className = 'ui-toast ui-toast--' + (type || 'info');
    t.innerHTML = icon(type === 'ok' ? 'check' : type === 'error' ? 'alert' : 'info', 18) + '<span>' + esc(msg) + '</span>';
    toastHost().appendChild(t);
    requestAnimationFrame(function () { t.classList.add('is-in'); });
    setTimeout(function () {
      t.classList.remove('is-in');
      setTimeout(function () { t.remove(); }, 250);
    }, ms || 3800);
  }

  /**
   * Diálogo modal. Devolve Promise<boolean>.
   * opts: {title, message, confirm, cancel, danger, html}
   */
  function confirmDialog(opts) {
    opts = opts || {};
    return new Promise(function (resolve) {
      var wrap = document.createElement('div');
      wrap.className = 'ui-dialog-backdrop';
      wrap.innerHTML =
        '<div class="ui-dialog" role="dialog" aria-modal="true" aria-labelledby="ui-dialog-title">' +
          '<h2 id="ui-dialog-title" class="ui-dialog-title">' + esc(opts.title || 'Confirmar') + '</h2>' +
          (opts.html ? '<div class="ui-dialog-body">' + opts.html + '</div>' : '<p class="ui-dialog-body">' + esc(opts.message || '') + '</p>') +
          '<div class="ui-dialog-actions">' +
            (opts.cancel === false ? '' : '<button type="button" class="btn btn-secondary" data-r="0">' + esc(opts.cancel || 'Cancelar') + '</button>') +
            '<button type="button" class="btn ' + (opts.danger ? 'btn-danger' : 'btn-primary') + '" data-r="1">' + esc(opts.confirm || 'Confirmar') + '</button>' +
          '</div>' +
        '</div>';
      function close(v) {
        var values = {};
        wrap.querySelectorAll('input[id], select[id], textarea[id]').forEach(function (el) { values[el.id] = el.value; });
        document.removeEventListener('keydown', onKey);
        wrap.remove();
        resolve(v && opts.returnValues ? values : v);
      }
      function onKey(e) {
        if (e.key === 'Escape') close(false);
        if (e.key === 'Enter' && e.target && e.target.tagName === 'INPUT') { e.preventDefault(); close(true); }
      }
      wrap.addEventListener('click', function (e) {
        if (e.target === wrap) return close(false);
        var b = e.target.closest('[data-r]');
        if (b) close(b.getAttribute('data-r') === '1');
      });
      document.addEventListener('keydown', onKey);
      document.body.appendChild(wrap);
      var focus = wrap.querySelector('input, select, textarea') || wrap.querySelector('[data-r="1"]');
      if (focus) focus.focus();
    });
  }

  window.UI = { api: api, money: money, esc: esc, parseDate: parseDate, dateTime: dateTime, toast: toast, confirm: confirmDialog, icon: icon, base: base };
})();
