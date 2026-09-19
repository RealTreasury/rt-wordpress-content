#!/usr/bin/env python3
"""Offline tests for scripts/wp_deploy_on_merge.py.

A real git repo in a temp dir plays origin; a fake publisher records its argv and
exits as told. Nothing here touches SSH, WordPress or GitHub (--no-github).
Run: python3 scripts/tests/test_wp_deploy_on_merge.py
"""
from __future__ import annotations

import json
import os
import subprocess
import sys
import tempfile
import unittest
from unittest.mock import patch
from pathlib import Path

HERE = Path(__file__).resolve().parent
sys.path.insert(0, str(HERE.parent))
import wp_deploy_on_merge as leg  # noqa: E402

PAGES = "\n".join([
    "# slug\tpost_id\tsource\tmode",
    "alpha\t1\tinsights/alpha/index.html\tpage",
    "beta\t2\tinsights/beta/index.html\tpage",
    "gamma-staging\t3\tinsights/gamma/index.html\tpage",
    "gamma-prod\t4\tinsights/gamma/index.html\tpage\tgamma",
    "",
])
DEPLOY = "# comment\nalpha\nbeta\ngamma-prod\n"

FAKE_PUBLISHER = """#!/usr/bin/env bash
echo "$@" >> "$FAKE_LOG"
if [ -n "${FAIL_SLUG:-}" ] && [ "${@: -1}" = "$FAIL_SLUG" ]; then echo boom >&2; exit 7; fi
echo "-- read-back OK"
"""


def sh(*cmd, cwd=None, env=None):
    return subprocess.run(cmd, cwd=cwd, env=env, check=True, capture_output=True,
                          text=True).stdout.strip()


class Mapping(unittest.TestCase):
    def setUp(self):
        self.pages = leg.parse_pages(PAGES)
        self.deploy = leg.parse_deploy(DEPLOY)

    def test_one_source_maps_to_its_row_only(self):
        self.assertEqual(leg.affected_slugs(["insights/beta/index.html"],
                                            self.deploy, self.pages), ["beta"])

    def test_shared_source_publishes_only_the_deploy_row(self):
        # gamma-staging shares the file but is not in deploy.tsv (staging id).
        self.assertEqual(leg.affected_slugs(["insights/gamma/index.html"],
                                            self.deploy, self.pages), ["gamma-prod"])

    def test_untracked_pages_publish_nothing(self):
        self.assertEqual(leg.affected_slugs(["webinars/index.html", "README.md"],
                                            self.deploy, self.pages), [])

    def test_converter_change_publishes_every_row(self):
        for path in ("scripts/lib/page_to_block.py", "scripts/wp_publish_post.py",
                     "wp/pages.tsv"):
            self.assertEqual(leg.affected_slugs([path], self.deploy, self.pages),
                             ["alpha", "beta", "gamma-prod"], path)

    def test_deploy_row_missing_from_pages_is_refused(self):
        with self.assertRaises(ValueError):
            leg.affected_slugs([], ["alpha", "ghost"], self.pages)

    def test_duplicate_deploy_row_is_refused(self):
        with self.assertRaises(ValueError):
            leg.parse_deploy("alpha\nalpha\n")


