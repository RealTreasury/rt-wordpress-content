#!/usr/bin/env bash
# Publish assets/css/shared.css into WordPress "Additional CSS" over SSH + WP-CLI.
#
#   scripts/wp_publish_shared_css.sh plan                 read-only: hashes, drift, diff
#   scripts/wp_publish_shared_css.sh baseline             record the live hash as the receipt (first adoption)
#   scripts/wp_publish_shared_css.sh publish [--accept-drift] [--allow-unmerged]
#
# Guards, in order: clean tracked source, live CSS matches the last receipt (drift refusal),
# something actually changed, write through core wp_update_custom_css_post(), read back and
# compare bytes, record the receipt, fetch the home page and confirm the CSS is being served.
#
# Credentials come from /opt/rt-ai/secrets/wpcom-ssh.env (rendered from the rt-ai secrets flow):
#   WPCOM_SSH_USER, WPCOM_SSH_HOST, WPCOM_SSH_KEY_FILE
# This is a whole-site credential. The script uses it for exactly one post and nothing else.
#
# The host key is pinned: scripts/wpcom_known_hosts is the only known_hosts file consulted and
# StrictHostKeyChecking=yes, so a key that is not in that file fails the connection rather than
# being accepted on first use. Update the file deliberately (ssh-keyscan -t ed25519 ssh.wp.com,
# compare the fingerprint against a connection you already trust) and commit it.
#
# Publish also requires the CSS being sent to be what origin/main has. A committed change on a
# feature branch is refused until it is merged; --allow-unmerged overrides for an emergency.
# The guard refreshes refs/remotes/origin/main itself and fails closed if it cannot reach the
# remote, rather than comparing against a tracking ref that may predate a newer CSS change.

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_REL="assets/css/shared.css"
SOURCE="$REPO_ROOT/$SOURCE_REL"
RECEIPT_REL="assets/css/shared.css.published.sha256"
RECEIPT="$REPO_ROOT/$RECEIPT_REL"
ENV_FILE="${WPCOM_SSH_ENV:-/opt/rt-ai/secrets/wpcom-ssh.env}"
SITE_URL="${WP_SITE_URL:-https://realtreasury.com/}"

usage() { sed -n '2,15p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 2; }
die()   { echo "ERROR: $*" >&2; exit 1; }
note()  { echo "-- $*"; }

CMD="${1:-}"; shift || true
ACCEPT_DRIFT=0
ALLOW_UNMERGED=0
for a in "$@"; do
  case "$a" in
    --accept-drift) ACCEPT_DRIFT=1 ;;
    --allow-unmerged) ALLOW_UNMERGED=1 ;;
    *) usage ;;
  esac
done
case "$CMD" in plan|baseline|publish) ;; *) usage ;; esac

[ -f "$SOURCE" ] || die "$SOURCE_REL not found"
[ -f "$ENV_FILE" ] || die "$ENV_FILE not found (render it from rt-ai secrets first)"
# shellcheck disable=SC1090
set -a; . "$ENV_FILE"; set +a
: "${WPCOM_SSH_USER:?}" "${WPCOM_SSH_HOST:?}" "${WPCOM_SSH_KEY_FILE:?}"
[ -r "$WPCOM_SSH_KEY_FILE" ] || die "cannot read key $WPCOM_SSH_KEY_FILE"
KNOWN_HOSTS="${WPCOM_KNOWN_HOSTS:-$REPO_ROOT/scripts/wpcom_known_hosts}"
[ -s "$KNOWN_HOSTS" ] || die "pinned host key file $KNOWN_HOSTS missing or empty"
grep -q "^$WPCOM_SSH_HOST " "$KNOWN_HOSTS" || die "no pinned host key for $WPCOM_SSH_HOST in $KNOWN_HOSTS"

