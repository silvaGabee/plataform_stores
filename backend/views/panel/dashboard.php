<?php
ob_start();
?>
<div class="panel-content panel-dashboard">
    <header class="panel-page-header">
        <p class="panel-page-eyebrow">Resumo</p>
        <h1>Dashboard</h1>
    </header>
    <p class="dashboard-welcome panel-lead">Bem-vindo, <strong><?= htmlspecialchars($welcome_user_name ?: 'utilizador') ?></strong> — está no painel de <strong><?= htmlspecialchars($store['name']) ?></strong>.</p>

    <div class="panel-surface-card dashboard-store-link-card">
        <div class="panel-surface-card-head">
            <span class="panel-surface-icon" aria-hidden="true">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span class="dashboard-store-link-label">Endereço público da vitrine</span>
        </div>
        <div class="dashboard-store-link-row">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" target="_blank" rel="noopener" class="dashboard-store-link-url" id="dashboard-store-url"><?= htmlspecialchars(base_url("loja/{$store['slug']}")) ?></a>
            <button type="button" class="btn btn-secondary btn-sm dashboard-store-link-copy" id="dashboard-copy-url" title="Copiar link">Copiar</button>
        </div>
    </div>

    <div id="dashboard-low-stock-alert" class="dashboard-low-stock-alert panel-alert-warning hidden">
        <h2>Estoque baixo</h2>
        <p>Os seguintes produtos estão abaixo do estoque mínimo:</p>
        <ul id="dashboard-low-stock-list"></ul>
        <p><a href="<?= base_url("painel/{$store['slug']}/estoque") ?>" class="btn btn-primary btn-sm">Ir para Estoque</a></p>
    </div>

    <?php if (!empty($panel_readonly)): ?>
    <p class="panel-readonly-badge panel-readonly-banner">Está em modo apenas visualização. Só o gerente pode alterar configurações e confirmar pagamentos.</p>
    <?php else: ?>
    <section class="panel-section-card">
        <div class="panel-section-head">
            <span class="panel-section-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M7 7h10v10H7zM7 3h10M7 21h10M3 7v10M21 7v10" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="panel-section-head-text">
                <h2 class="panel-section-title">Chave PIX da loja</h2>
                <p class="panel-section-desc">Define onde a loja recebe por PIX. O QR Code no checkout usa estes dados.</p>
            </div>
        </div>
        <div class="panel-section-body">
            <div id="pix-card" class="pix-card panel-pix-summary card hidden">
                <p class="pix-card-titular"><strong>Titular:</strong> <span id="pix-card-name"></span></p>
                <p class="pix-card-chave"><strong>Chave PIX:</strong> <span id="pix-card-key"></span></p>
                <button type="button" id="pix-btn-edit" class="btn btn-secondary btn-sm">Editar</button>
            </div>

            <form id="pix-config-form" class="panel-form-stack panel-form-narrow">
                <label for="pix-key-type">Tipo da chave</label>
                <select id="pix-key-type" name="pix_key_type">
                    <option value="cpf">CPF</option>
                    <option value="cnpj">CNPJ</option>
                    <option value="email">E-mail</option>
                    <option value="telefone">Telefone</option>
                    <option value="aleatoria">Chave aleatória</option>
                </select>
                <label for="pix-key">Chave PIX</label>
                <input type="text" id="pix-key" name="pix_key" placeholder="CPF, CNPJ, e-mail, telefone ou chave aleatória" autocomplete="off">
                <label for="pix-merchant-name">Nome do titular <span class="panel-label-optional">(opcional)</span></label>
                <input type="text" id="pix-merchant-name" name="merchant_name" placeholder="Nome que aparece no PIX">
                <label for="pix-merchant-city">Cidade <span class="panel-label-optional">(opcional)</span></label>
                <input type="text" id="pix-merchant-city" name="merchant_city" placeholder="Cidade">
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Salvar chave PIX</button>
                </div>
            </form>
            <p id="pix-config-msg" class="panel-form-msg" role="status" aria-live="polite"></p>
        </div>
    </section>

    <section class="panel-section-card">
        <div class="panel-section-head">
            <span class="panel-section-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="panel-section-head-text">
                <h2 class="panel-section-title">Pagamentos PIX pendentes</h2>
                <p class="panel-section-desc">Confirme manualmente quando o valor tiver entrado na sua conta.</p>
            </div>
        </div>
        <div class="panel-section-body">
            <div id="pending-payments" class="panel-pending-payments"></div>
        </div>
    </section>
    <?php endif; ?>
