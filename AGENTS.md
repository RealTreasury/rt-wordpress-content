# Repository Instructions

Static HTML content for the Real Treasury website. Most pages are pasted into
WordPress posts/pages directly; a few (`webinars/`, `treasury-tech-selection/waitlist/`, etc.)
are standalone HTML files served via WordPress.

## Keeping this file current

**AGENTS.md is the entry point for every AI coding session in this repo.**
If you change anything that affects how future contributors should work in
this codebase, update this file in the same commit. Specifically, if a
change to `README.md`, `docs/*.md`, plugin READMEs, or any other
authoritative reference does one of the following, AGENTS.md must reflect
or link to it:

- Introduces or renames a canonical path / directory convention.
- Deprecates a plugin, helper, template, or directory.
- Adds a new build / lint / test / publish command.
- Establishes a new architectural rule (e.g. "all X must use Y").
- Adds a new docs file that codifies a workflow.

Detailed reference content belongs in `docs/`; AGENTS.md should be a short,
high-signal index that names the rule and points at the deeper doc. If
you find a rule that exists in `docs/` or `README.md` but is not surfaced
here, treat that as a bug and fix it.

## Build commands

1. Install dependencies:
   ```bash
   npm install
   ```
2. Build static pages (renders EJS templates in `templates/` into the matching
   `insights/` directory):
   ```bash
   npm run build
   ```
3. Optionally run the EJS test script:
   ```bash
   npm run test:ejs
   ```
4. Run the test gate — the one command that means "the tests pass" here, and
   the gate the automated repair arm (`serena_coder`) runs on a bare worktree
   with no `npm install`:
   ```bash
   bash scripts/run_checks.sh
   ```
   It runs the package-free `npm run test:*` checks and stops at the first
   failure. `test:build-clean` is left out because it reads `git status` and is
   red on any dirty tree; CI runs it as its own step against a clean checkout.
   `RUN_CHECKS_BUILD_CLEAN=1 bash scripts/run_checks.sh` opts it back in.

## Site-wide banner

The source of record, promotion rota, date-window contract, test command and
WordPress deployment notes are in `docs/site-banner.md`. Run
`npm run test:banner` after changing the banner or its destinations.

## Directory conventions (read before adding new pages)

- **`webinars/`** (plural) — canonical home for every webinar page.
  New webinars go in `webinars/<slug>/index.html`.
- **`webinar/`** (singular) — DEPRECATED. Contains only redirect stubs
  pointing at `webinars/`. Do not add files here or reference these paths
  in new code.
- **`plugins/treasury-portal-access/`** — DEPRECATED gating plugin
  (Contact Form 7 + cookie). Kept for historical reference only. See
  the next section.

See also `docs/webinar-publishing.md` for the webinar publishing contract.

## Existing selection pages: SEO and copy

See `docs/selection-pages-seo.md` for the September 2026 four-page review and
publication checks. Preserve the owner's approved wording; improve contextual
links using existing phrases rather than adding SEO copy or vendor lists.
WordPress/Yoast owns document metadata. `verbatim` publishing preserves styles,
scripts and font links but removes embedded head titles/meta/canonical links so
they do not compete with WordPress. Body and SVG titles are preserved.

## Gated content — use RT Gate, not `treasury-portal-access`

All new gated content (forms that unlock a video, download, link, or
waitlist) MUST use the **RT Gate** WordPress plugin. The plugin exposes a
REST API at `/wp-json/rtg/v1/` and is configured via the WP Admin
("Forms", "Assets", "Mappings" screens).

- **Canonical client template:** `templates/partials/gated-video.html` —
  this is the reference implementation. New gated pages should mirror its
  `window.RTG_CONFIG` block and its form-rendering / submission script.
- **Full integration guide:** `docs/rt-gate.md`.
- **2026 selection-guide release sequence:** `docs/guide-release-runbook.md`.
- **Existing RT Gate pages** all live under `webinars/` (plural) and
  `treasury-tech-selection/waitlist/`. There are no RT Gate pages under
  `webinar/` (singular) — that directory is deprecated redirect stubs.

### Deprecated — do NOT build on this

