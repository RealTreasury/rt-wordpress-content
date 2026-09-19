#!/usr/bin/env python3
"""Publish a repo page into its WordPress post as server-rendered HTML.

    scripts/wp_publish_post.py plan    <slug>
    scripts/wp_publish_post.py publish <slug>
    scripts/wp_publish_post.py restore <slug> <backup-file>

WHY. These pages reached the site inside a cross-origin <iframe> to
realtreasury.github.io. A crawler does not pull a cross-origin frame's text into
the parent document, so the post shipped an iframe wrapper, no <h1>, and none of
its own words. Measured live: the iframed posts carried ~430 words each, which is
the header, nav and footer.

WHAT IT TOUCHES. Only the core/html block holding the iframe, or -- once this has
run before -- only the region between the rt:page-content markers. The header
pattern (wp:block ref 183), any mid-page pattern, the group wrapper and the footer
pattern are preserved byte for byte. Replacing whole post content instead would
silently drop those, and they are what put the nav and footer on the page.

SAFETY. Every publish writes the current post_content to wp-post-backups/ BEFORE
the write, reads the post back afterwards and compares, and refuses if the
read-back does not match what was sent. WordPress revisions are on and unlimited,
so `restore` is a second way back.

BACKSLASHES. wp_update_post() expects SLASHED input and runs wp_unslash() over it,
so content passed raw loses one level of backslashes. That is not hypothetical:
WordPress page 4587 sat with `[^s@]` where its source has `[^\\s@]`, turning "not
whitespace" into "not the letter s" and making the form reject any address whose
local part contains an "s". Hence wp_slash() on both write paths. Without it the
read-back below refuses every backslash-bearing file, which is safe but means the
script simply cannot publish one.
"""
from __future__ import annotations

import argparse
import base64
import difflib
import hashlib
import os
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent / "lib"))
import page_to_block  # noqa: E402

ROOT = Path(__file__).resolve().parents[1]
MANIFEST = ROOT / "wp" / "pages.tsv"
TARGETS = ROOT / "wp" / "targets.tsv"
BACKUPS = Path(os.environ.get("WP_POST_BACKUPS", Path.home() / "wp-post-backups"))
ENV_FILE = Path(os.environ.get("WPCOM_SSH_ENV", "/opt/rt-ai/secrets/wpcom-ssh.env"))
KNOWN_HOSTS = ROOT / "scripts" / "wpcom_known_hosts"

WPCODE_OPTION = "wpcode_snippets"

START_RE = re.compile(r"<!--\s*rt:page-content\s+([\w-]+)\s*-->")
END_MARK = "<!-- /rt:page-content -->"


def die(msg: str) -> None:
    print(f"ERROR: {msg}", file=sys.stderr)
    raise SystemExit(1)


def load_env() -> dict[str, str]:
    if not ENV_FILE.exists():
        die(f"{ENV_FILE} not found (render it from the rt-ai secrets flow first)")
    env = {}
    for line in ENV_FILE.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if line and not line.startswith("#") and "=" in line:
            k, v = line.split("=", 1)
            env[k.strip()] = v.strip().strip('"').strip("'")
    for key in ("WPCOM_SSH_USER", "WPCOM_SSH_HOST", "WPCOM_SSH_KEY_FILE"):
        if not env.get(key):
            die(f"{key} missing from {ENV_FILE}")
    return env


def targets(env: dict[str, str]) -> dict[str, str]:
    """target name -> ssh user. The KEY stays in the secrets rail; a username is not
    a credential, so it is versioned here rather than hidden."""
    if not TARGETS.exists():
        die(f"{TARGETS} not found")
    out = {}
    for n, line in enumerate(TARGETS.read_text(encoding="utf-8").splitlines(), 1):
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        parts = line.split("\t")
        if len(parts) != 2:
            die(f"{TARGETS}:{n}: expected 'target<TAB>ssh_user'")
        name, user = parts
        if user == "${WPCOM_SSH_USER}":
            user = env["WPCOM_SSH_USER"]
        out[name] = user
    return out


