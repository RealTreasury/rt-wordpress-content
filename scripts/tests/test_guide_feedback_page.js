// Guide feedback page (docs/guide-release-runbook.md, "Guide feedback survey",
// steps 3-4). The page's inline script is RUN here in a node vm against a small
// DOM stub and a recording fetch, so the submit behaviour is proven offline:
//
//   - the only network call the page can make is one POST to the HubSpot Forms
//     API for the "Website - Guide Feedback" form: no mail is sent by the page,
//     and the HubSpot form itself has no follow-up email (read from the API
//     October 19, 2026);
//   - an email-link click (?email=<address>&r=<1-5>) records exactly one
//     "via email click" row, only once the page is visible, and takes the
//     address out of the address bar;
//   - the typed form refuses a missing rating or address, drops honeypot
//     submissions, and sends a HubSpot visitor ID only for a typed address and
//     only when it is 32 hex characters;
//   - a failed save puts the star form back with the retry message.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const PAGE = path.join(__dirname, '..', '..', 'treasury-tech-selection', 'guidebook', 'feedback', 'wordpress-page.html');
const html = fs.readFileSync(PAGE, 'utf8');
const blocks = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
assert.strictEqual(blocks.length, 1, 'feedback page: expected exactly one inline script');
const script = blocks[0];
assert.ok(!script.includes('&&'), 'feedback page: && in inline JS is mangled by WP texturize');

const PORTAL_ID = '247523747';
const FORM_GUID = 'de7e1b1b-546f-4d4d-b8d1-bcb50c0f6f81';
const ENDPOINT = `https://api.hsforms.com/submissions/v3/integration/submit/${PORTAL_ID}/${FORM_GUID}`;
const HUTK = 'a'.repeat(32);

