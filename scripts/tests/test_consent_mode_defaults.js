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
// ad_storage is denied. The policies do not disclose it, and the LinkedIn ad
// tag is gated on analytics consent rather than ad_storage.
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

// The guide feedback email's star links carry ?email=...; GA4 must be given that
// page without its query. Other pages keep their full location.
function pageLocationSet(href) {
  const u = new URL(href);
  const ctx = { document: { cookie: '' },
    location: { href: u.href, origin: u.origin, pathname: u.pathname, search: u.search, hash: u.hash } };
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(js, ctx);
  const sets = Array.from(ctx.dataLayer).filter((a) => a[0] === 'set' && a[1] === 'page_location');
  assert.ok(sets.length <= 1, 'page_location set more than once');
  return sets.length ? sets[0][2] : null;
}
for (const p of ['/treasury-tech-selection-guide/feedback/', '/treasury-tech-selection-guide/feedback']) {
  const got = pageLocationSet('https://realtreasury.com' + p + '?email=jo%2Bguide%40corp.example&r=4#x');
  assert.strictEqual(got, 'https://realtreasury.com' + p, `feedback page_location must drop the query: ${got}`);
  assert.ok(!/email|@|%40/.test(got), 'page_location must not carry the address');
}
for (const p of ['/', '/treasury-tech-selection-guide/', '/contact/?email=a%40b.example', '/blog/feedback/?email=x']) {
  assert.strictEqual(pageLocationSet('https://realtreasury.com' + p), null,
    `page_location must not be overridden on ${p}`);
}

