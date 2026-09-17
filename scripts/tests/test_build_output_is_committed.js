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

// The build is run here rather than assumed, and the verdict is read only AFTER it.
// CI runs `npm run build` as its own step before this one, so refusing to start on a
// dirty tree would have meant a drifted branch failing with "commit or stash them
// first" — advice that, followed, commits the reverted pages and deploys them. That is
// the accident this test exists to prevent, so a pre-existing diff is reported and the
// build still runs; the diff after it is the only thing that decides.
const dirtyBefore = git('status', '--porcelain', ...GENERATED);
if (dirtyBefore) {
  console.log('Generated pages were already modified before this ran:\n' + dirtyBefore);
  console.log('(An earlier `npm run build` step does exactly this. Rebuilding anyway.)\n');
}

execFileSync('npm', ['run', 'build'], { cwd: repo, stdio: 'ignore' });

const drifted = git('status', '--porcelain', ...GENERATED);
if (drifted) {
  console.error('`npm run build` does not reproduce the committed pages:\n' + drifted);
  console.error('\nThe template is the source. Whatever those files hold on the branch would');
  console.error('be reverted by the next build — and, since a push to main is the deploy,');
  console.error('published. Fix templates/<path> so the build reproduces the committed page.');
  console.error('\nIf instead you meant to change a page, change its template and commit both.');
  console.error('\n' + git('diff', '--stat', ...GENERATED));
  process.exit(1);
}

console.log('OK: npm run build reproduces every committed page byte for byte.');
