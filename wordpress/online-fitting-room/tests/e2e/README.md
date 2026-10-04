# End-to-end tests

Three Playwright suites drive a real WordPress + WooCommerce site in Chromium. The
try-on service is replaced by `mock/ofr-mock.php` (a must-use plugin), which also
simulates an Nginx 413, a security plugin blocking the REST API, an exhausted
credit (HTTP 402) and captures outgoing email/SMS.

| Suite | What it covers |
|---|---|
| `core.js` | try-on flow, EXIF/GPS stripping, proof-of-work, encrypted results, background jobs, resume after reload, drag & drop, paste, 413 retry, refunds, daily limits, mobile |
| `ux.js` | photo guide + viewer, consent link, kept photo (IndexedDB), scan screen, before/after slider, zoom, retry, share, comparison, colour swatches, guest → login invitation, badges, button positions, dark mode, sticky bar |
| `features.js` | REST + admin-ajax fallback, polling cache, webhook, social proof, add-to-cart → real checkout → order meta and ROI, alerts (email + SMS webhook, 402, daily report), pose check, HEIC, logged-in REST, admin tabs, WP-CLI |

## Run

```bash
# WordPress with WooCommerce and this plugin active, served at WP_URL
export WP_PATH=/path/to/wordpress WP_URL=http://localhost:8080
bash tests/e2e/setup.sh        # once: mock, products, pages, state.json
npm install && npm run test:e2e
```

For the pose check, put the MediaPipe files in `wp-content/uploads/ofr-pose`
(settings → «دانلود فایل‌ها روی سرور سایت», or `wp eval 'OFR_Pose::download();'`).
