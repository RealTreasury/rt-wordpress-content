#!/usr/bin/env bash
# Write a WordPress-native page's body from its repo source over SSH + WP-CLI.
#
#   scripts/wp_publish_page.sh list                 the pages this script knows about
#   scripts/wp_publish_page.sh plan [<name>]        read-only: fetch each page and diff it
#   scripts/wp_publish_page.sh write <name>         write the body, then read it back and diff
#
# It writes `post_content` and nothing else. It never changes `post_status`, so it cannot
# publish a draft or unpublish a page — publishing stays a deliberate act in WP Admin.
#
# ## Why this exists: WordPress eats backslashes
#
# `wp_insert_post()` and `wp_update_post()` expect SLASHED input and run `wp_unslash()` over
# whatever they are given. Content passed raw loses one level of backslashes, silently.
#
# This is not theoretical. On September 16, 2026 a scripted write put the guide download page
# into WordPress page 4587 with every backslash stripped out of its one backslash-bearing line:
#
#     repo:  if (f.type === 'email' && v2 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v2)) { ... }
#     4587:  if (f.type === 'email' && v2 && !/^[^s@]+@[^s@]+.[^s@]+$/.test(v2))    { ... }
#
# `[^\s@]` ("not whitespace, not @") became `[^s@]` ("not the letter s, not @"), so that copy
# of the form rejects any address whose local part contains an "s". On a lead-generation page
# that is a large, invisible share of submissions, and the visitor just sees their own address
# marked invalid. Nothing errors, nothing logs, and it still works for most testers.
#
# So: `wp_slash()` on the way in, and a read-back diff on the way out. Both, every time.
#
# ## What the WordPress copy looks like
#
# The repo file is the source of record. The copy stored in WordPress is derived from it:
#
#   <!-- wp:html -->
#   <!-- Source of record: <path> in RealTreasury/rt-wordpress-content. ... -->
#   <the repo file, with its leading header comment removed>
#   <!-- /wp:html -->
#
# The block wrapper is what makes Gutenberg keep the markup as one Custom HTML block instead of
# parsing it into blocks. The header comment is repo-facing (it talks about paths and PR
# history) and is replaced by the one-line note so that someone who opens the page in WordPress
# still learns where to edit it.
#
# That derivation is deterministic, which is the point: `plan` can compare byte for byte rather
# than squinting past "expected" differences, and a corrupted line shows up as a diff instead of
# being written off as paste noise.
#
# ## Credentials
#
# /opt/rt-ai/secrets/wpcom-ssh.env (rendered from the rt-ai secrets flow):
#   WPCOM_SSH_USER, WPCOM_SSH_HOST, WPCOM_SSH_KEY_FILE
# This is a whole-site credential. This script uses it for the listed post IDs and nothing else.
#
# The host key is pinned to scripts/wpcom_known_hosts, the global known_hosts is pointed at
# /dev/null, and StrictHostKeyChecking=yes — a key that is not in that file fails the connection
# rather than being trusted on first use.
#
# WP-CLI global flags go BEFORE the subcommand: realtreasury.com runs WP-CLI 2.12.0, which
# rejects `wp eval --quiet ...` with "Error: Parameter errors: unknown --quiet parameter".

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${WPCOM_SSH_ENV:-/opt/rt-ai/secrets/wpcom-ssh.env}"

# name | post ID | repo source
#
# 4587 is a staging draft, not a destination. Its slug (…-guide-download) 404s and should never
# be published: the download form's home is 4202. It is listed so `plan` can see it, because it
# is the file someone would otherwise copy into 4202 by hand — and it is the corrupted one.
PAGES="
waitlist-confirmed|4809|treasury-tech-selection/waitlist/confirmed/wordpress-page.html
guide-thank-you|4585|treasury-tech-selection/guidebook/thank-you/wordpress-page.html
guide-download-staging|4587|treasury-tech-selection/guidebook/wordpress-page.html
guide-download|4202|treasury-tech-selection/guidebook/wordpress-page.html
"

usage() { sed -n '2,8p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 2; }
die()   { echo "ERROR: $*" >&2; exit 1; }
note()  { echo "-- $*"; }

CMD="${1:-}"; NAME="${2:-}"
case "$CMD" in list|plan|write) ;; *) usage ;; esac
[ "$CMD" = "write" ] && [ -z "$NAME" ] && usage

lookup() { printf '%s\n' "$PAGES" | awk -F'|' -v n="$1" 'NF==3 && $1==n {print; found=1} END{exit !found}'; }

if [ "$CMD" = "list" ]; then
  printf '%-24s %-6s %s\n' name id source
  printf '%s\n' "$PAGES" | awk -F'|' 'NF==3 {printf "%-24s %-6s %s\n", $1, $2, $3}'
  exit 0
