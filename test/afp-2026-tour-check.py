#!/usr/bin/env python3
"""Behaviour check for events/2026/afp/index.html — the AFP 2026 tour
registration page.

WHY THIS IS A BROWSER CHECK AND NOT A UNIT TEST
The page's logic (pillFor, tourChoices, tourChoiceValue, resolveMappingId)
lives inside one IIFE that closes over the DOM, so nothing is importable.
Unit-testing it would mean either shipping test hooks to production or
restructuring the page — neither is worth it for a static content repo. So
this drives the real page in a real browser instead, which is also what
actually catches the failures this page has had (layout, wrapping, CORS).

NOT WIRED INTO CI ON PURPOSE. .github/workflows/node.yml has no browser and
installs no Playwright, and adding one would put a browser download on every
PR in this repo. Run it by hand when you change the picker, the pills, or the
submit path.

USAGE
    python3 -m http.server 8790 --bind 127.0.0.1   # from the repo root
    python3 test/afp-2026-tour-check.py            # needs playwright

    Exits 0 if every assertion passes, 1 otherwise. Prints one line per case.

WHAT IT PROVES
    - the four tour choices render, in order, with the right labels
    - a session at capacity shows SOLD OUT, never "0 of N spots open"
    - a session with spots left still shows its count
    - every choice kind submits, with the right mapping id and stored value
    - "Request a Custom Tour" still submits the legacy "Private tour request"
      value against the private-tour mapping (existing leads must not break)
    - the eligibility acknowledgement is present, required and captured
    - no horizontal overflow at desktop, phone and both embed widths
    - an RT Gate outage never tells a visitor registration has not opened

Live RT Gate GETs are served from fixtures via a Playwright route: localhost
is cross-origin to realtreasury.com, and without this the page falls into its
designed offline state and every assertion below is vacuous. POST /submit is
intercepted, so running this creates nothing.
"""
import json
import sys

try:
    from playwright.sync_api import sync_playwright
except ImportError:
    print("SKIP: playwright not installed (pip install playwright && playwright install chromium)")
    raise SystemExit(0)

BASE = "http://127.0.0.1:8790/events/2026/afp/"
CORS = {
    "access-control-allow-origin": "*",
    "access-control-allow-headers": "*",
    "access-control-allow-methods": "GET,POST,OPTIONS",
    "content-type": "application/json",
}

# Mirrors RT Gate form 3. Only the shape matters; the page owns the picker,
# the phone/current-system fields and the acknowledgement regardless.
FORM = {
    "form_id": 3,
    "consent_text": "I agree to receive updates and accept the privacy policy.",
    "fields": [
        {"key": "full_name", "label": "Full name", "type": "text", "required": True},
        {"key": "email", "label": "Work email", "type": "email", "required": True},
        {"key": "company", "label": "Company", "type": "company", "required": True},
        {"key": "title", "label": "Title", "type": "text", "required": False},
        {"key": "group_size", "label": "Group size", "type": "select", "required": True,
         "options": ["Just me", "2 people", "3 people", "4+ people"]},
        {"key": "tms_timeline", "label": "Where are you in your evaluation?", "type": "select",
         "required": True, "options": ["Actively selecting in 2026", "Planning for 2027"]},
        {"key": "interests", "label": "Anything you want to be sure you see?",
         "type": "textarea", "required": False},
    ],
}

# Session 1 at capacity, session 2 with room. Sessions 3/4 are teased in page
# config and must stay invisible.
COUNTS = {
    "afp-2026-tour-session-1": 10,
    "afp-2026-tour-session-2": 4,
    "afp-2026-tour-session-3": 0,
    "afp-2026-tour-session-4": 0,
}

failures = []
captured = {}


def check(name, cond, detail=""):
    print(("  PASS  " if cond else "  FAIL  ") + name + (("  -- " + str(detail)) if detail and not cond else ""))
    if not cond:
        failures.append(name)


def route(offline=False):
    def handler(r, request):
        if offline:
            r.abort()
            return
        if request.method == "OPTIONS":
            r.fulfill(status=204, headers=CORS)
        elif request.method == "POST" and "/submit" in request.url:
            captured["payload"] = json.loads(request.post_data or "{}")
            r.fulfill(status=200, headers=CORS, body=json.dumps({"success": True}))
        elif "/form/" in request.url:
            r.fulfill(status=200, headers=CORS, body=json.dumps(FORM))
        elif "/stats/registrations" in request.url:
            r.fulfill(status=200, headers=CORS,
                      body=json.dumps({"counts": COUNTS, "registration": {}}))
        else:
            r.fulfill(status=200, headers=CORS, body=json.dumps({"mapping_id": 13}))
    return handler


def open_page(b, width, height, embed=False, offline=False):
    ctx = b.new_context(viewport={"width": width, "height": height})
    ctx.route("**/wp-json/rtg/v1/**", route(offline))
    p = ctx.new_page()
    p.errors = []
    p.on("pageerror", lambda e: p.errors.append(str(e)))
    p.goto(BASE + ("?embed=1" if embed else ""), wait_until="load")
    p.wait_for_selector("#rtTourChoiceGroup", timeout=15000)
    p.wait_for_timeout(1200)
    return ctx, p


