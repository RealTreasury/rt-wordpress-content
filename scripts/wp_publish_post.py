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


def manifest() -> dict[str, tuple[int, Path, str]]:
    """slug -> (post id, source file, mode).

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
        if len(parts) != 4:
            die(f"{MANIFEST}:{n}: expected 'slug<TAB>post_id<TAB>source[<TAB>mode]'")
        slug, post_id, src, mode = parts
        if mode not in ("page", "raw", "native"):
            die(f"{MANIFEST}:{n}: mode must be 'page', 'raw' or 'native', got {mode!r}")
        out[slug] = (int(post_id), ROOT / src, mode)
    return out


def fetch_content(env, post_id: int) -> str:
    php = f'echo get_post({post_id}) ? get_post({post_id})->post_content : "";'
    return ssh(env, f"wp --quiet eval {shell_quote(php)}")


def fetch_status(env, post_id: int) -> str:
    return ssh(env, f"wp --quiet post get {post_id} --field=post_status").strip()


def shell_quote(s: str) -> str:
    return "'" + s.replace("'", "'\\''") + "'"


def splice(current: str, block: str, slug: str) -> str:
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
    for hm in re.finditer(r"<!--\s*wp:html\s*-->([\s\S]*?)<!--\s*/wp:html\s*-->", current):
        if "realtreasury.github.io" in hm.group(1):
            return current[:hm.start()] + block + current[hm.end():]
    die("no rt:page-content region and no wp:html block containing a github.io iframe")


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


def render_native(text: str, rel: str) -> str:
    """The repo file as one Custom HTML block.

    The leading header comment is repo-facing — it talks about paths and PR history — so it
    is dropped and replaced by a one-line note that tells whoever opens the page in
    WordPress where to edit it. Only a comment that STARTS the file counts as the header; a
    comment further in is content.
    """
    body = re.sub(r"\A\s*<!--[\s\S]*?-->\s*", "", text, count=1)
    return "<!-- wp:html -->\n%s\n%s\n<!-- /wp:html -->" % (
        NATIVE_NOTE.format(rel=rel), body.rstrip("\n"))


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
    return page_to_block.render(text, slug)


def cmd_plan(env, slug, post_id, source, mode) -> int:
    current = fetch_content(env, post_id)
    status = fetch_status(env, post_id)
    block = build(slug, source, mode)
    new = block if mode in ("raw", "native") else splice(current, block, slug)
    print(f"-- post {post_id} ({slug}), post_status {status}")
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
    if mode == "page":
        preserved = re.findall(r'wp:block\s+\{"ref":(\d+)\}', current)
        kept = re.findall(r'wp:block\s+\{"ref":(\d+)\}', new)
        print(f"-- pattern refs: before {preserved} -> after {kept}"
              f"{'  OK' if preserved == kept else '  *** PATTERNS CHANGED ***'}")
        if preserved != kept:
            die("the splice would drop a pattern reference; refusing to call this a safe plan")
    return 0


def cmd_publish(env, slug, post_id, source, mode) -> int:
    current = fetch_content(env, post_id)
    status = fetch_status(env, post_id)
    block = build(slug, source, mode)
    new = block if mode in ("raw", "native") else splice(current, block, slug)
    if norm(new) == norm(current):
        if new != current:
            print("-- content matches; only line endings differ. Publishing to normalise them.")
        else:
            print("-- already published: nothing to do")
            return 0
    if mode == "page":
        before_refs = re.findall(r'wp:block\s+\{"ref":(\d+)\}', current)
        after_refs = re.findall(r'wp:block\s+\{"ref":(\d+)\}', new)
        if before_refs != after_refs:
            die(f"refusing: pattern refs would change {before_refs} -> {after_refs}")

    BACKUPS.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup = BACKUPS / f"{env['_target']}-{post_id}-{slug}-{stamp}.html"
    backup.write_text(current, encoding="utf-8")
    print(f"-- backed up {len(current)} bytes to {backup}")

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
        die("the remote did not return the STDIN probe token: 'wp eval-file -' is not reading "
            "the script this end sends. Refusing to attempt the write.")
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


def cmd_restore(env, slug, post_id, backup_path: str) -> int:
    content = Path(backup_path).read_text(encoding="utf-8")
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
    post_id, source, mode = pages[a.slug]
    env = load_env()
    known = targets(env)
    if a.target not in known:
        die(f"unknown target '{a.target}'; {TARGETS} has {sorted(known)}")
    env["_user"] = known[a.target]
    env["_target"] = a.target
    print(f"-- target: {a.target}  mode: {mode}")
    if a.command == "plan":
        return cmd_plan(env, a.slug, post_id, source, mode)
    if a.command == "publish":
        return cmd_publish(env, a.slug, post_id, source, mode)
    if not a.backup:
        die("restore needs a backup file")
    return cmd_restore(env, a.slug, post_id, a.backup)


if __name__ == "__main__":
    raise SystemExit(main())
