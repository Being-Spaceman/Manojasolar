/**
 * Lighthouse mobile audit against the built output in dist/.
 *
 * Runs the default mobile config: Moto G Power emulation, 4x CPU throttle and a
 * simulated slow 4G link — which is close to the actual audience for this site.
 *
 *   node scripts/audit.mjs             # home page, both locales
 *   node scripts/audit.mjs /thank-you/
 */

import fs from "node:fs/promises";
import path from "node:path";
import lighthouse from "lighthouse";
import * as chromeLauncher from "chrome-launcher";
import { serve, findChrome, ROOT } from "./_serve.mjs";

const OUT = path.join(ROOT, "lighthouse");
const targets = process.argv.slice(2).filter((a) => a.startsWith("/"));
const PAGES = targets.length ? targets : ["/", "/en/"];

const { port: httpPort, close } = await serve();

const chrome = await chromeLauncher.launch({
  chromePath: await findChrome(),
  chromeFlags: ["--headless=new", "--disable-gpu", "--no-sandbox"],
});

await fs.mkdir(OUT, { recursive: true });

const CORE = [
  "first-contentful-paint",
  "largest-contentful-paint",
  "total-blocking-time",
  "cumulative-layout-shift",
  "speed-index",
  "interactive",
];

const summaries = [];

for (const route of PAGES) {
  const url = `http://127.0.0.1:${httpPort}${route}`;
  const runnerResult = await lighthouse(
    url,
    { port: chrome.port, output: ["html", "json"], logLevel: "error" },
    undefined,
  );

  const lhr = runnerResult.lhr;
  const slug = route === "/" ? "home-mr" : route.replace(/\//g, "-").replace(/^-|-$/g, "");

  await fs.writeFile(path.join(OUT, `${slug}.report.html`), runnerResult.report[0]);
  await fs.writeFile(path.join(OUT, `${slug}.report.json`), runnerResult.report[1]);

  const cats = Object.fromEntries(
    Object.entries(lhr.categories).map(([k, v]) => [k, Math.round((v.score ?? 0) * 100)]),
  );

  const metrics = CORE.map((id) => ({
    id,
    title: lhr.audits[id]?.title ?? id,
    display: lhr.audits[id]?.displayValue ?? "—",
    score: lhr.audits[id]?.score,
  }));

  // Anything actually costing us points, biggest first.
  const opportunities = Object.values(lhr.audits)
    .filter(
      (a) =>
        a.score !== null &&
        a.score < 0.9 &&
        (a.details?.type === "opportunity" || a.details?.type === "table") &&
        a.scoreDisplayMode !== "informative",
    )
    .map((a) => ({
      title: a.title,
      savings: a.details?.overallSavingsMs ?? 0,
      bytes: a.details?.overallSavingsBytes ?? 0,
      score: a.score,
    }))
    .sort((a, b) => b.savings - a.savings || b.bytes - a.bytes)
    .slice(0, 8);

  const failed = Object.values(lhr.audits)
    .filter((a) => a.score !== null && a.score < 1 && a.scoreDisplayMode === "binary")
    .map((a) => a.title);

  // The specific DOM nodes behind each failing accessibility audit, so a failure
  // names the element to fix rather than just the rule it broke.
  const offenders = [];
  for (const audit of Object.values(lhr.audits)) {
    if (audit.score === null || audit.score >= 1) continue;
    const items = audit.details?.items;
    if (!Array.isArray(items)) continue;
    for (const item of items) {
      const node = item.node ?? item.subItems?.items?.[0]?.node;
      if (!node?.snippet) continue;
      offenders.push({
        audit: audit.id,
        snippet: node.snippet.replace(/\s+/g, " ").slice(0, 130),
        explanation: (node.explanation ?? "").replace(/\s+/g, " ").slice(0, 160),
      });
      if (offenders.length > 24) break;
    }
  }

  summaries.push({ route, slug, cats, metrics, opportunities, failed, offenders });
}

// chrome-launcher removes its temp profile on kill, which on Windows routinely
// fails with EPERM because Chrome has not released the directory yet. The audit is
// already done by this point, so a cleanup failure must not lose the results.
try {
  await chrome.kill();
} catch (err) {
  if (err?.code !== "EPERM") throw err;
}
close();

// ---------------------------------------------------------------------- report
for (const s of summaries) {
  console.log(`\n${"=".repeat(70)}`);
  console.log(`  ${s.route}   (mobile, simulated slow 4G)`);
  console.log("=".repeat(70));
  console.log(
    `  Performance ${s.cats.performance}   Accessibility ${s.cats.accessibility}   ` +
      `Best practices ${s.cats["best-practices"]}   SEO ${s.cats.seo}`,
  );

  console.log("\n  Core metrics");
  for (const m of s.metrics) {
    const flag = m.score === null ? " " : m.score >= 0.9 ? " " : "!";
    console.log(`   ${flag} ${m.title.padEnd(34)} ${m.display}`);
  }

  if (s.opportunities.length) {
    console.log("\n  Costing us points");
    for (const o of s.opportunities) {
      const bits = [];
      if (o.savings) bits.push(`~${Math.round(o.savings)}ms`);
      if (o.bytes) bits.push(`${(o.bytes / 1024).toFixed(0)}KB`);
      console.log(`     ${o.title}${bits.length ? "  — " + bits.join(", ") : ""}`);
    }
  }

  if (s.failed.length) {
    console.log("\n  Failed binary audits");
    for (const f of s.failed.slice(0, 12)) console.log(`     ${f}`);
  }

  if (s.offenders.length) {
    console.log("\n  Offending elements");
    for (const o of s.offenders) {
      console.log(`     [${o.audit}] ${o.snippet}`);
      if (o.explanation) console.log(`         ${o.explanation}`);
    }
  }

  console.log(`\n  full report: lighthouse/${s.slug}.report.html`);
}
console.log("");