// The feedback page reads email/r once, then strips the query from the address bar.
{
  const html = fs.readFileSync(path.join(__dirname, '..', '..',
    'treasury-tech-selection', 'guidebook', 'feedback', 'wordpress-page.html'), 'utf8');
  const fm = html.match(/<script>\s*(\(function \(\) \{[\s\S]*?\}\)\(\);)\s*<\/script>/);
  assert.ok(fm, 'feedback page script is missing');
  function runFeedback(search) {
    const replaced = [];
    const posts = [];
    function el() {
      return { hidden: false, value: '', disabled: false, textContent: '', className: '',
        classList: { add() {}, remove() {}, toggle() {} }, addEventListener() {}, appendChild() {},
        setAttribute() {}, querySelector() { return el(); }, focus() {} };
    }
    const byId = new Map();
    const form = el();
    const group = el();
    group.getAttribute = () => 'useful';
    form.querySelectorAll = () => [group];
    byId.set('rtGbfbForm', form);
    const document = {
      visibilityState: 'visible',
      getElementById: (id) => { if (!byId.has(id)) byId.set(id, el()); return byId.get(id); },
      createElement: el,
      addEventListener() {}, removeEventListener() {},
    };
    const location = { origin: 'https://realtreasury.com', pathname: '/treasury-tech-selection-guide/feedback/',
      search, hash: '#top' };
    const ctx = {
      document, location, URLSearchParams, JSON, Number, Math,
      history: { replaceState: (s, t, url) => replaced.push(url) },
      fetch: (u, o) => { posts.push(JSON.parse(o.body)); return Promise.resolve({ ok: true }); },
    };
    ctx.window = ctx;
    vm.createContext(ctx);
    vm.runInContext(fm[1], ctx);
    return { replaced, emailValue: byId.get('rtGbfbEmail').value, posts };
  }
  const withEmail = runFeedback('?email=jo+guide%40corp.example&r=4');
  assert.deepStrictEqual(withEmail.replaced, ['/treasury-tech-selection-guide/feedback/#top'],
    'feedback page must replace the URL with path + hash only');
  assert.strictEqual(withEmail.emailValue, 'jo+guide@corp.example', 'address must still be read before stripping');
  assert.strictEqual(runFeedback('').replaced.length, 0, 'no replaceState without an email param');
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

function runBanner(initialCookies, globals = {}) {
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
    Object.defineProperty(el, 'innerHTML', { get() { return el.html || ''; }, set(html) {
      el.html = html;
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
    document, dataLayer: [], location: { protocol: 'https:', hostname: 'www.realtreasury.com', pathname: '/' },
    localStorage: { setItem() {} }, setTimeout: () => {}, navigator: {},
  };
  Object.assign(ctx, globals);
  ctx.window = ctx;
  vm.createContext(ctx);
  // The page runs the defaults block (window.rtConsent) in the head first.
  vm.runInContext(js, ctx);
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
{ // Malformed rt_consent: must not throw; banner shows and the trigger still works.
  const b = runBanner({ rt_consent: '%E0%A4%A' });
  assert.ok(b.byId.get('cookieBanner'), 'banner must show when the stored choice cannot be decoded');
  assert.strictEqual(b.docListeners.length, 1, 'preferences trigger must be wired despite a malformed cookie');
  const trigger = {};
  b.docListeners.forEach((fn) => fn({ preventDefault() {},
    target: { closest: (sel) => (sel === '[data-rt-cookie-preferences]' ? trigger : null) } }));
  const panel = b.byId.get('rtConsentPanel');
  assert.ok(panel && panel.hidden === false, 'preferences trigger must open the panel');
  assert.strictEqual(b.byId.get('rtConsentAnalytics').checked, false, 'undecodable choice must read as not granted');
}

// ---- HubSpot (docs/hubspot-tracking-2026-10.md) ---------------------------
// HubSpot loads only from a GTM tag that requires analytics_storage. The banner
// must delete its cookies and tell a loaded tracker to stop when consent is
// refused or withdrawn, and the theme must never embed HubSpot directly.
const HS_COOKIES = ['__hstc', 'hubspotutk', '__hssc', '__hssrc'];
const hsJar = { __hstc: 'a.b.1', hubspotutk: '0123456789abcdef0123456789abcdef', __hssc: 'a.1.1', __hssrc: '1' };
{ // Reject clears all four HubSpot cookies and keeps rt_consent; no _hsp loaded must not throw.
  const b = runBanner({ ...hsJar, other: '1' });
  assert.strictEqual(b.ctx._hsp, undefined);
  b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'reject' });
  for (const n of HS_COOKIES) assert.ok(!b.jar.has(n), `${n} must be cleared on reject`);
  assert.strictEqual(b.jar.get('rt_consent'), 'essential', 'rt_consent must survive the clear');
  assert.strictEqual(b.jar.get('other'), '1');
  assert.ok(b.writes.some((w) => /^hubspotutk=; expires=Thu, 01 Jan 1970[^;]*; path=\/; domain=\.realtreasury\.com$/.test(w)),
    'hubspotutk must also be expired on the registrable domain, where HubSpot sets it');
}
{ // Panel withdrawal: cookies cleared, and a loaded tracker is told to stop, in order.
  const hsp = [];
  const b = runBanner({ rt_consent: 'analytics', ...hsJar }, { _hsp: hsp });
  b.docListeners.forEach((fn) => fn({ preventDefault() {},
    target: { closest: (sel) => (sel === '[data-rt-cookie-preferences]' ? {} : null) } }));
  b.byId.get('rtConsentAnalytics').checked = false;
  b.click(b.byId.get('rtConsentPanel'), { 'data-rt-panel': 'save' });
  for (const n of HS_COOKIES) assert.ok(!b.jar.has(n), `${n} must be cleared on withdrawal`);
  assert.strictEqual(b.jar.get('rt_consent'), 'essential');
  assert.deepStrictEqual(JSON.parse(JSON.stringify(hsp)), [
    ['setHubSpotConsent', { analytics: false, advertisement: false, functionality: true }],
    ['revokeCookieConsent'],
  ], '_hsp must get setHubSpotConsent (analytics off) then revokeCookieConsent');
}
{ // Accept leaves HubSpot alone: no revoke, cookies kept.
  const hsp = [];
  const b = runBanner({ ...hsJar }, { _hsp: hsp });
  b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'accept' });
  assert.strictEqual(hsp.length, 0, 'accepting must not revoke HubSpot consent');
  for (const n of HS_COOKIES) assert.ok(b.jar.has(n), `${n} must not be cleared on accept`);
}
{ // _hsp.push throwing must not stop the rest of decide().
  const b = runBanner({ ...hsJar }, { _hsp: { push() { throw new Error('boom'); } } });
  b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'reject' });
  assert.strictEqual(b.jar.get('rt_consent'), 'essential');
  assert.deepStrictEqual(b.updates(), ['denied']);
}
// The banner and the preference panel both name HubSpot.
{
  const panelRow = (bannerJs.match(/<strong>Analytics and marketing<\/strong>([\s\S]*?)<\/label>/) || [])[1] || '';
  assert.match(panelRow, /HubSpot/, 'preference panel must name HubSpot');
}
// Both policies disclose HubSpot; the Cookie Policy lists its cookies.
for (const page of ['cookie-policy/index.html', 'privacy-policy/index.html']) {
  assert.match(fs.readFileSync(path.join(root, page), 'utf8'), /HubSpot/, `${page} must name HubSpot`);
}
{
  const cookiePolicy = fs.readFileSync(path.join(root, 'cookie-policy/index.html'), 'utf8');
  for (const n of HS_COOKIES) {
    assert.ok(cookiePolicy.includes(`<code>${n}</code>`), `cookie policy must list ${n}`);
  }
}
// No direct embed: HubSpot does not read Consent Mode, so it loads only from GTM.
assert.ok(!/hs-scripts\.com|hs-analytics\.net|hscollectedforms\.net/i.test(php),
  'functions.php must not load HubSpot directly; it is a consent-gated GTM tag');

