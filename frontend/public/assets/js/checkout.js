(function () {
  var form = document.getElementById('checkout-form');
  var paymentArea = document.getElementById('payment-area');
  var pixQrContainer = document.getElementById('pix-qr-container');
  var paymentStatus = document.getElementById('payment-status');
  var addressBlock = document.getElementById('checkout-address-block');
  var addressSelect = document.getElementById('checkout-address-select');
  var addressPicker = document.getElementById('checkout-address-picker');
  var addressEmpty = document.getElementById('checkout-address-empty');
  var addressForm = document.getElementById('checkout-address-form');
  var saveAddressBtn = document.getElementById('checkout-save-address');
  var addAddressBtn = document.getElementById('checkout-add-address');
  var addAddressEmptyBtn = document.getElementById('checkout-add-address-empty');
  var cancelAddressBtn = document.getElementById('checkout-cancel-address');
  var cardBlock = document.getElementById('checkout-card-block');
  var cardHolder = document.getElementById('card-holder');
  var cardNumber = document.getElementById('card-number');
  var cardExpMonth = document.getElementById('card-exp-month');
  var cardExpYear = document.getElementById('card-exp-year');
  var cardCvv = document.getElementById('card-cvv');
  var cardFlipper = document.getElementById('checkout-card-flipper');
  var previewNumber = document.getElementById('card-preview-number');
  var previewName = document.getElementById('card-preview-name');
  var previewExpiry = document.getElementById('card-preview-expiry');
  var previewCvv = document.getElementById('card-preview-cvv');
  var paymentTitle = document.getElementById('checkout-payment-title');
  var paymentDesc = document.getElementById('checkout-payment-desc');
  var paymentBadge = document.getElementById('checkout-payment-badge');
  var paymentStatusWrap = document.getElementById('checkout-payment-status-wrap');
  var paymentSteps = document.querySelector('.checkout-payment-steps');
  var checkoutLead = document.querySelector('.checkout-lead');

  if (!form) return;
  if (typeof storeSlug === 'undefined') {
    console.error('storeSlug não definido');
    return;
  }
  if (typeof cartData === 'undefined' || !Array.isArray(cartData)) {
    window.cartData = [];
  }

  var lastCreatedAddressId = null;

  function getBaseUrl() {
    var base = (typeof window.BASE_URL !== 'undefined' && window.BASE_URL) ? String(window.BASE_URL) : '';
    if (!base) {
      var path = window.location.pathname || '';
      var idx = path.indexOf('/loja/');
      if (idx !== -1) {
        base = window.location.origin + path.substring(0, idx);
      } else {
        base = window.location.origin;
      }
    }
    return base.replace(/\/$/, '');
  }

  function api(path, options) {
    var base = getBaseUrl();
    var url = base ? (base + '/' + path.replace(/^\//, '')) : path;
    return fetch(url, {
      headers: { 'Content-Type': 'application/json' },
      ...options
    }).then(function (r) {
      if (!r.ok) {
        return r.text().then(function (text) {
          try {
            var j = JSON.parse(text);
            throw new Error(j.error || text || 'Erro na requisição');
          } catch (e) {
            if (e.message) throw e;
            throw new Error(text || 'Erro ' + r.status);
          }
        });
      }
      return r.json();
    });
  }

  function getDeliveryType() {
    var radio = form.querySelector('input[name="delivery_type"]:checked');
    return radio ? radio.value : 'retirada';
  }

  function getPaymentMethod() {
    var radio = form.querySelector('input[name="payment_method"]:checked');
    return radio ? radio.value : 'pix';
  }

  function shouldShowCardForm() {
    return getPaymentMethod() === 'cartao' && getDeliveryType() === 'entrega';
  }

  function setCardFieldsRequired(required) {
    if (cardExpMonth) cardExpMonth.required = !!required;
    if (cardExpYear) cardExpYear.required = !!required;
  }

  function updateCardBlockVisibility() {
    if (!cardBlock) return;
    var show = shouldShowCardForm();
    cardBlock.classList.toggle('hidden', !show);
    setCardFieldsRequired(show);
    if (!show) setCardFlipped(false);
    else updateCardPreview();
  }

  function digitsOnly(str) {
    return String(str || '').replace(/\D/g, '');
  }

  function luhnValid(digits) {
    if (!digits || digits.length < 13 || digits.length > 19) return false;
    var sum = 0;
    var parity = digits.length % 2;
    for (var i = 0; i < digits.length; i++) {
      var d = parseInt(digits.charAt(i), 10);
      if (i % 2 === parity) {
        d *= 2;
        if (d > 9) d -= 9;
      }
      sum += d;
    }
    return sum % 10 === 0;
  }

  function formatCardNumberInput(el) {
    if (!el) return;
    var digits = digitsOnly(el.value).slice(0, 19);
    var parts = [];
    for (var i = 0; i < digits.length; i += 4) {
      parts.push(digits.slice(i, i + 4));
    }
    el.value = parts.join(' ');
  }

  function getCardExpiryString() {
    var mm = cardExpMonth ? String(cardExpMonth.value || '').trim() : '';
    var yearVal = cardExpYear ? String(cardExpYear.value || '').trim() : '';
    if (!mm || !yearVal) return '';
    mm = mm.length === 1 ? '0' + mm : mm.slice(0, 2);
    var yearFull = parseInt(yearVal, 10);
    if (!yearFull) return '';
    var yy = String(yearFull % 100).padStart(2, '0');
    return mm + '/' + yy;
  }

  function formatPreviewNumber(digits) {
    if (!digits) return '•••• •••• •••• ••••';
    var out = '';
    for (var i = 0; i < 16; i++) {
      if (i > 0 && i % 4 === 0) out += ' ';
      out += digits.charAt(i) || '•';
    }
    return out;
  }

  function setCardFlipped(flipped) {
    if (cardFlipper) cardFlipper.classList.toggle('is-flipped', !!flipped);
  }

  function updateCardPreview() {
    var digits = cardNumber ? digitsOnly(cardNumber.value) : '';
    if (previewNumber) previewNumber.textContent = formatPreviewNumber(digits);

    var holder = cardHolder ? cardHolder.value.trim() : '';
    if (previewName) previewName.textContent = holder ? holder.toUpperCase() : 'SEU NOME';

    var expiry = getCardExpiryString();
    if (previewExpiry) previewExpiry.textContent = expiry || 'MM/AA';

    var cvv = cardCvv ? digitsOnly(cardCvv.value) : '';
    if (previewCvv) previewCvv.textContent = cvv || '•••';
  }

  function getCardPayload() {
    return {
      holder: cardHolder ? cardHolder.value.trim() : '',
      number: cardNumber ? digitsOnly(cardNumber.value) : '',
      expiry: getCardExpiryString(),
      cvv: cardCvv ? digitsOnly(cardCvv.value) : ''
    };
  }

  function validateCardForm() {
    if (!shouldShowCardForm()) return true;
    var card = getCardPayload();
    if (card.holder.length < 3) {
      alert('Informe o nome impresso no cartão.');
      if (cardHolder) cardHolder.focus();
      return false;
    }
    if (!luhnValid(card.number)) {
      alert('Número do cartão inválido.');
      if (cardNumber) cardNumber.focus();
      return false;
    }
    if (!/^(0[1-9]|1[0-2])\/([0-9]{2})$/.test(card.expiry)) {
      alert('Validade inválida. Informe mês (MM) e ano (AA).');
      if (cardExpMonth) cardExpMonth.focus();
      return false;
    }
    var expParts = card.expiry.split('/');
    var expMonth = parseInt(expParts[0], 10);
    var expYear = 2000 + parseInt(expParts[1], 10);
    var now = new Date();
    var expDate = new Date(expYear, expMonth - 1, 1);
    var thisMonth = new Date(now.getFullYear(), now.getMonth(), 1);
    if (expDate < thisMonth) {
      alert('Cartão expirado.');
      if (cardExpMonth) cardExpMonth.focus();
      return false;
    }
    var cvvLen = card.number.charAt(0) === '3' ? 4 : 3;
    if (card.cvv.length !== cvvLen) {
      alert('CVV inválido.');
      if (cardCvv) cardCvv.focus();
      return false;
    }
    return true;
  }

  function cardBrandLabel(brand) {
    var map = { visa: 'Visa', mastercard: 'Mastercard', amex: 'American Express', elo: 'Elo', hipercard: 'Hipercard', card: 'Cartão' };
    return map[brand] || 'Cartão';
  }

  function setPaymentUiState(state) {
    if (!paymentStatusWrap) return;
    paymentStatusWrap.classList.remove('is-waiting', 'is-success', 'is-error');
    if (state) paymentStatusWrap.classList.add(state);
  }

  function setPaymentBadgeType(type) {
    if (!paymentBadge) return;
    paymentBadge.className = 'checkout-payment-badge checkout-payment-badge--' + type;
    if (type === 'card') paymentBadge.textContent = 'Cartão';
    else if (type === 'ok') paymentBadge.textContent = 'Pago';
    else paymentBadge.innerHTML =
      '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true" focusable="false"><path d="M4 7h16v10H4V7z" stroke="currentColor" stroke-width="1.75"/><path d="M7 10.5h4M7 13.5h2.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg> PIX';
  }

  function togglePaymentSteps(show) {
    if (paymentSteps) paymentSteps.classList.toggle('hidden', !show);
  }

  function renderPixQrImage(src) {
    if (!pixQrContainer) return;
    pixQrContainer.innerHTML =
      '<div class="checkout-pix-frame">' +
      '<div class="checkout-pix-frame-inner">' +
      '<img src="' +
      src +
      '" alt="QR Code PIX" width="240" height="240" decoding="async" referrerpolicy="no-referrer">' +
      '</div></div>';
  }

  function showPaymentPanel() {
    form.classList.add('hidden');
    if (paymentArea) paymentArea.classList.remove('hidden');
    if (checkoutLead) {
      checkoutLead.textContent = 'Pedido registrado. Conclua o pagamento abaixo para finalizar.';
    }
  }

  function setPaymentStatusText(text, state) {
    if (paymentStatus) paymentStatus.textContent = text;
    setPaymentUiState(state || 'waiting');
  }

  function showAddressBlock(show) {
    if (addressBlock) {
      if (show) addressBlock.classList.remove('hidden'); else addressBlock.classList.add('hidden');
    }
  }

  function formatAddressLabel(addr) {
    if (!addr) return '';
    return [addr.street, addr.number, addr.neighborhood, addr.city, addr.state].filter(Boolean).join(', ');
  }

  function clearAddressFormFields() {
    ['addr-street', 'addr-number', 'addr-complement', 'addr-neighborhood', 'addr-city', 'addr-state', 'addr-zipcode'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) el.value = '';
    });
  }

  function showNewAddressForm() {
    if (!addressForm) return;
    addressForm.classList.remove('hidden');
    if (addressEmpty) addressEmpty.classList.add('hidden');
    clearAddressFormFields();
    var first = document.getElementById('addr-street');
    if (first) first.focus();
    addressForm.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function hideNewAddressForm() {
    if (addressForm) addressForm.classList.add('hidden');
  }

  function restoreAddressListAfterCancel() {
    var hasOptions = addressSelect && addressSelect.options.length > 0 && addressSelect.value;
    if (hasOptions) {
      if (addressPicker) addressPicker.classList.remove('hidden');
      if (addressEmpty) addressEmpty.classList.add('hidden');
      return;
    }
    if (addressPicker) addressPicker.classList.add('hidden');
    if (addressEmpty) addressEmpty.classList.remove('hidden');
  }

  function renderAddressUi(list, emailMissing) {
    if (!addressSelect) return;
    if (emailMissing) {
      if (addressPicker) addressPicker.classList.add('hidden');
      if (addressEmpty) addressEmpty.classList.add('hidden');
      hideNewAddressForm();
      addressSelect.innerHTML = '<option value="">Informe o e-mail acima primeiro</option>';
      return;
    }
    addressSelect.innerHTML = '';
    if (!list || list.length === 0) {
      if (addressPicker) addressPicker.classList.add('hidden');
      if (addressEmpty) addressEmpty.classList.remove('hidden');
      hideNewAddressForm();
      return;
    }
    if (addressEmpty) addressEmpty.classList.add('hidden');
    if (addressPicker) addressPicker.classList.remove('hidden');
    list.forEach(function (addr) {
      var opt = document.createElement('option');
      opt.value = addr.id;
      opt.textContent = formatAddressLabel(addr);
      addressSelect.appendChild(opt);
    });
    if (lastCreatedAddressId) {
      addressSelect.value = String(lastCreatedAddressId);
    }
    hideNewAddressForm();
  }

  function loadAddresses(email) {
    if (!addressSelect) return Promise.resolve({ addresses: [] });
    email = (email || '').trim();
    if (!email) {
      renderAddressUi([], true);
      return Promise.resolve({ addresses: [] });
    }
    addressSelect.innerHTML = '<option value="">Carregando endereços...</option>';
    if (addressPicker) addressPicker.classList.remove('hidden');
    if (addressEmpty) addressEmpty.classList.add('hidden');
    hideNewAddressForm();
    var url = 'api/loja/' + encodeURIComponent(storeSlug) + '/checkout/addresses?email=' + encodeURIComponent(email);
    return api(url).then(function (res) {
      var list = res.addresses || [];
      renderAddressUi(list, false);
      return res;
    }).catch(function () {
      addressSelect.innerHTML = '<option value="">Erro ao carregar endereços</option>';
      return { addresses: [] };
    });
  }

  if (form.querySelectorAll('input[name="delivery_type"]').length) {
    form.querySelectorAll('input[name="delivery_type"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        var isEntrega = getDeliveryType() === 'entrega';
        showAddressBlock(isEntrega);
        updateCardBlockVisibility();
        if (isEntrega) {
          var email = form.customer_email.value.trim();
          loadAddresses(email);
        }
      });
    });
  }

  if (form.querySelectorAll('input[name="payment_method"]').length) {
    form.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
      radio.addEventListener('change', updateCardBlockVisibility);
    });
  }

  if (cardNumber) {
    cardNumber.addEventListener('input', function () {
      formatCardNumberInput(cardNumber);
      updateCardPreview();
    });
  }
  if (cardHolder) {
    cardHolder.addEventListener('input', updateCardPreview);
  }
  if (cardExpMonth) {
    cardExpMonth.addEventListener('change', function () {
      updateCardPreview();
      if (cardExpMonth.value && cardExpYear && !cardExpYear.value) cardExpYear.focus();
    });
  }
  if (cardExpYear) {
    cardExpYear.addEventListener('change', function () {
      updateCardPreview();
      if (cardExpYear.value && cardCvv) cardCvv.focus();
    });
  }
  if (cardCvv) {
    cardCvv.addEventListener('input', function () {
      var max = cardNumber && digitsOnly(cardNumber.value).charAt(0) === '3' ? 4 : 3;
      cardCvv.value = digitsOnly(cardCvv.value).slice(0, max);
      updateCardPreview();
    });
    cardCvv.addEventListener('focus', function () { setCardFlipped(true); });
    cardCvv.addEventListener('blur', function () {
      setTimeout(function () {
        if (document.activeElement !== cardCvv) setCardFlipped(false);
      }, 120);
    });
  }

  updateCardBlockVisibility();
  updateCardPreview();

  if (typeof CepLookup !== 'undefined') {
    CepLookup.bind({
      zipcode: '#addr-zipcode',
      street: '#addr-street',
      neighborhood: '#addr-neighborhood',
      city: '#addr-city',
      state: '#addr-state',
      number: '#addr-number',
      status: '#addr-cep-status',
      lookupOnInput: true
    });
  }

  if (form.customer_email) {
    form.customer_email.addEventListener('blur', function () {
      if (getDeliveryType() === 'entrega') loadAddresses(form.customer_email.value.trim());
    });
  }

  function bindAddAddress(btn) {
    if (!btn) return;
    btn.addEventListener('click', function () {
      var email = form.customer_email.value.trim();
      if (!email) {
        alert('Informe o e-mail acima para cadastrar um endereço.');
        form.customer_email.focus();
        return;
      }
      showNewAddressForm();
    });
  }
  bindAddAddress(addAddressBtn);
  bindAddAddress(addAddressEmptyBtn);

  if (cancelAddressBtn) {
    cancelAddressBtn.addEventListener('click', function () {
      hideNewAddressForm();
      restoreAddressListAfterCancel();
    });
  }

  if (saveAddressBtn && addressForm) {
    saveAddressBtn.addEventListener('click', function () {
      var email = form.customer_email.value.trim();
      var name = form.customer_name.value.trim();
      if (!email) {
        alert('Informe o e-mail acima.');
        return;
      }
      var street = document.getElementById('addr-street') && document.getElementById('addr-street').value.trim();
      var number = document.getElementById('addr-number') && document.getElementById('addr-number').value.trim();
      var city = document.getElementById('addr-city') && document.getElementById('addr-city').value.trim();
      var state = document.getElementById('addr-state') && document.getElementById('addr-state').value.trim();
      var zipcode = document.getElementById('addr-zipcode') && document.getElementById('addr-zipcode').value.trim();
      if (!street || !number || !city || !state || !zipcode) {
        alert('Preencha todos os campos obrigatórios do endereço (Rua, Número, Cidade, UF, CEP).');
        return;
      }
      saveAddressBtn.disabled = true;
      var saveLabel = saveAddressBtn.textContent;
      saveAddressBtn.textContent = 'Salvando...';
      var payload = {
        email: email,
        customer_name: name,
        street: street,
        number: number,
        complement: (document.getElementById('addr-complement') && document.getElementById('addr-complement').value.trim()) || '',
        neighborhood: (document.getElementById('addr-neighborhood') && document.getElementById('addr-neighborhood').value.trim()) || '',
        city: city,
        state: state,
        zipcode: zipcode
      };
      api('api/loja/' + storeSlug + '/checkout/addresses', {
        method: 'POST',
        body: JSON.stringify(payload)
      }).then(function (res) {
        if (res.address) {
          lastCreatedAddressId = res.address.id;
        }
        return loadAddresses(email);
      }).then(function () {
        saveAddressBtn.disabled = false;
        saveAddressBtn.textContent = saveLabel || 'Salvar e usar este endereço';
        hideNewAddressForm();
      }).catch(function (err) {
        saveAddressBtn.disabled = false;
        saveAddressBtn.textContent = saveLabel || 'Salvar e usar este endereço';
        alert('Erro: ' + (err.message || err));
      });
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!cartData.length) {
      alert('Carrinho vazio. Adicione produtos antes de finalizar.');
      return;
    }
    var name = form.customer_name.value.trim();
    var email = form.customer_email.value.trim();
    var methodRadio = form.querySelector('input[name="payment_method"]:checked');
    var method = methodRadio ? methodRadio.value : 'pix';
    var deliveryType = getDeliveryType();
    if (!name || !email) {
      alert('Preencha nome e e-mail.');
      return;
    }
    if (deliveryType === 'entrega') {
      var addressId = (addressSelect && addressSelect.value) ? parseInt(addressSelect.value, 10) : lastCreatedAddressId;
      if (!addressId) {
        alert('Para entrega, selecione um endereço ou cadastre um novo.');
        return;
      }
    }
    if (!validateCardForm()) {
      return;
    }
    var btn = form.querySelector('button[type="submit"]');
    var btnLabel = btn ? btn.querySelector('span') : null;
    var btnText = btnLabel ? btnLabel.textContent : (btn ? btn.textContent : '');
    if (btn) {
      btn.disabled = true;
      if (btnLabel) btnLabel.textContent = 'Processando...';
      else btn.textContent = 'Processando...';
    }
    var payload = {
      order_type: 'online',
      customer_name: name,
      customer_email: email,
      delivery_type: deliveryType,
      items: cartData
    };
    if (deliveryType === 'entrega') {
      var aid = (addressSelect && addressSelect.value) ? parseInt(addressSelect.value, 10) : lastCreatedAddressId;
      if (aid) payload.address_id = aid;
    }
    var ordersUrl = getBaseUrl() + '/api/loja/' + storeSlug + '/orders';
    fetch(ordersUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) {
      if (!r.ok) {
        return r.text().then(function (text) {
          try { var j = JSON.parse(text); throw new Error(j.error || text); } catch (e) { if (e.message) throw e; throw new Error(text || 'Erro ' + r.status); }
        });
      }
      return r.json();
    }).then(function (res) {
      if (res.error) {
        alert(res.error);
        if (btn) {
          btn.disabled = false;
          if (btnLabel) btnLabel.textContent = btnText;
          else btn.textContent = btnText;
        }
        return;
      }
      var order = res.order;
      var payBody = { order_id: order.id, method: method };
      if (method === 'cartao' && deliveryType === 'entrega') {
        payBody.card = getCardPayload();
      }
      return api('api/loja/' + storeSlug + '/payments', {
        method: 'POST',
        body: JSON.stringify(payBody)
      }).then(function (payRes) {
        if (payRes.error) {
          alert(payRes.error);
          if (btn) {
          btn.disabled = false;
          if (btnLabel) btnLabel.textContent = btnText;
          else btn.textContent = btnText;
        }
          return;
        }
        var payment = payRes.payment;
        if (btn) {
          btn.disabled = false;
          if (btnLabel) btnLabel.textContent = btnText;
          else btn.textContent = btnText;
        }
        showPaymentPanel();
        if (method === 'cartao' && payment.status === 'confirmado') {
          setPaymentBadgeType('ok');
          togglePaymentSteps(false);
          if (paymentTitle) paymentTitle.textContent = 'Pagamento confirmado';
          if (paymentDesc) paymentDesc.textContent = 'Seu cartão foi processado com sucesso.';
          var brand = cardBrandLabel(payment.card_brand || 'card');
          var last4 = payment.card_last4 || '****';
          if (pixQrContainer) {
            pixQrContainer.innerHTML =
              '<div class="checkout-card-success">' +
              '<div class="checkout-card-success-icon" aria-hidden="true">✓</div>' +
              '<p><strong>' +
              brand +
              '</strong> final <strong>' +
              last4 +
              '</strong></p>' +
              '<p class="checkout-card-success-holder">' +
              (payment.card_holder || '').replace(/</g, '&lt;') +
              '</p></div>';
          }
          setPaymentStatusText('Redirecionando para o pedido…', 'success');
          clearCartAndRedirect(order.id);
        } else if (payment.pix_qr_code) {
          setPaymentBadgeType('pix');
          togglePaymentSteps(true);
          if (paymentTitle) paymentTitle.textContent = 'Pagamento PIX';
          if (paymentDesc) paymentDesc.textContent = 'Escaneie o QR Code no app do seu banco para concluir.';
          renderPixQrImage(payment.pix_qr_code);
          setPaymentStatusText('Aguardando pagamento…', 'waiting');
          pollPaymentStatus(payment.id);
        } else if (method === 'pix' && payment.pix_manual) {
          setPaymentBadgeType('pix');
          togglePaymentSteps(false);
          if (paymentTitle) paymentTitle.textContent = 'Pagamento PIX';
          if (paymentDesc) paymentDesc.textContent = 'Use os dados abaixo para transferir.';
          var m = payment.pix_manual;
          var valorStr = typeof m.valor === 'number' ? 'R$ ' + m.valor.toFixed(2).replace('.', ',') : m.valor;
          if (pixQrContainer) {
            pixQrContainer.innerHTML =
              '<div class="checkout-pix-manual">' +
              '<p class="checkout-pix-manual-label">Valor a pagar</p>' +
              '<p class="checkout-pix-manual-value">' +
              valorStr +
              '</p>' +
              '<p class="checkout-pix-manual-msg">Após a transferência, a confirmação é automática.</p>' +
              '</div>';
          }
          setPaymentStatusText('Aguardando pagamento…', 'waiting');
          pollPaymentStatus(payment.id);
        } else {
          setPaymentBadgeType('ok');
          togglePaymentSteps(false);
          if (paymentTitle) paymentTitle.textContent = 'Pedido registrado';
          if (paymentDesc) paymentDesc.textContent = 'O pagamento será confirmado em breve.';
          if (pixQrContainer) {
            pixQrContainer.innerHTML =
              '<div class="checkout-pix-manual"><p>Pagamento registrado. Aguarde a confirmação da loja.</p></div>';
          }
          setPaymentStatusText('Pedido em processamento', 'success');
          clearCartAndRedirect(order.id);
        }
      });
    }).catch(function (err) {
      alert('Erro: ' + (err.message || err));
      if (btn) { btn.disabled = false; btn.textContent = btnText; }
    });
  });

  function clearLocalCart() {
    try {
      var cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
      var sid = typeof storeId !== 'undefined' ? storeId : null;
      if (sid !== null && sid !== undefined) {
        delete cart[sid];
        delete cart[String(sid)];
      }
      if (storeSlug) delete cart[storeSlug];
      sessionStorage.setItem('cart', JSON.stringify(cart));
    } catch (e) {}
  }

  function clearCartAndRedirect(orderId) {
    var redirect = function () {
      clearLocalCart();
      if (orderId) {
        window.location.href = getBaseUrl() + '/loja/' + storeSlug + '/pedido/' + orderId;
      }
    };
    api('api/loja/' + storeSlug + '/cart/clear', { method: 'POST' })
      .then(function () { redirect(); })
      .catch(function () { redirect(); });
  }

  function pollPaymentStatus(paymentId) {
    var interval = setInterval(function () {
      api('api/loja/' + storeSlug + '/payments/' + paymentId + '/status').then(function (res) {
        if (res.payment && res.payment.status === 'confirmado') {
          clearInterval(interval);
          setPaymentBadgeType('ok');
          setPaymentStatusText('Pagamento confirmado! Redirecionando…', 'success');
          var orderId = res.payment.order_id;
          clearCartAndRedirect(orderId);
        }
      }).catch(function () {});
    }, 3000);
  }
})();
