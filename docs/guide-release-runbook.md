# 2026 Tech Selection Guide — release runbook

State of play as of September 19, 2026. Every page state, RT Gate `target_url`
and WordPress-vs-repo diff below was re-read from the live site on that date
rather than carried over from the last write-up.

**The release PDF is named, uploaded and wired.** Tim named Revision 9 on
September 17 — `E-Guide_Real Treasury_AV_rev09 with links.pdf`, 42 pages,
20,437,382 bytes, in file-repository under
`Partnership with Ernie/Guidebook DRAFTS/Revision 9`. It is **one PDF**. An
earlier draft of this runbook said Revision 8 shipped "three segment cuts"
(Cash Tools / TMS-Lite / TRMS) — that was wrong, and there are no segment cuts
to gate. **Re-read against production on September 19: steps 1, 2, 3, 4, 5, 6a,
6b and the automation half of 7 are all done.** 4585, the thank-you page, was
still a draft on September 19 but was published by September 22 (the URL
returns 200). `guide-thank-you` is now in `wp/deploy.tsv`, so the merge of this
branch writes its thank-you source to 4585 in the same deploy run as 4202; verify
the readback with `python3 scripts/wp_publish_post.py plan guide-thank-you`. The release broadcast remains optional. The
per-step notes below carry the evidence.

## Pages

| what | where | status |
|---|---|---|
| Download page (gated form) | WP **4202** `/treasury-tech-selection-guide/` | **published, and it is now the download page** — `plan guide-download` reads back 1035 words identical to the repo source |
| Download page content | WP **4587** draft, slug `…-guide-download` | draft, and **no longer matches the repo** — 242 changed lines against the source, because 4202 moved on and this staging copy did not |
| Guide thank-you | WP **4585** `/treasury-tech-selection-guide/thank-you/` | **published** by September 22, 2026: the URL returns 200 to an anonymous `curl` (a draft returns 404). It was a draft on September 19; the callout below records that state |
| Waitlist signup | WP post **4211** `/2026-treasury-tech-guide-waitlist/` | published (GitHub Pages iframe) |
| Waitlist confirmation | WP **4809** `/2026-treasury-tech-guide-waitlist-confirmed/` | **published**, matches the repo |