def ssh(env: dict[str, str], remote_cmd: str, stdin: bytes | None = None) -> str:
    if not KNOWN_HOSTS.is_file() or KNOWN_HOSTS.stat().st_size == 0:
        die(f"pinned host key file {KNOWN_HOSTS} missing or empty")
    cmd = [
        "ssh", "-i", env["WPCOM_SSH_KEY_FILE"],
        "-o", "IdentitiesOnly=yes", "-o", "BatchMode=yes",
        "-o", "StrictHostKeyChecking=yes",
        "-o", f"UserKnownHostsFile={KNOWN_HOSTS}",
        "-o", "GlobalKnownHostsFile=/dev/null",
        "-o", "ConnectTimeout=30",
        f"{env['_user']}@{env['WPCOM_SSH_HOST']}", remote_cmd,
    ]
    r = subprocess.run(cmd, input=stdin, capture_output=True, timeout=180)
    if r.returncode != 0:
        die(f"ssh failed ({r.returncode}): {r.stderr.decode('utf-8', 'replace')[:500]}")
    return r.stdout.decode("utf-8", "replace")


def manifest() -> dict[str, tuple[int, Path, str, str]]:
    """slug -> (post id, source file, mode, post_name).

    post_name is column 5: the name the post must already carry on the target being
    written. It defaults to the row label and diverges only for the `*-prod` rows,
    where ids are per site. `check_identity()` refuses on a mismatch and
    `page_slug_for()` keys the content region off it.

    mode `page` wraps the source page's body+CSS in a core/html block and splices it
    into the post, preserving the surrounding pattern refs.

    mode `raw` writes the source file as the whole post_content, byte for byte. That is
    for posts whose content IS the file — the site header lives in WPCode snippet post
    1885 (`rt-wordpress-content/header/main-menu /custom-header.php`), which is a normal
    post holding PHP. Splicing would be wrong there; there is no block structure to keep.

    mode `native` is `raw` for the WordPress-NATIVE pages: the post's content is the file
    wrapped as one Custom HTML block, with the repo header comment swapped for a one-line
    source-of-record note. That derivation is deterministic, which is the point — `plan`
    then compares exactly instead of squinting past "expected paste noise", which is the
    only reason page 4587's stripped backslashes were visible as a diff at all.

    mode `verbatim` is for a page already hand-pasted as a whole standalone document:
    the file IS post_content, spliced into the wrapper and pattern refs the post
    already carries. /errnot/ needs it — `page` would drop the <head> that loads its
    CDN Tailwind, and `native` would replace its full-bleed wrapper with a constrained
    one.
    """
    if not MANIFEST.exists():
        die(f"{MANIFEST} not found")
    out = {}
    for n, line in enumerate(MANIFEST.read_text(encoding="utf-8").splitlines(), 1):
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        parts = line.split("\t")
        if len(parts) == 3:
            parts.append("page")
        if len(parts) == 4:
            # No 5th column: the row's label IS the post_name we expect to find.
            parts.append(parts[0])
        if len(parts) != 5:
            die(f"{MANIFEST}:{n}: expected "
                f"'slug<TAB>post_id<TAB>source[<TAB>mode[<TAB>post_name]]'")
        slug, post_id, src, mode, post_name = parts
        if mode not in ("page", "raw", "native", "verbatim"):
            die(f"{MANIFEST}:{n}: mode must be 'page', 'raw', 'native' or 'verbatim', "
                f"got {mode!r}")
        out[slug] = (int(post_id), ROOT / src, mode, post_name)
    return out


def fetch_content(env, post_id: int) -> str:
    php = f'echo get_post({post_id}) ? get_post({post_id})->post_content : "";'
    return ssh(env, f"wp --quiet eval {shell_quote(php)}")


def fetch_status(env, post_id: int) -> str:
    return ssh(env, f"wp --quiet post get {post_id} --field=post_status").strip()


def eval_php(env, php: str) -> str:
    return ssh(env, "wp --quiet eval-file -", stdin=php.encode("utf-8")).strip()


