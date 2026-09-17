# 2026 Tech Selection Guide — release runbook

State of play as of September 17, 2026. Every page state, RT Gate `target_url`
and WordPress-vs-repo diff below was re-read from the live site on that date
rather than carried over from the last write-up.

**The release PDF is named, uploaded and wired.** Tim named Revision 9 on
September 17 — `E-Guide_Real Treasury_AV_rev09 with links.pdf`, 42 pages,
20,437,382 bytes, in file-repository under
`Partnership with Ernie/Guidebook DRAFTS/Revision 9`. It is **one PDF**. An
earlier draft of this runbook said Revision 8 shipped "three segment cuts"
(Cash Tools / TMS-Lite / TRMS) — that was wrong, and there are no segment cuts
to gate. Steps 3, 4 and 5 below are **done**; what remains is steps 1, 2, 6, 7,
all of which are deliberate human acts in WP Admin and Resend.

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

**What is left is exactly the set of acts a person has to perform:** publishing a
WordPress page, publishing a Resend template, and arming a send. Claude is blocked
from all three by the auto-mode classifier, deliberately, and by the house rule that
a person publishes. Everything that could be staged ahead of them has been.

**To see and test the download form before any of this, no publishing required:**
WP Admin → Pages → *The Real Treasury Tech Selection Guide* (4587) → **Preview**.
The form renders and submits for real against RT Gate — it writes a lead and creates
a Resend contact in the Downloads segment. The only thing that misbehaves is the
final redirect, which 404s until 4585 is published. No email arrives until step 7.

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
3. ~~**Upload the release PDF**~~ **DONE September 17, 2026.** Revision 9 is in the
   media library as attachment **4821**, under an unguessable filename linked from
   no page, and serves 200 `application/pdf`:
   `https://realtreasury.com/wp-content/uploads/2026/09/Real-Treasury-Technology-Selection-Guide-North-America-2026-3e2603e8f094.pdf`
   Email verification is the gate; the file itself is public and that is intended.
4. ~~**Set RT Gate asset #12's `target_url`**~~ **DONE September 17, 2026.** Asset #12
   (`treasury-tech-selection-guidebook`, mapping #8) now carries the URL above;
   written with a read-back check, previous value backed up. Mapping #8's
   `lead_email_mode` was already `none`.
5. ~~**Fix the two Resend placeholders**~~ **DONE September 17, 2026, with one thing
   left to click.** Template `guide-delivery` and draft broadcast
   `ae14794e-68ff-459c-bcc4-f18cb079b6fe` both carry the real PDF URL in every
   position (hero link, button, and the broadcast's Outlook VML button), the orange
   placeholder paragraphs are gone, and the stale `alt` text was corrected to the
   `-new.png` cover's line ("Success Starts with Selection"). The broadcast was also
   renamed from "Untitled" to "Guide waitlist — release".
   **The template edit is a SAVED DRAFT — someone must click Publish in Resend.**
   Until then the published version still carries the bank CRE placeholder. See the
   section below; this is exactly the trap that bit us on September 16.
6. **Publish the download page.** Two parts, in this order:
   a. **Publish the Resend template.** `guide-delivery` has the correct content
      saved as a draft; the published version still carries the bank CRE
      placeholder. Resend → Templates → *Tech Selection Guide — delivery* →
      **Publish**. Do this before step 7 or the first delivery goes out wrong,
      which is precisely what happened on September 16.
   b. **Write the form into 4202 and publish.** From a checkout of
      `feat/native-page-publish`:
      `python3 scripts/wp_publish_post.py publish guide-download --target production`
      — that puts `treasury-tech-selection/guidebook/wordpress-page.html` into
      **4202**. Then publish **4585** in WP Admin. The script writes `post_content`
      only and refuses if `post_status` moved, so it cannot publish anything itself.
      4202 is already published, so writing its content IS the cutover: the moment
      it lands, `/treasury-tech-selection-guide/` stops being the waitlist
      confirmation and becomes the download form. Steps 1 and 2 must already be done.
7. **Enable automation** "Tech Selection Guide — deliver on signup"
   (`01a067af-c041-7579-a1f3-ad0f042f25fe`, currently disabled; wiring re-verified
   September 17 and correct) and send the broadcast. This is the only step that
   causes mail to leave.

## How a download actually reaches someone

The form on 4202 never shows the PDF URL. The chain is:

1. Visitor submits the form on `/treasury-tech-selection-guide/`.
2. rt-gate `/submit` records the lead against asset
   `treasury-tech-selection-guidebook` (mapping #8) and, because that mapping
   carries `resend_segment_id`, creates a Resend contact in the **Downloads**
   segment `22b33094-35ee-4779-9809-7bdb7c6bb0a9` and fires `signup.created`.
3. The page ignores `/submit`'s `primary_redirect_url` on purpose — honouring it
   would hand the PDF to anyone who typed any address — and sends the visitor to
   `/treasury-tech-selection-guide/thank-you/` instead.
4. The Resend automation matches `event.segment_id` against that segment and sends
   template `guide-delivery`, which is the only place the real PDF URL appears.

So the email address is the gate, and every link in the chain is now wired except
the two switches a person throws: publish the template, enable the automation.

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

- The delivery automation is **disabled** (re-verified September 17), so nothing
  in `guide-delivery` reaches anyone either way. Its wiring is correct: it triggers
  on `signup.created`, branches on `event.segment_id == 22b33094-…` (the Downloads
  segment), and sends template `da8cbac1-…`.
- The release broadcast is a **draft**.
- The waitlist confirmation email links only to `/treasury-tech-market/`, never to
  4202, so the waitlist keeps working correctly no matter when 4202 changes.
- 4202 has not been touched; it still serves the waitlist confirmation, so the
  waitlist works today exactly as it always has. 4809 is not a fix for anything
  live — it is only where the waitlist lands *after* 4202 becomes the download
  page.
