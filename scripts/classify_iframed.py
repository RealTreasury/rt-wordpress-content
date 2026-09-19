#!/usr/bin/env python3
"""Decide which iframed posts are safe to serve natively, and say why for the rest.

Three things disqualify a page, each learned the hard way:

  gated   - RT Gate builds the page and its form feeds lead capture. Converting one
            risks the gate silently. `<form>` alone is not the test: tms-rfp-trap
            declares RTG_CONFIG and builds its form in JS.
  stub    - the repo "page" is a placeholder, not content. webinar/gated-video is
            421 bytes; publishing it would replace a working page with nothing.
  no-h1   - nothing to promote as the page heading, which is most of the point.
"""
from __future__ import annotations
import re, sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MIN_BYTES = 4000
MIN_WORDS = 100
GATE = re.compile(r"RTG_CONFIG|rtGv[A-Z]|rt-gate|wpcf7", re.I)


def classify(src: Path) -> tuple[str, str, int, int]:
    if not src.exists():
        return "missing", "no such file in this repo", 0, 0
    h = src.read_text(encoding="utf-8", errors="replace")
    m = re.search(r"<body[^>]*>([\s\S]*)</body>", h, re.I)
    body = m.group(1) if m else h
    clean = re.sub(r"<(script|style)[\s\S]*?</\1>", " ", body, flags=re.I)
    words = len(re.sub(r"\s+", " ", re.sub(r"<[^>]+>", " ", clean)).split())
    h1 = len(re.findall(r"<h1[\s>]", h, re.I))
    if GATE.search(h):
        return "gated", "RT Gate / form page", words, h1
    if len(h) < MIN_BYTES or words < MIN_WORDS:
        return "stub", f"only {len(h)} bytes / {words} words", words, h1
    if h1 == 0:
        return "no-h1", "no <h1> to promote", words, h1
    return "safe", "", words, h1


def main(argv: list[str]) -> int:
    rows = [l.split("\t") for l in Path(argv[1]).read_text(encoding="utf-8").strip().splitlines() if l.strip()]
    buckets: dict[str, list] = {}
    for pid, slug, raw in rows:
        src = raw.split("?")[0]
        if not src.endswith(".html"):
            src = src.rstrip("/") + "/index.html"
        verdict, why, words, h1 = classify(ROOT / src)
        buckets.setdefault(verdict, []).append((int(pid), slug, src, words, why))
    for verdict in ("safe", "gated", "stub", "no-h1", "missing"):
        rows_v = sorted(buckets.get(verdict, []), key=lambda r: -r[3])
        if not rows_v:
            continue
        print(f"=== {verdict.upper()} ({len(rows_v)}) ===")
        for pid, slug, src, words, why in rows_v:
            print(f"  {pid:<6} {slug:<38} {words:>5}w  {src}{'  -- ' + why if why else ''}")
    safe = sorted(buckets.get("safe", []), key=lambda r: -r[3])
    Path(ROOT / "wp" / "pages.tsv").write_text(
        "# slug\tpost_id\tsource page in this repo\n"
        + "".join(f"{slug}\t{pid}\t{src}\n" for pid, slug, src, _, _ in safe),
        encoding="utf-8")
    print(f"\nwrote wp/pages.tsv with {len(safe)} convertible pages "
          f"({sum(r[3] for r in safe):,} words currently invisible to search)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main(sys.argv))
