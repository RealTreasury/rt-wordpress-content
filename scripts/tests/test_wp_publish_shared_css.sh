#!/usr/bin/env bash
# Exercises scripts/wp_publish_shared_css.sh against a stub remote (WP_SSH_CMD) in a throwaway
# clone, so the guards are checked without touching realtreasury.com:
#   1. plan is read-only            2. baseline records the live hash
#   3. publish sends the exact bytes, reads back, writes the receipt
#   4. a second publish is a no-op  5. drift is refused, --accept-drift overrides
#   6. unmerged CSS is refused, --allow-unmerged overrides
#   7. a host key that is not pinned is refused before any connection
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
cat > "$T/stub_wp" <<STUB
#!/usr/bin/env bash
case "\$*" in
  *eval-file*) php="\$(cat)"; b64="\$(printf '%s' "\$php" | grep -o 'base64_decode("[^"]*")' | sed 's/base64_decode("//; s/")//')"; printf '%s' "\$b64" | base64 -d > "$STORE"; echo 42 ;;
  *eval*)      cat "$STORE" ;;
  *) echo "unexpected: \$*" >&2; exit 9 ;;
esac
STUB
chmod +x "$T/stub_wp"
printf 'WPCOM_SSH_USER=u\nWPCOM_SSH_HOST=ssh.wp.com\nWPCOM_SSH_KEY_FILE=%s\n' "$T/key" > "$T/env"; : > "$T/key"
run() { ( cd "$W" && WPCOM_SSH_ENV="$T/env" WP_SSH_CMD="$T/stub_wp" WP_SITE_URL="http://127.0.0.1:9/" bash scripts/wp_publish_shared_css.sh "$@" ); }

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
# 7 host key must be pinned (checked before any ssh; the stub is not used because the guard runs first)
: > "$W/scripts/wpcom_known_hosts"
run plan >/dev/null 2>&1 && fail "empty known_hosts was not refused"; pass "missing pinned host key refused"
echo "all wp_publish_shared_css checks passed"
