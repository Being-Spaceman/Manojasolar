/** Minimal static server over dist/, shared by the test and audit scripts. */

import http from "node:http";
import fs from "node:fs/promises";
import path from "node:path";
import { fileURLToPath } from "node:url";

export const ROOT = path.dirname(path.dirname(fileURLToPath(import.meta.url)));
export const DIST = path.join(ROOT, "dist");

const MIME = {
  ".html": "text/html; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".woff2": "font/woff2",
  ".svg": "image/svg+xml",
  ".png": "image/png",
  ".jpg": "image/jpeg",
  ".xml": "application/xml",
  ".txt": "text/plain; charset=utf-8",
  ".json": "application/json",
};

const CHROME = [
  "C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe",
  "C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe",
  path.join(process.env.LOCALAPPDATA ?? "", "Google\\Chrome\\Application\\chrome.exe"),
  "C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe",
  "C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe",
];

export async function findChrome() {
  for (const c of CHROME) {
    if (c && (await fs.stat(c).catch(() => null))) return c;
  }
  throw new Error("Chrome not found — install Chrome or Edge");
}

export async function serve() {
  const server = http.createServer(async (req, res) => {
    try {
      const p = decodeURIComponent(new URL(req.url, "http://x").pathname);
      let file = path.join(DIST, p);
      const stat = await fs.stat(file).catch(() => null);
      if (!stat || stat.isDirectory()) file = path.join(file, "index.html");
      const body = await fs.readFile(file);
      res.writeHead(200, {
        "content-type": MIME[path.extname(file)] ?? "application/octet-stream",
        // no-cache, NOT no-store: no-store disables the back/forward cache, which
        // makes Lighthouse report a bfcache failure that would never happen on the
        // real host. This still revalidates every request, so tests never see stale
        // output from a previous build.
        "cache-control": "no-cache",
      });
      res.end(body);
    } catch {
      res.writeHead(404).end("not found");
    }
  });
  await new Promise((r) => server.listen(0, "127.0.0.1", r));
  return { server, port: server.address().port, close: () => server.close() };
}
