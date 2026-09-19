# The site-wide banner

The fixed bar across the top of every realtreasury.com page. Source of record is
`header/main-menu/index.html`.

## What it does now

The bar runs a **rota**, not a single hardcoded promotion. `BANNER_ITEMS` near the
top of the page's script holds one entry per promotion:

```js
{
    key: 'tours',
    title: 'AFP 2026 Guided Tours',
    subtitle: 'November 9–10 at AFP Las Vegas — three booths, ten seats, free to attend.',
    cta: 'Register Now',
    url: 'https://realtreasury.com/afp-2026-tms-tour/',
    label: 'Real Treasury',
    start: '2026-09-15',
    end: '2026-11-10'
}
```

- `start`/`end` are **inclusive** dates, read in the site's timezone
  (America/Chicago) rather than the visitor's, so a window does not open a day
  early in Singapore.
- An entry with **neither** is evergreen: always eligible. That is what keeps the
  bar from going blank the day the last event window closes. At least one entry
  must be evergreen, and the test enforces it.
- A dated entry **outside** its window is not rendered at all. An expired event
  drops off by itself; nobody has to remember to remove it.
- Everything eligible today goes into the lineup, **dated entries first**. One
  eligible entry renders static. Two or more rotate every 8 seconds.
- `logo: true` swaps the "RT" tile for the Treasury Tech Virtual Forum mark. Leave
  it off for an RT-owned promotion or the event gets badged with someone else's.

The rotation pauses on hover and on focus, and does not run at all for a visitor
with `prefers-reduced-motion: reduce` — they get the first entry in the lineup,
which is why dated-first ordering matters.

## Adding or retiring a promotion

1. Add an entry to `BANNER_ITEMS`.
2. Add its URL to `DESTINATIONS` at the top of
   `scripts/tests/test_banner_destination.js`. That list is deliberately a second
   edit: it is the line that says which promotions this banner is for, so a URL
   cannot be changed to something unrelated and stay self-consistent.
3. If the new entry is the one that should paint before JavaScript runs — what a
   no-JS visitor sees — update the markup's `.banner-highlight`, `.banner-subtitle`
   and the CTA `href` to match it. The test checks that the markup's default is one
   of the entries and that its href agrees.
4. `npm run test:banner`.

Retiring is usually nothing: let the window close.

### The title budget is 28 characters

The nav container is offset from the top by a hardcoded 80px desktop / 112px
mobile / 134px below 480px. A `title` that wraps grows the bar past that offset
and pushes the navigation *underneath* it. 28 characters is the measured ceiling
at 375px, and the test fails above it. Put the short name in `title` and the date
and the pitch in `subtitle`.

Verify a swap headless at 375/414/480/768/1024/1440, and diff the bar's height
against a render of `main` rather than reading absolute numbers.

### The trap that used to live here

The destination was carried **twice** — on the CTA's `href` and in a
`LIVE_EVENT_REGISTRATION_URL` const — and every click path navigated via the
const. A swap that updated only the href looked correct in review and in the
rendered page, and sent every visitor to the previous event.

That is gone. The destination lives once, in `BANNER_ITEMS`; `applyBannerItem()`
writes it to the CTA's `href`, and both click paths (`registerLive`, the
whole-bar `expandBanner`) read it back off the element via `bannerDestination()`.
The test fails if `LIVE_EVENT_REGISTRATION_URL` ever comes back.

## Deploying it

**The banner is not on the build pipeline and a push does not deploy it.** It is
synced pattern **183** ("Header Menu", post type `wp_block`) on production, which
holds two nested documents: the banner, then the nav. Splicing the wrong one is
easy — anchor on `<div class="workshop-banner" id="workshopBanner">` and take the
innermost enclosing document (`rfind('<html')` before, `find('</html>')` after),
because pairing each `<html>` with the next `</html>` matches the outer document
and looks correct.

Before any write, diff the live pattern against `main:header/main-menu/index.html`
— it is the only way to catch a wp-admin edit that never came back to git.
