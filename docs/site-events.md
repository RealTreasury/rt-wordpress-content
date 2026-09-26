# Site events

Our own analytics events go through one function, `window.rtTrack(name, params)`, defined in
`assets/php/functions.php` (`rt_track_helper`, `wp_head` priority 2, right after the Consent
Mode defaults). It forwards to `gtag('event', …)`, so every event obeys the visitor's consent
choice. Tag Manager (`GTM-W877KNJR`) holds only third-party pixels; do not add our own events
there.

Callers never assume it exists:

```js
if (typeof window.rtTrack === 'function') window.rtTrack('generate_lead', { ... });
```

## Vocabulary

GA4 recommended names where one exists. Parameters are strings, clipped to 100 characters.
Never send a name, email address or company name.

| Event | Fires when | Parameters | GA4 key event |
|---|---|---|---|
| `generate_lead` | An RT Gate submit succeeds, or the Contact form sends | `form_name`, `asset`, `lead_page`, `contact_request` | yes |
| `file_download` | A gated asset is delivered | `asset`, `file_name` | yes |
| `sign_up` | Newsletter subscription succeeds | `method`, `lead_page` | register when first seen |
| `book_call` | A Calendly booking completes (Calendly embed message) | `lead_page` | register when first seen |
| `search` | Portal or market-chart search settles | `search_term`, `surface` | no |
| `vendor_view` | A vendor card or profile opens | `vendor`, `category`, `surface` | no |
| `filter_apply` | A portal or chart filter changes | `filter`, `value`, `surface` | no |
| `cta_click` | Any element with `data-rt-cta="<name>"` (optional `data-rt-cta-location`), or any Calendly or Microsoft Bookings link | `cta`, `location` | no |

`surface` is `portal` or `market`. Add a CTA by putting `data-rt-cta` on the link or button; no
script change is needed.

## Cross-origin pages

Pages served from `https://realtreasury.github.io` inside an iframe cannot call `rtTrack`. They
post to the parent instead, and the helper forwards the event with the parent page's path as
`lead_page`:

```js
window.parent.postMessage({ source: 'rt', type: 'rt:track', name, params }, 'https://realtreasury.com');
```

## Lead source

`window.rtSource()` returns the session's `{utm_source, utm_medium, utm_campaign, utm_content,
utm_term, landing_page, referrer_host}`. RT Gate sends it with every submission so a lead records
where it came from. Iframes ask for it with `{source:'rt', type:'rt:source?'}` and receive
`{source:'rt', type:'rt:source', data}`.

It is written to `sessionStorage` (key `rt_src`, cleared when the tab closes) only after the
visitor accepts analytics; before that it is held for the current page only. The Cookie Policy's
inventory lists `rt_src` alongside `rt_consent`; change both together.

## Campaign tags

Every link we send carries:

- `utm_source`: `linkedin`, `resend`, `apollo`, `event` or `partner`
- `utm_medium`: `social`, `email` or `referral`
- `utm_campaign`: `yyyy-mm-slug`, for example `2026-10-guide`

## Staging

`rt_block_tags_off_production()` blocks Site Kit's GA4, Tag Manager and Ads tags on any host other
than `realtreasury.com`, so the WordPress.com staging site no longer reports into production.
