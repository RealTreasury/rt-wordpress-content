// Runs the rt-track block from assets/php/functions.php in a fake browser and
// pins the contract other code relies on (docs/site-events.md): rtTrack forwards
// to gtag with clipped string params, the github.io iframe bridge forwards and
// answers source requests, other origins are ignored, and the session source is
// persisted only after analytics consent.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');
// Objects built inside the vm context carry its prototypes; compare as plain data.
const eq = (a, b, msg) => assert.deepStrictEqual(JSON.parse(JSON.stringify(a)), b, msg);

const php = fs.readFileSync(path.join(__dirname, '..', '..', 'assets', 'php', 'functions.php'), 'utf8');

assert.match(php, /add_action\(\s*'wp_head',\s*'rt_track_helper',\s*2\s*\)/,
  'rt_track_helper must run on wp_head priority 2, after the consent defaults (1)');
for (const mod of ['analytics-4', 'tagmanager', 'ads']) {
  assert.ok(php.includes(`'googlesitekit_${mod}_tag_blocked', 'rt_block_tags_off_production'`),
    `Site Kit ${mod} tag must be blocked off production`);
}

const fnStart = php.indexOf('function rt_track_helper()');
const body = php.slice(fnStart, php.indexOf('\n}\n', fnStart));
const m = body.match(/<script id="rt-track">([\s\S]*?)<\/script>/);
assert.ok(m, 'rt-track script block is missing');
const js = m[1].replace(/<\?php echo esc_js\( RT_CONSENT_COOKIE \); \?>/, 'rt_consent');
assert.ok(!/<\?php/.test(js), 'unexpected PHP inside the rt-track block');

function boot({ search = '', referrer = '', cookie = '', stored = null } = {}) {
  const store = new Map(stored ? [['rt_src', JSON.stringify(stored)]] : []);
  const listeners = { message: [], click: [] };
  const events = [];
  const win = {
    location: { search, pathname: '/treasury-tech-selection-guide/', hostname: 'realtreasury.com' },
    sessionStorage: { getItem: (k) => (store.has(k) ? store.get(k) : null), setItem: (k, v) => store.set(k, v) },
    addEventListener: (t, fn) => listeners[t].push(fn),
    gtag: (...a) => events.push(a),
  };
  const doc = { cookie, referrer, addEventListener: (t, fn) => listeners[t].push(fn) };
  vm.runInNewContext(js, { window: win, document: doc, URLSearchParams, URL, JSON, String, Object });
  return { win, store, events, listeners };
}

// rtTrack forwards to gtag, clips values, drops bad names and keys.
{
  const { win, events } = boot();
  win.rtTrack('generate_lead', { form_name: 'rtg-form-2', asset: 'x'.repeat(150), Bad: 'no', n: 3, empty: null });
  win.rtTrack('Not Valid', { a: 'b' });
  win.rtTrack(42);
  assert.strictEqual(events.length, 1);
  eq(events[0].slice(0, 2), ['event', 'generate_lead']);
  eq(events[0][2], { form_name: 'rtg-form-2', asset: 'x'.repeat(100), n: '3', transport_type: 'beacon' });
}

// Source: captured from the URL and referrer; NOT persisted without consent.
{
  const { win, store } = boot({ search: '?utm_source=linkedin&utm_medium=social&utm_campaign=2026-10-guide&x=1',
    referrer: 'https://www.linkedin.com/feed/' });
  eq(win.rtSource(), { utm_source: 'linkedin', utm_medium: 'social',
    utm_campaign: '2026-10-guide', landing_page: '/treasury-tech-selection-guide/', referrer_host: 'www.linkedin.com' });
  assert.ok(!store.has('rt_src'), 'source must not be stored before analytics consent');
}

// With consent: persisted, and a stored source wins over the current page.
{
  const { store } = boot({ search: '?utm_source=resend', cookie: 'foo=1; rt_consent=analytics' });
  assert.strictEqual(JSON.parse(store.get('rt_src')).utm_source, 'resend');
  const later = boot({ search: '', cookie: 'rt_consent=analytics', stored: { utm_source: 'resend', landing_page: '/' } });
  assert.strictEqual(later.win.rtSource().utm_source, 'resend');
  assert.strictEqual(later.win.rtSource().landing_page, '/');
}
// A rejected choice is not consent.
assert.ok(!boot({ search: '?utm_source=a', cookie: 'rt_consent=rejected' }).store.has('rt_src'));
assert.ok(!boot({ search: '?utm_source=a', cookie: 'rt_consent=analyticsX' }).store.has('rt_src'));

// Iframe bridge: github.io forwarded with this page's lead_page; others ignored.
{
  const { listeners, events } = boot();
  const fire = (origin, data, source) => listeners.message.forEach((fn) => fn({ origin, data, source }));
  fire('https://evil.example', { source: 'rt', type: 'rt:track', name: 'generate_lead', params: {} });
  fire('https://realtreasury.github.io', { source: 'other', type: 'rt:track', name: 'generate_lead' });
  assert.strictEqual(events.length, 0);
  fire('https://realtreasury.github.io', { source: 'rt', type: 'rt:track', name: 'generate_lead',
    params: { asset: 'waitlist', lead_page: '/spoofed/' } });
  eq(events[0][2], { asset: 'waitlist', lead_page: '/treasury-tech-selection-guide/', transport_type: 'beacon' });
  const replies = [];
  fire('https://realtreasury.github.io', { source: 'rt', type: 'rt:source?' },
    { postMessage: (msg, origin) => replies.push([msg, origin]) });
  assert.strictEqual(replies.length, 1);
  assert.strictEqual(replies[0][1], 'https://realtreasury.github.io');
  assert.strictEqual(replies[0][0].type, 'rt:source');
  assert.strictEqual(replies[0][0].data.landing_page, '/treasury-tech-selection-guide/');
  fire('https://calendly.com', { event: 'calendly.event_scheduled' });
  eq(events[events.length - 1].slice(1), ['book_call', { lead_page: '/treasury-tech-selection-guide/', transport_type: 'beacon' }]);
}

// CTA clicks: data-rt-cta and Calendly links.
{
  const { listeners, events } = boot();
  const el = (attrs) => ({ getAttribute: (k) => (k in attrs ? attrs[k] : null) });
  const click = (target) => listeners.click.forEach((fn) => fn({ target }));
  click({ closest: () => el({ 'data-rt-cta': 'get-guide', 'data-rt-cta-location': 'banner' }) });
  click({ closest: () => el({ href: 'https://calendly.com/x' }) });
  click({ closest: () => el({ href: 'https://outlook.office.com/book/RealTreasuryMeeting@realtreasury.com/s/abc' }) });
  click({ closest: () => null });
  eq(events.map((e) => e[2]), [
    { cta: 'get-guide', location: 'banner', transport_type: 'beacon' },
    { cta: 'calendly', location: '/treasury-tech-selection-guide/', transport_type: 'beacon' },
    { cta: 'bookings', location: '/treasury-tech-selection-guide/', transport_type: 'beacon' },
  ]);
}

console.log('rt-track helper: all checks passed');
