#!/usr/bin/env bash
# Exercises scripts/wp_publish_shared_css.sh against a stub remote (WP_SSH_CMD) in a throwaway
# clone, so the guards are checked without touching realtreasury.com:
#   1. plan is read-only            2. baseline records the live hash
#   3. publish sends the exact bytes, reads back, writes the receipt
#   4. a second publish is a no-op  5. drift is refused, --accept-drift overrides
#   6. unmerged CSS is refused, --allow-unmerged overrides
#   7. a host key that is not pinned is refused before any connection
#   8. uncommitted source is refused
#   9. a remote that stored something else fails the read-back AND leaves the receipt
#      alone, so the next run sees drift rather than adopting the bad bytes
#  10. trailing blank lines alone are forgiven, because WordPress trims them
#  11. when origin cannot be reached, publish is refused instead of falling back to a
#      stale tracking ref and overwriting newer live CSS with an older file
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "ok   $*"; }

# --- a repo with an "origin" whose main carries the CSS -------------------------------------
git init -q --bare -b main "$T/origin"

git clone -q "$T/origin" "$T/work"
W="$T/work"
mkdir -p "$W/scripts" "$W/assets/css"
cp "$REPO/scripts/wp_publish_shared_css.sh" "$W/scripts/"
cp "$REPO/scripts/wpcom_known_hosts" "$W/scripts/"
printf 'body { color: red; }\n' > "$W/assets/css/shared.css"
git -C "$W" add -A && git -C "$W" -c user.email=t@t -c user.name=t commit -q -m css && git -C "$W" push -q origin HEAD:main

# --- stub remote: "wp eval" prints the stored CSS, "wp eval-file -" stores the payload --------
STORE="$T/live.css"; : > "$STORE"
# STUB_CORRUPT appends bytes to whatever is sent, which is what the read-back
# compare exists to catch.
cat > "$T/stub_wp" <<STUB
#!/usr/bin/env bash
case "\$*" in
  *eval-file*) php="\$(cat)"; b64="\$(printf '%s' "\$php" | grep -o 'base64_decode("[^"]*")' | sed 's/base64_decode("//; s/")//')"; printf '%s' "\$b64" | base64 -d > "$STORE"; [ -n "\${STUB_CORRUPT:-}" ] && printf '%s' "\${STUB_CORRUPT}" >> "$STORE"; echo 42 ;;
  *eval*)      cat "$STORE" ;;
  *) echo "unexpected: \$*" >&2; exit 9 ;;
esac
STUB
chmod +x "$T/stub_wp"
printf 'WPCOM_SSH_USER=u\nWPCOM_SSH_HOST=ssh.wp.com\nWPCOM_SSH_KEY_FILE=%s\n' "$T/key" > "$T/env"; : > "$T/key"
run() { ( cd "$W" && WPCOM_SSH_ENV="$T/env" WP_SSH_CMD="$T/stub_wp" WP_SITE_URL="http://127.0.0.1:9/" \
    STUB_CORRUPT="${STUB_CORRUPT:-}" bash scripts/wp_publish_shared_css.sh "$@" ); }
commit_and_merge() { git -C "$W" -c user.email=t@t -c user.name=t commit -qam "$1" && git -C "$W" push -q origin HEAD:main; }

# 1 plan is read-only
run plan >/dev/null; [ ! -e "$W/assets/css/shared.css.published.sha256" ] || fail "plan wrote a receipt"; pass "plan is read-only"
# 2 baseline
run baseline >/dev/null; [ -s "$W/assets/css/shared.css.published.sha256" ] || fail "baseline wrote no receipt"; pass "baseline records live hash"
# 3 publish
run publish >/dev/null; cmp -s "$STORE" "$W/assets/css/shared.css" || fail "publish did not deliver exact bytes"
[ "$(cat "$W/assets/css/shared.css.published.sha256")" = "$(sha256sum "$STORE" | cut -d' ' -f1)" ] || fail "receipt != live"; pass "publish delivers exact bytes and writes receipt"
# 4 no-op
run publish | grep -q 'nothing to publish' || fail "second publish was not a no-op"; pass "identical source is a no-op"
# 5 drift
printf 'body { color: blue; }\n' > "$STORE"
printf 'body { color: green; }\n' > "$W/assets/css/shared.css"; git -C "$W" -c user.email=t@t -c user.name=t commit -qam green && git -C "$W" push -q origin HEAD:main
run publish >/dev/null 2>&1 && fail "drift was not refused"; pass "drift refused"
run publish --accept-drift >/dev/null; cmp -s "$STORE" "$W/assets/css/shared.css" || fail "--accept-drift did not publish"; pass "--accept-drift overrides"
# 6 unmerged
printf 'body { color: black; }\n' > "$W/assets/css/shared.css"; git -C "$W" -c user.email=t@t -c user.name=t commit -qam black   # committed, NOT pushed
run publish >/dev/null 2>&1 && fail "unmerged CSS was not refused"; pass "unmerged CSS refused"
run publish --allow-unmerged >/dev/null; cmp -s "$STORE" "$W/assets/css/shared.css" || fail "--allow-unmerged did not publish"; pass "--allow-unmerged overrides"
# 8 uncommitted source: the receipt would point at a commit that does not exist
printf 'body { color: navy; }\n' > "$W/assets/css/shared.css"
run publish --allow-unmerged >/dev/null 2>&1 && fail "uncommitted source was not refused"; pass "uncommitted source refused"
git -C "$W" checkout -q -- assets/css/shared.css

