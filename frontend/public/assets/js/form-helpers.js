/**
 * Formulários de entrada e cadastro (página da plataforma e entrada da loja).
 *
 * - Botão "Mostrar senha" em cada campo dentro de .ui-password.
 * - Validação com mensagem embaixo do campo, em formulários com
 *   [data-validate]. Antes o erro vinha no balão do navegador ("Aumente esse
 *   texto para 8 caracteres..."), que some sozinho e não segue o visual do site.
 *   O servidor continua validando tudo de novo.
 */
(function () {
  'use strict';

  var EYE = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
  var EYE_OFF = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19M14.12 14.12a3 3 0 1 1-4.24-4.24"/><path d="M1 1l22 22"/>';
  function svg(paths) {
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + paths + '</svg>';
  }

  // ------------------------------------------------------------ mostrar senha
  document.querySelectorAll('.ui-password').forEach(function (wrap) {
    var input = wrap.querySelector('input');
    if (!input || wrap.querySelector('.ui-password-toggle')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'ui-password-toggle';
    btn.setAttribute('aria-label', 'Mostrar senha');
    btn.setAttribute('aria-pressed', 'false');
    btn.innerHTML = svg(EYE);
    btn.addEventListener('click', function () {
      var mostrar = input.type === 'password';
      input.type = mostrar ? 'text' : 'password';
      btn.setAttribute('aria-pressed', mostrar ? 'true' : 'false');
      btn.setAttribute('aria-label', mostrar ? 'Esconder senha' : 'Mostrar senha');
      btn.innerHTML = svg(mostrar ? EYE_OFF : EYE);
    });
    wrap.appendChild(btn);
  });

  // ------------------------------------------------------------ validação
  function mensagem(input) {
    var v = input.validity;
    var rotulo = input.labels && input.labels[0] ? input.labels[0].textContent.trim().toLowerCase() : 'este campo';
    if (v.valueMissing) {
      if (input.type === 'password') return 'Digite sua senha.';
      if (input.type === 'email') return 'Digite seu e-mail.';
      return 'Preencha o campo ' + rotulo + '.';
    }
    if (v.typeMismatch && input.type === 'email') return 'Digite um e-mail válido, como nome@exemplo.com.';
    if (v.tooShort) return 'Use pelo menos ' + input.minLength + ' caracteres (você digitou ' + input.value.length + ').';
    if (v.tooLong) return 'Use no máximo ' + input.maxLength + ' caracteres.';
    return input.validationMessage;
  }

  function campo(input) {
    return input.closest('.ui-form-field') || input.parentElement;
  }

  function mostrarErro(input, texto) {
    var box = campo(input);
    var id = (input.id || input.name) + '-erro';
    var p = document.getElementById(id);
    if (!p) {
      p = document.createElement('p');
      p.id = id;
      p.className = 'ui-field-error';
      box.appendChild(p);
    }
    p.textContent = texto;
    input.setAttribute('aria-invalid', 'true');
    var desc = (input.getAttribute('aria-describedby') || '').split(' ').filter(Boolean);
    if (desc.indexOf(id) === -1) desc.push(id);
    input.setAttribute('aria-describedby', desc.join(' '));
  }

  function limparErro(input) {
    var id = (input.id || input.name) + '-erro';
    var p = document.getElementById(id);
    if (p) p.remove();
    input.removeAttribute('aria-invalid');
    var desc = (input.getAttribute('aria-describedby') || '').split(' ').filter(function (d) { return d && d !== id; });
    if (desc.length) input.setAttribute('aria-describedby', desc.join(' '));
    else input.removeAttribute('aria-describedby');
  }

  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    form.setAttribute('novalidate', '');
    var campos = form.querySelectorAll('input:not([type="hidden"]), textarea, select');
    campos.forEach(function (input) {
      input.addEventListener('input', function () {
        if (input.getAttribute('aria-invalid') === 'true' && input.checkValidity()) limparErro(input);
      });
      input.addEventListener('blur', function () {
        // Só reclama ao sair do campo se a pessoa já digitou algo.
        if (input.value !== '' && !input.checkValidity()) mostrarErro(input, mensagem(input));
      });
    });
    form.addEventListener('submit', function (e) {
      var primeiro = null;
      campos.forEach(function (input) {
        if (input.checkValidity()) {
          limparErro(input);
        } else {
          mostrarErro(input, mensagem(input));
          if (!primeiro) primeiro = input;
        }
      });
      if (primeiro) {
        e.preventDefault();
        primeiro.focus();
        return;
      }
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
      }
    });
  });

  // Voltar pelo navegador restaura a página da memória com o botão travado.
  window.addEventListener('pageshow', function () {
    document.querySelectorAll('form[data-validate] button[type="submit"][aria-busy]').forEach(function (b) {
      b.disabled = false;
      b.removeAttribute('aria-busy');
    });
  });
})();
