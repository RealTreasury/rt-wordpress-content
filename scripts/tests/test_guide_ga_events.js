// Guide funnel lead counting (docs/guide-release-runbook.md, "Counting the
// funnel"). A guide lead must count exactly ONCE, whatever order rt-gate #91
// (window.RTGLeadEvents), the theme's window.rtTrack and the two pages are
// released in:
//
//   - the download form (4202) calls RTGLeadEvents.trackLead on a successful
//     submit; when that returns true (handed to rtTrack) it redirects to the
//     thank-you page with #rt-lead-counted;
//   - the thank-you page (4585) fires its fallback generate_lead on load unless
//     that fragment is present (and never on a reload or back/forward visit).
//
// Both halves are RUN here in a node vm: the thank-you page's inline script as
// a whole, and the form's success-branch lines extracted from the page.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.join(__dirname, '..', '..', 'treasury-tech-selection', 'guidebook');
const LEAD_PAGE = '/treasury-tech-selection-guide/';
const THANK_YOU_URL = 'https://realtreasury.com/treasury-tech-selection-guide/thank-you/';

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

function scriptOf(rel) {
  const html = read(rel);
  const blocks = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
  assert.strictEqual(blocks.length, 1, `${rel}: expected exactly one inline script`);
  assert.ok(!blocks[0].includes('&&'), `${rel}: && in inline JS is mangled by WP texturize`);
  return blocks[0];
}

// ---- thank-you page ---------------------------------------------------------
const thankYou = scriptOf(path.join('thank-you', 'wordpress-page.html'));

// navType: performance navigation entry type, or null for a browser without the API.
function runThankYou({ navType = 'navigate', hash = '', withGtag = false } = {}) {
  const gtagCalls = [];
  const win = { location: { hash, pathname: '/treasury-tech-selection-guide/thank-you/', search: '' } };
  if (navType !== null) {
    win.performance = { getEntriesByType: (t) => (t === 'navigation' ? [{ type: navType }] : []) };
  }
  if (withGtag) {
    win.gtag = function () { gtagCalls.push(Array.from(arguments)); };
  }
  const context = { window: win, performance: win.performance };
  vm.createContext(context);
  vm.runInContext(thankYou, context);
  const fromDl = (win.dataLayer || []).map((a) => Array.from(a));
  return gtagCalls.concat(fromDl).filter((a) => a[0] === 'event');
}

for (const navType of ['navigate', null]) {
  const ev = runThankYou({ navType });
  assert.strictEqual(ev.length, 1, `thank-you (${navType}): one fallback event when the form did not count`);
  assert.strictEqual(ev[0][1], 'generate_lead');
  assert.deepStrictEqual(JSON.parse(JSON.stringify(ev[0][2])), { form_name: 'tech-selection-guide', lead_page: LEAD_PAGE });
}
assert.strictEqual(runThankYou({ withGtag: true }).length, 1, 'thank-you: gtag path fires once');
for (const navType of ['reload', 'back_forward']) {
  assert.strictEqual(runThankYou({ navType }).length, 0, `thank-you: a ${navType} must not re-fire generate_lead`);
}
assert.strictEqual(runThankYou({ hash: '#rt-lead-counted' }).length, 0,
  'thank-you: must not fire when the form already counted the lead');
assert.strictEqual(runThankYou({ hash: '#other' }).length, 1, 'thank-you: an unrelated fragment still counts');
assert.ok(!/history\.(replace|push)State/.test(thankYou),
  'thank-you: rewriting the URL would register a GA4 history-change page_view');

// ---- download form ----------------------------------------------------------
const download = read('wordpress-page.html');

assert.ok(!/var LE = window\.RTGLeadEvents/.test(download),
  'download page must not cache RTGLeadEvents at load: the plugin enqueues it in the footer, after this script');
assert.ok(/function leadEvents\(\)\s*\{\s*return window\.RTGLeadEvents \|\| null;\s*\}/.test(download),
  'download page must look the helper up through leadEvents()');

