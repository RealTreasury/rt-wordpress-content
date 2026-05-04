# Webinar publishing contract (WordPress Posts)

## Source-of-truth in this repo
- Canonical webinar template path: `webinars/`.
- Reusable gated embed/UI markup: `templates/partials/gated-video.html`.
- Webinar variants live under `webinars/<slug>/index.html`.
- Legacy `webinar/` paths are deprecated wrappers and should not be used for new content.

## WordPress publishing requirements
Each webinar is published as a **WordPress Post** using the post body HTML managed in this repo.

Required taxonomy:
- Category: `Webinars` (required)
- Tags: optional region tags, for example `EMEA`, `APAC`, `NORAM`, `LATAM`

Post content contract:
- Paste the repo-managed iframe/custom HTML snippet into the post body.
- Keep the post title aligned with the webinar template title/config.
- Set a featured image for the webinar card.

## QA checklist before publish
- Confirm post is in category `Webinars`.
- Confirm optional region tag (if region-specific) is applied.
- Confirm embedded snippet renders correctly in WP preview.
- Confirm permalink opens and card data populates in `/webinars/` library.
- Confirm title and featured image display correctly in webinar cards.
