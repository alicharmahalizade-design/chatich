// End-to-end check of the try-on flow against the local WordPress with the mocked API.
const { chromium } = require('playwright');
const { execSync } = require('child_process');
const { W, URL_BASE, FIX, SHOTS, CHROME, state, wp: wpCli } = require('./config');
const fs = require('fs');
const URL = `${URL_BASE}/?post_type=product&p=${state.simple}`;
const wp = wpCli;
const shots = `${SHOTS}/core`; fs.mkdirSync(shots, { recursive: true });
let pass = 0, fail = 0;
const check = (label, cond, extra = '') => { if (cond) { pass++; console.log('  ✓', label, extra); } else { fail++; console.log('  ✗', label, extra); } };

(async () => {
  wp(`eval 'global $wpdb; $wpdb->query("TRUNCATE ".OFR_Quota::table()); foreach (["mock_413","mock_413_hits","mock_posts","mock_mode"] as $o) delete_option($o); update_option("mock_polls_needed", 2);'`);
  const browser = await chromium.launch(CHROME ? { executablePath: CHROME } : {});
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, locale: 'fa-IR' });
  const page = await ctx.newPage();
  const errors = [];
  page.on('pageerror', (e) => errors.push(e.message));
  page.on('console', (m) => { if (m.type() === 'error' && !m.text().startsWith('Failed to load resource') && !m.text().startsWith('INFO:')) errors.push(m.text()); }); // HTTP errors are provoked on purpose.
  let lastStartBody = null;
  page.on('request', (r) => { if (r.method() === 'POST' && r.url().includes('admin-ajax') && (r.postData() || '').includes('ofr_start')) lastStartBody = r.postDataBuffer(); });

  console.log('1. Product page and modal');
  await page.goto(URL);
  check('button rendered', await page.locator('.ofr-open:not(.ofr-open--sticky)').count() === 1);
  await page.click('.ofr-open');
  await page.waitForSelector('.ofr:not([hidden])');
  check('photo guide visible', await page.isVisible('[data-guide]'));
  check('sale price shown without screen-reader clutter', (await page.textContent('[data-product-regular]')).includes('1,200,000') && !(await page.textContent('.ofr__product')).includes('Original'));
  await page.screenshot({ path: `${shots}/1-modal.png` });

  console.log('2. Photo with GPS EXIF, 3.9 MB, 3000x4000');
  await page.setInputFiles('[data-avatar]', `${FIX}/generated/person-gps.jpg`);
  await page.waitForFunction(() => document.querySelector('[data-preview]').src.startsWith('blob:'), null, { timeout: 30000 });
  await page.check('[data-consent]');
  await page.waitForFunction(() => !document.querySelector('[data-start]').disabled);
  await page.screenshot({ path: `${shots}/2-photo.png` });
  await page.click('[data-start]');
  await page.waitForSelector('[data-stage="result"].is-active', { timeout: 60000 });
  const received = fs.readFileSync(`${W}/wp-content/mock-person.bin`);
  check('upload ≤ 900 KB', received.length <= 900 * 1024, `(${Math.round(received.length / 1024)} KB)`);
  check('no EXIF/GPS reached the API', !received.includes('SECRET') && !received.includes('Exif'));
  // A scripted start without a solved challenge must be refused before any paid call.
  const bot = await page.evaluate(async (pid) => {
    const f = (o) => { const b = new FormData(); Object.entries(o).forEach(([k, v]) => b.append(k, v)); return b; };
    const s = await (await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: f({ action: 'ofr_session' }) })).json();
    const r1 = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: f({ action: 'ofr_start', nonce: s.data.nonce, product_id: pid, avatar: new File(['x'], 'a.jpg') }) });
    const r2 = await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: f({ action: 'ofr_start', nonce: s.data.nonce, product_id: pid, pow: s.data.captcha.challenge, pow_nonce: '1', avatar: new File(['x'], 'a.jpg') }) });
    return [r1.status, r2.status, (await r1.json()).data.captchaFailed];
  }, state.simple);
  const posts = Number(wp('option get mock_posts'));
  check('start without / with wrong proof-of-work refused (403)', bot[0] === 403 && bot[1] === 403 && bot[2] === true && posts === 1, JSON.stringify(bot));
  const natural = await page.evaluate(() => document.querySelector('[data-result]').naturalWidth);
  check('encrypted result decrypts and displays', natural === 400, `(${natural}px)`);
  const files = fs.readdirSync(`${W}/wp-content/uploads/ofr-results`).filter((f) => /\.(ofr|png|jpg)$/.test(f));
  check('stored result is encrypted (.ofr)', files.length > 0 && files.every((f) => f.endsWith('.ofr')), files.join(','));
  await page.screenshot({ path: `${shots}/3-result.png` });

  console.log('3. Close during processing → toast → view');
  wp(`option update mock_polls_needed 4`);
  await page.click('[data-again]');
  await page.setInputFiles('[data-avatar]', `${FIX}/generated/person-gps.jpg`);
  await page.waitForFunction(() => !document.querySelector('[data-start]').disabled, null, { timeout: 30000 });
  await page.click('[data-start]');
  await page.waitForSelector('[data-stage="working"].is-active');
  await page.keyboard.press('Escape');
  await page.waitForSelector('[data-ofr-toast]:not([hidden])');
  check('toast while working', (await page.getAttribute('[data-ofr-toast]', 'data-state')) === 'working');
  await page.screenshot({ path: `${shots}/4-toast-working.png` });
  await page.waitForSelector('[data-ofr-toast][data-state="done"]', { timeout: 60000 });
  await page.screenshot({ path: `${shots}/5-toast-ready.png` });
  await page.click('[data-toast-action]');
  await page.waitForSelector('[data-stage="result"].is-active');
  check('result opened from toast', await page.isVisible('[data-result]'));

  console.log('4. Navigate away while processing → resumes on next page');
  await page.click('[data-again]');
  await page.setInputFiles('[data-avatar]', `${FIX}/generated/person-gps.jpg`);
  await page.waitForFunction(() => !document.querySelector('[data-start]').disabled, null, { timeout: 30000 });
  await page.click('[data-start]');
  await page.waitForFunction(() => (sessionStorage.getItem('ofr_jobs') || '').includes('working'), null, { timeout: 30000 });
  await page.reload();
  await page.waitForSelector('[data-ofr-toast][data-state="done"]', { timeout: 60000 });
  check('job resumed after reload and finished', true);
  await page.click('[data-toast-close]');

  console.log('5. Drag & drop and paste');
  await page.click('.ofr-open');
  await page.waitForSelector('[data-stage="upload"].is-active');
  const b64 = fs.readFileSync(`${FIX}/shirt.jpg`).toString('base64');
  await page.evaluate(async (data) => {
    const bin = Uint8Array.from(atob(data), (c) => c.charCodeAt(0));
    const dt = new DataTransfer(); dt.items.add(new File([bin], 'me.jpg', { type: 'image/jpeg' }));
    const target = document.querySelector('[data-drop]');
    for (const type of ['dragenter', 'dragover', 'drop']) target.dispatchEvent(new DragEvent(type, { bubbles: true, cancelable: true, dataTransfer: dt }));
  }, b64);
  await page.waitForFunction(() => document.querySelector('[data-preview]').src.startsWith('blob:'), null, { timeout: 15000 });
  check('dropped photo accepted', true);
  await page.click('[data-change]').catch(() => {});
  await page.keyboard.press('Escape');
  await page.click('.ofr-open');
  await page.evaluate(async (data) => {
    const bin = Uint8Array.from(atob(data), (c) => c.charCodeAt(0));
    const dt = new DataTransfer(); dt.items.add(new File([bin], 'clip.png', { type: 'image/jpeg' }));
    document.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  }, b64);
  await page.waitForFunction(() => document.querySelector('[data-preview]').src.startsWith('blob:'), null, { timeout: 15000 });
  check('pasted photo accepted', true);
  await page.keyboard.press('Escape');

  console.log('6. Nginx-style HTML 413 → automatic lighter retry');
  wp(`eval 'global $wpdb; $wpdb->query("TRUNCATE ".OFR_Quota::table());'`);
  wp(`option update mock_413 300000`); wp('option update mock_polls_needed 2');
  await page.evaluate(() => localStorage.removeItem('ofr_target'));
  await page.click('.ofr-open');
  await page.setInputFiles('[data-avatar]', `${FIX}/generated/person-gps.jpg`);
  await page.waitForFunction(() => !document.querySelector('[data-start]').disabled, null, { timeout: 30000 });
  await page.click('[data-start]');
  await page.waitForSelector('[data-stage="result"].is-active', { timeout: 60000 });
  const hits = Number(wp('option get mock_413_hits'));
  const second = fs.readFileSync(`${W}/wp-content/mock-person.bin`).length;
  check('413 hit then retried and succeeded', hits === 1 && second <= 300000, `(retry ${Math.round(second / 1024)} KB)`);
  check('lower target remembered', Number(await page.evaluate(() => localStorage.getItem('ofr_target'))) <= 300000);
  wp('option delete mock_413');
  await page.keyboard.press('Escape');

  console.log('7. HTML error page message mapping');
  wp(`option update mock_413 1`);
  await page.evaluate(() => localStorage.setItem('ofr_target', '2000000'));
  // Upload a photo the browser cannot shrink below 1 byte: retry also fails → clear Persian message, not "network".
  await page.click('.ofr-open'); await page.click('[data-again]').catch(() => {});
  await page.setInputFiles('[data-avatar]', `${FIX}/shirt.jpg`);
  await page.waitForFunction(() => !document.querySelector('[data-start]').disabled, null, { timeout: 30000 });
  await page.click('[data-start]');
  await page.waitForSelector('.ofr__error:not([hidden])', { timeout: 30000 });
  const msg = await page.textContent('.ofr__error');
  check('413 shows "too large" message', msg.includes('حجم'), `(${msg})`);
  wp('option delete mock_413');
  await page.keyboard.press('Escape');

  console.log('8. Failed try-on gives the allowance back');
  wp(`eval 'global $wpdb; $wpdb->query("TRUNCATE ".OFR_Quota::table());'`);
  wp('option update mock_mode fail');
  await page.reload();
  await Promise.all([page.waitForResponse((r) => /ofr_session|ofr%2Fv1%2Fsession|ofr\/v1\/session/.test((r.request().postData() || '') + r.url())), page.click('.ofr-open')]);
  await page.waitForTimeout(300);
  const before = await page.textContent('[data-remaining]');
  await page.setInputFiles('[data-avatar]', `${FIX}/shirt.jpg`);
  await page.waitForFunction(() => !document.querySelector('[data-start]').disabled, null, { timeout: 30000 });
  await page.click('[data-start]');
  await page.waitForSelector('.ofr__error:not([hidden])', { timeout: 60000 });
  check('failure highlights the photo guide', await page.isVisible('[data-guide].is-highlight'));
  await page.screenshot({ path: `${shots}/6-failed-guide.png` });
  await page.keyboard.press('Escape');
  await Promise.all([page.waitForResponse((r) => /ofr_session|ofr%2Fv1%2Fsession|ofr\/v1\/session/.test((r.request().postData() || '') + r.url())), page.click('.ofr-open')]);
  await page.waitForTimeout(200);
  const after = await page.textContent('[data-remaining]');
  check('allowance refunded after failure', before === after, `(${before} → ${after})`);
  wp('option delete mock_mode');

  console.log('9. Daily per-visitor limit holds');
  wp(`eval 'global $wpdb; $wpdb->query("TRUNCATE ".OFR_Quota::table()); $s=Online_Fitting_Room::settings(); $s["limit_per_user_day"]=2; $s["limit_guest_day"]=2; update_option("ofr_settings",$s);'`);
  await page.keyboard.press('Escape');
  let limited = '';
  for (let i = 0; i < 3; i++) {
    await page.click('.ofr-open'); await page.click('[data-again]').catch(() => {});
    await page.setInputFiles('[data-avatar]', `${FIX}/shirt.jpg`);
    await page.waitForFunction(() => !document.querySelector('[data-start]').disabled, null, { timeout: 30000 });
    await page.click('[data-start]');
    await page.waitForSelector('[data-stage="result"].is-active, .ofr__error:not([hidden])', { timeout: 60000 });
    if (await page.isVisible('.ofr__error:not([hidden])')) limited = await page.textContent('.ofr__error');
    console.log('    try', i + 1, await page.evaluate(() => document.querySelector('.ofr__stage.is-active').dataset.stage), wp(`eval 'global $wpdb; echo $wpdb->get_var("SELECT GROUP_CONCAT(n) FROM ".OFR_Quota::table());'`), wp(`eval 'echo Online_Fitting_Room::settings()["limit_guest_day"];'`));
    await page.keyboard.press('Escape');
  }
  check('third try blocked with personal-quota message', limited.includes('سهم'), `(${limited})`);
  wp(`eval '$s=Online_Fitting_Room::settings(); $s["limit_per_user_day"]=5; $s["limit_guest_day"]=5; update_option("ofr_settings",$s);'`);

  console.log('10. Mobile layout');
  const mobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true, locale: 'fa-IR' });
  const m = await mobile.newPage();
  await m.goto(URL); await m.click('.ofr-open'); await m.waitForSelector('.ofr:not([hidden])');
  await m.screenshot({ path: `${shots}/7-mobile.png` });
  check('drop hint hidden on touch', await m.isHidden('.ofr__drop-hint'));

  check('no JS errors', errors.length === 0, errors.join(' | '));
  await browser.close();
  console.log(`\npassed ${pass}, failed ${fail}`);
  process.exit(fail ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(2); });
