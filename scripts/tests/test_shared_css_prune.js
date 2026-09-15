// Makes the shared.css prune checkable instead of a claim in a PR body.
//
// The claim is "live styles unchanged". That is a computed-style question and needs a
// browser and the published pages, so it is not what runs here. What runs here is the
// part that IS decidable offline, and it is the part that would otherwise be taken on
// trust — that the change really is a removal:
//
//   1. No selector was added.
//   2. Every surviving selector still carries a declaration block the base had. This is
//      the one a selector-set diff cannot see: a hand edit that strips declarations out
//      of a rule that still matches leaves the selector in place and the size smaller,
//      which looks exactly like a successful prune.
//   3. The result is a balanced stylesheet with no empty rule or @media block left where
//      a removal took the last declaration out of one.
//
// Case 4 is advisory and never fails: it lists removed selectors whose every class and id
// is still referenced in the repo's markup and scripts. Those are the rules to re-check
// against the live site on the next prune. It is not a failure list, because a rule can
// be dead while its class is very much alive — a page's own <style> may already have
// outranked it.
//
// The base is the MERGE BASE with main, never main's tip. Comparing against the tip
// reports every change main has taken since the branch was cut as if this branch had made
// it; that produced a confident false alarm about edited rules the first time this was
// run. PRUNE_BASE_REF overrides it.

'use strict';
const fs = require('fs');
const path = require('path');
const cp = require('child_process');
const assert = require('assert');

const REPO = path.join(__dirname, '..', '..');
const CSS_REL = 'assets/css/shared.css';

function git(...args) {
  try {
    return cp.execFileSync('git', ['-C', REPO, ...args],
      { encoding: 'utf8', maxBuffer: 64 * 1024 * 1024, stdio: ['ignore', 'pipe', 'ignore'] }).trim();
  } catch { return null; }
}

function baseRef() {
  if (process.env.PRUNE_BASE_REF) return process.env.PRUNE_BASE_REF;
  for (const tip of ['origin/main', 'main']) {
    const mb = git('merge-base', 'HEAD', tip);
    if (mb) return mb;
  }
  return null;
}

// --- selector + declaration extraction ------------------------------------------------
// A single pass over the text, tracking comments, quoted strings and url() explicitly.
// Regex pre-passes do NOT work here: one unpaired quote makes a string pattern match
// across to the next quote hundreds of lines later and silently delete everything
// between. That happened on the first version of this file and hid ~40 real rules, so
// the scanner below is the point, not an ornament.
//
// Conditional at-rules (@media, @supports, @layer, @container) become part of a rule's
// key, so `.a` inside @media is a different rule from `.a` outside it. @keyframes and
// friends are skipped whole: "0%" and "from" are step names, not selectors.
const OPAQUE_AT = /^@(keyframes|-\w+-keyframes|font-face|page|property|counter-style|font-feature-values)\b/;

// Index just past the string/comment starting at i, or i+1 if neither.
function skipLiteral(css, i) {
  const ch = css[i];
  if (ch === '/' && css[i + 1] === '*') {
    const end = css.indexOf('*/', i + 2);
    return end === -1 ? css.length : end + 2;
  }
  if (ch === '"' || ch === "'") {
    for (let j = i + 1; j < css.length; j++) {
      if (css[j] === '\\') { j++; continue; }
      if (css[j] === ch) return j + 1;
    }
    return css.length;                 // unterminated: consume the rest, do not re-pair
  }
  return i + 1;
}

// Index OF the "}" that closes the "{" at i.
function blockEnd(css, i) {
  let depth = 0;
  while (i < css.length) {
    const ch = css[i];
    if (ch === '/' && css[i + 1] === '*') { i = skipLiteral(css, i); continue; }
    if (ch === '"' || ch === "'") { i = skipLiteral(css, i); continue; }
    if (ch === '{') depth++;
    else if (ch === '}') { depth--; if (depth === 0) return i; }
    i++;
  }
  return css.length - 1;
}

