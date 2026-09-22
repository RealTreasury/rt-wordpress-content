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
  (Automattic/Jetpack, **Akismet**, Cloudflare, Google, Resend, Microsoft 365,
  Salesforce), drops the invented user accounts / passwords / payment
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

Two things in the draft are asserted rather than verified, because production
access was not available in the session that wrote it:

- **Is `privacy@realtreasury.com` a live, monitored mailbox?** The policy
  promises acknowledgement in 10 business days and a response in 30. If it does
  not exist or nobody watches it, point it at a mailbox that someone reads
  before publishing.
- **Is the Salesforce connector actually configured?** `rt-gate` ships
  `includes/class-salesforce.php`, but whether it is switched on in production
  could not be checked. Salesforce is named as a processor in section 3 of the
  privacy policy on the assumption that it is. If it is not live, strike that
  row — a policy that names a processor we do not use is as wrong as one that
  omits a processor we do.

These came out of the publish-check pass and are the remaining unevidenced
claims:

- **GA4 data retention.** Section 5 states 14 months. Confirm the setting on the
  property (Admin → Data Settings → Data Retention) and correct the number if it
  is not 14 months.
- **Governing law.** Terms of service section 9 names Texas law and Dallas
  County courts, chosen because Dallas leads the footer address. A venue clause
  is a legal decision, not a drafting one — confirm it matches where the LLC is
  organized, or replace it.
- **Multi-factor authentication.** An earlier draft of privacy policy section 7
  said access to enquiry records is "protected by multi-factor authentication."
  It was cut because it could not be verified from this session. If MFA is in
  fact enforced on Microsoft 365, Salesforce and WordPress.com, put the phrase
  back — it is worth saying.
- **Cookie inventory.** The cookie policy table is built from what Cloudflare,
  WordPress and GA4 are known to set, not from a live measurement — a plain
  `curl` of the site returned no cookies at all. Walk the table against a real
  browser session during the staging check below and correct any difference.

One accuracy fix landed in the same pass: `webinars/err-not-demo-script/`
built its player URL on `youtube.com/embed/`, which sets cookies on load, while
the cookie policy says we use the no-cookie player. It now builds
`youtube-nocookie.com/embed/`, matching `webinars/prompt-to-product/`.

### 3. Verify the consent layer on staging first

This changes analytics collection sitewide, so prove it on staging:

1. Load a page with a clean profile. In DevTools → Network, confirm
   `stats.wp.com` no longer loads and only the Site Kit tags remain.
2. In Console, `dataLayer` should carry the `consent default` entry with
   `analytics_storage: "denied"` as its first Google-related push.
3. Application → Cookies: no `_ga` before you click Accept.
4. Click Accept. `rt_consent=analytics` is set, `_ga` appears.
5. Reload, open Preferences from the footer Cookie Policy link, untick
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
