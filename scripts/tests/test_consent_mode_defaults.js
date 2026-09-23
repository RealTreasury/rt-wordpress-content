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

// url_passthrough appends ad-click identifiers to internal links while
// ad_storage is denied. We run no ads and the policies do not disclose it.
assert.ok(!/url_passthrough/.test(fnBody),
  'url_passthrough must not be enabled: it passes tracking parameters for visitors who declined');

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

// ---- Banner logic (rt_consent_banner) -------------------------------------
// Accept, Reject and a later un-tick in the panel only take effect if the
// banner writes rt_consent, pushes a consent update and, on revoke, expires the
// GA cookies. Run the emitted script against a minimal fake DOM and cookie jar.
const bStart = php.indexOf('function rt_consent_banner()');
assert.notStrictEqual(bStart, -1, 'rt_consent_banner() is missing');
const bBody = php.slice(bStart, php.indexOf('\n}\n', bStart));
const bm = bBody.match(/<script id="rt-consent-banner">([\s\S]*?)<\/script>/);
assert.ok(bm, 'rt-consent-banner script block is missing');
const bannerJs = bm[1].replace(/<\?php echo wp_json_encode\( RT_CONSENT_COOKIE \); \?>/g, '"rt_consent"');
assert.ok(!bannerJs.includes('<?php'), 'unexpected PHP inside the banner script');

function runBanner(initialCookies) {
  const jar = new Map(Object.entries(initialCookies));
  const writes = [];
  const byId = new Map();
  const docListeners = [];
  function makeEl() {
    const el = { attrs: {}, listeners: [], classList: { add() {}, remove() {} }, hidden: false,
      checked: false, parentNode: null };
    el.setAttribute = (k, v) => { el.attrs[k] = v; };
    el.getAttribute = (k) => (k in el.attrs ? el.attrs[k] : null);
    el.addEventListener = (t, fn) => el.listeners.push(fn);
    el.removeChild = () => {};
    Object.defineProperty(el, 'innerHTML', { set(html) {
      for (const [, id] of html.matchAll(/id="([^"]+)"/g)) byId.set(id, makeEl());
    } });
    return el;
  }
  const document = {
    readyState: 'complete',
    body: { appendChild(el) { el.parentNode = document.body; if (el.id) byId.set(el.id, el); } },
    createElement: makeEl,
    getElementById: (id) => byId.get(id) || null,
    addEventListener: (t, fn) => docListeners.push(fn),
  };
  Object.defineProperty(document, 'cookie', {
    get: () => Array.from(jar, ([k, v]) => `${k}=${v}`).join('; '),
    set(str) {
      writes.push(str);
      const [pair, ...attrs] = str.split(';');
      const eq = pair.indexOf('=');
      const name = pair.slice(0, eq).trim();
      if (attrs.some((a) => /expires=Thu, 01 Jan 1970/.test(a) || /max-age=0\b/.test(a))) jar.delete(name);
      else jar.set(name, pair.slice(eq + 1));
    },
  });
  const ctx = {
    document, dataLayer: [], location: { protocol: 'https:', hostname: 'www.realtreasury.com' },
    localStorage: { setItem() {} }, setTimeout: () => {},
  };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(bannerJs, ctx);
  const click = (el, attrs) => el.listeners.forEach((fn) => fn({ target: { getAttribute: (k) => attrs[k] ?? null } }));
  const updates = () => Array.from(ctx.dataLayer).filter((a) => a[0] === 'consent' && a[1] === 'update')
    .map((a) => a[2].analytics_storage);
  return { jar, writes, byId, ctx, click, updates, docListeners };
}

{ // No stored choice: banner shows; Accept stores and grants.
  const b = runBanner({});
  const banner = b.byId.get('cookieBanner');
  assert.ok(banner, 'banner must show when no choice is stored');
  b.click(banner, { 'data-rt-consent': 'accept' });
  assert.strictEqual(b.jar.get('rt_consent'), 'analytics');
  assert.ok(b.writes.some((w) => /^rt_consent=analytics; path=\/; max-age=\d+; SameSite=Lax; Secure$/.test(w)),
    'rt_consent must be written site-wide with max-age, SameSite and Secure: ' + b.writes.join(' | '));
  assert.deepStrictEqual(b.updates(), ['granted']);
}
{ // Reject stores essential, denies, and clears GA cookies but not others.
  const b = runBanner({ _ga: 'GA1.1.1', _ga_ABC: 'GS1', _gid: 'x', other: '1' });
  b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'reject' });
  assert.strictEqual(b.jar.get('rt_consent'), 'essential');
  assert.deepStrictEqual(b.updates(), ['denied']);
  for (const n of ['_ga', '_ga_ABC', '_gid']) assert.ok(!b.jar.has(n), `${n} must be cleared on reject`);
  assert.strictEqual(b.jar.get('other'), '1', 'non-analytics cookies must be left alone');
  assert.ok(b.writes.some((w) => /^_ga=; expires=Thu, 01 Jan 1970[^;]*; path=\/; domain=\.realtreasury\.com$/.test(w)),
    '_ga must also be expired on the registrable domain');
}
{ // Stored choice: no banner; preferences trigger opens panel ticked; un-tick + save revokes.
  const b = runBanner({ rt_consent: 'analytics', _ga: 'GA1.1.1' });
  assert.ok(!b.byId.get('cookieBanner'), 'banner must not show once a choice is stored');
  const trigger = {};
  b.docListeners.forEach((fn) => fn({ preventDefault() {},
    target: { closest: (sel) => (sel === '[data-rt-cookie-preferences]' ? trigger : null) } }));
  const panel = b.byId.get('rtConsentPanel');
  assert.ok(panel && panel.hidden === false, 'preferences trigger must open the panel');
  const box = b.byId.get('rtConsentAnalytics');
  assert.strictEqual(box.checked, true, 'panel must reflect the stored choice');
  box.checked = false;
  b.click(panel, { 'data-rt-panel': 'save' });
  assert.strictEqual(b.jar.get('rt_consent'), 'essential');
  assert.deepStrictEqual(b.updates(), ['denied']);
  assert.ok(!b.jar.has('_ga'), '_ga must be cleared when consent is withdrawn in the panel');
  assert.strictEqual(panel.hidden, true, 'panel must close after saving');
}

console.log(`consent mode defaults: ${cases.length} cookie cases OK; ` +
  `banner accept/reject/revoke OK; preference triggers present; ${webinarFiles.length} files free of youtube.com/embed`);
