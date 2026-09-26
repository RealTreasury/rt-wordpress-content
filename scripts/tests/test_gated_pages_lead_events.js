// Static checks that every page wired to RT Gate's lead-events helper
// (window.RTGLeadEvents, the rt-gate plugin repo's docs/11-GATED-PAGE-CONFIG.md "Lead events and
// session source") actually follows the contract:
//
//   - an iframe page (served from GitHub Pages inside a realtreasury.com
//     iframe) loads the helper by URL, before it is used;
//   - every submit sends `payload.source` from `LE.getSource()`;
//   - `LE.trackLead(...)` fires once, in the success branch (after the
//     `/submit` response is parsed, before any `.catch`), never inside the
//     catch handler itself.
//
// This is a plain-text/regex check on the committed HTML, not a DOM/JS
// harness -- consistent with the other static page checks in this repo
// (test_iframe_height, test_banner_destination, etc).
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');

const root = path.join(__dirname, '..', '..');

const LEAD_EVENTS_SCRIPT_TAG =
  '<script src="https://realtreasury.com/wp-content/plugins/rt-gate/assets/js/rtg-lead-events.js"></script>';

const PAGES = [
  { file: 'templates/partials/gated-video.html', iframe: true },
  { file: 'webinars/prompt-to-product/index.html', iframe: true },
  { file: 'webinars/tms-rfp-trap/index.html', iframe: true },
  { file: 'webinars/3-segments-1-smart-choice/index.html', iframe: true },
  { file: 'webinars/3-segments-1-smart-choice-emea/index.html', iframe: true },
  { file: 'webinars/err-not-demo-script/index.html', iframe: true },
  { file: 'treasury-tech-selection/waitlist/index.html', iframe: true },
  { file: 'events/2026/afp/index.html', iframe: true },
  // Native WordPress page: the plugin enqueues the helper on every
  // front-end page, so this one does NOT carry the <script src> tag.
  { file: 'treasury-tech-selection/guidebook/wordpress-page.html', iframe: false },
];

let failures = 0;

function check(label, condition) {
  if (!condition) {
    failures += 1;
    console.error('FAIL: ' + label);
  }
}

for (const page of PAGES) {
  const abs = path.join(root, page.file);
  const text = fs.readFileSync(abs, 'utf8');
  const label = page.file;

  if (page.iframe) {
    const tagIdx = text.indexOf(LEAD_EVENTS_SCRIPT_TAG);
    check(label + ': helper <script src> tag present', tagIdx !== -1);
    const usageIdx = text.indexOf('window.RTGLeadEvents');
    check(
      label + ': helper script tag loads before it is referenced',
      tagIdx !== -1 && usageIdx !== -1 && tagIdx < usageIdx
    );
  } else {
    check(
      label + ': native page relies on the plugin enqueue, no <script src> tag',
      !text.includes(LEAD_EVENTS_SCRIPT_TAG)
    );
  }

  // getSource() feeds payload.source, sent with every submit.
  check(
    label + ': payload.source set from LE.getSource()',
    /payload\.source\s*=\s*LE\s*\?\s*LE\.getSource\(\)\s*:\s*\{\}/.test(text)
  );

  // Exactly one trackLead call, and it must sit between the /submit fetch
  // and the first .catch that follows it -- i.e. in the success path, never
  // in the catch handler.
  const trackCalls = text.match(/LE\.trackLead\(/g) || [];
  check(label + ': exactly one LE.trackLead( call', trackCalls.length === 1);

  const submitIdx = text.indexOf("/submit'");
  check(label + ": has a '/submit' fetch call", submitIdx !== -1);

  const trackIdx = text.indexOf('LE.trackLead(');
  check(
    label + ': trackLead sits after the /submit call',
    trackIdx !== -1 && submitIdx !== -1 && trackIdx > submitIdx
  );
  // The .catch that closes THIS submit chain is the first one after
  // trackLead, not the first one after the /submit call -- a page can
  // define other, unrelated promise chains (e.g. sendEvent()'s own
  // internal .catch) in between the two.
  const chainCatchIdx = text.indexOf('.catch(', trackIdx === -1 ? submitIdx : trackIdx);
  check(label + ': has a .catch( closing the submit chain', chainCatchIdx !== -1);
  check(
    label + ': trackLead sits before the chain-closing .catch (success path, not catch)',
    trackIdx !== -1 && chainCatchIdx !== -1 && trackIdx < chainCatchIdx
  );

  // The trackLead call must set a real asset, never an empty string.
  const trackSnippetMatch = text.match(/LE\.trackLead\(\s*\{[^]*?\}\s*\)\s*;/);
  check(label + ': trackLead call body found', !!trackSnippetMatch);
  if (trackSnippetMatch) {
    const snippet = trackSnippetMatch[0];
    check(label + ": trackLead sets 'asset:'", /asset:\s*[^,}]+/.test(snippet));
    check(label + ": trackLead does not set asset to ''", !/asset:\s*''/.test(snippet));
    check(label + ": trackLead sets 'formId:'", /formId:\s*[^,}]+/.test(snippet));
  }
}

