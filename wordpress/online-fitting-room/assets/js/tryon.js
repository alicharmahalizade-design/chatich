(() => {
  'use strict';
  const root = document.querySelector('.ofr');
  const cfg = window.OnlineFittingRoom;
  if (!root || !cfg) return;
  const t = cfg.i18n;
  const $ = (s) => root.querySelector(s);
  const ui = {
    dialog: $('.ofr__dialog'), file: $('[data-avatar]'), preview: $('[data-preview]'), previewWrap: $('.ofr__preview'), previewNote: $('[data-preview-note]'), drop: $('.ofr__drop'),
    consent: $('[data-consent]'), start: $('[data-start]'), error: $('.ofr__error'), remaining: $('[data-remaining]'), result: $('[data-result]'),
    status: $('[data-status-text]'), cart: $('[data-cart]'), cartNote: $('[data-cart-note]'), download: $('[data-download]'), login: $('[data-login]')
  };
  const STAGE_STEP = { login: 0, upload: 0, working: 1, result: 2 };
  const MAX_POLLS = 90;

  let product = null, opener = null, objectUrl = '', session = null;
  // The photo to upload once prepared (converted JPEG, or the original for server-side conversion).
  let prepared = null, pick = 0;
  // Every try-on run gets an id; responses that belong to an older run are ignored.
  let run = 0, controller = null, timer = null;
  // Variation chosen on the product page, per product: { id, image }.
  const variations = {};

  const fmt = (n) => { try { return Number(n).toLocaleString(document.documentElement.lang || 'fa-IR'); } catch (_) { return String(n); } };
  const showError = (message) => { ui.error.textContent = message || ''; ui.error.hidden = !message; };
  const showRemaining = (n) => {
    ui.remaining.hidden = n === null || n === undefined;
    if (!ui.remaining.hidden) ui.remaining.textContent = t.remaining.replace('%s', fmt(n));
  };
  const stage = (name, focus) => {
    root.querySelectorAll('.ofr__stage').forEach((el) => el.classList.toggle('is-active', el.dataset.stage === name));
    root.querySelectorAll('.ofr__progress li').forEach((el, i) => {
      el.classList.toggle('is-active', i <= STAGE_STEP[name]);
      if (i === STAGE_STEP[name]) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
    });
    if (focus) { const h = root.querySelector(`[data-stage="${name}"] h3`); if (h) h.focus(); }
  };
  const cancelRun = () => { run++; clearTimeout(timer); if (controller) controller.abort(); controller = null; };
  const reset = () => {
    cancelRun(); pick++; prepared = null; ui.file.value = ''; ui.consent.checked = false; ui.start.disabled = true;
    ui.drop.hidden = false; ui.previewWrap.hidden = true; showError(''); ui.cartNote.hidden = true;
    if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = '';
    stage(session && session.loginRequired ? 'login' : 'upload');
  };

  /* ---------- Server calls ---------- */

  const post = async (data, signal) => {
    let response, json;
    try { response = await fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', signal }); }
    catch (e) { if (e.name === 'AbortError') throw e; throw new Error(t.network); }
    try { json = await response.json(); } catch (_) { throw new Error(t.network); }
    if (!response.ok || !json.success) {
      const error = new Error((json.data && json.data.message) || t.network);
      Object.assign(error, json.data || {});
      throw error;
    }
    return json.data;
  };
  const form = (action, fields) => {
    const body = new FormData(); body.append('action', action);
    if (session) body.append('nonce', session.nonce);
    Object.entries(fields || {}).forEach(([k, v]) => body.append(k, v));
    return body;
  };
  // The page may come from a full-page cache, so the nonce is fetched live.
  const loadSession = async () => {
    session = await post(form('ofr_session', { return: location.href }));
    ui.login.href = session.loginUrl;
    showRemaining(session.remaining);
    return session;
  };
  const call = async (action, fields, signal) => {
    if (!session) await loadSession();
    try { return await post(form(action, fields), signal); }
    catch (e) {
      if (!e.nonce) throw e;
      await loadSession(); // Nonce expired while the modal was open: renew once.
      return post(form(action, fields), signal);
    }
  };

  /* ---------- Photo preparation ---------- */

  const LABELS = { jpeg: 'JPG', png: 'PNG', webp: 'WEBP', gif: 'GIF', bmp: 'BMP', avif: 'AVIF', heic: 'HEIC', tiff: 'TIFF', jxl: 'JPEG XL', psd: 'PSD', ico: 'ICO', raw: 'RAW', svg: 'SVG' };
  const RAW_EXT = /\.(3fr|arw|cr2|crw|dcr|dng|erf|iiq|k25|kdc|mef|mrw|nef|nrw|orf|pef|raf|raw|rw2|sr2|srf|x3f)$/i;

  // Format from the file signature: HEIC and RAW files often have no or a wrong MIME type.
  const sniff = async (file) => {
    const b = new Uint8Array(await file.slice(0, 64).arrayBuffer());
    const at = (o, n) => String.fromCharCode(...b.slice(o, o + n));
    if (b[0] === 0xFF && b[1] === 0xD8 && b[2] === 0xFF) return 'jpeg';
    if (at(0, 4) === '\x89PNG') return 'png';
    if (at(0, 4) === 'GIF8') return 'gif';
    if (at(0, 4) === 'RIFF' && at(8, 4) === 'WEBP') return 'webp';
    if (at(0, 2) === 'BM') return 'bmp';
    if (at(4, 4) === 'ftyp') {
      const brands = at(8, 56);
      if (/avi[fs]/.test(brands)) return 'avif';
      if (brands.includes('crx ')) return 'raw';
      if (/hei[cxms]|hev[cxms]|mif1|msf1/.test(brands)) return 'heic';
    }
    if ((b[0] === 0xFF && b[1] === 0x0A) || at(4, 4) === 'JXL ') return 'jxl';
    if (at(0, 4) === '8BPS') return 'psd';
    if (at(0, 15) === 'FUJIFILMCCD-RAW' || at(0, 4) === 'IIRO' || at(0, 4) === 'IIU\0') return 'raw';
    if (at(0, 4) === 'II*\0' || at(0, 4) === 'MM\0*') return RAW_EXT.test(file.name) ? 'raw' : 'tiff';
    if (b[0] === 0 && b[1] === 0 && b[2] === 1 && b[3] === 0) return 'ico';
    if (/^\s*(<\?xml|<svg)/i.test(at(0, 64))) return 'svg';
    return /^image\//.test(file.type) ? 'other' : '';
  };

  // Native decoding first (createImageBitmap applies EXIF rotation), then <img>,
  // which also covers SVG and, in Safari, HEIC and TIFF.
  const decode = async (blob) => {
    if (window.createImageBitmap) {
      try { return await createImageBitmap(blob, { imageOrientation: 'from-image' }); } catch (_) { /* try <img> */ }
    }
    return new Promise((resolve) => {
      const img = new Image(); const url = URL.createObjectURL(blob);
      img.onload = () => { URL.revokeObjectURL(url); resolve(img.naturalWidth || img.width ? img : null); };
      img.onerror = () => { URL.revokeObjectURL(url); resolve(null); };
      img.src = url;
    });
  };
  const loadScript = (src) => new Promise((resolve, reject) => {
    const tag = document.createElement('script'); tag.src = src; tag.onload = resolve; tag.onerror = reject; document.head.append(tag);
  });
  // iPhone HEIC photos in browsers that cannot open them: the bundled decoder is downloaded only when needed.
  const heicToJpeg = async (file) => {
    try {
      if (!window.heic2any) await loadScript(cfg.heicDecoder);
      const out = await window.heic2any({ blob: file, toType: 'image/jpeg', quality: 0.92 });
      return Array.isArray(out) ? out[0] : out;
    } catch (_) { return null; }
  };
  // Downscale and re-encode: a 12 MB phone photo becomes a few hundred KB.
  const toJpeg = async (image) => {
    const w = image.width || 1024, h = image.height || 1024, scale = Math.min(1, cfg.maxDimension / Math.max(w, h));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(w * scale); canvas.height = Math.round(h * scale);
    const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(image, 0, 0, canvas.width, canvas.height);
    if (image.close) image.close();
    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
    return { blob, fits: scale === 1 };
  };
  /** @return {Promise<{blob?: Blob, preview?: Blob, error?: string}>} */
  const prepare = async (file) => {
    const format = await sniff(file);
    if (!format) return { error: t.invalid };
    let image = await decode(file);
    if (!image && format === 'heic') { const jpeg = await heicToJpeg(file); if (jpeg) image = await decode(jpeg); }
    if (image) {
      try {
        const { blob, fits } = await toJpeg(image);
        if (blob) {
          // An already small JPG is sent as is (the server applies any EXIF rotation); everything else as JPEG on white.
          const keep = fits && blob.size >= file.size && format === 'jpeg' && file.size <= cfg.maxBytes;
          return { blob: keep ? file : blob, preview: blob };
        }
      } catch (_) { /* fall through to server-side conversion */ }
    }
    if (cfg.serverFormats.includes(format)) {
      return file.size > cfg.maxBytes ? { error: t.large } : { blob: file, preview: null };
    }
    if (format === 'other') return { error: t.invalid };
    return { error: t.unsupported.replace('%s', LABELS[format] || format.toUpperCase()) };
  };

  /* ---------- Modal ---------- */

  const focusables = () => [...ui.dialog.querySelectorAll('button, [href], input, [tabindex]:not([tabindex="-1"])')]
    .filter((el) => !el.disabled && el.offsetParent !== null);
  const close = () => {
    root.hidden = true; document.documentElement.classList.remove('ofr-lock'); reset();
    if (opener && document.contains(opener)) opener.focus();
  };
  const open = async (button) => {
    try { product = JSON.parse(button.dataset.product); } catch (_) { return; }
    opener = button;
    const variation = variations[product.id];
    $('[data-product-image]').src = (variation && variation.image) || product.image;
    $('[data-product-title]').textContent = product.title; $('[data-product-price]').textContent = product.price;
    root.hidden = false; document.documentElement.classList.add('ofr-lock'); reset();
    ui.dialog.focus();
    // Only switch to the login step here: a reset would discard a photo picked while this loads.
    try { await loadSession(); if (session.loginRequired) stage('login'); } catch (e) { showError(e.message); }
    const first = focusables()[0]; if (first) first.focus();
  };
  const valid = () => { ui.start.disabled = !(prepared && ui.consent.checked); };

  /* ---------- Cart ---------- */

  const cartForm = () => [...document.querySelectorAll('form.cart')].find((f) =>
    String(f.dataset.product_id) === String(product.id) || f.querySelector(`[name="add-to-cart"][value="${product.id}"]`));
  const setupCart = () => {
    ui.cartNote.hidden = true; ui.cart.removeAttribute('aria-busy');
    const pageForm = cartForm();
    if (pageForm) { ui.cart.textContent = product.type === 'variable' && !variations[product.id] ? t.chooseOption : t.addToCart; ui.cart.href = '#'; ui.cart.dataset.mode = 'form'; }
    else if (product.ajaxAdd && cfg.wcAddToCart) { ui.cart.textContent = t.addToCart; ui.cart.href = product.url; ui.cart.dataset.mode = 'ajax'; }
    else { ui.cart.textContent = product.type === 'variable' ? t.chooseOption : t.viewProduct; ui.cart.href = product.url; ui.cart.dataset.mode = 'link'; }
  };
  const addToCart = async (e) => {
    const mode = ui.cart.dataset.mode;
    if (mode === 'link') return;
    e.preventDefault();
    if (mode === 'form') {
      // Use the product page's own form so the chosen size/colour and quantity apply.
      const pageForm = cartForm(); close();
      const submit = pageForm && pageForm.querySelector('.single_add_to_cart_button');
      pageForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
      if (submit && !submit.classList.contains('disabled') && !(product.type === 'variable' && !variations[product.id])) submit.click();
      else { const field = pageForm.querySelector('select, input:not([type=hidden])'); if (field) field.focus(); }
      return;
    }
    ui.cart.setAttribute('aria-busy', 'true'); ui.cart.textContent = t.adding;
    try {
      const body = new FormData(); body.append('product_id', product.id); body.append('quantity', 1);
      const response = await fetch(cfg.wcAddToCart, { method: 'POST', body, credentials: 'same-origin' });
      const json = await response.json();
      if (!json || json.error) { location.href = (json && json.product_url) || product.url; return; }
      if (window.jQuery) window.jQuery(document.body).trigger('added_to_cart', [json.fragments, json.cart_hash]);
      ui.cartNote.textContent = t.added + ' ';
      const link = document.createElement('a'); link.href = cfg.cartUrl; link.textContent = t.viewCart; ui.cartNote.append(link);
      ui.cartNote.hidden = false;
    } catch (_) { location.href = product.url; return; }
    ui.cart.removeAttribute('aria-busy'); ui.cart.textContent = t.addToCart;
  };

  /* ---------- Try-on ---------- */

  const fail = (id, error) => {
    if (id !== run || error.name === 'AbortError') return;
    stage(error.login ? 'login' : 'upload'); showError(error.login ? '' : error.message); valid();
  };
  const poll = async (id, job, attempts) => {
    if (id !== run) return;
    if (attempts > MAX_POLLS) return fail(id, new Error(t.timeout));
    try {
      const data = await call('ofr_status', { job }, controller.signal);
      if (id !== run) return;
      if (data.status === 'succeeded' && data.output) {
        ui.result.src = data.output; ui.download.href = data.download;
        $('[data-result-product]').textContent = product.title; setupCart(); stage('result', true); return;
      }
      ui.status.textContent = data.status === 'queued' ? t.queued : t.processing;
      timer = setTimeout(() => poll(id, job, attempts + 1), Math.max(2, Number(data.retryAfter) || 2) * 1000);
    } catch (error) { fail(id, error); }
  };
  const begin = async () => {
    if (ui.start.disabled) return;
    cancelRun(); const id = run; controller = new AbortController();
    showError(''); ui.start.disabled = true; ui.status.textContent = t.uploading; stage('working', true);
    const photo = prepared;
    // Converted photos go up as photo.jpg; originals keep their name (a hint for camera RAW files).
    const fields = { product_id: product.id, avatar: photo instanceof File ? photo : new File([photo], 'photo.jpg', { type: 'image/jpeg' }) };
    if (variations[product.id]) fields.variation_id = variations[product.id].id;
    try {
      const data = await call('ofr_start', fields, controller.signal);
      if (id !== run) return;
      showRemaining(data.remaining); ui.status.textContent = t.queued;
      timer = setTimeout(() => poll(id, data.job, 1), Math.max(2, Number(data.retryAfter) || 2) * 1000);
    } catch (error) { fail(id, error); }
  };

  /* ---------- Events ---------- */

  document.addEventListener('click', (e) => { const b = e.target.closest('.ofr-open'); if (b) open(b); });
  root.addEventListener('click', (e) => {
    if (e.target.closest('[data-ofr-close]')) close();
    if (e.target.closest('[data-change]')) ui.file.click();
    if (e.target.closest('[data-again]')) reset();
  });
  document.addEventListener('keydown', (e) => {
    if (root.hidden) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key !== 'Tab') return;
    // Keep keyboard focus inside the dialog.
    const items = focusables(); if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (e.shiftKey && (document.activeElement === first || !ui.dialog.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  ui.consent.addEventListener('change', valid);
  const note = (text) => { ui.previewNote.textContent = text || ''; ui.previewNote.hidden = !text; };
  ui.file.addEventListener('change', async () => {
    const file = ui.file.files[0], token = ++pick; showError(''); prepared = null; valid();
    if (!file) return;
    if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = '';
    ui.preview.removeAttribute('src'); ui.drop.hidden = true; ui.previewWrap.hidden = false; note(t.reading);
    const result = await prepare(file);
    if (token !== pick) return; // Another photo was picked meanwhile.
    if (result.error) { ui.file.value = ''; ui.previewWrap.hidden = true; ui.drop.hidden = false; showError(result.error); return; }
    prepared = result.blob;
    if (result.preview) { objectUrl = URL.createObjectURL(result.preview); ui.preview.src = objectUrl; note(''); } else note(t.noPreview);
    valid();
  });
  ui.start.addEventListener('click', begin);
  ui.cart.addEventListener('click', addToCart);

  // WooCommerce variation forms (jQuery events): try on the chosen colour's image.
  if (window.jQuery) {
    window.jQuery(document.body)
      .on('found_variation', 'form.variations_form', function (event, variation) {
        const id = this.dataset.product_id; if (!id || !variation) return;
        variations[id] = { id: variation.variation_id, image: variation.image && (variation.image.src || variation.image.full_src) };
      })
      .on('reset_data hide_variation', 'form.variations_form', function () { delete variations[this.dataset.product_id]; });
  }
})();