// ---- US default (owner decision, October 11, 2026) ------------------------
// rt_region=US, no stored choice, no Global Privacy Control: analytics_storage
// defaults to granted and the US notice shows instead of the opt-in banner.
// Everyone else, GPC, an explicit refusal, and an unknown region stay opt-in.
// The rule lives in window.rtConsent only.

// One place: the region and GPC rules appear once in functions.php.
assert.strictEqual((php.match(/globalPrivacyControl/g) || []).length, 1,
  'navigator.globalPrivacyControl must be read in one place (window.rtConsent)');
assert.strictEqual((php.match(/=== 'US'/g) || []).length, 1, "the US rule must live in one place (window.rtConsent)");
assert.ok(!/readConsent|document\.cookie\.match/.test(bannerJs),
  'the banner must ask window.rtConsent, not read rt_consent itself');
assert.match(bannerJs, /var MAX_AGE = 60 \* 60 \* 24 \* 365;/, 'a choice is remembered for 12 months');

function trace(body, { ok = true, status = 200 } = {}) {
  return () => Promise.resolve({ ok, status, text: () => Promise.resolve(body) });
}
const flush = () => new Promise((r) => setImmediate(r));

// Runs the defaults block alone with a cookie jar; returns the analytics default,
// the consent updates pushed, cookie writes and fetch calls.
function runDefaults(cookies, { gpc, fetch, timers } = {}) {
  const jar = new Map(Object.entries(cookies));
  const writes = [];
  const fetched = [];
  const document = {};
  Object.defineProperty(document, 'cookie', {
    get: () => Array.from(jar, ([k, v]) => `${k}=${v}`).join('; '),
    set(str) { writes.push(str); const pair = str.split(';')[0]; const i = pair.indexOf('=');
      jar.set(pair.slice(0, i).trim(), pair.slice(i + 1)); },
  });
  const ctx = { document, location: { protocol: 'https:', pathname: '/' },
    navigator: gpc === undefined ? {} : { globalPrivacyControl: gpc } };
  if (fetch) ctx.fetch = (url, opts) => { fetched.push([url, opts]); return fetch(url, opts); };
  if (timers) ctx.setTimeout = (fn, ms) => timers.push([fn, ms]);
  ctx.window = ctx;
  vm.createContext(ctx);
  vm.runInContext(js, ctx);
  const consent = (kind) => Array.from(ctx.dataLayer).filter((a) => a[0] === 'consent' && a[1] === kind);
  const def = consent('default');
  assert.strictEqual(def.length, 1, 'exactly one consent default');
  for (const k of ['ad_storage', 'ad_user_data', 'ad_personalization']) {
    assert.strictEqual(def[0][2][k], 'denied', `${k} must stay denied everywhere`);
  }
  return { ctx, jar, writes, fetched, def: def[0][2].analytics_storage,
    updates: () => consent('update').map((a) => a[2].analytics_storage),
    order: () => Array.from(ctx.dataLayer).filter((a) => a[0] === 'consent').map((a) => a[1]) };
}

