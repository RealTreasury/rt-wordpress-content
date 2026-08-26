# Walk the Map (AFP 2026) — WordPress + RT Gate wiring

The page in this folder is served from GitHub Pages and embedded in the
WordPress post (draft 4485, "Guided Vendor Tours at AFP 2026") inside the
form card. RT Gate has **no** gated-form shortcode; a `[RT_GATE_SHORTCODE_GOES_HERE]`
placeholder can never render. Use the iframe block below.

## 1. WordPress post — replace the placeholder shortcode block

In post 4485, inside the `rt-afp-formcard` group, delete the
`wp:shortcode` block and paste this **Custom HTML** block in its place
(after the "Reserve your spot" / "Pick a session below." paragraphs):

```html
<iframe id="iframe-default" class="rt-afp-embed" src="https://realtreasury.github.io/rt-wordpress-content/events/2026/afp/?embed=1" title="Walk the Map tour registration" scrolling="no" style="width:100%;border:0;min-height:720px;display:block;overflow:hidden" data-iframe-resizer="true" data-iframe-resizer-allowed-origins="https://realtreasury.github.io"></iframe>
<script>
(function () {
  var frame = document.getElementById('iframe-default');
  if (!frame) return;
  var ORIGIN = 'https://realtreasury.github.io';
  /* Forward campaign parameters from the post URL into the embed so the
     registration records utm_* exactly as the ad link carried them. */
  try {
    var src = new URLSearchParams(window.location.search);
    var pass = [];
    ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function (k) {
      var v = src.get(k);
      if (v) pass.push(k + '=' + encodeURIComponent(v));
    });
    if (pass.length) frame.src = frame.getAttribute('src') + '&' + pass.join('&');
  } catch (e) {}
  /* Height: the embed posts its content height (typed setHeight). The
     site-wide iframe-resizer handles it when present; this is the fallback. */
  window.addEventListener('message', function (e) {
    if (e.origin !== ORIGIN) return;
    var d = e.data;
    if (!d || d.type !== 'setHeight') return;
    var h = parseInt(d.height, 10);
    if (h > 0) frame.style.height = h + 'px';
  });
})();
</script>
```

Notes
- `id="iframe-default"` is what the site-wide `assets/js/iframe-resizer.js`
  looks for; the inline script is the fallback and is origin-checked.
- `utm_*` on the post URL is forwarded into the embed and stored with the
  registration.
- Optional tidy-up: the post title duplicates the hero headline; in the post
  sidebar (Astra → Disable Elements) hide the post title.

## 2. Releasing / capping sessions — WP Admin, no deploy

WP Admin → Real Treasury Gate → Assets → edit the session asset → Config (JSON).
Add the two keys next to the existing `public_stats`:

```json
{ "public_stats": true, "registration_state": "open", "capacity": 10 }
```

| Asset (slug)               | Mapping | Default state in page |
|----------------------------|---------|-----------------------|
| afp-2026-tour-session-1    | 9       | open                  |
| afp-2026-tour-session-2    | 10      | teased                |
| afp-2026-tour-session-3    | 11      | teased                |
| afp-2026-tour-session-4    | 12      | teased                |
| afp-2026-tour-private      | 13      | always offered        |

- `registration_state`: `open` (taking registrations; flips to waitlist by
  itself when `capacity` is reached), `teased` ("Opening soon", not
  selectable), `waitlist` (force "Full — waitlist open"; keeps collecting),
  `closed` (hidden entirely).
- `capacity`: positive integer. Soft cap per the Aug 18 decision — the
  waitlist keeps collecting past it.
- Takes effect within ~60 s (stats cache). Requires rt-gate PR #79 deployed;
  until then the page uses its own `RT_TOUR` config (fallback).
- Waitlist registrations are ordinary registrations whose `tour_preference`
  starts with `Waitlist:`; they land under the same session mapping so the
  per-session count and lead list stay together.

## 3. Form 3 fields (WP Admin → Real Treasury Gate → Forms → AFP 2026 Walk the Map Tour Registration)

The page already captures all of these even while form 3 is the stock
email-only form. Pasting the schema makes WP Admin show the same fields:

```json
[
  { "key": "full_name",       "label": "Full name",                              "type": "text",     "required": true,  "placeholder": "Jane Smith" },
  { "key": "email",           "label": "Work email",                             "type": "email",    "required": true,  "placeholder": "you@company.com" },
  { "key": "company",         "label": "Company",                                "type": "company",  "required": true },
  { "key": "title",           "label": "Title",                                  "type": "text",     "required": false },
  { "key": "group_size",      "label": "Group size",                             "type": "select",   "required": true,  "options": ["Just me", "2 people", "3 people", "4+ people"] },
  { "key": "tms_timeline",    "label": "Where are you in your evaluation?",      "type": "select",   "required": true,  "options": ["Actively selecting in 2026", "Planning for 2027", "Just researching"] },
  { "key": "tour_preference", "label": "Which tour?",                            "type": "text",     "required": true },
  { "key": "interests",       "label": "Anything you want to be sure you see?",  "type": "textarea", "required": false },
  { "key": "utm",             "label": "UTM",                                    "type": "text",     "required": false }
]
```

Email settings: confirmation to registrant only; internal notify
tschultz@ + tknight@.

## 4. Smoke test (after merge)

1. Open the draft preview; confirm the schedule + form render inside the card
   and the card grows to fit (no inner scrollbar).
2. Submit once with `tschultz+afptest@realtreasury.com`, Session 1. Expect:
   "You're on the list", counter 10→9 within a minute, lead + event in WP
   Admin, confirmation + internal emails.
3. Soft-delete the test event **and** lead in WP Admin before the 6:45 AM
   lead sync.
4. Publish the post (a person publishes).
