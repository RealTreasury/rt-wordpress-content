# HubSpot page-view tracking, consent-gated (October 2026)

Owner decision, October 11, 2026: turn on HubSpot's website tracking so a contact's HubSpot record
shows which of our pages they read before and after they filled in a form. Disclosure goes first.
Visit history linked to a person follows the contact record: deleted three years after our last
contact. The policy wording is kept to what other sites typically say, on purpose (Tim, October
11, 2026): HubSpot named in the Privacy Policy's provider table, HubSpot's cookies and one line in
the Cookie Policy, and HubSpot in the cookie preference panel's list of tools.

HubSpot's tracking code does not read Google Consent Mode. Loaded directly, it sets `__hstc` and
`hubspotutk` for a visitor who rejected, which breaks the site rule in `AGENTS.md` ("Consent and
analytics"). So it is loaded only by a GTM tag that requires `analytics_storage`, the same gate as
the LinkedIn (tag 12) and Apollo (tag 5) tags. **No HubSpot script is embedded in the theme**, and
`scripts/tests/test_consent_mode_defaults.js` fails if an `hs-scripts.com` URL appears in
`assets/php/functions.php`.

Second owner decision, October 11, 2026: "track by default for US". For US visitors
`analytics_storage` is granted by default, so this tag (and LinkedIn and Apollo) fires for them
without a click. Everyone else still opts in. See "US default" below.

## What is in this repo

| Where | What |
|---|---|
| `assets/php/functions.php`, `rt_consent_banner()` | `clearAnalyticsCookies()` also expires `__hstc`, `__hssc`, `__hssrc` and `hubspotutk`. On Reject or withdrawal, `applyConsent(false)` tells a loaded HubSpot tracker to stop (`setHubSpotConsent` with analytics off, then `revokeCookieConsent`). The preference panel names HubSpot (with Google Analytics, LinkedIn and Apollo, and what each is for); the opt-in banner's short copy names no tool and links to the Cookie Policy. US visitors see a small notice instead of the banner (see "US default"). |
| `assets/php/functions.php`, `rt_track_helper()` | `window.rtSource()` adds `hutk` (the `hubspotutk` value) only with analytics consent as `window.rtConsent.analytics()` defines it (an Accept, or the US default) and when the cookie is exactly 32 lowercase hex characters. It is read from the cookie on each call and never copied into `rt_src`. Page output is unchanged for every visitor; PHP never reads the cookie. |
| `treasury-tech-selection/guidebook/feedback/wordpress-page.html` | The direct HubSpot Forms post adds `context.hutk` only when `rtSource()` returns a valid one **and** the reader typed an address different from the link's `?email=`. The emailed-link path (auto-submit and the failed-save Send button) carries no visitor ID, because a forwarded email would otherwise tie one person's browsing to someone else's contact. HubSpot rejects a malformed `hutk` with `INVALID_HUTK`. |
| `privacy-policy/index.html` (page 167) | The HubSpot provider row: we use HubSpot to manage our contacts and understand how visitors use our site; it receives your contact details, your IP address and browser type when you use our guide feedback page, and, while analytics cookies are on, your visits to our site. Section 4 carries the US line. |
| `cookie-policy/index.html` (page 360) | HubSpot named with LinkedIn and Apollo; four HubSpot cookie rows; one line on HubSpot with its privacy policy. Section 1 carries the US line; `rt_region` is in the essential table. The preference panel lists HubSpot. |

The rest of the chain is outside this repo: RT Gate must keep `source.hutk` (a strict 32-hex check)
and `hubspot_web_sync.py` must pass it to HubSpot as `context.hutk`. Until both ship, `hutk` from a
gated form is dropped and only the guide feedback page links visits to a contact.

## US default (October 11, 2026)

Owner decision (Tim): track by default for US visitors; everyone else stays opt-in exactly as
before.

**The rule, in one place.** `window.rtConsent` in `rt_consent_mode_defaults()` (`wp_head`
priority 1) answers "do we have analytics consent?" for every caller: the head defaults, the first-page
update, `rtTrack`'s `consented()`, `rtSource()` (`rt_src` and `hutk`) and the banner. Consent means
`rt_consent=analytics`, or all of: `rt_region=US`, no `rt_consent` cookie at all, and the browser
does not send Global Privacy Control (`navigator.globalPrivacyControl === true`). Any stored
`rt_consent` other than `analytics` is a refusal. The US default is never written to `rt_consent`.
`ad_storage`, `ad_user_data` and `ad_personalization` stay denied for everyone.

**GPC against a stored choice.** In the US, GPC overrides a choice recorded without it: an OK on
the notice, or an Accept made before GPC was turned on. Only an Accept or panel save made while
the browser sends GPC wins; the banner then also writes `rt_consent_gpc=1` (12 months), and any
other choice clears it. An overridden `rt_consent=analytics` is left in place but counts as no
consent everywhere (head default, `rt_src`, `hutk`), and the opt-in banner shows again so the
visitor can choose under GPC. Outside the US, GPC changes nothing. Existing `analytics` cookies
have no `rt_consent_gpc`, so GPC overrides them (fails safe).

**Region.** Pages are cached and identical for every visitor, so the country is found in the
browser: `rtConsent` fetches `/cdn-cgi/trace` (same origin, Cloudflare; a `loc=US` line) once and
stores the two-letter code in `rt_region` (30 days, `path=/`, `SameSite=Lax`, `Secure`). Later
pages read the cookie at priority 1 with no fetch. A trace with no country stores `XX`. A failed
fetch, a non-200, or no answer within 2 seconds means opt-in for that page and stores nothing, so
the next page asks again. A late answer is stored for the next page only: the page that timed out
stays opt-in throughout, so `rtConsent` never disagrees with the banner it is showing. Off
Cloudflare (staging) the trace 404s, so staging is always opt-in.

**Re-check once per session.** The cookie lasts 30 days, so the first page of each browser
session (no `rt_region_checked` in `sessionStorage`) still uses the cookie for the head default,
then fetches the trace again. If the country changed, `rt_region` is rewritten and the page
follows: a cookie that said US for a visitor now in Germany gets one consent update to `denied`,
`rt_src` removed, Google Analytics and HubSpot cookies deleted, HubSpot told to stop, and the
notice replaced by the opt-in banner. A failed re-check keeps the cookie and the next page tries
again. Limitation: a visitor who changes country mid-session is covered only at the next session,
and on the first page of that session the tags may already have loaded from the old cookie before
the re-check answers.

**Analytics off without a click.** When the region is known and analytics is off (GPC turned on,
the re-check moved the visitor out of the US, or a stored refusal), the banner deletes the Google
Analytics and HubSpot cookies on page load, as Reject does. It never writes `rt_consent`.

**Without the head block.** If `window.rtConsent` is missing, the banner runs strictly: no US
default, the opt-in banner when nothing is stored, and the preference trigger still works.

**First US page.** The default goes out denied (the region is not known yet). When the trace says
US, `rtConsent` pushes one `gtag('consent','update',{analytics_storage:'granted'})` and calls
`rtSourcePersist()`, so the GTM consent-gated tags (LinkedIn 12, Apollo 5, HubSpot) fire on that
page the way they do after Accept, and the landing page's UTM tags are kept. From the second page
the default itself is `granted`.

**What visitors see.** The banner waits for the region on the first page.

| Visitor | Analytics by default | Shown |
|---|---|---|
| US, no choice, no GPC | granted | Notice: "We use cookies to understand how our site is used." Cookie settings (opens the panel), Do not sell or share my personal information, OK |
| US with GPC, no choice, or a choice recorded without GPC | denied | The opt-in banner (an Accept there or in the panel, made under GPC, wins over GPC) |
| Outside the US, or region unknown | denied | The opt-in banner: Accept and Reject the same size and style, Settings as a text link |
| Anyone else with a stored choice | their choice | Nothing |

"OK" records `rt_consent=analytics`. "Do not sell or share my personal information" records
`rt_consent=essential` in one click and hides the notice, the same as Reject: consent update to
denied, Google Analytics and HubSpot cookies deleted, HubSpot told to stop. We chose one click
over opening the panel because the opt-out should take no further step; Cookie settings is right
beside it for anyone who wants the detail. A choice is now remembered for 12 months (was 180 days).

Tests: `scripts/tests/test_consent_mode_defaults.js` (region cases, first-page trace, slow trace,
session re-check, GPC against a stored choice, cookie clearing, strict fallback, notice vs banner,
equal buttons, 12 months) and `scripts/tests/test_rt_track_helper.js` (`rt_src` and `hutk` under
the US default, GPC, a refusal, a re-check and a late trace).

**Footer opt-out link (required before the theme deploy).** The notice disappears once answered,
and GPC visitors see the opt-in banner instead, so the site may not qualify for the CCPA exemption
from posting a "Do Not Sell or Share" link. `footer/index.html` now carries
`<a href="#" data-rt-cookie-preferences>Do Not Sell or Share My Personal Information</a>` in the
legal row; the live footer is WordPress pattern 398, which must get the same link (owner step 4b).
The banner's click handler already opens the preference panel for it; untick and save opts out.

Open for the owner:

- **Settled (Tim, October 11, 2026):** LinkedIn runs by default in the US too, and we sometimes
  pass names, companies and roles to vendors or other firms for introductions without being asked.
  So the opening line is now "We do not sell your personal information, and we do not receive fees
  from technology vendors." ("or share" removed), section 3 says we sometimes pass those details on
  (not paid; email privacy@ to stop), and the California section says the same.
- `rt_region` is set for every visitor, including EU and UK visitors before they choose. It is
  listed as essential (it decides which cookie rules apply).

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
  runs with `analytics_storage` granted: after an Accept, or under the US default. Advertisement
  stays off; we run no HubSpot ads.
- The `hs-script-loader` id check stops a second copy if the tag is ever duplicated.
- We do not use `_hsq.push(['doNotTrack'])`. The tag never fires without `analytics_storage`
  granted, and `doNotTrack` would leave a six-month cookie on someone who said no.
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
2. **Run publish-check** on the policy changes and on the banner and notice copy in
   `rt_consent_banner()` (all outward-facing), then **merge this PR.**
3. **Retention the Privacy Policy promises.** Three-year deletion of HubSpot contacts, with their
   linked visit history: the rt-ai monthly review job (rt-ai #1068) lists contacts past three years
   since our last contact for the owner to approve, and a guarded script deletes the approved
   ones. Have it running before the policy publishes.
4. **Privacy Policy opening line:** settled (see "Open for the owner"); nothing to do.

   **Publish the two policy pages and the guide feedback page by hand, before the theme.** The
   theme deploy in step 5 turns on the LinkedIn and Apollo tags (already live in GTM) by default
   for US visitors, so the policies must say so first; until step 5 they over-disclose (the US
   default, `rt_region`, 12 months), which is the safe direction. For each of `privacy-policy`,
   `cookie-policy` and `guide-feedback`: run `scripts/wp_publish_post.py plan <slug> --target
   production`, read the diff (the policy pages also carry earlier changes on main that are not
   live yet), publish, and read the live page back. Both policy pages say "Last Updated: October
   11, 2026"; change it to the publish date if they go out later. The four HubSpot cookie rows in
   the Cookie Policy come from HubSpot's documentation, not a measurement; check them in a clean
   browser on production after the tag is live (verification step c) and fix any name or lifetime
   that differs.

   **4b. Footer opt-out link (required before step 5).** Add
   `<a href="#" data-rt-cookie-preferences>Do Not Sell or Share My Personal Information</a>` to the
   legal row of WordPress footer pattern 398, matching `footer/index.html`. Back up its
   `post_content` first (as in `docs/GO-LIVE-SEPT-2026.md`, step 5) and read the live footer back.
5. **Hand-deploy the theme `functions.php`** (all of main) over the WordPress.com SSH rail
   (`/opt/rt-ai/secrets/wpcom-ssh.env`, host key pinned in `scripts/wpcom_known_hosts`). Back up the
   live `wp-content/themes/astra/functions.php` first and run `php -l` on the new file. This also
   ships everything else on main that is not live yet (the `rt-track` helper and the JSON-LD
   changes); follow `docs/guide-release-runbook.md` for the guide pages at the same time. Then curl
   the home page: it returns 200, `id="rt-track"` and `window.rtConsent` are present, and the
   banner script contains `HubSpot`, `Do not sell or share` and the `hubspotutk` pattern. Purge the
   page cache if the old head block is still served. From here US visitors are tracked by default
   by the LinkedIn and Apollo tags.
6. **Ship the RT Gate and `hubspot_web_sync.py` changes** that carry `hutk` (rt-gate #92, rt-ai
   #1067). Until these ship, only the guide feedback page links visits to a contact.
7. **Publish the GTM tag last**, as container version 13. No GTM change is needed for the US
   default: tags 5, 12 and HubSpot keep requiring `analytics_storage`, and the theme grants it.

## Live verification (after the GTM publish)

We cannot change our own IP, and from here the site sees us as US. Test the other paths by setting
`rt_region` by hand. The cookie is re-checked once per session, so also mark the session as
checked, or the first page will put `US` back. To set both before any tag fires, open
`https://realtreasury.com/cdn-cgi/trace` (a Cloudflare text page, no tags), and in the DevTools
console there run
`document.cookie = 'rt_region=DE; path=/; max-age=2592000; SameSite=Lax; Secure'; sessionStorage.setItem('rt_region_checked', '1')`,
then open the home page **in the same tab** (`sessionStorage` is per tab). Use `XX` for an unknown
country and `US` for the US. Delete the cookie to make the next page ask Cloudflare again.

Clean browser profile for each path, production, DevTools open on Network and Application >
Cookies. In the console, `dataLayer.filter(a => a[0] === 'consent')` shows the default and any update.

US path (no cookie set by hand):

- [ ] (u1) First page: one request to `/cdn-cgi/trace`; `rt_region=US` is set; the small notice
  shows, not the banner; `dataLayer` has the default (`analytics_storage: denied`) then one update
  to `granted`; the HubSpot loader, LinkedIn and Apollo load on that page; no `rt_consent` cookie.
- [ ] (u2) Second page: no trace request; the default is `granted`; the notice shows again until
  you answer it.
- [ ] (u3) Notice > Do not sell or share my personal information: `rt_consent=essential`,
  `__hstc`, `hubspotutk`, `__hssc`, `__hssrc` and `_ga*` are gone, the notice hides, and the next
  page makes no HubSpot requests. In a fresh profile, Notice > OK: `rt_consent=analytics` with a
  12-month expiry. Notice > Cookie settings opens the panel with the box ticked.
- [ ] (u4) Global Privacy Control on (Brave, or Firefox with GPC enabled), clean profile: the
  opt-in banner shows, not the notice; the default stays `denied` with no update; no HubSpot,
  LinkedIn or Apollo requests. Accept in that banner: they load, and `rt_consent_gpc=1` is set.
- [ ] (u5) GPC against an earlier OK: in a profile where you clicked OK on the notice, turn GPC
  on and reload: the opt-in banner shows, the default is `denied`, and `_ga*` and the HubSpot
  cookies are gone; `rt_consent=analytics` is still there.
- [ ] (u6) Session re-check: set `rt_region=DE` on the trace page as below but do **not** set
  `rt_region_checked`, then open the home page: the opt-in banner shows first, one request to
  `/cdn-cgi/trace` follows, `rt_region` becomes `US`, `dataLayer` gets one update to `granted`,
  and the notice replaces the banner. (We cannot test the US-to-elsewhere direction from here; the
  node test covers it.)
- [ ] (u7) Footer: "Do Not Sell or Share My Personal Information" is in the footer legal row on
  every page and opens the preference panel.

Outside the US (`rt_region=DE` set by hand as above), then repeat with `rt_region=XX`:

- [ ] (a) Before any choice: no requests to `js-na2.hs-scripts.com`, `hs-analytics.net`,
  `hscollectedforms.net` or `hs-banner.com`, and no `__hstc` or `hubspotutk` cookie. The banner
  shows Accept and Reject the same size and style, and Settings as a text link.
- [ ] (b) Reject: still none, across two page loads.
- [ ] (c) Accept, on the same page: the loader is requested on that page (this proves GTM fires a
  consent-blocked tag after the banner's consent update), `__hstc` and `hubspotutk` are set on
  `.realtreasury.com`, and no HubSpot banner appears. Note each HubSpot cookie's name and expiry
  and compare them with the Cookie Policy table.

Either path:

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
- [ ] (h) `curl` both policy pages and find `HubSpot`, `Global Privacy Control` and (Cookie
  Policy) `rt_region` and `rt_consent_gpc` in each.

To switch tracking off: pause the tag in GTM and publish the container. Nothing in the theme
loads HubSpot, so no deploy is needed; the policies then over-disclose until they are edited.
