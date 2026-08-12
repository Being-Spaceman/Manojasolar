/**
 * Marathi overflow test.
 *
 * Marathi copy is not final. When it is rewritten it will almost certainly get
 * longer — Devanagari runs longer than English for the same idea, and a rewrite by
 * a native copywriter tends to add words rather than remove them. This test pads
 * every Devanagari string on the built pages to ~130% of its length and then checks
 * that nothing breaks: no horizontal page scroll, nothing pushed past the viewport
 * edge, no text clipped out of a fixed-height box.
 *
 * It runs against dist/, so it tests the real shipped CSS, not a dev-server
 * approximation. Padding happens in the browser, so no source file is touched.
 *
 *   node scripts/overflow-test.mjs            # report only
 *   node scripts/overflow-test.mjs --shots    # also write screenshots
 */

import http from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";
import puppeteer from "puppeteer-core";

const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
const DIST = path.join(ROOT, "dist");
const SHOT_DIR = path.join(ROOT, "lighthouse", "overflow");
const WANT_SHOTS = process.argv.includes("--shots");

const CHROME = [
  "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
  "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
  path.join(process.env.LOCALAPPDATA ?? "", "Google\\Chrome\\Application\\chrome.exe"),
  "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe",
];

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".woff2": "font/woff2",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".xml": "application/xml",
  ".txt": "text/plain; charset=utf-8",
};

/** Viewports: the two most common cheap-Android widths, plus the design width. */
const VIEWPORTS = [
  { name: "320", width: 320, height: 640 },
  { name: "360", width: 360, height: 740 },
  { name: "390", width: 390, height: 844 },
  { name: "412", width: 412, height: 915 },
];

const PAGES = [
  { name: "home-mr", url: "/" },
  { name: "thanks-mr", url: "/thank-you/" },
];

// ---------------------------------------------------------------- static server

async function serve() {
  const server = http.createServer(async (req, res) => {
    try {
      let p = decodeURIComponent(new URL(req.url, "http://x").pathname);
      let file = path.join(DIST, p);
      const stat = await fs.stat(file).catch(() => null);
      if (!stat || stat.isDirectory()) file = path.join(file, "index.html");
      const body = await fs.readFile(file);
      res.writeHead(200, { "content-type": MIME[path.extname(file)] ?? "application/octet-stream" });
      res.end(body);
    } catch {
      res.writeHead(404).end("not found");
    }
  });
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  return { server, port: server.address().port };
}

async function findChrome() {
  for (const c of CHROME) {
    if (c && (await fs.stat(c).catch(() => null))) return c;
  }
  throw new Error("Chrome not found");
}

// ------------------------------------------------------------- in-page routines

/**
 * Pad every Devanagari text node to ~130% of its length, using real Marathi words
 * so the result wraps and hyphenates the way genuine copy would. Long compound
 * words are included on purpose: an unbreakable 18-character word is exactly what
 * blows out a narrow column.
 */
const PAD_FN = `(factor) => {
  const FILLER = ["अनुदानाची", "कागदपत्रं", "महावितरणकडून", "इन्स्टॉलेशन", "जबाबदारी", "छतावरील"];
  const DEVA = /[\\u0900-\\u097F]/;
  const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let n, i = 0, count = 0;
  const nodes = [];
  while ((n = walker.nextNode())) {
    const t = n.nodeValue;
    if (!t || !t.trim() || !DEVA.test(t)) continue;
    if (n.parentElement?.closest("script,style")) continue;
    // Skip single glyphs and numerals — the म in the logo tile, the १२३४ on the
    // process steps. These are fixed marks, not sentences; they do not get longer
    // when a copywriter rewrites the page, so padding them tests nothing real.
    if (t.trim().length <= 3) continue;
    nodes.push(n);
  }
  for (const node of nodes) {
    const original = node.nodeValue;
    const target = Math.ceil(original.trim().length * factor);
    let out = original.replace(/\\s+$/, "");
    while (out.length < target) {
      out += " " + FILLER[i++ % FILLER.length];
    }
    node.nodeValue = out;
    count++;
  }
  return count;
}`;

/**
 * What counts as broken:
 *   pageScroll — the document scrolls sideways. This is the one users feel.
 *   pastEdge   — an element's box crosses the viewport's right edge.
 *   clipped    — text taller than its fixed-height container, i.e. cut off.
 */