# 9 the remote stored something other than what was sent
printf 'body { color: gold; }\n' > "$W/assets/css/shared.css"; commit_and_merge gold
STUB_CORRUPT='/* injected */' run publish >/dev/null 2>&1 && fail "read-back mismatch was not refused"
cmp -s "$STORE" "$W/assets/css/shared.css" && fail "stub did not actually corrupt the write"
pass "read-back mismatch refused"
# The receipt is deliberately not advanced over a bad write, so the next run treats
# the injected bytes as drift instead of quietly adopting them.
run publish >/dev/null 2>&1 && fail "the run after a bad write did not see drift"; pass "a bad write leaves the receipt alone"

# 10 WordPress trims trailing blank lines; that alone must not fail a publish
printf 'body { color: gold; }\n' > "$STORE"
run baseline >/dev/null
printf 'body { color: silver; }\n' > "$W/assets/css/shared.css"; commit_and_merge silver
STUB_CORRUPT=$'\n\n' run publish > "$T/out" 2>&1 || { cat "$T/out" >&2; fail "trailing blank lines were not forgiven"; }
grep -q 'trailing blank lines' "$T/out" || { cat "$T/out" >&2; fail "the trailing-blank-line path did not run"; }
pass "trailing blank lines forgiven"

# 11 origin moved on, and this checkout cannot reach it. The merge guard used to warn and then
#    compare against whatever refs/remotes/origin/main still held, which is the stale state it
#    exists to catch — so an older file could overwrite newer live CSS. It must fail closed.
#    (`git fetch origin main` does update the tracking ref opportunistically when the remote
#    carries the usual fetch refspec; the hole is the path where the fetch does not happen.)
git clone -q "$T/origin" "$T/other"
printf 'body { color: teal; }\n' > "$T/other/assets/css/shared.css"
git -C "$T/other" -c user.email=t@t -c user.name=t commit -qam teal
git -C "$T/other" push -q origin HEAD:main
mv "$T/origin" "$T/origin.away"                  # W keeps its pre-teal tracking ref and cannot refresh
[ "$(git -C "$W" rev-parse origin/main)" != "$(git -C "$T/other" rev-parse HEAD)" ] || fail "the working clone's tracking ref was not stale"
printf 'body { color: maroon; }\n' > "$STORE"   # live must differ from source or publish short-circuits
run baseline >/dev/null                          # adopt it so the drift guard does not mask the result
run publish > "$T/out11" 2>&1 && fail "publish was not refused when origin/main could not be refreshed"
grep -q 'possibly stale ref' "$T/out11" || { cat "$T/out11" >&2; fail "refusal did not come from the merged-main guard"; }
cmp -s "$STORE" "$W/assets/css/shared.css" && fail "the stale checkout published anyway"
# --allow-unmerged is the documented emergency override and must still work offline
run publish --allow-unmerged >/dev/null; cmp -s "$STORE" "$W/assets/css/shared.css" || fail "--allow-unmerged did not publish offline"
pass "an unrefreshable origin/main fails closed"
mv "$T/origin.away" "$T/origin"

# 7 host key must be pinned (checked before any ssh; the stub is not used because the guard runs first)
: > "$W/scripts/wpcom_known_hosts"
run plan >/dev/null 2>&1 && fail "empty known_hosts was not refused"; pass "missing pinned host key refused"
echo "all wp_publish_shared_css checks passed"
