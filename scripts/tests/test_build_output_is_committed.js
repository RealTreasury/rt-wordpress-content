#!/usr/bin/env node
/*
 * Every page under templates/ is rendered by `npm run build` straight over the
 * same path at the repo root, and both sides are committed. Nothing kept them
 * in sync, so the committed pages and their templates drifted: a clean build on
 * main at 06a944f rewrote 11 files, deleting the iframe height posters from ten
 * insight pages, reverting "Real Treasury" to "RealTreasury", and restoring a
 * stale marketing draft of real-treasury-explained.
 *
 * Two ways that bites. A fix applied to the generated file is erased by the next
 * build. And because a push to main is the deploy, anyone who runs the build and
 * commits the result publishes those reversions to the live site.
 *
 * This asserts the only property that keeps both true at once: building from a
 * clean tree changes nothing that is committed.
 */
const { execFileSync } = require('child_process');
const path = require('path');

const repo = path.resolve(__dirname, '..', '..');
const git = (...args) =>
  execFileSync('git', args, { cwd: repo, encoding: 'utf8' }).trim();

// Only the generated pages matter here. An edited template is the normal state
// of a working change, and is the very thing this test is asked to validate.
const GENERATED = ['--', '*.html', ':(exclude)templates/**'];

const dirtyBefore = git('status', '--porcelain', ...GENERATED);
if (dirtyBefore) {
  console.error('Generated pages already have uncommitted changes:\n' + dirtyBefore);
  console.error('\nCommit or stash them first — this test cannot tell them from build drift.');
  process.exit(1);
}

execFileSync('npm', ['run', 'build'], { cwd: repo, stdio: 'ignore' });

const drifted = git('status', '--porcelain', ...GENERATED);
if (drifted) {
  console.error('`npm run build` rewrote committed pages:\n' + drifted);
  console.error('\nThe template is the source. Whatever those files hold now would be');
  console.error('reverted on the next build — and deployed. Fix templates/<path> so the');
  console.error('build reproduces the committed page, then rerun this.');
  console.error('\n' + git('diff', '--stat', ...GENERATED));
  process.exit(1);
}

console.log('OK: npm run build reproduces every committed page byte for byte.');