def wpcode_cache(env, post_id: int) -> tuple[int, str]:
    """The snippet's CACHED code, and how many copies of it the option holds.

    WPCode renders from the `wpcode_snippets` OPTION, not from the snippet post.
    The post is the editable source; the option is a separate ~44 KB copy keyed by
    snippet id. Writing the post alone changes nothing on the front end -- verified
    on staging September 18, 2026, where the post said TMS SELECTION and every
    served page still said WEBINARS, with nothing in any log.
    """
    php = "\n".join([
        "<?php",
        f'$o = get_option({php_str(WPCODE_OPTION)});',
        'if (!is_array($o)) { fwrite(STDERR, "wpcode_snippets is not an array\\n"); exit(1); }',
        '$found = [];',
        '$walk = function ($node) use (&$walk, &$found) {',
        '    if (!is_array($node)) { return; }',
        f'    if (isset($node["id"]) && (int) $node["id"] === {post_id} && isset($node["code"])) {{',
        '        $found[] = (string) $node["code"];',
        '    }',
        '    foreach ($node as $child) { $walk($child); }',
        '};',
        '$walk($o);',
        'echo count($found) . "\\n";',
        'if ($found) { echo base64_encode($found[0]); }',
    ])
    out = eval_php(env, php).split("\n", 1)
    count = int(out[0].strip())
    code = base64.b64decode(out[1].strip()).decode("utf-8") if count and len(out) > 1 else ""
    return count, code


def check_identity(env, slug: str, post_id: int, mode: str,
                   post_name: str | None = None) -> str:
    """Refuse if post_id is not the post this slug names ON THIS TARGET.

    pages.tsv carries ONE id per slug, but ids are per-site. Staging was cloned
    at a point in time and the two sites have drifted: staging 1519 is
    how-to-select-a-tms, production 1519 is the live /2024-tms-selection-guide/
    post, and staging 4799 is tms-selection-mistakes while production 4799 is a
    Flamingo spam record. Writing by id alone would overwrite an unrelated,
    published page and nothing else in this script would notice -- the pattern-ref
    guard passes happily when the refs match, which between two site pages they do.
    """
    post_name = post_name or slug
    name = ssh(env, f"wp --quiet post get {post_id} --field=post_name").strip()
    kind = ssh(env, f"wp --quiet post get {post_id} --field=post_type").strip()
    if mode == "raw":
        # A raw row targets a WPCode snippet, whose post_name is the snippet's own
        # (custom-header-html), not the manifest label. What matters there is that the
        # id still points at a snippet and not at a page that happens to share the id.
        if kind == "wpcode":
            return kind
        die(f"refusing: post {post_id} on this target is a '{kind}' named '{name}', "
            f"not the wpcode snippet '{slug}' expects.")
    if name != post_name:
        die(f"refusing: post {post_id} on this target is '{name}' ({kind}), not "
            f"'{post_name}' as row '{slug}' declares. "
            f"pages.tsv holds one id per row and ids are per-site; resolve the id for "
            f"this target (wp post list --name={post_name}) before writing.")
    return kind


def shell_quote(s: str) -> str:
    return "'" + s.replace("'", "'\\''") + "'"


def php_str(s: str) -> str:
    return '"' + s.replace("\\", "\\\\").replace('"', '\\"').replace("$", "\\$") + '"'


def splice(current: str, block: str, slug: str, mode: str = "page") -> str:
    """Put `block` where the iframe (or a previous run's region) is.

    Everything outside that one region is preserved exactly -- the header and
    footer pattern refs live there.
    """
    m = START_RE.search(current)
    if m:
        if m.group(1) != slug:
            die(f"post carries a content region for '{m.group(1)}', not '{slug}'")
        end = current.find(END_MARK, m.end())
        if end == -1:
            die("found an rt:page-content start marker with no end marker")
        return current[:m.start()] + block + current[end + len(END_MARK):]

    # First run: replace the core/html block that holds the github.io iframe.
    blocks = list(re.finditer(r"<!--\s*wp:html\s*-->([\s\S]*?)<!--\s*/wp:html\s*-->", current))
    for hm in blocks:
        if "realtreasury.github.io" in hm.group(1):
            return current[:hm.start()] + block + current[hm.end():]
    if mode == "verbatim":
        # A page that was ALREADY hand-pasted as a native document has no iframe to aim
        # at. Its single Custom HTML block is the page, so that is the region -- but only
        # if there is exactly one. Two blocks means guessing which one is the body, and
        # guessing here overwrites whichever half is not the page.
        if len(blocks) == 1:
            return current[:blocks[0].start()] + block + current[blocks[0].end():]
        die(f"verbatim first run needs exactly one wp:html block to claim; found "
            f"{len(blocks)}. Add the rt:page-content markers by hand first.")
    die("no rt:page-content region and no wp:html block containing a github.io iframe. "
        "A post created by `wp post create` has empty content and gives splice() nothing "
        "to claim: seed it in WP Admin first with the header ref, the full-bleed group, an "
        f"empty `<!-- rt:page-content {slug} -->` / `<!-- /rt:page-content -->` pair and the "
        "footer ref. See docs/GO-LIVE-SEPT-2026.md.")


