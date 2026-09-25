#!/usr/bin/env python3
"""Turn a standalone repo page into WordPress post content that Google can read.

WHY THIS EXISTS. These pages were reaching the site inside a cross-origin
<iframe> pointing at realtreasury.github.io. Crawlers do not pull a cross-origin
frame's text into the parent document, so `/real-treasury-explained/` shipped 676
bytes of iframe wrapper, no <h1>, and none of its 698 words. Measured on the live
site, the four iframed posts each carried ~430 words -- which is the header, nav
and footer, i.e. nothing of their own.

WHAT IT DOES NOT DO. It does not convert the markup to Gutenberg blocks. These
pages are design-led -- 62 divs, custom classes, inline SVG -- and turning them
into wp:heading/wp:paragraph would mean redesigning every one. A core/html block
is rendered SERVER-SIDE into the post's HTML, so the text is in the page for a
crawler either way. Semantic blocks are a better long-term home; they are a
per-page redesign, not a transform, and they are not what makes this crawlable.

THE PART THAT NEEDS CARE: CSS SCOPING. Inside an iframe the page's stylesheet was
isolated. Injected into the site it is not, and these pages use names like
.hero-section, .stat-item and .btn-primary that the theme and shared.css also use.
So every selector is rewritten under a per-page wrapper class, and the body is
wrapped in it. That makes the injection inert in both directions: the page cannot
restyle the site, and the site cannot restyle the page.

The selector rewrite is a single pass that tracks comments, strings and at-rule
nesting -- NOT a regex. A regex pre-pass over CSS is how you get one unpaired
quote eating four hundred lines; that exact failure was found and recorded in
scripts/tests/test_shared_css_prune.js, and this file does not repeat it.
"""
from __future__ import annotations

import re
import sys
from pathlib import Path

# Selectors that must NOT be scoped: they target the document, not page content.
# Rewriting `html` to `.rt-page-x html` matches nothing and silently drops the rule.
ROOT_SELECTORS = {"html", "body", ":root", "*", "html body"}
# At-rules whose bodies are not selectors (keyframe steps, font descriptors).
OPAQUE_AT = re.compile(
    r"^@(-\w+-)?(keyframes|font-face|page|property|counter-style|font-feature-values)\b")


def _skip_literal(css: str, i: int) -> int:
    """Index just past the comment or quoted string starting at i, else i+1."""
    ch = css[i]
    if ch == "/" and css[i + 1:i + 2] == "*":
        end = css.find("*/", i + 2)
        return len(css) if end == -1 else end + 2
    if ch in "\"'":
        j = i + 1
        while j < len(css):
            if css[j] == "\\":
                j += 2
                continue
            if css[j] == ch:
                return j + 1
            j += 1
        return len(css)          # unterminated: consume the rest, never re-pair
    return i + 1


def _block_end(css: str, i: int) -> int:
    """Index OF the '}' closing the '{' at i."""
    depth = 0
    while i < len(css):
        ch = css[i]
        if ch == "/" and css[i + 1:i + 2] == "*":
            i = _skip_literal(css, i)
            continue
        if ch in "\"'":
            i = _skip_literal(css, i)
            continue
        if ch == "{":
            depth += 1
        elif ch == "}":
            depth -= 1
            if depth == 0:
                return i
        i += 1
    return len(css) - 1


def strip_comments(text: str) -> str:
    """Remove /* ... */ from a rule head.

    The head is the raw text between the previous '}' and this '{', so a comment
    written above a rule is part of it. Left in, `.a /* note */ .b` scopes as one
    selector and `.b` silently becomes a descendant of `.a` -- the rule still
    parses, still looks plausible, and matches nothing. Found exactly that way on
    `/* Hero Section */` above `.hero-section`.
    """
    out, i = [], 0
    while i < len(text):
        ch = text[i]
        if ch == "/" and text[i + 1:i + 2] == "*":
            i = _skip_literal(text, i)
            out.append(" ")
            continue
        if ch in "\"'":
            j = _skip_literal(text, i)
            out.append(text[i:j])
            i = j
            continue
        out.append(ch)
        i += 1
    return "".join(out)