> **Re-read against production on September 19, 2026** with
> `scripts/wp_publish_post.py plan <slug>` (it reports `post_status`), a plain
> `curl` of each URL, the Resend API for the template, automation and broadcast,
> and WP-CLI over the same SSH rail `wp_publish_post.py` uses for post status and
> the RT Gate tables. **The release has largely already happened.** The
> September 17 state this table used to record — 4202 still serving the waitlist
> confirmation, 4809 a draft — is no longer true. Steps 1, 2, 5, 6a, 6b and the
> automation half of step 7 are done.
>
> **Superseded September 22, 2026: 4585 is now published and returns 200.** The
> rest of this paragraph records the September 19 state.
>
> **On September 19 one thing was outstanding, and it was live and broken: 4585,
> the guide thank-you page, was still a draft.** `/treasury-tech-selection-guide/thank-you/`
> returns 404, while 4202 is live as the download form and the delivery
> automation is enabled. The live page carries
> `redirectUrl: 'https://realtreasury.com/treasury-tech-selection-guide/thank-you/'`,
> so every visitor who submits the form right now is sent to a 404. The lead and
> the delivery email are unaffected (see "How a download actually reaches
> someone") — the visitor is simply told nothing. After this branch merges,
> write the updated source to 4585 with `wp_publish_post.py`, verify the readback,
> and then publish 4585 in WP Admin. The status change is the last step that
> needs a person.

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
to the repo sources at that commit**, both still drafts. This branch now changes
the 4585 button rule, so repeat the guarded publish/readback after merge before
changing its status. 4809 matches too. 4202 has since become the live download
page, as recorded above.

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

**What is left:** after this branch merges, use the guarded publisher to update
4585 while it is still a draft, verify that the source and WordPress read back
identically, and then have a person publish the page in WP Admin. The broadcast
can be sent later when desired.

**To see and test the download form before any of this, no publishing required:**
WP Admin → Pages → *The Real Treasury Tech Selection Guide* (4587) → **Preview**.
The form renders and submits for real against RT Gate — it writes a lead and creates
a Resend contact in the Downloads segment. The only thing that misbehaves is the
final redirect, which 404s until 4585 is published. No email arrives until step 7.

1. ~~**Publish the waitlist confirmation page 4809.**~~ **DONE.** The page is
   published and `/2026-treasury-tech-guide-waitlist-confirmed/` returns 200;
   `plan guide-waitlist-confirmed` reports `post_status publish` and identical
   content. It had to land before step 2, and it did.
2. ~~**Repoint the waitlist redirect.**~~ **DONE, verified September 19.** RT Gate
   asset #9 (`treasury-tech-selection-guide-waitlist`, mapping #5) now carries
   `target_url = https://realtreasury.com/2026-treasury-tech-guide-waitlist-confirmed/`.
   Waitlist signups land on the confirmation page, not the download form. This
   had to be true before 4202 changed meaning, and it was.

   **Where to read it, because guessing cost a false alarm.** `target_url` is a
   key inside the asset's JSON `config` column — `wp_rtg_assets` holds only
   `id, name, slug, type, config`, with no `target_url` column of its own. A
   query naming that column returns an empty result, which reads like "unset"
   rather than like a bad query. Select `config` for the asset id over the same
   SSH + WP-CLI rail `scripts/wp_publish_post.py` uses. The waitlist page itself
   carries no `redirectUrl`; it follows the `/submit` response's
   `primary_redirect_url`, which is where this value ends up.
3. ~~**Upload the release PDF**~~ **DONE September 17, 2026.** Revision 9 is in the
   media library as attachment **4821**, under an unguessable filename linked from
   no page, and serves 200 `application/pdf`:
   `https://realtreasury.com/wp-content/uploads/2026/09/Real-Treasury-Technology-Selection-Guide-North-America-2026-3e2603e8f094.pdf`
   Email verification is the gate; the file itself is public and that is intended.
   **Superseded; this URL returns 404 since September 23, 2026.** See "The PDF URL
   of record" below.

   **The PDF URL of record (September 23, 2026).** Link to the stable name
   `https://realtreasury.com/wp-content/uploads/2026/09/2026-Real-Treasury-Tech-Selection-Guide.pdf`,
   never to a hashed upload name. A Cloudflare redirect rule 302s it to the current
   edition's file, today
   `https://realtreasury.com/wp-content/uploads/2026/09/2026-Real-Treasury-Tech-Selection-Guide-20260923.pdf`
   (200, `application/pdf`, 14,755,690 bytes), which is what the published Resend
   `guide-delivery` template links to (published September 23, 12:45 UTC). It is a
   302 so a later edition can repoint it without editing the email template. The
   September 17 files have moved on: the 4821 URL above returns 404, and the 4828
   file (`…-a16b248f6bbc.pdf`) still returns 200 but nothing links to it. RT Gate
   asset #12's `target_url` was not re-read on September 23; the download form
   ignores `primary_redirect_url`, so it does not decide what a reader gets.
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
   ~~**The template edit is a SAVED DRAFT — someone must click Publish in Resend.**~~
   **Published, verified September 19** (`list-templates` reports
   `guide-delivery` as `published`). It was a saved draft when this was written,
   and until it was published the live version carried the bank CRE placeholder.
   See the section below; this is exactly the trap that bit us on September 16.
6. **Publish the download page.** Two parts, in this order:
   a. ~~**Publish the Resend template.**~~ **DONE.** Template
      `guide-delivery` (`da8cbac1-…`) reads `published` from the Resend API on
      September 19. It had to precede step 7 or the first delivery would go out
      carrying the bank CRE placeholder, which is precisely what happened on
      September 16.
   b. **Write the form into 4202 and publish.** *The 4202 half is DONE* —
      `plan guide-download` reads back 1035 words identical to
      `treasury-tech-selection/guidebook/wordpress-page.html`, so the cutover has
      landed and `/treasury-tech-selection-guide/` is the download form.
      `python3 scripts/wp_publish_post.py publish guide-download --target production`
      is the command that does it; the script is on `main` now, no special
      checkout needed. It writes `post_content` only and refuses if `post_status`
      moved, so it cannot publish anything itself.
      **Still outstanding:** after this branch merges, run
      `python3 scripts/wp_publish_post.py publish guide-thank-you --target production`.
      It updates `post_content` while preserving the draft status and verifies
      the readback. Only then publish 4585 in WP Admin. Until that status change,
      the form's post-submit redirect 404s for every real visitor.
7. **Enable automation and send the broadcast.** *The automation half is DONE* —
   "Tech Selection Guide — deliver on signup"
   (`01a067af-c041-7579-a1f3-ad0f042f25fe`) reads `enabled` from the Resend API
   on September 19; wiring was re-verified September 17 and is correct. **Still
   outstanding: the broadcast.** "2026 Guide Release — Waitlist"
   (`ae14794e-…`) is still a `draft`. Sending it is the only remaining act that
   pushes mail out on purpose — but note that with the automation already on,
   delivery mail is *already* leaving for anyone who submits the form.

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

So the email address is the gate, and every link in the chain is wired. Both
switches this paragraph used to wait on — publish the template, enable the
automation — were thrown before September 19 and verified that day, so the
chain is live end to end.

## Counting the funnel

**Superseded September 26, 2026 (this branch, `feat/rt-track-helper`):** the
guide lead is counted once per submit, at the download form when it can be and
on the thank-you page when it cannot:

- 4202 (the form) calls `window.RTGLeadEvents.trackLead(...)` on a successful RT
  Gate submit (see the rt-gate plugin repo's docs/11-GATED-PAGE-CONFIG.md, "Lead
  events and session source"). `trackLead` returns `true` only when it handed the
  event to the theme's `window.rtTrack`; then the form redirects to
  `/treasury-tech-selection-guide/thank-you/#rt-lead-counted`.
- 4585 (the thank-you page) keeps the PR #919 on-load `generate_lead`
  (`form_name=tech-selection-guide`) as the fallback and skips it when the URL
  carries `#rt-lead-counted` (and, as before, on a reload or back/forward visit).

`scripts/tests/test_guide_ga_events.js` runs both pages' scripts and pins that
each combination counts exactly one `generate_lead`.

**Release order does not matter.** rt-gate #91 (`rtg-lead-events.js`, which
defines `window.RTGLeadEvents`), this merge (4202 and 4585), and the hand deploy of
the theme's `rt_track_helper` (`assets/php/functions.php`) can land in any order:

| live | where the lead counts |
|---|---|
| none of them (today) | 4585, fallback event |
| 4202 + 4585 only, or plus rt-gate #91 only | 4585, fallback event (no `rtTrack`, so `trackLead` returns `false` and no fragment) |
| 4202 + 4585 + rt-gate #91 + theme | 4202, `form_name=rtg-form-<id>`; 4585 fires nothing |
| old 4202 + new 4585 (a run that stopped after 4585) | 4585, fallback event |

`guide-thank-you` sits above `guide-download` in `wp/deploy.tsv` because the deploy
leg publishes in manifest order and stops at the first failure, so it can never
leave new 4202 with the old 4585 (the only combination that would count twice).
Until all three are live the `form_name` in GA4 stays `tech-selection-guide`; once
they are, it becomes `rtg-form-<id>`. Filter the Key events report on either.
The measurement numbers and the "why the thank-you page" reasoning below are the
September 22 state and are kept for history; re-read the funnel against GA4 once
the new event has had a few days of traffic.

Checked September 22, 2026 against GA4 property 446224629 (Data API, 30 days): the
download page had 129 views and 26 `form_start` events, the thank-you page 25
views, and **zero** `form_submit`, `generate_lead` or `file_download` events for the
guide. The 25 thank-you views are real page loads, not 404s: 4585 returned 200 on
September 22. The form is built in JavaScript and submits with `fetch()`, so GA4's
enhanced-measurement `form_submit` never fires. The property already treats
`generate_lead` as a key event and already has the event-scoped custom dimensions
`form_name` ("Lead Form Name") and `lead_page` ("Lead Conversion Page"), which the
/contact/ form uses. The guide now reuses both, so it appears in the same reports.

Three counters, one per step:

| step | where it is counted | event | how to read it |
|---|---|---|---|
| page visited | GA4, `/treasury-tech-selection-guide/` | `page_view` | Reports > Engagement > Pages and screens, filter the path |
| form completed | GA4/rtTrack, `/treasury-tech-selection-guide/` (4202), fired at submit success; fallback on the thank-you page (4585) when the form could not | `generate_lead` with `form_name=rtg-form-<id>`, `asset=treasury-tech-selection-guidebook` (form), or `form_name=tech-selection-guide` (fallback) | Reports > Engagement > Events, or the Key events report |
| PDF downloaded | Resend, the `guide-delivery` email | click on the download button | Resend dashboard > Emails (or the automation's runs), per-email `clicked` status |

Historical note (superseded above): the thank-you page used to be the only
place that fired `generate_lead` on load, reasoning that nothing links to it except the
redirect after a successful rt-gate submit, so one load equaled one
completed form. That reasoning still held for the thank-you page in
isolation; it broke once the form page *also* started firing its own lead
event on the same submit. Firing from the form page itself was avoided
originally because that meant touching 4202, whose live copy was still
Tim's `content/guide-form-layout` layout rather than main's — moot now that
the event lives in the lead-events helper's `trackLead` call in the page's
own submit handler, not in a separate inline script.

**Downloads are not a GA4 event.** The delivery email links straight at the PDF,
and a file fetch runs no JavaScript, so GA4 cannot see it. An interstitial
`/download/` page that fired `file_download` was considered and dropped
(September 23, 2026): it added a page to maintain and a guessable URL that hands
out the PDF with no email step. The click on the email's download button is the
download count, and Resend records it, but only with click tracking on.
**Click tracking is off** on `news.realtreasury.com` (read from the Resend API,
September 23, 2026), so today downloads are counted nowhere.

Steps to turn it on, in order (the rail is read-only from an agent seat; a
person runs the writes):

1. `scripts/wp_publish_post.py publish guide-thank-you --target production`
   (live 4585 was identical to main on September 22, so this is additive). This
   writes content only, never `post_status`: confirm `plan guide-thank-you`
   reports `publish`. If it reports `draft`, run
   `wp post update 4585 --post_status=publish`, or `generate_lead` never fires.
2. Decide on Resend click tracking for `news.realtreasury.com` (Resend dashboard >
   Domains > the domain > Configuration). It is **domain-wide**: it also rewrites
   the links in the newsletter track's emails through Resend's tracking redirect.
   Leave the `guide-delivery` template as it is; its link to the stable PDF name
   does not change.

Scanners (mail-security link checks, sales-tool prefetchers) fetch email links
too, so Resend clicks overcount real readers the same way they inflate page views;
read them next to `ga_traffic_quality`. Delivery emails sent before click
tracking is turned on carry untracked links and are never counted.

## Resend: publish the template, or you ship the old one

Saving a Resend template leaves a **draft**; the automation sends the last
**published** version, and the API's `get-template` returns the published version
too — so a saved-but-unpublished fix looks correct everywhere you would think to
check, and still goes out wrong.

This bit us on September 16: `guide-waitlist-confirmation` had the new cover saved
but not published, so a 17:55 UTC test signup delivered the old light cover. Tim
clicked Publish at 17:58 UTC and the next send was correct. **`guide-delivery`
was checked the same way and is `published` as of September 19**, so the
placeholder-PDF version is no longer the one that sends. The trap is still
worth knowing: it is the reason to re-check any template you edit from here on,
not a step still outstanding.

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

> **Superseded on September 19, 2026.** This section described the state before
> the cutover. It is kept for the record; the corrections are inline.

- ~~The delivery automation is **disabled**~~ — **it is enabled now.** Mail from
  `guide-delivery` reaches anyone who submits the form on 4202. Its wiring is
  correct and unchanged: it triggers on `signup.created`, branches on
  `event.segment_id == 22b33094-…` (the Downloads segment), and sends template
  `da8cbac1-…`, which is published.
- The release broadcast is a **draft**. *(Still true — `ae14794e-…`, verified
  September 19.)*
- The waitlist confirmation email links only to `/treasury-tech-market/`, never to
  4202, so the waitlist keeps working correctly no matter when 4202 changes.
- ~~4202 has not been touched; it still serves the waitlist confirmation~~ — **no
  longer true as of September 19.** 4202 is the download page now and 4809 is
  published. The waitlist still lands correctly, because step 2's mapping was
  repointed at 4809 — verified September 19, not assumed.
