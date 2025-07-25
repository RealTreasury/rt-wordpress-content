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

## Portal Access Gate

The repository includes the **Treasury Portal Access** plugin (`plugins/treasury-portal-access`) used to protect the Treasury Tech Portal page.

### Setup

1. Install and activate **Contact Form 7** and **Treasury Portal Access** in WordPress.
2. Go to **Portal Access → Settings** and choose the Contact Form 7 form that will grant portal access (this stores the form ID used by the plugin).
3. Place the `[portal_button]` shortcode on the portal page (and wrap protected sections in `[protected_content]...[/protected_content]`). Alternatively link directly to the modal with `<a href="#openPortalModal">Access Portal</a>` (case sensitive; a lowercase alias `#openportalmodal` also works).
4. If you rely on the fallback gate instead of the plugin, edit `assets/php/functions.php` and replace `YOUR_FORM_ID_HERE` with your form ID.
5. Run `npm run format` before committing to apply Prettier formatting.

### Testing

1. Visit the portal page in a private or incognito window.
2. Submit the access form and verify you are redirected to your portal page.
3. Refresh the page to confirm protected content remains visible while the cookie is active.
4. Click the original portal button again and ensure it now navigates directly to the portal instead of reopening the form.

### Abandoned Attempt Tracking

As of plugin version 1.0.7 the portal records when a visitor opens the access modal but leaves without submitting the form. These entries appear on the Portal Access admin screen so you can gauge interest in the portal.

Version 1.0.9 adds weekly metrics showing the abandonment rate for both the current and previous week.

## Clean Media URLs Plugin

The repository also includes the **Clean Media URLs** plugin (`plugins/clean-media-urls`). Activate it in WordPress to automatically sanitize media filenames so URLs contain only lowercase letters, numbers, and hyphens.

## Treasury Tech Portal

Use the **Treasury Tech Portal** plugin (`plugins/treasury-tech-portal`) to embed the portal on any WordPress page.

### Installation

1. Copy the `treasury-tech-portal` directory into your site's `wp-content/plugins/` folder.
2. Activate **Treasury Tech Portal** from the WordPress Plugins screen.

### Shortcode

Place `[treasury_portal]` on a page or post to display the portal. The shortcode automatically enqueues `treasury-portal.css` and `treasury-portal.js` so no extra asset management is required.

### Troubleshooting

Some browsers or ad-blocking extensions may block requests to `*.wp.com`, resulting in `ERR_BLOCKED_BY_CLIENT` messages in the developer console. These errors are harmless and the portal will continue to function normally. If the messages are distracting, whitelist the site in your browser or ad-blocker to remove them.