const MEASURE_FN = `() => {
  const vw = document.documentElement.clientWidth;
  const problems = { pageScroll: null, pastEdge: [], clipped: [], escaping: [], wrongFont: [] };

  // Devanagari must never be set in a face that cannot render it. Plex Mono ships as
  // a Latin-only subset, so Marathi in a mono kicker silently falls back to whatever
  // Devanagari font the device happens to have — different metrics, different colour,
  // and nothing about it looks deliberate.
  //
  // document.fonts.check() is no use here: it reports whether the FACE is loaded, not
  // whether it covers the characters, so it returns true for Plex Mono + Devanagari.
  // Instead measure the Devanagari run twice on a canvas — once with the family at the
  // head of the stack, once with a family that cannot exist. Identical widths mean the
  // named family contributed no glyphs and the text is being rendered by the fallback.
  const DEVA_RE = /[ऀ-ॿ]/g;
  const ctx = document.createElement("canvas").getContext("2d");
  const textWalker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
  let tn;
  const seenFont = new Set();
  while ((tn = textWalker.nextNode())) {
    const txt = tn.nodeValue;
    if (!txt || !txt.trim()) continue;
    const deva = (txt.match(DEVA_RE) || []).join("");
    if (deva.length < 2) continue;
    const parent = tn.parentElement;
    if (!parent || parent.closest("script,style")) continue;

    const cs = getComputedStyle(parent);
    const key = cs.fontFamily + "|" + parent.className;
    if (seenFont.has(key)) continue;

    // The assertion is not "the first family in the stack covers this" — Mukta is
    // deliberately second in the mono stack, behind a Latin-only face. It is the
    // stronger claim that every Devanagari run on this site is rendered by Mukta.
    // Measuring the whole computed stack against Mukta alone says exactly that.
    ctx.font = cs.fontWeight + " " + cs.fontSize + " " + cs.fontFamily;
    const actual = ctx.measureText(deva).width;
    ctx.font = cs.fontWeight + " " + cs.fontSize + ' "Mukta"';
    const inMukta = ctx.measureText(deva).width;

    if (Math.abs(actual - inMukta) > 0.5) {
      seenFont.add(key);
      problems.wrongFont.push({
        el:
          parent.tagName.toLowerCase() +
          (parent.className
            ? "." + String(parent.className).trim().split(/\s+/).join(".")
            : ""),
        family: cs.fontFamily.split(",")[0].trim().replace(/^["']|["']$/g, ""),
        text: txt.trim().slice(0, 34),
      });
    }
  }

  const scrollW = Math.max(document.documentElement.scrollWidth, document.body.scrollWidth);
  if (scrollW > vw + 1) problems.pageScroll = { scrollW, vw };

  const label = (el) => {
    const cls = typeof el.className === "string" && el.className ? "." + el.className.trim().split(/\\s+/).join(".") : "";
    const id = el.id ? "#" + el.id : "";
    const txt = (el.textContent || "").trim().slice(0, 40);
    return el.tagName.toLowerCase() + id + cls + (txt ? ' "' + txt + '"' : "");
  };

  for (const el of document.querySelectorAll("body *")) {
    const cs = getComputedStyle(el);
    if (cs.display === "none" || cs.visibility === "hidden" || cs.position === "fixed") continue;
    const r = el.getBoundingClientRect();
    if (r.width === 0 && r.height === 0) continue;

    // Two deliberate off-screen idioms that are not overflow and must not be flagged:
    //   - the honeypot, parked at left:-9999px so bots find it and people never do
    //   - .sr-only text, clipped to a 1px box for screen readers
    const parkedOffLeft = r.right < 0;
    const visuallyHidden = el.clientWidth <= 1 || el.clientHeight <= 1;
    if (parkedOffLeft || visuallyHidden) continue;

    if (r.right > vw + 1.5 || r.left < -1.5) {
      problems.pastEdge.push({ el: label(el), left: Math.round(r.left), right: Math.round(r.right), vw });
    }
    const pinnedHeight = cs.height !== "auto";
    const hidesOverflow = cs.overflowY === "hidden" || cs.overflow === "hidden";
    const tooTall = el.scrollHeight > el.clientHeight + 1;

    // Pinned height + hidden overflow => the text is cut off.
    if (pinnedHeight && hidesOverflow && tooTall) {
      problems.clipped.push({ el: label(el), scrollH: el.scrollHeight, clientH: el.clientHeight });
    }
    // Pinned height + visible overflow => text escapes the box and lands on whatever
    // is underneath, with the wrong background behind it. This is how the language
    // toggle's label ended up rendering below the green header bar.
    //
    // The threshold matters. Devanagari set at a line-height tighter than the font's
    // own metrics — which every headline here is, deliberately — always reports a
    // scrollHeight a few px over its clientHeight. That is ordinary typography, not
    // overflow: the glyphs simply breathe outside the line box. Only a discrepancy
    // big enough to be an extra WRAPPED LINE is a real escape.
    const escapedLine =
      el.scrollHeight > el.clientHeight * 1.25 && el.scrollHeight - el.clientHeight > 8;
    if (pinnedHeight && !hidesOverflow && tooTall && escapedLine && el.children.length === 0) {
      problems.escaping.push({ el: label(el), scrollH: el.scrollHeight, clientH: el.clientHeight });
    }
  }

  // Dedupe: a wide child usually drags its parents into pastEdge too. Keep the
  // deepest offenders, which are the ones actually at fault.
  const seen = new Set();
  problems.pastEdge = problems.pastEdge.filter((p) => {
    if (seen.has(p.el)) return false;
    seen.add(p.el);
    return true;
  }).slice(0, 12);

  return problems;
}`;

