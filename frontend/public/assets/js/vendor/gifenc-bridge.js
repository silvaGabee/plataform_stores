/**
 * Expõe gifenc no browser (CommonJS).
 */
(function (global) {
  'use strict';
  var exp = global.exports;
  if (!exp) return;
  var GIFEncoder = exp.GIFEncoder || exp.default;
  if (!GIFEncoder) return;
  global.BannerGifEnc = {
    GIFEncoder: GIFEncoder,
    quantize: exp.quantize,
    applyPalette: exp.applyPalette
  };
})(typeof window !== 'undefined' ? window : this);
