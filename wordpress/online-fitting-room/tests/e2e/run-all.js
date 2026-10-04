// Runs the three suites one after another; exits non-zero if any check fails.
const { spawnSync } = require('child_process');
const path = require('path');
let failed = false;
for (const suite of ['core.js', 'ux.js', 'features.js']) {
  console.log(`\n=== ${suite} ===`);
  const run = spawnSync(process.execPath, [path.join(__dirname, suite)], { stdio: 'inherit', env: process.env });
  if (run.status !== 0) failed = true;
}
process.exit(failed ? 1 : 0);
