// Runs the thank-you page's GA4 script in a node vm with a fake window, in the same
// node-only style as the other static-page checks: generate_lead, and its
// reload/back-forward skip.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..', '..', 'treasury-tech-selection', 'guidebook');
const LEAD_PAGE = '/treasury-tech-selection-guide/';

function scriptOf(rel) {
  const html = fs.readFileSync(path.join(root, rel), 'utf8');
  const blocks = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
  assert.strictEqual(blocks.length, 1, `${rel}: expected exactly one inline script`);
  assert.ok(!blocks[0].includes('&&'), `${rel}: && in inline JS is mangled by WP texturize`);
  return blocks[0];
}

// Returns { win, run() }. withGtag: install a gtag that records calls. navType: performance navigation entry type,
// or null for a browser without the API.
function sandbox({ withGtag = false, navType = 'navigate' } = {}) {
  const gtagCalls = [];
  const win = { gtagCalls };
  if (navType !== null) {
    win.performance = { getEntriesByType: (t) => (t === 'navigation' ? [{ type: navType }] : []) };
  }
  if (withGtag) {
    win.gtag = function () { gtagCalls.push(Array.from(arguments)); };
  }
  const context = { window: win };
  context.performance = win.performance;
  vm.createContext(context);
  return { win, run: (src) => vm.runInContext(src, context) };
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

console.log('guide GA4 event checks passed');
