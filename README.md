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

The file `assets/css/shared.css` is version controlled in this repository but is
no longer loaded automatically by the theme. To keep these shared styles active,
manually copy the file contents into WordPress:

1. Open `assets/css/shared.css` and copy all of its text.
2. In the WordPress admin go to **Appearance → Customize → Additional CSS**.
3. Paste the CSS into the editor and click **Publish**.

Repeat these steps whenever `shared.css` changes in Git.

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
