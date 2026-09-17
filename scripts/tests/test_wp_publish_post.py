#!/usr/bin/env python3
"""Exercises scripts/wp_publish_post.py against a fake remote, offline.

`ssh` is the only thing replaced. Everything above it — the manifest, the three build
modes, the splice, the no-op check, the backup, the read-back, the post_status guard — runs
for real, so the guards are tested rather than described:

  1.  native mode: header comment dropped, block wrapper added, note points at the source
  2.  native mode leaves a comment that is NOT the header alone
  3.  plan writes nothing to the remote
  4.  publish sends the built content, reads it back, and leaves post_status alone
  5.  a second publish is a no-op
  6.  THE BACKSLASH REGRESSION. wp_update_post() runs wp_unslash() over its input, so
      content passed raw loses one level of backslashes — that is how page 4587 ended up
      with `[^s@]` where its source has `[^\\s@]`, silently rejecting any address whose
      local part contains an "s". The fake remote simulates wp_unslash; the write must
      survive it, and the same write with wp_slash() removed must NOT.
  7.  the read-back catches a remote that stored something else
  8.  a post_status that moved during the write is refused
  9.  a write is refused outright if the remote does not read the script from stdin
  10. publish backs the old content up before writing
  11. page mode refuses to drop a wp:block pattern ref
  12. the manifest rejects an unknown mode
  13. WP-CLI global flags go BEFORE the subcommand (WP-CLI 2.12.0 rejects them after)
"""
from __future__ import annotations

import base64
import importlib.util
import re
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

spec = importlib.util.spec_from_file_location("wpp", ROOT / "scripts" / "wp_publish_post.py")
wpp = importlib.util.module_from_spec(spec)
spec.loader.exec_module(wpp)

failures = []


def check(name, cond):
    if cond:
        print(f"ok   {name}")
    else:
        print(f"FAIL {name}", file=sys.stderr)
        failures.append(name)


class Remote:
    """A WordPress that stores one body and one status per post id.

    unslash=True  reproduces wp_unslash(): strips one level of backslashes from whatever is
                  written, UNLESS the PHP asked for wp_slash().
    corrupt=True  appends a byte, which the read-back exists to catch.
    flip=True     moves post_status during the write.
    nostdin=True  ignores stdin, as an SSH jail that does not forward it would.
    """

    def __init__(self, **kw):
        self.body: dict[int, str] = {}
        self.status: dict[int, str] = {}
        self.unslash = kw.get("unslash", False)
        self.corrupt = kw.get("corrupt", False)
        self.flip = kw.get("flip", False)
        self.nostdin = kw.get("nostdin", False)
        self.writes = 0
        self.commands: list[str] = []

    def __call__(self, env, remote_cmd, stdin=None):
        self.commands.append(remote_cmd)
        # WP-CLI 2.12.0 rejects a global flag placed after the subcommand.
        if re.search(r"\b(eval|eval-file|post)\b.*--quiet", remote_cmd):
            wpp.die("Error: Parameter errors: unknown --quiet parameter")
        m = re.search(r"post get (\d+) --field=post_status", remote_cmd)
        if m:
            return self.status.get(int(m.group(1)), "") + "\n"
        m = re.search(r"get_post\((\d+)\)", remote_cmd)
        if m:
            return self.body.get(int(m.group(1)), "")
        if "eval-file -" in remote_cmd:
            php = (stdin or b"").decode("utf-8")
            if self.nostdin:
                return ""
            if wpp.STDIN_PROBE_TOKEN in php:
                return wpp.STDIN_PROBE_TOKEN
            pid = int(re.search(r'"ID" => (\d+)', php).group(1))
            payload = re.search(r'base64_decode\("([^"]*)"\)', php).group(1)
            content = base64.b64decode(payload).decode("utf-8")
            if self.unslash and "wp_slash(" not in php:
                content = content.replace("\\", "")
            if self.corrupt:
                content += "X"
            self.body[pid] = content
            if self.flip:
                self.status[pid] = "publish"
            self.writes += 1
            return str(pid)
        raise AssertionError(f"fake remote: unhandled command {remote_cmd!r}")


def env_for(remote):
    wpp.ssh = remote
    return {"_user": "stub", "_target": "production", "WPCOM_SSH_HOST": "h",
            "WPCOM_SSH_KEY_FILE": "k"}


