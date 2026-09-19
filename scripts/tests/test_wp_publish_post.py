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
  14. the per-site identity guard refuses an id that is a different post on this target
  15-17. the WPCode snippet's post AND its rendering option cache
  18. a `*-prod` row names its content region after the post_name, not the row label
  19. restore takes the same identity guard as publish
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
        # What `wp post get <id> --field=post_name/post_type` answers. The identity guard
        # reads these; ids are per-site, so a row's id can point at a different post on a
        # different target and the guard is the only thing that sees it.
        self.name: dict[int, str] = kw.get("name", {})
        self.kind: dict[int, str] = kw.get("kind", {})
        self.unslash = kw.get("unslash", False)
        self.corrupt = kw.get("corrupt", False)
        self.flip = kw.get("flip", False)
        self.nostdin = kw.get("nostdin", False)
        # The wpcode_snippets OPTION: snippet id -> cached code. This, not the post, is
        # what WPCode renders. Absent from this dict means the option has no copy.
        self.snippets: dict[int, str] = dict(kw.get("snippets", {}))
        self.writes = 0
        self.commands: list[str] = []
        self.stdins: list[str] = []

    def __call__(self, env, remote_cmd, stdin=None):
        self.commands.append(remote_cmd)
        # WP-CLI 2.12.0 rejects a global flag placed after the subcommand.
        if re.search(r"\b(eval|eval-file|post)\b.*--quiet", remote_cmd):
            wpp.die("Error: Parameter errors: unknown --quiet parameter")
        m = re.search(r"post get (\d+) --field=post_status", remote_cmd)
        if m:
            return self.status.get(int(m.group(1)), "") + "\n"
        m = re.search(r"post get (\d+) --field=post_name", remote_cmd)
        if m:
            pid = int(m.group(1))
            return self.name.get(pid, DEFAULT_NAMES.get(pid, "")) + "\n"
        m = re.search(r"post get (\d+) --field=post_type", remote_cmd)
        if m:
            pid = int(m.group(1))
            return self.kind.get(pid, DEFAULT_KINDS.get(pid, "page")) + "\n"
        m = re.search(r"get_post\((\d+)\)", remote_cmd)
        if m:
            return self.body.get(int(m.group(1)), "")
        if "eval-file -" in remote_cmd:
            php = (stdin or b"").decode("utf-8")
            self.stdins.append(php)
            if self.nostdin:
                return ""
            if wpp.STDIN_PROBE_TOKEN in php:
                return wpp.STDIN_PROBE_TOKEN
            # Read-only probe of the snippet cache.
            if "get_option(" in php and "update_option(" not in php:
                pid = int(re.search(r'=== (\d+)', php).group(1))
                if pid not in self.snippets:
                    return "0\n"
                blob = base64.b64encode(self.snippets[pid].encode("utf-8")).decode("ascii")
                return "1\n" + blob
            # The WPCode two-write path: $wpdb->update() on the post, then the option.
            if "$wpdb->update(" in php:
                pid = int(re.search(r'"ID" => (\d+)', php).group(1))
                payload = re.search(r'base64_decode\("([^"]*)"\)', php).group(1)
                content = base64.b64decode(payload).decode("utf-8")
                # $wpdb->update() prepares its own values and does NOT wp_unslash them,
                # so backslashes survive without wp_slash() -- and wp_slash() here would
                # write literal extra ones. Model that, so the test would catch either.
                if "wp_slash(" in php:
                    content = content.replace("\\", "\\\\")
                if self.corrupt:
                    content += "X"
                self.body[pid] = content
                hits = 1 if pid in self.snippets else 0
                if hits:
                    self.snippets[pid] = content
                if self.flip:
                    self.status[pid] = "publish"
                self.writes += 1
                return f"1 {hits}"
            pid = int(re.search(r'"ID" => (\d+)', php).group(1))
            # wp_update_post() REFUSES post_type=wpcode: something on that type hooks
            # wp_insert_post_empty_content and returns WP_Error "Content, title, and
            # excerpt are empty" for correct non-empty content. The remote then exits 1
            # and the operator sees a bare `ssh failed (1)`. That is the wall the
            # September 18 nav release hit; keep the fake honest about it.
            if self.kind.get(pid, DEFAULT_KINDS.get(pid, "page")) == "wpcode":
                wpp.die("ssh failed (1): ")
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


