(() => {
  'use strict';
  // Turnstile keys are only relevant when Turnstile is the chosen check.
  const captcha = document.getElementById('ofr-captcha');
  if (captcha) {
    const sync = () => document.querySelectorAll('[data-ofr-turnstile]').forEach((el) => { el.hidden = captcha.value !== 'turnstile'; });
    captcha.addEventListener('change', sync); sync();
  }

  const button = document.getElementById('ofr-test');
  const box = document.getElementById('ofr-test-result');
  if (!button || !box || !window.OFRAdmin) return;
  const show = (kind, text) => { box.className = 'ofr-alert ofr-alert--' + kind; box.textContent = text; box.hidden = false; };
  button.addEventListener('click', async () => {
    const body = new FormData();
    body.append('action', 'ofr_test_connection'); body.append('nonce', OFRAdmin.nonce);
    button.disabled = true; show('info', OFRAdmin.checking);
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    try {
      const json = await (await fetch(OFRAdmin.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' })).json();
      show(json.success ? 'ok' : 'error', (json.data && json.data.message) || OFRAdmin.invalid);
    } catch (e) { show('error', e.message || OFRAdmin.invalid); }
    button.disabled = false;
  });
})();