class EndToEnd(unittest.TestCase):
    def setUp(self):
        self.tmp = Path(tempfile.mkdtemp(prefix="wpdeploy-"))
        self.origin = self.tmp / "origin.git"
        self.author = self.tmp / "author"
        self.checkout = self.tmp / "checkout"
        self.state = self.tmp / "state"
        sh("git", "init", "-q", "--bare", "-b", "main", str(self.origin))
        sh("git", "clone", "-q", str(self.origin), str(self.author))
        self.genv = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@x",
                     "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@x"}
        self.write("wp/pages.tsv", PAGES)
        self.write("wp/deploy.tsv", DEPLOY)
        self.write("insights/alpha/index.html", "<p>a</p>")
        self.write("insights/beta/index.html", "<p>b</p>")
        self.write("insights/gamma/index.html", "<p>g</p>")
        self.write("scripts/lib/page_to_block.py", "# converter")
        self.commit("initial")
        sh("git", "clone", "-q", str(self.origin), str(self.checkout))
        self.fake_log = self.tmp / "publisher.log"
        self.publisher = self.tmp / "fake_publish.sh"
        self.publisher.write_text(FAKE_PUBLISHER, encoding="utf-8")
        self.publisher.chmod(0o755)
        os.environ["FAKE_LOG"] = str(self.fake_log)
        os.environ.pop("FAIL_SLUG", None)

    def write(self, rel, text):
        p = self.author / rel
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text(text, encoding="utf-8")

    def commit(self, msg):
        sh("git", "add", "-A", cwd=self.author, env=self.genv)
        sh("git", "commit", "-q", "-m", msg, cwd=self.author, env=self.genv)
        sh("git", "push", "-q", "origin", "HEAD:main", cwd=self.author, env=self.genv)
        return sh("git", "rev-parse", "HEAD", cwd=self.author)

    def run_leg(self, dry_run=False):
        return leg.run(self.checkout, self.state, "git", [str(self.publisher)],
                       dry_run, use_github=False)

    def published(self):
        if not self.fake_log.exists():
            return []
        return [l.split()[-1] for l in self.fake_log.read_text().splitlines()]

    def deployed_sha(self):
        return (self.state / "deployed.sha").read_text().strip()

    def test_first_run_records_baseline_and_publishes_nothing(self):
        head = sh("git", "rev-parse", "HEAD", cwd=self.author)
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.deployed_sha(), head)
        self.assertEqual(self.published(), [])

    def test_changed_source_publishes_that_row_and_advances(self):
        self.run_leg()
        self.write("insights/beta/index.html", "<p>b2</p>")
        new = self.commit("beta")
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.published(), ["beta"])
        self.assertEqual(self.deployed_sha(), new)
        self.assertEqual(sh("git", "rev-parse", "HEAD", cwd=self.checkout), new)
        rec = json.loads((self.state / "deploy.jsonl").read_text().splitlines()[-1])
        self.assertTrue(rec["ok"])
        self.assertEqual(rec["slugs"], ["beta"])
        self.assertEqual(rec["github"], "skipped")

    def test_publisher_gets_the_production_publish_argv(self):
        self.run_leg()
        self.write("insights/alpha/index.html", "<p>a2</p>")
        self.commit("alpha")
        self.run_leg()
        self.assertEqual(self.fake_log.read_text().strip(),
                         "publish --target production alpha")

    def test_unchanged_main_is_a_quiet_noop(self):
        self.run_leg()
        before = self.deployed_sha()
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.deployed_sha(), before)
        self.assertFalse((self.state / "deploy.jsonl").exists())

    def test_untracked_page_advances_without_publishing(self):
        self.run_leg()
        self.write("webinars/index.html", "<p>w</p>")
        new = self.commit("webinar")
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.published(), [])
        self.assertEqual(self.deployed_sha(), new)

    def test_converter_change_publishes_all_rows_once(self):
        self.run_leg()
        self.write("scripts/lib/page_to_block.py", "# converter v2")
        self.commit("converter")
        self.run_leg()
        self.assertEqual(self.published(), ["alpha", "beta", "gamma-prod"])

    def test_dry_run_publishes_nothing_and_moves_nothing(self):
        self.run_leg()
        before = self.deployed_sha()
        self.write("insights/beta/index.html", "<p>b3</p>")
        self.commit("beta")
        self.assertEqual(self.run_leg(dry_run=True), 0)
        self.assertEqual(self.published(), [])
        self.assertEqual(self.deployed_sha(), before)
        self.assertEqual(sh("git", "rev-parse", "HEAD", cwd=self.checkout), before)

    def test_dry_run_uses_the_new_tree_manifests(self):
        self.run_leg()
        self.write("wp/deploy.tsv", DEPLOY.replace("beta\n", ""))
        self.commit("remove beta deploy row")
        messages = []
        with patch.object(leg, "log", messages.append):
            self.assertEqual(self.run_leg(dry_run=True), 0)
        plan = next(message for message in messages if "page(s) to publish" in message)
        self.assertIn("['alpha', 'gamma-prod']", plan)
        self.assertNotIn("beta", plan)

    def test_failure_sets_a_hold_keeps_the_sha_and_stops_the_next_run(self):
        self.run_leg()
        before = self.deployed_sha()
        self.write("insights/alpha/index.html", "<p>a2</p>")
        self.write("insights/beta/index.html", "<p>b2</p>")
        self.commit("both")
        os.environ["FAIL_SLUG"] = "alpha"
        self.assertEqual(self.run_leg(), 2)
        self.assertEqual(self.published(), ["alpha"])  # stopped at the failure
        self.assertEqual(self.deployed_sha(), before)
        hold = self.state / "deploy-hold.json"
        self.assertTrue(hold.exists())
        self.assertIn("boom", json.loads(hold.read_text())["results"][-1]["tail"])
        os.environ.pop("FAIL_SLUG")
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.published(), ["alpha"])  # held: nothing more ran
        hold.unlink()
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.published(), ["alpha", "alpha", "beta"])

    def test_missing_deploy_row_refuses_before_publishing(self):
        self.run_leg()
        self.write("wp/deploy.tsv", DEPLOY + "ghost\n")
        self.write("insights/beta/index.html", "<p>b2</p>")
        self.commit("bad manifest")
        self.assertEqual(self.run_leg(), 1)
        self.assertEqual(self.published(), [])

    def test_kill_switch(self):
        self.run_leg()
        (self.state / "hooks-off").write_text("")
        self.write("insights/beta/index.html", "<p>b2</p>")
        self.commit("beta")
        self.assertEqual(self.run_leg(), 0)
        self.assertEqual(self.published(), [])

    def test_concurrent_run_leaves_the_active_deployment_alone(self):
        with leg.deployment_lock(self.state) as acquired:
            self.assertTrue(acquired)
            self.assertEqual(self.run_leg(), 0)
        self.assertFalse((self.state / "deployed.sha").exists())
        self.assertEqual(self.published(), [])

    def test_refuses_off_main_and_dirty_trees(self):
        self.run_leg()
        (self.checkout / "insights/alpha/index.html").write_text("local edit")
        self.assertEqual(self.run_leg(), 1)
        sh("git", "checkout", "-q", "--", ".", cwd=self.checkout)
        sh("git", "checkout", "-q", "-b", "side", cwd=self.checkout)
        self.assertEqual(self.run_leg(), 1)


if __name__ == "__main__":
    unittest.main(verbosity=1)
