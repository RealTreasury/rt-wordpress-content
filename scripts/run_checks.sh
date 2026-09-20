#!/usr/bin/env bash
# run_checks.sh — the one command that means "the tests pass" in this repo.
#
# Why this file exists, and why at this exact path: serena_coder's
# test_gate_command() looks for scripts/run_checks.sh in a checkout and runs it
# as the gate that decides tests_failed; only if it is absent does it fall back
# to `pytest -x -q tests`. This repository has no tests/ directory and no pytest
# suite at all — its checks are the npm test:* scripts, a mix of node, python
# and bash. Without this file the automated reviewer's repair arm would mark
# every patch tests-failed regardless of content, which is the exact failure the
# rt-ai and halo-platform entries in serena_coder.REPOS document paying for.
#
# `set -e` stops at the first failing check, which is the `-x` behaviour the
# callers already expect.
#
# Two checks are deliberately NOT in the default run:
#   * test:build-clean reads `git status`, so it fails on ANY uncommitted .html
#     outside templates/ — including a page the build never regenerates. On a
#     dirty working tree a red run proves nothing (see the note in
#     scripts/tests/test_build_output_is_committed.js). CI checks out a clean
#     tree and runs it as its own step, which is where it belongs. Set
#     RUN_CHECKS_BUILD_CLEAN=1 to include it here.
#   * test:ejs only asserts that node_modules is installed, which the build
#     step proves more directly.
#
# Those two exclusions are also what lets this run in a bare `git worktree add`
# with no `npm install`, which is exactly what the repair arm hands it: the seven
# checks below are plain node/python/bash and need no packages, while build,
# test:ejs and test:build-clean all do. Verified on a fresh worktree with no
# node_modules present: 7 passed, exit 0. Keep that true when adding a check.
#
# Usage:  bash scripts/run_checks.sh
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

CHECKS=(
  test:iframe-height
  test:banner
  test:carousel
  test:css-prune
  test:publish-post
  test:publish-css
  test:deploy-on-merge
)

if [ "${RUN_CHECKS_BUILD_CLEAN:-0}" = "1" ]; then
  CHECKS+=(test:build-clean)
fi

failed=0
for check in "${CHECKS[@]}"; do
  echo "=== $check ==="
  if npm run --silent "$check"; then
    echo "--- $check OK"
  else
    # Report the exit status, not a grep for "OK" in the output: several of
    # these scripts print the word on their way to failing.
    echo "--- $check FAILED" >&2
    failed=1
    break
  fi
done

if [ "$failed" -ne 0 ]; then
  echo "run_checks.sh: a check failed; see above." >&2
  exit 1
fi

echo "run_checks.sh: all ${#CHECKS[@]} checks passed."
