#!/usr/bin/env bash
# Exercises scripts/wp_publish_page.sh against a stub remote (WP_SSH_CMD) in a throwaway clone,
# so the guards are checked without touching realtreasury.com:
#
#   1. list prints the page table without needing credentials or a network
#   2. plan is read-only, and reports a page that differs
#   3. plan reports a page that matches
#   4. plan visits EVERY page, not just the first. ssh reads stdin, so a loop that fed itself
#      from stdin had its table swallowed by the first page's ssh call and stopped there —
#      silently, with exit 0 if that first page happened to match.
#   5. write sends the rendered body, reads it back, and leaves post_status alone
#   6. a second write is a no-op
#   7. THE BACKSLASH REGRESSION. wp_update_post() runs wp_unslash() over its input, so content
#      passed raw loses one level of backslashes — that is how WordPress page 4587 ended up
#      with `[^s@]` where the repo has `[^\s@]`, silently rejecting any address whose local
#      part contains an "s". The stub simulates wp_unslash; a script that stopped calling
#      wp_slash() must fail here.
#   8. the read-back catches a remote that stored something else, and says so
#   9. a post_status that moved during the write is refused — this script must never publish
#  10. uncommitted source is refused
#  11. source that does not match origin/main is refused
#  12. a host key that is not pinned is refused before any connection
#  13. write refuses outright if the remote does not read the script from stdin
#  14. WP-CLI global flags are sent BEFORE the subcommand. realtreasury.com runs WP-CLI 2.12.0,
#      which rejects `wp post get --quiet ...`; the stub rejects it the same way.
#  15. an unknown page name is refused
set -euo pipefail
HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO="$(cd "$HERE/../.." && pwd)"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
fail() { echo "FAIL: $*" >&2; exit 1; }
pass() { echo "ok   $*"; }

SRC_REL="treasury-tech-selection/guidebook/thank-you/wordpress-page.html"
OTHER_REL="treasury-tech-selection/waitlist/confirmed/wordpress-page.html"
GUIDE_REL="treasury-tech-selection/guidebook/wordpress-page.html"

# --- a repo whose origin/main carries the sources -------------------------------------------
git init -q --bare -b main "$T/origin"
git clone -q "$T/origin" "$T/work"
W="$T/work"
mkdir -p "$W/scripts/tests" "$W/$(dirname "$SRC_REL")" "$W/$(dirname "$OTHER_REL")" "$W/$(dirname "$GUIDE_REL")"
cp "$REPO/scripts/wp_publish_page.sh" "$W/scripts/"
cp "$REPO/scripts/wpcom_known_hosts" "$W/scripts/"

# One backslash-bearing line, which is the whole point of case 7.
cat > "$W/$SRC_REL" <<'EOF'
<!--
  Header comment. Repo-facing; the script strips it.
-->
<div class="rt-gbty">
  <script>if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) { bad(); }</script>
</div>
EOF
printf '<div class="rt-wlty">confirmed</div>\n' > "$W/$OTHER_REL"
# 4587 and 4202 share this source; it is the one that carries the email check in production.
cp "$W/$SRC_REL" "$W/$GUIDE_REL"
git -C "$W" add -A
git -C "$W" -c user.email=t@t -c user.name=t commit -q -m sources
git -C "$W" push -q origin HEAD:main

SCRIPT="$W/scripts/wp_publish_page.sh"

# --- credentials the script insists on, all pointed at throwaways ----------------------------
KEY="$T/key"; : > "$KEY"; chmod 600 "$KEY"
HOST="$(awk 'NF{split($1,a,","); print a[1]; exit}' "$REPO/scripts/wpcom_known_hosts")"
[ -n "$HOST" ] || fail "no host in scripts/wpcom_known_hosts to borrow for the stub"
cat > "$T/env" <<EOF
WPCOM_SSH_USER=stub
WPCOM_SSH_HOST=$HOST
WPCOM_SSH_KEY_FILE=$KEY
EOF

# --- stub remote -----------------------------------------------------------------------------
# Stores one body per post id under $STUB_STORE, and one status per post id under $STUB_STATUS.
# STUB_UNSLASH=1  simulates wp_unslash(): strips one level of backslashes from what is written
#                 UNLESS the PHP asks for wp_slash().
# STUB_CORRUPT=1  appends a byte, which the read-back exists to catch.
# STUB_FLIP=1     flips post_status during the write.
# STUB_NOSTDIN=1  ignores stdin, as an SSH jail that does not forward it would.
STUB_STORE="$T/store"; mkdir -p "$STUB_STORE"
STUB_STATUS="$T/status"; mkdir -p "$STUB_STATUS"
printf 'draft' > "$STUB_STATUS/4585"
printf 'draft' > "$STUB_STATUS/4809"
printf 'draft' > "$STUB_STATUS/4587"
printf 'publish' > "$STUB_STATUS/4202"

