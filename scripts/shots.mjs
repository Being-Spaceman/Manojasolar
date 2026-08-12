/**
 * Screenshot the built pages for visual review.
 *
 *   node scripts/shots.mjs            # mobile 390 + desktop 1280, both locales
 *   node scripts/shots.mjs --open-faq # with every FAQ answer expanded
 */

import fs from "node:fs/promises";
import path from "node:path";
import puppeteer from "puppeteer-core";
import { serve, findChrome, ROOT } from "./_serve.mjs";

const OUT = path.join(ROOT, "lighthouse", "shots");
const OPEN_FAQ = process.argv.includes("--open-faq");

const SHOTS = [
  { name: "home-mr-mobile", url: "/", w: 390, h: 844, mobile: true },
  { name: "home-en-mobile", url: "/en/", w: 390, h: 844, mobile: true },
  { name: "home-mr-desktop", url: "/", w: 1280, h: 900, mobile: false },
  { name: "thanks-mr-mobile", url: "/thank-you/", w: 390, h: 844, mobile: true },
];

const { port, close } = await serve();
const browser = await puppeteer.launch({
  executablePath: await findChrome(),
  headless: true,
  args: ["--hide-scrollbars", "--disable-gpu"],
});

await fs.mkdir(OUT, { recursive: true });

for (const s of SHOTS) {
  const page = await browser.newPage();
  await page.setViewport({
    width: s.w,
    height: s.h,
    deviceScaleFactor: s.mobile ? 2 : 1,
    isMobile: s.mobile,
    hasTouch: s.mobile,
  });
  await page.goto(`http://127.0.0.1:${port}${s.url}`, { waitUntil: "networkidle0" });
  if (OPEN_FAQ) {
    await page.evaluate(() => document.querySelectorAll("details").forEach((d) => (d.open = true)));
  }
  await page.evaluate(() => new Promise((r) => requestAnimationFrame(() => requestAnimationFrame(r))));
  const file = path.join(OUT, `${s.name}.png`);
  await page.screenshot({ path: file, fullPage: true });
  console.log(`  ${s.name.padEnd(20)} ${s.w}x${s.h}`);
  await page.close();
}

await browser.close();
close();
console.log(`\n  written to lighthouse/shots/\n`);
