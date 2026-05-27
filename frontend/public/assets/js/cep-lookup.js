/**
 * Busca CEP via ViaCEP e preenche endereço automaticamente.
 * Uso: CepLookup.bind({ zipcode: '#id', street: '#id', ... });
 */
(function (global) {
  'use strict';

  function $(selector) {
    if (!selector) return null;
    return typeof selector === 'string' ? document.querySelector(selector) : selector;
  }

  function digitsOnly(value) {
    return String(value || '').replace(/\D/g, '');
  }

  function formatCepInput(el) {
    if (!el) return;
    var digits = digitsOnly(el.value).slice(0, 8);
    el.value = digits.length > 5 ? digits.slice(0, 5) + '-' + digits.slice(5) : digits;
  }

  function bind(options) {
    var zipEl = $(options.zipcode);
    if (!zipEl) return;

    var streetEl = $(options.street);
    var neighborhoodEl = $(options.neighborhood);
    var cityEl = $(options.city);
    var stateEl = $(options.state);
    var numberEl = $(options.number);
    var statusEl = options.status ? $(options.status) : null;
    var loading = false;

    function setStatus(msg, isError) {
      if (!statusEl) return;
      statusEl.textContent = msg || '';
      statusEl.classList.toggle('cep-lookup-status--error', !!isError);
      statusEl.classList.toggle('cep-lookup-status--loading', msg === 'Buscando CEP...');
    }

    function lookup() {
      var cep = digitsOnly(zipEl.value);
      if (cep.length !== 8) return;
      if (loading) return;
      loading = true;
      zipEl.setAttribute('aria-busy', 'true');
      setStatus('Buscando CEP...', false);

      fetch('https://viacep.com.br/ws/' + cep + '/json/', {
        method: 'GET',
        headers: { Accept: 'application/json' }
      })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(function (data) {
          loading = false;
          zipEl.removeAttribute('aria-busy');
          if (data.erro) {
            setStatus('CEP não encontrado.', true);
            return;
          }
          if (streetEl && data.logradouro) streetEl.value = data.logradouro;
          if (neighborhoodEl && data.bairro) neighborhoodEl.value = data.bairro;
          if (cityEl && data.localidade) cityEl.value = data.localidade;
          if (stateEl && data.uf) stateEl.value = String(data.uf).toUpperCase().slice(0, 2);
          setStatus('Endereço encontrado.', false);
          if (typeof options.onSuccess === 'function') options.onSuccess(data);
          if (numberEl && !String(numberEl.value || '').trim()) {
            numberEl.focus();
          } else if (streetEl && data.logradouro && !String(numberEl ? numberEl.value : '').trim()) {
            numberEl && numberEl.focus();
          }
          setTimeout(function () {
            if (statusEl && statusEl.textContent === 'Endereço encontrado.') setStatus('');
          }, 2500);
        })
        .catch(function () {
          loading = false;
          zipEl.removeAttribute('aria-busy');
          setStatus('Não foi possível buscar o CEP. Verifique sua conexão.', true);
        });
    }

    zipEl.addEventListener('input', function () {
      formatCepInput(zipEl);
      var cep = digitsOnly(zipEl.value);
      if (cep.length < 8 && statusEl) {
        setStatus('');
      }
      if (cep.length === 8 && options.lookupOnInput) {
        lookup();
      }
    });

    zipEl.addEventListener('blur', lookup);
    zipEl.addEventListener('keydown', function (e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        lookup();
      }
    });
  }

  global.CepLookup = { bind: bind, formatCepInput: formatCepInput, digitsOnly: digitsOnly };
})(typeof window !== 'undefined' ? window : this);