const regionCases = [
  // [cookies, gpc, want]
  [{ rt_region: 'US' }, undefined, 'granted'],                         // US default
  [{ rt_region: 'US' }, false, 'granted'],
  [{ rt_region: 'US' }, true, 'denied'],                               // US + GPC
  [{ rt_region: 'US', rt_consent: 'essential' }, undefined, 'denied'], // US explicit Reject
  [{ rt_region: 'US', rt_consent: 'essential' }, true, 'denied'],
  [{ rt_region: 'US', rt_consent: 'analytics' }, true, 'granted'],     // explicit choice beats GPC
  [{ rt_region: 'US', rt_consent: 'rejected' }, undefined, 'denied'],  // any other stored value is a refusal
  [{ rt_region: 'DE' }, undefined, 'denied'],                          // non-US
  [{ rt_region: 'GB' }, false, 'denied'],
  [{ rt_region: 'CA' }, undefined, 'denied'],
  [{ rt_region: 'XX' }, undefined, 'denied'],                          // unknown
  [{ rt_region: 'DE' }, true, 'denied'],
  [{ rt_region: 'DE', rt_consent: 'analytics' }, undefined, 'granted'],
  [{ rt_region: 'us' }, undefined, 'denied'],                          // malformed region is unknown
  [{ rt_region: 'USA' }, undefined, 'denied'],
  [{ xrt_region: 'US' }, undefined, 'denied'],
];
for (const [cookies, gpc, want] of regionCases) {
  const d = runDefaults(cookies, { gpc, fetch: trace('loc=DE\n') });
  assert.strictEqual(d.def, want, `default for ${JSON.stringify(cookies)} gpc=${gpc}`);
  if (/^[A-Z][A-Z0-9]$/.test(cookies.rt_region || '')) {
    assert.strictEqual(d.fetched.length, 0, 'a known region must not fetch the trace again');
  }
  assert.deepStrictEqual(d.updates(), [], 'no consent update when the region is already known');
}
{ // rtConsent exposes the rule to the other blocks.
  const d = runDefaults({ rt_region: 'US' });
  assert.strictEqual(d.ctx.rtConsent.analytics(), true);
  assert.strictEqual(d.ctx.rtConsent.usDefault(), true);
  assert.strictEqual(d.ctx.rtConsent.stored(), null);
  d.jar.set('rt_consent', 'essential');
  assert.strictEqual(d.ctx.rtConsent.analytics(), false, 'a stored refusal ends the US default at once');
}

