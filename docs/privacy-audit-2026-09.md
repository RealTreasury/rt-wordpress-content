# Privacy audit, September 2026 — what changed and what still needs a person

Audit run September 22, 2026 against the live site. This file records what this
branch fixes, what it deliberately does not, and the exact steps left for a
person to run.

## The problem this branch fixes

The site had a cookie banner that gated nothing. `assets/php/functions.php`
hooked `wp_footer` and, on accept, called a `loadGoogleAnalytics()` that
immediately returned because `gtag` was already defined — Site Kit and Jetpack
had both printed their tags into `<head>` long before the banner rendered.
"Decline" deleted the `_ga` cookie while the tag kept running and re-set it on
the next pageview. Jetpack Stats was never gated at all, and the banner skipped
itself entirely in private browsing while analytics still loaded.

Three Google tags were live at once — Site Kit's `GT-5MX2ZW24` and
`GTM-W877KNJR`, plus Jetpack's own `G-6KLBPGHTSM` — so pageviews were being
double counted.

## What changed in code

`assets/php/functions.php`

- `rt_consent_mode_defaults()` on `wp_head` priority 1. Pushes Google Consent
  Mode v2 defaults into `dataLayer` **before** any Google tag loads.
  `analytics_storage` defaults to `denied`; ad storage is denied permanently
  because we run no ads. Priority 1 is what puts it ahead of Site Kit — do not
  change it.
- `rt_has_analytics_consent()` reads a first-party `rt_consent` cookie. A cookie
  rather than localStorage alone, so PHP can read it and so the Cookie Policy
  can describe it truthfully.
- `rt_disable_jetpack_trackers()` filters `jetpack_active_modules` to drop
  `google-analytics` (the duplicate GA4 tag) and `stats` (stats.wp.com, which is
  not Consent Mode aware, was undisclosed in our policies, and is redundant next
  to GA4 and Search Console). Removed unconditionally rather than varied on the
  consent cookie, so page output stays cacheable.
- `rt_consent_banner()` replaces the old banner. Accept / Reject / Preferences,
  a real preference panel, and consent changes applied via
  `gtag('consent','update',…)` rather than by injecting a script. Any element
  carrying `data-rt-cookie-preferences` opens the panel, and
  `window.rtOpenCookiePreferences()` is exposed for the same purpose.

The panel CSS is emitted inline by the same function rather than living in
`assets/css/shared.css`, so the whole consent layer deploys in one step. If it
is ever moved to shared.css it needs a second publish via
`scripts/wp_publish_shared_css.sh` or the panel ships unstyled.

## What changed in copy

- `privacy-policy/index.html` — rewritten. Names every processor
  (Automattic/Jetpack, **Akismet**, Cloudflare, Google, Resend, Microsoft 365),
  drops the invented user accounts / passwords / payment
  processing, gives real retention periods, adds a CCPA/CPRA section, and states
  the security posture honestly instead of claiming certifications we do not
  hold.
- `cookie-policy/index.html` — **new file.** The live page dated from December
  2024 and listed cookies the site does not set (`session_id`, `csrf_token`)
  while omitting every one it does. It also claimed we use advertising networks,
  which we do not, and offered a preference centre that did not exist. The new
  page lists the actual inventory and the preference control now works.
- `terms-of-service/index.html` — was a 27-line stub that had drifted away from
  the live page entirely. Production carries 11 sections describing user
  accounts, passwords and an "account deletion feature in your user settings"
  that this site has never had. Now the full corrected text, including the
  no-advice and vendor-independence statements.

## Left for a person

### 1. Publish the three pages

Add these rows to `wp/pages.tsv` and run the rail. They are not committed on
this branch on purpose — adding them is the decision to deploy.

```
privacy-policy		167	privacy-policy/index.html	page	privacy-policy
terms-of-service	358	terms-of-service/index.html	page	terms-of-service
cookie-policy		360	cookie-policy/index.html	page	cookie-policy
```

Post IDs were read from the `page-id-NNN` body class on the live pages on
September 22, 2026. Columns are tab separated.

```bash
scripts/wp_publish_post.py plan    privacy-policy
scripts/wp_publish_post.py plan    cookie-policy
scripts/wp_publish_post.py plan    terms-of-service
```

Read all three diffs before publishing. **Terms of service especially** — this
replaces the live body rather than topping it up, because the repo file and the
live page had diverged.

Per the note at the foot of `wp/pages.tsv`: never round-trip a `page`-mode post
through REST or the block editor afterwards without re-checking that the
`rt:page-content` markers survived.

### 2. Confirm before the privacy policy goes live

`privacy@realtreasury.com` is confirmed live and monitored (Tim, September 22,
2026), so the 10-business-day acknowledgement and 30-day response the policy
commits to in section 6 are addressable. Nothing to change there.

Salesforce was briefly named as a processor in section 3, on the assumption the
`rt-gate` connector was live. Tim confirmed on September 22, 2026 that we are
retiring Salesforce, so the row is out and the policy no longer names it.

