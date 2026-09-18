# Going live with the four guide pages

Written September 18, 2026, from the state of the two sites on that date. Everything
below was measured, not assumed. Re-check before running it — staging and production
drift.

## What is on staging and not on production

| page | staging | production | what production needs |
|---|---|---|---|
| `/errnot/` | post 157, published, current | post 157, published, **old version** | a publish |
| `/how-to-select-a-tms/` | post 1519, published | URL 404s; post **1519 is the old 2024 guide** | a publish into 1519, then a slug move |
| `/tms-selection-mistakes/` | post 4799, published | **does not exist**, URL 404s | a post has to be created |
| footer Quick Links | pattern 398, has the link | pattern 398, no link | a write to 398 |
| nav label | snippet 1885 + `wpcode_snippets`, TMS SELECTION | still says **WEBINARS** | snippet + option write |
| `.rt-logo` / nav breakpoint | Additional CSS, fixed | not fixed | `wp_publish_shared_css.sh` |

Production's nav has never carried the SELECTION item at all. It is not that the label
changed — the whole item is new to production, and it points at a URL that 404s there
today. **Both article URLs have to resolve before the nav goes live, or the nav ships a
broken link.**

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

So the order is: for the mistakes post, create it on production, read back its real id,
put that id in a production row, and only then publish. For 1519 there is nothing to
create — the row declares the name the post still carries, so the guard passes, and the
slug moves after the content lands.

## Order

0. **Do NOT create a new post for `how-to-select-a-tms`.** An earlier draft of this
   runbook said to. It is wrong, and it would throw away the only search equity these
   two articles have. Production **1519** is the live `/2024-tms-selection-guide/` post,
   869 GSC impressions at position 23.4. The decision on record is to rebuild 1519 in
   place and move its slug; WP writes `_wp_old_slug` and 301s the old URL. A second post
   would leave the old iframe page live beside the new one, competing with it.
   Only `tms-selection-mistakes` is genuinely new to production.

1. **Create the mistakes post on production**, status draft:

   ```
   wp post create --post_type=post --post_status=draft --post_author=240264699 \
     --post_title='The 5 Most Common Selection Failures and How to Avoid Them' \
     --post_name=tms-selection-mistakes --post_category=10096769 \
     --tags_input='Cash Forecasting,Cash Visibility,ERP Connectivity,Explainer,Liquidity Management,TMS,Vendor Selection' \
     --porcelain
   ```

   Author and terms read live off staging 4799 on September 18, 2026.
   Put the id it returns into the `tms-selection-mistakes-prod` row in `wp/pages.tsv`
   and uncomment it. The `*-prod` rows are already written; do not edit the staging
   ids away, staging still uses them.

2. **Publish 1519's content before its slug moves.** The `how-to-select-a-tms-prod`
   row declares column 5 as `2024-tms-selection-guide` on purpose, so `check_identity()`
   passes while the post still carries the old name:

   ```
   scripts/wp_publish_post.py plan    how-to-select-a-tms-prod --target production
   scripts/wp_publish_post.py publish how-to-select-a-tms-prod --target production
   ```

   Then move the slug and the title:

   ```
   wp post update 1519 --post_name=how-to-select-a-tms \
     --post_title='How to Select a Treasury Management System'
   ```

   Then set column 5 of that row to `how-to-select-a-tms` and commit.
   Content first, rename second: the old URL never serves the new slug with old content,
   and the new URL never appears before the article does. Check the 301 afterwards —
   `curl -sI https://realtreasury.com/2024-tms-selection-guide/`.

3. `plan` then `publish` `tms-selection-mistakes-prod`, then `errnot`, then
   `guide-download` (post 4202, the landing page — it is live and published, so that
   copy goes public the moment it is written).

4. **Publish the mistakes post** (WP Admin — the rail writes content and refuses a
   status move on purpose).
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
