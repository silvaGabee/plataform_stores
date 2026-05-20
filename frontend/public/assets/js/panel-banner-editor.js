/**
 * Editor de banner da vitrine: recorte panorâmico (3,75:1) com zoom e posição.
 * GIF animado: mantém animação ao exportar (quando possível).
 */
(function (global) {
  'use strict';

  var OUT_W = 1920;
  var OUT_H = 512;
  var ASPECT = OUT_W / OUT_H;

  function BannerCropEditor(opts) {
    this.modal = opts.modal;
    this.viewport = opts.viewport;
    this.img = opts.img;
    this.zoomInput = opts.zoomInput;
    this.zoomVal = opts.zoomVal;
    this.applyBtn = opts.applyBtn;
    this.cancelBtn = opts.cancelBtn;
    this.noticeEl = opts.noticeEl || null;
    this.onApply = opts.onApply || function () {};
    this.onExportError = opts.onExportError || function () {};
    this._zoom = 1;
    this._panX = 0;
    this._panY = 0;
    this._drag = null;
    this._sourceMime = 'image/jpeg';
    this._sourceBlob = null;
    this._sourceName = 'banner.jpg';
    this._bind();
  }

  BannerCropEditor.prototype._bind = function () {
    var self = this;
    var cancelClose = function () {
      self.close();
    };
    if (this.cancelBtn) {
      this.cancelBtn.addEventListener('click', cancelClose);
    }
    if (this.modal) {
      this.modal.querySelectorAll('.js-banner-crop-cancel').forEach(function (btn) {
        if (btn !== self.cancelBtn) btn.addEventListener('click', cancelClose);
      });
      this.modal.addEventListener('click', function (e) {
        if (e.target === self.modal) cancelClose();
      });
    }
    if (this.zoomInput) {
      var syncZoomUi = function () {
        var min = Number(self.zoomInput.min) || 100;
        var max = Number(self.zoomInput.max) || 300;
        var val = Number(self.zoomInput.value);
        self._zoom = val / 100;
        var pct = max > min ? ((val - min) / (max - min)) * 100 : 0;
        self.zoomInput.style.setProperty('--zoom-pct', pct + '%');
        self.zoomInput.setAttribute('aria-valuenow', String(val));
        if (self.zoomVal) self.zoomVal.textContent = val + '%';
        self._render();
      };
      this.zoomInput.addEventListener('input', syncZoomUi);
      syncZoomUi();
    }
    if (this.applyBtn) {
      this.applyBtn.addEventListener('click', function () {
        self._export();
      });
    }
    if (this.viewport) {
      this.viewport.addEventListener('pointerdown', function (e) {
        if (e.button !== 0 || !self.img.src) return;
        self._drag = { x: e.clientX, y: e.clientY, panX: self._panX, panY: self._panY };
        self.viewport.setPointerCapture(e.pointerId);
        e.preventDefault();
      });
      this.viewport.addEventListener('pointermove', function (e) {
        if (!self._drag) return;
        self._panX = self._drag.panX + (e.clientX - self._drag.x);
        self._panY = self._drag.panY + (e.clientY - self._drag.y);
        self._clampPan();
        self._render();
      });
      var endDrag = function () {
        self._drag = null;
      };
      this.viewport.addEventListener('pointerup', endDrag);
      this.viewport.addEventListener('pointercancel', endDrag);
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && self.modal && !self.modal.classList.contains('hidden')) {
        self.close();
      }
    });
  };

  BannerCropEditor.prototype._isGif = function () {
    if (this._sourceMime === 'image/gif') return true;
    if (this._sourceBlob && this._sourceBlob.type === 'image/gif') return true;
    if (this._sourceName && /\.gif$/i.test(this._sourceName)) return true;
    if (this.img && this.img.src && /\.gif(\?|$)/i.test(this.img.src)) return true;
    return false;
  };

  BannerCropEditor.prototype._ensureGifBlob = function (done) {
    var self = this;
    if (this._sourceBlob) {
      done(this._sourceBlob);
      return;
    }
    if (!this.img || !this.img.src) {
      done(null);
      return;
    }
    fetch(this.img.src, { credentials: 'same-origin' })
      .then(function (r) {
        return r.ok ? r.blob() : Promise.reject(new Error('fetch'));
      })
      .then(function (b) {
        self._sourceBlob = b;
        done(b);
      })
      .catch(function () {
        done(null);
      });
  };

  BannerCropEditor.prototype._updateNotice = function () {
    if (!this.noticeEl) return;
    if (this._isGif()) {
      this.noticeEl.textContent =
        'GIF animado: o recorte mantém a animação. Arraste e use o zoom para enquadrar.';
      this.noticeEl.classList.remove('hidden');
    } else {
      this.noticeEl.textContent =
        'Arraste a imagem para reposicionar. Use o zoom para aproximar. A área visível é o que aparece na vitrine.';
      this.noticeEl.classList.remove('hidden');
    }
  };

  BannerCropEditor.prototype.open = function (src, mime, blob, filename) {
    var self = this;
    if (!this.modal || !this.img || !src) return;
    this._sourceMime = mime || 'image/jpeg';
    if (this._sourceMime === 'image/webp') this._sourceMime = 'image/jpeg';
    this._sourceBlob = blob || null;
    this._sourceName = filename || (this._isGif() ? 'banner.gif' : 'banner.jpg');
    if (this._isGif()) {
      this._sourceMime = 'image/gif';
      if (!this._sourceName || !/\.gif$/i.test(this._sourceName)) {
        this._sourceName = 'banner.gif';
      }
    }
    this._zoom = 1;
    this._panX = 0;
    this._panY = 0;
    if (this.zoomInput) {
      this.zoomInput.value = '100';
      this.zoomInput.style.setProperty('--zoom-pct', '0%');
      this.zoomInput.setAttribute('aria-valuenow', '100');
      if (this.zoomVal) this.zoomVal.textContent = '100%';
    }
    this._updateNotice();
    this.modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    this.img.crossOrigin = 'anonymous';
    this.img.onload = function () {
      self._clampPan();
      self._render();
    };
    this.img.onerror = function () {
      self.img.removeAttribute('crossorigin');
      self.img.src = src;
    };
    this.img.src = src;
    if (this.img.complete && this.img.naturalWidth) {
      this._clampPan();
      this._render();
    }
  };

  BannerCropEditor.prototype.close = function () {
    if (!this.modal) return;
    this.modal.classList.add('hidden');
    document.body.style.overflow = '';
    this._drag = null;
    this._sourceBlob = null;
    if (this.img) this.img.removeAttribute('src');
  };

  BannerCropEditor.prototype._cropChanged = function () {
    return this._zoom !== 1 || Math.abs(this._panX) > 0.5 || Math.abs(this._panY) > 0.5;
  };

  BannerCropEditor.prototype._getCropRect = function () {
    var vw = this.viewport.clientWidth;
    var vh = this.viewport.clientHeight;
    var iw = this.img.naturalWidth;
    var ih = this.img.naturalHeight;
    if (!vw || !vh || !iw || !ih) return null;
    var s = this._coverScale();
    var dw = iw * s;
    var dh = ih * s;
    var left = (vw - dw) / 2 + this._panX;
    var top = (vh - dh) / 2 + this._panY;
    return {
      sx: Math.max(0, -left / s),
      sy: Math.max(0, -top / s),
      sw: Math.min(iw, vw / s),
      sh: Math.min(ih, vh / s)
    };
  };

  BannerCropEditor.prototype._coverScale = function () {
    var vw = this.viewport.clientWidth;
    var vh = this.viewport.clientHeight;
    var iw = this.img.naturalWidth;
    var ih = this.img.naturalHeight;
    if (!vw || !vh || !iw || !ih) return 1;
    return Math.max(vw / iw, vh / ih) * this._zoom;
  };

  BannerCropEditor.prototype._clampPan = function () {
    var vw = this.viewport.clientWidth;
    var vh = this.viewport.clientHeight;
    var iw = this.img.naturalWidth;
    var ih = this.img.naturalHeight;
    if (!vw || !vh || !iw || !ih) return;
    var s = this._coverScale();
    var dw = iw * s;
    var dh = ih * s;
    var maxX = Math.max(0, (dw - vw) / 2);
    var maxY = Math.max(0, (dh - vh) / 2);
    this._panX = Math.min(maxX, Math.max(-maxX, this._panX));
    this._panY = Math.min(maxY, Math.max(-maxY, this._panY));
  };

  BannerCropEditor.prototype._render = function () {
    var s = this._coverScale();
    this.img.style.transform =
      'translate(calc(-50% + ' + this._panX + 'px), calc(-50% + ' + this._panY + 'px)) scale(' + s + ')';
  };

  BannerCropEditor.prototype._exportStatic = function () {
    var self = this;
    var crop = this._getCropRect();
    if (!crop) return;
    var canvas = document.createElement('canvas');
    canvas.width = OUT_W;
    canvas.height = OUT_H;
    var ctx = canvas.getContext('2d');
    if (!ctx) return;
    if (this._sourceMime === 'image/jpeg') {
      ctx.fillStyle = '#000';
      ctx.fillRect(0, 0, OUT_W, OUT_H);
    }
    ctx.drawImage(this.img, crop.sx, crop.sy, crop.sw, crop.sh, 0, 0, OUT_W, OUT_H);
    var mime = this._sourceMime === 'image/png' ? 'image/png' : 'image/jpeg';
    var quality = mime === 'image/png' ? undefined : 0.92;
    var ext = mime === 'image/png' ? 'png' : 'jpg';
    canvas.toBlob(
      function (blob) {
        if (!blob) return;
        self.close();
        self.onApply(blob, 'banner.' + ext, mime);
      },
      mime,
      quality
    );
  };

  BannerCropEditor.prototype._export = function () {
    var self = this;

    if (!this._isGif()) {
      this._exportStatic();
      return;
    }

    if (this.applyBtn) {
      this.applyBtn.disabled = true;
      this.applyBtn.textContent = 'A processar GIF…';
    }

    this._ensureGifBlob(function (blob) {
      if (!blob) {
        if (self.applyBtn) {
          self.applyBtn.disabled = false;
          self.applyBtn.textContent = 'Aplicar e guardar';
        }
        self.onExportError('Não foi possível ler o GIF. Tente escolher o ficheiro outra vez.');
        return;
      }

      function finishOriginal() {
        if (self.applyBtn) {
          self.applyBtn.disabled = false;
          self.applyBtn.textContent = 'Aplicar e guardar';
        }
        self.close();
        self.onApply(blob, 'banner.gif', 'image/gif');
      }

      if (!self._cropChanged()) {
        finishOriginal();
        return;
      }

      var crop = self._getCropRect();
      if (!crop) {
        finishOriginal();
        return;
      }

      if (typeof global.BannerGifExport === 'undefined') {
        finishOriginal();
        return;
      }

      global.BannerGifExport.cropAnimatedGif(
        blob,
        crop,
        OUT_W,
        OUT_H,
        function (outBlob) {
          if (self.applyBtn) {
            self.applyBtn.disabled = false;
            self.applyBtn.textContent = 'Aplicar e guardar';
          }
          self.close();
          self.onApply(outBlob, 'banner.gif', 'image/gif');
        },
        function () {
          finishOriginal();
          self.onExportError(
            'Não foi possível recortar o GIF com animação; foi guardada a versão original. Para recortar, use um GIF já no tamanho 1920×512 ou converta para vídeo/MP4.'
          );
        }
      );
    });
  };

  global.BannerCropEditor = BannerCropEditor;
  global.BANNER_CROP_ASPECT = ASPECT;
})(typeof window !== 'undefined' ? window : this);