# The ids the cases below use, and what they are on the target they pretend to be.
DEFAULT_NAMES = {4585: "guide-thank-you", 1491: "real-treasury-explained",
                 157: "errnot", 1885: "site-header-snippet"}
DEFAULT_KINDS = {4585: "page", 1491: "post", 157: "page", 1885: "wpcode"}


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

# --- 11c. verbatim mode: the whole document, the existing wrapper, no CSS scoping -------------
# /errnot/ was hand-pasted as a full standalone document and already renders natively. Mode
# `page` keeps only the <body> inner HTML, so the <head> that loads Tailwind from a CDN would
# be dropped and every utility class would go unstyled; it also rewrites every selector under
# a per-page wrapper, restyling a page nobody asked to restyle. Mode `native` emits its OWN
# group wrapper, and /errnot/'s is a zero-padding full-bleed one. Hence verbatim.
vb_src = tmp / "errnot.html"
vb_src.write_text(
    '<!DOCTYPE html>\n<html><head><script src="https://cdn.tailwindcss.com"></script>'
    '<title>Embedded title</title><meta name="robots" content="noindex">'
    '<meta name="description" content="Embedded description">'
    '<link rel="canonical" href="https://example.com/wrong/">'
    '<link rel="stylesheet" href="https://fonts.example.com/inter.css">'
    '<style>.hero { color: red; }</style></head>'
    '<body class="rt-no-body-padding"><h1>The Method</h1>'
    '<svg><title>Accessible illustration</title></svg></body></html>', encoding="utf-8")
r3 = Remote()
env3 = env_for(r3)
r3.status[157] = "publish"
WRAP_OPEN = ('<!-- wp:block {"ref":183} /-->\n\n'
             '<!-- wp:group {"align":"full","layout":{"type":"default"}} -->\n'
             '<div class="wp-block-group alignfull" style="padding-top:0">')
WRAP_CLOSE = ('</div>\n<!-- /wp:group -->\n\n<!-- wp:block {"ref":398} /-->\n\n'
              '<!-- wp:paragraph -->\n<p></p>\n<!-- /wp:paragraph -->')
r3.body[157] = WRAP_OPEN + "<!-- wp:html -->\nold pasted page\n<!-- /wp:html -->" + WRAP_CLOSE
wpp.cmd_publish(env3, "errnot", 157, vb_src, "verbatim")
after = r3.body[157]
check("11c verbatim claims the one wp:html block on the first run",
      "old pasted page" not in after)
check("11c verbatim keeps the head, so the Tailwind CDN survives",
      "cdn.tailwindcss.com" in after)
check("11c embedded SEO metadata cannot conflict with WordPress",
      "Embedded title" not in after and "Embedded description" not in after
      and "noindex" not in after and "example.com/wrong" not in after)
check("11c metadata cleanup preserves fonts and accessible SVG titles",
      "fonts.example.com/inter.css" in after and "<title>Accessible illustration</title>" in after)
check("11c verbatim does not scope the CSS", ".hero { color: red; }" in after
      and "rt-page--errnot .hero" not in after)
check("11c verbatim keeps the post's own group wrapper and trailing block",
      after.startswith(WRAP_OPEN) and after.endswith(WRAP_CLOSE))
check("11c verbatim keeps the pattern refs",
      re.findall(r'wp:block\s+\{"ref":(\d+)\}', after) == ["183", "398"])
check("11c verbatim leaves markers so the next run splices them",
      "<!-- rt:page-content errnot -->" in after and "<!-- /rt:page-content -->" in after)
# second run: the markers, not the wp:html block, define the region
r3.body[157] = after
wpp.cmd_publish(env3, "errnot", 157, vb_src, "verbatim")
check("11c a second verbatim run is a no-op", r3.body[157] == after)

# --- 11d. verbatim refuses to guess between two wp:html blocks --------------------------------
r4 = Remote()
env4 = env_for(r4)
r4.status[157] = "publish"
r4.body[157] = ('<!-- wp:block {"ref":183} /-->\n'
                '<!-- wp:html -->\nfirst\n<!-- /wp:html -->\n'
                '<!-- wp:html -->\nsecond\n<!-- /wp:html -->\n'
                '<!-- wp:block {"ref":398} /-->')
try:
    wpp.cmd_publish(env4, "errnot", 157, vb_src, "verbatim")
    check("11d verbatim refuses two candidate blocks", False)
except SystemExit:
    check("11d verbatim refuses two candidate blocks", True)
