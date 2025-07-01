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

The WordPress theme previously enqueued `assets/css/shared.css`. After removing
that function, copy the contents of `assets/css/shared.css` into the
WordPress **Additional CSS** area (Appearance → Customize → Additional CSS) to
retain the same styling.