One thing to watch on timing. The policy now tells readers that Salesforce is
not among our processors. If contact records are still sitting in Salesforce
when the policy publishes, the document is ahead of reality in the wrong
direction — under-disclosure, which is the worse way to be wrong. Either finish
the wind-down and delete the data before publishing, or put the row back until
it is done. `includes/class-salesforce.php` still ships in `rt-gate`, and the
erasure notice added in rt-gate PR #90 still names Salesforce, which is correct
for as long as data remains there. Both can be cleaned up once the migration
lands.

These came out of the publish-check pass:

- **GA4 data retention** — confirmed at 14 months (Tim, September 22, 2026,
  from recollection of setting it; GA4 offers only 2 or 14 months, so there is
  no third reading). Section 5 stands. Worth a glance at Admin → Data Settings
  → Data Retention next time someone is in the property, but not a blocker.
- **Governing law** — resolved to **Florida, Hillsborough County**. The first
  draft said Texas / Dallas County, chosen only because Dallas leads the footer
  address. Our executed client consulting agreement governs by Florida law with
  exclusive venue in Hillsborough County, and terms of service section 9 now
  matches it. Rationale below.
- **Cookie inventory.** The cookie policy table is built from what Cloudflare,
  WordPress and GA4 are known to set, not from a live measurement — a plain
  `curl` of the site returned no cookies at all. Walk the table against a real
  browser session during the staging check below and correct any difference.

One accuracy fix landed in the same pass: `webinars/err-not-demo-script/`
built its player URL on `youtube.com/embed/`, which sets cookies on load, while
the cookie policy says we use the no-cookie player. It now builds
`youtube-nocookie.com/embed/`, matching `webinars/prompt-to-product/`.

### 2b. Why the terms of service say Florida

Not a preference between two fine options. The point of a venue clause is that
related disputes land in one forum, and our client paperwork already picked one.

- Our executed client consulting agreement is **governed by Florida law with
  exclusive venue in Hillsborough County, Florida**.
- A website terms of service pointing at Dallas County, Texas would have split
  the forum. Someone who downloads the guide under these terms and later signs
  an engagement would be covered by two clauses naming two states — which is
  the one scenario where a venue clause on a brochure site matters at all.

So the website follows the client agreement, not the footer address.

**Which entity the website belongs to.** There are two, and they are organized
in different states. Per the Management Services Agreement effective July 1,
2026:

- **Real Treasury, LLC — a Florida limited liability company.** The "Operating
  Company": it holds the client contracts, earns the revenue, and is the entity
  behind this website.
- **Real Treasury Services, LLC — a Texas limited liability company**, S-corp
  elected. The shared-services and payroll entity that employs the principals.

So Florida is right for the website twice over: it is the state of the entity
that operates it, and it matches the forum in the client consulting agreement.

An earlier revision of this file claimed the two states were a contradiction
needing legal review. That was wrong — it conflated the two entities and
anchored on a June 2024 Texas operating agreement for Real Treasury, LLC over
the July 2026 MSA. The MSA refers to the Operating Company's operating
agreement "as amended," so a later one supersedes the 2024 document. Nothing
here needs a lawyer.

### 3. Verify the consent layer on staging first

This changes analytics collection sitewide, so prove it on staging:

1. Load a page with a clean profile. In DevTools → Network, confirm
   `stats.wp.com` no longer loads and only the Site Kit tags remain.
2. In Console, `dataLayer` should carry the `consent default` entry with
   `analytics_storage: "denied"` as its first Google-related push.
3. Application → Cookies: no `_ga` before you click Accept.
4. Click Accept. `rt_consent=analytics` is set, `_ga` appears.
5. Reload, open the Cookie Policy page and use its Manage cookie preferences button
   (no footer trigger exists; the banner's Preferences button only shows before a choice), untick
   Analytics, save. `_ga` is cleared and does not come back on reload.
6. Confirm the WP Admin Jetpack Stats dashboard going quiet is acceptable — the
   `stats` module is now off. One line in `rt_disable_jetpack_trackers()` puts
   it back if it is wanted.

### 4. Re-baseline GA4

Dropping the duplicate Jetpack tag and gating the rest on consent will move the
numbers in both directions — fewer double-counted pageviews, and no data at all
from visitors who decline. The September 19, 2026 SEO baseline is not comparable
to anything measured after this ships. Take a fresh baseline a week after
deploy. Search Console is unaffected and stays the better trend line across the
change.

### 5. Separately: gated leads are outside WordPress's privacy tooling

`rt-gate` registers no `wp_privacy_personal_data_exporters` or
`wp_privacy_personal_data_erasers` hooks, so a data request handled through
WordPress's built-in export/erase tools silently misses every gated-asset lead —
while the privacy policy promises access and deletion within 30 days. Tracked
separately against the `rt-gate` repo.
