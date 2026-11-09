/**
 * Analyzing BI: simulador de compra de estoque (VPL, TIR, payback) e
 * financiamento da compra pela Price e pelo SAC.
 *
 * Toda conta é feita no servidor (App\Domain\Finance\Investment e
 * Installments); aqui só se monta o formulário e se desenha a resposta.
 */
(function () {
  'use strict';
  var secao = document.getElementById('bi-sim');
  var form = document.getElementById('bi-sim-form');
  if (!secao || !form || !window.UI || typeof storeSlug === 'undefined') return;

  var base = 'api/loja/' + encodeURIComponent(storeSlug) + '/analyzing-bi/simulador-compra';
  var produtos = [];
  var resultado = document.getElementById('bi-sim-result');
  var info = document.getElementById('bi-sim-produto-info');
  var pedido = 0;
  var espera = null;

  function money(v) { return UI.money(v); }
  function pct(v) {
    return v === null || v === undefined ? '—' : Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 2 }) + '% a.m.';
  }
  function meses(v) {
    if (v === null || v === undefined) return 'não se paga';
    var n = Number(v).toLocaleString('pt-BR', { maximumFractionDigits: 1 });
    return n + (Number(v) === 1 ? ' mês' : ' meses');
  }
  function stat(rotulo, valor, nota, classe) {
    return '<div class="bi-pix-stat' + (classe ? ' ' + classe : '') + '"><span>' + UI.esc(rotulo) + '</span><strong>' + UI.esc(valor) + '</strong><small>' + UI.esc(nota) + '</small></div>';
  }
  function produtoAtual() {
    var id = Number(form.elements.produto.value);
    for (var i = 0; i < produtos.length; i++) if (produtos[i].produto_id === id) return produtos[i];
    return null;
  }

  function mostrarProduto() {
    var p = produtoAtual();
    if (!p) { info.textContent = ''; return; }
    info.textContent = 'Custo ' + money(p.custo) + ' · ' +
      (p.preco_origem === 'media_vendida' ? 'preço médio recebido ' : 'preço de cadastro (ainda sem vendas) ') + money(p.preco) +
      ' · vende cerca de ' + Number(p.venda_mensal).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' por mês · ' +
      p.estoque + ' em estoque.';
  }

  function sugerirQuantidade() {
    var p = produtoAtual();
    // Ponto de partida: o que vende em três meses (mínimo 1).
    form.elements.quantidade.value = p ? Math.max(1, Math.round(p.venda_mensal * 3)) : '';
  }

  function tabelaFluxo(d) {
    var tma = Number(d.premissas.tma) / 100;
    var acum = 0;
    var linhas = d.fluxos.map(function (fc, t) {
      var vp = fc / Math.pow(1 + tma, t);
      acum += vp;
      return '<tr><td>' + (t === 0 ? 'Hoje (compra)' : 'Mês ' + t) + '</td>' +
        '<td class="ui-num">' + money(fc) + '</td>' +
        '<td class="ui-num">' + money(vp) + '</td>' +
        '<td class="ui-num' + (acum < 0 ? ' bi-sim-neg' : '') + '">' + money(acum) + '</td></tr>';
    }).join('');
    return '<details class="bi-sim-details"><summary>Fluxo de caixa mês a mês</summary><div class="ui-table-wrap"><table class="ui-table ui-table--compact">' +
      '<thead><tr><th>Período</th><th class="ui-num">Fluxo</th><th class="ui-num">Valor presente</th><th class="ui-num">Acumulado (VP)</th></tr></thead>' +
      '<tbody>' + linhas + '</tbody></table></div>' +
      '<p class="bi-muted">Valor presente = fluxo ÷ (1 + TMA)<sup>t</sup>. O VPL é o último acumulado.</p></details>';
  }

  function blocoFinanciamento(d) {
    var f = d.financiamento;
    if (!f) return '';
    var p = f.price, s = f.sac;
    var linhas = p.tabela.map(function (lp, k) {
      var ls = s.tabela[k];
      return '<tr><td>' + lp.n + '</td><td class="ui-num">' + money(lp.parcela) + '</td><td class="ui-num">' + money(lp.juros) + '</td>' +
        '<td class="ui-num">' + money(ls.parcela) + '</td><td class="ui-num">' + money(ls.juros) + '</td></tr>';
    }).join('');
    var maisBarato = f.valor_presente_price < f.valor_presente_sac ? 'Price' : (f.valor_presente_sac < f.valor_presente_price ? 'SAC' : null);
    var leitura = 'O SAC paga ' + money(p.juros_total - s.juros_total) + ' a menos de juros porque devolve o principal mais cedo. ' +
      'Pela TMA de ' + pct(f.tma) + ', o que importa é o valor presente das parcelas: ' +
      (maisBarato ? 'a ' + maisBarato + ' pesa menos hoje' : 'os dois empatam') +
      (Number(f.tma) > Number(p.taxa_mensal) ? ', e as duas custam menos que pagar à vista ' + money(p.principal) + ', porque o dinheiro da loja rende mais que os juros cobrados.'
        : Number(f.tma) < Number(p.taxa_mensal) ? ', mas as duas custam mais que pagar à vista ' + money(p.principal) + ', porque os juros cobrados passam do que o dinheiro da loja renderia.'
        : '.');
    return '<h3 class="bi-sim-subtitle">Financiar ' + money(p.principal) + ' em ' + p.parcelas + 'x a ' + pct(p.taxa_mensal) + '</h3>' +
      '<div class="bi-sim-compare">' +
        '<div class="bi-pix-stat"><span>Tabela Price — parcelas iguais</span><strong>' + money(p.parcela) + '</strong>' +
          '<small>total ' + money(p.total) + ' · juros ' + money(p.juros_total) + '</small><small>valor presente à TMA: ' + money(f.valor_presente_price) + '</small></div>' +
        '<div class="bi-pix-stat"><span>SAC — amortização constante de ' + money(s.amortizacao) + '</span><strong>' + money(s.primeira_parcela) + ' → ' + money(s.ultima_parcela) + '</strong>' +
          '<small>total ' + money(s.total) + ' · juros ' + money(s.juros_total) + '</small><small>valor presente à TMA: ' + money(f.valor_presente_sac) + '</small></div>' +
      '</div>' +
      '<p class="bi-pix-note">' + UI.esc(leitura) + '</p>' +
      '<details class="bi-sim-details"><summary>Parcelas lado a lado</summary><div class="ui-table-wrap"><table class="ui-table ui-table--compact">' +
      '<thead><tr><th>Nº</th><th class="ui-num">Price</th><th class="ui-num">juros</th><th class="ui-num">SAC</th><th class="ui-num">juros</th></tr></thead>' +
      '<tbody>' + linhas + '</tbody></table></div></details>';
  }

  function desenhar(d) {
    var compensa = d.decisao === 'compensa';
    var avisos = [];
    if (d.meses_ate_comecar > 1) avisos.push('O lote só começa a vender no mês ' + d.meses_ate_comecar + ': antes sai o estoque que já está na prateleira.');
    if (d.sobra > 0) avisos.push(Number(d.sobra).toLocaleString('pt-BR', { maximumFractionDigits: 1 }) + ' peça(s) não vendem em ' + d.meses + ' meses no ritmo atual e ficam fora da conta.');
    if (d.fluxos.length === 1) avisos.push('Sem vendas nos últimos 90 dias, não há ritmo para projetar: o dinheiro não volta na simulação.');

    resultado.innerHTML =
      '<div class="bi-pix-grid bi-sim-grid">' +
        stat('Investimento hoje', money(d.investimento), Number(d.premissas.quantidade).toLocaleString('pt-BR') + ' peças × ' + money(d.produto.custo)) +
        stat('VPL à TMA de ' + pct(d.premissas.tma), money(d.vpl), compensa ? 'rende mais que a TMA: compensa' : 'rende menos que a TMA: não compensa', compensa ? 'is-pos' : 'is-neg') +
        stat('TIR', d.tir === null ? 'não existe' : pct(d.tir), d.tir === null ? 'o dinheiro investido não volta' : (d.tir >= d.premissas.tma ? 'acima' : 'abaixo') + ' da TMA', d.tir === null ? 'is-neg' : (d.tir >= d.premissas.tma ? 'is-pos' : 'is-neg')) +
        stat('Lucro sem descontar o tempo', money(d.lucro_nominal), 'entradas ' + money(d.entradas) + ' − investimento') +
        stat('Payback simples', meses(d.payback_simples), 'quando a soma das entradas cobre a compra') +
        stat('Payback descontado', meses(d.payback_descontado), 'o mesmo, com as entradas trazidas a valor presente') +
        (avisos.length ? '<p class="bi-pix-note">' + avisos.map(UI.esc).join(' ') + '</p>' : '') +
      '</div>' +
      tabelaFluxo(d) +
      blocoFinanciamento(d);
  }

  function simular() {
    var p = produtoAtual();
    if (!p) { resultado.innerHTML = ''; return; }
    var q = new URLSearchParams({
      produto: String(p.produto_id),
      quantidade: form.elements.quantidade.value || '0',
      tma: form.elements.tma.value || '0',
      estoque: form.elements.estoque.checked ? '1' : '0',
      parcelas: form.elements.parcelas.value,
      taxa: form.elements.taxa.value || '0'
    });
    var meu = ++pedido;
    UI.api(base + '?' + q.toString()).then(function (d) {
      if (meu !== pedido) return; // chegou uma resposta mais nova antes
      if (d.error) { resultado.innerHTML = '<p class="bi-msg bi-msg--error">' + UI.esc(d.error) + '</p>'; return; }
      desenhar(d);
    });
  }

  function agendar() {
    clearTimeout(espera);
    espera = setTimeout(simular, 300);
  }

  form.addEventListener('submit', function (e) { e.preventDefault(); simular(); });
  form.addEventListener('input', function (e) {
    if (e.target.name === 'produto') return;
    agendar();
  });
  form.elements.produto.addEventListener('change', function () {
    mostrarProduto();
    sugerirQuantidade();
    simular();
  });
  form.elements.parcelas.addEventListener('change', function () {
    form.elements.taxa.disabled = form.elements.parcelas.value === '0';
  });
  form.elements.taxa.disabled = true;

  UI.api(base).then(function (d) {
    if (d.error) return;
    // Sem custo não há investimento para analisar.
    produtos = (d.produtos || []).filter(function (p) { return p.custo > 0; });
    if (!produtos.length) return;
    // Começa pelo que mais vende: é a compra que a loja mais repete.
    var inicial = produtos.slice().sort(function (a, b) { return b.venda_mensal - a.venda_mensal; })[0];
    form.elements.produto.innerHTML = produtos.map(function (p) {
      return '<option value="' + p.produto_id + '"' + (p === inicial ? ' selected' : '') + '>' + UI.esc(p.nome) + '</option>';
    }).join('');
    secao.classList.remove('hidden');
    mostrarProduto();
    sugerirQuantidade();
    simular();
  });
})();
