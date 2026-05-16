# Repository Instructions

Static HTML content for the Real Treasury website. Most pages are pasted into
WordPress posts/pages directly; a few (`webinars/`, `treasury-tech-selection/waitlist/`, etc.)
are standalone HTML files served via WordPress.

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

## Gated content — use RT Gate, not `treasury-portal-access`

All new gated content (forms that unlock a video, download, link, or
waitlist) MUST use the **RT Gate** WordPress plugin. The plugin exposes a
REST API at `/wp-json/rtg/v1/` and is configured via the WP Admin
("Forms", "Assets", "Mappings" screens).

- **Canonical client template:** `templates/partials/gated-video.html` —
  this is the reference implementation. New gated pages should mirror its
  `window.RTG_CONFIG` block and its form-rendering / submission script.
- **Full integration guide:** `docs/rt-gate.md`.
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

`assets/css/shared.css` is version controlled but no longer auto-loaded by
the theme. To keep these styles active, copy the contents into
**Appearance -> Customize -> Additional CSS** in WP and click Publish.
Repeat whenever `shared.css` changes.

## Webinar publishing

See `docs/webinar-publishing.md` for the taxonomy contract and pre-publish
QA checklist.
