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

    <section class="panel-section-card dashboard-banner-section" id="dashboard-banner-section" aria-labelledby="dashboard-banner-title">
        <div class="panel-section-head">
            <span class="panel-section-icon" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.5"/><circle cx="8.5" cy="10.5" r="1.5" fill="currentColor"/><path d="M21 15l-5-5-4 4-2-2-5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <div class="panel-section-head-text">
                <h2 id="dashboard-banner-title" class="panel-section-title">Banner da vitrine</h2>
                <p class="panel-section-desc">Aparece na página inicial da loja, entre o cabeçalho e o catálogo, para destacar promoções ou novidades.</p>
            </div>
        </div>
        <div class="panel-section-body dashboard-banner-body">
            <div class="dashboard-banner-layout">
                <div class="dashboard-banner-col dashboard-banner-col-visual">
                    <p class="dashboard-banner-col-label">Pré-visualização</p>
                    <div id="dashboard-banner-stage" class="dashboard-banner-stage"<?= empty($panel_readonly) ? '' : ' data-readonly="1"' ?>>
                        <div id="dashboard-banner-placeholder" class="dashboard-banner-empty" role="status">
                            <div class="dashboard-banner-empty-inner">
                                <span class="dashboard-banner-empty-icon" aria-hidden="true">
                                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><rect x="3" y="5" width="18" height="14" rx="2" stroke="currentColor" stroke-width="1.35" opacity="0.35"/><circle cx="8.5" cy="10.5" r="1.5" fill="currentColor" opacity="0.45"/><path d="M21 15l-5-5-4 4-2-2-5 5" stroke="currentColor" stroke-width="1.35" stroke-linecap="round" stroke-linejoin="round" opacity="0.45"/></svg>
                                </span>
                                <p class="dashboard-banner-empty-title">Pré-visualização</p>
                                <p class="dashboard-banner-placeholder-text">A carregar…</p>
                            </div>
                        </div>
                        <figure id="dashboard-banner-preview-wrap" class="dashboard-banner-preview-figure hidden">
                            <img id="dashboard-banner-preview" src="" alt="Pré-visualização do banner da vitrine" class="dashboard-banner-preview-img" width="1200" height="360" decoding="async">
                            <figcaption class="dashboard-banner-preview-caption"><span class="dashboard-banner-preview-badge">Na vitrine</span></figcaption>
                        </figure>
                    </div>
                </div>
                <div class="dashboard-banner-col dashboard-banner-col-actions">
                    <?php if (empty($panel_readonly)): ?>
                    <ul class="dashboard-banner-hints" aria-label="Recomendações">
                        <li>JPG, PNG, GIF ou WebP — largura recomendada <strong>≥ 1100 px</strong></li>
                        <li>Proporção larga (ex.: <strong>3:1</strong>) encaixa bem entre o título e o catálogo</li>
                        <li>Pode <strong>arrastar e largar</strong> a imagem na área à esquerda</li>
                    </ul>
                    <form id="dashboard-banner-form" class="dashboard-banner-form" enctype="multipart/form-data">
                        <p class="dashboard-banner-field-label">Ficheiro</p>
                        <div class="dashboard-banner-drop">
                            <input type="file" id="dashboard-banner-file" name="banner" class="dashboard-banner-file-input" accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp" title="Escolher imagem">
                            <label for="dashboard-banner-file" class="dashboard-banner-drop-label">
                                <span class="dashboard-banner-drop-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" focusable="false"><path d="M12 5v14M5 12h14" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                </span>
                                <span class="dashboard-banner-drop-text">Escolher imagem</span>
                            </label>
                            <span id="dashboard-banner-filename" class="dashboard-banner-filename">Nenhum ficheiro selecionado</span>
                        </div>
                        <div class="dashboard-banner-form-actions">
                            <button type="submit" class="btn btn-primary btn-sm dashboard-banner-btn-save">Guardar na vitrine</button>
                            <button type="button" id="dashboard-banner-remove" class="btn btn-secondary btn-sm dashboard-banner-btn-remove hidden">Remover banner</button>
                        </div>
                    </form>
                    <p id="dashboard-banner-msg" class="panel-form-msg dashboard-banner-msg" role="status" aria-live="polite"></p>
                    <?php else: ?>
                    <p class="dashboard-banner-readonly-note">Só o gerente pode carregar ou remover o banner.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

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
  var panelBannerReadonly = <?= !empty($panel_readonly) ? 'true' : 'false' ?>;
  var base = (document.querySelector('meta[name="base-url"]') || {}).content || '';
  base = base.replace(/\/$/, '');
  var section = document.getElementById('dashboard-banner-section');
  var stage = document.getElementById('dashboard-banner-stage');
  var ph = document.getElementById('dashboard-banner-placeholder');
  var wrap = document.getElementById('dashboard-banner-preview-wrap');
  var img = document.getElementById('dashboard-banner-preview');
  var form = document.getElementById('dashboard-banner-form');
  var fileInput = document.getElementById('dashboard-banner-file');
  var removeBtn = document.getElementById('dashboard-banner-remove');
  var msg = document.getElementById('dashboard-banner-msg');
  var nameEl = document.getElementById('dashboard-banner-filename');

  function setMsg(text, kind) {
    if (!msg) return;
    if (msg._timer) { clearTimeout(msg._timer); msg._timer = null; }
    msg.textContent = text || '';
    msg.classList.remove('is-error', 'is-success');
    if (kind === 'error') msg.classList.add('is-error');
    else if (kind === 'success') msg.classList.add('is-success');
    if (text && kind === 'success') {
      msg._timer = setTimeout(function() {
        msg.textContent = '';
        msg.classList.remove('is-success');
        msg._timer = null;
      }, 4800);
    }
  }

  function setFilenameLabel() {
    if (!nameEl || !fileInput) return;
    var f = fileInput.files && fileInput.files[0];
    nameEl.textContent = f ? f.name : 'Nenhum ficheiro selecionado';
    nameEl.classList.toggle('dashboard-banner-filename--picked', !!(f && f.name));
  }

  function setBannerUi(url) {
    if (!ph || !wrap || !img) return;
    ph.classList.add('hidden');
    ph.classList.remove('dashboard-banner-empty--loading');
    if (section) section.classList.toggle('dashboard-banner-section--has-banner', !!url);
    if (stage) stage.classList.toggle('dashboard-banner-stage--has-image', !!url);
    if (url) {
      img.src = url;
      wrap.classList.remove('hidden');
      if (removeBtn) removeBtn.classList.remove('hidden');
    } else {
      wrap.classList.add('hidden');
      img.removeAttribute('src');
      if (removeBtn) removeBtn.classList.add('hidden');
      ph.classList.remove('hidden');
      var t = ph.querySelector('.dashboard-banner-placeholder-text');
      if (t) {
        t.textContent = panelBannerReadonly
          ? 'Nenhum banner definido para esta loja.'
          : 'Arraste uma imagem para aqui ou use «Escolher imagem» à direita.';
      }
    }
  }

  if (ph) ph.classList.add('dashboard-banner-empty--loading');

  fetch(base + '/api/loja/' + encodeURIComponent(slug) + '/banner', { credentials: 'same-origin' })
    .then(function(r){ return r.json(); })
    .then(function(res){
      if (ph) ph.classList.remove('dashboard-banner-empty--loading');
      if (res.error) {
        if (ph) {
          ph.classList.remove('hidden');
          var t = ph.querySelector('.dashboard-banner-placeholder-text');
          if (t) t.textContent = res.error;
        }
        return;
      }
      if (res.banner_url) setBannerUi(res.banner_url);
      else setBannerUi(null);
    })
    .catch(function(){
      if (ph) {
        ph.classList.remove('dashboard-banner-empty--loading');
        ph.classList.remove('hidden');
        var t = ph.querySelector('.dashboard-banner-placeholder-text');
        if (t) t.textContent = 'Não foi possível carregar o estado do banner.';
      }
    });

  if (!panelBannerReadonly && form && fileInput) {
    fileInput.addEventListener('change', function() {
      setFilenameLabel();
      setMsg('', '');
    });

    function assignBannerFile(file) {
      if (!file || !/^image\//.test(file.type)) {
        setMsg('Use uma imagem (JPG, PNG, GIF ou WebP).', 'error');
        return;
      }
      try {
        var dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
      } catch (err) {
        setMsg('Este navegador não suporta largar ficheiros aqui. Use «Escolher imagem».', 'error');
        return;
      }
      setFilenameLabel();
      setMsg('', '');
    }

    if (stage && stage.getAttribute('data-readonly') !== '1') {
      ['dragenter', 'dragover'].forEach(function(ev) {
        stage.addEventListener(ev, function(e) {
          e.preventDefault();
          e.stopPropagation();
          stage.classList.add('dashboard-banner-stage--dropping');
        });
      });
      stage.addEventListener('dragleave', function(e) {
        if (!stage.contains(e.relatedTarget)) stage.classList.remove('dashboard-banner-stage--dropping');
      });
      stage.addEventListener('drop', function(e) {
        e.preventDefault();
        e.stopPropagation();
        stage.classList.remove('dashboard-banner-stage--dropping');
        var f = e.dataTransfer && e.dataTransfer.files && e.dataTransfer.files[0];
        if (f) assignBannerFile(f);
      });
    }

    form.addEventListener('submit', function(e){
      e.preventDefault();
      if (!fileInput.files || !fileInput.files[0]) {
        setMsg('Escolha ou largue uma imagem primeiro.', 'error');
        return;
      }
      var fd = new FormData();
      fd.append('banner', fileInput.files[0]);
      fetch(base + '/api/loja/' + encodeURIComponent(slug) + '/banner', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res.error) {
            setMsg(res.error, 'error');
            return;
          }
          setMsg('Banner guardado — já está visível na vitrine.', 'success');
          fileInput.value = '';
          setFilenameLabel();
          if (res.banner_url) setBannerUi(res.banner_url);
        })
        .catch(function(){ setMsg('Erro de rede.', 'error'); });
    });
  }
  if (!panelBannerReadonly && removeBtn) {
    removeBtn.addEventListener('click', function(){
      if (!confirm('Remover o banner da vitrine?')) return;
      fetch(base + '/api/loja/' + encodeURIComponent(slug) + '/banner', { method: 'DELETE', credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(res){
          if (res.error) {
            setMsg(res.error, 'error');
            return;
          }
          setMsg('Banner removido.', 'success');
          if (fileInput) fileInput.value = '';
          setFilenameLabel();
          setBannerUi(null);
        })
        .catch(function(){ setMsg('Erro de rede.', 'error'); });
    });
  }
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