(async () => {
  { // First US page: default denied, trace says US, ONE update to granted, region cookie written.
    const d = runDefaults({}, { fetch: trace('fl=1\nh=realtreasury.com\nip=203.0.113.9\nloc=US\ntls=TLSv1.3\n') });
    assert.strictEqual(d.def, 'denied', 'first page starts denied until the region is known');
    assert.strictEqual(d.fetched.length, 1);
    assert.strictEqual(d.fetched[0][0], '/cdn-cgi/trace', 'region comes from the same-origin Cloudflare trace');
    await flush();
    assert.deepStrictEqual(d.updates(), ['granted'], 'one consent update on the first US page');
    assert.deepStrictEqual(d.order(), ['default', 'update'], 'the default reaches dataLayer before the update');
    assert.strictEqual(d.jar.get('rt_region'), 'US');
    assert.ok(d.writes.includes('rt_region=US; path=/; max-age=2592000; SameSite=Lax; Secure'),
      'rt_region must be written for 30 days, site-wide, SameSite=Lax, Secure: ' + d.writes.join(' | '));
    assert.ok(!d.jar.has('rt_consent'), 'the US default must never be written to rt_consent');
    await flush();
    assert.deepStrictEqual(d.updates(), ['granted'], 'still exactly one update');
  }
  { // First page, non-US: region stored, no update.
    const d = runDefaults({}, { fetch: trace('loc=DE\n') });
    await flush();
    assert.deepStrictEqual(d.updates(), []);
    assert.strictEqual(d.jar.get('rt_region'), 'DE');
  }
  { // First page, US with GPC: region stored, no update.
    const d = runDefaults({}, { gpc: true, fetch: trace('loc=US\n') });
    await flush();
    assert.deepStrictEqual(d.updates(), []);
    assert.strictEqual(d.jar.get('rt_region'), 'US');
  }
  { // First page, US, already accepted: default was granted; no second grant.
    const d = runDefaults({ rt_consent: 'analytics' }, { fetch: trace('loc=US\n') });
    assert.strictEqual(d.def, 'granted');
    await flush();
    assert.deepStrictEqual(d.updates(), []);
  }
  { // Trace with no country: XX stored, strict.
    const d = runDefaults({}, { fetch: trace('fl=1\nh=x\n') });
    await flush();
    assert.deepStrictEqual(d.updates(), []);
    assert.strictEqual(d.jar.get('rt_region'), 'XX');
  }
  for (const fetch of [() => Promise.reject(new Error('offline')), trace('', { ok: false, status: 404 }),
    () => { throw new Error('sync'); }]) {
    // Fetch failure: strict for this page, nothing stored, so the next page asks again.
    const d = runDefaults({}, { fetch });
    await flush();
    assert.deepStrictEqual(d.updates(), [], 'fetch failure must not grant');
    assert.ok(!d.jar.has('rt_region'), 'a failed lookup must not be cached');
    let ran = false;
    d.ctx.rtConsent.whenRegion(() => { ran = true; });
    assert.ok(ran, 'a failed lookup must still release the banner');
    assert.strictEqual(d.ctx.rtConsent.region(), null);
  }
  { // No fetch at all (old browser): strict, no throw.
    const d = runDefaults({});
    assert.strictEqual(d.def, 'denied');
    assert.deepStrictEqual(d.updates(), []);
  }
  { // Slow trace: the timeout settles first (strict); a late US answer is stored for the next page only.
    const timers = [];
    let answer;
    const d = runDefaults({}, { timers, fetch: () => new Promise((r) => { answer = r; }) });
    assert.strictEqual(timers.length, 1);
    let ran = 0;
    d.ctx.rtConsent.whenRegion(() => { ran++; });
    assert.strictEqual(ran, 0, 'the banner waits for the region');
    timers[0][0]();
    assert.strictEqual(ran, 1, 'the timeout releases the banner');
    answer({ ok: true, text: () => Promise.resolve('loc=US\n') });
    await flush();
    assert.deepStrictEqual(d.updates(), [], 'a late answer must not grant on this page');
    assert.strictEqual(d.jar.get('rt_region'), 'US', 'a late answer is kept for the next page');
    assert.strictEqual(ran, 1);
  }

  // ---- Banner vs US notice --------------------------------------------------
  const mode = (b) => { const el = b.byId.get('cookieBanner'); return el ? el.getAttribute('data-rt-mode') : null; };
  { // Opt-in banner (everyone else): short copy, Accept and Reject equal, Settings a text link.
    const b = runBanner({ rt_region: 'DE' });
    assert.strictEqual(mode(b), 'opt-in');
    const html = b.byId.get('cookieBanner').innerHTML;
    assert.match(html, /help us improve the guides and research we publish\./);
    assert.match(html, /Nothing is set until you choose\./);
    const btn = (action) => (html.match(new RegExp(`<button[^>]*data-rt-consent="${action}"[^>]*>`)) || [])[0];
    const cls = (tag) => (tag.match(/class="([^"]*)"/) || [])[1];
    assert.ok(btn('accept') && btn('reject'), 'Accept and Reject must both be there');
    assert.strictEqual(cls(btn('accept')), cls(btn('reject')), 'Accept and Reject must share size and styling');
    assert.ok(html.indexOf('data-rt-consent="accept"') < html.indexOf('data-rt-consent="reject"'));
    assert.strictEqual(cls(btn('manage')), 'rt-consent-link', 'Settings is a small text link, not a third button');
    assert.ok(!/data-rt-consent="(ok|optout)"/.test(html));
    b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'accept' });
    assert.ok(b.writes.some((w) => w === 'rt_consent=analytics; path=/; max-age=31536000; SameSite=Lax; Secure'),
      'the choice must be kept 12 months: ' + b.writes.join(' | '));
  }
  for (const [cookies, gpc] of [[{ rt_region: 'GB' }, true], [{ rt_region: 'XX' }, undefined],
    [{ rt_region: 'US' }, true]]) {
    // Non-US with GPC, unknown region, and US with GPC: the opt-in banner.
    const b = runBanner(cookies, { navigator: { globalPrivacyControl: gpc } });
    assert.strictEqual(mode(b), 'opt-in', `opt-in banner for ${JSON.stringify(cookies)} gpc=${gpc}`);
  }
  { // US + GPC: panel opens unticked; an explicit Accept there wins.
    const b = runBanner({ rt_region: 'US' }, { navigator: { globalPrivacyControl: true } });
    b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'manage' });
    const box = b.byId.get('rtConsentAnalytics');
    assert.strictEqual(box.checked, false, 'GPC means analytics off in the panel');
    box.checked = true;
    b.click(b.byId.get('rtConsentPanel'), { 'data-rt-panel': 'save' });
    assert.strictEqual(b.jar.get('rt_consent'), 'analytics');
    assert.strictEqual(b.ctx.rtConsent.analytics(), true, 'an explicit choice beats GPC');
  }
  { // US, no choice, no GPC: the small notice, not the banner.
    const b = runBanner({ rt_region: 'US' });
    assert.strictEqual(mode(b), 'notice');
    const html = b.byId.get('cookieBanner').innerHTML;
    assert.match(html, /We use cookies to understand how our site is used\./);
    assert.match(html, /data-rt-consent="manage">Cookie settings</);
    assert.match(html, /data-rt-consent="optout">Do not sell or share my personal information</);
    assert.match(html, /data-rt-consent="ok">OK</);
    assert.ok(!/data-rt-consent="(accept|reject)"/.test(html), 'the notice has no Accept/Reject');
    assert.ok(!b.jar.has('rt_consent'), 'showing the notice records nothing');
    // Cookie settings opens the panel, ticked (analytics is on under the US default).
    b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'manage' });
    assert.strictEqual(b.byId.get('rtConsentPanel').hidden, false);
    assert.strictEqual(b.byId.get('rtConsentAnalytics').checked, true);
  }
  { // US notice OK: records analytics for 12 months.
    const b = runBanner({ rt_region: 'US' });
    b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'ok' });
    assert.strictEqual(b.jar.get('rt_consent'), 'analytics');
    assert.ok(b.writes.includes('rt_consent=analytics; path=/; max-age=31536000; SameSite=Lax; Secure'));
  }
  { // US notice "Do not sell or share": records essential-only in one click, revokes and clears.
    const hsp = [];
    const b = runBanner({ rt_region: 'US', ...hsJar, _ga: 'GA1.1.1' }, { _hsp: hsp });
    assert.strictEqual(b.ctx.rtConsent.analytics(), true);
    b.click(b.byId.get('cookieBanner'), { 'data-rt-consent': 'optout' });
    assert.strictEqual(b.jar.get('rt_consent'), 'essential');
    assert.deepStrictEqual(b.updates(), ['denied']);
    for (const n of [...HS_COOKIES, '_ga']) assert.ok(!b.jar.has(n), `${n} must be cleared on opt-out`);
    assert.strictEqual(hsp.length, 2, 'a loaded HubSpot tracker must be told to stop');
    assert.strictEqual(b.ctx.rtConsent.analytics(), false);
  }
  { // US with a stored choice: neither banner nor notice.
    for (const v of ['essential', 'analytics']) {
      assert.strictEqual(mode(runBanner({ rt_region: 'US', rt_consent: v })), null, `nothing shown with ${v}`);
    }
  }
  { // First US page: the banner waits for the trace, then shows the notice; one grant.
    const b = runBanner({}, { fetch: trace('loc=US\n') });
    assert.strictEqual(mode(b), null, 'nothing shows before the region is known');
    await flush();
    assert.strictEqual(mode(b), 'notice');
    assert.deepStrictEqual(b.updates(), ['granted'], 'exactly one consent update on the first US page');
    assert.strictEqual(b.jar.get('rt_region'), 'US');
  }
  { // First page, trace fails: the opt-in banner, nothing granted.
    const b = runBanner({}, { fetch: () => Promise.reject(new Error('offline')) });
    await flush();
    assert.strictEqual(mode(b), 'opt-in');
    assert.deepStrictEqual(b.updates(), []);
  }

  console.log(`consent mode defaults: ${cases.length} cookie cases OK; ${regionCases.length} region cases OK; ` +
    'first-page trace OK; banner accept/reject/revoke OK; US notice OK; HubSpot clear/revoke OK; ' +
    `preference triggers present; ${webinarFiles.length} files free of youtube.com/embed`);
})().catch((e) => { console.error(e); process.exit(1); });