</div>
<script>
(function(){
  var copyBtn = document.getElementById('dashboard-copy-url');
  var urlEl = document.getElementById('dashboard-store-url');
  if (copyBtn && urlEl) {
    copyBtn.addEventListener('click', function(){
      var url = urlEl.href || urlEl.textContent;
      if (!url) return;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(function(){
          var t = copyBtn.textContent;
          copyBtn.textContent = 'Copiado!';
          setTimeout(function(){ copyBtn.textContent = t; }, 2000);
        }).catch(function(){ alert('Não foi possível copiar.'); });
      } else {
        var ta = document.createElement('textarea');
        ta.value = url;
        ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try {
          document.execCommand('copy');
          var t = copyBtn.textContent;
          copyBtn.textContent = 'Copiado!';
          setTimeout(function(){ copyBtn.textContent = t; }, 2000);
        } catch (e) { alert('Não foi possível copiar.'); }
        document.body.removeChild(ta);
      }
    });
  }
})();
</script>
<script>
(function(){
  if (typeof window.panelReadonly !== 'undefined' && window.panelReadonly) return;
  var slug = <?= json_encode($store['slug']) ?>;
  var base = document.querySelector('meta[name="base-url"]')?.content || '';
  base = base.replace(/\/$/, '');

  var pixCard = document.getElementById('pix-card');
  var pixForm = document.getElementById('pix-config-form');
  var pixMsg = document.getElementById('pix-config-msg');

  function showPixCard(config) {
    var name = (config.merchant_name && config.merchant_name.trim()) ? config.merchant_name.trim() : 'Não informado';
    var key = (config.pix_key && config.pix_key.trim()) ? config.pix_key.trim() : '';
    if (!key) { pixCard.classList.add('hidden'); pixForm.classList.remove('hidden'); return; }
    document.getElementById('pix-card-name').textContent = name;
    document.getElementById('pix-card-key').textContent = key;
    pixCard.classList.remove('hidden');
    pixForm.classList.add('hidden');
    pixMsg.textContent = '';
  }
  function showPixForm() {
    pixCard.classList.add('hidden');
    pixForm.classList.remove('hidden');
    pixMsg.textContent = '';
  }

  fetch(base + '/api/loja/' + slug + '/pix-config').then(function(r){ return r.json(); }).then(function(res){
    var c = res.config || {};
    document.getElementById('pix-key-type').value = c.pix_key_type || 'aleatoria';
    document.getElementById('pix-key').value = c.pix_key || '';
    document.getElementById('pix-merchant-name').value = c.merchant_name || '';
    document.getElementById('pix-merchant-city').value = c.merchant_city || '';
    if (c.pix_key && c.pix_key.trim()) showPixCard(c); else showPixForm();
  });

  document.getElementById('pix-btn-edit').addEventListener('click', function(){ showPixForm(); });

  document.getElementById('pix-config-form').addEventListener('submit', function(e){
    e.preventDefault();
    var payload = {
      pix_key_type: document.getElementById('pix-key-type').value,
      pix_key: document.getElementById('pix-key').value.trim(),
      merchant_name: document.getElementById('pix-merchant-name').value.trim(),
      merchant_city: document.getElementById('pix-merchant-city').value.trim()
    };
    if (!payload.pix_key) { pixMsg.textContent = 'Informe a chave PIX.'; pixMsg.classList.add('is-error'); return; }
    pixMsg.classList.remove('is-error');
    fetch(base + '/api/loja/' + slug + '/pix-config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function(r){ return r.json(); }).then(function(res){
      if (res.success) {
        showPixCard({ merchant_name: payload.merchant_name, pix_key: payload.pix_key });
      } else {
        pixMsg.textContent = res.error || 'Erro ao salvar.';
        pixMsg.classList.add('is-error');
      }
    });
  });

  var emptyPendingHtml = '<div class="panel-empty-state" role="status">' +
    '<div class="panel-empty-icon" aria-hidden="true"><svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg></div>' +
    '<p class="panel-empty-title">Nenhum pagamento pendente</p>' +
    '<p class="panel-empty-desc">Quando um cliente finalizar com PIX aguardando confirmação, o pedido aparece aqui.</p></div>';

  fetch(base + '/api/loja/' + slug + '/payments/pending').then(function(r){ return r.json(); }).then(function(res){
    var list = res.payments || [];
    var el = document.getElementById('pending-payments');
    if (!list.length) { el.innerHTML = emptyPendingHtml; return; }
    el.innerHTML = list.map(function(p){
      return '<div class="panel-pending-row card">' +
        '<div class="panel-pending-info"><span class="panel-pending-order">Pedido #' + p.order_id + '</span>' +
        '<span class="panel-pending-amount">R$ ' + parseFloat(p.amount).toFixed(2).replace('.', ',') + '</span>' +
        '<span class="panel-pending-method">' + (p.method || 'PIX') + '</span></div>' +
        '<button type="button" class="btn btn-sm btn-primary confirm-pix" data-payment-id="' + p.id + '">Confirmar PIX</button></div>';
    }).join('');
    el.querySelectorAll('.confirm-pix').forEach(function(btn){
      btn.addEventListener('click', function(){
        var id = this.dataset.paymentId;
        fetch(base + '/api/loja/' + slug + '/payments/confirm', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ payment_id: id })
        }).then(function(r){ return r.json(); }).then(function(r){
          if (r.error) alert(r.error); else { alert('Pagamento confirmado.'); location.reload(); }
        });
      });
    });
  });
})();
</script>
<script>
(function(){
  var slug = <?= json_encode($store['slug']) ?>;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  base = base.replace(/\/$/, '');
  fetch(base + '/api/loja/' + slug + '/products/low-stock').then(function(r){ return r.json(); }).then(function(res){
    var list = res.products || [];
    var box = document.getElementById('dashboard-low-stock-alert');
    var ul = document.getElementById('dashboard-low-stock-list');
    if (!box || !ul) return;
    if (list.length === 0) { box.classList.add('hidden'); return; }
    box.classList.remove('hidden');
    ul.innerHTML = list.map(function(p){
      return '<li><strong>' + (p.name || 'Produto').replace(/</g, '&lt;') + '</strong> — Estoque: ' + (p.stock_quantity || 0) + ' (mínimo: ' + (p.min_stock || 0) + ')</li>';
    }).join('');
  }).catch(function(){ var b = document.getElementById('dashboard-low-stock-alert'); if (b) b.classList.add('hidden'); });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout_panel.php';
