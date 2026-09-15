// Drives the webinar-library page's iframe height poster against a fake DOM, so the messages it
// sends to the WordPress parent are checked without a browser.
//
// The script under test is not a module — it is the height-poster IIFE at the bottom of
// webinars/index.html. This file lifts that exact text out of the page and runs it, so the test
// cannot drift away from what ships.
//
// The case that matters is the shrink. Once the parent has sized the iframe, the child's viewport
// IS that height, so document.documentElement.scrollHeight can never report a value below it. A
// poster that measures documentElement therefore ratchets: this page filters, searches and has a
// Show More button, and after any of those narrow the list the embed would stay frozen at its
// tallest with a screen of blank page underneath. The fake DOM below models that ratchet
// explicitly, so a regression back to documentElement fails here.

'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');
const assert = require('assert');

const PAGE = path.join(__dirname, '..', '..', 'webinars', 'index.html');

function extractPoster() {
  const html = fs.readFileSync(PAGE, 'utf8');
  const marker = html.indexOf("Posts this page's content height to the parent");
  assert.notStrictEqual(marker, -1, 'the height-poster comment is gone from webinars/index.html');
  const start = html.lastIndexOf('<script>', marker);
  const end = html.indexOf('</script>', marker);
  assert.ok(start !== -1 && end !== -1, 'could not bound the height-poster script');
  return html.slice(start + '<script>'.length, end);
}

// A document whose body box is set by the test, and whose documentElement ratchets the way a
// real one does: it is never shorter than the viewport the parent last gave the iframe.
function makeEnv() {
  const posted = [];
  let viewport = 0;              // what the parent has sized the iframe to
  let bodyHeight = 0;
  const handlers = {};
  let pendingFrame = null;

  const body = {
    scrollHeight: 0,
    getBoundingClientRect: () => ({ height: bodyHeight }),
  };
  const documentElement = {
    get scrollHeight() { return Math.max(viewport, bodyHeight); },
  };

  const sandbox = {
    document: {
      body,
      documentElement,
      addEventListener() {},
    },
    MutationObserver: function () { return { observe() {} }; },
    ResizeObserver: function () { return { observe() {} }; },
    setInterval: () => 0,
    clearInterval: () => {},
    requestAnimationFrame(fn) { pendingFrame = fn; return 1; },
    cancelAnimationFrame() { pendingFrame = null; },
    console,
  };
  sandbox.window = {
    parent: {
      postMessage(msg) {
        posted.push(msg);
        // The parent applies the height, which is what makes documentElement ratchet.
        if (msg && typeof msg.height === 'number') { viewport = msg.height; }
      },
    },
    addEventListener(ev, fn) { (handlers[ev] = handlers[ev] || []).push(fn); },
    getComputedStyle: () => ({ marginTop: '0px', marginBottom: '0px' }),
  };
  sandbox.requestAnimationFrame = sandbox.requestAnimationFrame;
  sandbox.getComputedStyle = sandbox.window.getComputedStyle;

  return {
    sandbox,
    posted,
    setContentHeight(h) { bodyHeight = h; body.scrollHeight = h; },
    flush() { const fn = pendingFrame; pendingFrame = null; if (fn) fn(); },
    fire(ev) { (handlers[ev] || []).forEach(fn => fn()); },
    viewport: () => viewport,
  };
}

function boot(initialHeight) {
  const env = makeEnv();
  env.setContentHeight(initialHeight);
  vm.createContext(env.sandbox);
  vm.runInContext(extractPoster(), env.sandbox);
  env.flush();
  return env;
}

const cases = {
  'posts the content height once the page is up'() {
    const env = boot(910);
    assert.deepStrictEqual(env.posted.map(m => m.height), [910]);
    assert.strictEqual(env.posted[0].type, 'setHeight');
    assert.strictEqual(env.posted[0].id, 'iframe-default', 'the live embed is id="iframe-default"');
  },

  'posts a taller height when the list grows'() {
    const env = boot(910);
    env.setContentHeight(3910);
    env.fire('resize'); env.flush();
    assert.deepStrictEqual(env.posted.map(m => m.height), [910, 3910]);
  },

  'posts a shorter height when the list is filtered down'() {
    // The regression guard. documentElement.scrollHeight is pinned at 3910 here by the parent's
    // own sizing, so a poster that reads it never sends the 911 and the embed stays frozen tall.
    const env = boot(910);
    env.setContentHeight(3910);
    env.fire('resize'); env.flush();
    env.setContentHeight(911);
    env.fire('resize'); env.flush();
    assert.deepStrictEqual(env.posted.map(m => m.height), [910, 3910, 911],
      'the embed did not shrink — the poster is measuring documentElement, not the body box');
    assert.strictEqual(env.viewport(), 911);
  },

  'does not repost an unchanged height'() {
    const env = boot(910);
    env.fire('resize'); env.flush();
    env.fire('resize'); env.flush();
    assert.deepStrictEqual(env.posted.map(m => m.height), [910]);
  },

  'adds the body\'s own vertical margins'() {
    const env = makeEnv();
    env.sandbox.window.getComputedStyle = () => ({ marginTop: '12px', marginBottom: '8px' });
    env.sandbox.getComputedStyle = env.sandbox.window.getComputedStyle;
    env.setContentHeight(500);
    vm.createContext(env.sandbox);
    vm.runInContext(extractPoster(), env.sandbox);
    env.flush();
    assert.deepStrictEqual(env.posted.map(m => m.height), [520]);
  },

  'stays quiet when the page is not framed'() {
    const env = makeEnv();
    env.setContentHeight(910);
    env.sandbox.window.parent = env.sandbox.window;   // top-level, not embedded
    vm.createContext(env.sandbox);
    vm.runInContext(extractPoster(), env.sandbox);
    env.flush();
    assert.deepStrictEqual(env.posted, [], 'posted a height while not embedded');
  },

  'never uses documentElement to measure'() {
    // Belt and braces: the ratchet is invisible in any test whose content only grows, so the
    // source is checked directly too.
    assert.ok(!/documentElement\s*[.?]/.test(extractPoster().replace(/\/\*[\s\S]*?\*\//g, '')),
      'the poster reads documentElement — it can only ever grow');
  },
};

let failed = 0;
for (const [name, fn] of Object.entries(cases)) {
  try { fn(); console.log('ok   ' + name); }
  catch (e) { failed++; console.error('FAIL ' + name + '\n     ' + e.message); }
}
if (failed) { console.error(failed + ' iframe-height check(s) failed'); process.exit(1); }
console.log('all webinars iframe-height checks passed');
