# Publishing repo pages as native WordPress content

## Why

These pages reached realtreasury.com inside a cross-origin `<iframe>` pointing at
`realtreasury.github.io`. Crawlers do not pull a cross-origin frame's text into the
parent document, so the post shipped an iframe wrapper and nothing else.

Measured on the live site before this change — the site chrome (banner, nav, footer)
is ~430 words on every page, so "431 words" means the page had none of its own:

| page | words Google read | `<h1>` | post_content |
|---|---|---|---|
| `/real-treasury-explained/` | 431 | none | 676 bytes of iframe wrapper |
| `/payment-networks-explained/` | 431 | none | 679 bytes |
| `/data-flows-of-a-tms/` | 435 | none | 666 bytes |

## What the publisher does

    scripts/wp_publish_post.py plan    <slug>
    scripts/wp_publish_post.py publish <slug>
    scripts/wp_publish_post.py restore <slug> <backup-file>

`wp/pages.tsv` maps slug → post id → source page.

It replaces **only** the `core/html` block holding the iframe — or, on later runs, only
the region between the `rt:page-content` markers. The header pattern (`wp:block` ref 183),
the mid-page pattern (1562) and the footer pattern (398) are preserved byte for byte, and
the publish refuses if the set of pattern refs would change. Replacing whole post content
is how you silently drop the nav and footer.

## The part that needs care: CSS scoping

Inside an iframe the page's stylesheet was isolated. Injected into the site it is not, and
these pages use `.hero-section`, `.stat-item`, `.btn-primary` — names the Astra theme and
`assets/css/shared.css` also use. So `scripts/lib/page_to_block.py` rewrites every selector
under `.rt-page--<slug>` and wraps the body in that class, which makes the injection inert
in both directions.

The selector rewrite is a single pass tracking comments, strings and at-rule nesting, not a
regex. Two traps it handles, both found by checking output rather than trusting the transform:

- **A comment above a rule is part of its head.** `/* Hero Section */` before `.hero-section`
  scoped as one selector and turned `.hero-section` into a descendant of the wrapper — the
  rule still parses, still looks right, and matches nothing.
- **Root selectors must not be scoped.** `html`, `body` and `:root` become the wrapper itself;
  prefixing them would match nothing and drop the rule silently.

## Not converted to Gutenberg blocks, and why

These pages are design-led — 62 divs, custom classes, inline SVG. Turning them into
`wp:heading`/`wp:paragraph` is a per-page redesign, not a transform, and it is not what
makes them crawlable: a `core/html` block is rendered server-side into the post's HTML, so
the text is in the page either way. Semantic blocks are a better long-term home (see
`/cash-tools-explained/`, which was hand-converted) but they are a separate decision.

## Safety

Every publish writes the current `post_content` to `~/wp-post-backups/` **before** the write,
reads the post back afterwards, and refuses if the read-back does not match what was sent.
WordPress revisions are on and unlimited, so `restore` is a second way back.

## Still iframed

20 posts carried a github.io iframe when this landed. Three are converted. The rest fall into
two groups:

- **Plain content pages** — the same transform should work; each needs its source checked for
  duplicate nav/footer chrome first.
- **RT Gate pages** (`RTG_CONFIG` present: `tms-rfp-trap`, `on-demand-workshop`,
  `on-demand-workshop-emea`, `err-not-demo-script`, `prompt-to-product`, the guide waitlist)
  — these build their content with JS and post to the gate API. Converting one risks lead
  capture and needs the gate exercised end to end first. Not attempted here.
