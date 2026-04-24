(function () {
  if (typeof storeSlug === 'undefined') return;

  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';

  function apiUrl(path) {
    return base.replace(/\/$/, '') + (path.indexOf('/') === 0 ? path : '/' + path);
  }

  function formatMoney(n) {
    var v = n != null ? parseFloat(n) : 0;
    if (isNaN(v)) v = 0;
    return 'R$ ' + v.toFixed(2).replace('.', ',');
  }

  function formatQty(n) {
    var v = n != null ? parseFloat(n) : 0;
    if (isNaN(v)) v = 0;
    var s = v % 1 === 0 ? String(Math.round(v)) : v.toFixed(2).replace('.', ',');
    return s;
  }

  function formatPct(n) {
    var v = n != null ? parseFloat(n) : 0;
    if (isNaN(v)) v = 0;
    return v.toFixed(1).replace('.', ',') + '%';
  }

  function showError(msg) {
    var el = document.getElementById('bi-error');
    if (!el) return;
    el.textContent = msg || 'Erro ao carregar.';
    el.classList.remove('hidden');
  }

  function hideError() {
    var el = document.getElementById('bi-error');
    if (el) el.classList.add('hidden');
  }

  function setHidden(id, hidden) {
    var el = document.getElementById(id);
    if (el) el.classList.toggle('hidden', !!hidden);
  }

  function renderProductCardBody(containerId, obj, growthKey, growthLabel) {
    var el = document.getElementById(containerId);
    if (!el) return;
    if (!obj || typeof obj !== 'object' || Object.keys(obj).length === 0) {
      el.innerHTML = '<p class="bi-muted">Sem vendas registradas no mês atual para este indicador.</p>';
      return;
    }
    var nome = obj.nome != null ? String(obj.nome) : '—';
    var q = obj.quantidade_vendida != null ? obj.quantidade_vendida : 0;
    var g = obj[growthKey];
    var gNum = g != null ? parseFloat(g) : null;
    var gStr = gNum != null && !isNaN(gNum) ? formatPct(gNum) : '—';
    var gCls = gNum != null && !isNaN(gNum) && gNum < 0 ? ' bi-trend--down' : gNum > 0 ? ' bi-trend--up' : '';
    el.innerHTML =
      '<p class="bi-card-name">' +
      escapeHtml(nome) +
      '</p>' +
      '<p class="bi-card-metric"><span class="bi-card-metric-label">Quantidade</span> ' +
      formatQty(q) +
      '</p>' +
      '<p class="bi-card-metric' +
      gCls +
      '"><span class="bi-card-metric-label">' +
      escapeHtml(growthLabel) +
      '</span> ' +
      gStr +
      '</p>';
  }

  function escapeHtml(s) {
    var d = document.createElement('div');
    d.textContent = s;
    return d.innerHTML;
  }

  function renderStalled(list) {
    var el = document.getElementById('bi-card-stalled-body');
    if (!el) return;
    if (!list || !list.length) {
      el.innerHTML = '<p class="bi-muted">Nenhum produto em situação de queda forte em relação ao mês anterior.</p>';
      return;
    }
    var ul = document.createElement('ul');
    ul.className = 'bi-mini-list';
    list.slice(0, 8).forEach(function (p) {
      var li = document.createElement('li');
      li.innerHTML =
        '<strong>' +
        escapeHtml(p.nome || '') +
        '</strong> — este mês: ' +
        formatQty(p.quantidade_mes_atual) +
        ', mês anterior: ' +
        formatQty(p.quantidade_mes_anterior);
      ul.appendChild(li);
    });
    el.innerHTML = '';
    el.appendChild(ul);
  }

  function renderCritical(list) {
    var el = document.getElementById('bi-card-stock-body');
    if (!el) return;
    if (!list || !list.length) {
      el.innerHTML = '<p class="bi-muted">Nenhum produto abaixo do mínimo definido.</p>';
      return;
    }
    var ul = document.createElement('ul');
    ul.className = 'bi-mini-list';
    list.forEach(function (p) {
      var li = document.createElement('li');
      li.innerHTML =
        '<strong>' +
        escapeHtml(p.nome || '') +
        '</strong> — stock: ' +
        formatQty(p.estoque_atual) +
        ' (mín. ' +
        formatQty(p.estoque_minimo) +
        ')';
      ul.appendChild(li);
    });
    el.innerHTML = '';
    el.appendChild(ul);
  }

  /** Teto “redondo” (1–2–5 × 10^k) sempre >= n, para o eixo Y acompanhar vendas grandes (ex.: 50, 500). */
  function niceYMax(n) {
    if (n <= 0) return 5;
    var m = Math.max(5, n);
    var exp = Math.floor(Math.log(m) / Math.LN10);
    var f = m / Math.pow(10, exp);
    var nf = f <= 1 ? 1 : f <= 2 ? 2 : f <= 5 ? 5 : 10;
    var ceil = nf * Math.pow(10, exp);
    while (ceil < m) {
      if (nf < 10) {
        nf = nf === 1 ? 2 : nf === 2 ? 5 : 10;
      } else {
        nf = 1;
        exp += 1;
      }
      ceil = nf * Math.pow(10, exp);
    }
    return ceil;
  }

  function formatAxisNumber(val) {
    var rounded = Math.round(val * 100) / 100;
    if (Math.abs(rounded - Math.round(rounded)) > 1e-6) {
      return String(rounded).replace('.', ',');
    }
    var r = Math.round(rounded);
    return String(r).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  }

  function renderChart(items) {
    var wrap = document.getElementById('bi-chart-bars');
    var empty = document.getElementById('bi-chart-empty');
    if (!wrap) return;
    wrap.innerHTML = '';
    var arr = (items || []).filter(function (x) {
      return x && parseFloat(x.previsao_proximo_mes) > 0;
    });
    if (!arr.length) {
      if (empty) empty.classList.remove('hidden');
      return;
    }
    if (empty) empty.classList.add('hidden');

    var max = 0;
    arr.forEach(function (x) {
      var v = parseFloat(x.previsao_proximo_mes) || 0;
      if (v > max) max = v;
    });
    if (max <= 0) max = 1;
    var yMax = niceYMax(max);

    var tickSteps = 5;
    var ticks = [];
    for (var ti = tickSteps; ti >= 0; ti--) {
      var val = (yMax * ti) / tickSteps;
      ticks.push(formatAxisNumber(val));
    }

    var slice = arr.slice(0, 12);

    var root = document.createElement('div');
    root.className = 'bi-chart-vchart';
    root.setAttribute('role', 'img');
    root.setAttribute('aria-label', 'Gráfico de barras: quantidade prevista por produto');

    var main = document.createElement('div');
    main.className = 'bi-chart-vchart-main';

    var yAxis = document.createElement('div');
    yAxis.className = 'bi-chart-y-axis';
    yAxis.setAttribute('aria-hidden', 'true');
    ticks.forEach(function (t) {
      var span = document.createElement('span');
      span.className = 'bi-chart-y-tick';
      span.textContent = t;
      yAxis.appendChild(span);
    });

    var outer = document.createElement('div');
    outer.className = 'bi-chart-plot-outer';

    var plot = document.createElement('div');
    plot.className = 'bi-chart-plot';

    var grid = document.createElement('div');
    grid.className = 'bi-chart-grid-bg';
    grid.setAttribute('aria-hidden', 'true');

    var stack = document.createElement('div');
    stack.className = 'bi-chart-bars-stack';
    stack.style.setProperty('--bi-chart-bar-count', String(slice.length));

    var stackInner = document.createElement('div');
    stackInner.className = 'bi-chart-bars-stack-inner';

    var rowPlot = document.createElement('div');
    rowPlot.className = 'bi-chart-bars-row bi-chart-bars-row--plot';

    var rowLabels = document.createElement('div');
    rowLabels.className = 'bi-chart-bars-row bi-chart-bars-labels-row';

    slice.forEach(function (x) {
      var v = parseFloat(x.previsao_proximo_mes) || 0;
      var pct = yMax > 0 ? (v / yMax) * 100 : 0;
      if (v > 0 && pct < 2.5) {
        pct = 2.5;
      }
      if (pct > 100) {
        pct = 100;
      }

      var colPlot = document.createElement('div');
      colPlot.className = 'bi-chart-bar-col bi-chart-bar-col--plot';

      var cell = document.createElement('div');
      cell.className = 'bi-chart-bar-cell';

      var bar = document.createElement('div');
      bar.className = 'bi-chart-bar';
      bar.style.height = pct + '%';
      bar.title = (x.nome || '') + ': ' + formatQty(v);

      cell.appendChild(bar);
      colPlot.appendChild(cell);
      rowPlot.appendChild(colPlot);

      var labelCol = document.createElement('div');
      labelCol.className = 'bi-chart-bar-label-col';

      var qty = document.createElement('span');
      qty.className = 'bi-chart-bar-qty';
      qty.textContent = formatQty(v);

      var xl = document.createElement('div');
      xl.className = 'bi-chart-x-label';
      xl.textContent = x.nome || '—';
      xl.title = x.nome || '';

      labelCol.appendChild(qty);
      labelCol.appendChild(xl);
      rowLabels.appendChild(labelCol);
    });

    stackInner.appendChild(grid);
    stackInner.appendChild(rowPlot);
    stack.appendChild(stackInner);
    stack.appendChild(rowLabels);
    plot.appendChild(stack);
    outer.appendChild(plot);
    main.appendChild(yAxis);
    main.appendChild(outer);
    root.appendChild(main);
    wrap.appendChild(root);
  }

  function renderIdeas(list) {
    var ul = document.getElementById('bi-ideas-list');
    if (!ul) return;
    ul.innerHTML = '';
    (list || []).forEach(function (t) {
      var li = document.createElement('li');
      li.className = 'bi-idea-item';
      li.textContent = t;
      ul.appendChild(li);
    });
  }

  var biRevenueChart = null;
  var biRevenueFiltersBound = false;

  function biCssVar(name, fallback) {
    var v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
  }

  function destroyBiRevenueChart() {
    if (biRevenueChart) {
      biRevenueChart.destroy();
      biRevenueChart = null;
    }
  }

  var biMesesCurto = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];

  function formatRevenueAxisLabel(dataStr, granularidade) {
    if (!dataStr) return '';
    var p = String(dataStr).split('-');
    if (p.length < 3) return dataStr;
    if (granularidade === 'mes') {
      var mi = parseInt(p[1], 10) - 1;
      return (biMesesCurto[mi] || p[1]) + '/' + p[0];
    }
    return p[2] + '/' + p[1];
  }

  function setRevenueChartError(msg) {
    var el = document.getElementById('bi-revenue-chart-error');
    if (!el) return;
    if (msg) {
      el.textContent = msg;
      el.classList.remove('hidden');
    } else {
      el.textContent = '';
      el.classList.add('hidden');
    }
  }

  function bindRevenueFiltersOnce() {
    if (biRevenueFiltersBound) return;
    var g = document.querySelector('.bi-revenue-filters');
    if (!g) return;
    biRevenueFiltersBound = true;
    g.addEventListener('click', function (ev) {
      var btn = ev.target.closest('.bi-revenue-filter');
      if (!btn || !g.contains(btn)) return;
      var p = btn.getAttribute('data-periodo');
      if (!p) return;
      g.querySelectorAll('.bi-revenue-filter').forEach(function (b) {
        b.classList.toggle('bi-revenue-filter--active', b === btn);
      });
      loadFaturamentoSeries(p);
    });
  }

  function loadFaturamentoSeries(periodo) {
    var canvas = document.getElementById('bi-revenue-chart');
    if (!canvas) return;
    if (typeof Chart === 'undefined') {
      setRevenueChartError('Biblioteca de gráfico indisponível. Verifique a ligação à internet (Chart.js).');
      return;
    }
    setRevenueChartError('');
    destroyBiRevenueChart();
    fetch(
      apiUrl(
        '/api/loja/' +
          encodeURIComponent(storeSlug) +
          '/analyzing-bi/faturamento?periodo=' +
          encodeURIComponent(periodo || '30d')
      ),
      {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
      }
    )
      .then(function (r) {
        return r.text().then(function (text) {
          var j = null;
          try {
            j = text ? JSON.parse(text) : null;
          } catch (e) {
            j = null;
          }
          if (r.status === 403) {
            throw new Error((j && j.error) || 'Acesso reservado ao gerente.');
          }
          if (!r.ok) {
            throw new Error((j && j.error) || text || 'Erro ao carregar faturamento.');
          }
          return j || {};
        });
      })
      .then(function (payload) {
        var serie = payload.serie || [];
        var gran = payload.granularidade === 'mes' ? 'mes' : 'dia';
        var labels = serie.map(function (row) {
          return formatRevenueAxisLabel(row.data, gran);
        });
        var values = serie.map(function (row) {
          return parseFloat(row.valor) || 0;
        });
        var primary = biCssVar('--primary', '#2563eb');
        var fillSoft = parseColorToRgba(primary, 0.14);

        var datasets = [
          {
            label: 'Faturamento',
            data: values,
            fill: true,
            tension: 0.35,
            borderColor: primary,
            backgroundColor: fillSoft,
            borderWidth: 2,
            pointRadius: 4,
            pointHoverRadius: 6,
            pointBackgroundColor: primary,
            pointBorderColor: biCssVar('--bg-card', '#fff'),
            pointBorderWidth: 2
          }
        ];
        /* serie_previsao (mesmo eixo X que `serie`): quando existir no API, acrescentar 2.º dataset com borderDash: [6,4] */

        biRevenueChart = new Chart(canvas, {
          type: 'line',
          data: { labels: labels, datasets: datasets },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
              legend: {
                display: false,
                labels: { color: biCssVar('--text', '#0f172a'), boxWidth: 12 }
              },
              tooltip: {
                callbacks: {
                  title: function (items) {
                    return items.length ? String(items[0].label || '') : '';
                  },
                  label: function (ctx) {
                    var v = ctx.parsed.y;
                    return ' ' + formatMoney(v);
                  }
                }
              }
            },
            scales: {
              x: {
                ticks: {
                  color: biCssVar('--text-muted', '#64748b'),
                  maxRotation: gran === 'dia' ? 45 : 0,
                  autoSkip: true,
                  maxTicksLimit: gran === 'dia' ? 14 : 12
                },
                grid: { color: biCssVar('--border', '#e2e8f0') }
              },
              y: {
                beginAtZero: true,
                ticks: {
                  color: biCssVar('--text-muted', '#64748b'),
                  callback: function (val) {
                    return formatMoney(val);
                  }
                },
                grid: { color: biCssVar('--border', '#e2e8f0') }
              }
            }
          }
        });
      })
      .catch(function (e) {
        setRevenueChartError(e.message || 'Erro ao carregar o gráfico de faturamento.');
      });
  }

  function parseColorToRgba(color, alpha) {
    var c = String(color).trim();
    if (c.indexOf('rgb') === 0) {
      var nums = c
        .replace(/^rgba?\(/, '')
        .replace(/\)$/, '')
        .split(',')
        .map(function (x) {
          return parseFloat(String(x).trim());
        });
      if (nums.length >= 3 && !isNaN(nums[0])) {
        return 'rgba(' + nums[0] + ',' + nums[1] + ',' + nums[2] + ',' + alpha + ')';
      }
    }
    var h = c.replace('#', '');
    if (h.length === 3) {
      h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
    }
    if (h.length !== 6) {
      return 'rgba(37, 99, 235,' + alpha + ')';
    }
    var r = parseInt(h.slice(0, 2), 16);
    var g = parseInt(h.slice(2, 4), 16);
    var b = parseInt(h.slice(4, 6), 16);
    return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
  }

  function showData(data) {
    hideError();
    setHidden('bi-loading', true);
    setHidden('bi-kpis', false);
    setHidden('bi-revenue-section', false);
    setHidden('bi-chart-section', false);
    setHidden('bi-cards', false);
    setHidden('bi-ideas', false);

    var elTotal = document.getElementById('bi-kpi-total');
    var elMonth = document.getElementById('bi-kpi-month');
    var elOrd = document.getElementById('bi-kpi-orders');
    var elTick = document.getElementById('bi-kpi-ticket');
    var elProf = document.getElementById('bi-kpi-profit');
    if (elTotal) elTotal.textContent = formatMoney(data.valor_total);
    if (elMonth) elMonth.textContent = formatMoney(data.valor_mensal);
    if (elOrd) elOrd.textContent = String(data.quantidade_pedidos != null ? data.quantidade_pedidos : 0);
    if (elTick) elTick.textContent = formatMoney(data.ticket_medio);
    if (elProf) elProf.textContent = formatMoney(data.lucro_estimado);

    bindRevenueFiltersOnce();
    var activeP = document.querySelector('.bi-revenue-filter--active');
    var periodoInicial = (activeP && activeP.getAttribute('data-periodo')) || '30d';
    loadFaturamentoSeries(periodoInicial);

    renderChart(data.previsao_produtos);
    renderProductCardBody('bi-card-top-body', data.produto_mais_vendido, 'crescimento_percentual', 'vs. mês anterior');
    renderProductCardBody('bi-card-bottom-body', data.produto_menos_vendido, 'variacao_percentual', 'Variação vs. mês anterior');
    renderStalled(data.produtos_parados);
    renderCritical(data.estoque_critico);
    renderIdeas(data.ideias_investimento);
  }

  fetch(apiUrl('/api/loja/' + encodeURIComponent(storeSlug) + '/analyzing-bi'), {
    credentials: 'same-origin',
    headers: { Accept: 'application/json' }
  })
    .then(function (r) {
      return r.text().then(function (text) {
        var j = null;
        try {
          j = text ? JSON.parse(text) : null;
        } catch (e) {
          j = null;
        }
        if (r.status === 403) {
          throw new Error((j && j.error) || 'Acesso reservado ao gerente.');
        }
        if (!r.ok) {
          throw new Error((j && j.error) || text || 'Erro ao carregar BI.');
        }
        return j || {};
      });
    })
    .then(showData)
    .catch(function (e) {
      setHidden('bi-loading', true);
      showError(e.message || 'Erro ao carregar.');
    });
})();