- `plugins/treasury-portal-access/` — Contact Form 7 + cookie-based gate
  with `[protected_content]` / `[portal_button]` shortcodes. Kept for
  historical reference only. Do **not** modify it, extend it, or
  reference its shortcodes in new pages.
- Any page that hand-rolls its own form fetch/submit against the RT Gate
  API instead of using the canonical template's logic. The renderer
  must handle every RT Gate field type — `text`, `email`, `tel`, `url`,
  `number`, `date`, `textarea`, `select`, `radio`, `checkbox` — plus
  `placeholder`, `autocomplete`, and `consent_text` from the schema.
  Anything less will silently drop options on dropdowns/radios.

### Quick reference

```html
<script>
window.RTG_CONFIG = {
    assetSlug:  'my-asset-slug', // Must match the asset slug in WP Admin -> RT Gate -> Assets
    mappingId:  5                // The Form -> Asset mapping ID from RT Gate -> Mappings
    // formId:  123              // Optional: pin a specific form ID and skip the asset mapping lookup
};
</script>
```

When in doubt about form/asset wiring, check WP Admin (RT Gate menu) for the
authoritative slugs and IDs — don't guess.

## WordPress Additional CSS

`assets/css/shared.css` is version controlled but not loaded by the theme; it
lives in **Appearance -> Customize -> Additional CSS**. Do not paste it by hand.
Publish it from the rt-ai-02 box after the change is merged to `main`:

```bash
scripts/wp_publish_shared_css.sh plan       # read-only: live vs source diff, drift
scripts/wp_publish_shared_css.sh publish    # guarded write, byte read-back, receipt
```

`publish` refuses unmerged or uncommitted CSS, refuses when the live CSS is not
what the script last published (drift), pins the `ssh.wp.com` host key from
`scripts/wpcom_known_hosts`, and writes `assets/css/shared.css.published.sha256`
(commit it). Credentials: `/opt/rt-ai/secrets/wpcom-ssh.env`. Tests:
`scripts/tests/test_wp_publish_shared_css.sh` (stub remote, no network). Full
notes in `README.md`.

## Consent and analytics

Analytics consent is Google Consent Mode v2, implemented in
`assets/php/functions.php`:

- `rt_consent_mode_defaults()` runs on `wp_head` **priority 1** so the consent
  defaults reach `dataLayer` before Site Kit's tags. Do not lower that priority
  and do not move it to `wp_footer` — that is exactly the bug it replaced.
- `rt_disable_jetpack_trackers()` keeps Jetpack's `google-analytics` and `stats`
  modules off. Jetpack's GA tag duplicated Site Kit's and ignored Consent Mode;
  `stats` is not Consent Mode aware.
- Anything that should open the preference panel gets a
  `data-rt-cookie-preferences` attribute, or calls
  `window.rtOpenCookiePreferences()`.

Do not add a tag, pixel or embed that sets cookies without routing it through
this. If it cannot respect Consent Mode, it does not go on the site. Tags in
the GTM container (`GTM-W877KNJR`) are outside this repo: the banner's promise
holds only if each one requires `analytics_storage` in GTM's consent settings
(see `docs/privacy-audit-2026-09.md`).

## Legal pages

`privacy-policy/`, `cookie-policy/` and `terms-of-service/` are `page`-mode
sources for native WordPress pages 167, 360 and 358. They are content
fragments — a `<style>` block plus body markup, with no `<html>`/`<head>`/`<body>`
wrapper and no head tags. Page mode takes a wrapper-less file whole, so a
`<title>` or `<meta>` here would land in post content; WordPress/Yoast owns
metadata. `scripts/tests/test_wp_publish_post.py` checks this.

They describe what the site actually does, so a change to forms, embeds,
analytics or any third-party service means a matching change here. Both are
outward-facing, so a draft goes through the publish-check skill before anyone
ships it. Background and the publish steps: `docs/privacy-audit-2026-09.md`.

## Webinar publishing

See `docs/webinar-publishing.md` for the taxonomy contract and pre-publish
QA checklist.

## Tech Selection Guide release

See `docs/guide-release-runbook.md` for the current WordPress-native guide
release state and the remaining publish sequence.
