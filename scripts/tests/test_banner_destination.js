// Guards the site-wide banner's destinations against the ways they fail quietly.
//
// The banner used to carry one registration URL twice — on the CTA's href and in a
// LIVE_EVENT_REGISTRATION_URL const — and every click path navigated via the const,
// so a swap that updated only the href looked correct in review and in the rendered
// page while sending every visitor to the previous event. That trap is gone: the
// destination now lives once, in BANNER_ITEMS, and both click paths read it back off
// the CTA element. This file keeps it gone, and guards what replaced it.
//
// BANNER_ITEMS is the rota. Each entry is a promotion with an optional inclusive
// date window; the bar shows everything eligible today and rotates when more than
// one is. So the checks below are: the list parses, every entry is complete and
// points somewhere this banner is allowed to send people, every date window is a
// real window, the title still fits on one line, the markup's own default is one of
// the entries (what a no-JS visitor gets), and no retired destination survives.
//
// DESTINATIONS names every page the banner may send people to. Agreement inside the
// file is not enough — an entry could be moved to an unrelated URL and stay
// self-consistent. Changing DESTINATIONS is part of a banner swap, not an obstacle
// to one: it is the line that says which promotions this banner is for.

'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');
const vm = require('vm');

const FILE = path.join(__dirname, '..', '..', 'header', 'main-menu', 'index.html');
const html = fs.readFileSync(FILE, 'utf8');

// The promotions this banner is currently allowed to run.
const DESTINATIONS = [
  'https://realtreasury.com/afp-2026-tms-tour/',
  'https://realtreasury.com/treasury-tech-selection-guide/',
];

// A title over this wraps at 375px, and a wrapped title grows the bar past the
// hardcoded nav offset and pushes the navigation underneath itself.
const TITLE_MAX = 28;

function bannerItems() {
  const start = html.indexOf('const BANNER_ITEMS = [');
  assert.notStrictEqual(start, -1, 'BANNER_ITEMS is gone from header/main-menu/index.html');
  const open = html.indexOf('[', start);
  let depth = 0;
  let end = -1;
  for (let i = open; i < html.length; i++) {
    if (html[i] === '[') depth++;
    else if (html[i] === ']') {
      depth--;
      if (depth === 0) { end = i; break; }
    }
  }
  assert.notStrictEqual(end, -1, 'BANNER_ITEMS is not a closed array literal');
  return vm.runInNewContext('(' + html.slice(open, end + 1) + ')');
}

const items = bannerItems();

