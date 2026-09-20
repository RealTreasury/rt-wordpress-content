#!/usr/bin/env python3
"""Deploy leg: publish WordPress-native pages when `main` advances.

    scripts/wp_deploy_on_merge.py            # run from cron every 10 minutes
    scripts/wp_deploy_on_merge.py --dry-run  # say what a run would publish

WHY. GitHub Pages deploys the iframed pages the moment `main` moves. The pages
served natively from WordPress (wp/pages.tsv) did not deploy at all: a merged fix
sat in the repo until someone ran scripts/wp_publish_post.py by hand, and PR #909
sat fixed-but-unpublished for weeks that way. This closes that gap for the rows
listed in wp/deploy.tsv, so merging to main is the release for those pages.

WHAT IT DOES. Against a dedicated checkout of this repo (never a working tree a
person edits in), it fetches origin/main, fast-forwards, diffs the range since the
last deployed sha, maps the changed files onto wp/deploy.tsv rows, and runs

    scripts/wp_publish_post.py publish --target production <slug>

for each affected row. That script owns every safety guard (identity check,
backup, pattern-ref drop refusal, read-back, post_status guard) and is a no-op
when the live post already matches. This leg adds nothing to the write path.

WHAT IT RECORDS. A GitHub Deployment (environment "production") with a
success/failure status on the merged sha, so the PR and commit carry a real
"Deployed" signal; a JSON line per run in <state>/deploy.jsonl; the deployed sha
in <state>/deployed.sha.

WHEN IT STOPS. A failed publish writes <state>/deploy-hold.json and the leg does
nothing until a person removes it, so one broken page cannot be retried every
ten minutes. `<state>/hooks-off` is the kill switch. The first run on an empty
state only records the current sha as the baseline and publishes nothing, so
turning this on never bulk-publishes history.
"""
from __future__ import annotations

import argparse
import contextlib
import fcntl
import json
import os
import subprocess
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

DEFAULT_STATE = Path(os.environ.get("WP_DEPLOY_STATE", "/srv/ai-data/wp-deploy"))
DEFAULT_GIT = os.environ.get("WP_DEPLOY_GIT", "/opt/rt-ai/scripts/git_auth.sh")
SECRETS = Path(os.environ.get("WP_DEPLOY_SECRETS", "/opt/rt-ai/secrets/secrets.env"))
REPO_SLUG = os.environ.get("WP_DEPLOY_REPO", "RealTreasury/rt-wordpress-content")
BRANCH = "main"
PUBLISH_TIMEOUT = int(os.environ.get("WP_DEPLOY_PUBLISH_TIMEOUT", "900"))

# A change to any of these touches every published page, not one of them: the
# converter decides what gets written, the manifests decide where.
GLOBAL_PATHS = ("scripts/wp_publish_post.py", "wp/pages.tsv", "wp/deploy.tsv")
GLOBAL_PREFIXES = ("scripts/lib/",)


def log(msg: str) -> None:
    print(f"[{datetime.now(timezone.utc).strftime('%FT%TZ')}] wp_deploy_on_merge: {msg}",
          flush=True)


@contextlib.contextmanager
def deployment_lock(state: Path):
    """Serialize cron invocations before either reads deployed.sha."""
    state.mkdir(parents=True, exist_ok=True)
    with (state / "deploy.lock").open("a", encoding="utf-8") as handle:
        try:
            fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        except BlockingIOError:
            yield False
        else:
            try:
                yield True
            finally:
                fcntl.flock(handle, fcntl.LOCK_UN)


# ---------------------------------------------------------------- manifests --

def parse_pages(text: str) -> dict[str, str]:
    """slug -> source path, from wp/pages.tsv (the columns this leg needs)."""
    pages: dict[str, str] = {}
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        cols = [c.strip() for c in line.split("\t")]
        if len(cols) < 3:
            raise ValueError(f"malformed pages.tsv row: {raw!r}")
        pages[cols[0]] = cols[2]
    return pages


def parse_deploy(text: str) -> list[str]:
    slugs: list[str] = []
    for raw in text.splitlines():
        line = raw.strip()
        if not line or line.startswith("#"):
            continue
        slug = line.split("\t")[0].strip()
        if slug in slugs:
            raise ValueError(f"duplicate deploy.tsv row: {slug}")
        slugs.append(slug)
    return slugs


def affected_slugs(changed: list[str], deploy: list[str],
                   pages: dict[str, str]) -> list[str]:
    """Which deploy.tsv rows a set of changed paths touches, in manifest order.

    Raises ValueError on a deploy row with no pages.tsv entry: that is a config
    error, and the leg must refuse the whole run rather than publish the rest."""
    missing = [s for s in deploy if s not in pages]
    if missing:
        raise ValueError(f"deploy.tsv rows missing from pages.tsv: {missing}")
    changed_set = set(changed)
    if any(p in changed_set for p in GLOBAL_PATHS) or any(
            c.startswith(GLOBAL_PREFIXES) for c in changed):
        return list(deploy)
    return [s for s in deploy if pages[s] in changed_set]