# WP_SSH_CMD lets tests substitute a stub for the remote side.
ssh_wp() {
  if [ -n "${WP_SSH_CMD:-}" ]; then "$WP_SSH_CMD" "$@"; return; fi
  ssh -i "$WPCOM_SSH_KEY_FILE" -o IdentitiesOnly=yes -o BatchMode=yes \
      -o StrictHostKeyChecking=yes -o UserKnownHostsFile="$KNOWN_HOSTS" -o ConnectTimeout=30 \
      "$WPCOM_SSH_USER@$WPCOM_SSH_HOST" "$@"
}

sha() { sha256sum "$1" | cut -d' ' -f1; }

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT
LIVE="$TMP/live.css"

fetch_live() {
  # Prints the stored post_content exactly. Empty output when no custom_css post exists yet.
  ssh_wp 'wp eval --quiet '"'"'$p = wp_get_custom_css_post(); if ($p) { echo $p->post_content; }'"'" > "$LIVE"
}

fetch_live
SRC_SHA="$(sha "$SOURCE")"
LIVE_SHA="$(sha "$LIVE")"
RECEIPT_SHA="$(cat "$RECEIPT" 2>/dev/null | tr -d '[:space:]' || true)"
note "source  $SRC_SHA  $(wc -c < "$SOURCE") bytes  ($SOURCE_REL @ $(git -C "$REPO_ROOT" rev-parse --short HEAD))"
note "live    $LIVE_SHA  $(wc -c < "$LIVE") bytes"
note "receipt ${RECEIPT_SHA:-<none>}"

DRIFT=0
if [ -z "$RECEIPT_SHA" ]; then
  note "no receipt yet: run 'baseline' once to adopt the current live CSS as the starting point"
  DRIFT=1
elif [ "$RECEIPT_SHA" != "$LIVE_SHA" ]; then
  note "DRIFT: live CSS is not what this script last published. Someone changed it in WordPress."
  DRIFT=1
else
  note "live matches receipt: no drift"
fi

if [ "$CMD" = "baseline" ]; then
  printf '%s\n' "$LIVE_SHA" > "$RECEIPT"
  note "wrote $RECEIPT_REL = $LIVE_SHA. Commit it."
  exit 0
fi

if [ "$SRC_SHA" = "$LIVE_SHA" ]; then
  note "source and live are byte-identical: nothing to publish"
  [ "$CMD" = "plan" ] && exit 0
  exit 0
fi

note "diff (live -> source):"
diff -u --label live --label "$SOURCE_REL" "$LIVE" "$SOURCE" || true

[ "$CMD" = "plan" ] && exit 0

# ---------------------------------------------------------------- publish
if ! git -C "$REPO_ROOT" diff --quiet -- "$SOURCE_REL" || ! git -C "$REPO_ROOT" diff --cached --quiet -- "$SOURCE_REL"; then
  die "$SOURCE_REL has uncommitted changes. Commit (and merge) first so the receipt points at a real commit."
fi
# The bytes going live must be the bytes on origin/main, or the receipt points at a commit that
# may never merge and the next publish from main silently reverts it.
if [ "$ALLOW_UNMERGED" = 0 ]; then
  # `git fetch origin main` writes FETCH_HEAD and leaves refs/remotes/origin/main alone, so
  # reading `origin/main:...` after it compares against whatever this checkout last happened to
  # sync — which can predate a newer CSS change on main and let an old file overwrite newer live
  # CSS. Fetch with an explicit refspec so the tracking ref actually moves, and fail closed if
  # the fetch does not happen: a stale comparison is the exact case this guard exists to catch.
  git -C "$REPO_ROOT" fetch -q origin "+refs/heads/main:refs/remotes/origin/main" \
    || die "could not fetch origin/main; refusing to publish against a possibly stale ref (--allow-unmerged overrides for an emergency)"
  MAIN_BLOB="$(git -C "$REPO_ROOT" rev-parse -q --verify "refs/remotes/origin/main:$SOURCE_REL" 2>/dev/null || true)"
  HEAD_BLOB="$(git -C "$REPO_ROOT" hash-object "$SOURCE")"
  [ -n "$MAIN_BLOB" ] || die "cannot read origin/main:$SOURCE_REL"
  [ "$MAIN_BLOB" = "$HEAD_BLOB" ] || die "$SOURCE_REL differs from origin/main. Merge first; --allow-unmerged overrides for an emergency."
