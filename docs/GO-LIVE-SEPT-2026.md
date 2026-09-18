# Going live with the four guide pages

Written September 18, 2026, from the state of the two sites on that date. Everything
below was measured, not assumed. Re-check before running it — staging and production
drift.

## What is on staging and not on production

| page | staging | production | what production needs |
|---|---|---|---|
| `/errnot/` | post 157, published, current | post 157, published, **old version** | a publish |
| `/how-to-select-a-tms/` | post 1519, published | **does not exist**, URL 404s | a post has to be created |
| `/tms-selection-mistakes/` | post 4799, published | **does not exist**, URL 404s | a post has to be created |
| footer Quick Links | pattern 398, has the link | pattern 398, no link | a write to 398 |
| nav label | snippet 1885 + `wpcode_snippets`, TMS SELECTION | still says **WEBINARS** | snippet + option write |
| `.rt-logo` / nav breakpoint | Additional CSS, fixed | not fixed | `wp_publish_shared_css.sh` |

Production's nav has never carried the SELECTION item at all. It is not that the label
changed — the whole item is new to production, and it points at a URL that 404s there
today. **Create the two posts before the nav goes live, or the nav ships a broken link.**

## The id trap, which the publisher now refuses

`wp/pages.tsv` holds one post id per row and ids are per site.

- production **1519** is the live `/2024-tms-selection-guide/` post. Staging 1519 is
  `how-to-select-a-tms`.
- production **4799** is a Flamingo spam record (`flamingo_inbound`). Staging 4799 is
  `tms-selection-mistakes`.

`publish how-to-select-a-tms --target production` would have overwritten a published
page. The pattern-ref guard cannot see it, because between two site pages the refs
match. `check_identity()` refuses on a post_name mismatch; column 5 declares the
expected name where the row label is not the slug.

So the order is: create the posts on production, read back their real ids, put those
ids in a production row, and only then publish.

## Order

1. **Create the two posts on production** in WP Admin (or `wp post create`), with the
   slugs `how-to-select-a-tms` and `tms-selection-mistakes`, status draft.
   Record the ids they get. They will not be 1519 and 4799.
2. **Point pages.tsv at the production ids.** One row per target, or swap the ids at
   publish time — do not edit the staging ids away, staging still uses them.
3. `scripts/wp_publish_post.py plan <slug> --target production`, read the diff, then
   `publish`. Both pages, then `errnot`.
4. **Publish the two posts** (WP Admin — the rail writes content and refuses a status
   move on purpose).
5. **Footer pattern 398.** Not in pages.tsv. Back up `post_content`, patch the one
   `<li>`, write with `wp_slash()`, diff the read-back. The staging copy has staging
   URLs; production needs `https://realtreasury.com/how-to-select-a-tms/`.
6. **`shared.css`** — `scripts/wp_publish_shared_css.sh plan` then `publish`. It refuses
   CSS that is not on `origin/main`, so this branch has to merge first. This carries the
   logo fix and the 1150px nav breakpoint.
7. **The nav**, last, once the links resolve. Two writes, both required — see
   `wpcode-snippet-cache-serves-not-post`: the `wpcode_snippets` **option** is what
   renders, post 1885 is only the source. Back both up first. `wpcode()->cache->
   delete_cache()` empties the option and does NOT rebuild it; the nav then disappears
   site-wide and restoring the backup is the only way back.
8. **Verify by fetching, not by trusting a green step.**
   `curl -s https://realtreasury.com/ | grep 'TMS SELECTION'`, and the two new URLs for
   a 200.

## Two things that will waste an hour if forgotten

- **The staging page cache.** Any query string busts it. Check a render with
  `?cb=$RANDOM` and a *fresh* value each time — reusing one cachebuster across a loop
  caches the first response and every later check reads the old page. That happened
  during this work and made a correct CSS fix look broken.
- **Media queries carry no specificity.** A `@media` block that overrides a plain rule
  has to come after it in the file.
