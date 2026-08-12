#!/usr/bin/env python3
"""
Generate the static brand images that the HTML references:

    public/og-default.png    1200x630  social preview (WhatsApp, Facebook, X)
    public/apple-touch-icon.png  180x180  iOS home-screen icon

Rendered by headless Chrome rather than drawn with Pillow, because Pillow only
shapes Devanagari correctly when it was built against libraqm — and the whole point
of the OG image is that मनोजा एजन्सीज renders with proper conjuncts and matras. Chrome
already has the shaping engine and we already ship the exact subsetted webfont, so
the image is guaranteed to match what the site itself renders.

The OG image matters more than usual here: WhatsApp is the primary channel, and a
shared link renders this card in the chat.

    python scripts/make_images.py
"""

from __future__ import annotations

import base64
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
FONTS = ROOT / "public" / "fonts"
OUT = ROOT / "public"

CHROME_CANDIDATES = [
    Path(r"C:\Program Files\Google\Chrome\Application\chrome.exe"),
    Path(r"C:\Program Files (x86)\Google\Chrome\Application\chrome.exe"),
    Path.home() / r"AppData\Local\Google\Chrome\Application\chrome.exe",
    Path(r"C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe"),
    Path(r"C:\Program Files\Microsoft\Edge\Application\msedge.exe"),
]

GREEN = "#007B3C"
FOREST = "#04492A"


def find_chrome() -> Path:
    for p in CHROME_CANDIDATES:
        if p.exists():
            return p
    found = shutil.which("chrome") or shutil.which("msedge") or shutil.which("chromium")
    if found:
        return Path(found)
    print("  no Chrome or Edge found — cannot render images", file=sys.stderr)
    sys.exit(1)


def font_face(name: str, weight: int, file: str) -> str:
    """Inline the woff2 as a data URI so file:// rendering never hits a CORS wall."""
    data = base64.b64encode((FONTS / file).read_bytes()).decode("ascii")
    return f"""
    @font-face {{
      font-family: "{name}";
      font-weight: {weight};
      font-display: block;
      src: url(data:font/woff2;base64,{data}) format("woff2");
    }}"""


def shell(body: str, css: str) -> str:
    faces = (
        font_face("Mukta", 400, "mukta-400.woff2")
        + font_face("Mukta", 600, "mukta-600.woff2")
        + font_face("Mukta", 800, "mukta-800.woff2")
        + font_face("Plex Mono", 400, "plexmono-400.woff2")
    )
    return f"""<!doctype html>
<html lang="mr"><head><meta charset="utf-8"><style>
  {faces}
  * {{ margin:0; padding:0; box-sizing:border-box; }}
  body {{ font-family:"Mukta",sans-serif; -webkit-font-smoothing:antialiased; }}
  {css}
</style></head><body>{body}</body></html>"""


# --------------------------------------------------------------------------- og

OG_BODY = """
<div class="card">
  <div class="top">
    <span class="tile">म</span>
    <span class="names">
      <span class="deva">मनोजा एजन्सीज</span>
      <span class="latin">MANOJA AGENCIES</span>
    </span>
  </div>

  <h1>वीजबिल ₹३,०००?<br>आता ₹० करा.</h1>
  <p class="sub">घर आणि दुकानासाठी वारी छतावरील सोलर — अनुदानासह.</p>

  <div class="foot">
    <span class="partner">अधिकृत चॅनल पार्टनर — वारी एनर्जीज</span>
    <span class="city">लातूर, महाराष्ट्र</span>
  </div>
</div>
"""

OG_CSS = f"""
  body {{ width:1200px; height:630px; }}
  .card {{
    background:{FOREST}; color:#fff; display:flex; flex-direction:column;
    height:630px; padding:64px 72px; width:1200px;
  }}
  .top {{ align-items:center; display:flex; gap:18px; }}
  .tile {{
    align-items:center; background:#fff; color:{FOREST}; display:flex;
    font-size:44px; font-weight:800; height:64px; justify-content:center; width:64px;
  }}
  .names {{ display:flex; flex-direction:column; }}
  .deva {{ font-size:34px; font-weight:800; line-height:1.2; }}
  .latin {{
    color:rgba(255,255,255,.72); font-size:15px; font-weight:600; letter-spacing:.22em;
  }}
  h1 {{ font-size:88px; font-weight:800; line-height:1.22; margin-top:auto; }}
  .sub {{
    color:rgba(255,255,255,.88); font-size:31px; margin-top:20px;
  }}
  .foot {{
    align-items:center; border-top:2px solid rgba(255,255,255,.22); display:flex;
    justify-content:space-between; margin-top:36px; padding-top:26px;
  }}
  .partner {{ font-size:24px; font-weight:600; }}
  /* Mukta, not the mono: Plex Mono is Latin-only by design, so Devanagari set in it
     silently falls back to a system face with different metrics. */
  .city {{
    color:rgba(255,255,255,.7); font-size:22px; font-weight:600;
  }}
"""

# -------------------------------------------------------------------------- icon

ICON_BODY = '<div class="ico"><span>म</span></div>'
ICON_CSS = f"""
  body {{ width:180px; height:180px; }}
  .ico {{
    align-items:center; background:{GREEN}; display:flex; height:180px;
    justify-content:center; width:180px;
  }}
  span {{ color:#fff; font-size:128px; font-weight:800; line-height:1; }}
"""


def render(chrome: Path, html: str, out: Path, w: int, h: int) -> None:
    with tempfile.TemporaryDirectory() as tmp:
        page = Path(tmp) / "page.html"
        page.write_text(html, encoding="utf-8")
        shot = Path(tmp) / "shot.png"

        subprocess.run(
            [
                str(chrome),
                "--headless=new",
                "--disable-gpu",
                "--hide-scrollbars",
                "--force-device-scale-factor=1",
                "--default-background-color=00000000",
                f"--window-size={w},{h}",
                f"--screenshot={shot}",
                page.as_uri(),
            ],
            check=True,
            capture_output=True,
            timeout=120,
        )

        if not shot.exists():
            raise RuntimeError(f"chrome produced no screenshot for {out.name}")

        from PIL import Image

        img = Image.open(shot).convert("RGB")
        if img.size != (w, h):
            img = img.resize((w, h), Image.LANCZOS)
        # Flat brand colour and sharp type: PNG beats JPEG on both size and edges here.
        img.save(out, "PNG", optimize=True)
        print(f"  {out.name:<24} {out.stat().st_size / 1024:6.1f} KB  {w}x{h}")


def main() -> int:
    chrome = find_chrome()
    OUT.mkdir(parents=True, exist_ok=True)
    print(f"rendering images with {chrome.name}:")
    render(chrome, shell(OG_BODY, OG_CSS), OUT / "og-default.png", 1200, 630)
    render(chrome, shell(ICON_BODY, ICON_CSS), OUT / "apple-touch-icon.png", 180, 180)
    return 0


if __name__ == "__main__":
    sys.exit(main())
