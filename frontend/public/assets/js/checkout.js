(function () {
  var form = document.getElementById('checkout-form');
  var paymentArea = document.getElementById('payment-area');
  var pixQrContainer = document.getElementById('pix-qr-container');
  var paymentStatus = document.getElementById('payment-status');
  var addressBlock = document.getElementById('checkout-address-block');
  var addressSelect = document.getElementById('checkout-address-select');
  var addressNone = document.getElementById('checkout-address-none');
  var addressForm = document.getElementById('checkout-address-form');
  var saveAddressBtn = document.getElementById('checkout-save-address');

  if (!form) return;
  if (typeof storeSlug === 'undefined') {
    console.error('storeSlug não definido');
    return;
  }
  if (typeof cartData === 'undefined' || !Array.isArray(cartData) || cartData.length === 0) {
    alert('Carrinho vazio. Adicione produtos antes de finalizar.');
    return;
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

  function showAddressBlock(show) {
    if (addressBlock) {
      if (show) addressBlock.classList.remove('hidden'); else addressBlock.classList.add('hidden');
    }
  }

  function loadAddresses(email) {
    if (!addressSelect) return Promise.resolve({ addresses: [] });
    email = (email || '').trim();
    if (!email) {
      addressSelect.innerHTML = '<option value="">Informe o e-mail acima primeiro</option>';
      if (addressNone) addressNone.classList.add('hidden');
      if (addressForm) addressForm.classList.add('hidden');
      return Promise.resolve({ addresses: [] });
    }
    addressSelect.innerHTML = '<option value="">Carregando...</option>';
    if (addressNone) addressNone.classList.add('hidden');
    if (addressForm) addressForm.classList.add('hidden');
    var url = 'api/loja/' + encodeURIComponent(storeSlug) + '/checkout/addresses?email=' + encodeURIComponent(email);
    return api(url).then(function (res) {
      var list = res.addresses || [];
      addressSelect.innerHTML = '';
      if (list.length === 0) {
        addressSelect.innerHTML = '<option value="">Nenhum endereço</option>';
        addressSelect.classList.add('hidden');
        if (addressNone) addressNone.classList.remove('hidden');
        if (addressForm) addressForm.classList.remove('hidden');
      } else {
        addressSelect.classList.remove('hidden');
        if (addressNone) addressNone.classList.add('hidden');
        if (addressForm) addressForm.classList.add('hidden');
        list.forEach(function (addr) {
          var opt = document.createElement('option');
          opt.value = addr.id;
          opt.textContent = [addr.street, addr.number, addr.neighborhood, addr.city].filter(Boolean).join(', ');
          addressSelect.appendChild(opt);
        });
      }
      return res;
    }).catch(function () {
      addressSelect.innerHTML = '<option value="">Erro ao carregar</option>';
      return { addresses: [] };
    });
  }

  if (form.querySelectorAll('input[name="delivery_type"]').length) {
    form.querySelectorAll('input[name="delivery_type"]').forEach(function (radio) {
      radio.addEventListener('change', function () {
        var isEntrega = getDeliveryType() === 'entrega';
        showAddressBlock(isEntrega);
        if (isEntrega) {
          var email = form.customer_email.value.trim();
          loadAddresses(email);
        }
      });
    });
  }

  if (form.customer_email) {
    form.customer_email.addEventListener('blur', function () {
      if (getDeliveryType() === 'entrega') loadAddresses(form.customer_email.value.trim());
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
        saveAddressBtn.disabled = false;
        saveAddressBtn.textContent = 'Salvar endereço e usar';
        if (res.address) {
          lastCreatedAddressId = res.address.id;
          var opt = document.createElement('option');
          opt.value = res.address.id;
          opt.selected = true;
          opt.textContent = [res.address.street, res.address.number, res.address.neighborhood, res.address.city].filter(Boolean).join(', ');
          addressSelect.innerHTML = '';
          addressSelect.appendChild(opt);
          addressSelect.classList.remove('hidden');
          if (addressNone) addressNone.classList.add('hidden');
          addressForm.classList.add('hidden');
        }
      }).catch(function (err) {
        saveAddressBtn.disabled = false;
        saveAddressBtn.textContent = 'Salvar endereço e usar';
        alert('Erro: ' + (err.message || err));
      });
    });
  }

  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var name = form.customer_name.value.trim();
    var email = form.customer_email.value.trim();
    var method = form.payment_method.value;
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
    var btn = form.querySelector('button[type="submit"]');
    var btnText = btn ? btn.textContent : '';
    if (btn) {
      btn.disabled = true;
      btn.textContent = 'Gerando...';
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
        if (btn) { btn.disabled = false; btn.textContent = btnText; }
        return;
      }
      var order = res.order;
      return api('api/loja/' + storeSlug + '/payments', {
        method: 'POST',
        body: JSON.stringify({ order_id: order.id, method: method })
      }).then(function (payRes) {
        if (payRes.error) {
          alert(payRes.error);
          if (btn) { btn.disabled = false; btn.textContent = btnText; }
          return;
        }
        var payment = payRes.payment;
        if (btn) { btn.disabled = false; btn.textContent = btnText; }
        form.classList.add('hidden');
        paymentArea.classList.remove('hidden');
        if (payment.pix_qr_code) {
          var qrSrc = payment.pix_qr_code;
          pixQrContainer.innerHTML = '<img src="' + qrSrc + '" alt="QR Code PIX" style="max-width:220px;height:auto;display:block;margin:0 auto">';
        } else if (method === 'pix' && payment.pix_manual) {
          var m = payment.pix_manual;
          var valorStr = typeof m.valor === 'number' ? 'R$ ' + m.valor.toFixed(2).replace('.', ',') : m.valor;
          pixQrContainer.innerHTML = '<p><strong>PIX por transferência.</strong></p>' +
            '<p><strong>Valor:</strong> ' + valorStr + '</p>' +
            '<p class="checkout-pix-msg">Após pagar será confirmado, automaticamente.</p>';
        } else {
          pixQrContainer.innerHTML = '<p>Pagamento registrado. Aguarde confirmação.</p>';
        }
        if (method === 'pix') {
          paymentStatus.textContent = 'Aguardando pagamento...';
          pollPaymentStatus(payment.id);
        } else {
          paymentStatus.textContent = 'Pagamento registrado. O pedido será processado.';
          clearCartAndRedirect(order.id);
        }
      });
    }).catch(function (err) {
      alert('Erro: ' + (err.message || err));
      if (btn) { btn.disabled = false; btn.textContent = btnText; }
    });
  });

  function clearCartAndRedirect(orderId) {
    api('api/loja/' + storeSlug + '/cart/clear', { method: 'POST' }).catch(function () {});
    try {
      var cart = JSON.parse(sessionStorage.getItem('cart') || '{}');
      if (cart[storeSlug]) delete cart[storeSlug];
      sessionStorage.setItem('cart', JSON.stringify(cart));
    } catch (e) {}
    setTimeout(function () {
      window.location.href = getBaseUrl() + '/loja/' + storeSlug + '/pedido/' + orderId;
    }, orderId ? 1500 : 0);
  }

  function pollPaymentStatus(paymentId) {
    var interval = setInterval(function () {
      api('api/loja/' + storeSlug + '/payments/' + paymentId + '/status').then(function (res) {
        if (res.payment && res.payment.status === 'confirmado') {
          clearInterval(interval);
          paymentStatus.textContent = 'Pagamento confirmado! Redirecionando...';
          var orderId = res.payment.order_id;
          clearCartAndRedirect(orderId);
        }
      }).catch(function () {});
    }, 3000);
  }
})();
