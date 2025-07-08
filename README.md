# Real Treasury Static Content

This repository contains the static HTML content for the Real Treasury website.

## Building HTML

Insight pages are maintained in the `templates/` directory and use [EJS](https://ejs.co/) templates. The shared hero markup lives in `templates/partials/insight-hero.html` and is included by each insight template. To generate the final static HTML run:

```bash
npm install
npm run build
```

The `build` script renders every template in `templates/` and writes the resulting HTML files back into the repository structure.

The compiled pages live in the `insights/` directory. These HTML files are standalone and can be pasted directly into WordPress. They are generated from EJS templates that rely on shared partials, so remember to run `npm run build` whenever a template or partial changes.

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
3. Place the `[portal_button]` shortcode on the portal page (and wrap protected sections in `[protected_content]...[/protected_content]`).
4. If you rely on the fallback gate instead of the plugin, edit `assets/php/functions.php` and replace `YOUR_FORM_ID_HERE` with your form ID.

### Testing

1. Visit the portal page in a private or incognito window.
2. Submit the access form and verify you are redirected to your portal page.
3. Refresh the page to confirm protected content remains visible while the cookie is active.