def options(p):
    return p.eval_on_selector_all("#rtTourChoiceGroup label", """els => els.map(l => ({
        label: l.querySelector('span').childNodes[0].textContent.trim(),
        pill: (l.querySelector('.rt-t-pill') || {}).textContent || '',
        kind: l.querySelector('input').dataset.kind
    }))""")


def submit_as(p, kind):
    idx = [i for i, o in enumerate(options(p)) if o["kind"] == kind][0]
    p.eval_on_selector_all("#rtTourChoiceGroup input", "(els, i) => els[i].click()", idx)
    for sel, val in [('[name="full_name"]', "Test Person"),
                     ('[name="email"]', "test@examplecorp.com"),
                     ('[name="phone"]', "5555555555"),
                     ('[name="company"]', "Example Corp"),
                     ('[name="current_system"]', "none")]:
        if p.query_selector(sel):
            p.fill(sel, val)
    for s in p.query_selector_all("#rtTourForm select"):
        vals = s.eval_on_selector_all("option", "os => os.map(o => o.value).filter(Boolean)")
        if vals:
            s.select_option(vals[0])
    for sel in ('[name="eligibility_confirm"]', "#rtTourConsent"):
        el = p.query_selector(sel)
        if el:
            el.check()
    captured.pop("payload", None)
    p.click(".rtg-submit-btn")
    p.wait_for_timeout(2000)
    return captured.get("payload", {}), p.query_selector(".rt-t-success")


EXPECTED = ["TMS Replacement Tour", "Visibility Tour",
            "Request a Custom Tour", "None of These Work for Me"]

with sync_playwright() as pw:
    b = pw.chromium.launch()

    print("\n[1] choices, pills and layout")
    for label, w, h, embed in [("desktop 1280", 1280, 1000, False),
                               ("phone 390", 390, 844, False),
                               ("embed 560", 560, 1000, True),
                               ("embed 360", 360, 800, True)]:
        ctx, p = open_page(b, w, h, embed)
        opts = options(p)
        check("%s: four choices" % label, len(opts) == 4, [o["label"] for o in opts])
        for i, want in enumerate(EXPECTED):
            check("%s: choice %d is %r" % (label, i + 1, want),
                  i < len(opts) and opts[i]["label"].startswith(want),
                  opts[i]["label"] if i < len(opts) else "missing")
        check("%s: full session says SOLD OUT" % label,
              any(o["pill"].strip() == "SOLD OUT" for o in opts), [o["pill"] for o in opts])
        check("%s: no zero count anywhere" % label,
              not any("0 of " in o["pill"] for o in opts), [o["pill"] for o in opts])
        check("%s: session with room still shows its count" % label,
              any("6 of 10 spots open" == o["pill"].strip() for o in opts), [o["pill"] for o in opts])
        check("%s: no horizontal overflow" % label,
              p.evaluate("document.documentElement.scrollWidth - document.documentElement.clientWidth") <= 0)
        check("%s: no JS errors" % label, not p.errors, p.errors)
        ack = p.query_selector('[name="eligibility_confirm"]')
        check("%s: acknowledgement present and required" % label,
              ack is not None and ack.get_attribute("required") is not None)
        check("%s: registration is open (submit enabled)" % label,
              p.query_selector(".rtg-submit-btn").get_attribute("disabled") is None)
        ctx.close()

    print("\n[2] submissions")
    # (kind, expected mapping id, expected stored value)
    for kind, mapping, value in [
        ("session", 9, "TMS Replacement Tour: Monday, November 9, 10:15–11:30 AM — waitlist"),
        ("private", 13, "Private tour request"),
        ("none", 13, "None of These Work for Me"),
    ]:
        ctx, p = open_page(b, 1280, 1000)
        payload, success = submit_as(p, kind)
        f = payload.get("fields", {})
        check("%s: submitted successfully" % kind, success is not None)
        check("%s: mapping id %s" % (kind, mapping), payload.get("mapping_id") == mapping,
              payload.get("mapping_id"))
        check("%s: stores %r" % (kind, value), f.get("tour_preference") == value,
              f.get("tour_preference"))
        check("%s: acknowledgement captured" % kind, bool(f.get("eligibility_confirm")))
        ctx.close()

    print("\n[3] RT Gate outage never says registration has not opened")
    ctx, p = open_page(b, 1280, 1000, offline=True)
    body = p.inner_text("body").lower()
    check("outage: no 'opens shortly'", "opens shortly" not in body)
    check("outage: no 'check back'", "check back" not in body)
    check("outage: no JS errors", not p.errors, p.errors)
    ctx.close()
    b.close()

print("\n%d checks failed" % len(failures))
for f in failures:
    print("  -", f)
sys.exit(1 if failures else 0)
