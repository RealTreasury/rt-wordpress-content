// Pins the Consent Mode v2 defaults in assets/php/functions.php.
//
// The defaults block is part of page HTML that WordPress.com and Cloudflare
// cache for anonymous visitors. If PHP renders analytics_storage from the
// request's rt_consent cookie, the cache hands one visitor's choice to the next
// (an accepter's 'granted' to someone who rejected). So the block must be the
// same bytes for everyone and read the cookie in the browser. This check runs
// the emitted script against several cookie jars and asserts the default.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const file = path.join(__dirname, '..', '..', 'assets', 'php', 'functions.php');
const php = fs.readFileSync(file, 'utf8');

// Priority 1 on wp_head is what puts the defaults ahead of Site Kit's tags.
assert.match(php, /add_action\(\s*'wp_head',\s*'rt_consent_mode_defaults',\s*1\s*\)/,
  'rt_consent_mode_defaults must stay on wp_head priority 1');

const fnStart = php.indexOf('function rt_consent_mode_defaults()');
assert.notStrictEqual(fnStart, -1, 'rt_consent_mode_defaults() is missing');
const fnEnd = php.indexOf('\n}\n', fnStart);
const fnBody = php.slice(fnStart, fnEnd);

assert.ok(!/\$_COOKIE|rt_has_analytics_consent\s*\(/.test(fnBody),
  'the consent default must not be rendered from the request cookie in PHP: ' +
  'cached HTML would carry one visitor\'s consent to another');

const m = fnBody.match(/<script id="rt-consent-defaults">([\s\S]*?)<\/script>/);
assert.ok(m, 'rt-consent-defaults script block is missing');
// The only PHP inside the block is the cookie name.
const js = m[1].replace(/<\?php echo wp_json_encode\( RT_CONSENT_COOKIE \); \?>/g, '"rt_consent"');
assert.ok(!js.includes('<?php'), 'unexpected PHP inside the defaults script: ' + js);

function analyticsDefault(cookie) {
  const ctx = { document: { cookie } };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(js, ctx);
  const entry = Array.from(ctx.dataLayer).find((a) => a[0] === 'consent' && a[1] === 'default');
  assert.ok(entry, 'no consent default pushed');
  assert.strictEqual(entry[2].ad_storage, 'denied');
  return entry[2].analytics_storage;
}

const cases = [
  ['', 'denied'],
  ['rt_consent=essential', 'denied'],
  ['rt_consent=analytics', 'granted'],
  ['_ga=GA1.1.1; rt_consent=analytics; other=1', 'granted'],
  ['not_rt_consent=analytics', 'denied'],
  ['rt_consent=analytics_x', 'denied'],
  ['rt_consent=%E0%A4%A', 'denied'], // malformed escape must not throw
];
for (const [cookie, want] of cases) {
  assert.strictEqual(analyticsDefault(cookie), want, `cookie ${JSON.stringify(cookie)}`);
}

// The Cookie Policy and Privacy Policy both promise a preference control on
// their own page, and the banner script is what makes that control work.
assert.match(php, /closest\('\[data-rt-cookie-preferences\]'\)/,
  'the banner script must open the panel for [data-rt-cookie-preferences]');
const root = path.join(__dirname, '..', '..');
for (const page of ['cookie-policy/index.html', 'privacy-policy/index.html']) {
  const html = fs.readFileSync(path.join(root, page), 'utf8');
  assert.match(html, /data-rt-cookie-preferences/,
    `${page} promises a cookie preferences control but carries no trigger`);
}

// The Cookie Policy says embedded video uses the no-cookie player.
function htmlFiles(dir) {
  return fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
    const full = path.join(dir, e.name);
    if (e.isDirectory()) return htmlFiles(full);
    return /\.(html|ejs)$/.test(e.name) ? [full] : [];
  });
}
const webinarFiles = ['webinars', 'templates'].flatMap((d) => htmlFiles(path.join(root, d)));
for (const f of webinarFiles) {
  assert.ok(!/youtube\.com\/embed\//.test(fs.readFileSync(f, 'utf8')),
    `${path.relative(root, f)} embeds youtube.com/embed/; use youtube-nocookie.com/embed/`);
}

console.log(`consent mode defaults: ${cases.length} cookie cases OK; ` +
  `preference triggers present; ${webinarFiles.length} files free of youtube.com/embed`);