check("11d nothing was written when it refused",
      "first" in r4.body[157] and "second" in r4.body[157])

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
rows = wpp.manifest()
modes = {slug: r[2] for slug, r in rows.items()}
check("the shipped manifest parses", "guide-download" in modes and modes["guide-download"] == "native")
check("a row without a 5th column defaults post_name to its label",
      rows["errnot"][3] == "errnot")
check("a row whose label is not its slug declares the real post_name",
      rows["guide-download"][3] == "treasury-tech-selection-guide")

# --- 14 the identity guard ------------------------------------------------------------------------------
# Post ids are per site. Production 1519 is the live /2024-tms-selection-guide/ post while
# staging 1519 is how-to-select-a-tms; writing by id alone would replace a published page,
# and the pattern-ref guard cannot see it because between two site pages the refs match.
r14 = Remote(name={1519: "2024-tms-selection-guide"}, kind={1519: "post"})
r14.body[1519] = "<!-- wp:block {\"ref\":183} /-->"
r14.status[1519] = "publish"
env14 = env_for(r14)
try:
    wpp.cmd_publish(env14, "how-to-select-a-tms", 1519, page_src, "page", "how-to-select-a-tms")
    check("14 the guard refuses an id that is a different post on this target", False)
except SystemExit:
    check("14 the guard refuses an id that is a different post on this target", True)
check("14 nothing was written when it refused", r14.writes == 0)

# The same row against the target where the id IS that post must still go through.
r14b = Remote(name={1519: "how-to-select-a-tms"}, kind={1519: "post"})
r14b.body[1519] = ("<!-- wp:block {\"ref\":183} /-->\n<!-- wp:html -->\n"
                   "<iframe src=\"https://realtreasury.github.io/x/\"></iframe>\n"
                   "<!-- /wp:html -->")
r14b.status[1519] = "publish"
wpp.cmd_publish(env_for(r14b), "how-to-select-a-tms", 1519, page_src, "page",
                "how-to-select-a-tms")
check("14 the guard passes when the id is the right post", r14b.writes == 1)

# A raw row points at a WPCode snippet, whose post_name is its own, not the row label.
raw_src = tmp / "snippet.php"
raw_src.write_text("<?php // nav snippet\n<nav class=\"rt-nav-link\">TMS SELECTION</nav>\n",
                   encoding="utf-8")
r14c = Remote(name={1885: "custom-header-html"}, kind={1885: "wpcode"},
              snippets={1885: "old snippet"})
r14c.body[1885] = "old snippet"
r14c.status[1885] = "publish"
wpp.cmd_publish(env_for(r14c), "site-header-snippet", 1885, raw_src, "raw",
                "site-header-snippet")
check("14 a raw row is checked by post_type, not by name", r14c.writes == 1)

# --- 15. the WPCode snippet needs BOTH writes, and not wp_update_post() -------------------
# The post is the editable source; the wpcode_snippets option is what renders. Publishing
# the post alone is the failure that left the live nav saying WEBINARS after a green PR.
check("15 the snippet post was written", r14c.body[1885] == raw_src.read_text())
check("15 the CACHED copy was written too -- this is what the front end serves",
      r14c.snippets[1885] == raw_src.read_text())
check("15 the write went through $wpdb->update, not wp_update_post",
      any("$wpdb->update(" in c for c in r14c.stdins)
      and not any("wp_update_post(" in c for c in r14c.stdins))
check("15 it never calls delete_cache(), which empties the nav site-wide",
      not any("delete_cache" in c for c in r14c.stdins))

# Backslashes: $wpdb->update() prepares its values and does not wp_unslash them, so the
# wpcode path must NOT wp_slash() -- that is the opposite of the wp_update_post() path.
slash_src = tmp / "snippet-slash.php"
slash_src.write_text('<?php $re = "/^[^\\s@]+@[^\\s@]+$/";\n', encoding="utf-8")
r15 = Remote(name={1885: "custom-header-html"}, kind={1885: "wpcode"},
             snippets={1885: "old"}, unslash=True)
r15.body[1885] = "old"
r15.status[1885] = "publish"
wpp.cmd_publish(env_for(r15), "site-header-snippet", 1885, slash_src, "raw",
                "site-header-snippet")
check("15 backslashes survive the wpcode write path",
      r15.snippets[1885] == slash_src.read_text())