cat > "$T/stub" <<'STUB'
#!/usr/bin/env bash
set -euo pipefail
CMD="$*"
# WP-CLI 2.12.0 rejects a global flag placed after the subcommand.
case "$CMD" in
  *"post get"*"--quiet"*|*"eval-file"*"- --quiet"*)
    echo "Error: Parameter errors: unknown --quiet parameter" >&2; exit 1 ;;
esac
case "$CMD" in
  *"post get "*"--field=post_content"*)
    id="$(sed -E 's/.*post get ([0-9]+).*/\1/' <<<"$CMD")"
    cat "$STUB_STORE/$id" 2>/dev/null || true; exit 0 ;;
  *"post get "*"--field=post_status"*)
    id="$(sed -E 's/.*post get ([0-9]+).*/\1/' <<<"$CMD")"
    cat "$STUB_STATUS/$id" 2>/dev/null || true; exit 0 ;;
  *"eval-file -"*)
    php="$(cat)"
    if [ "${STUB_NOSTDIN:-0}" = 1 ]; then echo ""; exit 0; fi
    # the stdin probe
    if grep -q 'RT_STDIN_OK' <<<"$php"; then printf 'RT_STDIN_OK'; exit 0; fi
    id="$(grep -oE '"ID" => [0-9]+' <<<"$php" | grep -oE '[0-9]+')"
    b64="$(sed -nE 's/.*base64_decode\("([^"]*)"\).*/\1/p' <<<"$php")"
    body="$(base64 -d <<<"$b64")"
    if [ "${STUB_UNSLASH:-0}" = 1 ] && ! grep -q 'wp_slash(' <<<"$php"; then
      body="$(sed 's/\\//g' <<<"$body")"
    fi
    printf '%s\n' "$body" > "$STUB_STORE/$id"
    [ "${STUB_CORRUPT:-0}" = 1 ] && printf 'X' >> "$STUB_STORE/$id"
    [ "${STUB_FLIP:-0}" = 1 ] && printf 'publish' > "$STUB_STATUS/$id"
    echo "$id"; exit 0 ;;
esac
echo "stub: unhandled: $CMD" >&2; exit 1
STUB
chmod +x "$T/stub"
export STUB_STORE STUB_STATUS
run() { WPCOM_SSH_ENV="$T/env" WP_SSH_CMD="$T/stub" "$SCRIPT" "$@"; }

# --- 1. list ---------------------------------------------------------------------------------
out="$(WPCOM_SSH_ENV=/nonexistent "$SCRIPT" list)"
grep -q "guide-thank-you" <<<"$out" || fail "1: list did not name guide-thank-you"
grep -q "4202" <<<"$out" || fail "1: list did not name page 4202"
pass "1 list works without credentials"

# --- 2. plan is read-only and reports a difference -------------------------------------------
printf 'something else\n' > "$STUB_STORE/4585"
before="$(cat "$STUB_STORE/4585")"
if run plan guide-thank-you > "$T/plan.out" 2>&1; then fail "2: plan exited 0 on a differing page"; fi
grep -q "DIFFERS" "$T/plan.out" || fail "2: plan did not report DIFFERS"
[ "$(cat "$STUB_STORE/4585")" = "$before" ] || fail "2: plan wrote to the remote"
pass "2 plan is read-only and reports a difference"

# --- 3. plan reports a match -----------------------------------------------------------------
run write guide-thank-you >/dev/null
run plan guide-thank-you > "$T/plan2.out" 2>&1 || fail "3: plan exited non-zero on a matching page"
grep -q "matches" "$T/plan2.out" || fail "3: plan did not report a match"
pass "3 plan reports a match"

# --- 4. plan visits every page ----------------------------------------------------------------
run plan > "$T/planall.out" 2>&1 || true
for name in waitlist-confirmed guide-thank-you guide-download-staging guide-download; do
  grep -q "^-- $name " "$T/planall.out" || fail "4: plan never reached $name (stdin swallowed?)"
done
pass "4 plan visits every page"

# --- 5. write leaves post_status alone ---------------------------------------------------------
printf 'stale\n' > "$STUB_STORE/4202"
run write guide-download > "$T/w.out" 2>&1 || fail "5: write failed: $(cat "$T/w.out")"
grep -q "read-back OK" "$T/w.out" || fail "5: no read-back confirmation"
[ "$(cat "$STUB_STATUS/4202")" = "publish" ] || fail "5: post_status changed"
grep -q '<!-- wp:html -->' "$STUB_STORE/4202" || fail "5: stored body is not wrapped as a block"
grep -q 'Header comment' "$STUB_STORE/4202" && fail "5: repo header comment was not stripped"
pass "5 write stores the rendered body and leaves post_status alone"

# --- 6. a second write is a no-op --------------------------------------------------------------
run write guide-download > "$T/w2.out" 2>&1 || fail "6: second write failed"
grep -q "nothing to write" "$T/w2.out" || fail "6: second write was not a no-op"
pass "6 a second write is a no-op"

# --- 7. THE BACKSLASH REGRESSION ---------------------------------------------------------------
# With wp_unslash() simulated, a write that did not call wp_slash() loses every backslash and
# must be caught by the read-back rather than stored quietly.
printf 'stale\n' > "$STUB_STORE/4585"
STUB_UNSLASH=1 run write guide-thank-you > "$T/w3.out" 2>&1 || fail "7: write failed under wp_unslash: $(cat "$T/w3.out")"
grep -q 'wp_slash' <<<"$(cat "$SCRIPT")" || fail "7: the script no longer calls wp_slash()"
grep -qF '[^\s@]' "$STUB_STORE/4585" || fail "7: backslashes were lost on the way into the remote"
pass "7 wp_slash() survives the round trip with wp_unslash() simulated"

# and the same write WITHOUT wp_slash must fail, or case 7 proves nothing
sed 's/wp_slash(\$c)/\$c/' "$SCRIPT" > "$T/noslash.sh"; chmod +x "$T/noslash.sh"
cp "$T/noslash.sh" "$W/scripts/noslash.sh"
printf 'stale\n' > "$STUB_STORE/4585"
if STUB_UNSLASH=1 WPCOM_SSH_ENV="$T/env" WP_SSH_CMD="$T/stub" "$W/scripts/noslash.sh" write guide-thank-you > "$T/w4.out" 2>&1; then
  fail "7b: a write without wp_slash() was accepted"
fi
grep -q "READ-BACK MISMATCH" "$T/w4.out" || fail "7b: the read-back did not name the mismatch"
rm -f "$W/scripts/noslash.sh"
pass "7b a write without wp_slash() fails the read-back"

# --- 8. read-back catches a corrupting remote ---------------------------------------------------
printf 'stale\n' > "$STUB_STORE/4585"
if STUB_CORRUPT=1 run write guide-thank-you > "$T/w5.out" 2>&1; then fail "8: corrupted write accepted"; fi
grep -q "READ-BACK MISMATCH" "$T/w5.out" || fail "8: no read-back mismatch reported"
pass "8 the read-back catches a corrupting remote"

# --- 9. a post_status that moved is refused ------------------------------------------------------
printf 'stale\n' > "$STUB_STORE/4585"; printf 'draft' > "$STUB_STATUS/4585"
if STUB_FLIP=1 run write guide-thank-you > "$T/w6.out" 2>&1; then fail "9: a status flip was accepted"; fi
grep -q "post_status changed" "$T/w6.out" || fail "9: the status change was not named"
printf 'draft' > "$STUB_STATUS/4585"
pass "9 a post_status that moved during the write is refused"

# --- 10. uncommitted source is refused ------------------------------------------------------------
printf '\n<!-- edit -->\n' >> "$W/$SRC_REL"
if run write guide-thank-you > "$T/w7.out" 2>&1; then fail "10: uncommitted source accepted"; fi
grep -q "uncommitted" "$T/w7.out" || fail "10: the refusal did not name uncommitted changes"
pass "10 uncommitted source is refused"

# --- 11. source that is not on origin/main is refused ----------------------------------------------
git -C "$W" -c user.email=t@t -c user.name=t commit -qam edit
if run write guide-thank-you > "$T/w8.out" 2>&1; then fail "11: unmerged source accepted"; fi
grep -q "differs from origin/main" "$T/w8.out" || fail "11: the refusal did not name origin/main"
git -C "$W" reset -q --hard HEAD~1
pass "11 source that is not on origin/main is refused"

# --- 12. an unpinned host key is refused ------------------------------------------------------------
sed "s/^WPCOM_SSH_HOST=.*/WPCOM_SSH_HOST=not.pinned.example/" "$T/env" > "$T/env.bad"
if WPCOM_SSH_ENV="$T/env.bad" WP_SSH_CMD="$T/stub" "$SCRIPT" plan > "$T/w9.out" 2>&1; then
  fail "12: an unpinned host was accepted"
fi
grep -q "no pinned host key" "$T/w9.out" || fail "12: the refusal did not name the pinned host key"
pass "12 an unpinned host key is refused"

# --- 13. a remote that does not read stdin is refused ------------------------------------------------
printf 'stale\n' > "$STUB_STORE/4585"
if STUB_NOSTDIN=1 run write guide-thank-you > "$T/w10.out" 2>&1; then fail "13: write ran without a stdin probe"; fi
grep -q "STDIN probe token" "$T/w10.out" || fail "13: the refusal did not name the probe"
pass "13 a remote that does not read stdin is refused"

# --- 14. WP-CLI global flags go before the subcommand ------------------------------------------------
grep -qE "wp post get .*--quiet" "$SCRIPT" && fail "14: --quiet is placed after the subcommand"
run plan guide-download >/dev/null 2>&1 || true   # the stub errors on a misplaced flag
pass "14 WP-CLI global flags go before the subcommand"

# --- 15. an unknown page name is refused ---------------------------------------------------------------
if run write no-such-page > "$T/w11.out" 2>&1; then fail "15: an unknown page name was accepted"; fi
grep -q "unknown page" "$T/w11.out" || fail "15: the refusal did not name the page"
pass "15 an unknown page name is refused"

echo "all wp_publish_page.sh checks passed"
