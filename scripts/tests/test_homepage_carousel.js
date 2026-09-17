// Drives the homepage chart carousel's timer logic with a fake clock and a minimal DOM,
// so the dwell rules are checked without a browser.
//
// The script under test is not a module — it is the "// Chart carousel" IIFE inside
// Homepage/index.html. This file lifts that exact text out of the page and runs it, so the
// test cannot drift away from what ships.
//
// Covered:
//   1. plain rotation advances every 15s
//   2. a dot click holds that chart for 45s, then rotation resumes on the 15s cadence
//   3. clicking the already-active dot restarts the hold
//   4. hovering pauses; leaving honours whatever hold time is left, or resumes at once
//      if the hover outlasted it
//   5. a click made while hovering does not start rotating at 45s — the pointer wins
//   6. repeated hover in/out leaves exactly one live interval (the stacked-timer bug)

'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const PAGE = path.join(__dirname, '..', '..', 'Homepage', 'index.html');
const ROTATE_MS = 15000;
const HOLD_MS = 45000;

function extractCarouselScript() {
  const html = fs.readFileSync(PAGE, 'utf8');
  const start = html.indexOf('// Chart carousel');
  assert.notStrictEqual(start, -1, 'the "// Chart carousel" marker is gone from Homepage/index.html');
  const end = html.indexOf('})();', start);
  assert.notStrictEqual(end, -1, 'could not find the end of the carousel IIFE');
  return html.slice(start, end + '})();'.length);
}

// --- fake clock -----------------------------------------------------------------------------
function makeClock() {
  let now = 1000000;
  let seq = 0;
  const timers = new Map();
  const api = {
    now: () => now,
    liveIntervals: () => [...timers.values()].filter(t => t.repeat).length,
    setInterval(fn, ms) { const id = ++seq; timers.set(id, { fn, ms, at: now + ms, repeat: true }); return id; },
    setTimeout(fn, ms) { const id = ++seq; timers.set(id, { fn, ms, at: now + ms, repeat: false }); return id; },
    clear(id) { timers.delete(id); },
    advance(ms) {
      const target = now + ms;
      // Fire in due order, re-arming intervals, until nothing else is due.
      for (;;) {
        let next = null;
        for (const [id, t] of timers) if (t.at <= target && (next === null || t.at < next[1].at)) next = [id, t];
        if (!next) break;
        const [id, t] = next;
        now = t.at;
        if (t.repeat) t.at = now + t.ms; else timers.delete(id);
        t.fn();
      }
      now = target;
    },
  };
  return api;
}

// --- minimal DOM ----------------------------------------------------------------------------
function makeDom(slideCount) {
  const mk = () => {
    const classes = new Set();
    return {
      classList: { add: c => classes.add(c), remove: c => classes.delete(c), contains: c => classes.has(c) },
      _classes: classes,
      _listeners: {},
      addEventListener(ev, fn) { (this._listeners[ev] = this._listeners[ev] || []).push(fn); },
      fire(ev) { (this._listeners[ev] || []).forEach(fn => fn()); },
    };
  };
  const slides = Array.from({ length: slideCount }, mk);
  const dots = Array.from({ length: slideCount }, mk);
  // The shipped markup marks the first slide and dot active; the script never does it at boot.
  slides[0].classList.add('active');
  dots[0].classList.add('active');
  const carousel = mk();
  carousel.querySelectorAll = sel => (sel === '.chart-slide' ? slides : dots);
  return { carousel, slides, dots };
}

function activeIndex(dots) {
  const i = dots.findIndex(d => d.classList.contains('active'));
  assert.notStrictEqual(i, -1, 'no dot is active');
  assert.strictEqual(dots.filter(d => d.classList.contains('active')).length, 1, 'more than one dot is active');
  return i;
}

function boot(slideCount = 4) {
  const clock = makeClock();
  const dom = makeDom(slideCount);
  const sandbox = {
    document: { querySelector: () => dom.carousel },
    setInterval: clock.setInterval,
    clearInterval: clock.clear,
    setTimeout: clock.setTimeout,
    clearTimeout: clock.clear,
    Date: { now: clock.now },
  };
  vm.createContext(sandbox);
  vm.runInContext(extractCarouselScript(), sandbox);
  return { clock, ...dom };
}

