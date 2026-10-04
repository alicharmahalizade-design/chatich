/**
 * Shared settings for the end-to-end suites. Configure with environment variables:
 *
 *   WP_PATH      WordPress root (required)          e.g. /var/www/html
 *   WP_URL       Site URL                           default http://localhost:8080
 *   WP_CLI       WP-CLI command                     default "wp --allow-root"
 *   CHROME_PATH  Chromium binary (optional; otherwise Playwright's own)
 *
 * Run `bash tests/e2e/setup.sh` once against a fresh WordPress + WooCommerce first;
 * it writes state.json with the ids of the test products and pages.
 */
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const W = process.env.WP_PATH;
if (!W) throw new Error('Set WP_PATH to the WordPress root (see tests/e2e/README.md).');
const URL_BASE = (process.env.WP_URL || 'http://localhost:8080').replace(/\/$/, '');
const CLI = process.env.WP_CLI || 'wp --allow-root';
const statePath = path.join(__dirname, 'state.json');
if (!fs.existsSync(statePath)) throw new Error('Run tests/e2e/setup.sh first.');

module.exports = {
  W,
  URL_BASE,
  FIX: path.join(__dirname, 'fixtures'),
  SHOTS: process.env.SHOTS || path.join(__dirname, 'screenshots'),
  CHROME: process.env.CHROME_PATH || '',
  state: JSON.parse(fs.readFileSync(statePath, 'utf8')),
  wp: (cmd) => execSync(`${CLI} --path=${W} ${cmd}`, { encoding: 'utf8' }).trim(),
};