def scope_selector(selector: str, wrapper: str) -> str:
    """Prefix one comma-free selector with `wrapper`, leaving root selectors alone."""
    sel = selector.strip()
    if not sel:
        return sel
    low = sel.lower()
    if low in ROOT_SELECTORS:
        # The wrapper stands in for the document for these.
        return wrapper
    if low.startswith(("html ", "body ")):
        return f"{wrapper} {sel.split(' ', 1)[1].strip()}"
    if sel.startswith(("&", wrapper)):
        return sel
    return f"{wrapper} {sel}"


def scope_css(css: str, wrapper: str) -> str:
    """Rewrite every selector in `css` to sit under `wrapper`."""
    out: list[str] = []
    i = 0
    head_start = 0
    while i < len(css):
        ch = css[i]
        if ch == "/" and css[i + 1:i + 2] == "*":
            i = _skip_literal(css, i)
            continue
        if ch in "\"'":
            i = _skip_literal(css, i)
            continue
        if ch == "{":
            head = css[head_start:i]
            stripped = strip_comments(head).strip()
            if stripped.startswith("@"):
                if OPAQUE_AT.match(stripped):
                    end = _block_end(css, i)
                    out.append(css[head_start:end + 1])   # verbatim, steps are not selectors
                    i = head_start = end + 1
                    continue
                # Conditional at-rule (@media/@supports/@layer/@container): keep the
                # head, recurse into the body so the selectors inside get scoped.
                end = _block_end(css, i)
                out.append(head + "{" + scope_css(css[i + 1:end], wrapper) + "}")
                i = head_start = end + 1
                continue
            end = _block_end(css, i)
            lead = head[:len(head) - len(head.lstrip())]
            stripped = strip_comments(stripped).strip()
            scoped = ", ".join(scope_selector(s, wrapper) for s in stripped.split(",") if s.strip())
            out.append(f"{lead}{scoped} {{{css[i + 1:end]}}}")
            i = head_start = end + 1
            continue
        i += 1
    out.append(css[head_start:])
    return "".join(out)


def extract(html: str) -> tuple[str, str]:
    """Return (page css, body inner html) for a standalone page."""
    body_match = re.search(r"<body[^>]*>([\s\S]*)</body>", html, re.I)
    body = body_match.group(1) if body_match else html
    css_parts = re.findall(r"<style[^>]*>([\s\S]*?)</style>", html, re.I)
    # <style> that lived in <body> is pulled out with the rest; leaving a copy behind
    # would apply it unscoped.
    body = re.sub(r"<style[^>]*>[\s\S]*?</style>", "", body, flags=re.I)
    return "\n".join(css_parts), body


START = "<!-- rt:page-content {slug} -->"
END = "<!-- /rt:page-content -->"


def render(html: str, slug: str) -> str:
    """The core/html block that replaces the iframe, delimited for later splicing."""
    css, body = extract(html)
    wrapper_class = f"rt-page rt-page--{slug}"
    scoped = scope_css(css, f".rt-page--{slug}") if css.strip() else ""
    inner = []
    if scoped.strip():
        inner.append(f"<style>\n{scoped.strip()}\n</style>")
    inner.append(f'<div class="{wrapper_class}">{body.rstrip()}\n</div>')
    return "\n".join([
        START.format(slug=slug),
        "<!-- wp:html -->",
        "\n".join(inner),
        "<!-- /wp:html -->",
        END,
    ])


def main(argv: list[str]) -> int:
    if len(argv) != 3:
        print("usage: page_to_block.py <page.html> <slug>", file=sys.stderr)
        return 2
    sys.stdout.write(render(Path(argv[1]).read_text(encoding="utf-8"), argv[2]))
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
