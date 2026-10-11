# HubSpot page-view tracking, consent-gated (October 2026)

Owner decision, October 11, 2026: turn on HubSpot's website tracking so a contact's HubSpot record
shows which of our pages they read before and after they filled in a form. Disclosure goes first.
Visit history linked to a person is reviewed and deleted three years after our last contact, the
same as the contact record itself. Visit data not linked to a person is kept 14 months, the same
period the Privacy Policy states for Google Analytics (owner decisions, October 11, 2026).

HubSpot's tracking code does not read Google Consent Mode. Loaded directly, it sets `__hstc` and
`hubspotutk` for a visitor who rejected, which breaks the site rule in `AGENTS.md` ("Consent and
analytics"). So it is loaded only by a GTM tag that requires `analytics_storage`, the same gate as
the LinkedIn (tag 12) and Apollo (tag 5) tags. **No HubSpot script is embedded in the theme**, and
`scripts/tests/test_consent_mode_defaults.js` fails if an `hs-scripts.com` URL appears in
`assets/php/functions.php`.

## What is in this repo

| Where | What |
|---|---|
| `assets/php/functions.php`, `rt_consent_banner()` | `clearAnalyticsCookies()` also expires `__hstc`, `__hssc`, `__hssrc` and `hubspotutk`. On Reject or withdrawal, `applyConsent(false)` tells a loaded HubSpot tracker to stop (`setHubSpotConsent` with analytics off, then `revokeCookieConsent`). Banner and preference-panel copy name HubSpot. |
| `assets/php/functions.php`, `rt_track_helper()` | `window.rtSource()` adds `hutk` (the `hubspotutk` value) only when `rt_consent=analytics` and the cookie is exactly 32 lowercase hex characters. It is read from the cookie on each call and never copied into `rt_src`. Page output is unchanged for every visitor; PHP never reads the cookie. |
| `treasury-tech-selection/guidebook/feedback/wordpress-page.html` | The direct HubSpot Forms post adds `context.hutk` only when `rtSource()` returns a valid one **and** the reader typed an address different from the link's `?email=`. The emailed-link path (auto-submit and the failed-save Send button) carries no visitor ID, because a forwarded email would otherwise tie one person's browsing to someone else's contact. HubSpot rejects a malformed `hutk` with `INVALID_HUTK`. |
| `privacy-policy/index.html` (page 167) | The HubSpot processor row and retention bullet now cover page visits after consent; a section 2 purpose row; the visitor ID recorded with a gated-form submission; the California internet-activity category; a retention bullet for HubSpot visit data not linked to a contact (14 months, the Google Analytics period). Visit linking is described as "our gated download forms or the guide feedback page", the only forms that send `hutk` (also in the banner and Cookie Policy). Contact Form 7 and the newsletter do not; re-word all three places if either ever starts sending it. |
| `cookie-policy/index.html` (page 360) | HubSpot named with LinkedIn and Apollo; four HubSpot cookie rows; a HubSpot paragraph with its privacy policy. |

The rest of the chain is outside this repo: RT Gate must keep `source.hutk` (a strict 32-hex check)
and `hubspot_web_sync.py` must pass it to HubSpot as `context.hutk`. Until both ship, `hutk` from a
gated form is dropped and only the guide feedback page links visits to a contact.

## The GTM tag

Container `GTM-W877KNJR`. New tag, type **Custom HTML**, name `HubSpot tracking`.

Tag body, exactly:

```html
<script>window.disableHubSpotCookieBanner = true; var _hsp = window._hsp = window._hsp || []; _hsp.push(['setHubSpotConsent',{analytics:true,advertisement:false,functionality:true}]); (function(){ if (document.getElementById('hs-script-loader')) return; var s=document.createElement('script'); s.id='hs-script-loader'; s.async=true; s.defer=true; s.src='https://js-na2.hs-scripts.com/247523747.js'; document.head.appendChild(s); })();</script>
```

- **Triggering:** All Pages (the `gtm.js` event), the same trigger tags 5 and 12 use.
- **Exception:** the same blocking trigger as tags 5 and 12, which blocks when the first-party
  cookie variable `_rt_internal` equals `true`. It keeps our own visits out.
- **Advanced Settings > Consent Settings:** "Require additional consent for tag to fire" =
  `analytics_storage`. Not `ad_storage`: the banner never grants it, so the tag would never fire.
- **Advanced Settings > Tag firing options:** Once per page.

Why the body looks like this:

- `disableHubSpotCookieBanner` stops HubSpot showing its own banner; our banner is the only one.
- `setHubSpotConsent` with analytics on is pushed before the loader, because the tag only ever
  runs after the visitor accepted. Advertisement stays off; we run no HubSpot ads.
- The `hs-script-loader` id check stops a second copy if the tag is ever duplicated.
- We do not use `_hsq.push(['doNotTrack'])`. The tag never fires without consent, and `doNotTrack`
  would leave a six-month cookie on someone who said no.
- Before publishing, compare the loader URL with HubSpot > Settings > Tracking & Analytics >
  Tracking code. If it differs, use HubSpot's value and update this file in the same PR.

Staging: `rt_block_tags_off_production()` blocks Site Kit's GTM snippet off production, so the tag
cannot fire on staging. Test with GTM Preview (Tag Assistant) on production.

## Owner steps, in order

Each step needs the ones before it. Disclosure ships before the tag: over-disclosure for a few
hours is fine, under-disclosure is not.

1. **HubSpot settings (before anything ships).**
   - Marketing > Forms > Non-HubSpot forms: turn "Collect data from website forms" **off**. The
     loader always brings in collected forms, which would capture RT Gate and Contact Form 7
     submissions outside `hubspot_web_sync.py`'s non-marketing guard.
   - Settings > Privacy & Consent > Consent banners: **no** active banner policy for
     realtreasury.com.
   - Settings > Tracking & Analytics > Tracking code: confirm the embed is
     `js-na2.hs-scripts.com/247523747.js`, add realtreasury.com to the site domains, and exclude
     our own IP addresses if wanted.
   - Account data-sharing settings: turn off any sharing of tracking data with HubSpot's own
     datasets, so HubSpot acts only as our processor. This was not checked in the scouting pass.
2. **Merge this PR.**
3. **Hand-deploy the theme `functions.php`** (all of main) over the WordPress.com SSH rail
   (`/opt/rt-ai/secrets/wpcom-ssh.env`, host key pinned in `scripts/wpcom_known_hosts`). Back up the
   live `wp-content/themes/astra/functions.php` first and run `php -l` on the new file. This also
   ships everything else on main that is not live yet (the `rt-track` helper and the JSON-LD
   changes); follow `docs/guide-release-runbook.md` for the guide pages at the same time. Then curl
   the home page: it returns 200, `id="rt-track"` is present, and the banner script contains
   `HubSpot` and the `hubspotutk` pattern.
4. **Retention the Privacy Policy promises.**
   - Done (owner decision, October 11, 2026): the section 5 placeholder is replaced. HubSpot visit
     data not linked to a contact is kept 14 months, the same as Google Analytics.
   - Three-year deletion of HubSpot contacts, with their linked visit history: an rt-ai monthly
     review job lists contacts past three years since our last contact for the owner to approve,
     and a guarded script deletes the approved ones. Both are built separately in rt-ai, not in
     this repo. The policy says "we review and delete them three years after our last contact
     with you", which matches that job; have it running before the policy publishes.
   - Open: how the 14-month limit on unlinked visit data is enforced in HubSpot was not checked.
     Confirm a HubSpot setting or a step in the rt-ai review job covers it before the policy
     publishes.
5. **Publish the two policy pages and the guide feedback page by hand.** For each of
   `privacy-policy`, `cookie-policy` and `guide-feedback`: run
   `scripts/wp_publish_post.py plan <slug> --target production`, read the diff (the policy pages
   also carry earlier changes on main that are not live yet), publish, and read the live page back.
   Both policy pages say "Last Updated: October 11, 2026"; change it to the publish date if they go
   out later. The four HubSpot cookie rows in the Cookie Policy come from HubSpot's documentation,
   not a measurement; check them in a clean browser on production after Accept (verification
   step c) and fix any name or lifetime that differs.
6. **Ship the RT Gate and `hubspot_web_sync.py` changes** that carry `hutk` (separate PRs). The
   policies and banner say visits are linked through "our gated download forms or the guide
   feedback page". If these changes are not live when the tag is published, change that wording
   to the guide feedback page alone first (banner, Cookie Policy, Privacy Policy sections 1, 3 and
   the California paragraph).
7. **Publish the GTM tag last**, as container version 13.

## Live verification (after the GTM publish)

Clean browser profile, production, DevTools open on Network and Application > Cookies.

- [ ] (a) Before any choice: no requests to `js-na2.hs-scripts.com`, `hs-analytics.net`,
  `hscollectedforms.net` or `hs-banner.com`, and no `__hstc` or `hubspotutk` cookie.
- [ ] (b) Reject: still none, across two page loads.
- [ ] (c) Accept, on the same page: the loader is requested on that page (this proves GTM fires a
  consent-blocked tag after the banner's consent update), `__hstc` and `hubspotutk` are set on
  `.realtreasury.com`, and no HubSpot banner appears. Note each HubSpot cookie's name and expiry
  and compare them with the Cookie Policy table.
- [ ] (d) With `_rt_internal=true` set: the tag does not fire (Tag Assistant).
- [ ] (e) Cookie Policy > Manage cookie preferences > untick and save: `__hstc`, `hubspotutk`,
  `__hssc` and `__hssrc` are gone, and the next page load makes no HubSpot requests.
- [ ] (f) After Accept, submit one controlled RT Gate test lead. Within 15 minutes
  `hubspot_web_sync.py` exits 0 with the marketing-contact count unchanged, and that contact's
  HubSpot timeline shows page views (`hs_analytics_num_page_views` above 0).
- [ ] (g) After Accept, open the guide feedback page from a test link (`?email=` and `?r=`): the
  auto-submitted rating carries no `context.hutk` (check the request payload) and is accepted.
  Then open the page without `email=`, type a test address, and send a rating: the payload
  carries `context.hutk`, the submission is accepted (no `INVALID_HUTK`), and the contact shows
  page views.
- [ ] (h) `curl` both policy pages and find `HubSpot` in each.

To switch tracking off: pause the tag in GTM and publish the container. Nothing in the theme
loads HubSpot, so no deploy is needed; the policies then over-disclose until they are edited.
