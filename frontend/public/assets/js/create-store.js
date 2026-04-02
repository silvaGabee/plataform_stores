(function () {
  var form = document.querySelector('.create-store-form');
  var back = document.querySelector('.js-create-store-back');
  var dialog = document.getElementById('create-store-unsaved-dialog');
  if (!form || !back || !dialog) return;

  var selector =
    'input[name="store_name"], input[name="category"], input[name="city"], input[name="phone"], input[name="manager_password"]';
  var inputs = form.querySelectorAll(selector);

  function snapshot() {
    var o = {};
    inputs.forEach(function (el) {
      o[el.name] = el.value;
    });
    return o;
  }

  var initial = snapshot();
  var pendingUrl = '';
  var isSubmitting = false;

  function isDirty() {
    var cur = snapshot();
    for (var k in initial) {
      if (Object.prototype.hasOwnProperty.call(initial, k) && initial[k] !== cur[k]) {
        return true;
      }
    }
    return false;
  }

  form.addEventListener('submit', function () {
    isSubmitting = true;
  });

  back.addEventListener('click', function (e) {
    if (isSubmitting || !isDirty()) return;
    e.preventDefault();
    pendingUrl = back.getAttribute('href') || '';
    openDialog();
  });

  window.addEventListener('beforeunload', function (e) {
    if (isSubmitting || !isDirty()) return;
    e.preventDefault();
    e.returnValue = '';
  });

  function openDialog() {
    dialog.hidden = false;
    dialog.setAttribute('aria-hidden', 'false');
    dialog.classList.add('is-open');
    document.body.classList.add('create-store-unsaved-open');
    var btn = dialog.querySelector('.js-create-store-unsaved-discard');
    if (btn) setTimeout(function () { btn.focus(); }, 50);
  }

  function closeDialog() {
    dialog.classList.remove('is-open');
    dialog.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('create-store-unsaved-open');
    setTimeout(function () {
      dialog.hidden = true;
    }, 200);
    pendingUrl = '';
    back.focus();
  }

  dialog.querySelectorAll('.js-create-store-unsaved-cancel').forEach(function (el) {
    el.addEventListener('click', closeDialog);
  });

  var discardBtn = dialog.querySelector('.js-create-store-unsaved-discard');
  if (discardBtn) {
    discardBtn.addEventListener('click', function () {
      var url = pendingUrl || back.getAttribute('href');
      if (!url) return;
      isSubmitting = true;
      window.location.href = url;
    });
  }

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && dialog.classList.contains('is-open')) {
      closeDialog();
    }
  });
})();
