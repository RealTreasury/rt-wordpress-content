# RT Gate integration guide

The **RT Gate** plugin (installed on the WordPress side, not in this repo) is
the only gating system used for new content. This doc explains how a static
HTML page in this repo wires itself up to RT Gate.

> The legacy `plugins/treasury-portal-access` plugin (Contact Form 7 +
> `[protected_content]` / `[portal_button]` shortcodes, cookie-based) is
> **deprecated**. Do not build on it.

## Concepts

RT Gate has three primary objects, managed in WP Admin:

| Object | What it is | Identifier on the page |
|---|---|---|
| **Form** | A reusable set of fields (e.g. "General Form" with name, email, company, role). | `formId` (optional) |
| **Asset** | A thing being gated (a video, download, link, or waitlist). Has a slug. | `assetSlug` (required) |
| **Mapping** | The Form -> Asset pairing. One asset can only have one active mapping at a time. | `mappingId` (recommended) |

The relationship: a visitor fills out a **Form** to unlock an **Asset**;
the **Mapping** tells RT Gate which form to show for which asset.

## REST API

Base URL: `https://realtreasury.com/wp-json/rtg/v1`

| Endpoint | Returns |
|---|---|
| `GET /gate/{assetSlug}` | The form schema for the asset's active mapping. Use when you only know the slug. |
| `GET /form/{formId}` | The form schema for a specific form. Use when you want to pin a form regardless of mapping. |
| `POST /submit` | Submits form values. Body: `{ form_id, fields, consent, honeypot, mapping_id }`. Returns assets + tokens. |
| `POST /validate` | Validates a token returned by `/submit`. |
| `POST /event` | Logs a tracking event (view, download, etc.). |

## Page-side config

Every gated page MUST set `window.RTG_CONFIG` **before** the rendering
script runs. Resolution order:

1. If `formId` is set, fetch the form schema directly via `/form/{formId}`.
2. Otherwise, fetch `/gate/{assetSlug}` and use the active mapping's form.
3. `mappingId` is sent on `/submit` to scope token issuance to that mapping.
   If the gate's mapping_id disagrees with the configured value, log a
   console warning.

```html
<script>
window.RTG_CONFIG = {
    assetSlug:    'webinar-prompt-to-product', // required
    mappingId:    6,                           // recommended (scopes /submit)
    // formId:    42,                          // optional — pin a specific form

    // Optional UI / behavior overrides used by the canonical template:
    storageKey:   'rtg_my_asset_access_v1',    // localStorage key for unlock state
    badgeText:    'Free On-Demand Workshop',
    heroTitle:    'Title that appears in the hero',
    heroSubtitle: 'Short subtitle.',
    formHeading:  'Watch the Recording',
    formSubtext:  'Fill out the form below.',
    ctaHeading:   'Ready to take the next step?',
    ctaLabel:     'Book a Call',
    ctaUrl:       'https://...',
    videoTitle:   'On-Demand Workshop'
};
</script>
```

## Reference implementation

`templates/partials/gated-video.html` is the canonical implementation. Copy
its `<script>` block (the IIFE starting around `var C = window.RTG_CONFIG;`)
when building a new gated page. It already handles:

- All RT Gate field types: `text`, `email`, `tel`, `url`, `number`, `date`,
  `textarea`, `select`, `radio`, `checkbox`.
- `placeholder`, `autocomplete`, and `consent_text` from the schema.
- Honeypot input + per-field required/email validation with `.rtg-invalid`
  error styling.
- Submit → token → validate → unlock flow with localStorage persistence.
- `formId` / `mappingId` resolution and disagreement warnings.

If you build a custom variant (e.g. a waitlist that just redirects on
success instead of swapping in a video), keep these rules:

- **Render every field type.** A renderer that only handles `textarea`
  and `<input>` will silently drop the options on `select`/`radio`/`checkbox`
  fields (the "role" dropdown in the General Form is a real-world example
  this has bitten).
- **Always send `mapping_id` on `/submit`** when you have one configured.
- **Escape user-visible schema values** (`label`, `placeholder`, options)
  before injecting into HTML. The canonical template uses `escHtml` /
  `escAttr` helpers — reuse them.
- **Surface load failures.** If `/gate/{slug}` 404s, show "Unable to load
  form" with the error message rather than an empty form card.

## Existing pages using RT Gate

All gated webinar pages live under **`webinars/`** (plural) — this is the
canonical webinar path per `docs/webinar-publishing.md`. The legacy
`webinar/` (singular) directory contains only redirect stubs pointing
here; never add new content there.

- `webinars/prompt-to-product/index.html` — video gate (mapping #6)
- `webinars/tms-rfp-trap/index.html` — video gate (mapping #4)
- `webinars/3-segments-1-smart-choice/index.html` — NORAM video gate (mapping #1)
- `webinars/3-segments-1-smart-choice-emea/index.html` — EMEA video gate (mapping #3)
- `treasury-tech-selection/waitlist/index.html` — waitlist signup (mapping #5)

When adding a new gated page, create the Form / Asset / Mapping in WP Admin
first, then mirror one of the pages above whose pattern matches yours
(video unlock vs. waitlist redirect). New gated webinars go in
`webinars/<slug>/index.html` — never in `webinar/`.
