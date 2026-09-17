# 2026 Tech Selection Guide — release runbook

State of play as of September 17, 2026. Every page state, RT Gate `target_url`
and WordPress-vs-repo diff below was re-read from the live site on that date
rather than carried over from the last write-up. The release is **on hold**:
Revision 8 landed on SharePoint on September 15 (one full guide plus three
segment cuts) and no file has been named as the release PDF. Everything below is staged so that
release is a short, ordered sequence rather than a build.

## Pages

| what | where | status |
|---|---|---|
| Download page (gated form) | WP **4202** `/treasury-tech-selection-guide/` | published, still serving the **waitlist confirmation** |
| Download page content | WP **4587** draft, slug `…-guide-download` | draft, matches the repo (the corrupted email check was repaired September 17) |
| Guide thank-you | WP **4585** `/treasury-tech-selection-guide/thank-you/` | draft, matches the repo |
| Waitlist signup | WP post **4211** `/2026-treasury-tech-guide-waitlist/` | published (GitHub Pages iframe) |
| Waitlist confirmation | WP **4809** `/2026-treasury-tech-guide-waitlist-confirmed/` | draft, matches the repo |

4587 is the staging draft, **not a destination**. `/treasury-tech-selection-guide-download/`
404s because the page has never been published, and it should never be published
at that URL — the download form's home is 4202.

Sources of record, all WordPress-native (a paste, not the GitHub Pages iframe
pipeline):

- `treasury-tech-selection/guidebook/wordpress-page.html`
- `treasury-tech-selection/guidebook/thank-you/wordpress-page.html`
- `treasury-tech-selection/waitlist/confirmed/wordpress-page.html`

The only expected differences between these files and their WordPress copies are
the repo header comment (replaced by a one-line source-of-record note) and the
`<!-- wp:html -->` block wrapper. `scripts/wp_publish_post.py` derives both (mode
`native`), so `wp_publish_post.py plan <slug>` compares exactly rather than squinting past
paste noise — which is the only reason the corruption below was visible at all.

Readback diff, September 17, after the repair: **4585 and 4587 read back identical
to their repo sources**, both still drafts. 4809 matches too. 4202 differs,
correctly — it serves the waitlist confirmation until release, which is the whole
reason 4809 exists.

## WordPress eats backslashes on the way in — `wp_slash()` or lose them

**Repaired in 4587 on September 17, and it will recur on every paste or scripted
write unless the write path handles it.** `scripts/wp_publish_post.py` is that
write path; use it rather than pasting.

`treasury-tech-selection/guidebook/wordpress-page.html` contains exactly one
backslash-bearing line, the client-side email check:

```js
if (f.type === 'email' && v2 && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v2)) { … }
```

What was in WordPress page 4587 from the September 16 write until it was
repaired on September 17:

```js
if (f.type === 'email' && v2 && !/^[^s@]+@[^s@]+.[^s@]+$/.test(v2)) { … }
```

