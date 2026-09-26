// Runs treasury-tech-market/index.html's inline script in a node vm with a
// minimal fake window/document, in the same node-only style as the other
// static-page checks (see test_guide_ga_events.js, test_homepage_carousel.js).
//
// Covered:
//   1. the script has no "&&" - WordPress texturize mangles that operator on
//      publish (see test_guide_ga_events.js for the same guard)
//   2. ttpTrack() calls window.rtTrack when the theme has defined it
//   3. ttpTrack() no-ops silently when rtTrack is absent and there is no
//      parent frame to fall back to (this page is not embedded in an iframe)
//   4. ttpTrack() falls back to postMessage when running in a cross-origin
//      iframe and rtTrack is unreachable
//   5. each of the three "Find Your Path" CTA links fires cta_click once,
//      with the visible button label and a fixed location
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const PAGE = path.join(__dirname, '..', '..', 'treasury-tech-market', 'index.html');

function extractScript() {
  const html = fs.readFileSync(PAGE, 'utf8');
  const blocks = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)].map((m) => m[1]);
  assert.strictEqual(blocks.length, 1, 'expected exactly one inline script in treasury-tech-market/index.html');
  assert.ok(!blocks[0].includes('&&'), 'double-ampersand in inline JS is mangled by WP texturize on publish');
  return blocks[0];
}

const SCRIPT = extractScript();

// Objects built inside the vm sandbox's realm read as "same structure but
// not reference-equal" to assert.deepStrictEqual against plain literals in
// this file's realm. Round-trip through JSON to normalize before comparing.
function plain(value) {
  return JSON.parse(JSON.stringify(value));
}

// --- minimal fake DOM -------------------------------------------------------
function makeCtaLink(titleText) {
  const listeners = {};
  return {
    addEventListener(ev, fn) {
      (listeners[ev] = listeners[ev] || []).push(fn);
    },
    click() {
      (listeners.click || []).forEach((fn) => fn());
    },
    querySelector(sel) {
      return sel === '.cta-title' ? { textContent: titleText } : null;
    }
  };
}

// Boots the script in a fresh sandbox. `rtTrack`, when given, is installed on
// the fake window before the script runs. `parentWindow`, when given, models
// window.parent being a different (cross-origin) window than window itself;
// omitting it models the page's normal, un-embedded case (window.parent ===
// window), where there is nowhere to postMessage to.
function boot({ rtTrack, parentWindow } = {}) {
  const ctaLinks = [
    makeCtaLink('Strategy Session'),
    makeCtaLink('Tech Portal'),
    makeCtaLink('On-Demand Workshop')
  ];

  const domListeners = {};
  const doc = {
    addEventListener(ev, fn) {
      (domListeners[ev] = domListeners[ev] || []).push(fn);
    },
    querySelectorAll(sel) {
      return sel === '.cta-section .cta-card' ? ctaLinks : [];
    }
  };

  const win = {};
  if (rtTrack) {
    win.rtTrack = rtTrack;
  }
  win.parent = parentWindow || win;

  const sandbox = { document: doc, window: win, console };
  vm.createContext(sandbox);
  vm.runInContext(SCRIPT, sandbox);

  (domListeners.DOMContentLoaded || []).forEach((fn) => fn());

  return { win, ctaLinks, sandbox };
}

// --- 2. calls rtTrack when present ------------------------------------------
{
  const calls = [];
  const { ctaLinks } = boot({ rtTrack: (name, params) => calls.push([name, params]) });

  ctaLinks[0].click();

  assert.strictEqual(calls.length, 1, 'expected exactly one rtTrack call');
  assert.deepStrictEqual(plain(calls[0]), [
    'cta_click',
    { cta: 'Strategy Session', location: 'market_find_your_path' }
  ]);
}

// --- 3. no-ops when rtTrack is absent and there is no parent frame ---------
{
  const posted = [];
  const { ctaLinks } = boot({});
  // window.parent === window here (boot()'s default), so ttpTrack has
  // nowhere to postMessage to even though it would try.
  assert.doesNotThrow(() => ctaLinks[1].click());
  assert.strictEqual(posted.length, 0);
}

// --- 4. falls back to postMessage when embedded cross-origin --------------
{
  const posted = [];
  const parentWindow = { postMessage: (message, targetOrigin) => posted.push([message, targetOrigin]) };
  const { ctaLinks } = boot({ parentWindow });

  ctaLinks[2].click();

  assert.strictEqual(posted.length, 1, 'expected exactly one postMessage call');
  const [message, targetOrigin] = posted[0];
  assert.strictEqual(targetOrigin, 'https://realtreasury.com');
  assert.deepStrictEqual(plain(message), {
    source: 'rt',
    type: 'rt:track',
    name: 'cta_click',
    params: { cta: 'On-Demand Workshop', location: 'market_find_your_path' }
  });
}

// --- 5. each CTA fires with its own label, once per click ------------------
{
  const calls = [];
  const { ctaLinks } = boot({ rtTrack: (name, params) => calls.push([name, params]) });

  ctaLinks.forEach((link) => link.click());
  ctaLinks[0].click(); // a second click on the first CTA is a second event

  assert.strictEqual(calls.length, 4);
  const labels = calls.map((c) => c[1].cta);
  assert.deepStrictEqual(labels, [
    'Strategy Session',
    'Tech Portal',
    'On-Demand Workshop',
    'Strategy Session'
  ]);
  calls.forEach((c) => assert.strictEqual(c[1].location, 'market_find_your_path'));
}

console.log('market ttpTrack checks passed');