function normaliseDecls(text) {
  let out = '', i = 0;
  while (i < text.length) {
    const ch = text[i];
    if (ch === '/' && text[i + 1] === '*') { i = skipLiteral(text, i); continue; }
    if (ch === '"' || ch === "'") { const j = skipLiteral(text, i); out += text.slice(i, j); i = j; continue; }
    out += ch; i++;
  }
  return out.trim().replace(/\s+/g, ' ');
}

// key ("@media ... | .selector") -> Set of normalised declaration blocks
function parse(css) {
  const rules = new Map();
  const stack = [];
  let head = '';
  let i = 0;
  while (i < css.length) {
    const ch = css[i];
    if (ch === '/' && css[i + 1] === '*') { i = skipLiteral(css, i); head += ' '; continue; }
    if (ch === '"' || ch === "'") { const j = skipLiteral(css, i); head += css.slice(i, j); i = j; continue; }
    if (ch === '{') {
      const h = head.trim().replace(/\s+/g, ' ');
      head = '';
      if (h.startsWith('@')) {
        if (OPAQUE_AT.test(h)) { i = blockEnd(css, i) + 1; continue; }
        stack.push(h);
        i++;
        continue;
      }
      const end = blockEnd(css, i);
      const body = normaliseDecls(css.slice(i + 1, end));
      const ctx = stack.length ? stack.join(' | ') + ' | ' : '';
      for (const sel of h.split(',')) {
        const name = sel.trim().replace(/\s+/g, ' ');
        if (!name) continue;
        const key = ctx + name;
        if (!rules.has(key)) rules.set(key, new Set());
        rules.get(key).add(body);
      }
      i = end + 1;
      continue;
    }
    if (ch === '}') { stack.pop(); head = ''; i++; continue; }
    if (ch === ';') { head = ''; i++; continue; }      // @import / @charset / @namespace
    head += ch;
    i++;
  }
  return rules;
}

