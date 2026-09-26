// Guide funnel lead counting (see docs/11-GATED-PAGE-CONFIG.md, "Lead events
// and session source", and rt-guide-ga4-funnel-events.md): the guide lead is
// counted ONCE, at the download form, via window.RTGLeadEvents. This test
// pins that decision:
//
//   - the thank-you page (reached after a successful submit) fires no lead
//     event at all -- it used to fire GA4 `generate_lead` on load (PR #919);
//     that was removed so the guide lead isn't double-counted.
//   - the guide download page (../wordpress-page.html) sends `payload.source`
//     from the lead-events helper on every submit, and calls
//     `window.RTGLeadEvents.trackLead(...)` with a real asset, only in the
//     success branch (after the /submit response is parsed, before the
//     redirect to the thank-you page), never inside the catch handler.
//
// Static text checks, in the same style as the other page checks in this
// repo -- no DOM/JS harness.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..', 'treasury-tech-selection', 'guidebook');

function read(rel) {
  return fs.readFileSync(path.join(root, rel), 'utf8');
}

// ---- thank-you: no lead event of any kind -----------------------------
const thankYou = read(path.join('thank-you', 'wordpress-page.html'));
assert.ok(!/<script/.test(thankYou), 'thank-you page must not carry a <script> block');
assert.ok(!thankYou.includes('generate_lead\''), 'thank-you page must not fire generate_lead');
assert.ok(!thankYou.includes('trackLead('), 'thank-you page must not call trackLead');

// ---- download page: source + trackLead, success branch only ----------
const download = read('wordpress-page.html');

assert.ok(
  /var LE = window\.RTGLeadEvents \|\| null;/.test(download),
  'download page must read window.RTGLeadEvents into LE'
);
assert.ok(
  /payload\.source = LE \? LE\.getSource\(\) : \{\};/.test(download),
  'download page must set payload.source from LE.getSource() on every submit'
);

const submitIdx = download.indexOf("fetch(API_BASE + '/submit'");
assert.notStrictEqual(submitIdx, -1, 'download page must POST to /submit');

const sourceIdx = download.indexOf('payload.source = LE');
assert.ok(sourceIdx !== -1 && sourceIdx < submitIdx, 'payload.source must be set before the /submit fetch');

const trackCalls = download.match(/LE\.trackLead\(/g) || [];
assert.strictEqual(trackCalls.length, 1, 'download page must call LE.trackLead exactly once');

const trackIdx = download.indexOf('LE.trackLead(');
assert.ok(trackIdx > submitIdx, 'trackLead must run after the /submit call');

const parseIdx = download.indexOf('.then(parseJsonOrThrow)', submitIdx);
assert.ok(parseIdx !== -1 && parseIdx < trackIdx, 'trackLead must run after the /submit response is parsed');

const redirectIdx = download.indexOf('window.location.href = CONFIG_REDIRECT_URL', trackIdx);
assert.ok(redirectIdx !== -1 && trackIdx < redirectIdx, 'trackLead must run before the thank-you page redirect');

const catchIdx = download.indexOf('.catch(', trackIdx);
assert.ok(catchIdx !== -1 && trackIdx < catchIdx, 'trackLead must sit in the success branch, before the .catch');

const trackSnippet = (download.match(/LE\.trackLead\(\s*\{[^]*?\}\s*\)\s*;/) || [''])[0];
assert.ok(trackSnippet, 'could not isolate the trackLead(...) call body');
assert.ok(/asset:\s*ASSET_SLUG/.test(trackSnippet), 'trackLead must set asset: ASSET_SLUG (never empty)');
assert.ok(/formId:\s*schema\.form_id/.test(trackSnippet), 'trackLead must set formId');

console.log('guide GA4 / lead-event checks passed');
