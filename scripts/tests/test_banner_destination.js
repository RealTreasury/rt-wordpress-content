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
    // Membership alone kept passing on an EXPIRED default: the entry stays in
    // BANNER_ITEMS after its window closes, and a visitor without JavaScript
    // goes on being sent to the finished event with nothing failing. The
    // default has to be one that cannot expire.
    assert.ok(!match.start && !match.end,
      'the markup default is "' + match.key + '", which is dated (' + match.start + ' to ' +
      match.end + '). A no-JS visitor still sees it after the window closes. Put an ' +
      'evergreen promotion in the markup; bannerLineup() swaps the dated one in before paint.');
  },

  'the whole-banner click reads the live destination and the CTA remains a native link'() {
    assert.ok(!/LIVE_EVENT_REGISTRATION_URL/.test(html),
      'LIVE_EVENT_REGISTRATION_URL is back. It is the trap this file exists for: a second ' +
      'copy of the destination that the clicks use and the markup does not.');
    for (const h of ['function expandBanner']) {
      const start = html.indexOf(h);
      assert.notStrictEqual(start, -1, h + ' is gone');
      const body = html.slice(start, html.indexOf('\n}', start));
      assert.ok(/bannerDestination\(\)/.test(body),
        h + ' no longer navigates via bannerDestination(), so it can drift from the CTA');
    }
    assert.ok(!/function registerLive/.test(html),
      'registerLive duplicates ordinary anchor navigation and prevents modified clicks');
    assert.ok(!/onclick="registerLive\(event\)"/.test(html),
      'the CTA should use its native anchor behavior');
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

// --------------------------------------------------------------------------
// Everything above reads the page as text. That is enough for "is the value
// right", and useless for "does the code do what the comment says" — the
// rota's eligibility, ordering and CTA rewriting were asserted only by
// grepping for literal source strings, which pass on code that does not run.
// So: lift the real functions out of the page and execute them, the same way
// test_homepage_carousel.js drives the carousel IIFE.
// --------------------------------------------------------------------------

function bannerScript() {
  const start = html.indexOf('const BANNER_ITEMS = [');
  assert.notStrictEqual(start, -1, 'BANNER_ITEMS is gone');
  const end = html.indexOf('// Runs synchronously, before first paint', start);
  assert.notStrictEqual(end, -1,
    'the marker above the top-level rota bootstrap moved; this harness lifts the ' +
    'pure functions only, and stops before the code that touches the live DOM');
  return html.slice(start, end);
}

// The rest of the rota: the pre-paint bootstrap that builds BANNER_LINEUP, and
// startBannerRotation/stopBannerRotation. Lifted separately because it declares
// module state and touches the banner element, so it only runs where the stub
// DOM and the virtual clock are in place.
function bannerBootstrapScript() {
  const start = html.indexOf('// Runs synchronously, before first paint');
  assert.notStrictEqual(start, -1, 'the pre-paint bootstrap marker moved');
  const end = html.indexOf('// Add banner state management', start);
  assert.notStrictEqual(end, -1,
    'the marker below stopBannerRotation moved; this harness lifts the bootstrap and the ' +
    'rotation only, and stops before the rest of the banner behaviour');
  return html.slice(start, end);
}

// The hover/focus wiring is a handful of addEventListener calls inside the load
// handler. Lifting the slice means the tests fire the listeners the page really
// registers, not a re-typed copy of them.
function bannerWiringScript() {
  const start = html.indexOf('// Rotate only when more than one promotion is in window today.');
  assert.notStrictEqual(start, -1, 'the rotation wiring comment moved');
  const end = html.indexOf('// Expand banner on hover when minimized', start);
  assert.notStrictEqual(end, -1, 'the marker below the rotation wiring moved');
  return html.slice(start, end);
}

// A DOM stub with only what applyBannerItem() reaches for.
function makeNode(cls) {
  const node = {
    className: cls || '',
    classes: new Set(),
    attrs: {},
    style: {},
    listeners: {},
    _text: '',
    childNodes: [],
    get textContent() { return this._text; },
    set textContent(v) { this._text = String(v); this.childNodes = [{ nodeType: 3, nodeValue: String(v) }]; },
    setAttribute(k, v) { this.attrs[k] = String(v); },
    getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
    insertBefore(node, ref) { this.childNodes.unshift(node); },
    get firstChild() { return this.childNodes[0] || null; },
    addEventListener(type, fn) { (this.listeners[type] || (this.listeners[type] = [])).push(fn); },
    // The rotation's hold/resume is wired entirely through listeners, so the
    // tests below have to be able to fire them.
    dispatch(type, event) { (this.listeners[type] || []).forEach((fn) => fn(event || {})); },
    contains(other) { return other === this; },
  };
  node.classList = {
    toggle(name, on) { on ? node.classes.add(name) : node.classes.delete(name); },
    add(name) { node.classes.add(name); },
    remove(name) { node.classes.delete(name); },
    contains(name) { return node.classes.has(name); },
  };
  return node;
}

// A virtual clock, so the 8s rota and the 260ms crossfade are asserted rather
// than waited on. Timers fire in due order; an interval re-arms itself.
function makeClock() {
  let now = 0;
  let seq = 0;
  const timers = new Map();
  return {
    count: () => timers.size,
    api: {
      setInterval(fn, ms) { const id = ++seq; timers.set(id, { fn, due: now + ms, every: ms }); return id; },
      setTimeout(fn, ms) { const id = ++seq; timers.set(id, { fn, due: now + ms, every: null }); return id; },
      clearInterval(id) { timers.delete(id); },
      clearTimeout(id) { timers.delete(id); },
    },
    advance(ms) {
      const target = now + ms;
      for (;;) {
        let next = null;
        for (const entry of timers) {
          if (entry[1].due <= target && (!next || entry[1].due < next[1].due)) next = entry;
        }
        if (!next) break;
        now = next[1].due;
        if (next[1].every === null) timers.delete(next[0]);
        else next[1].due = now + next[1].every;
        next[1].fn();
      }
      now = target;
    },
  };
}

function makeBanner() {
  const icon = makeNode('banner-icon');
  icon.textContent = 'RT';
  const title = makeNode('banner-highlight');
  const subtitle = makeNode('banner-subtitle');
  const cta = makeNode('banner-cta');
  // The shipped CTA is a text node followed by the arrow <span>.
  cta.childNodes = [{ nodeType: 3, nodeValue: 'Register Now ' }, { nodeType: 1, tag: 'SPAN' }];
  const banner = makeNode('workshop-banner');
  banner.dataset = {};
  const map = { '.banner-icon': icon, '.banner-highlight': title, '.banner-subtitle': subtitle, '.banner-cta': cta };
  banner.querySelector = (sel) => map[sel] || null;
  return { banner, icon, title, subtitle, cta };
}

// Runs the lifted functions with BANNER_ITEMS replaced and the clock pinned.
function runRota(itemsOverride, today, opts) {
  opts = opts || {};
  const dom = makeBanner();
  const clock = makeClock();
  const ctx = {
    document: { getElementById: (id) => (id === 'workshopBanner' ? dom.banner : null), createTextNode: (v) => ({ nodeType: 3, nodeValue: v }) },
    window: {
      matchMedia: (q) => ({
        matches: /prefers-reduced-motion/.test(q) ? opts.reducedMotion === true : false,
      }),
    },
    setInterval: clock.api.setInterval,
    clearInterval: clock.api.clearInterval,
    setTimeout: clock.api.setTimeout,
    clearTimeout: clock.api.clearTimeout,
    Intl: { DateTimeFormat: function () { return { format: () => today }; } },
    Date,
    console,
  };
  vm.createContext(ctx);
  vm.runInContext(bannerScript(), ctx);
  // Swap in the fixture AFTER the page's own list is defined.
  vm.runInContext('BANNER_ITEMS.length = 0; Array.prototype.push.apply(BANNER_ITEMS, ' +
    JSON.stringify(itemsOverride) + ');', ctx);
  // ...and before the bootstrap freezes the lineup, when the caller wants the
  // real rotation rather than just the pure functions.
  if (opts.rotate) {
    vm.runInContext(bannerBootstrapScript(), ctx);
    vm.runInContext('(function (banner) {' + bannerWiringScript() + '})(' +
      'document.getElementById("workshopBanner"));', ctx);
  }
  // Array.from re-homes the result: an array built inside the VM carries that
  // realm's Array.prototype, and deepStrictEqual compares prototypes, so a
  // correct lineup would fail the comparison for the wrong reason.
  const keys = () => Array.from(vm.runInContext(
    'bannerLineup().map(function (i) { return i.key; })', ctx));
  return { ctx, dom, clock, keys, title: () => dom.title.textContent };
}

const DATED = { key: 'dated', title: 'Dated', subtitle: 's', cta: 'Go', url: 'https://realtreasury.com/a/', start: '2026-09-15', end: '2026-11-10' };
const EVER = { key: 'ever', title: 'Ever', subtitle: 's', cta: 'Get', url: 'https://realtreasury.com/b/' };

const runtimeCases = {
  'a date window includes both its first and its last day'() {
    for (const day of ['2026-09-15', '2026-10-01', '2026-11-10']) {
      const keys = runRota([DATED, EVER], day).keys();
      assert.deepStrictEqual(keys, ['dated', 'ever'], 'on ' + day + ' the dated entry should be live');
    }
  },

  'a window that has not opened, or has closed, drops the entry'() {
    for (const day of ['2026-09-14', '2026-11-11']) {
      const keys = runRota([DATED, EVER], day).keys();
      assert.deepStrictEqual(keys, ['ever'],
        'on ' + day + ' the dated entry is outside its window and must not run');
    }
  },

  'dated promotions sort ahead of evergreen ones'() {
    const keys = runRota([EVER, DATED], '2026-10-01').keys();
    assert.deepStrictEqual(keys, ['dated', 'ever'],
      'the promotion with a deadline must paint first, whatever order the list is in');
  },

  'a half-dated entry is dropped, not treated as evergreen'() {
    const half = Object.assign({}, DATED, { key: 'half', end: undefined });
    const keys = runRota([half, EVER], '2026-10-01').keys();
    assert.deepStrictEqual(keys, ['ever'],
      'an entry with a start and no end is a typo, and running it forever is the wrong guess');
  },

  'an entry without an https destination never renders'() {
    const todo = Object.assign({}, EVER, { key: 'todo', url: '[TO CONFIRM]' });
    const keys = runRota([todo, EVER], '2026-10-01').keys();
    assert.deepStrictEqual(keys, ['ever'], 'a placeholder URL would render a dead call to action');
  },

  'applying a promotion rewrites the CTA label and keeps the arrow'() {
    const r = runRota([EVER], '2026-10-01');
    vm.runInContext('applyBannerItem(BANNER_ITEMS[0]);', r.ctx);
    assert.strictEqual(r.dom.title.textContent, 'Ever');
    assert.strictEqual(r.dom.cta.getAttribute('href'), EVER.url);
    assert.strictEqual(r.dom.cta.childNodes[0].nodeValue, 'Get ');
    assert.strictEqual(r.dom.cta.childNodes.length, 2, 'the arrow <span> was dropped');
    assert.strictEqual(r.dom.cta.childNodes[1].tag, 'SPAN');
  },

  'a logo promotion clears the "RT" the icon would paint over it'() {
    const forum = Object.assign({}, EVER, { key: 'forum', label: 'Treasury Tech Virtual Forum', logo: true });
    const r = runRota([forum], '2026-10-01');
    vm.runInContext('applyBannerItem(BANNER_ITEMS[0]);', r.ctx);
    assert.ok(r.dom.icon.classes.has('banner-logo'), 'the logo class was not applied');
    assert.strictEqual(r.dom.icon.textContent, '',
      '.banner-logo only paints a background image; leaving "RT" renders it over the mark');
    assert.strictEqual(r.dom.icon.getAttribute('aria-label'), 'Treasury Tech Virtual Forum');
  },

  'an RT promotion puts the "RT" tile back'() {
    const r = runRota([EVER], '2026-10-01');
    vm.runInContext('applyBannerItem(BANNER_ITEMS[0]);', r.ctx);
    assert.ok(!r.dom.icon.classes.has('banner-logo'));
    assert.strictEqual(r.dom.icon.textContent, 'RT',
      'swapping back from a logo entry must restore the tile, not leave it blank');
  },

  'the rota advances to the next promotion across the crossfade'() {
    const r = runRota([DATED, EVER], '2026-10-01', { rotate: true });
    assert.strictEqual(r.title(), 'Dated', 'the bootstrap should paint the dated entry first');
    r.clock.advance(8000);
    assert.ok(r.dom.banner.classes.has('swapping'),
      'the bar should fade out before the swap, not cut to the next promotion');
    assert.strictEqual(r.title(), 'Dated', 'the swap must land after the fade, not with it');
    r.clock.advance(260);
    assert.strictEqual(r.title(), 'Ever', 'the rota did not advance to the next promotion');
    assert.strictEqual(r.dom.cta.getAttribute('href'), EVER.url,
      'the rota moved the title but left the CTA pointing at the previous promotion');
    assert.ok(!r.dom.banner.classes.has('swapping'), 'the bar was left faded out');
    r.clock.advance(8000 + 260);
    assert.strictEqual(r.title(), 'Dated', 'the rota does not wrap back to the first promotion');
  },

  'a lineup of one never rotates'() {
    const r = runRota([EVER], '2026-10-01', { rotate: true });
    assert.strictEqual(r.clock.count(), 0,
      'a single eligible promotion armed a rotation timer — the bar would crossfade to itself');
    r.clock.advance(8000 * 3);
    assert.strictEqual(r.title(), 'Ever');
    assert.ok(!r.dom.banner.classes.has('swapping'));
  },

  'reduced motion holds the first promotion'() {
    const r = runRota([DATED, EVER], '2026-10-01', { rotate: true, reducedMotion: true });
    assert.strictEqual(r.clock.count(), 0, 'the rotation ignored prefers-reduced-motion');
    r.clock.advance(8000 * 3);
    assert.strictEqual(r.title(), 'Dated',
      'a reduced-motion visitor gets the first entry and it does not move');
  },

  'hovering holds the promotion and leaving resumes the rota'() {
    for (const [hold, resume] of [['mouseenter', 'mouseleave'], ['focusin', 'focusout']]) {
      const r = runRota([DATED, EVER], '2026-10-01', { rotate: true });
      r.dom.banner.dispatch(hold, {});
      r.clock.advance(8000 + 260);
      assert.strictEqual(r.title(), 'Dated',
        hold + ' did not hold the promotion — a moving CTA is hard to read and hard to click');
      r.dom.banner.dispatch(resume, {});
      r.clock.advance(8000 + 260);
      assert.strictEqual(r.title(), 'Ever', resume + ' did not resume the rota');
    }
  },

  'holding during a crossfade cancels the queued swap and clears the fade'() {
    const r = runRota([DATED, EVER], '2026-10-01', { rotate: true });
    r.clock.advance(8000);
    assert.ok(r.dom.banner.classes.has('swapping'), 'the crossfade did not start');
    r.dom.banner.dispatch('mouseenter', {});
    assert.ok(!r.dom.banner.classes.has('swapping'),
      'pausing mid-crossfade left the bar faded out');
    r.clock.advance(8000 + 260);
    assert.strictEqual(r.title(), 'Dated',
      'the queued swap still fired after the pause, so the promotion changed under the cursor');
  },

  'moving focus between controls inside the banner does not restart the rota'() {
    const r = runRota([DATED, EVER], '2026-10-01', { rotate: true });
    r.dom.banner.dispatch('focusin', {});
    // relatedTarget is the banner itself, which banner.contains() reports as inside.
    r.dom.banner.dispatch('focusout', { relatedTarget: r.dom.banner });
    r.clock.advance(8000 + 260);
    assert.strictEqual(r.title(), 'Dated',
      'focus moved from one control in the banner to another and the rota restarted underneath it');
  },

  'an empty lineup hides the bar instead of leaving the default promotion up'() {
    const expired = Object.assign({}, DATED, { key: 'expired' });
    const r = runRota([expired], '2026-11-11', { rotate: true });
    assert.strictEqual(r.dom.banner.style.display, 'none',
      'nothing is eligible, so the bar must hide rather than keep painting the markup default');
    assert.strictEqual(r.clock.count(), 0);
  },

  "the shipped list is live today, so the bar is not blank right now"() {
    const today = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'America/Chicago', year: 'numeric', month: '2-digit', day: '2-digit',
    }).format(new Date());
    const keys = runRota(items, today).keys();
    assert.ok(keys.length > 0, 'nothing in BANNER_ITEMS is eligible today — the bar would hide itself');
  },
};

Object.assign(cases, runtimeCases);

let failed = 0;
for (const [name, fn] of Object.entries(cases)) {
  try { fn(); console.log('ok   ' + name); }
  catch (e) { failed++; console.error('FAIL ' + name + '\n     ' + e.message); }
}
if (failed) { console.error(failed + ' banner check(s) failed'); process.exit(1); }
console.log('all banner checks passed (' + items.length + ' promotions)');