const cases = {
  'the banner has at least one promotion to run'() {
    assert.ok(items.length > 0, 'BANNER_ITEMS is empty — the bar would render nothing');
  },

  'at least one promotion is evergreen, so the bar is never empty'() {
    const evergreen = items.filter((i) => !i.start && !i.end);
    assert.ok(evergreen.length > 0,
      'every entry is dated, so the banner goes blank the day the last window closes. ' +
      'Leave one entry without start/end as the fallback.');
  },

  'every promotion is complete'() {
    for (const item of items) {
      const where = 'BANNER_ITEMS entry ' + JSON.stringify(item.key || '(no key)');
      for (const field of ['key', 'title', 'subtitle', 'cta', 'url']) {
        assert.ok(typeof item[field] === 'string' && item[field].trim(),
          where + ' is missing ' + field);
      }
    }
  },

  'promotion keys are unique'() {
    const keys = items.map((i) => i.key);
    assert.strictEqual(new Set(keys).size, keys.length,
      'two BANNER_ITEMS entries share a key: ' + keys.join(', '));
  },

  'every destination is one this banner is for'() {
    for (const item of items) {
      assert.match(item.url, /^https:\/\/[^\s"']+$/,
        item.key + "'s url is not an absolute https URL: " + item.url);
      assert.ok(DESTINATIONS.includes(item.url),
        'the banner would send people to ' + item.url + ' (' + item.key + '), which is not in ' +
        'DESTINATIONS at the top of this file. If the rota moved on, move DESTINATIONS with ' +
        'it; if it did not, the banner is pointing somewhere it should not.');
    }
  },

  'every date window is a real window'() {
    for (const item of items) {
      const dated = 'start' in item || 'end' in item;
      if (!dated) continue;
      for (const field of ['start', 'end']) {
        assert.match(String(item[field]), /^\d{4}-\d{2}-\d{2}$/,
          item.key + "'s " + field + ' is not a YYYY-MM-DD date: ' + item[field] +
          '. A half-filled window is silently ignored and the entry never runs.');
      }
      assert.ok(item.start <= item.end,
        item.key + ' starts after it ends (' + item.start + ' > ' + item.end + ')');
    }
  },

  'every title still fits on one line'() {
    for (const item of items) {
      assert.ok(item.title.length <= TITLE_MAX,
        `${item.key}'s title is ${item.title.length} chars ("${item.title}") — ` +
        `over ${TITLE_MAX} it wraps at 375px and pushes the nav under the banner`);
    }
  },

  "the markup's own default is one of the promotions"() {
    // What a visitor with JavaScript off sees, and what paints before the picker runs.
    const title = html.match(/<span class="banner-highlight">([^<]+)<\/span>/);
    const href = html.match(/<a\s+href="([^"]+)"\s+class="banner-cta"/);
    assert.ok(title, 'no .banner-highlight in the banner markup');
    assert.ok(href, 'no <a class="banner-cta"> with an href in the banner markup');
    const match = items.find((i) => i.title === title[1].trim());
    assert.ok(match,
      'the markup shows "' + title[1].trim() + '", which is not in BANNER_ITEMS — a no-JS ' +
      'visitor gets a promotion the rota no longer runs');
    assert.strictEqual(href[1], match.url,
      'the markup\'s CTA href is ' + href[1] + ' but ' + match.key + ' points at ' + match.url);
  },

  'both click paths read the live destination, not a stale copy'() {
    assert.ok(!/LIVE_EVENT_REGISTRATION_URL/.test(html),
      'LIVE_EVENT_REGISTRATION_URL is back. It is the trap this file exists for: a second ' +
      'copy of the destination that the clicks use and the markup does not.');
    for (const h of ['function registerLive', 'function expandBanner']) {
      const start = html.indexOf(h);
      assert.notStrictEqual(start, -1, h + ' is gone');
      const body = html.slice(start, html.indexOf('\n}', start));
      assert.ok(/bannerDestination\(\)/.test(body),
        h + ' no longer navigates via bannerDestination(), so it can drift from the CTA');
    }
  },

  'the rotation can be held and cannot run on one promotion'() {
    assert.ok(/prefers-reduced-motion/.test(html),
      'the rotation no longer honours prefers-reduced-motion');
    assert.ok(/BANNER_LINEUP\.length < 2/.test(html),
      'startBannerRotation no longer refuses to rotate a single promotion');
    for (const hold of ['mouseenter', 'focusin']) {
      assert.ok(new RegExp("'" + hold + "', stopBannerRotation").test(html),
        'the rotation no longer pauses on ' + hold + ' — a moving CTA is hard to click');
    }
    assert.ok(/focusout'[\s\S]*banner\.contains\(event\.relatedTarget\)/.test(html),
      'moving focus between controls inside the banner must not restart rotation');
    assert.ok(/clearTimeout\(bannerSwapTimer\)/.test(html),
      'pausing during a crossfade must cancel the queued promotion swap');
  },

  'no retired destination survives anywhere in the file'() {
    // A swap that misses an edit point usually leaves the old id behind.
    const retired = [
      '52ec549d-0107-4908-a71d-21a4844f4afe',   // Sept 15 2026 "Make AFP Count" Teams event
    ];
    for (const id of retired) {
      assert.ok(!html.includes(id), 'a retired destination is still referenced: ' + id);
    }
  },
};

let failed = 0;
for (const [name, fn] of Object.entries(cases)) {
  try { fn(); console.log('ok   ' + name); }
  catch (e) { failed++; console.error('FAIL ' + name + '\n     ' + e.message); }
}
if (failed) { console.error(failed + ' banner check(s) failed'); process.exit(1); }
console.log('all banner checks passed (' + items.length + ' promotions)');