function tokensOf(selector) {
  return [
    ...[...selector.matchAll(/\.(-?[_A-Za-z][-\w]*)/g)].map(m => m[1]),
    ...[...selector.matchAll(/#(-?[_A-Za-z][-\w]*)/g)].map(m => m[1]),
    ...[...selector.matchAll(/\[([-\w]+)/g)].map(m => m[1]),
  ];
}

// Families that live only in markup this repo never spells out (WordPress core blocks,
// Contact Form 7, Yoast). Their absence from the repo proves nothing.
const RUNTIME_PREFIXES = ['wp-', 'wpcf7', 'has-', 'is-', 'screen-reader', 'align', 'block-',
  'editor-', 'admin-bar', 'entry-', 'post-', 'page-'];
const isRuntime = name => RUNTIME_PREFIXES.some(p => name.startsWith(p));

// <style> blocks are stripped: a class that appears only in a page's own stylesheet is not
// evidence that the class is on any page.
function repoCorpus() {
  const files = (git('ls-files') || '').split('\n')
    .filter(f => /\.(html?|js|php)$/i.test(f))
    .filter(f => f !== CSS_REL && !f.startsWith('scripts/tests/') && !f.startsWith('docs/'));
  let blob = '';
  for (const f of files) {
    try {
      blob += fs.readFileSync(path.join(REPO, f), 'utf8')
        .replace(/<style[\s\S]*?<\/style>/gi, ' ') + '\n';
    } catch { /* unreadable */ }
  }
  return blob;
}

const BASE = baseRef();
const pruned = fs.readFileSync(path.join(REPO, CSS_REL), 'utf8');
const prunedRules = parse(pruned);
const baseCss = BASE === null ? null : git('show', `${BASE}:${CSS_REL}`);
const baseRules = baseCss === null ? null : parse(baseCss);
const skip = () => { console.log('     (skipped: no readable base for ' + CSS_REL + ')'); };

const cases = {
  'the base is the merge base, and it is readable'() {
    assert.ok(baseRules, 'could not resolve a merge base with main — set PRUNE_BASE_REF');
    console.log(`     base ${BASE.slice(0, 12)}: ${baseRules.size} rules, ` +
                `${(baseCss.length / 1024).toFixed(1)} KB -> ${prunedRules.size} rules, ` +
                `${(pruned.length / 1024).toFixed(1)} KB`);
  },

  'no selector was added'() {
    if (!baseRules) return skip();
    const added = [...prunedRules.keys()].filter(s => !baseRules.has(s));
    assert.deepStrictEqual(added, [],
      added.length + ' selector(s) are not in the base — this is not a pure removal:\n  ' +
      added.slice(0, 25).join('\n  '));
  },

  'no surviving rule had its declarations edited'() {
    if (!baseRules) return skip();
    const edited = [];
    for (const [sel, bodies] of prunedRules) {
      const before = baseRules.get(sel);
      if (!before) continue;                       // covered by the case above
      for (const body of bodies) {
        if (!before.has(body)) {
          edited.push(`${sel}\n        base   : ${[...before][0].slice(0, 200)}` +
                      `\n        pruned : ${body.slice(0, 200)}`);
        }
      }
    }
    assert.deepStrictEqual(edited, [],
      edited.length + ' surviving rule(s) carry a declaration block the base did not have. ' +
      'A prune removes rules; it does not rewrite them, and a rewritten rule that still ' +
      'matches is the one failure a size diff cannot show:\n  ' + edited.slice(0, 10).join('\n  '));
  },

  'something was actually removed'() {
    if (!baseRules) return skip();
    const removed = [...baseRules.keys()].filter(s => !prunedRules.has(s));
    assert.ok(removed.length > 0, 'no selector was removed — is HEAD really the prune?');
    console.log(`     ${removed.length} selectors removed`);
  },

  'the file is still a balanced stylesheet'() {
    const clean = pruned.replace(/\/\*[\s\S]*?\*\//g, '');
    let depth = 0;
    for (const ch of clean) {
      if (ch === '{') depth++;
      else if (ch === '}') { depth--; assert.ok(depth >= 0, 'unbalanced } in ' + CSS_REL); }
    }
    assert.strictEqual(depth, 0, 'unclosed { in ' + CSS_REL);
  },

  'no empty rule or empty at-rule block was left behind'() {
    const clean = pruned.replace(/\/\*[\s\S]*?\*\//g, '');
    const empties = [...clean.matchAll(/([^{}]+)\{\s*\}/g)].map(m => m[1].trim().slice(0, 80));
    assert.deepStrictEqual(empties, [], 'empty blocks left behind:\n  ' + empties.join('\n  '));
  },

  'removed selectors whose classes are still in the markup (advisory, never fails)'() {
    if (!baseRules) return skip();
    const corpus = repoCorpus();
    const corpusTokens = new Set(corpus.match(/[-\w]{2,}/g) || []);
    const present = n => isRuntime(n) || corpusTokens.has(n) || corpus.includes(n);
    const removed = [...baseRules.keys()].filter(s => !prunedRules.has(s));
    // Every part of a selector has to match, so ONE absent token proves the rule dead.
    const stillNamed = removed.filter(sel => {
      const names = tokensOf(sel);
      return names.length === 0 || names.every(present);
    });
    if (!stillNamed.length) { console.log('     none'); return; }
    console.log(`     ${stillNamed.length} of ${removed.length} removed selectors name only ` +
                'tokens that are still in the markup.\n     Not failures — re-check these ' +
                'against the live site on the next prune:');
    for (const sel of stillNamed.slice(0, 60)) console.log('       ' + sel);
    if (stillNamed.length > 60) console.log(`       ... and ${stillNamed.length - 60} more`);
  },
};

let failed = 0;
for (const [name, fn] of Object.entries(cases)) {
  try { fn(); console.log('ok   ' + name); }
  catch (e) { failed++; console.error('FAIL ' + name + '\n     ' + e.message); }
}
if (failed) { console.error(failed + ' shared.css prune check(s) failed'); process.exit(1); }
console.log('all shared.css prune checks passed');