# ---------------------------------------------------------------------- git --

def git(auth_git: str, checkout: Path, *args: str, check: bool = True) -> str:
    cmd = [auth_git, "-C", str(checkout), *args]
    res = subprocess.run(cmd, capture_output=True, text=True, timeout=300)
    if check and res.returncode != 0:
        raise RuntimeError(f"git {' '.join(args)} failed rc={res.returncode}: "
                           f"{res.stderr.strip()[:300]}")
    return res.stdout.strip()


# ------------------------------------------------------------------- github --

def github_token() -> str:
    """RT_PAT from the secrets file; empty when unreadable (the runner user
    cannot read it, and a test never should)."""
    if not os.access(SECRETS, os.R_OK):
        return ""
    try:
        for line in SECRETS.read_text(encoding="utf-8").splitlines():
            if line.startswith("RT_PAT="):
                return line.split("=", 1)[1].strip().strip('"').strip("'")
    except OSError:
        return ""
    return ""


def gh(path: str, payload: dict, token: str) -> dict:
    req = urllib.request.Request(
        f"https://api.github.com{path}", method="POST",
        data=json.dumps(payload).encode("utf-8"),
        headers={"Authorization": f"Bearer {token}",
                 "Accept": "application/vnd.github+json",
                 "Content-Type": "application/json",
                 "User-Agent": "rt-wordpress-content deploy leg"})
    with urllib.request.urlopen(req, timeout=30) as resp:
        return json.loads(resp.read().decode("utf-8") or "{}")


def record_deployment(sha: str, ok: bool, summary: str, token: str) -> dict:
    """Best effort: a Deployment + status, else a commit comment, else nothing.
    Never lets a GitHub failure change what was or was not published."""
    if not token:
        return {"github": "skipped", "reason": "no_token"}
    body = f"wp_deploy_on_merge: {summary}"
    try:
        dep = gh(f"/repos/{REPO_SLUG}/deployments",
                 {"ref": sha, "environment": "production", "task": "wp-publish",
                  "auto_merge": False, "required_contexts": [],
                  "production_environment": True, "description": body[:140]},
                 token)
        dep_id = dep.get("id")
        if dep_id:
            gh(f"/repos/{REPO_SLUG}/deployments/{dep_id}/statuses",
               {"state": "success" if ok else "failure",
                "description": body[:140], "environment": "production"},
               token)
            return {"github": "deployment", "id": dep_id}
    except (urllib.error.URLError, OSError, ValueError) as exc:
        err = str(exc)[:200]
    else:
        err = "no deployment id"
    try:
        gh(f"/repos/{REPO_SLUG}/commits/{sha}/comments", {"body": body}, token)
        return {"github": "commit_comment", "deployment_error": err}
    except (urllib.error.URLError, OSError, ValueError) as exc:
        return {"github": "failed", "error": f"{err}; {str(exc)[:200]}"}


# ---------------------------------------------------------------------- run --

def run(checkout: Path, state: Path, auth_git: str, publisher: list[str],
        dry_run: bool, use_github: bool) -> int:
    with deployment_lock(state) as acquired:
        if not acquired:
            log("another deployment run is active; doing nothing")
            return 0
        return _run(checkout, state, auth_git, publisher, dry_run, use_github)