// ---- static: where the page can send anything --------------------------------
assert.strictEqual((script.match(/fetch\(/g) || []).length, 1, 'page must have exactly one fetch call');
assert.ok(script.includes(`var FORM_GUID = '${FORM_GUID}'`), 'FORM_GUID must be the Website - Guide Feedback form');
assert.ok(script.includes(`var PORTAL_ID = '${PORTAL_ID}'`), 'PORTAL_ID must be the HubSpot portal');
assert.ok(!/resend\.com|mailto:|sendBeacon|XMLHttpRequest/.test(script), 'page script must have no mail or other send path');
assert.ok(/jetpack_seo_noindex|noindex/.test(fs.readFileSync(path.join(__dirname, '..', '..', 'docs', 'guide-release-runbook.md'), 'utf8')),
  'runbook must still record that the feedback page is noindex');

// ---- DOM stub ----------------------------------------------------------------
class El {
  constructor(id) {
    this.id = id || '';
    this.hidden = false;
    this.value = '';
    this.textContent = '';
    this.className = '';
    this.disabled = false;
    this.checked = false;
    this.children = [];
    this.listeners = {};
    this.attrs = {};
    const set = new Set();
    this.classes = set;
    this.classList = {
      add: (c) => set.add(c),
      remove: (c) => set.delete(c),
      toggle: (c, on) => { if (on === undefined ? !set.has(c) : on) { set.add(c); } else { set.delete(c); } },
      contains: (c) => set.has(c),
    };
  }
  addEventListener(t, fn) { (this.listeners[t] = this.listeners[t] || []).push(fn); }
  appendChild(c) { this.children.push(c); return c; }
  setAttribute(k, v) { this.attrs[k] = v; }
  getAttribute(k) { return this.attrs[k]; }
  focus() {}
  fire(type, ev) { (this.listeners[type] || []).forEach((fn) => fn(ev)); }
}

// opts: search, visibility ('visible' | 'hidden'), fetchResult ('ok' | 'fail'),
//       hutk (value returned by rtSource().hutk), withGtag.
function load(opts = {}) {
  const els = {};
  ['rtGbfbForm', 'rtGbfbStatus', 'rtGbfbSubmit', 'rtGbfbEmail', 'rtGbfbEmailRow', 'rtGbfbGot',
    'rtGbfbHeading', 'rtGbfbIntro', 'rtGbfbDone', 'rtGbfbWebsite'].forEach((id) => { els[id] = new El(id); });
  els.rtGbfbEmailRow.hidden = true;
  els.rtGbfbGot.hidden = true;
  els.rtGbfbDone.hidden = true;

  const stars = new El('stars');
  const fieldset = new El('fs');
  fieldset.attrs['data-q'] = 'useful';
  fieldset.querySelector = (sel) => (sel === '.rt-gbfb-stars' ? stars : null);
  els.rtGbfbForm.querySelectorAll = (sel) => (sel === '.rt-gbfb-q' ? [fieldset] : []);
  els.rtGbfbForm.querySelector = (sel) => (sel === '[data-q="useful"]' ? fieldset : null);

  const docListeners = {};
  const document = {
    visibilityState: opts.visibility || 'visible',
    getElementById: (id) => els[id] || stars.children.find((c) => c.id === id) || null,
    createElement: () => new El(),
    addEventListener: (t, fn) => { (docListeners[t] = docListeners[t] || []).push(fn); },
    removeEventListener: (t, fn) => { docListeners[t] = (docListeners[t] || []).filter((f) => f !== fn); },
  };

  const fetches = [];
  const gtagCalls = [];
  const replaced = [];
  const win = {
    location: { origin: 'https://realtreasury.com', pathname: '/treasury-tech-selection-guide/feedback/', search: opts.search || '', hash: '' },
    history: { replaceState: (a, b, url) => { replaced.push(url); } },
  };
  if (opts.hutk !== undefined) { win.rtSource = () => ({ hutk: opts.hutk }); }
  if (opts.withGtag) { win.gtag = function () { gtagCalls.push(Array.from(arguments)); }; }
  const context = {
    window: win,
    document,
    URLSearchParams,
    fetch: (url, init) => {
      fetches.push({ url, init, body: JSON.parse(init.body) });
      return opts.fetchResult === 'fail'
        ? Promise.resolve({ ok: false, status: 500 })
        : Promise.resolve({ ok: true, status: 200 });
    },
  };
  vm.createContext(context);
  vm.runInContext(script, context);
  return {
    els, fetches, replaced, gtagCalls, win, docListeners, document,
    pickStar(n) { const r = stars.children.find((c) => c.id === `rtGbfb-useful-${n}`); r.fire('change', { target: { value: String(n) } }); },
    submit() { els.rtGbfbForm.fire('submit', { preventDefault() {} }); },
  };
}

const settle = () => new Promise((r) => setImmediate(r));
const msg = (call) => call.body.fields.find((f) => f.name === 'message').value;
const addr = (call) => call.body.fields.find((f) => f.name === 'email').value;

(async () => {
  // ---- email-link click: one row, nothing else ---------------------------------
  {
    const p = load({ search: '?email=reader%40corp.example&r=5', hutk: HUTK, withGtag: true });
    await settle();
    assert.strictEqual(p.fetches.length, 1, 'email click: exactly one request');
    const f = p.fetches[0];
    assert.strictEqual(f.url, ENDPOINT, 'email click: posts to the HubSpot form only');
    assert.strictEqual(f.init.method, 'POST');
    assert.strictEqual(addr(f), 'reader@corp.example');
    assert.strictEqual(msg(f), 'Guide feedback | Usefulness 5/5 | via email click');
    assert.strictEqual(f.body.context.pageName, 'Guide feedback');
    assert.ok(!('hutk' in f.body.context), 'email click: no visitor ID for an address that came from the link');
    assert.deepStrictEqual(p.replaced, ['/treasury-tech-selection-guide/feedback/'], 'email click: address taken out of the address bar');
    assert.strictEqual(p.els.rtGbfbDone.hidden, false, 'email click: thank-you shown');
    assert.strictEqual(p.els.rtGbfbForm.hidden, true, 'email click: star form hidden');
    assert.strictEqual(p.els.rtGbfbEmailRow.hidden, true, 'email click: never asks for the address it already has');
    assert.deepStrictEqual(p.gtagCalls.map((c) => [c[0], c[1], c[2].method]), [['event', 'guide_feedback', 'email_click']]);
  }

  // ---- a '+' in the address survives the query string ---------------------------
  {
    const p = load({ search: '?email=jo+guide@corp.example&r=3' });
    await settle();
    assert.strictEqual(addr(p.fetches[0]), 'jo+guide@corp.example');
    assert.strictEqual(msg(p.fetches[0]), 'Guide feedback | Usefulness 3/5 | via email click');
  }

  // ---- a prefetch (page not visible) records nothing until it is shown ----------
  {
    const p = load({ search: '?email=reader%40corp.example&r=4', visibility: 'hidden' });
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'hidden page: no request');
    p.docListeners.visibilitychange.forEach((fn) => fn());
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'still hidden after a visibility event: no request');
    p.document.visibilityState = 'visible';
    p.docListeners.visibilitychange.forEach((fn) => fn());
    await settle();
    assert.strictEqual(p.fetches.length, 1, 'page shown: one request');
    assert.strictEqual(p.docListeners.visibilitychange.length, 0, 'page shown: listener removed so it cannot post twice');
  }

  // ---- failed save: star form comes back with the retry message ------------------
  {
    const p = load({ search: '?email=reader%40corp.example&r=2', fetchResult: 'fail' });
    await settle();
    assert.strictEqual(p.fetches.length, 1);
    assert.strictEqual(p.els.rtGbfbForm.hidden, false, 'failed save: star form shown again');
    assert.strictEqual(p.els.rtGbfbGot.hidden, false);
    assert.ok(p.els.rtGbfbGot.textContent.startsWith('Your rating did not save.'), 'failed save: retry message');
  }

  // ---- links that are not a click ------------------------------------------------
  for (const search of ['', '?email=reader%40corp.example', '?email=reader%40corp.example&r=0', '?email=reader%40corp.example&r=6', '?email=reader%40corp.example&r=2.5', '?r=5']) {
    const p = load({ search });
    await settle();
    assert.strictEqual(p.fetches.length, 0, `no valid click (${search || 'bare'}): no request on load`);
    assert.strictEqual(p.els.rtGbfbForm.hidden, false);
  }
  {
    const p = load({ search: '?r=5' });
    assert.strictEqual(p.els.rtGbfbEmailRow.hidden, false, 'rating with no address: asks for the address');
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'rating with no address: nothing recorded until the reader sends it');
  }
  {
    const p = load({ search: '?email=reader%40corp.example' });
    assert.deepStrictEqual(p.replaced, ['/treasury-tech-selection-guide/feedback/'], '?email= is stripped even without a rating');
  }

  // ---- typed form ------------------------------------------------------------------
  {
    const p = load({});
    p.submit();
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'no rating: nothing sent');
    assert.strictEqual(p.els.rtGbfbStatus.textContent, 'Please pick a star rating.');
    p.pickStar(4);
    p.submit();
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'no address: nothing sent');
    p.els.rtGbfbEmail.value = 'not-an-address';
    p.submit();
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'bad address: nothing sent');
    p.els.rtGbfbWebsite.value = 'spam';
    p.els.rtGbfbEmail.value = 'typed@corp.example';
    p.submit();
    await settle();
    assert.strictEqual(p.fetches.length, 0, 'honeypot filled: dropped silently');
    p.els.rtGbfbWebsite.value = '';
    p.submit();
    await settle();
    assert.strictEqual(p.fetches.length, 1, 'valid submit: one request');
    assert.strictEqual(p.fetches[0].url, ENDPOINT);
    assert.strictEqual(addr(p.fetches[0]), 'typed@corp.example');
    assert.strictEqual(msg(p.fetches[0]), 'Guide feedback | Usefulness 4/5');
    assert.strictEqual(p.els.rtGbfbDone.hidden, false, 'valid submit: thank-you shown');
  }

  // ---- visitor ID: typed address only, 32 hex only ----------------------------------
  for (const [label, hutk, want] of [['valid', HUTK, true], ['malformed', 'not-hex', false], ['absent', undefined, false]]) {
    const p = load({ hutk });
    p.pickStar(5);
    p.els.rtGbfbEmail.value = 'typed@corp.example';
    p.submit();
    await settle();
    assert.strictEqual('hutk' in p.fetches[0].body.context, want, `typed address, ${label} visitor ID`);
    if (want) { assert.strictEqual(p.fetches[0].body.context.hutk, HUTK); }
  }
  {
    // Address from the link, then submitted unchanged from the star form: still no visitor ID.
    const p = load({ search: '?email=reader%40corp.example&r=1', hutk: HUTK, fetchResult: 'fail' });
    await settle();
    p.pickStar(3);
    p.submit();
    await settle();
    const last = p.fetches[p.fetches.length - 1];
    assert.strictEqual(addr(last), 'reader@corp.example');
    assert.ok(!('hutk' in last.body.context), 'link address resubmitted: no visitor ID');
  }

  // ---- typed submit failure re-enables the button -----------------------------------
  {
    const p = load({ fetchResult: 'fail' });
    p.pickStar(2);
    p.els.rtGbfbEmail.value = 'typed@corp.example';
    p.submit();
    await settle();
    assert.strictEqual(p.els.rtGbfbSubmit.disabled, false, 'failed typed submit: button usable again');
    assert.ok(p.els.rtGbfbStatus.textContent.includes('contact@realtreasury.com'), 'failed typed submit: fallback address shown');
    assert.strictEqual(p.els.rtGbfbDone.hidden, true);
  }

  console.log('guide feedback page: all checks passed');
})().catch((e) => { console.error(e); process.exit(1); });