STDIN_PROBE_TOKEN = "RT_STDIN_OK"


def probe_stdin(env) -> bool:
    """`wp eval-file -` reads the script from stdin. An SSH jail that does not forward it
    would otherwise fail in the middle of a write with no useful message, so prove the
    transport with a throwaway script first — using the same invocation as the write."""
    php = f'<?php echo "{STDIN_PROBE_TOKEN}";'.encode("utf-8")
    try:
        return ssh(env, "wp --quiet eval-file -", stdin=php).strip() == STDIN_PROBE_TOKEN
    except SystemExit:
        return False


def norm(text: str) -> str:
    """Line endings and the trailing newline normalised away.

    The live WPCode snippet is stored CRLF (337 of its 338 lines); the repo file is LF.
    That is a 336-byte difference on a file that is otherwise identical, and comparing raw
    bytes makes every publish look like a full rewrite and the no-op check never fire.
    difflib.splitlines() hides it the other way -- it reported "identical" while the byte
    counts differed -- so neither raw bytes nor split lines is the right comparison.
    """
    return text.replace("\r\n", "\n").replace("\r", "\n").rstrip("\n")


def words(html: str) -> int:
    t = re.sub(r"<(script|style)[\s\S]*?</\1>", " ", html, flags=re.I)
    return len(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", t)).split())


NATIVE_NOTE = (
    "<!-- Source of record: {rel} in RealTreasury/rt-wordpress-content. Edit there, then "
    "publish with scripts/wp_publish_post.py. Native page, not an iframe embed: same-origin "
    "API calls, no height negotiation, and the content is crawlable. -->"
)


HEADER_REF = 183   # wp_block "Header Menu"
FOOTER_REF = 398   # wp_block "Footer"


def render_native(text: str, rel: str) -> str:
    """The repo file as one Custom HTML block, between the site's header and footer patterns.

    The leading header comment is repo-facing — it talks about paths and PR history — so it
    is dropped and replaced by a one-line note that tells whoever opens the page in
    WordPress where to edit it. Only a comment that STARTS the file counts as the header; a
    comment further in is content.

    THE PATTERNS ARE NOT OPTIONAL. Every page on this site carries the nav and the footer as
    synced patterns INSIDE post_content — `wp:block {"ref":183}` then a full-width group
    holding the page, then `wp:block {"ref":398}`. The theme supplies a menu of its own, so a
    page missing ref 183 still looks navigable and the loss is easy to miss; the FOOTER has no
    such understudy and simply vanishes, taking Quick Links, the contact block, the copyright
    line and the Privacy/Terms/Cookie/Sitemap links with it. That is what happened to page
    4809 when it was first published from this rail: it went live with no footer at all, and
    nothing complained. Emit the same sandwich every other page uses.
    """
    body = re.sub(r"\A\s*<!--[\s\S]*?-->\s*", "", text, count=1)
    return (
        '<!-- wp:block {"ref":%d} /-->\n'
        '\n'
        '<!-- wp:group {"align":"full","layout":{"type":"constrained"}} -->\n'
        '<div class="wp-block-group alignfull"><!-- wp:html -->\n'
        '%s\n'
        '%s\n'
        '<!-- /wp:html --></div>\n'
        '<!-- /wp:group -->\n'
        '\n'
        '<!-- wp:block {"ref":%d} /-->'
    ) % (HEADER_REF, NATIVE_NOTE.format(rel=rel), body.rstrip("\n"), FOOTER_REF)


def render_verbatim(text: str, slug: str) -> str:
    """Keep the page's assets and styles inside its existing group wrapper.

    For pages that were hand-pasted as a whole standalone document and already render
    natively -- /errnot/ is the case this was written for. Mode `page` is wrong for them
    twice over: it keeps only the <body> inner HTML, so a <head> that loads Tailwind from
    a CDN is silently dropped and every utility class goes unstyled; and it rewrites every
    selector under a per-page wrapper, which is a visual change to a page nobody asked to
    restyle. Mode `native` is wrong too -- it emits its own group wrapper, and /errnot/'s
    is a zero-padding full-bleed one, not the constrained wrapper native builds.

    WordPress/Yoast owns document metadata. Remove the embedded document's title,
    meta tags and canonical link so they cannot compete with the real page head.
    Restrict this to the source head: SVG titles and body content must survive.
    Styles, font links and scripts (including Tailwind) remain unchanged.
    """
    def without_metadata(match: re.Match) -> str:
        head = re.sub(r"<title\b[^>]*>[\s\S]*?</title\s*>", "", match.group(1), flags=re.I)
        head = re.sub(r"<meta\b[^>]*>", "", head, flags=re.I)
        head = re.sub(r'''<link\b(?=[^>]*\brel\s*=\s*["']canonical["'])[^>]*>''', "", head, flags=re.I)
        return "<head>" + head + "</head>"

    text = re.sub(r"<head\b[^>]*>([\s\S]*?)</head\s*>", without_metadata, text, count=1, flags=re.I)
    return "\n".join([
        page_to_block.START.format(slug=slug),
        "<!-- wp:html -->",
        text.rstrip("\n"),
        "<!-- /wp:html -->",
        page_to_block.END,
    ])


def rel_to_root(path: Path) -> str:
    """The repo-relative path, for labelling a diff and for the source-of-record note.

    Every manifest source is under ROOT, so this is the path in practice. Only a test
    fixture ever sits outside it, and falling back to the name beats raising out of
    relative_to() on a display string.
    """
    try:
        return str(path.relative_to(ROOT))
    except ValueError:
        return path.name


def build(slug: str, source: Path, mode: str) -> str:
    if not source.exists():
        die(f"source page {source} not found")
    text = source.read_text(encoding="utf-8")
    if mode == "raw":
        return text
    if mode == "native":
        return render_native(text, rel_to_root(source))
    if mode == "verbatim":
        return render_verbatim(text, slug)
    return page_to_block.render(text, slug)


def page_slug_for(slug: str, post_name: str | None) -> str:
    """The slug that names the content region and scopes its CSS.

    It is the post_name, not the manifest row label. Those are the same thing for
    every row that targets one site, but they diverge for the `*-prod` rows, which
    exist only because post ids are per site: the row is `how-to-select-a-tms-prod`
    and the post is `how-to-select-a-tms`. Keying the region off the label would put
    `rt-page--how-to-select-a-tms-prod` into production's markup while staging carried
    `rt-page--how-to-select-a-tms` -- two sites whose HTML no longer diffs cleanly, for
    no reason but a bookkeeping name. Column 5 already declares the post_name; use it.
    """
    return post_name or slug


def cmd_plan(env, slug, post_id, source, mode, post_name=None) -> int:
    kind = check_identity(env, slug, post_id, mode, post_name)
    page_slug = page_slug_for(slug, post_name)
    current = fetch_content(env, post_id)
    status = fetch_status(env, post_id)
    block = build(page_slug, source, mode)
    new = block if mode in ("raw", "native") else splice(current, block, page_slug, mode)
    print(f"-- post {post_id} ({slug}), post_status {status}")
    if kind == "wpcode":
        # The post is the editable source; the option is what renders. Say so here,
        # because a plan that only diffs the post reads as if the post were the deploy.
        count, cached = wpcode_cache(env, post_id)
        if count == 0:
            print(f"-- {WPCODE_OPTION}: NO cached copy of snippet {post_id}. That option is "
                  f"what renders; a publish would refuse.", file=sys.stderr)
        else:
            state = "in sync with the post" if norm(cached) == norm(current) else "DRIFTED"
            print(f"-- {WPCODE_OPTION}: {count} cached cop{'y' if count == 1 else 'ies'}, "
                  f"{len(cached)} bytes, {state}. This is what the front end serves; "
                  f"a publish rewrites it too.")
    print(f"-- live now : {len(current):>7} bytes, {words(current):>5} words, "
          f"h1={'yes' if re.search(r'<h1', current, re.I) else 'NO'}, "
          f"iframe={'yes' if 'github.io' in current else 'no'}")
    print(f"-- would be : {len(new):>7} bytes, {words(new):>5} words, "
          f"h1={'yes' if re.search(r'<h1', new, re.I) else 'NO'}, "
          f"iframe={'yes' if 'github.io' in new else 'no'}")
    if mode in ("raw", "native"):
        # No word-count or h1 signal to read here, and a whole-content write replaces
        # everything,
        # so show the actual change. Whitespace-only drift between a pasted snippet and
        # the repo file is common and worth seeing before it is written.
        crlf = current.count("\r\n")
        if crlf and "\r\n" not in new:
            print(f"-- line endings: live is CRLF on {crlf} line(s), the repo file is LF. "
                  "A publish rewrites them to LF once; later runs then compare clean.")
        d = list(difflib.unified_diff(
            norm(current).split("\n"), norm(new).split("\n"),
            fromfile="live", tofile=rel_to_root(source), lineterm="", n=1))
        if not d:
            print("-- identical apart from line endings"
                  if norm(current) == norm(new) and current != new else "-- identical")
        else:
            visible = [l for l in d if l.startswith(("+", "-")) and not l.startswith(("+++", "---"))]
            ws_only = all(l[1:].strip() == "" or
                          any(o[1:].strip() == l[1:].strip() for o in visible if o[0] != l[0])
                          for l in visible)
            print(f"-- {len(visible)} changed line(s)"
                  f"{'  (whitespace only)' if ws_only else ''}")
            for line in d[:40]:
                print("   " + line)
            if len(d) > 40:
                print(f"   ... {len(d) - 40} more diff lines")
    if mode in ("page", "native", "verbatim"):
        preserved = re.findall(r'wp:block\s+\{"ref":(\d+)\}', current)
        kept = re.findall(r'wp:block\s+\{"ref":(\d+)\}', new)
        dropped = [r for r in preserved if r not in kept]
        print(f"-- pattern refs: before {preserved} -> after {kept}"
              f"{'  *** WOULD DROP ' + ','.join(dropped) + ' ***' if dropped else '  OK'}")
        if dropped:
            die("the write would drop a pattern reference; refusing to call this a safe plan")
    return 0


def write_wpcode(env, post_id: int, current: str, new: str, backup: Path) -> None:
    """Write a WPCode snippet -- which takes TWO writes, and not wp_update_post().

    1. `wp_update_post()` REFUSES this post type: something on `wpcode` hooks
       wp_insert_post_empty_content and the call comes back WP_Error "Content, title,
       and excerpt are empty" even with correct non-empty content. It refuses rather
       than corrupting, so nothing breaks -- the publish just dies with the remote's
       exit 1. That is the `ssh failed (1)` that stopped the September 18 nav release.
       $wpdb->update() on post_content plus clean_post_cache() is the way in.

    2. The post is not what renders. WPCode serves the `wpcode_snippets` option, a
       separate cached copy of every active snippet's code keyed by snippet id. Patch
       only the post and the front end keeps serving the old nav, silently. Patch the
       option through get_option/update_option so WordPress handles serialization --
       never string-replace the serialized form, whose byte-length prefixes any change
       in length would corrupt. Do not reach for wpcode()->cache->delete_cache(): it
       empties the option and does not rebuild it, which takes the nav off every page.
    """
    count, cached = wpcode_cache(env, post_id)
    if count == 0:
        die(f"no snippet with id {post_id} in the {WPCODE_OPTION} option. That option is "
            f"what renders; refusing to write a post the front end will ignore.")
    if count > 1:
        die(f"{count} cached copies of snippet {post_id} in {WPCODE_OPTION}; expected 1. "
            f"Refusing rather than guessing which one renders.")
    cache_backup = backup.with_suffix(".wpcode-option.txt")
    cache_backup.write_text(cached, encoding="utf-8")
    print(f"-- backed up {len(cached)} bytes of cached snippet code to {cache_backup}")
    if norm(cached) != norm(current):
        print(f"-- note: the cached copy ({len(cached)} bytes) and post {post_id} "
              f"({len(current)} bytes) had DRIFTED. Both are being set to the repo file.",
              file=sys.stderr)

    payload = base64.b64encode(new.encode("utf-8")).decode("ascii")
    digest = hashlib.sha256(new.encode("utf-8")).hexdigest()
    php = "\n".join([
        "<?php",
        "kses_remove_filters();",
        "global $wpdb;",
        f'$c = base64_decode("{payload}");',
        f'if (hash("sha256", $c) !== "{digest}") {{ fwrite(STDERR, "payload hash mismatch\\n"); exit(1); }}',
        # $wpdb->update() prepares its own values, so this takes the content RAW --
        # wp_slash() here would write literal backslashes into the snippet.
        f'$n = $wpdb->update($wpdb->posts, ["post_content" => $c], ["ID" => {post_id}]);',
        'if ($n === false) { fwrite(STDERR, "wpdb update failed: " . $wpdb->last_error . "\\n"); exit(1); }',
        f"clean_post_cache({post_id});",
        f'$o = get_option({php_str(WPCODE_OPTION)});',
        '$hits = 0;',
        '$patch = function (&$node) use (&$patch, $c, &$hits) {',
        '    if (!is_array($node)) { return; }',
        f'    if (isset($node["id"]) && (int) $node["id"] === {post_id} && isset($node["code"])) {{',
        '        $node["code"] = $c;',
        '        $hits++;',
        '    }',
        '    foreach ($node as &$child) { $patch($child); }',
        '    unset($child);',
        '};',
        '$patch($o);',
        'if ($hits !== 1) { fwrite(STDERR, "patched $hits cached copies, expected 1\\n"); exit(1); }',
        f'update_option({php_str(WPCODE_OPTION)}, $o);',
        'echo "$n $hits";',
    ])
    if not probe_stdin(env):
        die("the remote did not return the STDIN probe token: 'wp eval-file -' is not reading "
            "the script this end sends. Refusing to attempt the write.")
    out = eval_php(env, php)
    if not re.fullmatch(r"\d+ 1", out):
        die(f"unexpected write result {out!r}; "
            f"restore with: {sys.argv[0]} restore <slug> {backup}")
    print(f"-- wrote post {post_id} and 1 cached copy in {WPCODE_OPTION}")

    # The option is the thing that renders, so read IT back, not just the post.
    _, after = wpcode_cache(env, post_id)
    if norm(after) != norm(new):
        die(f"cached snippet read-back mismatch: live {len(after)} bytes != sent {len(new)}. "
            f"restore with: {sys.argv[0]} restore <slug> {backup}")
    print(f"-- cache read-back OK: {len(after)} bytes")


def cmd_publish(env, slug, post_id, source, mode, post_name=None) -> int:
    kind = check_identity(env, slug, post_id, mode, post_name)
    page_slug = page_slug_for(slug, post_name)
    current = fetch_content(env, post_id)
    status = fetch_status(env, post_id)
    block = build(page_slug, source, mode)
    new = block if mode in ("raw", "native") else splice(current, block, page_slug, mode)
    if norm(new) == norm(current):
        if new != current:
            print("-- content matches; only line endings differ. Publishing to normalise them.")
        else:
            print("-- already published: nothing to do")
            return 0
    if mode in ("page", "native", "verbatim"):
        # A DROP is the failure that matters: the page silently loses its nav or footer.
        # Gaining a ref is how a native page that was published without them gets repaired,
        # so allow that rather than forcing a hand-edit in WP Admin.
        before_refs = re.findall(r'wp:block\s+\{"ref":(\d+)\}', current)
        after_refs = re.findall(r'wp:block\s+\{"ref":(\d+)\}', new)
        dropped = [r for r in before_refs if r not in after_refs]
        if dropped:
            die(f"refusing: the write would drop pattern ref(s) {dropped} "
                f"({before_refs} -> {after_refs})")

    BACKUPS.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup = BACKUPS / f"{env['_target']}-{post_id}-{slug}-{stamp}.html"
    backup.write_text(current, encoding="utf-8")
    print(f"-- backed up {len(current)} bytes to {backup}")

    if kind == "wpcode":
        write_wpcode(env, post_id, current, new, backup)
    else:
        payload = base64.b64encode(new.encode("utf-8")).decode("ascii")
        digest = hashlib.sha256(new.encode("utf-8")).hexdigest()
        php = "\n".join([
            "<?php",
            "kses_remove_filters();",
            f'$c = base64_decode("{payload}");',
            f'if (hash("sha256", $c) !== "{digest}") {{ fwrite(STDERR, "payload hash mismatch\\n"); exit(1); }}',
            f'$r = wp_update_post(["ID" => {post_id}, "post_content" => wp_slash($c)], true);',
            'if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\\n"); exit(1); }',
            'echo $r;',
        ])
        if not probe_stdin(env):
            die("the remote did not return the STDIN probe token: 'wp eval-file -' is not "
                "reading the script this end sends. Refusing to attempt the write.")
        out = ssh(env, "wp --quiet eval-file -", stdin=php.encode("utf-8")).strip()
        if not out.isdigit():
            die(f"write returned no post ID: {out!r}")
        print(f"-- wrote post {out}")

    back = fetch_content(env, post_id)
    if norm(back) != norm(new):
        print(f"READ-BACK MISMATCH: live {len(back)} bytes != sent {len(new)}", file=sys.stderr)
        die(f"restore with: {sys.argv[0]} restore {slug} {backup}")
    # This script writes post_content and nothing else. Several of the pages it now handles
    # are drafts staged for a release, and publishing one is a deliberate act in WP Admin —
    # never a side effect of syncing its body. If post_status moved, something is wrong.
    after = fetch_status(env, post_id)
    if after != status:
        die(f"post_status changed {status} -> {after}; it should not have. "
            f"restore with: {sys.argv[0]} restore {slug} {backup}")
    print(f"-- read-back OK: {len(back)} bytes, {words(back)} words; still {after}")
    return 0


def cmd_restore(env, slug, post_id, mode, backup_path: str, post_name=None) -> int:
    # A restore is a full post_content write, the same blast radius as a publish, so it
    # takes the same guard. Without it `restore --target production how-to-select-a-tms`
    # would push a STAGING backup onto production 1519 -- the live post this row's id
    # trap is about -- because ids are per site and nothing downstream re-checks.
    kind = check_identity(env, slug, post_id, mode, post_name)
    content = Path(backup_path).read_text(encoding="utf-8")
    if kind == "wpcode":
        # Restoring the post alone would leave the live nav on the rolled-forward code,
        # because the option is what renders. write_wpcode puts both back.
        write_wpcode(env, post_id, fetch_content(env, post_id), content,
                     Path(backup_path).with_suffix(".rollback"))
        print(f"-- restored post {post_id} and its cached copy from {backup_path} "
              f"({len(content)} bytes)")
        return 0
    payload = base64.b64encode(content.encode("utf-8")).decode("ascii")
    digest = hashlib.sha256(content.encode("utf-8")).hexdigest()
    php = "\n".join([
        "<?php",
        "kses_remove_filters();",
        f'$c = base64_decode("{payload}");',
        f'if (hash("sha256", $c) !== "{digest}") {{ fwrite(STDERR, "payload hash mismatch\\n"); exit(1); }}',
        f'$r = wp_update_post(["ID" => {post_id}, "post_content" => wp_slash($c)], true);',
        'if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\\n"); exit(1); }',
        'echo $r;',
    ])
    ssh(env, "wp --quiet eval-file -", stdin=php.encode("utf-8"))
    back = fetch_content(env, post_id)
    if norm(back) != norm(content):
        die("restore read-back did not match the backup")
    print(f"-- restored post {post_id} from {backup_path} ({len(content)} bytes)")
    return 0


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--target", default="production",
                   help="which site to act on, from wp/targets.tsv (default: %(default)s)")
    p.add_argument("command", choices=["plan", "publish", "restore"])
    p.add_argument("slug")
    p.add_argument("backup", nargs="?")
    a = p.parse_args(argv)
    pages = manifest()
    if a.slug not in pages:
        die(f"'{a.slug}' is not in {MANIFEST}")
    post_id, source, mode, post_name = pages[a.slug]
    env = load_env()
    known = targets(env)
    if a.target not in known:
        die(f"unknown target '{a.target}'; {TARGETS} has {sorted(known)}")
    env["_user"] = known[a.target]
    env["_target"] = a.target
    print(f"-- target: {a.target}  mode: {mode}")
    if a.command == "plan":
        return cmd_plan(env, a.slug, post_id, source, mode, post_name)
    if a.command == "publish":
        return cmd_publish(env, a.slug, post_id, source, mode, post_name)
    if not a.backup:
        die("restore needs a backup file")
    return cmd_restore(env, a.slug, post_id, mode, a.backup, post_name)


if __name__ == "__main__":
    raise SystemExit(main())
