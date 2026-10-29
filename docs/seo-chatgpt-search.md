# ChatGPT Search and AI crawlers: robots.txt, llms.txt, JSON-LD

Task: rtai-etbi. State checked live October 29, 2026. Nothing here changes visible page copy.

## How the files reach the site

`/robots.txt` and `/llms.txt` are physical files in the site's document root on
WordPress.com (written September 19, 2026). The web server serves them directly, so they
shadow Yoast's virtual robots.txt and Yoast cannot regenerate llms.txt (`enable_llms_txt`
is off). **Merging to `main` does not publish them.** `wp/deploy.tsv` only covers
WordPress posts and pages, so there is no deploy-on-merge leg for these two files.

`robots.txt` and `llms.txt` at the root of this repo are the reviewable copies of what is
live, byte for byte as of October 29, 2026. To change either: edit it here, merge the PR,
then the owner copies the file to the document root over the WordPress.com SSH rail and
purges the edge cache for that URL (static files carry `max-age=14400`, so without the
purge the old copy keeps serving). Do not edit the live file first; the repo copy must
stay equal to it. The box also keeps a working copy at `~/rt-ai-seo/` (the earlier
source of record); after this PR the repo copy is the one to edit.

Rollback: the previous files are backed up on the box
(`~/wp-post-backups/production-{robots,llms}.txt-20260919T124257Z.bak`) and next to the
live files on the server; or copy the prior version of the repo file from git history.

## What is live (October 29, 2026)

| Check | Result |
|---|---|
| `GET /robots.txt` | 200. Names OAI-SearchBot (also ChatGPT-User, Claude, Perplexity and others) in an explicit `Allow: /` group, with the on-site search and REST API paths disallowed. One sitemap line, `https://realtreasury.com/sitemap_index.xml`. |
| GPTBot | **Allowed**, in the same group. The July 29 task text assumed GPTBot stays disallowed. It does not; the September 19 file allows it. See "Open decision" below. |
| `GET /llms.txt` | 200, 25 unique URLs (24 pages plus the sitemap index). Every one returned 200 with no redirect. No 2024 CRE entries. |
| JSON-LD on `/`, `/about/`, `/contact/`, `/team/` | Yoast's graph only: WebPage, ImageObject, BreadcrumbList, WebSite, Organization. No ProfessionalService, no Person nodes. |
| PR #875 (JSON-LD) | Merged October 3, but it only changed `assets/php/functions.php`, which reaches the site only with the theme deploy (`wp_theme_deploy.py`, which is not in this repo and is not one of the publish commands in AGENTS.md; this doc does not record where it lives). Not live. |
| Sitemap | `sitemap_index.xml` 200, lists post, page, category and tag sitemaps. |
| Page robots meta | Homepage: `index, follow`. |
| Request with the OAI-SearchBot user-agent string from our box | 403 from Cloudflare (a plain `curl` gets 200). Most likely Cloudflare refusing a spoofed verified-bot name from a non-OpenAI IP, as noted July 29, so it does not prove OpenAI's crawler is blocked. It is not proof it is allowed either. See "Open checks". |

### JSON-LD that will go live with the theme deploy

The #875 code adds `ProfessionalService` to Yoast's existing Organization node
(`legalName`, `description`, `founder`, one Service for TMS selection with the ERR NOT
method) and appends two Person nodes (Tim Schultz, Tracey Knight, title as shown on
`/team/`). Each fact was re-checked against the public site on October 29, 2026: the
legal name in the footer, the title on `/team/`, the description is `/team/`'s meta
description, the service name and method are on `/errnot/`. No client or vendor names,
no founding year, address, phone, email, biography or profile links. After the theme
deploy, check the homepage with validator.schema.org for Organization plus
ProfessionalService and the two Person nodes.

## Open decision: GPTBot

OpenAI documents two independent crawlers. OAI-SearchBot decides appearance in ChatGPT
Search answers. GPTBot is for model training and does not affect search eligibility. The
live file allows both. If the owner wants training use refused while keeping search, the
change is one edit: take `User-agent: GPTBot` out of the Allow group and give it its own
`Disallow: /` group. Not changed here, because it is a policy call and the file was written
this way on purpose.

## Open checks (need the Cloudflare console, read-only)

1. Cloudflare dashboard, Security, Events: filter user agent contains `OAI-SearchBot` over
   the last 30 days. Confirm real OpenAI traffic is allowed, not challenged or blocked.
   OpenAI publishes its crawler IP ranges at https://openai.com/searchbot.json; verified-bot
   handling should pass those.
2. Weekly drift check: `~/rt-ai-seo/seo_check.py` exists on the box but was never added to
   the crontab (September 23 audit). If wanted, the line is
   `20 7 * * 1 /usr/bin/python3 /home/tschultz/rt-ai-seo/seo_check.py >> /srv/ai-data/imports/firebase/seo-check.log 2>&1`.

## llms.txt maintenance

- List only pages that exist. Before each edit, `curl -s -o /dev/null -w '%{http_code}'`
  every URL; all must be 200 with no redirect.
- The AFP 2026 tour entry (under Optional) describes an event on November 9 to 10, 2026.
  Remove it, or repoint it, once the event is over.
- No client or vendor names, no vendor counts, nothing that says or implies Real Treasury
  implements systems. Run the publish-check rules on any new description.

## Zero-cost measurement baseline

No paid tooling and no change to analytics collection. Three free sources:

1. **GA4, referral source.** ChatGPT Search appends `utm_source=chatgpt.com` to the links
   it cites, so visits show as session source `chatgpt.com` in GA4 (Reports, Acquisition,
   Traffic acquisition, add the Session source dimension). Baseline read through the box's
   read-only GA4 report tool on October 29, 2026, property total sessions all sources:

   | Window | Total sessions | Sessions with source `chatgpt.com` |
   |---|---|---|
   | Last 30 days (to October 28) | 82 | 0 |
   | Last 90 days (to October 28) | 2,004 | 3 (2 users) |

   No other AI assistant source (perplexity, claude, gemini, copilot, openai) appears in the
   90-day window. GA4 here is also affected by automated traffic and blocked analytics, so
   treat small counts as a floor, not a measurement.
2. **Cloudflare analytics and Security events** (free tier): requests by user agent for
   OAI-SearchBot and ChatGPT-User show whether OpenAI is fetching pages and whether any are
   blocked. This is the only direct evidence of crawling; GA4 only sees clicks.
3. **Manual citation check, monthly.** In ChatGPT with search on, ask five fixed questions a
   treasury team would ask ("how do I select a TMS", "what is an RFP trap for treasury
   software", and similar). Record on a dated list: whether realtreasury.com is cited, which
   URL, and the position. Keep the questions the same each month. This is manual and small
   on purpose; it shows a trend, not a guarantee. OpenAI makes no promise that any page is
   cited.

Re-run the GA4 read and the manual check one month after the theme deploy and compare to
the table above.
