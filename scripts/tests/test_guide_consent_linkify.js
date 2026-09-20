// Exercises the guide form's consent-text escaping in the same node-only style
// as the other static-page checks.  The source text arrives from RT Gate, so a
// URL and surrounding text must both remain data rather than markup.
'use strict';

const assert = require('assert');
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const file = path.join(__dirname, '..', '..', 'treasury-tech-selection',
  'guidebook', 'wordpress-page.html');
const html = fs.readFileSync(file, 'utf8');

function helperSource() {
  const start = html.indexOf('function escHtml(');
  const end = html.indexOf('function parseJsonOrThrow', start);
  assert.notStrictEqual(start, -1, 'guide escaping helper is missing');
  assert.notStrictEqual(end, -1, 'guide escaping helper boundary is missing');
  return html.slice(start, end);
}

function render(input) {
  const escape = (value) => String(value).replace(/&/g, '&amp;')
    .replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  const context = {
    input,
    document: {
      createElement: () => ({
        innerHTML: '',
        appendChild(node) { this.innerHTML = escape(node.textContent); },
      }),
      createTextNode: (text) => ({ textContent: text }),
    },
  };
  vm.createContext(context);
  vm.runInContext(helperSource() + '\nresult = escHtmlLinkify(input);', context);
  return context.result;
}

assert.strictEqual(render('No link <here>.'), 'No link &lt;here&gt;.');
assert.strictEqual(
  render('Read https://example.test/privacy-policy. <required>'),
  'Read <a href="https://example.test/privacy-policy" target="_blank" rel="noopener noreferrer">https://example.test/privacy-policy</a>. &lt;required&gt;',
);
console.log('guide consent linkify checks passed');