SOURCE = """<!--
  Repo-facing header. Paths, PR history. The script drops this one.
-->
<div class="rt-x">
  <script>if (!/^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/.test(v)) { bad(); }</script>
  <!-- this comment is content, not the header -->
</div>
"""

tmp = Path(tempfile.mkdtemp())
src = tmp / "page.html"
src.write_text(SOURCE, encoding="utf-8")
wpp.BACKUPS = tmp / "backups"

# --- 1, 2. the native derivation ---------------------------------------------------------
built = wpp.render_native(SOURCE, "some/path/page.html")
check("1 native strips the repo header comment",
      "Repo-facing header" not in built)
check("1 native wraps the body as one Custom HTML block",
      "<!-- wp:html -->" in built and "<!-- /wp:html -->" in built)
check("1 native sandwiches the page between the header and footer patterns",
      built.startswith('<!-- wp:block {"ref":183} /-->')
      and built.endswith('<!-- wp:block {"ref":398} /-->'))
check("1 native puts the body inside the full-width group, not beside it",
      built.index('<!-- wp:group') < built.index("<!-- wp:html -->")
      < built.index("<!-- /wp:html -->") < built.index('{"ref":398}'))
check("1 native names the source in the note",
      "Source of record: some/path/page.html" in built)
check("2 native keeps a comment that is not the header",
      "this comment is content" in built)
check("2 native preserves backslashes in the source",
      r"[^\s@]" in built)

# --- 3. plan is read-only ------------------------------------------------------------------
r = Remote()
env = env_for(r)
r.body[4585] = "<!-- wp:html -->\nold\n<!-- /wp:html -->"
r.status[4585] = "draft"
wpp.cmd_plan(env, "guide-thank-you", 4585, src, "native")
check("3 plan writes nothing", r.writes == 0)
check("3 plan leaves the body untouched", r.body[4585].endswith("old\n<!-- /wp:html -->"))

# --- 4, 5, 10. publish, no-op, backup ------------------------------------------------------
rc = wpp.cmd_publish(env, "guide-thank-you", 4585, src, "native")
check("4 publish returns 0", rc == 0)
check("4 publish stored the built content",
      wpp.norm(r.body[4585]) == wpp.norm(wpp.build("guide-thank-you", src, "native")))
check("4 publish left post_status alone", r.status[4585] == "draft")
check("10 publish backed the old content up", any(p.is_file() for p in (tmp / "backups").glob("*")))
writes = r.writes
wpp.cmd_publish(env, "guide-thank-you", 4585, src, "native")
check("5 a second publish is a no-op", r.writes == writes)

# --- 6. THE BACKSLASH REGRESSION -------------------------------------------------------------
r = Remote(unslash=True)
env = env_for(r)
r.body[4585] = "stale"
r.status[4585] = "draft"
wpp.cmd_publish(env, "guide-thank-you", 4585, src, "native")
check("6 wp_slash() survives a remote that unslashes", r"[^\s@]" in r.body[4585])

# the same write WITHOUT wp_slash must fail, or case 6 proves nothing
orig_publish_src = (ROOT / "scripts" / "wp_publish_post.py").read_text(encoding="utf-8")
noslash_src = orig_publish_src.replace('"post_content" => wp_slash($c)', '"post_content" => $c')
check("6b the script does call wp_slash() on the write path",
      noslash_src != orig_publish_src)
noslash_path = tmp / "noslash.py"
noslash_path.write_text(noslash_src, encoding="utf-8")
nspec = importlib.util.spec_from_file_location("wpp_noslash", noslash_path)
ns = importlib.util.module_from_spec(nspec)
nspec.loader.exec_module(ns)
ns.BACKUPS = tmp / "backups"
r2 = Remote(unslash=True)
ns.ssh = r2
r2.body[4585] = "stale"
r2.status[4585] = "draft"
try:
    ns.cmd_publish({"_user": "stub", "_target": "production"}, "guide-thank-you", 4585,
                   src, "native")
    check("6b a write without wp_slash() is refused", False)
except SystemExit:
    check("6b a write without wp_slash() is refused", True)

# --- 7. the read-back catches a corrupting remote ---------------------------------------------
r = Remote(corrupt=True)
env = env_for(r)
r.body[4585] = "stale"
r.status[4585] = "draft"
try:
    wpp.cmd_publish(env, "guide-thank-you", 4585, src, "native")
    check("7 a corrupting remote is caught by the read-back", False)