Every backslash is gone, which silently changes `\s` (whitespace) into a literal
`s`. `[^\s@]+` ("anything but whitespace or @") becomes `[^s@]+` ("anything but
the letter s or @"), so the form **rejects any address whose local part contains
an "s"** — `tschultz@realtreasury.com` fails, `tknight@realtreasury.com` passes.
On a lead-generation page that is a large, invisible share of submissions, and
the visitor just sees their own email marked invalid.

Cause: `wp_insert_post()` / `wp_update_post()` expect **slashed** input and run
`wp_unslash()` over it. Content passed raw loses one level of backslashes. This
is not a Gutenberg paste quirk — a scripted `wp_update_post()` write reproduced
the corruption byte for byte on September 16.

The fix on a scripted write is `wp_slash()`:

```php
wp_update_post(array("ID" => 4202, "post_content" => wp_slash($content)), true);
```

KSES has to be lifted for that call or core strips the `<script>` blocks as
untrusted HTML — WP-CLI runs with no user. `scripts/wp_publish_post.py` does it
inside the one `wp eval-file` process rather than globally.

**Always read the content back and diff it against the source** — this failure is
silent, and the page keeps working for most testers. The script does that too, and
refuses the write if the read-back does not match.

Blast radius is limited to the three WordPress-native pages above, and only the
guidebook page has any backslashes at all. The gated pages on the GitHub Pages
iframe pipeline (`webinars/*`, `events/2026/afp/`, the waitlist signup) are served
as static files and are verified intact.

## Order of operations

The ordering matters because page 4202 changes meaning.

1. **Publish the waitlist confirmation page 4809.** It is a draft today, so
   `/2026-treasury-tech-guide-waitlist-confirmed/` 404s — verified September 17.
   This has to land *before* step 2, not with it: repointing the gate at a draft
   page sends every waitlist signup to a 404 for as long as the gap lasts.
   Publishing 4809 on its own changes nothing for visitors, because nothing links
   to it yet.
2. **Repoint the waitlist redirect.** RT Gate asset #9
   (`treasury-tech-selection-guide-waitlist`, mapping #5) has `target_url` set to
   `https://realtreasury.com/treasury-tech-selection-guide/` — verified still true
   on September 17. Change it to
   `https://realtreasury.com/2026-treasury-tech-guide-waitlist-confirmed/`.
   Until this is done, turning 4202 into the download page drops waitlist signups
   onto the download form. WP Admin only — the waitlist page carries no
   `redirectUrl` and follows the `/submit` response's `primary_redirect_url`.
   Verify by submitting the waitlist form once and watching where it lands.
3. **Upload the release PDF** under an unguessable filename, linked from no page.
   Email verification is the gate; the file itself is public.
4. **Set RT Gate asset #12's `target_url`** (`treasury-tech-selection-guidebook`,
   mapping #8) to that PDF. It currently points at the unrelated bank CRE exposure
   report. Mapping #8's `lead_email_mode` is already `none` — verified, nothing to
   do there.
5. **Fix the two Resend placeholders.** Published template `guide-delivery` still
   points its hero link and its "Download the guide" button at that same bank CRE
   PDF, with a visible orange `[PLACEHOLDER …]` line under the button. Swap both
   for the real URL and delete the warning line. Do the same in draft broadcast
   `ae14794e-68ff-459c-bcc4-f18cb079b6fe`.
6. **Publish the download page.** `scripts/wp_publish_post.py publish
   guide-download` puts `treasury-tech-selection/guidebook/wordpress-page.html`
   into **4202**, then publish 4585 and 4202 in WP Admin. Despite the name the
   script writes `post_content` only and refuses if `post_status` moved, so it
   cannot publish anything itself — that stays a deliberate act.
7. **Enable automation** "Tech Selection Guide — deliver on signup"
   (`01a067af-c041-7579-a1f3-ad0f042f25fe`, currently disabled) and send the
   broadcast.

## Resend: publish the template, or you ship the old one

Saving a Resend template leaves a **draft**; the automation sends the last
**published** version, and the API's `get-template` returns the published version
too — so a saved-but-unpublished fix looks correct everywhere you would think to
check, and still goes out wrong.

This bit us on September 16: `guide-waitlist-confirmation` had the new cover saved
but not published, so a 17:55 UTC test signup delivered the old light cover. Tim
clicked Publish at 17:58 UTC and the next send was correct. **Check
`guide-delivery` the same way before release** — its published version still
carries the placeholder PDF, and there may be a draft above it.

## Cover art: `-new.png` is the current one

Settled September 16. Two files are in the media library and they are easy to
confuse:

| file | uploaded | what it is |
|---|---|---|
| `2026/09/2026-Real-Treasury-Tech-Selection-Guide-new.png` (4736) | Sep 12 | **current** — dark purple cover, new chart wheel, "Success Starts with Selection." |
| `2026/09/2026-Real-Treasury-Tech-Selection-Guide.png` (4636) | Sep 6 | superseded — light/white cover, old chart layout |

Both templates now reference `-new.png`. **Their `alt` text is still the old
cover's tagline** ("an unbiased guide to help you select your TMS with
confidence") and no longer describes the image; the new cover reads "Success
Starts with Selection." Worth correcting when the templates are next edited —
it is what screen readers and image-blocked clients render.

## Safe today, because

- The delivery automation is **disabled**, so the wrong-PDF placeholder in
  `guide-delivery` cannot reach anyone.
- The release broadcast is a **draft**.
- The waitlist confirmation email links only to `/treasury-tech-market/`, never to
  4202, so the waitlist keeps working correctly no matter when 4202 changes.
- 4202 has not been touched; it still serves the waitlist confirmation, so the
  waitlist works today exactly as it always has. 4809 is not a fix for anything
  live — it is only where the waitlist lands *after* 4202 becomes the download
  page.
