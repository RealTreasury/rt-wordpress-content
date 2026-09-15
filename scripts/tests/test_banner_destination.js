// Guards the site-wide banner's destination against the one way it fails quietly.
//
// The banner carries the registration URL TWICE: on the CTA's href, and in the
// LIVE_EVENT_REGISTRATION_URL const. Every click path — the desktop CTA
// (registerLive), the whole-bar mobile target (expandBanner), and the minimized bar —
// calls preventDefault() and navigates via the const, so the href is decoration. A swap
// that updates only the href looks completely correct in review and in the rendered page,
// and sends every visitor to the previous event.
//
// So: the two must agree, and no id from a retired destination may survive anywhere in
// the file. Neither check needs a browser, and both are exactly the mistake this file's
// history keeps producing.

'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');

const FILE = path.join(__dirname, '..', '..', 'header', 'main-menu', 'index.html');
const html = fs.readFileSync(FILE, 'utf8');

function ctaHref() {
  const m = html.match(/<a\s+href="([^"]+)"\s+class="banner-cta"/);
  assert.ok(m, 'no <a class="banner-cta"> with an href in header/main-menu/index.html');
  return m[1];
}

function registrationConst() {
  const m = html.match(/const\s+LIVE_EVENT_REGISTRATION_URL\s*=\s*'([^']+)'/);
  assert.ok(m, 'LIVE_EVENT_REGISTRATION_URL is gone from header/main-menu/index.html');
  return m[1];
}

const cases = {
  'the CTA href and the const that actually navigates agree'() {
    assert.strictEqual(ctaHref(), registrationConst(),
      'the banner CTA href and LIVE_EVENT_REGISTRATION_URL point at different places — ' +
      'the const is the one that navigates, so visitors would go to the href-less one');
  },

  'every navigation path reads the const, not the href'() {
    // If a handler ever starts using the href, the check above stops being the whole story.
    const handlers = ['function registerLive', 'function expandBanner'];
    for (const h of handlers) {
      const start = html.indexOf(h);
      assert.notStrictEqual(start, -1, h + ' is gone');
      const body = html.slice(start, html.indexOf('\n}', start));
      assert.ok(/LIVE_EVENT_REGISTRATION_URL/.test(body), h + ' no longer navigates via the const');
    }
  },

  'the destination is an absolute https URL'() {
    assert.match(registrationConst(), /^https:\/\/[^\s"']+$/, 'the destination is not an absolute https URL');
  },

  'no retired destination survives anywhere in the file'() {
    // A swap that misses one of the two edit points usually leaves the old id behind.
    const retired = [
      '52ec549d-0107-4908-a71d-21a4844f4afe',   // Sept 15 2026 "Make AFP Count" Teams event
    ];
    for (const id of retired) {
      assert.ok(!html.includes(id), 'a retired destination is still referenced: ' + id);
    }
  },

  'the banner title still fits on one line'() {
    // A long .banner-highlight is what has pushed the banner under the nav before.
    const m = html.match(/<span class="banner-highlight">([^<]+)<\/span>/);
    assert.ok(m, 'no .banner-highlight in the banner');
    const title = m[1].trim();
    assert.ok(title.length > 0 && title.length <= 28,
      `banner title is ${title.length} chars ("${title}") — over 28 it wraps at 375px`);
  },
};

let failed = 0;
for (const [name, fn] of Object.entries(cases)) {
  try { fn(); console.log('ok   ' + name); }
  catch (e) { failed++; console.error('FAIL ' + name + '\n     ' + e.message); }
}
if (failed) { console.error(failed + ' banner check(s) failed'); process.exit(1); }
console.log('all banner destination checks passed');