// ------------------------------------------------------------------------- main

const { server, port } = await serve();
const browser = await puppeteer.launch({
  executablePath: await findChrome(),
  headless: true,
  args: ["--hide-scrollbars", "--disable-gpu"],
});

if (WANT_SHOTS) await fs.mkdir(SHOT_DIR, { recursive: true });

let failures = 0;
const rows = [];

for (const pageDef of PAGES) {
  for (const vp of VIEWPORTS) {
    for (const padded of [false, true]) {
      const page = await browser.newPage();
      await page.setViewport({
        width: vp.width,
        height: vp.height,
        deviceScaleFactor: 2,
        isMobile: true,
        hasTouch: true,
      });
      await page.goto(`http://127.0.0.1:${port}${pageDef.url}`, {
        waitUntil: "networkidle0",
      });

      // Open every FAQ answer — collapsed content hides its own overflow.
      await page.evaluate(`() => document.querySelectorAll("details").forEach(d => d.open = true)`);

      let padCount = 0;
      if (padded) {
        padCount = await page.evaluate(`(${PAD_FN})(1.3)`);
        // Let the browser settle after rewriting every string on the page.
        await page.evaluate(`() => new Promise(r => requestAnimationFrame(() => requestAnimationFrame(r)))`);
      }

      const res = await page.evaluate(`(${MEASURE_FN})()`);

      const bad =
        (res.pageScroll ? 1 : 0) +
        res.pastEdge.length +
        res.clipped.length +
        res.escaping.length +
        res.wrongFont.length;
      if (bad) failures++;

      rows.push({
        page: pageDef.name,
        vp: vp.name,
        mode: padded ? `+30% (${padCount} nodes)` : "baseline",
        res,
        bad,
      });

      if (WANT_SHOTS && padded) {
        await page.screenshot({
          path: path.join(SHOT_DIR, `${pageDef.name}-${vp.name}-padded.png`),
          fullPage: true,
        });
      }

      await page.close();
    }
  }
}

await browser.close();
server.close();

// ---------------------------------------------------------------------- report

console.log("\nMarathi overflow test — padded to 130% of real copy length\n");
const pad = (s, n) => String(s).padEnd(n);
console.log(pad("PAGE", 12) + pad("WIDTH", 8) + pad("MODE", 22) + "RESULT");
console.log("-".repeat(72));

for (const r of rows) {
  const verdict = r.bad === 0 ? "clean" : `${r.bad} problem(s)`;
  console.log(pad(r.page, 12) + pad(r.vp, 8) + pad(r.mode, 22) + verdict);
  if (r.res.pageScroll) {
    console.log(`      horizontal scroll: document is ${r.res.pageScroll.scrollW}px in a ${r.res.pageScroll.vw}px viewport`);
  }
  for (const p of r.res.pastEdge) {
    console.log(`      past right edge (${p.right} > ${p.vw}): ${p.el}`);
  }
  for (const c of r.res.clipped) {
    console.log(`      text clipped (${c.scrollH}px of text in a ${c.clientH}px box): ${c.el}`);
  }
  for (const c of r.res.escaping) {
    console.log(`      text escapes its box (${c.scrollH}px of text in a ${c.clientH}px box): ${c.el}`);
  }
  for (const f of r.res.wrongFont) {
    console.log(`      Devanagari not rendered in Mukta (stack leads with "${f.family}"): ${f.el}  "${f.text}"`);
  }
}

console.log("-".repeat(72));
if (failures === 0) {
  console.log(`PASS — ${rows.length} combinations, no overflow at any width.\n`);
} else {
  console.log(`FAIL — ${failures} of ${rows.length} combinations overflow.\n`);
}
process.exit(failures === 0 ? 0 : 1);
