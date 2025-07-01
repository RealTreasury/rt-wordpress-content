# Real Treasury Static Content

This repository contains the static HTML content for the Real Treasury website.

## Building HTML

Insight pages are maintained in the `templates/` directory and use [EJS](https://ejs.co/) templates. The shared hero markup lives in `templates/partials/insight-hero.html` and is included by each insight template. To generate the final static HTML run:

```bash
npm install
npm run build
```

The `build` script renders every template in `templates/` and writes the resulting HTML files back into the repository structure.

## WordPress Additional CSS

The file `assets/css/shared.css` is version controlled in this repository but is
no longer loaded automatically by the theme. To keep these shared styles active,
manually copy the file contents into WordPress:

1. Open `assets/css/shared.css` and copy all of its text.
2. In the WordPress admin go to **Appearance → Customize → Additional CSS**.
3. Paste the CSS into the editor and click **Publish**.

Repeat these steps whenever `shared.css` changes in Git.