except SystemExit:
    check("7 a corrupting remote is caught by the read-back", True)

# --- 8. a post_status that moved is refused -----------------------------------------------------
r = Remote(flip=True)
env = env_for(r)
r.body[4585] = "stale"
r.status[4585] = "draft"
try:
    wpp.cmd_publish(env, "guide-thank-you", 4585, src, "native")
    check("8 a post_status that moved is refused", False)
except SystemExit:
    check("8 a post_status that moved is refused", True)

# --- 9. a remote that does not read stdin is refused ----------------------------------------------
r = Remote(nostdin=True)
env = env_for(r)
r.body[4585] = "stale"
r.status[4585] = "draft"
try:
    wpp.cmd_publish(env, "guide-thank-you", 4585, src, "native")
    check("9 a remote that ignores stdin is refused", False)
except SystemExit:
    check("9 a remote that ignores stdin is refused", True)
check("9 nothing was written when the probe failed", r.writes == 0)

# --- 11. page mode will not drop a pattern ref ------------------------------------------------------
page_src = tmp / "iframed.html"
page_src.write_text(
    "<html><head><style>.hero { color: red; }</style></head>"
    "<body><h1>Title</h1><p>Body copy here.</p></body></html>", encoding="utf-8")
r = Remote()
env = env_for(r)
r.status[1491] = "publish"
r.body[1491] = (
    '<!-- wp:block {"ref":183} /-->\n'
    '<!-- wp:html --><iframe src="https://realtreasury.github.io/x/"></iframe><!-- /wp:html -->\n'
    '<!-- wp:block {"ref":398} /-->')
wpp.cmd_plan(env, "real-treasury-explained", 1491, page_src, "page")
refs_before = re.findall(r'wp:block\s+\{"ref":(\d+)\}', r.body[1491])
check("11 page mode keeps the pattern refs", refs_before == ["183", "398"])

# --- 11b. native mode refuses to drop a pattern ref ----------------------------------------
# The bug this guards: 4202 carried refs 183 and 398 in post_content and mode `native` used
# to emit a bare wp:html block, so the cutover would have taken the site footer off the
# page with nothing in the diff drawing attention to it.
r2 = Remote()
env2 = env_for(r2)
r2.body[4585] = ('<!-- wp:block {"ref":183} /-->\n<!-- wp:html -->\nold\n'
                 '<!-- /wp:html -->\n<!-- wp:block {"ref":398} /-->')
r2.status[4585] = "draft"
saved_render = wpp.render_native
wpp.render_native = lambda text, rel: "<!-- wp:html -->\nbare\n<!-- /wp:html -->"
try:
    wpp.cmd_publish(env2, "guide-thank-you", 4585, src, "native")
    check("11b native refuses a write that would drop a pattern ref", False)
except SystemExit:
    check("11b native refuses a write that would drop a pattern ref", True)
finally:
    wpp.render_native = saved_render
check("11b nothing was written when refs would be dropped", "bare" not in r2.body[4585])

# --- 12. the manifest rejects an unknown mode ---------------------------------------------------------
bad = tmp / "pages.tsv"
bad.write_text("slug\t123\tsome/file.html\tsideways\n", encoding="utf-8")
saved = wpp.MANIFEST
wpp.MANIFEST = bad
try:
    wpp.manifest()
    check("12 an unknown mode is rejected", False)
except SystemExit:
    check("12 an unknown mode is rejected", True)
wpp.MANIFEST = saved

# --- 13. WP-CLI global flags go before the subcommand --------------------------------------------------
misplaced = [c for c in r.commands if re.search(r"\b(eval|eval-file|post)\b.*--quiet", c)]
check("13 no WP-CLI global flag is sent after the subcommand", not misplaced)
check("13 the fake remote did see real commands", len(r.commands) > 0)

# --- the real manifest still parses --------------------------------------------------------------------
modes = {slug: m for slug, (_, _, m) in wpp.manifest().items()}
check("the shipped manifest parses", "guide-download" in modes and modes["guide-download"] == "native")

if failures:
    print(f"\n{len(failures)} check(s) failed", file=sys.stderr)
    raise SystemExit(1)
print("\nall wp_publish_post.py checks passed")
