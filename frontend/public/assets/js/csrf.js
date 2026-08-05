/**
 * Envia o token CSRF em toda requisição que altera estado.
 *
 * Intercepta window.fetch em vez de alterar os quinze arquivos que fazem
 * requisição — dez deles com a própria função api(). Um lugar só para acertar,
 * e nenhum caminho novo escapa por esquecimento.
 *
 * Precisa ser carregado ANTES de qualquer script que faça fetch.
 */
(function () {
  'use strict';

  var META = 'meta[name="csrf-token"]';
  var METODOS_INSEGUROS = ['POST', 'PUT', 'PATCH', 'DELETE'];

  function token() {
    var el = document.querySelector(META);
    return el ? el.getAttribute('content') || '' : '';
  }

  /** Requisição para outro host não recebe o token: ele é segredo desta origem. */
  function mesmaOrigem(input) {
    try {
      var url = new URL(
        typeof input === 'string' ? input : (input && input.url) || '',
        window.location.href
      );
      return url.origin === window.location.origin;
    } catch (e) {
      // URL relativa que não parseia é, por definição, desta origem.
      return true;
    }
  }

  function metodoDe(input, init) {
    var m = (init && init.method) || (input && input.method) || 'GET';
    return String(m).toUpperCase();
  }

  var fetchOriginal = window.fetch;
  if (typeof fetchOriginal !== 'function') {
    return;
  }

  window.fetch = function (input, init) {
    var metodo = metodoDe(input, init);
    if (METODOS_INSEGUROS.indexOf(metodo) === -1 || !mesmaOrigem(input)) {
      return fetchOriginal.apply(this, arguments);
    }

    var t = token();
    if (!t) {
      return fetchOriginal.apply(this, arguments);
    }

    init = init || {};
    // Headers pode chegar como objeto literal, array de pares ou Headers.
    var headers = new Headers(init.headers || (input && input.headers) || undefined);
    if (!headers.has('X-CSRF-Token')) {
      headers.set('X-CSRF-Token', t);
    }
    init.headers = headers;
    // Sem credentials same-origin explícito, um fetch sem cookie chegaria
    // sem sessão e o token não teria com o que ser comparado.
    if (!init.credentials) {
      init.credentials = 'same-origin';
    }

    return fetchOriginal.call(this, input, init);
  };

  /**
   * Token vencido volta como 403 com o cabeçalho X-CSRF-Retry. Sem tratar, o
   * usuário veria só "sem permissão" numa ação que antes funcionava.
   *
   * O cabeçalho é lido em vez do corpo de propósito: ler o corpo aqui o
   * consumiria, e quem chamou não teria mais o que ler.
   */
  var jaAvisou = false;
  var fetchComToken = window.fetch;
  window.fetch = function () {
    return fetchComToken.apply(this, arguments).then(function (resposta) {
      if (resposta && resposta.status === 403 && resposta.headers.get('X-CSRF-Retry') && !jaAvisou) {
        jaAvisou = true;
        window.alert('Sua sessão expirou. A página será recarregada.');
        window.location.reload();
      }
      return resposta;
    });
  };
})();