fi

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
  # GlobalKnownHostsFile is silenced as well as UserKnownHostsFile set: OpenSSH consults
  # /etc/ssh/ssh_known_hosts(2) in addition to the user file, so without this the pinned file
  # is not actually the only key that can satisfy the connection.
  ssh -i "$WPCOM_SSH_KEY_FILE" -o IdentitiesOnly=yes -o BatchMode=yes \
      -o StrictHostKeyChecking=yes -o UserKnownHostsFile="$KNOWN_HOSTS" \
      -o GlobalKnownHostsFile=/dev/null -o ConnectTimeout=30 \
      "$WPCOM_SSH_USER@$WPCOM_SSH_HOST" "$@"
}

# `wp eval-file -` reads the script from STDIN. Prove the transport with a throwaway script
# before trusting it with a write: an SSH jail that does not forward stdin would otherwise fail
# in the middle of one with no useful message. The probe uses the same invocation as the write.
STDIN_PROBE_TOKEN='RT_STDIN_OK'
probe_stdin() {
  local out
  out="$(printf '<?php echo "%s";' "$STDIN_PROBE_TOKEN" \
         | ssh_wp 'wp --quiet eval-file -' 2>/dev/null \
         | tr -d '[:space:]' || true)"
  [ "$out" = "$STDIN_PROBE_TOKEN" ]
}

TMP="$(mktemp -d)"; trap 'rm -rf "$TMP"' EXIT

# The repo file minus its leading header comment, wrapped as one Custom HTML block with a
# one-line note pointing back at the source. Deterministic, so the compare can be exact.
render() {
  python3 - "$1" "$2" <<'PY'
import re, sys
path, rel = sys.argv[1], sys.argv[2]
src = open(path, encoding="utf-8").read()
# Only a header comment counts: one that starts the file. A comment further in is content.
src = re.sub(r'\A\s*<!--.*?-->\s*', '', src, count=1, flags=re.S)
note = ("<!-- Source of record: %s in RealTreasury/rt-wordpress-content. Edit there, then "
        "publish with scripts/wp_publish_page.sh. Native page, not an iframe embed: same-origin "
        "API calls, no height negotiation, and the content is crawlable. -->" % rel)
sys.stdout.write("<!-- wp:html -->\n%s\n%s\n<!-- /wp:html -->" % (note, src.rstrip("\n")))
PY
}

fetch_page() { ssh_wp "wp --quiet post get $1 --field=post_content"; }   # prints to stdout

# WordPress stores what it is given, but the transports on both sides are line-oriented enough
# that a trailing-newline difference is noise rather than signal. Nothing else is normalised:
# the whole point is that a single changed character shows up.
norm() { awk '{ line[NR]=$0 } END { n=NR; while (n>0 && line[n] ~ /^[[:space:]]*$/) n--; for (i=1;i<=n;i++) printf "%s\n", line[i] }' "$1"; }

check_one() {  # name id rel  -> 0 identical, 1 differs
  local name="$1" id="$2" rel="$3"
  local src="$REPO_ROOT/$rel"
  # A source that only exists on a feature branch is not an error for `plan` — say so and move
  # on, so a rollup plan still reports on every other page.
  if [ ! -f "$src" ]; then
    note "$name (page $id): SKIPPED, $rel is not in this checkout"
    return 0
  fi
  local want="$TMP/$name.want" live="$TMP/$name.live"
  render "$src" "$rel" > "$want"
  fetch_page "$id" > "$live"
  local status
  status="$(ssh_wp "wp --quiet post get $id --field=post_status" | tr -d '[:space:]')"
  if cmp -s <(norm "$want") <(norm "$live"); then
    note "$name (page $id, $status): matches $rel"
    return 0
  fi
  note "$name (page $id, $status): DIFFERS from $rel"
  diff -u --label "live/$id" --label "$rel" <(norm "$live") <(norm "$want") || true
  return 1
}

if [ "$CMD" = "plan" ]; then
  rc=0
  if [ -n "$NAME" ]; then
    row="$(lookup "$NAME")" || die "unknown page '$NAME' (try: list)"
    IFS='|' read -r n i r <<<"$row"; check_one "$n" "$i" "$r" || rc=1
  else
    # Read the table on fd 3, not stdin. ssh reads stdin, and the write path needs it — so if
    # the loop fed itself from stdin the first page checked would swallow the rest of the table
    # and the run would silently stop after one page.
    while IFS='|' read -r n i r <&3; do
      [ -n "$n" ] || continue
      check_one "$n" "$i" "$r" || rc=1
    done 3<<<"$(printf '%s\n' "$PAGES" | awk -F'|' 'NF==3')"
  fi
  if probe_stdin; then note "remote reads the write script from stdin (probe OK)"
  else note "WARNING: the remote did not return the STDIN probe token. 'write' will refuse."; fi
  exit $rc
