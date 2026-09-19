# Existing selection pages — SEO review, September 19, 2026

Task: rtai-64kf. The owner narrowed scope to these four existing pages and asked
us to retain their wording. No market-page expansion, vendor directory, new
articles, rankings, revised headlines or new explanatory copy is included.

| Page | Existing SEO title | Purpose |
| --- | --- | --- |
| `/how-to-select-a-tms/` | How to Select a Treasury Management System &#124; Real Treasury | Selection questions; main educational article |
| `/treasury-tech-selection-guide/` | 2026 Treasury Tech Selection Guide &#124; Real Treasury | Guide download; North American vendor coverage |
| `/tms-selection-mistakes/` | The 5 Most Common TMS Selection Failures &#124; Real Treasury | Selection errors and how to avoid them |
| `/errnot/` | The ERR NOT Method for TMS Selection &#124; Real Treasury | The six-step methodology |

## What is already working

Live checks found HTTP 200, index/follow, a self-referencing canonical, descriptive
Yoast title and description, one content H1, and native HTML text on each page.
The two articles have Article markup; the guide and how-to include FAQ markup.
Do not promise FAQ rich results for a commercial site.

Google URL Inspection reports all four as submitted and indexed with matching
Google/user canonical URLs. How-to and mistakes were crawled September 19 at
00:49:02 UTC. The latest reported crawls of the guide and ERR NOT were September 9,
before their current revisions; indexed does not mean the newest content has
already been processed. The old `/2024-tms-selection-guide/` correctly redirects
with HTTP 301 to the how-to article. Preserve it.

Search Console's June 19–September 16 final web data reported 115 impressions,
zero clicks, average position 5.86 for the exact query `treasury management
systems vendor selection guide`. That period predates the new articles and does
not measure their effect. These are property impressions, not search volume or
a fixed ranking. Query privacy filtering limits coverage.

## Changes in this PR

- Link existing phrases in the guide to ERR NOT, mistakes and the how-to article.
- Link existing phrases in mistakes and ERR NOT back to the how-to article.
- Remove competing embedded title/meta/canonical tags from the head of pages
  published in `verbatim` mode. Preserve Tailwind, font links, styles, body copy,
  SVG accessibility titles and the existing WordPress block wrapper.
- Retain all four existing SEO titles and descriptions: they already describe
  the pages accurately. The how-to article already links to the other three and
  needs no content change.

These pages directly address selection intent. Improving them supports relevant
searches but does not establish that they will rank first for broad best/top
vendor-list searches. No fabricated rankings or keyword-stuffed copy is needed.

## Publication after owner review and merge

Merging source alone does not update these WordPress pages. From the merged
checkout, inspect these read-only plans, then publish the three changed pages:

```bash
python3 scripts/wp_publish_post.py plan guide-download
python3 scripts/wp_publish_post.py plan tms-selection-mistakes-prod
python3 scripts/wp_publish_post.py plan errnot

python3 scripts/wp_publish_post.py publish guide-download
python3 scripts/wp_publish_post.py publish tms-selection-mistakes-prod
python3 scripts/wp_publish_post.py publish errnot
```

The production identities are 4202, 4832 and 157 respectively; the publisher
checks slugs, preserves pattern refs, backs up content and verifies read-back.
Do not use the staging mistakes row. No metadata, slug, status, access, form or
canonical setting needs to change for this patch.

After publication, verify the five new anchors in served HTML; compare readable
text to the prior versions; confirm HTTP status, canonical and robots directives;
and verify the ERR NOT source document's extra title is gone. The shared banner
still emits its own `Workshop Banner` title, so do not claim the entire page head
is clean. Check mobile and desktop rendering. Use the saved content backups and
the corresponding `restore` commands for rollback; revert source through a PR.

## Findings held for separate review

- Mistakes visibly credits Tracey Knight, but Yoast's Article author is Tim
  Schultz. The other WordPress account exposes a username as its public display
  name. Correct attribution after resolving the intended author profile; do not
  invent an author or change account details during this copy-preserving patch.
- Shared banner/related-post widgets emit embedded document titles. Homepage
  metadata duplication and the Jetpack/Yoast sitemap cleanup are site-wide work,
  outside the four-page scope. Multiple sitemaps alone are not a ranking penalty.
- Mobile Core Web Vitals and organic download/consultation conversions were not
  measured here. After publication, compare equal 28-day Search Console windows
  once enough data exists, focusing on nonbrand selection queries and these four
  landing pages. Track business conversions separately through existing analytics.

Guidance used: Google Search Central's title-link documentation,
`https://developers.google.com/search/docs/appearance/title-link`, and crawlable
links guidance, `https://developers.google.com/search/docs/crawling-indexing/links-crawlable`.
