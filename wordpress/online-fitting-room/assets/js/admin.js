(() => {
  'use strict';
  const cfg = window.OFRAdmin;
  const box = document.getElementById('ofr-test-result');
  const show = (kind, text) => {
    if (!box) return;
    box.className = 'ofr-alert ofr-alert--' + kind; box.textContent = text; box.hidden = false;
    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
  };
  const ajax = async (action, fields) => {
    const body = new FormData();
    body.append('action', action);
    Object.entries(fields).forEach(([k, v]) => body.append(k, v));
    const response = await fetch(cfg.ajaxUrl, { method: 'POST', body, credentials: 'same-origin' });
    return response.json();
  };

  // Show only the fields that belong to the chosen option.
  const syncVisibility = (selectId, attr, value) => {
    const select = document.getElementById(selectId);
    if (!select) return;
    const sync = () => document.querySelectorAll(`[${attr}]`).forEach((el) => { el.hidden = select.value !== value; });
    select.addEventListener('change', sync); sync();
  };
  syncVisibility('ofr-captcha', 'data-ofr-turnstile', 'turnstile');
  syncVisibility('ofr-sms_provider', 'data-ofr-sms-webhook', 'webhook');

  if (!cfg) return;

  // Connection test (settings tab).
  const test = document.getElementById('ofr-test');
  if (test) {
    test.addEventListener('click', async () => {
      test.disabled = true; show('info', cfg.checking);
      try {
        const json = await ajax('ofr_test_connection', { nonce: cfg.nonce });
        show(json.success ? 'ok' : 'error', (json.data && json.data.message) || cfg.invalid);
      } catch (e) { show('error', e.message || cfg.invalid); }
      test.disabled = false;
    });
  }

  // Buttons that run a server action: test SMS/email, refresh balance, download pose files, clear logs.
  document.querySelectorAll('[data-ofr-action]').forEach((button) => {
    button.addEventListener('click', async () => {
      if (button.dataset.confirm && !window.confirm(cfg.confirmClear)) return;
      const label = button.textContent;
      button.disabled = true; button.textContent = cfg.working;
      try {
        const fields = { nonce: cfg.adminNonce };
        if (button.dataset.channel) fields.channel = button.dataset.channel;
        const json = await ajax(button.dataset.ofrAction, fields);
        show(json.success ? 'ok' : 'error', (json.data && json.data.message) || cfg.invalid);
        if (json.success && json.data && json.data.value !== undefined) document.querySelectorAll('[data-ofr-balance]').forEach((el) => { el.textContent = Number(json.data.value).toLocaleString(document.documentElement.lang || 'fa-IR'); });
        if (json.success && json.data && json.data.reload) setTimeout(() => location.reload(), 600);
      } catch (e) { show('error', e.message || cfg.invalid); }
      button.disabled = false; button.textContent = label;
    });
  });

  // Chart tooltips.
  document.querySelectorAll('[data-ofr-chart]').forEach((chart) => {
    const tip = chart.querySelector('.ofr-chart__tip');
    chart.addEventListener('mousemove', (e) => {
      const col = e.target.closest('[data-tip]');
      if (!col) { tip.hidden = true; return; }
      const rect = chart.getBoundingClientRect(), bar = col.getBoundingClientRect();
      tip.textContent = col.dataset.tip; tip.hidden = false;
      tip.style.left = (bar.left + bar.width / 2 - rect.left) + 'px';
      tip.style.top = (e.clientY - rect.top) + 'px';
    });
    chart.addEventListener('mouseleave', () => { tip.hidden = true; });
  });
})();