# --- 16. no cached copy means the write would not reach the front end --------------------
r16 = Remote(name={1885: "custom-header-html"}, kind={1885: "wpcode"})
r16.body[1885] = "old snippet"
r16.status[1885] = "publish"
try:
    wpp.cmd_publish(env_for(r16), "site-header-snippet", 1885, raw_src, "raw",
                    "site-header-snippet")
    check("16 refuses when the option holds no copy of the snippet", False)
except SystemExit:
    check("16 refuses when the option holds no copy of the snippet", True)
check("16 nothing was written when it refused", r16.writes == 0)

# --- 17. plan on a wpcode row reports the cache, and writes nothing ----------------------
r17 = Remote(name={1885: "custom-header-html"}, kind={1885: "wpcode"},
             snippets={1885: "old snippet"})
r17.body[1885] = "old snippet"
r17.status[1885] = "publish"
wpp.cmd_plan(env_for(r17), "site-header-snippet", 1885, raw_src, "raw",
             "site-header-snippet")
check("17 plan reads the snippet cache", any("get_option(" in c for c in r17.stdins))
check("17 plan writes nothing", r17.writes == 0)

# --- 18. a *-prod row names its region after the post, not after the row ----------------
# This is the whole reason column 5 exists. The row is `how-to-select-a-tms-prod` because
# ids are per site; the POST is `how-to-select-a-tms`. Keying the content region and the
# CSS scope off the row label wrote `rt-page--how-to-select-a-tms-prod` into production
# while staging carried `rt-page--how-to-select-a-tms`, so two sites serving the same
# article no longer diffed clean. 146 occurrences had to be renamed in place.
check("18 page_slug_for prefers the post_name over the row label",
      wpp.page_slug_for("how-to-select-a-tms-prod", "how-to-select-a-tms")
      == "how-to-select-a-tms")
check("18 page_slug_for falls back to the label when column 5 is absent",
      wpp.page_slug_for("errnot", None) == "errnot")

r18 = Remote(name={1519: "how-to-select-a-tms"}, kind={1519: "post"})
r18.body[1519] = ("<!-- wp:block {\"ref\":183} /-->\n<!-- wp:html -->\n"
                  "<iframe src=\"https://realtreasury.github.io/x/\"></iframe>\n"
                  "<!-- /wp:html -->")
r18.status[1519] = "publish"
wpp.cmd_publish(env_for(r18), "how-to-select-a-tms-prod", 1519, page_src, "page",
                "how-to-select-a-tms")
check("18 the region marker carries the post_name, not the row label",
      "rt:page-content how-to-select-a-tms " in r18.body[1519] + " "
      and "how-to-select-a-tms-prod" not in r18.body[1519])
check("18 the CSS scope carries the post_name too",
      "rt-page--how-to-select-a-tms-prod" not in r18.body[1519]
      and "rt-page--how-to-select-a-tms" in r18.body[1519])

# --- 19. restore takes the same identity guard as publish -------------------------------
# restore writes the WHOLE post_content by manifest id, the same blast radius as a
# publish. Unguarded, `restore --target production how-to-select-a-tms` pushes a STAGING
# backup onto production 1519 -- the live post the guard exists for.
backup = tmp / "backup.html"
backup.write_text("<!-- wp:block {\"ref\":183} /-->\nrestored body\n", encoding="utf-8")

r19 = Remote(name={1519: "2024-tms-selection-guide"}, kind={1519: "post"})
r19.body[1519] = "the live 2024 guide"
r19.status[1519] = "publish"
try:
    wpp.cmd_restore(env_for(r19), "how-to-select-a-tms", 1519, "page", str(backup),
                    "how-to-select-a-tms")
    check("19 restore refuses an id that is a different post on this target", False)
except SystemExit:
    check("19 restore refuses an id that is a different post on this target", True)
check("19 nothing was restored over the wrong post", r19.writes == 0
      and r19.body[1519] == "the live 2024 guide")

# The same restore against the target where the id IS that post still goes through.
r19b = Remote(name={1519: "how-to-select-a-tms"}, kind={1519: "post"})
r19b.body[1519] = "current body"
r19b.status[1519] = "publish"
wpp.cmd_restore(env_for(r19b), "how-to-select-a-tms", 1519, "page", str(backup),
                "how-to-select-a-tms")
check("19 restore still works when the id is the right post",
      r19b.body[1519] == backup.read_text(encoding="utf-8"))

if failures:
    print(f"\n{len(failures)} check(s) failed", file=sys.stderr)
    raise SystemExit(1)
print("\nall wp_publish_post.py checks passed")
