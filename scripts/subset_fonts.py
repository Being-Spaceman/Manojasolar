#!/usr/bin/env python3
"""
Subset and self-host the webfonts for manoja-solar.

Why unicode-range subsetting and not --text: Devanagari conjuncts (क्ष, ज्ञ, ऱ्ह) and
the eyelash-ल are reached through GSUB substitutions, not codepoints. Subsetting to the
exact characters in today's Marathi copy would silently drop the glyphs a *future* string
needs. We keep the whole Devanagari block plus the layout features, so copy can change
without anyone remembering to re-run this.

    python scripts/subset_fonts.py            # download + subset -> public/fonts
    python scripts/subset_fonts.py --verify   # assert every glyph used in src/i18n is covered

--verify runs as part of `npm run build`, so a Marathi string containing a glyph the
subset cannot render fails the build instead of rendering as tofu in production.
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
import urllib.request
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CACHE = ROOT / "scripts" / ".fontsrc"
OUT = ROOT / "public" / "fonts"
I18N = ROOT / "src" / "i18n"

GF = "https://raw.githubusercontent.com/google/fonts/main"

# family key -> (source url, output basename, css weight)
#
# Three Mukta weights, not the design's four. A Devanagari weight costs ~70 KB subsetted,
# and the design's 700 (CTA labels, step titles) is indistinguishable from 600/800 at the
# 17-19 px these appear at on a phone. 700 folds into 600 for step titles and 800 for CTAs
# — a bolder CTA is no loss. That buys back 70 KB on a 4G budget.
FONTS = {
    "mukta-400": (f"{GF}/ofl/mukta/Mukta-Regular.ttf", "mukta-400", 400),
    "mukta-600": (f"{GF}/ofl/mukta/Mukta-SemiBold.ttf", "mukta-600", 600),
    "mukta-800": (f"{GF}/ofl/mukta/Mukta-ExtraBold.ttf", "mukta-800", 800),
    "plexmono-400": (
        f"{GF}/ofl/ibmplexmono/IBMPlexMono-Regular.ttf",
        "plexmono-400",
        400,
    ),
}

# Devanagari families carry both scripts; the mono is Latin-only.
DEVANAGARI_RANGES = [
    (0x0900, 0x097F),  # Devanagari, incl. ॐ, danda ।॥ and numerals ०-९
    (0x200C, 0x200D),  # ZWNJ / ZWJ — control half-forms and conjunct breaking
    (0x25CC, 0x25CC),  # dotted circle, so a broken cluster is visible not invisible
]

LATIN_RANGES = [
    (0x0020, 0x007E),  # basic Latin
    (0x00A0, 0x00A0),  # nbsp
    (0x00A9, 0x00A9),  # ©  footer copyright
    (0x00B7, 0x00B7),  # ·  used as a separator throughout the design
    (0x2013, 0x2014),  # – —
    (0x2018, 0x201D),  # ' ' " "
    (0x2022, 0x2022),  # •
    (0x2026, 0x2026),  # …
    (0x20B9, 0x20B9),  # ₹
    (0x2212, 0x2212),  # − minus, used in the subsidy table
]

LATIN_ONLY = {"plexmono-400"}

# Codepoints that only ever appear inside Devanagari text. The Latin-only mono face is
# not expected to carry them — ZWJ in particular shows up in व्हॉट्सअ‍ॅप (WhatsApp), which
# is the design's own spelling.
DEVA_CONTEXT = set(range(0x0900, 0x0980)) | {0x200C, 0x200D, 0x25CC}


def ranges_for(key: str) -> list[tuple[int, int]]:
    if key in LATIN_ONLY:
        return LATIN_RANGES
    return LATIN_RANGES + DEVANAGARI_RANGES


def unicodes_for(key: str) -> set[int]:
    out: set[int] = set()
    for lo, hi in ranges_for(key):
        out.update(range(lo, hi + 1))
    return out


def download(url: str, dest: Path) -> Path:
    if dest.exists():
        return dest
    dest.parent.mkdir(parents=True, exist_ok=True)
    print(f"  fetching {url.rsplit('/', 1)[-1]}")
    req = urllib.request.Request(url, headers={"User-Agent": "manoja-solar-build"})
    with urllib.request.urlopen(req, timeout=90) as r:
        dest.write_bytes(r.read())
    return dest


def subset_all() -> int:
    from fontTools import subset

    OUT.mkdir(parents=True, exist_ok=True)
    total = 0

    for key, (url, base, _weight) in FONTS.items():
        src = download(url, CACHE / f"{base}.ttf")
        dst = OUT / f"{base}.woff2"

        opts = subset.Options()
        opts.flavor = "woff2"
        opts.with_zopfli = True
        opts.desubroutinize = False
        opts.hinting = False
        opts.drop_tables += ["DSIG"]
        opts.notdef_outline = True
        # Keep every OpenType feature: Indic shaping is entirely GSUB/GPOS driven and
        # pruning features here is how eyelash-ल and the ऱ्ह conjunct quietly disappear.
        opts.layout_features = ["*"]
        opts.name_IDs = ["*"]
        opts.name_legacy = True
        opts.recommended_glyphs = True

        font = subset.load_font(str(src), opts)
        subsetter = subset.Subsetter(options=opts)
        subsetter.populate(unicodes=unicodes_for(key))
        subsetter.subset(font)
        subset.save_font(font, str(dst), opts)
        font.close()

        kb = dst.stat().st_size / 1024
        total += dst.stat().st_size
        src_kb = src.stat().st_size / 1024
        print(f"  {dst.name:<22} {kb:6.1f} KB   (from {src_kb:6.1f} KB)")

    print(f"\n  total shipped font weight: {total / 1024:.1f} KB")
    return 0


# --------------------------------------------------------------------------- verify

# Skip i18n values that are not rendered as text on the page.
NON_TEXT_KEY = re.compile(r"(^|\.)(href|url|src|id|_comment|lang|dir|locale)$", re.I)


def walk(node, prefix: str = ""):
    if isinstance(node, dict):
        for k, v in node.items():
            yield from walk(v, f"{prefix}.{k}" if prefix else k)
    elif isinstance(node, list):
        for i, v in enumerate(node):
            yield from walk(v, f"{prefix}[{i}]")
    elif isinstance(node, str):
        yield prefix, node


def verify() -> int:
    from fontTools.ttLib import TTFont

    locale_files = sorted(I18N.glob("*.json"))
    if not locale_files:
        print("  no i18n files yet — nothing to verify")
        return 0

    # Every codepoint the copy actually uses, with where it came from.
    used: dict[int, tuple[str, str]] = {}
    strings = 0
    for path in locale_files:
        data = json.loads(path.read_text(encoding="utf-8"))
        for key, value in walk(data):
            if NON_TEXT_KEY.search(key):
                continue
            strings += 1
            for ch in value:
                cp = ord(ch)
                if cp < 0x0020:  # newlines, tabs — never rendered as glyphs
                    continue
                used.setdefault(cp, (path.name, key))

    missing: dict[str, list[str]] = {}

    for fkey, (_url, base, _w) in FONTS.items():
        woff = OUT / f"{base}.woff2"
        if not woff.exists():
            print(f"  MISSING BUILD ARTEFACT: {woff.relative_to(ROOT)}")
            print("  run: npm run fonts:subset")
            return 1

        font = TTFont(str(woff))
        cmap = set(font.getBestCmap().keys())
        font.close()

        for cp, (fname, key) in sorted(used.items()):
            # The mono face is Latin-only by design: Devanagari, its joiners and its
            # dotted-circle placeholder are never set in it, so their absence is correct.
            if fkey in LATIN_ONLY and cp in DEVA_CONTEXT:
                continue
            if cp not in cmap:
                name = unicodedata.name(chr(cp), "?")
                missing.setdefault(fkey, []).append(
                    f"{fname}  {key}  ->  U+{cp:04X} {name} ({chr(cp)})"
                )

    if missing:
        print("\n  FONT COVERAGE FAILURE\n")
        for fkey, items in missing.items():
            print(f"  {fkey}:")
            for line in items:
                print(f"    {line}")
        print("\n  Widen the ranges in scripts/subset_fonts.py, then: npm run fonts:subset")
        return 1

    print(
        f"  font coverage OK — {strings} strings, {len(used)} distinct glyphs "
        f"across {len(locale_files)} locales, all present in the subsets"
    )
    return 0


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--verify", action="store_true", help="check i18n glyph coverage only")
    args = ap.parse_args()
    if args.verify:
        return verify()
    print("subsetting fonts:")
    return subset_all()


if __name__ == "__main__":
    sys.exit(main())
