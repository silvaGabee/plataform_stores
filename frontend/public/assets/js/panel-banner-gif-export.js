/**
 * Recorte de GIF animado preservando frames.
 * Preferência: ImageDecoder (frames já compostos). Fallback: omggif + gifenc.
 */
(function (global) {
  'use strict';

  function hasGifEnc() {
    return global.BannerGifEnc && global.BannerGifEnc.GIFEncoder && global.BannerGifEnc.quantize;
  }

  function encodeFramesToGif(frames, outW, outH, loopCount, onDone, onError) {
    if (!hasGifEnc()) {
      onError(new Error('Encoder GIF indisponível.'));
      return;
    }
    try {
      var enc = global.BannerGifEnc.GIFEncoder({ auto: true });
      for (var i = 0; i < frames.length; i++) {
        var fr = frames[i];
        var rgba = fr.rgba;
        var palette = global.BannerGifEnc.quantize(rgba, 256);
        var index = global.BannerGifEnc.applyPalette(rgba, palette);
        var delayMs = fr.delayMs != null ? fr.delayMs : 100;
        var opts = {
          palette: palette,
          delay: delayMs,
          dispose: 2,
          first: i === 0
        };
        if (i === 0 && loopCount != null && loopCount >= 0) {
          opts.repeat = loopCount;
        }
        enc.writeFrame(index, outW, outH, opts);
      }
      enc.finish();
      onDone(new Blob([enc.bytes()], { type: 'image/gif' }));
    } catch (err) {
      onError(err);
    }
  }

  function cropFromImageSources(sources, crop, outW, outH, loopCount, onDone, onError) {
    var outCanvas = document.createElement('canvas');
    outCanvas.width = outW;
    outCanvas.height = outH;
    var outCtx = outCanvas.getContext('2d');
    var frames = [];
    var sx = crop.sx;
    var sy = crop.sy;
    var sw = crop.sw;
    var sh = crop.sh;

    function processIndex(i) {
      if (i >= sources.length) {
        encodeFramesToGif(frames, outW, outH, loopCount, onDone, onError);
        return;
      }
      var src = sources[i];
      var draw = function (bitmapOrImg) {
        try {
          outCtx.clearRect(0, 0, outW, outH);
          outCtx.drawImage(bitmapOrImg, sx, sy, sw, sh, 0, 0, outW, outH);
          frames.push({
            rgba: outCtx.getImageData(0, 0, outW, outH).data,
            delayMs: src.delayMs
          });
          if (bitmapOrImg.close) bitmapOrImg.close();
        } catch (e) {
          onError(e);
          return;
        }
        processIndex(i + 1);
      };
      if (src.bitmap) {
        draw(src.bitmap);
      } else if (src.image) {
        draw(src.image);
      } else {
        onError(new Error('Frame inválido.'));
      }
    }

    processIndex(0);
  }

  function cropWithImageDecoder(blob, crop, outW, outH, onDone, onError) {
    if (typeof ImageDecoder === 'undefined') {
      onError(new Error('ImageDecoder indisponível.'));
      return;
    }
    blob.arrayBuffer().then(function (buf) {
      var decoder = new ImageDecoder({ data: buf, type: 'image/gif' });
      return decoder.tracks.ready.then(function () {
        var track = decoder.tracks.selectedTrack || decoder.tracks[0];
        if (!track) throw new Error('Faixa GIF inválida.');
        var frameCount = track.frameCount;
        if (!frameCount || frameCount < 1) {
          throw new Error('GIF sem frames.');
        }
        var loopCount = null;
        var sources = [];
        var chain = Promise.resolve();
        for (var i = 0; i < frameCount; i++) {
          (function (idx) {
            chain = chain.then(function () {
              return decoder.decode({ frameIndex: idx }).then(function (result) {
                var durationUs = result.image.duration;
                var delayMs = durationUs && durationUs > 0 ? Math.max(20, durationUs / 1000) : 100;
                sources.push({ bitmap: result.image, delayMs: delayMs });
              });
            });
          })(i);
        }
        return chain.then(function () {
          cropFromImageSources(sources, crop, outW, outH, loopCount, onDone, onError);
        });
      });
    }).catch(function (err) {
      onError(err);
    });
  }

  function cropWithOmggif(blob, crop, outW, outH, onDone, onError) {
    if (typeof GifReader === 'undefined') {
      onError(new Error('GifReader indisponível.'));
      return;
    }
    var reader = new FileReader();
    reader.onload = function () {
      try {
        var bytes = new Uint8Array(reader.result);
        var gif = new GifReader(bytes);
        var gw = gif.width;
        var gh = gif.height;
        var n = gif.numFrames();
        if (!n || !gw || !gh) {
          onError(new Error('GIF inválido.'));
          return;
        }
        var loopCount = gif.loopCount();
        var compose = document.createElement('canvas');
        compose.width = gw;
        compose.height = gh;
        var composeCtx = compose.getContext('2d', { willReadFrequently: true });
        var savedBefore = null;
        var sources = [];

        for (var i = 0; i < n; i++) {
          var info = gif.frameInfo(i);
          if (i > 0) {
            var prev = gif.frameInfo(i - 1);
            if (prev.disposal === 2) {
              composeCtx.clearRect(0, 0, gw, gh);
            } else if (prev.disposal === 3 && savedBefore) {
              composeCtx.putImageData(savedBefore, 0, 0);
            }
          } else {
            composeCtx.clearRect(0, 0, gw, gh);
          }

          var buf = composeCtx.getImageData(0, 0, gw, gh);
          gif.decodeAndBlitFrameRGBA(i, buf.data);
          composeCtx.putImageData(buf, 0, 0);
          savedBefore = composeCtx.getImageData(0, 0, gw, gh);

          var delayMs = info.delay != null && info.delay > 0 ? info.delay * 10 : 100;
          var frameCanvas = document.createElement('canvas');
          frameCanvas.width = gw;
          frameCanvas.height = gh;
          frameCanvas.getContext('2d').putImageData(savedBefore, 0, 0);
          sources.push({ image: frameCanvas, delayMs: delayMs });
        }

        cropFromImageSources(sources, crop, outW, outH, loopCount, onDone, onError);
      } catch (err) {
        onError(err);
      }
    };
    reader.onerror = function () {
      onError(new Error('Leitura do GIF falhou.'));
    };
    reader.readAsArrayBuffer(blob);
  }

  function cropAnimatedGif(blob, crop, outW, outH, onDone, onError) {
    if (typeof ImageDecoder !== 'undefined') {
      cropWithImageDecoder(blob, crop, outW, outH, onDone, function () {
        cropWithOmggif(blob, crop, outW, outH, onDone, onError);
      });
      return;
    }
    if (!hasGifEnc() && typeof GifReader === 'undefined') {
      onError(new Error('Bibliotecas GIF indisponíveis.'));
      return;
    }
    cropWithOmggif(blob, crop, outW, outH, onDone, onError);
  }

  global.BannerGifExport = { cropAnimatedGif: cropAnimatedGif };
})(typeof window !== 'undefined' ? window : this);