const submitIdx = download.indexOf("fetch(API_BASE + '/submit'");
assert.notStrictEqual(submitIdx, -1, 'download page must POST to /submit');
const leIdx = download.indexOf('var LE = leadEvents();');
assert.ok(leIdx !== -1 && leIdx < submitIdx, 'LE must be resolved at submit time, before the /submit fetch');
const sourceIdx = download.indexOf('payload.source = LE ? LE.getSource() : {};');
assert.ok(sourceIdx > leIdx && sourceIdx < submitIdx, 'payload.source must be set from LE.getSource() before /submit');

assert.strictEqual((download.match(/LE\.trackLead\(/g) || []).length, 1, 'download page must call LE.trackLead exactly once');
const trackIdx = download.indexOf('LE.trackLead(');
const parseIdx = download.indexOf('.then(parseJsonOrThrow)', submitIdx);
assert.ok(parseIdx !== -1 && parseIdx < trackIdx, 'trackLead must run after the /submit response is parsed');
const catchIdx = download.indexOf('.catch(', trackIdx);
assert.ok(catchIdx !== -1 && trackIdx < catchIdx, 'trackLead must sit in the success branch, before the .catch');
const trackSnippet = (download.match(/LE\.trackLead\(\s*\{[^]*?\}\s*\)\s*;/) || [''])[0];
assert.ok(/asset:\s*ASSET_SLUG/.test(trackSnippet), 'trackLead must set asset: ASSET_SLUG (never empty)');
assert.ok(/formId:\s*schema\.form_id/.test(trackSnippet), 'trackLead must set formId');

// Extract the success-branch lines from `var counted` through the redirect and run them.
const startIdx = download.indexOf('var counted = false;');
const redirectLine = download.indexOf('window.location.href = counted', startIdx);
assert.ok(startIdx !== -1 && startIdx < trackIdx && redirectLine > trackIdx,
  'download page must decide `counted` around trackLead and redirect on it');
const branch = download.slice(startIdx, download.indexOf('\n', redirectLine));

// le: undefined = rt-gate #91 not live; otherwise what trackLead returns
// (true = handed to window.rtTrack, false = theme not live, 'throw' = helper error).
function runForm(le) {
  const win = { location: { href: '' } };
  const calls = [];
  const LE = le === undefined ? null : {
    trackLead: (o) => { calls.push(o); if (le === 'throw') { throw new Error('x'); } return le; },
  };
  const fn = new Function('window', 'LE', 'CONFIG_REDIRECT_URL', 'resp', 'schema', 'ASSET_SLUG', 'collected', branch);
  fn(win, LE, THANK_YOU_URL, null, { form_id: 2 }, 'treasury-tech-selection-guidebook', { email: 'a@b.co' });
  return { href: win.location.href, calls };
}

// ---- every release combination counts exactly once ----------------------------
const cases = [
  ['rt-gate and theme not live', undefined, 0],
  ['rt-gate live, theme not live', false, 0],
  ['rt-gate and theme live', true, 1],
  ['helper throws', 'throw', 0],
];
for (const [label, le, formEvents] of cases) {
  const { href, calls } = runForm(le);
  assert.ok(href.startsWith(THANK_YOU_URL), `${label}: redirects to the thank-you page`);
  assert.strictEqual(href.endsWith('#rt-lead-counted'), formEvents === 1, `${label}: fragment only when the form counted`);
  const hash = href.includes('#') ? href.slice(href.indexOf('#')) : '';
  assert.strictEqual(formEvents + runThankYou({ hash }).length, 1, `${label}: exactly one generate_lead per guide submit`);
  if (le !== undefined) { assert.strictEqual(calls.length, 1, `${label}: trackLead called once`); }
}
// Old live thank-you page (no fragment check) + new form is the one double-count
// combination; wp/deploy.tsv must publish guide-thank-you before guide-download.
{
  const rows = fs.readFileSync(path.join(__dirname, '..', '..', 'wp', 'deploy.tsv'), 'utf8')
    .split('\n').map((l) => l.trim()).filter((l) => l && !l.startsWith('#'));
  const ty = rows.indexOf('guide-thank-you');
  const dl = rows.indexOf('guide-download');
  assert.ok(ty !== -1 && dl !== -1 && ty < dl, 'deploy.tsv: guide-thank-you must be listed before guide-download');
}

console.log('guide GA4 / lead-event checks passed');