fi

# ------------------------------------------------------------------------------------- write
row="$(lookup "$NAME")" || die "unknown page '$NAME' (try: list)"
IFS='|' read -r NAME POST_ID REL <<<"$row"
SRC="$REPO_ROOT/$REL"
[ -f "$SRC" ] || die "$REL not found"

# The bytes going into WordPress must be the bytes on origin/main, or the page carries a version
# of the file that may never merge and the next write from main silently reverts it.
if ! git -C "$REPO_ROOT" diff --quiet -- "$REL" || ! git -C "$REPO_ROOT" diff --cached --quiet -- "$REL"; then
  die "$REL has uncommitted changes. Commit and merge first."
fi
git -C "$REPO_ROOT" fetch -q origin "+refs/heads/main:refs/remotes/origin/main" \
  || die "could not fetch origin/main; refusing to write against a possibly stale ref"
MAIN_BLOB="$(git -C "$REPO_ROOT" rev-parse -q --verify "refs/remotes/origin/main:$REL" 2>/dev/null || true)"
[ -n "$MAIN_BLOB" ] || die "cannot read origin/main:$REL"
[ "$MAIN_BLOB" = "$(git -C "$REPO_ROOT" hash-object "$SRC")" ] \
  || die "$REL differs from origin/main. Merge first."

WANT="$TMP/want.html"
render "$SRC" "$REL" > "$WANT"
WANT_SHA="$(sha256sum "$WANT" | cut -d' ' -f1)"

LIVE="$TMP/live.html"
fetch_page "$POST_ID" > "$LIVE"
STATUS="$(ssh_wp "wp --quiet post get $POST_ID --field=post_status" | tr -d '[:space:]')"
note "$NAME -> page $POST_ID ($STATUS), $(wc -c < "$WANT") bytes from $REL"

if cmp -s <(norm "$WANT") <(norm "$LIVE"); then
  note "page already matches the source: nothing to write"
  exit 0
fi
note "diff (live -> source):"
diff -u --label "live/$POST_ID" --label "$REL" <(norm "$LIVE") <(norm "$WANT") || true

probe_stdin || die "the remote did not return the STDIN probe token: 'wp eval-file -' is not reading the script this end sends. Refusing to attempt the write."

# base64 so no shell, SSH or PHP quoting layer can touch the bytes, and a hash check on the far
# side so a truncated transfer cannot be written. wp_slash() is the whole reason this file
# exists — see the header. KSES is lifted for this one process because WP-CLI runs with no user
# and core would otherwise strip the <script> and <style> blocks as untrusted HTML; it is not
# lifted globally, and the process ends with this script.
PHP="$TMP/write.php"
{
  echo '<?php'
  echo 'kses_remove_filters();'
  printf '$c = base64_decode("%s");\n' "$(base64 -w0 < "$WANT")"
  echo 'if (hash("sha256", $c) !== "'"$WANT_SHA"'") { fwrite(STDERR, "payload hash mismatch\n"); exit(1); }'
  echo '$r = wp_update_post(array("ID" => '"$POST_ID"', "post_content" => wp_slash($c)), true);'
  echo 'if (is_wp_error($r)) { fwrite(STDERR, $r->get_error_message() . "\n"); exit(1); }'
  echo 'echo $r, "\n";'
} > "$PHP"

WROTE="$(ssh_wp 'wp --quiet eval-file -' < "$PHP" | tr -d '[:space:]')"
[ "$WROTE" = "$POST_ID" ] || die "write returned '$WROTE', expected $POST_ID"
note "wrote page $POST_ID"

fetch_page "$POST_ID" > "$LIVE"
if ! cmp -s <(norm "$WANT") <(norm "$LIVE"); then
  echo "READ-BACK MISMATCH on page $POST_ID" >&2
  diff -u --label "$REL" --label "live/$POST_ID" <(norm "$WANT") <(norm "$LIVE") >&2 || true
  die "WordPress did not store what was sent. The previous body is in the page's revisions."
fi
NEW_STATUS="$(ssh_wp "wp --quiet post get $POST_ID --field=post_status" | tr -d '[:space:]')"
[ "$NEW_STATUS" = "$STATUS" ] || die "post_status changed $STATUS -> $NEW_STATUS; it should not have"
note "read-back OK; still $NEW_STATUS (this script never publishes)"