fi
if [ "$DRIFT" = 1 ] && [ "$ACCEPT_DRIFT" = 0 ]; then
  die "refusing to overwrite drifted live CSS. Read the diff above; rerun with --accept-drift to overwrite it anyway (the prior CSS stays in WordPress revisions)."
fi

# Send the exact bytes inside a PHP file over stdin. KSES is lifted for this one call because
# WP-CLI runs with no user, and core would otherwise filter the CSS as untrusted HTML.
PHP="$TMP/publish.php"
{
  echo '<?php'
  echo 'kses_remove_filters();'
  printf '$css = base64_decode("%s");\n' "$(base64 -w0 < "$SOURCE")"
  echo 'if (hash("sha256", $css) !== "'"$SRC_SHA"'") { fwrite(STDERR, "payload hash mismatch\n"); exit(1); }'
  echo '$r = wp_update_custom_css_post($css);'
  echo 'if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\n"); exit(1); }'
  echo 'echo $r->ID, "\n";'
} > "$PHP"

note "publishing $(wc -c < "$SOURCE") bytes"
POST_ID="$(ssh_wp 'wp eval-file --quiet -' < "$PHP" | tr -d '[:space:]')"
[ -n "$POST_ID" ] || die "write returned no post ID"
note "wrote custom_css post $POST_ID"

fetch_live
NEW_LIVE_SHA="$(sha "$LIVE")"
if [ "$NEW_LIVE_SHA" != "$SRC_SHA" ]; then
  if cmp -s <(sed -e :a -e '/^\n*$/{$d;N;ba' -e '}' "$LIVE") <(sed -e :a -e '/^\n*$/{$d;N;ba' -e '}' "$SOURCE"); then
    note "read-back differs only in trailing blank lines (WordPress trims); accepted"
  else
    echo "READ-BACK MISMATCH: live $NEW_LIVE_SHA != source $SRC_SHA" >&2
    diff -u --label "$SOURCE_REL" --label live "$SOURCE" "$LIVE" >&2 || true
    die "live CSS is not what was sent. Restore via Appearance -> Customize -> Additional CSS revisions, or rerun."
  fi
fi
printf '%s\n' "$NEW_LIVE_SHA" > "$RECEIPT"
note "read-back OK; receipt $RECEIPT_REL = $NEW_LIVE_SHA (commit it)"

# ---------------------------------------------------------------- verify delivery
PAGE="$TMP/page.html"
if curl -fsSL --max-time 30 "${SITE_URL}?rtcb=$(date +%s)" -o "$PAGE"; then
  python3 - "$PAGE" "$LIVE" <<'PY' || true
import re, sys
page = open(sys.argv[1], encoding="utf-8", errors="replace").read()
live = open(sys.argv[2], encoding="utf-8", errors="replace").read()
m = re.search(r'<style[^>]*id="wp-custom-css"[^>]*>(.*?)</style>', page, re.S)
if not m:
    print("-- verify: no #wp-custom-css style block on the home page (cache lag, or CSS empty)"); sys.exit(0)
served = m.group(1)
# core wraps the stored bytes in one leading and one trailing newline
if served.startswith("\n"): served = served[1:]
if served.endswith("\n"): served = served[:-1]
if served.rstrip() == live.rstrip():
    print("-- verify: home page serves the published CSS")
else:
    print("-- verify: home page style block differs from what was stored (edge cache lag is the usual cause; recheck in a minute)")
PY
else
  note "verify: could not fetch $SITE_URL"
fi
note "done"
