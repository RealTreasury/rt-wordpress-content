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
"""
from __future__ import annotations

import argparse
import base64
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
        f"{env['WPCOM_SSH_USER']}@{env['WPCOM_SSH_HOST']}", remote_cmd,
    ]
    r = subprocess.run(cmd, input=stdin, capture_output=True, timeout=180)
    if r.returncode != 0:
        die(f"ssh failed ({r.returncode}): {r.stderr.decode('utf-8', 'replace')[:500]}")
    return r.stdout.decode("utf-8", "replace")


def manifest() -> dict[str, tuple[int, Path]]:
    if not MANIFEST.exists():
        die(f"{MANIFEST} not found")
    out = {}
    for n, line in enumerate(MANIFEST.read_text(encoding="utf-8").splitlines(), 1):
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        parts = line.split("\t")
        if len(parts) != 3:
            die(f"{MANIFEST}:{n}: expected 'slug<TAB>post_id<TAB>source'")
        slug, post_id, src = parts
        out[slug] = (int(post_id), ROOT / src)
    return out


def fetch_content(env, post_id: int) -> str:
    php = f'echo get_post({post_id}) ? get_post({post_id})->post_content : "";'
    return ssh(env, f"wp --quiet eval {shell_quote(php)}")


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


def words(html: str) -> int:
    t = re.sub(r"<(script|style)[\s\S]*?</\1>", " ", html, flags=re.I)
    return len(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", t)).split())


def build(slug: str, source: Path) -> str:
    if not source.exists():
        die(f"source page {source} not found")
    return page_to_block.render(source.read_text(encoding="utf-8"), slug)


def cmd_plan(env, slug, post_id, source) -> int:
    current = fetch_content(env, post_id)
    block = build(slug, source)
    new = splice(current, block, slug)
    print(f"-- post {post_id} ({slug})")
    print(f"-- live now : {len(current):>7} bytes, {words(current):>5} words, "
          f"h1={'yes' if re.search(r'<h1', current, re.I) else 'NO'}, "
          f"iframe={'yes' if 'github.io' in current else 'no'}")
    print(f"-- would be : {len(new):>7} bytes, {words(new):>5} words, "
          f"h1={'yes' if re.search(r'<h1', new, re.I) else 'NO'}, "
          f"iframe={'yes' if 'github.io' in new else 'no'}")
    preserved = [r for r in re.findall(r'wp:block\s+\{"ref":(\d+)\}', current)]
    kept = [r for r in re.findall(r'wp:block\s+\{"ref":(\d+)\}', new)]
    print(f"-- pattern refs: before {preserved} -> after {kept}"
          f"{'  OK' if preserved == kept else '  *** PATTERNS CHANGED ***'}")
    if preserved != kept:
        die("the splice would drop a pattern reference; refusing to call this a safe plan")
    return 0


def cmd_publish(env, slug, post_id, source) -> int:
    current = fetch_content(env, post_id)
    block = build(slug, source)
    new = splice(current, block, slug)
    if new == current:
        print("-- already published: nothing to do")
        return 0
    before_refs = re.findall(r'wp:block\s+\{"ref":(\d+)\}', current)
    after_refs = re.findall(r'wp:block\s+\{"ref":(\d+)\}', new)
    if before_refs != after_refs:
        die(f"refusing: pattern refs would change {before_refs} -> {after_refs}")

    BACKUPS.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now(timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    backup = BACKUPS / f"{post_id}-{slug}-{stamp}.html"
    backup.write_text(current, encoding="utf-8")
    print(f"-- backed up {len(current)} bytes to {backup}")

    payload = base64.b64encode(new.encode("utf-8")).decode("ascii")
    digest = hashlib.sha256(new.encode("utf-8")).hexdigest()
    php = "\n".join([
        "<?php",
        "kses_remove_filters();",
        f'$c = base64_decode("{payload}");',
        f'if (hash("sha256", $c) !== "{digest}") {{ fwrite(STDERR, "payload hash mismatch\\n"); exit(1); }}',
        f'$r = wp_update_post(["ID" => {post_id}, "post_content" => $c], true);',
        'if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\\n"); exit(1); }',
        'echo $r;',
    ])
    out = ssh(env, "wp --quiet eval-file -", stdin=php.encode("utf-8")).strip()
    if not out.isdigit():
        die(f"write returned no post ID: {out!r}")
    print(f"-- wrote post {out}")

    back = fetch_content(env, post_id)
    if back.rstrip("\n") != new.rstrip("\n"):
        print(f"READ-BACK MISMATCH: live {len(back)} bytes != sent {len(new)}", file=sys.stderr)
        die(f"restore with: {sys.argv[0]} restore {slug} {backup}")
    print(f"-- read-back OK: {len(back)} bytes, {words(back)} words")
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
        f'$r = wp_update_post(["ID" => {post_id}, "post_content" => $c], true);',
        'if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\\n"); exit(1); }',
        'echo $r;',
    ])
    ssh(env, "wp --quiet eval-file -", stdin=php.encode("utf-8"))
    back = fetch_content(env, post_id)
    if back.rstrip("\n") != content.rstrip("\n"):
        die("restore read-back did not match the backup")
    print(f"-- restored post {post_id} from {backup_path} ({len(content)} bytes)")
    return 0


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("command", choices=["plan", "publish", "restore"])
    p.add_argument("slug")
    p.add_argument("backup", nargs="?")
    a = p.parse_args(argv)
    pages = manifest()
    if a.slug not in pages:
        die(f"'{a.slug}' is not in {MANIFEST}")
    post_id, source = pages[a.slug]
    env = load_env()
    if a.command == "plan":
        return cmd_plan(env, a.slug, post_id, source)
    if a.command == "publish":
        return cmd_publish(env, a.slug, post_id, source)
    if not a.backup:
        die("restore needs a backup file")
    return cmd_restore(env, a.slug, post_id, a.backup)


if __name__ == "__main__":
    raise SystemExit(main())
