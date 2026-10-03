# Tour Companion — AFP 2026 Guided Vendor Tours

A phone page for registrants, opened by a per-tour QR code at the start of the
tour. Public, static, standalone: no RT Gate, Tracker or rt-ai calls, no
analytics. Notes stay in the attendee's browser.

Live URL once merged:
`https://realtreasury.github.io/rt-wordpress-content/events/2026/afp/companion/`

| Tab | What it does |
|---|---|
| Start | The tour's three vendors (booth, time, guidebook description), what every vendor will show (the route's script areas), the run of show, what to watch for |
| Stop 1–3 | A short lesson on the demo script, read while walking between booths, plus one optional line to remember the stop by |
| Debrief | Notes per stop, no scores (by design). Email to myself, Save as PDF, Copy. Guide and contact links |
| Hall | Every tracked treasury tech vendor exhibiting, with booth numbers. Open to anyone |

## Why the lineups are encrypted

Routes are not published vendor by vendor (registration page FAQ). Each tour's
lineup is in `lineups.js` as AES-GCM ciphertext, keyed (PBKDF2-SHA256) by that
tour's QR code. Without the code the page shows "Stop 1 / 2 / 3". With it, the
page decrypts in the browser and then drops the code from the address bar.

## Rebuilding after a lineup, booth or text change

The plaintext input lives **outside this repo** at
`~/afp-2026-companion/lineups.private.json` on rt-ai-02, with the QR images
next to it. Never commit it. Edit it, then:

```
node events/2026/afp/companion/build-lineups.mjs ~/afp-2026-companion/lineups.private.json
```

That rewrites `lineups.js` and `directory.js` and prints the four QR URLs.
Codes are kept between rebuilds, so printed QR codes keep working. Commit the
two `.js` files through a PR as usual.

`"directoryText": false` in the input publishes the Hall as names and booths
only.

### Adding the Replacement script

Only the Visibility script exists so far (Tracey is writing the Replacement
one). Each session's `"script"` in the private input is a paraphrase of its
route's script areas: `[{"title": "...", "items": ["...", "..."]}]`. Add it to
`s1` and `s4`, rebuild, and the "What every vendor will show" card appears on
those tours. Paraphrase only — the script document is marked confidential, so
its text and sample data never go in.

## Sources

- Lineups, times, booths: `Vendor Tracker and Matrix 20261001.xlsx`, AFP Tours
  sheet (file-repository → General/5. Marketing/02 Conferences/2026/AFP Las Vegas).
- Vendor descriptions: our `2026-Real-Treasury-Tech-Selection-Guide.pdf` (FINAL,
  September 29, 2026), shown with "as supplied for the guide" attribution.
- Demo timing and the Visibility script areas: `Visibility Tour Demo Script,
  November 2026` (Info Package for Vendors folder, October 1, 2026).
- Session times: `window.RT_TOUR` in `../index.html`. Keep them in step.

## Open before the show

- Meeting point: blank (`meetingPoint` in `index.html`); the row hides until set.
- Replacement script: pending from Tracey (see above).
- The registration page still says 12-minute stops; the script sets 10.
