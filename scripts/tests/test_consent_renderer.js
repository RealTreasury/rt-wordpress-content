// Every gate page renders the RT Gate form's consent_text, and consent_text is
// authored in WP Admin, not here. The label the visitor ticks is the record of
// what they agreed to, and the submission stores the original string, so a page
// that rewrites the text at display time makes the shown label and the stored
// consent disagree.
//
// That is exactly what this page did: a local consentHtml() stripped the
// privacy-policy URL out of the sentence, hung the link on the words "privacy
// policy" instead, and appended a full stop when the remaining text did not end
// in one. It read better and it was a second, incompatible answer to a question
// events/2026/afp/index.html had already answered -- the two pages disagreed
// about whether an authored <a href> in consent_text was markup or text.
//
// So there is one renderer, copied verbatim, and this file is what keeps it
// verbatim: a copy that drifts is the failure, not a copy that exists. If the
// consent label needs a link, the link belongs in the consent_text in WP Admin,
// where safeConsentHtml already honours it.

'use strict';
const fs = require('fs');
const path = require('path');
const assert = require('assert');

const ROOT = path.join(__dirname, '..', '..');
// Every page that renders consent_text through safeConsentHtml. Adding a page
// here is how a new gate joins the rail.
const PAGES = [
  'events/2026/afp/index.html',
  'treasury-tech-selection/guidebook/wordpress-page.html',
];

function renderer(file) {
  const html = fs.readFileSync(path.join(ROOT, file), 'utf8');
  const start = html.indexOf('  function safeConsentHtml(str) {');
  assert.notStrictEqual(start, -1, `${file}: safeConsentHtml is missing`);
  const end = html.indexOf('\n  }\n', start);
  assert.notStrictEqual(end, -1, `${file}: safeConsentHtml has no end`);
  return html.slice(start, end + 4);
}

// 1. One rail, not several: the copies must be character for character equal.
const [canonical, ...rest] = PAGES.map(renderer);
PAGES.slice(1).forEach((file, i) => {
  assert.strictEqual(
    rest[i], canonical,
    `${file}: safeConsentHtml has drifted from ${PAGES[0]}. Copy it back, or ` +
    'move both to a shared partial -- do not fork it.');
});
console.log(`ok   ${PAGES.length} pages share one consent renderer`);

// 2. No page keeps a second mechanism alongside it.
PAGES.forEach((file) => {
  const html = fs.readFileSync(path.join(ROOT, file), 'utf8');
  ['function consentHtml', 'function escHtmlLinkify', 'function linkifyText']
    .forEach((fn) => {
      assert.ok(
        !html.includes(fn),
        `${file}: ${fn} is a second consent renderer; safeConsentHtml is the rail`);
    });
  assert.ok(
    !/>' \+ consentHtml\(/.test(html),
    `${file}: the consent label is still rendered by consentHtml`);
});
console.log('ok   no page carries a competing consent renderer');

// 3. The renderer does not rewrite the authored text. Run the real function
//    against a minimal DOM so this asserts behaviour, not just source shape.
const { JSDOM } = (() => {
  try { return require('jsdom'); } catch (e) { return {}; }
})();

if (!JSDOM) {
  // jsdom is not a dependency of this repo and CI installs nothing (see
  // AGENTS.md). Assert on the source instead: the two mutations that caused
  // the finding must not come back.
  const src = canonical;
  assert.ok(!src.includes(".replace(url, '')"),
            'safeConsentHtml strips the URL out of the authored sentence');
  assert.ok(!/out \+= '\.'/.test(src),
            'safeConsentHtml appends punctuation the author did not write');
  assert.ok(src.includes('escHtml(n.nodeValue)'),
            'text nodes must be escaped and passed through unchanged');
  console.log('ok   the renderer passes authored text through unmodified');
} else {
  const dom = new JSDOM('<!doctype html><html><body></body></html>');
  global.document = dom.window.document;
  global.DOMParser = dom.window.DOMParser;
  const fn = new Function(
    'escHtml', 'escAttr',
    canonical + '\nreturn safeConsentHtml;')(
      (s) => { const d = dom.window.document.createElement('div');
               d.appendChild(dom.window.document.createTextNode(s == null ? '' : String(s)));
               return d.innerHTML; },
      (s) => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
               .replace(/</g, '&lt;').replace(/>/g, '&gt;'));

  const plain = 'I agree to the privacy policy: https://realtreasury.com/privacy/';
  assert.strictEqual(fn(plain), plain.replace(/&/g, '&amp;'),
                     'a bare URL must render as the author wrote it');
  const authored = 'I agree to the <a href="https://realtreasury.com/privacy/">privacy policy</a>.';
  assert.ok(fn(authored).includes('href="https://realtreasury.com/privacy/"'),
            'an authored <a href> must survive as a link');
  assert.ok(fn('<script>alert(1)</script>ok').indexOf('<script') === -1,
            'markup that is not an http(s) anchor must be escaped');
  console.log('ok   the renderer passes authored text through unmodified');
}

console.log('all consent renderer checks passed');
