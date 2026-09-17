# Real Treasury Static Content

This repository contains the static HTML content for the Real Treasury website.

This project uses **Node 18**, matching the version used in CI. If you use
[nvm](https://github.com/nvm-sh/nvm), running `nvm use` will activate the correct
version as defined in `.nvmrc`.

## Building HTML

Insight pages are maintained in the `templates/` directory and use [EJS](https://ejs.co/) templates. The shared hero markup lives in `templates/partials/insight-hero.html` and is included by each insight template. To generate the final static HTML run:

```bash
npm install
npm run build
```

The `build` script renders every template in `templates/` and writes the resulting HTML files back into the repository structure.

The compiled pages live in the `insights/` directory. These HTML files are standalone and can be pasted directly into WordPress. They are generated from EJS templates that rely on shared partials, so remember to run `npm run build` whenever a template or partial changes.

## Previewing pages locally

Run the development server to serve the compiled static files:

```bash
npm run serve
```

This command starts [http-server](https://www.npmjs.com/package/http-server) on port `8080`. Open <http://localhost:8080> in your browser to preview the generated pages.

## WordPress Additional CSS

The file `assets/css/shared.css` is version controlled here but is not loaded by
the theme. It lives in **Appearance → Customize → Additional CSS** and is
published there from the rt-ai-02 box over SSH and WP-CLI:

```bash
scripts/wp_publish_shared_css.sh plan       # read-only: hashes, drift, diff
scripts/wp_publish_shared_css.sh publish    # after the change is merged to main (enforced)
```

`plan` shows the diff between the live CSS and the file. `publish` refuses if the
file has uncommitted changes, if the live CSS is not what the script last
published (someone edited it in WordPress; read the diff, then `--accept-drift`),
or if nothing changed. It writes through core `wp_update_custom_css_post()`, reads
the post back and compares bytes, records the hash in
`assets/css/shared.css.published.sha256` (commit that file), and checks the home
page serves the new CSS. Credentials come from `/opt/rt-ai/secrets/wpcom-ssh.env`.
First-time adoption on a site: `baseline` records the current live hash.
`npm run test:publish-css` runs that whole state machine against a stub remote — no SSH, no network, no WordPress — and runs in CI.

Host key: the connection only trusts the key in `scripts/wpcom_known_hosts`
(`StrictHostKeyChecking=yes`, and `GlobalKnownHostsFile=/dev/null` so `/etc/ssh/ssh_known_hosts`
cannot satisfy it instead). Rotate it deliberately: `ssh-keyscan -t ed25519 ssh.wp.com`,
compare the fingerprint with a connection you already trust, commit the new line.

Before the write, `publish` sends a throwaway script over the same `wp eval-file -`
invocation and checks the token comes back. `wp eval-file -` has read STDIN since 2018, but
nothing here has run against the real host yet, and a probe that fails gives a named reason
instead of a confusing mid-publish error.

Merge guard: `publish` compares the file with `origin/main` and refuses a committed but
unmerged change, so the receipt always names a commit that reached `main`.
`--allow-unmerged` overrides for an emergency.

Tests: `scripts/tests/test_wp_publish_shared_css.sh` runs plan, baseline, publish, no-op,
drift refusal, unmerged refusal, both overrides and the missing-host-key refusal against a
stub remote. No network, no credentials.

Fallback if the box is down: copy the file's text into Additional CSS by hand and
click **Publish**, then run `baseline` when the box is back.

The earlier least-privilege design in `docs/shared-css-publishing.md` (PR #887)
was superseded on September 14, 2026 by the owner's decision to hold a site SSH
key on the box.

## Gated content — RT Gate

All new gated forms (waitlists, video unlocks, downloads) use the **RT Gate**
WordPress plugin and the `window.RTG_CONFIG` pattern. See
[`docs/rt-gate.md`](docs/rt-gate.md) for the integration guide and
[`templates/partials/gated-video.html`](templates/partials/gated-video.html)
for the reference client implementation.

### Portal Access Gate (Deprecated)

> **This plugin is no longer in active use.** The `plugins/treasury-portal-access` directory is kept for historical reference only. Do not modify it or add new features to it. New gated pages must use RT Gate (see above).

## Clean Media URLs Plugin

The repository also includes the **Clean Media URLs** plugin (`plugins/clean-media-urls`). Activate it in WordPress to automatically sanitize media filenames so URLs contain only lowercase letters, numbers, and hyphens.

### Troubleshooting

Some browsers or ad-blocking extensions may block requests to `*.wp.com`, resulting in `ERR_BLOCKED_BY_CLIENT` messages in the developer console. These errors are harmless and the portal will continue to function normally. If the messages are distracting, whitelist the site in your browser or ad-blocker to remove them.


## Webinar publishing

Webinar content is maintained in this repo and published as WordPress Posts. See `docs/webinar-publishing.md` for the taxonomy contract and pre-publish QA checklist.
