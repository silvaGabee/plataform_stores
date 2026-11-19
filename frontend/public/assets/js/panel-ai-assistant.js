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
  var clearBtn = document.getElementById('panel-ai-clear');
  var backdrop = modal && modal.querySelector('.panel-ai-backdrop');
  var form = document.getElementById('panel-ai-form');
  var input = document.getElementById('panel-ai-input');
  var messagesEl = document.getElementById('panel-ai-messages');
  var sendBtn = document.getElementById('panel-ai-send');
  var countEl = document.getElementById('panel-ai-count');
  if (!modal || !fab || !form || !input || !messagesEl || !dialog || !sendBtn) return;

  var MAX = parseInt(input.getAttribute('maxlength'), 10) || 2000;
  var MSG_INDISPONIVEL = 'Assistente temporariamente indisponível';
  var emptyTemplate = document.getElementById('panel-ai-empty');
  emptyTemplate = emptyTemplate ? emptyTemplate.cloneNode(true) : null;

  var lastFocus = null;
  var closeTimer = null;
  var busy = false;

  /*
   * Conversa temporária: fica no sessionStorage, então sobrevive a recarregar
   * a página e a seguir um link do chat (ex.: abrir Configurações e voltar ao
   * assistente para ler o passo a passo), e some ao fechar a aba. A chave
   * leva o dono (usuário + loja), para outra pessoa na mesma aba não ver.
   */
  var MAX_SALVAS = 40;
  var storageKey = 'panel-ai-chat:' + (modal.getAttribute('data-chat-owner') || slug);
  var historico = [];

  function salvarHistorico() {
    try {
      if (historico.length) {
        sessionStorage.setItem(storageKey, JSON.stringify(historico.slice(-MAX_SALVAS)));
      } else {
        sessionStorage.removeItem(storageKey);
      }
    } catch (e) { /* armazenamento bloqueado ou cheio: segue sem salvar */ }
  }

  function lerHistorico() {
    try {
      var bruto = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
      if (!Array.isArray(bruto)) return [];
      return bruto.filter(function (m) {
        return m && (m.role === 'user' || m.role === 'assistant') && typeof m.text === 'string';
      });
    } catch (e) {
      return [];
    }
  }

  var ICON_MARK =
    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
    '<path d="M12 3L13.5 8L18 9L13.5 10L12 15L10.5 10L6 9L10.5 8L12 3Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>';
  var ICON_COPY =
    '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">' +
    '<rect x="9" y="9" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.8"/>' +
    '<path d="M5 15V5a2 2 0 0 1 2-2h10" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>';

  /* ---------- Campo de pergunta ---------- */

  function autoGrow() {
    input.style.height = 'auto';
    input.style.height = Math.min(input.scrollHeight, 144) + 'px';
  }

  function updateComposer() {
    var len = input.value.length;
    sendBtn.disabled = busy || input.value.trim() === '';
    if (countEl) {
      // O contador só aparece perto do limite, para não poluir o campo.
      var perto = len >= MAX * 0.8;
      countEl.hidden = !perto;
      countEl.textContent = len.toLocaleString('pt-BR') + ' / ' + MAX.toLocaleString('pt-BR');
      countEl.classList.toggle('is-limit', len >= MAX);
    }
    autoGrow();
  }

  input.addEventListener('input', updateComposer);
  input.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter' || e.isComposing) return;
    // Enter envia; Shift+Enter quebra a linha (Ctrl/Cmd+Enter também envia).
    if (e.shiftKey && !(e.ctrlKey || e.metaKey)) return;
    e.preventDefault();
    if (!sendBtn.disabled) send();
  });

  /* ---------- Mensagens ---------- */

  function removeEmptyState() {
    var empty = document.getElementById('panel-ai-empty');
    if (empty) empty.remove();
  }

  function restoreEmptyState() {
    historico = [];
    salvarHistorico();
    messagesEl.textContent = '';
    if (emptyTemplate) {
      var node = emptyTemplate.cloneNode(true);
      messagesEl.appendChild(node);
      bindChips(node);
    }
    if (clearBtn) clearBtn.hidden = true;
  }

  function scrollToEnd() {
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function srLabel(text) {
    var s = document.createElement('span');
    s.className = 'visually-hidden';
    s.textContent = text;
    return s;
  }

  function avatar() {
    var av = document.createElement('div');
    av.className = 'panel-ai-msg-avatar';
    av.setAttribute('aria-hidden', 'true');
    av.innerHTML = ICON_MARK;
    return av;
  }

  function copyText(text, btn) {
    var done = function () {
      var label = btn.querySelector('.panel-ai-action-label');
      if (!label) return;
      label.textContent = 'Copiado';
      setTimeout(function () { label.textContent = 'Copiar'; }, 1600);
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(done, function () {});
      return;
    }
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); done(); } catch (err) { /* sem cópia */ }
    ta.remove();
  }

  function appendMessage(role, text, opts) {
    opts = opts || {};
    removeEmptyState();
    if (!opts.restaurando) {
      historico.push({ role: role, text: text, erro: !!opts.erro });
      salvarHistorico();
    }
    var row = document.createElement('div');
    row.className = 'panel-ai-msg panel-ai-msg--' + role + (opts.erro ? ' panel-ai-msg--erro' : '');
    if (role === 'assistant') row.appendChild(avatar());

    var col = document.createElement('div');
    col.className = 'panel-ai-msg-col';
    var bubble = document.createElement('div');
    bubble.className = 'panel-ai-bubble panel-ai-bubble--' + role;
    bubble.appendChild(srLabel(role === 'user' ? 'Você: ' : 'Assistente: '));
    if (role === 'assistant') {
      var md = document.createElement('div');
      md.className = 'panel-ai-bubble--md';
      renderMarkdown(md, text);
      bubble.appendChild(md);
    } else {
      bubble.appendChild(document.createTextNode(text));
    }
    col.appendChild(bubble);

    if (role === 'assistant' && !opts.erro) {
      var actions = document.createElement('div');
      actions.className = 'panel-ai-msg-actions';
      var copy = document.createElement('button');
      copy.type = 'button';
      copy.className = 'panel-ai-action';
      copy.innerHTML = ICON_COPY + '<span class="panel-ai-action-label">Copiar</span>';
      copy.setAttribute('aria-label', 'Copiar resposta');
      copy.addEventListener('click', function () { copyText(text, copy); });
      actions.appendChild(copy);
      col.appendChild(actions);
    }

    row.appendChild(col);
    messagesEl.appendChild(row);
    if (clearBtn) clearBtn.hidden = false;
    scrollToEnd();
  }

  function showTyping() {
    removeTyping();
    removeEmptyState();
    var row = document.createElement('div');
    row.className = 'panel-ai-msg panel-ai-msg--assistant panel-ai-typing';
    row.setAttribute('role', 'status');
    row.appendChild(avatar());
    var col = document.createElement('div');
    col.className = 'panel-ai-msg-col';
    var dots = document.createElement('span');
    dots.className = 'panel-ai-typing-dots';
    dots.setAttribute('aria-hidden', 'true');
    for (var i = 0; i < 3; i++) {
      var d = document.createElement('span');
      d.className = 'panel-ai-typing-dot';
      dots.appendChild(d);
    }
    col.appendChild(dots);
    col.appendChild(srLabel('O assistente está escrevendo a resposta.'));
    row.appendChild(col);
    messagesEl.appendChild(row);
    scrollToEnd();
  }

  function removeTyping() {
    var t = messagesEl.querySelector('.panel-ai-typing');
    if (t) t.remove();
  }

  /**
   * Formatação da resposta da IA (markdown simples) montada com nós do DOM —
   * nunca innerHTML, então nada do texto vira HTML de verdade.
   *
   * Entende: **negrito**, *itálico*, `código`, títulos (#), listas com - * •
   * ou 1., tabelas com | e linha separadora, e --- (vira um espaço).
   */
  /**
   * Link só vira clicável se apontar para o painel desta loja ou para a
   * vitrine dela, no mesmo site. O servidor já filtra; aqui é a segunda trava.
   */
  function internalHref(raw) {
    try {
      var u = new URL(raw, window.location.href);
      if (u.origin !== window.location.origin) return null;
      var basePath = base ? new URL(base, window.location.href).pathname.replace(/\/$/, '') : '';
      var s = encodeURIComponent(slug);
      var ok = [basePath + '/painel/' + s, basePath + '/loja/' + s];
      var p = u.pathname.replace(/\/$/, '');
      for (var k = 0; k < ok.length; k++) {
        if (p === ok[k] || p.indexOf(ok[k] + '/') === 0) return u.href;
      }
    } catch (e) { /* endereço inválido */ }
    return null;
  }

  function inlineMd(parent, text) {
    var link = /\[([^\]]+)\]\(([^)\s]+)\)/g;
    var lm;
    var pos = 0;
    var achou = false;
    while ((lm = link.exec(text))) {
      achou = true;
      if (lm.index > pos) inlineMd(parent, text.slice(pos, lm.index));
      var href = internalHref(lm[2]);
      if (href) {
        var a = document.createElement('a');
        a.href = href;
        a.className = 'panel-ai-link';
        inlineMd(a, lm[1]);
        parent.appendChild(a);
      } else {
        inlineMd(parent, lm[1]);
      }
      pos = link.lastIndex;
    }
    if (achou) {
      if (pos < text.length) inlineMd(parent, text.slice(pos));
      return;
    }
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
    // "R$ 579,70" não quebra entre o símbolo e o número.
    var lines = String(text).replace(/\r\n?/g, '\n').replace(/R\$ (?=\d)/g, 'R$ ').split('\n');
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

  /* ---------- Envio ---------- */

  function api(path, body) {
    return fetch(base + path, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(body),
    }).then(function (r) {
      return r.text().then(function (text) {
        var j = null;
        try { j = text ? JSON.parse(text) : null; } catch (e) { j = null; }
        if (!r.ok) {
          // Só a mensagem que o servidor escreveu para a pessoa (limite,
          // pergunta longa); HTML de erro ou texto técnico não vai ao chat.
          var err = new Error(j && j.error ? String(j.error) : '');
          err.paraPessoa = !!(j && j.error);
          return Promise.reject(err);
        }
        return j || {};
      });
    });
  }

  function setBusy(on) {
    busy = on;
    sendBtn.classList.toggle('is-busy', on);
    sendBtn.setAttribute('aria-label', on ? 'Aguardando resposta' : 'Enviar pergunta');
    input.setAttribute('aria-busy', on ? 'true' : 'false');
    updateComposer();
  }

  function send(texto) {
    var q = (texto != null ? texto : input.value).trim();
    if (!q || busy) return;
    if (q.length > MAX) q = q.slice(0, MAX);
    appendMessage('user', q);
    input.value = '';
    setBusy(true);
    showTyping();
    api('/api/loja/' + encodeURIComponent(slug) + '/ai/chat', { pergunta: q })
      .then(function (data) {
        removeTyping();
        var reply = data.resposta != null ? String(data.resposta) : MSG_INDISPONIVEL;
        // Detalhe técnico (só com APP_DEBUG) vai para o console, não para a conversa.
        if (data.detalhe_ia && window.console) console.warn('[assistente IA]', String(data.detalhe_ia));
        var erro = reply === MSG_INDISPONIVEL || /^Muitas perguntas/.test(reply);
        appendMessage('assistant', erro ? reply + '. Tente de novo em instantes.' : reply, { erro: erro });
      })
      .catch(function (err) {
        removeTyping();
        var msg = err && err.paraPessoa && err.message ? err.message : MSG_INDISPONIVEL + '. Tente de novo em instantes.';
        appendMessage('assistant', msg, { erro: true });
      })
      .finally(function () {
        setBusy(false);
        input.focus();
      });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    send();
  });

  function bindChips(scope) {
    scope.querySelectorAll('.panel-ai-chip').forEach(function (chip) {
      chip.addEventListener('click', function () {
        send(chip.getAttribute('data-pergunta') || chip.textContent);
      });
    });
  }
  bindChips(messagesEl);

  if (clearBtn) {
    clearBtn.addEventListener('click', function () {
      if (busy) return;
      restoreEmptyState();
      input.focus();
    });
  }

  /* ---------- Abrir, fechar e foco ---------- */

  function focusables() {
    return Array.prototype.filter.call(
      dialog.querySelectorAll('button, textarea, [href], [tabindex]:not([tabindex="-1"])'),
      function (el) { return !el.disabled && !el.hidden && el.offsetParent !== null; }
    );
  }

  // Tab fica dentro da janela enquanto ela está aberta.
  dialog.addEventListener('keydown', function (e) {
    if (e.key !== 'Tab') return;
    var els = focusables();
    if (!els.length) return;
    var first = els[0];
    var last = els[els.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });

  function isOpen() {
    return !modal.hidden && modal.classList.contains('is-open');
  }

  function open() {
    if (closeTimer) { clearTimeout(closeTimer); closeTimer = null; }
    lastFocus = document.activeElement;
    modal.hidden = false;
    void modal.offsetWidth;
    modal.classList.add('is-open');
    document.body.classList.add('panel-ai-open');
    fab.setAttribute('aria-expanded', 'true');
    updateComposer();
    // Com a janela escondida a rolagem não acontece; a conversa restaurada
    // abre na última mensagem.
    scrollToEnd();
    setTimeout(function () { if (isOpen()) input.focus(); }, 60);
  }

  function close() {
    if (modal.hidden) return;
    modal.classList.remove('is-open');
    document.body.classList.remove('panel-ai-open');
    fab.setAttribute('aria-expanded', 'false');
    closeTimer = setTimeout(function () {
      modal.hidden = true;
      closeTimer = null;
    }, 320);
    var back = lastFocus && typeof lastFocus.focus === 'function' && lastFocus !== document.body ? lastFocus : fab;
    back.focus();
  }

  // Conversa salva nesta aba volta ao carregar a página.
  historico = lerHistorico();
  historico.forEach(function (m) {
    appendMessage(m.role, m.text, { erro: m.erro, restaurando: true });
  });

  fab.setAttribute('aria-expanded', 'false');
  fab.addEventListener('click', open);
  if (closeBtn) closeBtn.addEventListener('click', close);
  if (backdrop) backdrop.addEventListener('click', close);
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && isOpen()) {
      e.preventDefault();
      close();
    }
  });

  updateComposer();
})();
