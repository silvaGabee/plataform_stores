<?php $content = ob_start(); ?>
<div class="panel-content">
    <h1>Dashboard</h1>
    <p class="dashboard-welcome">Bem-vindo <strong><?= htmlspecialchars($welcome_user_name ?: 'usuário') ?></strong> ao painel de <strong><?= htmlspecialchars($store['name']) ?></strong>.</p>
    <div class="dashboard-store-link-card card">
        <span class="dashboard-store-link-label">Sua loja está disponível em:</span>
        <div class="dashboard-store-link-row">
            <a href="<?= base_url("loja/{$store['slug']}") ?>" target="_blank" rel="noopener" class="dashboard-store-link-url" id="dashboard-store-url"><?= htmlspecialchars(base_url("loja/{$store['slug']}")) ?></a>
            <button type="button" class="btn btn-secondary btn-sm dashboard-store-link-copy" id="dashboard-copy-url" title="Copiar link">Copiar</button>
        </div>
    </div>

    <div id="dashboard-low-stock-alert" class="dashboard-low-stock-alert hidden">
        <h2>Estoque baixo</h2>
        <p>Os seguintes produtos estão abaixo do estoque mínimo:</p>
        <ul id="dashboard-low-stock-list"></ul>
        <p><a href="<?= base_url("painel/{$store['slug']}/estoque") ?>" class="btn btn-primary btn-sm">Ir para Estoque</a></p>
    </div>

    <?php if (!empty($panel_readonly)): ?>
    <p class="panel-readonly-badge" style="display:inline-block;margin-top:1rem">Você está em modo apenas visualização. Apenas o gerente pode alterar configurações e confirmar pagamentos.</p>
    <?php else: ?>
    <section class="report-section" style="margin-top:1.5rem">
        <h2>Chave PIX da loja</h2>
        <p class="text-muted">Configure a chave PIX para onde sua loja receberá os pagamentos. O QR Code gerado na finalização da compra usará esta chave.</p>

        <div id="pix-card" class="pix-card card" style="max-width:400px;display:none">
            <p class="pix-card-titular"><strong>Titular:</strong> <span id="pix-card-name"></span></p>
            <p class="pix-card-chave"><strong>Chave PIX:</strong> <span id="pix-card-key"></span></p>
            <button type="button" id="pix-btn-edit" class="btn btn-secondary">Editar</button>
        </div>

        <form id="pix-config-form" style="max-width:400px">
            <label>Tipo da chave</label>
            <select id="pix-key-type" name="pix_key_type">
                <option value="cpf">CPF</option>
                <option value="cnpj">CNPJ</option>
                <option value="email">E-mail</option>
                <option value="telefone">Telefone</option>
                <option value="aleatoria">Chave aleatória</option>
            </select>
            <label>Chave PIX (CPF, CNPJ, e-mail, telefone ou chave aleatória)</label>
            <input type="text" id="pix-key" name="pix_key" placeholder="Ex: 12345678900 ou email@loja.com">
            <label>Nome do titular (opcional)</label>
            <input type="text" id="pix-merchant-name" name="merchant_name" placeholder="Nome que aparece no PIX">
            <label>Cidade (opcional)</label>
            <input type="text" id="pix-merchant-city" name="merchant_city" placeholder="Cidade">
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">Salvar chave PIX</button>
            </div>
        </form>
        <p id="pix-config-msg" style="margin-top:0.5rem;font-size:0.9rem"></p>
    </section>

    <section class="report-section" style="margin-top:1.5rem">
        <h2>Pagamentos PIX pendentes (confirmar manualmente)</h2>
        <div id="pending-payments"></div>
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
    if (!key) { pixCard.style.display = 'none'; pixForm.style.display = 'block'; return; }
    document.getElementById('pix-card-name').textContent = name;
    document.getElementById('pix-card-key').textContent = key;
    pixCard.style.display = 'block';
    pixForm.style.display = 'none';
    pixMsg.textContent = '';
  }
  function showPixForm() {
    pixCard.style.display = 'none';
    pixForm.style.display = 'block';
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
    if (!payload.pix_key) { pixMsg.textContent = 'Informe a chave PIX.'; pixMsg.style.color = 'red'; return; }
    fetch(base + '/api/loja/' + slug + '/pix-config', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function(r){ return r.json(); }).then(function(res){
      if (res.success) {
        showPixCard({ merchant_name: payload.merchant_name, pix_key: payload.pix_key });
      } else {
        pixMsg.textContent = res.error || 'Erro ao salvar.';
        pixMsg.style.color = 'red';
      }
    });
  });

  fetch(base + '/api/loja/' + slug + '/payments/pending').then(function(r){ return r.json(); }).then(function(res){
    var list = res.payments || [];
    var el = document.getElementById('pending-payments');
    if (!list.length) { el.innerHTML = '<p>Nenhum pagamento pendente.</p>'; return; }
    el.innerHTML = list.map(function(p){
      return '<div class="card" style="margin-bottom:0.5rem;padding:0.75rem">Pedido #' + p.order_id + ' — R$ ' + parseFloat(p.amount).toFixed(2) + ' (' + (p.method || '') + ')' +
        ' <button type="button" class="btn btn-sm btn-primary confirm-pix" data-payment-id="' + p.id + '">Confirmar PIX</button></div>';
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
<?php $content = ob_get_clean(); require __DIR__ . '/layout_panel.php'; ?>