def _run(checkout: Path, state: Path, auth_git: str, publisher: list[str],
         dry_run: bool, use_github: bool) -> int:
    state.mkdir(parents=True, exist_ok=True)
    hold = state / "deploy-hold.json"
    if (state / "hooks-off").exists():
        log("hooks-off present; doing nothing")
        return 0
    if hold.exists():
        log(f"hold present ({hold}); doing nothing until a person removes it")
        return 0
    if not (checkout / ".git").exists():
        log(f"refused: {checkout} is not a git checkout")
        return 1
    branch = git(auth_git, checkout, "rev-parse", "--abbrev-ref", "HEAD")
    if branch != BRANCH:
        log(f"refused: checkout is on '{branch}', not {BRANCH}")
        return 1
    if git(auth_git, checkout, "status", "--porcelain"):
        log("refused: checkout has local changes; this leg needs a clean tree")
        return 1

    git(auth_git, checkout, "fetch", "-q", "origin", BRANCH)
    new = git(auth_git, checkout, "rev-parse", f"origin/{BRANCH}")
    sha_file = state / "deployed.sha"
    last = sha_file.read_text(encoding="utf-8").strip() if sha_file.exists() else ""
    if not last:
        if not dry_run:
            git(auth_git, checkout, "merge", "-q", "--ff-only", f"origin/{BRANCH}")
            sha_file.write_text(new + "\n", encoding="utf-8")
        log(f"baseline recorded at {new[:7]}; nothing published on a first run")
        return 0
    if new == last:
        return 0
    if not dry_run:
        git(auth_git, checkout, "merge", "-q", "--ff-only", f"origin/{BRANCH}")

    changed = [c for c in git(auth_git, checkout, "diff", "--name-only",
                              last, new).splitlines() if c]
    try:
        # `new` is the tree a real run will fast-forward to.  Read its manifests
        # explicitly so --dry-run reports exactly the same mapping without
        # moving the dedicated checkout first.
        pages = parse_pages(git(auth_git, checkout, "show", f"{new}:wp/pages.tsv"))
        deploy = parse_deploy(git(auth_git, checkout, "show", f"{new}:wp/deploy.tsv"))
        slugs = affected_slugs(changed, deploy, pages)
    except (OSError, ValueError) as exc:
        log(f"refused: {exc}")
        if not dry_run:
            # A bad manifest is a misconfiguration, not a transient error: leave the
            # same operator-visible artifacts a publish failure leaves, so the cron
            # leg stops instead of refusing again every ten minutes into the log.
            record = {"ts": datetime.now(timezone.utc).strftime("%FT%TZ"),
                      "from": last, "to": new, "changed": len(changed), "slugs": [],
                      "ok": False, "results": [], "error": f"refused: {exc}",
                      "github": "skipped"}
            with (state / "deploy.jsonl").open("a", encoding="utf-8") as fh:
                fh.write(json.dumps(record) + "\n")
            hold.write_text(
                json.dumps({**record, "clear": f"rm {hold} to resume"}, indent=2),
                encoding="utf-8")
            log(f"hold written: {hold}")
        return 1

    log(f"{last[:7]}..{new[:7]}: {len(changed)} changed file(s), "
        f"{len(slugs)} page(s) to publish: {slugs or '-'}")
    if dry_run:
        return 0

    results = []
    ok = True
    for slug in slugs:
        cmd = [*publisher, "publish", "--target", "production", slug]
        try:
            res = subprocess.run(cmd, cwd=checkout, capture_output=True, text=True,
                                 timeout=PUBLISH_TIMEOUT, stdin=subprocess.DEVNULL)
            rc, out, err = res.returncode, res.stdout, res.stderr
        except subprocess.TimeoutExpired:
            rc, out, err = 124, "", f"timed out after {PUBLISH_TIMEOUT}s"
        results.append({"slug": slug, "rc": rc, "tail": (out + err)[-600:]})
        log(f"publish {slug}: rc={rc}")
        if rc != 0:
            ok = False
            break  # a real failure needs eyes; do not carry on past it

    summary = (f"published {[r['slug'] for r in results if r['rc'] == 0]} "
               f"for {last[:7]}..{new[:7]}"
               if ok else f"FAILED on {results[-1]['slug']} (rc={results[-1]['rc']}) "
               f"for {last[:7]}..{new[:7]}; hold set")
    github = record_deployment(new, ok, summary, github_token()) if (
        slugs and use_github) else {"github": "skipped"}
    record = {"ts": datetime.now(timezone.utc).strftime("%FT%TZ"), "from": last,
              "to": new, "changed": len(changed), "slugs": slugs, "ok": ok,
              "results": results, **github}
    with (state / "deploy.jsonl").open("a", encoding="utf-8") as fh:
        fh.write(json.dumps(record) + "\n")
    if ok:
        sha_file.write_text(new + "\n", encoding="utf-8")
        return 0
    hold.write_text(json.dumps({**record, "clear": f"rm {hold} to resume"}, indent=2),
                    encoding="utf-8")
    log(f"hold written: {hold}")
    return 2


def main(argv=None) -> int:
    p = argparse.ArgumentParser(description=__doc__,
                                formatter_class=argparse.RawDescriptionHelpFormatter)
    p.add_argument("--state", type=Path, default=DEFAULT_STATE)
    p.add_argument("--checkout", type=Path, default=None,
                   help="dedicated clone on main (default: <state>/rt-wordpress-content)")
    p.add_argument("--git", default=DEFAULT_GIT, help="authenticated git wrapper")
    p.add_argument("--publisher", default=None,
                   help="publisher command (default: <checkout>/scripts/wp_publish_post.py)")
    p.add_argument("--dry-run", action="store_true")
    p.add_argument("--no-github", action="store_true",
                   help="do not record a GitHub Deployment")
    a = p.parse_args(argv)
    checkout = a.checkout or (a.state / "rt-wordpress-content")
    publisher = ([a.publisher] if a.publisher
                 else [sys.executable, str(checkout / "scripts" / "wp_publish_post.py")])
    use_github = not a.no_github and os.environ.get("WP_DEPLOY_GITHUB", "1") == "1"
    try:
        return run(checkout, a.state, a.git, publisher, a.dry_run, use_github)
    except (RuntimeError, subprocess.TimeoutExpired, OSError) as exc:
        log(f"error: {exc}")
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
