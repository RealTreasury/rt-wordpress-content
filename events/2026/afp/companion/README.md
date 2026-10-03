# Tour Companion — AFP 2026 Guided Vendor Tours

A phone page for registrants, opened by a per-tour QR code at the start of the
tour. Public, static, standalone: no RT Gate, Tracker or rt-ai calls, no
analytics. Notes stay in the attendee's browser.

Live URL once merged:
`https://realtreasury.github.io/rt-wordpress-content/events/2026/afp/companion/`

| Tab | What it does |
|---|---|
| Start | The tour's three vendors (booth, time, guidebook description), the run of show, what to watch for on the route |
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

## Sources

- Lineups, times, booths: `Vendor Tracker and Matrix 20261001.xlsx`, AFP Tours
  sheet (file-repository → General/5. Marketing/02 Conferences/2026/AFP Las Vegas).
- Vendor descriptions: `2026-Real-Treasury-Tech-Selection-Guide.pdf` (FINAL,
  September 29, 2026), shown with "as supplied for the guide" attribution.
- Session times: `window.RT_TOUR` in `../index.html`. Keep them in step.

## Open before the show

- Meeting point: blank (`meetingPoint` in `index.html`); the row hides until set.
- Demo format 5 / 1–2 / 3 minutes is from the September 30, 2026 meeting;
  confirm. The registration page still says 12-minute stops.
