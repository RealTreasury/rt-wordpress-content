// Runs the guide's two GA4 scripts in a node vm with a fake window, in the same
// node-only style as the other static-page checks: the thank-you page's
// generate_lead (and its reload/back-forward skip) and the download page's
// file_download plus its single redirect to the PDF.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..', '..', 'treasury-tech-selection', 'guidebook');
const PDF = 'https://realtreasury.com/wp-content/uploads/2026/09/2026-Real-Treasury-Tech-Selection-Guide.pdf';
const LEAD_PAGE = '/treasury-tech-selection-guide/';

function scriptOf(rel) {
  const html = fs.readFileSync(path.join(root, rel), 'utf8');
  const blocks = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
  assert.strictEqual(blocks.length, 1, `${rel}: expected exactly one inline script`);
  assert.ok(!blocks[0].includes('&&'), `${rel}: && in inline JS is mangled by WP texturize`);
  return blocks[0];
}

// Returns { window, timers, run() }. withGtag: install a gtag that records calls
// and optionally runs event_callback. navType: performance navigation entry type,
// or null for a browser without the API.
function sandbox({ withGtag = false, runCallback = false, navType = 'navigate' } = {}) {
  const replaced = [];
  const timers = [];
  const gtagCalls = [];
  const win = {
    location: { replace: (url) => replaced.push(url) },
    replaced,
    gtagCalls,
  };
  if (navType !== null) {
    win.performance = { getEntriesByType: (t) => (t === 'navigation' ? [{ type: navType }] : []) };
  }
  if (withGtag) {
    win.gtag = function () {
      gtagCalls.push(Array.from(arguments));
      const params = arguments[2];
      if (runCallback) {
        if (params) { if (typeof params.event_callback === 'function') { params.event_callback(); } }
      }
    };
  }
  const context = {
    window: win,
    setTimeout: (fn, ms) => { timers.push({ fn, ms }); },
  };
  context.performance = win.performance;
  vm.createContext(context);
  return { win, timers, run: (src) => vm.runInContext(src, context) };
}

function events(win) {
  const fromDl = (win.dataLayer || []).map((a) => Array.from(a));
  return win.gtagCalls.concat(fromDl).filter((a) => a[0] === 'event');
}

// ---- thank-you: generate_lead ------------------------------------------------
const thankYou = scriptOf(path.join('thank-you', 'wordpress-page.html'));

for (const navType of ['navigate', null]) {
  const s = sandbox({ navType });
  s.run(thankYou);
  const ev = events(s.win);
  assert.strictEqual(ev.length, 1, `thank-you (${navType}): one event queued on dataLayer`);
  assert.strictEqual(ev[0][1], 'generate_lead');
  assert.deepStrictEqual(JSON.parse(JSON.stringify(ev[0][2])), { form_name: 'tech-selection-guide', lead_page: LEAD_PAGE });
}

for (const navType of ['reload', 'back_forward']) {
  const s = sandbox({ navType });
  s.run(thankYou);
  assert.strictEqual(events(s.win).length, 0, `thank-you: a ${navType} must not re-fire generate_lead`);
}

{
  // A second real submit in the same tab is a fresh navigation and counts again.
  const s = sandbox({ withGtag: true });
  s.run(thankYou);
  s.run(thankYou);
  assert.strictEqual(events(s.win).length, 2, 'thank-you: two fresh loads count twice');
}

// ---- download: file_download then one redirect ---------------------------------
const download = scriptOf(path.join('download', 'wordpress-page.html'));

{
  // No gtag.js: event queued, redirect comes from the timeout alone.
  const s = sandbox();
  s.run(download);
  const ev = events(s.win);
  assert.strictEqual(ev.length, 1);
  assert.strictEqual(ev[0][1], 'file_download');
  assert.strictEqual(ev[0][2].link_url, PDF);
  assert.strictEqual(ev[0][2].form_name, 'tech-selection-guide');
  assert.strictEqual(ev[0][2].lead_page, LEAD_PAGE, 'download: same lead_page as generate_lead');
  assert.strictEqual(s.win.replaced.length, 0, 'download: no redirect before callback or timeout');
  assert.strictEqual(s.timers.length, 1);
  assert.strictEqual(s.timers[0].ms, 1500);
  s.timers[0].fn();
  assert.deepStrictEqual(Array.from(s.win.replaced), [PDF], 'download: timeout redirects without gtag');
}

{
  // gtag.js loaded: event_callback redirects, and the later timeout is a no-op.
  const s = sandbox({ withGtag: true, runCallback: true });
  s.run(download);
  assert.deepStrictEqual(Array.from(s.win.replaced), [PDF], 'download: event_callback redirects');
  s.timers.forEach((t) => t.fn());
  assert.deepStrictEqual(Array.from(s.win.replaced), [PDF], 'download: redirect happens exactly once');
}

{
  // gtag throws: the download still happens.
  const s = sandbox();
  s.win.gtag = () => { throw new Error('blocked'); };
  s.run(download);
  s.timers.forEach((t) => t.fn());
  assert.deepStrictEqual(Array.from(s.win.replaced), [PDF], 'download: analytics failure never blocks the PDF');
}

console.log('guide GA4 event checks passed');
