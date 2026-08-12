/**
 * Sticky CTA bar behaviour test.
 *
 * The bar is fixed over the bottom of every mobile page, which makes it the single
 * most likely thing on the site to sit on top of something the user needs. Two
 * rules, both asserted here against the built output:
 *
 *   1. it must not overlap the footer      — the footer holds the address, phone,
 *                                            GST number and the Waaree partner line
 *   2. it must not cover a focused field   — on Android the keyboard shoves the
 *                                            viewport up and a fixed bar lands
 *                                            squarely on the input being typed into
 *
 *   node scripts/sticky-test.mjs
 */

import puppeteer from "puppeteer-core";
import { serve, findChrome } from "./_serve.mjs";

const VIEWPORT = { width: 390, height: 844, deviceScaleFactor: 2, isMobile: true, hasTouch: true };

const { port, close } = await serve();
const browser = await puppeteer.launch({
  executablePath: await findChrome(),
  headless: true,
  args: ["--hide-scrollbars", "--disable-gpu"],
});

const page = await browser.newPage();
await page.setViewport(VIEWPORT);
await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: "networkidle0" });

const settle = () =>
  page.evaluate(() => new Promise((r) => setTimeout(() => requestAnimationFrame(r), 350)));

const barInfo = () =>
  page.evaluate(() => {
    const bar = document.getElementById("sticky-cta");
    if (!bar) return null;
    const r = bar.getBoundingClientRect();
    return {
      state: bar.dataset.state,
      top: Math.round(r.top),
      bottom: Math.round(r.bottom),
      // Off the bottom of the viewport counts as genuinely out of the way.
      offscreen: r.top >= window.innerHeight - 1,
      vh: window.innerHeight,
    };
  });

const results = [];
const check = (name, pass, detail) => {
  results.push({ name, pass, detail });
};

// -------------------------------------------------------- 1. visible at the top
await settle();
let bar = await barInfo();
check(
  "bar is visible at the top of the page",
  bar && bar.state === "visible" && !bar.offscreen,
  `state=${bar?.state} top=${bar?.top} vh=${bar?.vh}`,
);

// ------------------------------------------------- 2. hides when footer is on screen
await page.evaluate(() => document.querySelector("footer")?.scrollIntoView({ block: "end" }));
await settle();
bar = await barInfo();

const overlap = await page.evaluate(() => {
  const bar = document.getElementById("sticky-cta");
  const footer = document.querySelector("footer");
  if (!bar || !footer) return null;
  const b = bar.getBoundingClientRect();
  const f = footer.getBoundingClientRect();
  const vh = window.innerHeight;
  // Only the part of the footer actually on screen can be covered.
  const visibleFooterTop = Math.max(f.top, 0);
  const visibleFooterBottom = Math.min(f.bottom, vh);
  const covered = Math.min(b.bottom, visibleFooterBottom) - Math.max(b.top, visibleFooterTop);
  return { covered: Math.round(Math.max(0, covered)), barTop: Math.round(b.top), vh };
});

check(
  "bar does not overlap the footer",
  bar?.state === "hidden" || overlap?.covered === 0,
  `state=${bar?.state} overlap=${overlap?.covered}px`,
);

// ------------------------------------------- 3. hides and clears a focused field
// The lead form's audience fieldsets are hidden until a radio is picked (JS-enhanced
// progressive disclosure — see LeadForm.astro), so the individual path is selected
// first to reveal #lf-ind-mobile.
await page.click("#lf-type-individual");
await page.evaluate(() => document.getElementById("lf-ind-mobile")?.scrollIntoView({ block: "center" }));
await settle();
await page.focus("#lf-ind-mobile");
await page.type("#lf-ind-mobile", "9876543210", { delay: 10 });
await settle();

bar = await barInfo();
const fieldClear = await page.evaluate(() => {
  const bar = document.getElementById("sticky-cta");
  const field = document.getElementById("lf-ind-mobile");
  if (!bar || !field) return null;
  const b = bar.getBoundingClientRect();
  const f = field.getBoundingClientRect();
  const covered = Math.min(b.bottom, f.bottom) - Math.max(b.top, f.top);
  return {
    covered: Math.round(Math.max(0, covered)),
    state: bar.dataset.state,
    fieldTop: Math.round(f.top),
    barTop: Math.round(b.top),
  };
});

check(
  "bar clears the focused phone field",
  bar?.state === "hidden" || fieldClear?.covered === 0,
  `state=${fieldClear?.state} overlap=${fieldClear?.covered}px`,
);

// ------------------------------------- 4. submit button reachable while scrolling
// Blur first: the form is much longer now (audience picker + up to 8 fields), so the
// submit button sits far below the field focused in step 3, off-screen at that scroll
// position — comparing rects there would be comparing two off-screen boxes, which can
// overlap on paper without either being visible. Scrolling the button into view first
// tests the thing that actually matters: does the visible floating bar cover it.
await page.evaluate(() => document.activeElement?.blur());
await page.evaluate(() =>
  document.querySelector("#lead-form button[type=submit]")?.scrollIntoView({ block: "center" }),
);
await settle();

const submitClear = await page.evaluate(() => {
  const bar = document.getElementById("sticky-cta");
  const btn = document.querySelector("#lead-form button[type=submit]");
  if (!bar || !btn) return null;
  const b = bar.getBoundingClientRect();
  const s = btn.getBoundingClientRect();
  const covered = Math.min(b.bottom, s.bottom) - Math.max(b.top, s.top);
  return { covered: Math.round(Math.max(0, covered)), barState: bar.dataset.state };
});
check(
  "bar clears the form's own submit button",
  submitClear?.barState === "hidden" || submitClear?.covered === 0,
  `state=${submitClear?.barState} overlap=${submitClear?.covered}px`,
);

// -------------------------------------------- 5. comes back after leaving the form
await page.evaluate(() => document.activeElement?.blur());
await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight * 0.35));
await settle();
bar = await barInfo();
check(
  "bar returns after the field loses focus",
  bar?.state === "visible" && !bar.offscreen,
  `state=${bar?.state}`,
);

// ---------------------------------------------------- 6. hidden entirely on desktop
await page.setViewport({ width: 1280, height: 900, deviceScaleFactor: 1 });
await page.reload({ waitUntil: "networkidle0" });
await settle();
const desktopHidden = await page.evaluate(() => {
  const bar = document.getElementById("sticky-cta");
  return !bar || getComputedStyle(bar).display === "none";
});
check("bar is not rendered at desktop widths", desktopHidden, "display:none at 1280px");

await browser.close();
close();

// ---------------------------------------------------------------------- report
console.log("\nSticky CTA bar behaviour\n");
let failed = 0;
for (const r of results) {
  if (!r.pass) failed++;
  console.log(`  ${r.pass ? "PASS" : "FAIL"}  ${r.name}`);
  console.log(`        ${r.detail}`);
}
console.log(
  `\n${failed === 0 ? "PASS" : "FAIL"} — ${results.length - failed}/${results.length} checks passed.\n`,
);
process.exit(failed === 0 ? 0 : 1);
