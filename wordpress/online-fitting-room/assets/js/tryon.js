(() => {
  'use strict';
  const root = document.querySelector('.ofr');
  const cfg = window.OnlineFittingRoom;
  if (!root || !cfg) return;
  const t = cfg.i18n;
  const $ = (s) => root.querySelector(s);
  const toastEl = document.querySelector('[data-ofr-toast]');
  const ui = {
    dialog: $('.ofr__dialog'), file: $('[data-avatar]'), preview: $('[data-preview]'), previewWrap: $('.ofr__preview'), previewNote: $('[data-preview-note]'), drop: $('[data-drop]'),
    consent: $('[data-consent]'), start: $('[data-start]'), error: $('.ofr__error'), remaining: $('[data-remaining]'), result: $('[data-result]'),
    status: $('[data-status-text]'), cart: $('[data-cart]'), cartNote: $('[data-cart-note]'), download: $('[data-download]'), login: $('[data-login]'),
    guide: $('[data-guide]'), guideToggle: $('[data-guide-toggle]'), captcha: $('[data-captcha]'), dropzone: $('[data-dropzone]'),
    price: $('[data-product-price]'), regular: $('[data-product-regular]'),
    toastText: toastEl && toastEl.querySelector('[data-toast-text]'), toastAction: toastEl && toastEl.querySelector('[data-toast-action]')
  };
  const STAGE_STEP = { login: 0, upload: 0, working: 1, result: 2 };
  const MAX_POLLS = 90;
  const STORE = 'ofr_jobs';

  let product = null, opener = null, objectUrl = '', session = null;
  // The photo to upload once prepared: { blob, canvas? } — canvas allows a lighter re-encode after HTTP 413.
  let prepared = null, pick = 0;
  // Try-ons keep running when the modal closes; `current` is the one the modal shows.
  const jobs = new Set();
  let current = null, toastJob = null;
  // Variation chosen on the product page, per product: { id, image, price, regular }.
  const variations = {};

  const isOpen = () => !root.hidden;
  const fmt = (n) => { try { return Number(n).toLocaleString(document.documentElement.lang || 'fa-IR'); } catch (_) { return String(n); } };
  const showError = (message) => { ui.error.textContent = message || ''; ui.error.hidden = !message; };
  const showRemaining = (n) => {
    ui.remaining.hidden = n === null || n === undefined;
    if (!ui.remaining.hidden) ui.remaining.textContent = t.remaining.replace('%s', fmt(n));
  };
  const currentStage = () => { const el = root.querySelector('.ofr__stage.is-active'); return el ? el.dataset.stage : ''; };
  const stage = (name, focus) => {
    root.querySelectorAll('.ofr__stage').forEach((el) => el.classList.toggle('is-active', el.dataset.stage === name));
    root.querySelectorAll('.ofr__progress li').forEach((el, i) => {
      el.classList.toggle('is-active', i <= STAGE_STEP[name]);
      if (i === STAGE_STEP[name]) el.setAttribute('aria-current', 'step'); else el.removeAttribute('aria-current');
    });
    if (focus) { const h = root.querySelector(`[data-stage="${name}"] h3`); if (h) h.focus(); }
  };
  const storage = (fn) => { try { return fn(window.sessionStorage); } catch (_) { return null; } };

  /* ---------- Server calls ---------- */

  // Web servers, firewalls and CDNs answer some failures with an HTML page instead of JSON.
  const httpMessage = (status) => {
    if (status === 413) return t.tooLarge;
    if (status === 429) return t.busy;
    if ([401, 403, 406, 418].includes(status)) return t.blocked;
    if (status >= 500) return t.serverError;
    return t.network;
  };
  const post = async (data, signal) => {
    let response, json = null;
    try { response = await fetch(cfg.ajaxUrl, { method: 'POST', body: data, credentials: 'same-origin', signal }); }
    catch (e) { if (e.name === 'AbortError') throw e; throw new Error(t.network); }
    try { json = await response.json(); } catch (_) { /* not JSON */ }
    if (!json || typeof json !== 'object') throw Object.assign(new Error(httpMessage(response.status)), { status: response.status, raw: true });
    const payload = json.data && typeof json.data === 'object' ? json.data : {};
    if (payload.captcha) applyCaptcha(payload.captcha);
    if (!response.ok || !json.success) throw Object.assign(new Error(payload.message || httpMessage(response.status)), payload, { status: response.status });
    return payload;
  };
  const form = (action, fields) => {
    const body = new FormData(); body.append('action', action);
    if (session) body.append('nonce', session.nonce);
    Object.entries(fields || {}).forEach(([k, v]) => { if (v !== undefined && v !== null) body.append(k, v); });
    return body;
  };
  // The page may come from a full-page cache, so the nonce is fetched live.
  const loadSession = async () => {
    session = await post(form('ofr_session', { return: location.href }));
    if (ui.login) ui.login.href = session.loginUrl;
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

  /* ---------- Bot protection ---------- */

  // SHA-256 (FIPS 180-4) for the built-in proof-of-work; returns the first two words of the digest.
  const K = new Uint32Array([
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5, 0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3,
    0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174, 0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967, 0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13,
    0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85, 0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3, 0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208,
    0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2
  ]);
  const W = new Uint32Array(64);
  const sha256Head = (str) => {
    const len = str.length, total = ((len + 9 + 63) >> 6) << 6, bytes = new Uint8Array(total);
    for (let i = 0; i < len; i++) bytes[i] = str.charCodeAt(i) & 0xff;
    bytes[len] = 0x80;
    const bits = len * 8;
    bytes[total - 4] = bits >>> 24; bytes[total - 3] = bits >>> 16; bytes[total - 2] = bits >>> 8; bytes[total - 1] = bits;
    let h0 = 0x6a09e667, h1 = 0xbb67ae85, h2 = 0x3c6ef372, h3 = 0xa54ff53a, h4 = 0x510e527f, h5 = 0x9b05688c, h6 = 0x1f83d9ab, h7 = 0x5be0cd19;
    for (let off = 0; off < total; off += 64) {
      for (let i = 0; i < 16; i++) { const j = off + i * 4; W[i] = (bytes[j] << 24) | (bytes[j + 1] << 16) | (bytes[j + 2] << 8) | bytes[j + 3]; }
      for (let i = 16; i < 64; i++) {
        const a = W[i - 15], b = W[i - 2];
        const s0 = ((a >>> 7) | (a << 25)) ^ ((a >>> 18) | (a << 14)) ^ (a >>> 3);
        const s1 = ((b >>> 17) | (b << 15)) ^ ((b >>> 19) | (b << 13)) ^ (b >>> 10);
        W[i] = (W[i - 16] + s0 + W[i - 7] + s1) | 0;
      }
      let a = h0, b = h1, c = h2, d = h3, e = h4, f = h5, g = h6, h = h7;
      for (let i = 0; i < 64; i++) {
        const S1 = ((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7));
        const t1 = (h + S1 + ((e & f) ^ (~e & g)) + K[i] + W[i]) | 0;
        const S0 = ((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10));
        const t2 = (S0 + ((a & b) ^ (a & c) ^ (b & c))) | 0;
        h = g; g = f; f = e; e = (d + t1) | 0; d = c; c = b; b = a; a = (t1 + t2) | 0;
      }
      h0 = (h0 + a) | 0; h1 = (h1 + b) | 0; h2 = (h2 + c) | 0; h3 = (h3 + d) | 0; h4 = (h4 + e) | 0; h5 = (h5 + f) | 0; h6 = (h6 + g) | 0; h7 = (h7 + h) | 0;
    }
    return [h0 >>> 0, h1 >>> 0];
  };
  const zeroBits = (h) => (h[0] ? Math.clz32(h[0]) : 32 + Math.clz32(h[1]));
  // Finds n with sha256(challenge + ':' + n) starting with `bits` zero bits; yields to the page every few thousand tries.
  const solve = async (challenge) => {
    const bits = Number(challenge.split('.')[2]) || 16, prefix = challenge + ':';
    for (let n = 0; n < 1e8; n++) {
      if (zeroBits(sha256Head(prefix + n)) >= bits) return String(n);
      if (n % 3000 === 2999) await new Promise((resolve) => setTimeout(resolve, 0));
    }
    throw new Error(t.network);
  };

  let captcha = { mode: 'off' }, pow = null, tsWidget = null, tsToken = '';
  const loadScript = (src) => new Promise((resolve, reject) => {
    const tag = document.createElement('script'); tag.src = src; tag.async = true; tag.onload = resolve; tag.onerror = reject; document.head.append(tag);
  });
  const renderTurnstile = async () => {
    if (!ui.captcha) return;
    ui.captcha.hidden = false;
    try {
      if (!window.turnstile) await loadScript('https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit');
      if (tsWidget !== null) return;
      tsWidget = window.turnstile.render(ui.captcha, {
        sitekey: captcha.siteKey, language: 'fa', appearance: 'interaction-only',
        callback: (token) => { tsToken = token; valid(); },
        'expired-callback': () => { tsToken = ''; valid(); },
        'error-callback': () => { tsToken = ''; valid(); }
      });
    } catch (_) { showError(t.network); }
  };
  const resetTurnstile = () => { tsToken = ''; if (tsWidget !== null && window.turnstile) window.turnstile.reset(tsWidget); valid(); };
  // Every server response hands over the next challenge; the PoW is solved in the background right away.
  function applyCaptcha(config) {
    captcha = config || { mode: 'off' };
    if (captcha.mode === 'pow' && captcha.challenge && (!pow || pow.challenge !== captcha.challenge)) {
      const entry = { challenge: captcha.challenge, nonce: null };
      entry.promise = solve(entry.challenge).then((nonce) => { entry.nonce = nonce; return nonce; });
      entry.promise.catch(() => {});
      pow = entry;
    }
    if (captcha.mode === 'turnstile') renderTurnstile();
    else if (ui.captcha) ui.captcha.hidden = true;
  }
  const captchaFields = async (job) => {
    if (captcha.mode === 'pow' && pow) {
      if (!pow.nonce) { job.status = t.securing; render(job); }
      const entry = pow, nonce = await entry.promise;
      return { pow: entry.challenge, pow_nonce: nonce };
    }
    if (captcha.mode === 'turnstile') return { captcha: tsToken };
    return {};
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
  // iPhone HEIC photos in browsers that cannot open them: the bundled decoder is downloaded only when needed.
  const heicToJpeg = async (file) => {
    try {
      if (!window.heic2any) await loadScript(cfg.heicDecoder);
      const out = await window.heic2any({ blob: file, toType: 'image/jpeg', quality: 0.92 });
      return Array.isArray(out) ? out[0] : out;
    } catch (_) { return null; }
  };

  // The upload size this browser should aim for; lowered for good after a server refused a photo as too large.
  const target = () => {
    const saved = Number((() => { try { return window.localStorage.getItem('ofr_target'); } catch (_) { return 0; } })());
    return Math.max(150 * 1024, Math.min(cfg.uploadTarget, saved || cfg.uploadTarget));
  };
  const lowerTarget = (bytes) => { try { window.localStorage.setItem('ofr_target', String(bytes)); } catch (_) { /* private mode */ } };

  const draw = (source, maxDim) => {
    const w = source.width || 1024, h = source.height || 1024, scale = Math.min(1, maxDim / Math.max(w, h));
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(1, Math.round(w * scale)); canvas.height = Math.max(1, Math.round(h * scale));
    const ctx = canvas.getContext('2d'); ctx.fillStyle = '#fff'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(source, 0, 0, canvas.width, canvas.height);
    return canvas;
  };
  const encode = (canvas, quality) => new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
  /**
   * Re-encodes on a canvas, which drops all metadata (GPS position, device,
   * date) and transparency, lowering quality and then size until the photo fits
   * the upload target — so web servers with a 1 MB body limit accept it.
   */
  const compress = async (source, limit) => {
    let canvas = draw(source, cfg.maxDimension), blob = null;
    for (let round = 0; round < 6; round++) {
      for (const quality of [0.9, 0.84, 0.78, 0.72]) {
        blob = await encode(canvas, quality);
        if (!blob) return null;
        if (blob.size <= limit) return { blob, canvas };
      }
      const next = Math.round(Math.max(canvas.width, canvas.height) * 0.82);
      if (next < 720) break;
      canvas = draw(canvas, next);
    }
    return blob ? { blob, canvas } : null;
  };
  /** @return {Promise<{blob?: Blob, canvas?: HTMLCanvasElement, preview?: Blob, error?: string}>} */
  const prepare = async (file) => {
    const format = await sniff(file);
    if (!format) return { error: t.invalid };
    let image = await decode(file);
    if (!image && format === 'heic') { const jpeg = await heicToJpeg(file); if (jpeg) image = await decode(jpeg); }
    if (image) {
      try {
        const out = await compress(image, target());
        if (image.close) image.close();
        if (out) return { blob: out.blob, canvas: out.canvas, preview: out.blob };
      } catch (_) { /* fall through to server-side conversion */ }
    }
    // The server converts what the browser cannot (and removes metadata there).
    if (cfg.serverFormats.includes(format)) {
      return file.size > cfg.maxBytes ? { error: t.large } : { blob: file, preview: null };
    }
    if (format === 'other') return { error: t.invalid };
    return { error: t.unsupported.replace('%s', LABELS[format] || format.toUpperCase()) };
  };

  /* ---------- Product card ---------- */

  const parsePrice = (html) => {
    if (!html) return null;
    const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
    doc.querySelectorAll('.screen-reader-text').forEach((el) => el.remove());
    const clean = (s) => (s || '').replace(/\s+/g, ' ').trim();
    const del = doc.querySelector('del'), ins = doc.querySelector('ins');
    if (del && ins) return { price: clean(ins.textContent), regular: clean(del.textContent) };
    const price = clean(doc.body.textContent);
    return price ? { price, regular: '' } : null;
  };
  const fillProduct = (p) => {
    const variation = variations[p.id];
    $('[data-product-image]').src = (variation && variation.image) || p.image;
    $('[data-product-title]').textContent = p.title;
    const price = (variation && variation.price) ? variation : p;
    ui.price.textContent = price.price || '';
    ui.regular.textContent = price.regular || '';
    ui.regular.hidden = !price.regular;
  };

  /* ---------- Guide ---------- */

  const showGuide = (open) => {
    if (!ui.guide) return;
    ui.guide.hidden = !open;
    if (ui.guideToggle) ui.guideToggle.setAttribute('aria-expanded', String(open));
  };
  const highlightGuide = () => {
    if (!ui.guide) return;
    showGuide(true);
    const ok = ui.guide.querySelector('.is-ok');
    if (ok) { ok.classList.remove('is-highlight'); void ok.offsetWidth; ok.classList.add('is-highlight'); }
  };

  /* ---------- Modal ---------- */

  const focusables = () => [...ui.dialog.querySelectorAll('button, [href], input, select, textarea, iframe, [tabindex]:not([tabindex="-1"])')]
    .filter((el) => !el.disabled && el.offsetParent !== null);
  const valid = () => { ui.start.disabled = !(prepared && ui.consent.checked && (captcha.mode !== 'turnstile' || tsToken)); };
  const clearPhoto = () => {
    pick++; prepared = null; ui.file.value = '';
    ui.drop.hidden = false; ui.previewWrap.hidden = true; showGuide(true);
    if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = '';
    valid();
  };
  const reset = () => {
    current = null; clearPhoto(); ui.consent.checked = consentRemembered(); showError(''); ui.cartNote.hidden = true; valid();
    stage(session && session.loginRequired ? 'login' : 'upload');
  };
  const consentRemembered = () => storage((s) => s.getItem('ofr_consent') === '1') || false;
  const close = () => {
    if (!isOpen()) return;
    root.hidden = true; hideDrop(); document.documentElement.classList.remove('ofr-lock');
    // A try-on in progress keeps running; the toast tells the shopper when it is ready.
    const running = (job) => job.state === 'starting' || job.state === 'working';
    const pending = current && running(current) ? current : [...jobs].reverse().find((job) => running(job) || (job.state === 'done' && !job.seen));
    current = null;
    if (pending) toast(pending);
    if (opener && document.contains(opener)) opener.focus();
  };
  const open = async (p, button) => {
    product = p; opener = button || null;
    fillProduct(product);
    root.hidden = false; document.documentElement.classList.add('ofr-lock');
    hideToast();
    const running = [...jobs].find((job) => job.product.id === product.id && (job.state === 'starting' || job.state === 'working'));
    reset();
    if (running) { current = running; render(running); }
    ui.dialog.focus();
    // Only switch to the login step here: a reset would discard a photo picked while this loads.
    try { await loadSession(); if (session.loginRequired && !current) stage('login'); } catch (e) { showError(e.message); }
    const first = focusables()[0]; if (first) first.focus();
  };
  const openResult = (job) => {
    product = job.product; fillProduct(product);
    root.hidden = false; document.documentElement.classList.add('ofr-lock'); hideToast();
    reset(); current = job; render(job, true);
  };

  /* ---------- Toast (try-ons running in the background) ---------- */

  const hideToast = () => { if (toastEl) toastEl.hidden = true; toastJob = null; };
  const toast = (job) => {
    if (!toastEl || isOpen()) return;
    toastJob = job;
    toastEl.dataset.state = job.state === 'done' ? 'done' : job.state === 'failed' ? 'failed' : 'working';
    ui.toastText.textContent = job.state === 'done' ? t.bgReady.replace('%s', job.product.title)
      : job.state === 'failed' ? (job.message || t.bgFailed)
        : t.bgWorking.replace('%s', job.product.title);
    ui.toastAction.hidden = job.state !== 'done' && job.state !== 'failed';
    ui.toastAction.textContent = job.state === 'done' ? t.view : t.retry;
    toastEl.hidden = false;
  };
  if (toastEl) {
    toastEl.addEventListener('click', (e) => {
      const job = toastJob;
      if (e.target.closest('[data-toast-close]')) { if (job && job.state === 'done') markSeen(job); hideToast(); return; }
      if (!job || !e.target.closest('[data-toast-action]')) return;
      if (job.state === 'done') openResult(job); else open(job.product);
    });
  }

  /* ---------- Jobs: shown in the modal or, when it is closed, in the toast ---------- */

  const persist = () => storage((s) => s.setItem(STORE, JSON.stringify([...jobs]
    .filter((job) => job.token && (job.state === 'working' || (job.state === 'done' && !job.seen)))
    .map((job) => ({ token: job.token, product: job.product, state: job.state, output: job.output, download: job.download, at: job.at })))));
  const markSeen = (job) => { job.seen = true; persist(); };

  function render(job, focus) {
    if (job !== current || !isOpen()) { if (!isOpen()) toast(job); return; }
    if (job.state === 'starting' || job.state === 'working') {
      ui.status.textContent = job.status || t.processing;
      if (currentStage() !== 'working') stage('working', true);
    } else if (job.state === 'done') {
      ui.result.src = job.output; ui.download.href = job.download;
      $('[data-result-product]').textContent = job.product.title; setupCart(); stage('result', true); markSeen(job);
    } else if (job.state === 'failed') {
      stage(job.login ? 'login' : 'upload', !!focus); showError(job.login ? '' : job.message);
      if (job.photoProblem) highlightGuide();
      valid();
    }
  }
  const finish = (job, error) => {
    if (error && error.name === 'AbortError') return;
    clearTimeout(job.timer);
    if (error) {
      job.state = 'failed'; job.message = error.message; job.login = !!error.login;
      job.photoProblem = [415, 422].includes(error.status);
    } else job.state = 'done';
    persist(); render(job);
  };
  const poll = async (job) => {
    if (job.state !== 'working') return;
    if (++job.attempts > MAX_POLLS) return finish(job, new Error(t.timeout));
    try {
      const data = await call('ofr_status', { job: job.token });
      if (data.status === 'succeeded' && data.output) { job.output = data.output; job.download = data.download; return finish(job); }
      job.status = data.status === 'queued' ? t.queued : t.processing; render(job);
      job.timer = setTimeout(() => poll(job), Math.max(2, Number(data.retryAfter) || 2) * 1000);
    } catch (error) {
      // A lost connection (e.g. a phone switching networks) is retried; a server verdict ends the job.
      const transient = !error.status || (error.raw && error.status >= 500);
      if (transient && !error.terminal && job.attempts < MAX_POLLS) { job.timer = setTimeout(() => poll(job), 5000); return; }
      finish(job, error);
    }
  };
  const begin = async () => {
    if (ui.start.disabled || !prepared) return;
    if (captcha.mode === 'turnstile' && !tsToken) { showError(t.captcha); return; }
    const job = { product, state: 'starting', status: t.preparing, attempts: 0, at: Date.now() };
    jobs.add(job); current = job;
    showError(''); ui.start.disabled = true; render(job);
    const photo = prepared, variation = variations[product.id];
    // Converted photos go up as photo.jpg; originals keep their name (a hint for camera RAW files).
    const asFile = (blob) => (blob instanceof File ? blob : new File([blob], 'photo.jpg', { type: 'image/jpeg' }));
    const send = async (blob) => {
      const fields = Object.assign({ product_id: product.id, avatar: asFile(blob), variation_id: variation ? variation.id : null }, await captchaFields(job));
      job.status = t.uploading; render(job);
      return call('ofr_start', fields);
    };
    try {
      let data;
      try { data = await send(photo.blob); }
      catch (error) {
        // 413: the web server's body limit is lower than expected. Send a lighter version once, and remember the limit.
        if (error.status !== 413 || !photo.canvas || (captcha.mode === 'turnstile' && !error.raw)) throw error;
        const limit = Math.max(150 * 1024, Math.floor(Math.min(photo.blob.size, target()) * 0.55));
        lowerTarget(limit);
        job.status = t.retrying; render(job);
        const lighter = await compress(photo.canvas, limit);
        if (!lighter) throw error;
        data = await send(lighter.blob);
      }
      if (captcha.mode === 'turnstile') resetTurnstile();
      showRemaining(data.remaining);
      job.token = data.job; job.state = 'working'; job.status = t.queued; persist(); render(job);
      job.timer = setTimeout(() => poll(job), Math.max(2, Number(data.retryAfter) || 2) * 1000);
    } catch (error) {
      if (captcha.mode === 'turnstile') resetTurnstile();
      finish(job, error);
    }
  };

  // Try-ons started on a previous page (same tab) carry on here.
  const resume = () => {
    const saved = storage((s) => JSON.parse(s.getItem(STORE) || '[]')) || [];
    saved.forEach((item) => {
      if (!item || !item.token || !item.product || Date.now() - item.at > (item.state === 'done' ? 864e5 : 30 * 6e4)) return;
      const job = Object.assign({ attempts: 0, status: t.processing }, item);
      jobs.add(job);
      if (job.state === 'working') poll(job); else toast(job);
    });
    persist();
    const working = [...jobs].find((job) => job.state === 'working');
    if (working) toast(working);
  };

  /* ---------- Cart ---------- */

  const cartForm = () => [...document.querySelectorAll('form.cart')].find((f) =>
    String(f.dataset.product_id) === String(product.id) || f.querySelector(`[name="add-to-cart"][value="${product.id}"]`));
  function setupCart() {
    ui.cartNote.hidden = true; ui.cart.removeAttribute('aria-busy');
    const pageForm = cartForm();
    if (pageForm) { ui.cart.textContent = product.type === 'variable' && !variations[product.id] ? t.chooseOption : t.addToCart; ui.cart.href = '#'; ui.cart.dataset.mode = 'form'; }
    else if (product.ajaxAdd && cfg.wcAddToCart) { ui.cart.textContent = t.addToCart; ui.cart.href = product.url; ui.cart.dataset.mode = 'ajax'; }
    else { ui.cart.textContent = product.type === 'variable' ? t.chooseOption : t.viewProduct; ui.cart.href = product.url; ui.cart.dataset.mode = 'link'; }
  }
  const addToCart = async (e) => {
    const mode = ui.cart.dataset.mode;
    if (mode === 'link') return;
    e.preventDefault();
    if (mode === 'form') {
      // Use the product page's own form so the chosen size/colour and quantity apply.
      const pageForm = cartForm(); close();
      if (!pageForm) return;
      const submit = pageForm.querySelector('.single_add_to_cart_button');
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

  /* ---------- Choosing a photo: picker, drag & drop, paste ---------- */

  const note = (text) => { ui.previewNote.textContent = text || ''; ui.previewNote.hidden = !text; };
  const handleFile = async (file) => {
    if (!file) return;
    const token = ++pick; showError(''); prepared = null; valid();
    if (objectUrl) URL.revokeObjectURL(objectUrl); objectUrl = '';
    ui.preview.removeAttribute('src'); ui.drop.hidden = true; ui.previewWrap.hidden = false; showGuide(false); note(t.reading);
    const result = await prepare(file);
    if (token !== pick) return; // Another photo was picked meanwhile.
    if (result.error) { ui.file.value = ''; ui.previewWrap.hidden = true; ui.drop.hidden = false; showGuide(true); showError(result.error); return; }
    prepared = { blob: result.blob, canvas: result.canvas || null };
    if (result.preview) { objectUrl = URL.createObjectURL(result.preview); ui.preview.src = objectUrl; note(''); } else note(t.noPreview);
    valid();
  };
  ui.file.addEventListener('change', () => handleFile(ui.file.files[0]));

  const uploadReady = () => isOpen() && currentStage() === 'upload';
  const hasFiles = (e) => e.dataTransfer && [...(e.dataTransfer.types || [])].includes('Files');
  let dragDepth = 0;
  function hideDrop() { dragDepth = 0; if (ui.dropzone) ui.dropzone.hidden = true; ui.drop.classList.remove('is-dragover'); }
  ui.dialog.addEventListener('dragenter', (e) => {
    if (!hasFiles(e) || !uploadReady()) return;
    e.preventDefault(); dragDepth++;
    if (ui.dropzone) { ui.dropzone.hidden = false; ui.dropzone.firstElementChild.textContent = t.drop; }
    ui.drop.classList.add('is-dragover');
  });
  ui.dialog.addEventListener('dragover', (e) => { if (hasFiles(e) && uploadReady()) { e.preventDefault(); e.dataTransfer.dropEffect = 'copy'; } });
  ui.dialog.addEventListener('dragleave', () => { if (--dragDepth <= 0) hideDrop(); });
  ui.dialog.addEventListener('drop', (e) => {
    if (!hasFiles(e)) return;
    e.preventDefault(); hideDrop();
    if (!uploadReady()) return;
    const file = [...e.dataTransfer.files].find((f) => /^image\//.test(f.type) || RAW_EXT.test(f.name) || /\.(heic|heif|avif|jxl|psd|tiff?)$/i.test(f.name)) || e.dataTransfer.files[0];
    if (file) handleFile(file); else showError(t.notImage);
  });
  // While the modal is open a file dropped next to it must not make the browser leave the page.
  ['dragover', 'drop'].forEach((type) => window.addEventListener(type, (e) => { if (isOpen() && hasFiles(e)) e.preventDefault(); }));
  document.addEventListener('paste', (e) => {
    if (!uploadReady() || !e.clipboardData) return;
    const item = [...e.clipboardData.items].find((i) => i.kind === 'file' && /^image\//.test(i.type));
    if (item) { e.preventDefault(); handleFile(item.getAsFile()); }
  });

  /* ---------- Events ---------- */

  document.addEventListener('click', (e) => {
    const b = e.target.closest('.ofr-open');
    if (!b || !b.dataset.product) return;
    let p; try { p = JSON.parse(b.dataset.product); } catch (_) { return; }
    open(p, b);
  });
  root.addEventListener('click', (e) => {
    if (e.target.closest('[data-ofr-close]') || e.target.closest('[data-background]')) close();
    if (e.target.closest('[data-change]')) ui.file.click();
    if (e.target.closest('[data-again]')) reset();
    if (e.target.closest('[data-guide-toggle]')) showGuide(ui.guide && ui.guide.hidden);
  });
  document.addEventListener('keydown', (e) => {
    if (!isOpen()) return;
    if (e.key === 'Escape') { close(); return; }
    if (e.key !== 'Tab') return;
    // Keep keyboard focus inside the dialog.
    const items = focusables(); if (!items.length) return;
    const first = items[0], last = items[items.length - 1];
    if (e.shiftKey && (document.activeElement === first || !ui.dialog.contains(document.activeElement))) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });
  ui.consent.addEventListener('change', () => { storage((s) => (ui.consent.checked ? s.setItem('ofr_consent', '1') : s.removeItem('ofr_consent'))); valid(); });
  ui.start.addEventListener('click', begin);
  ui.cart.addEventListener('click', addToCart);

  // WooCommerce variation forms (jQuery events): try on the chosen colour's image, show its price.
  if (window.jQuery) {
    window.jQuery(document.body)
      .on('found_variation', 'form.variations_form', function (event, variation) {
        const id = this.dataset.product_id; if (!id || !variation) return;
        const price = parsePrice(variation.price_html) || {};
        variations[id] = { id: variation.variation_id, image: variation.image && (variation.image.src || variation.image.full_src), price: price.price || '', regular: price.regular || '' };
        if (isOpen() && product && String(product.id) === String(id)) fillProduct(product);
      })
      .on('reset_data hide_variation', 'form.variations_form', function () {
        delete variations[this.dataset.product_id];
        if (isOpen() && product && String(product.id) === String(this.dataset.product_id)) fillProduct(product);
      });
  }

  resume();
})();