// AFP computes its asset at runtime (asset: chosenAssetSlug(chosenTour)), so the
// literal '' check above cannot see an empty result. Run the page's own
// selector against its committed RT_TOUR config for every choice a visitor can
// make, and require a real asset for each. Also pin that the asset and the
// submit's mapping come from one shared selector, so they cannot drift apart.
{
  const label = 'events/2026/afp/index.html';
  const text = fs.readFileSync(path.join(root, label), 'utf8');
  const vm = require('vm');

  function extractFunction(name) {
    const start = text.indexOf('function ' + name + '(');
    if (start === -1) return null;
    let depth = 0;
    for (let i = text.indexOf('{', start); i < text.length; i++) {
      if (text[i] === '{') depth += 1;
      else if (text[i] === '}') {
        depth -= 1;
        if (depth === 0) return text.slice(start, i + 1);
      }
    }
    return null;
  }

  const configMatch = text.match(/window\.RT_TOUR\s*=\s*\{[^]*?\n\};/);
  const selector = extractFunction('mappingConfigFor');
  const assetFn = extractFunction('chosenAssetSlug');
  const resolveFn = extractFunction('resolveMappingId');
  check(label + ': RT_TOUR config found', !!configMatch);
  check(label + ': mappingConfigFor() found', !!selector);
  check(label + ': chosenAssetSlug() found', !!assetFn);
  check(label + ': resolveMappingId() found', !!resolveFn);
  if (assetFn) {
    check(label + ': chosenAssetSlug() reads mappingConfigFor()', assetFn.includes('mappingConfigFor(chosen)'));
  }
  if (resolveFn) {
    check(label + ': resolveMappingId() reads mappingConfigFor()', resolveFn.includes('mappingConfigFor(chosen)'));
  }

  if (configMatch && selector && assetFn) {
    const sandbox = { window: {} };
    vm.runInNewContext(
      configMatch[0] + '\nvar T = window.RT_TOUR || { sessions: [] };\n' + selector + '\n' + assetFn +
        '\nwindow.chosenAssetSlug = chosenAssetSlug;',
      sandbox
    );
    const T = sandbox.window.RT_TOUR;
    const choices = (T.sessions || []).map((s) => ({ kind: 'session', session: s }));
    choices.push({ kind: 'private' }, { kind: 'none' });
    for (const c of choices) {
      const which = c.kind === 'session' ? 'session ' + c.session.id : c.kind;
      const asset = sandbox.window.chosenAssetSlug(c);
      check(label + ': chosenAssetSlug() is non-empty for ' + which, typeof asset === 'string' && asset !== '');
    }
  }
}

// The guide thank-you page must NOT fire a lead event -- the guide lead is
// now counted once, at the download form (see test_guide_ga_events.js).
{
  const thankYou = fs.readFileSync(
    path.join(root, 'treasury-tech-selection', 'guidebook', 'thank-you', 'wordpress-page.html'),
    'utf8'
  );
  check('guide thank-you page: no <script> block', !/<script/.test(thankYou));
  check('guide thank-you page: no trackLead call', !thankYou.includes('trackLead('));
}

if (failures > 0) {
  console.error(failures + ' check(s) failed.');
  process.exit(1);
}

console.log('gated pages lead-events checks passed (' + PAGES.length + ' pages).');
