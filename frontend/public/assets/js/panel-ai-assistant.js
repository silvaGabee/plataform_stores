(function () {
  var main = document.querySelector('.panel-main[data-store-slug]');
  if (!main) return;
  var slug = (main.getAttribute('data-store-slug') || '').trim();
  if (!slug) return;

  var base = (document.querySelector('meta[name="base-url"]') || {}).getAttribute('content') || '';
  base = base.replace(/\/$/, '');

  var modal = document.getElementById('panel-ai-modal');
  var dialog = document.getElementById('panel-ai-dialog');
  var fab = document.getElementById('panel-ai-fab');
  var closeBtn = document.getElementById('panel-ai-close');
  var backdrop = modal && modal.querySelector('.panel-ai-backdrop');
  var form = document.getElementById('panel-ai-form');
  var input = document.getElementById('panel-ai-input');
  var messagesEl = document.getElementById('panel-ai-messages');
  var sendBtn = document.getElementById('panel-ai-send');
  if (!modal || !fab || !form || !input || !messagesEl || !dialog) return;

  var lastFocus = null;
  var closeFallbackTimer = null;

  function restoreLastFocus() {
    if (lastFocus && typeof lastFocus.focus === 'function') lastFocus.focus();
  }

  function removeEmptyState() {
    var empty = document.getElementById('panel-ai-empty');
    if (empty && empty.parentNode) empty.parentNode.removeChild(empty);
  }

  function removeTypingIndicator() {
    var t = messagesEl.querySelector('.panel-ai-typing');
    if (t) t.remove();
  }

  function showTypingIndicator() {
    removeEmptyState();
    removeTypingIndicator();
    var row = document.createElement('div');
    row.className = 'panel-ai-typing';
    row.setAttribute('role', 'status');
    row.setAttribute('aria-live', 'polite');
    row.setAttribute('aria-label', 'Assistente está preparando a resposta');

    var av = document.createElement('div');
    av.className = 'panel-ai-typing-avatar';
    av.setAttribute('aria-hidden', 'true');
    av.innerHTML =
      '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
      '<path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round"/>' +
      '</svg>';

    var bubble = document.createElement('div');
    bubble.className = 'panel-ai-typing-bubble';

    var dots = document.createElement('span');
    dots.className = 'panel-ai-typing-dots';
    dots.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < 3; i++) {
      var dot = document.createElement('span');
      dot.className = 'panel-ai-typing-dot';
      dots.appendChild(dot);
    }
    bubble.appendChild(dots);

    var col = document.createElement('div');
    col.className = 'panel-ai-msg-col';
    var meta = document.createElement('span');
    meta.className = 'panel-ai-bubble-meta panel-ai-bubble-meta--typing';
    meta.textContent = 'A gerar resposta…';
    col.appendChild(meta);
    col.appendChild(bubble);
    row.appendChild(av);
    row.appendChild(col);
    messagesEl.appendChild(row);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function api(path, body) {
    var url = base + path;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.text().then(function (text) {
        var j = null;
        try {
          j = text ? JSON.parse(text) : null;
        } catch (e) {
          j = null;
        }
        if (!r.ok) {
          var err = j && j.error ? j.error : text || 'Erro HTTP ' + r.status;
          return Promise.reject(new Error(err));
        }
        return j || {};
      });
    });
  }

  /**
   * Formatação da resposta da IA (markdown simples) montada com nós do DOM —
   * nunca innerHTML, então nada do texto vira HTML de verdade. Antes a
   * resposta aparecia crua, com ** e | espalhados.
   *
   * Entende: **negrito**, *itálico*, `código`, títulos (#), listas com - * •
   * ou 1., tabelas com | e linha separadora, e --- (vira um espaço).
   */
  function inlineMd(parent, text) {
    var re = /(\*\*|__)(.+?)\1|`([^`]+)`|(^|[^*\w])\*([^*\s][^*]*?)\*(?!\*)/g;
    var last = 0;
    var m;
    while ((m = re.exec(text))) {
      var start = m.index;
      var lead = '';
      if (m[5] !== undefined) {
        lead = m[4] || '';
        start += lead.length;
      }
      if (start > last || lead) parent.appendChild(document.createTextNode(text.slice(last, m.index) + lead));
      var el;
      if (m[2] !== undefined) {
        el = document.createElement('strong');
        inlineMd(el, m[2]);
      } else if (m[3] !== undefined) {
        el = document.createElement('code');
        el.textContent = m[3];
      } else {
        el = document.createElement('em');
        el.textContent = m[5];
      }
      parent.appendChild(el);
      last = re.lastIndex;
    }
    // Sobras de marcação sem par (ex.: um ** solto) não aparecem.
    var resto = text.slice(last).replace(/\*\*|__/g, '');
    if (resto) parent.appendChild(document.createTextNode(resto));
  }

  function tableCells(line) {
    var s = line.trim().replace(/^\|/, '').replace(/\|$/, '');
    return s.split('|').map(function (c) { return c.trim(); });
  }

  function renderMarkdown(container, text) {
    var lines = String(text).replace(/\r\n?/g, '\n').split('\n');
    var i = 0;
    var para = [];
    function flushPara() {
      if (!para.length) return;
      var p = document.createElement('p');
      para.forEach(function (l, k) {
        if (k) p.appendChild(document.createElement('br'));
        inlineMd(p, l);
      });
      container.appendChild(p);
      para = [];
    }
    while (i < lines.length) {
      var line = lines[i];
      var t = line.trim();
      if (!t) { flushPara(); i++; continue; }
      if (/^(-{3,}|\*{3,}|_{3,})$/.test(t)) { flushPara(); i++; continue; }

      var h = t.match(/^(#{1,6})\s+(.*)$/);
      if (h) {
        flushPara();
        var hd = document.createElement('p');
        hd.className = 'panel-ai-md-heading';
        inlineMd(hd, h[2].replace(/\*\*/g, ''));
        container.appendChild(hd);
        i++;
        continue;
      }

      // Tabela: linha com | seguida de separador |---|---|.
      if (t.indexOf('|') !== -1 && i + 1 < lines.length && /^\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?$/.test(lines[i + 1].trim())) {
        flushPara();
        var head = tableCells(t);
        var wrap = document.createElement('div');
        wrap.className = 'panel-ai-md-table';
        var table = document.createElement('table');
        var thead = document.createElement('thead');
        var tr = document.createElement('tr');
        head.forEach(function (c) {
          var th = document.createElement('th');
          th.scope = 'col';
          inlineMd(th, c);
          tr.appendChild(th);
        });
        thead.appendChild(tr);
        table.appendChild(thead);
        var tbody = document.createElement('tbody');
        i += 2;
        while (i < lines.length && lines[i].trim().indexOf('|') !== -1) {
          var row = document.createElement('tr');
          var cells = tableCells(lines[i]);
          for (var c = 0; c < head.length; c++) {
            var td = document.createElement('td');
            inlineMd(td, cells[c] || '');
            row.appendChild(td);
          }
          tbody.appendChild(row);
          i++;
        }
        table.appendChild(tbody);
        wrap.appendChild(table);
        container.appendChild(wrap);
        continue;
      }

      var isUl = /^([-*•])\s+/.test(t);
      var isOl = /^\d+[.)]\s+/.test(t);
      if (isUl || isOl) {
        flushPara();
        var list = document.createElement(isOl ? 'ol' : 'ul');
        var olStart = isOl ? parseInt(t, 10) : 1;
        if (isOl && olStart > 1) list.start = olStart;
        var itemRe = isOl ? /^\d+[.)]\s+/ : /^[-*•]\s+/;
        var li = null;
        while (i < lines.length) {
          var lt = lines[i].trim();
          if (itemRe.test(lt)) {
            li = document.createElement('li');
            inlineMd(li, lt.replace(itemRe, ''));
            list.appendChild(li);
            i++;
          } else if (lt && li && /^\s{2,}/.test(lines[i]) && !/^([-*•]|\d+[.)])\s+/.test(lt)) {
            // Continuação recuada do item anterior.
            li.appendChild(document.createElement('br'));
            inlineMd(li, lt);
            i++;
          } else if (lt && li && /^\s{2,}/.test(lines[i])) {
            // Subitem: vira linha dentro do item, com marcador.
            li.appendChild(document.createElement('br'));
            inlineMd(li, '– ' + lt.replace(/^([-*•]|\d+[.)])\s+/, ''));
            i++;
          } else {
            break;
          }
        }
        container.appendChild(list);
        continue;
      }

      para.push(t);
      i++;
    }
    flushPara();
  }

  function appendBubble(role, text) {
    removeEmptyState();
    var row = document.createElement('div');
    row.className = 'panel-ai-msg panel-ai-msg--' + role;
    if (role === 'assistant') {
      var av = document.createElement('div');
      av.className = 'panel-ai-msg-avatar';
      av.setAttribute('aria-hidden', 'true');
      av.innerHTML =
        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
        '<path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" stroke="currentColor" stroke-width="1.35" stroke-linejoin="round"/>' +
        '</svg>';
      row.appendChild(av);
    }
    var col = document.createElement('div');
    col.className = 'panel-ai-msg-col';
    var meta = document.createElement('span');
    meta.className = 'panel-ai-bubble-meta';
    meta.textContent = role === 'user' ? 'Você' : 'Assistente';
    var div = document.createElement('div');
    div.className = 'panel-ai-bubble panel-ai-bubble--' + role;
    if (role === 'assistant') {
      div.classList.add('panel-ai-bubble--md');
      renderMarkdown(div, text);
    } else {
      div.textContent = text;
    }
    col.appendChild(meta);
    col.appendChild(div);
    row.appendChild(col);
    messagesEl.appendChild(row);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function setOpen(open) {
    if (open) {
      if (closeFallbackTimer) {
        clearTimeout(closeFallbackTimer);
        closeFallbackTimer = null;
      }
      lastFocus = document.activeElement;
      modal.hidden = false;
      modal.classList.remove('is-open');
      void modal.offsetWidth;
      requestAnimationFrame(function () {
        requestAnimationFrame(function () {
          modal.classList.add('is-open');
        });
      });
      document.body.classList.add('panel-ai-open');
      setTimeout(function () {
        if (!modal.hidden && modal.classList.contains('is-open')) input.focus();
      }, 320);
    } else {
      removeTypingIndicator();
      if (modal.hidden || !modal.classList.contains('is-open')) {
        modal.hidden = true;
        modal.classList.remove('is-open');
        document.body.classList.remove('panel-ai-open');
        restoreLastFocus();
        return;
      }
      var finished = false;
      function finishClose() {
        if (finished) return;
        finished = true;
        dialog.removeEventListener('transitionend', onDialogTransitionEnd);
        if (closeFallbackTimer) {
          clearTimeout(closeFallbackTimer);
          closeFallbackTimer = null;
        }
        modal.hidden = true;
        modal.classList.remove('is-open');
        document.body.classList.remove('panel-ai-open');
        restoreLastFocus();
      }
      function onDialogTransitionEnd(e) {
        if (e.target !== dialog) return;
        if (e.propertyName !== 'transform') return;
        finishClose();
      }
      dialog.addEventListener('transitionend', onDialogTransitionEnd);
      modal.classList.remove('is-open');
      closeFallbackTimer = setTimeout(finishClose, 420);
    }
  }

  input.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
      e.preventDefault();
      if (typeof form.requestSubmit === 'function') form.requestSubmit();
      else if (sendBtn) sendBtn.click();
    }
  });

  fab.addEventListener('click', function () {
    setOpen(true);
  });
  closeBtn.addEventListener('click', function () {
    setOpen(false);
  });
  if (backdrop) {
    backdrop.addEventListener('click', function () {
      setOpen(false);
    });
  }
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !modal.hidden && modal.classList.contains('is-open')) {
      e.preventDefault();
      setOpen(false);
    }
  });

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var q = (input.value || '').trim();
    if (!q) return;
    appendBubble('user', q);
    input.value = '';
    sendBtn.disabled = true;
    input.setAttribute('aria-busy', 'true');
    showTypingIndicator();
    var path = '/api/loja/' + encodeURIComponent(slug) + '/ai/chat';
    api(path, { pergunta: q })
      .then(function (data) {
        removeTypingIndicator();
        var reply = data.resposta != null ? String(data.resposta) : 'Assistente temporariamente indisponível';
        // Detalhe técnico (só com APP_DEBUG): vai para o console, não para
        // a conversa — antes aparecia o nome dos provedores e o erro HTTP.
        if (data.detalhe_ia && window.console) {
          console.warn('[assistente IA]', String(data.detalhe_ia));
        }
        appendBubble('assistant', reply);
      })
      .catch(function () {
        removeTypingIndicator();
        appendBubble('assistant', 'Assistente temporariamente indisponível');
      })
      .finally(function () {
        sendBtn.disabled = false;
        input.removeAttribute('aria-busy');
        input.focus();
      });
  });
})();
