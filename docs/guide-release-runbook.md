# 2026 Tech Selection Guide — release runbook

State of play as of September 16, 2026. The release is **on hold**: Revision 8
landed on SharePoint on September 15 (one full guide plus three segment cuts) and
no file has been named as the release PDF. Everything below is staged so that
release is a short, ordered sequence rather than a build.

## Pages

| what | where | status |
|---|---|---|
| Download page (gated form) | WP **4202** `/treasury-tech-selection-guide/` | published, still serving the **waitlist confirmation** |
| Download page content | WP **4587** draft, slug `…-guide-download` | draft, **stale** — see below |
| Guide thank-you | WP **4585** `/treasury-tech-selection-guide/thank-you/` | draft, matches the repo byte for byte |
| Waitlist signup | WP post **4211** `/2026-treasury-tech-guide-waitlist/` | published |
| Waitlist confirmation | WP **4809** `/2026-treasury-tech-guide-waitlist-confirmed/` | draft, matches the repo |

Sources of record, all WordPress-native (a paste, not the GitHub Pages iframe
pipeline):

- `treasury-tech-selection/guidebook/wordpress-page.html`
- `treasury-tech-selection/guidebook/thank-you/wordpress-page.html`
- `treasury-tech-selection/waitlist/confirmed/wordpress-page.html`

**4587 is stale.** It carries the May 2026 chart art
(`2026/05/tms-market-NORAM-05-2026-clean.png`) and the old overlay chart title.
The repo carries the September 2026 art
(`2026/09/tms-market-NORAM-09-2026-clean-new-1.png`) — the same file the live
`/treasury-tech-market/` page uses — and the header-band title. Re-paste before
anyone reviews the draft.

## Order of operations

The ordering matters because page 4202 changes meaning.

1. **Repoint the waitlist redirect first.** RT Gate mapping #5's asset
   (`treasury-tech-selection-guide-waitlist`) has its gate URL set to
   `/treasury-tech-selection-guide/`. Change it to
   `/2026-treasury-tech-guide-waitlist-confirmed/` and publish page 4809. Until
   this is done, turning 4202 into the download page drops waitlist signups onto
   the download form. WP Admin only — the waitlist page carries no `redirectUrl`
   and follows the `/submit` response's `primary_redirect_url`.
2. **Upload the release PDF** under an unguessable filename, linked from no page.
   Email verification is the gate; the file itself is public.
3. **Set RT Gate mapping #8's asset `target_url`** to that PDF, and confirm the
   mapping's `lead_email_mode` is `none` — otherwise every downloader gets the
   rt-gate `wp_mail` confirmation *and* the Resend delivery email.
4. **Fix the two Resend placeholders.** Template `guide-delivery` currently points
   its hero image and its "Download the guide" button at an unrelated published
   PDF (a bank CRE exposure report), with a visible orange warning line under the
   button. Swap both for the real URL and delete the warning line. Do the same in
   draft broadcast `ae14794e-68ff-459c-bcc4-f18cb079b6fe`.
5. **Publish the download page.** Paste the repo source into 4202 and publish
   4585 (thank-you). 4587 is the staging draft, not a destination.
6. **Enable automation** "Tech Selection Guide — deliver on signup"
   (`01a067af-c041-7579-a1f3-ad0f042f25fe`, currently disabled) and send the
   broadcast.

## Safe today, because

- The delivery automation is **disabled**, so the wrong-PDF placeholder in
  `guide-delivery` cannot reach anyone.
- The release broadcast is a **draft**.
- The waitlist confirmation email (`guide-waitlist-confirmation`, enabled) links
  only to `/treasury-tech-market/`, never to 4202, so the waitlist keeps working
  correctly no matter when 4202 changes.

## Known divergence, not fixed

`guide-delivery` uses hero `2026-Real-Treasury-Tech-Selection-Guide-new.png`
(updated September 12); `guide-waitlist-confirmation` uses
`2026-Real-Treasury-Tech-Selection-Guide.png`. Confirm which art is current
before release rather than assuming the newer file is the right one.