// --- cases ----------------------------------------------------------------------------------
const cases = {
  'rotates every 15s'() {
    const c = boot();
    assert.strictEqual(activeIndex(c.dots), 0);
    c.clock.advance(ROTATE_MS - 1);
    assert.strictEqual(activeIndex(c.dots), 0, 'advanced early');
    c.clock.advance(1);
    assert.strictEqual(activeIndex(c.dots), 1);
    c.clock.advance(ROTATE_MS);
    assert.strictEqual(activeIndex(c.dots), 2);
  },

  'a dot click holds 45s, then resumes the 15s cadence'() {
    const c = boot();
    c.dots[2].fire('click');
    assert.strictEqual(activeIndex(c.dots), 2);
    c.clock.advance(HOLD_MS - 1);
    assert.strictEqual(activeIndex(c.dots), 2, 'left the chosen chart before 45s');
    c.clock.advance(1);
    assert.strictEqual(activeIndex(c.dots), 3, 'did not advance at 45s');
    c.clock.advance(ROTATE_MS);
    assert.strictEqual(activeIndex(c.dots), 0, 'did not return to the 15s cadence');
  },

  'clicking the active dot restarts the hold'() {
    const c = boot();
    c.dots[1].fire('click');
    c.clock.advance(30000);
    c.dots[1].fire('click');            // same dot again
    c.clock.advance(HOLD_MS - 1);
    assert.strictEqual(activeIndex(c.dots), 1, 'the second click did not restart the hold');
    c.clock.advance(1);
    assert.strictEqual(activeIndex(c.dots), 2);
  },

  'hover pauses, and leaving honours the hold that is left'() {
    const c = boot();
    c.dots[1].fire('click');
    c.clock.advance(10000);
    c.carousel.fire('mouseenter');
    c.clock.advance(5000);
    assert.strictEqual(activeIndex(c.dots), 1, 'rotated while hovered');
    c.carousel.fire('mouseleave');
    c.clock.advance(HOLD_MS - 15000 - 1);
    assert.strictEqual(activeIndex(c.dots), 1, 'the remaining hold was not honoured after mouseleave');
    c.clock.advance(1);
    assert.strictEqual(activeIndex(c.dots), 2);
  },

  'a hover long enough to outlast the hold resumes on mouseleave'() {
    // The hold is wall-clock, not a countdown that pauses: parking the pointer for longer than
    // 45s uses it up, and leaving then returns straight to the normal cadence.
    const c = boot();
    c.dots[1].fire('click');
    c.carousel.fire('mouseenter');
    c.clock.advance(HOLD_MS * 3);
    assert.strictEqual(activeIndex(c.dots), 1, 'rotated while hovered');
    c.carousel.fire('mouseleave');
    c.clock.advance(ROTATE_MS - 1);
    assert.strictEqual(activeIndex(c.dots), 1, 'jumped as soon as the pointer left');
    c.clock.advance(1);
    assert.strictEqual(activeIndex(c.dots), 2);
  },

  'a click while hovering does not rotate when the hold expires'() {
    const c = boot();
    c.carousel.fire('mouseenter');
    c.dots[2].fire('click');            // pointer is still over the carousel
    c.clock.advance(HOLD_MS + 1);
    assert.strictEqual(activeIndex(c.dots), 2, 'rotation resumed under the pointer the moment the hold ran out');
    c.clock.advance(ROTATE_MS * 3);     // (4 slides — a whole lap would land back on 2 and hide it)
    assert.strictEqual(activeIndex(c.dots), 2, 'rotation resumed under the pointer after the hold ran out');
    c.carousel.fire('mouseleave');      // the hold is long gone, so rotation restarts here
    c.clock.advance(ROTATE_MS - 1);
    assert.strictEqual(activeIndex(c.dots), 2);
    c.clock.advance(1);
    assert.strictEqual(activeIndex(c.dots), 3);
  },

  'repeated hover in/out leaves one live interval'() {
    const c = boot();
    for (let i = 0; i < 5; i++) {
      c.carousel.fire('mouseenter');
      c.clock.advance(500);
      c.carousel.fire('mouseleave');
      c.clock.advance(500);
    }
    assert.strictEqual(c.clock.liveIntervals(), 1, 'hover stacked rotation timers');
    c.dots[0].fire('click');
    c.carousel.fire('mouseenter');
    c.carousel.fire('mouseleave');
    assert.strictEqual(c.clock.liveIntervals(), 0, 'a hold should be a timeout, not a live interval');
  },
};

let failed = 0;
for (const [name, fn] of Object.entries(cases)) {
  try { fn(); console.log('ok   ' + name); }
  catch (e) { failed++; console.error('FAIL ' + name + '\n     ' + e.message); }
}
if (failed) { console.error(failed + ' carousel check(s) failed'); process.exit(1); }
console.log('all homepage carousel checks passed');
