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
    div.textContent = text;
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
        if (data.detalhe_ia) {
          reply += '\n\n— ' + String(data.detalhe_ia);
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
