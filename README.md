# Manoja Agencies — rooftop solar website

Bilingual (Marathi / English) lead-generation website for **Manoja Agencies**, an
authorised Waaree Energies channel partner selling and installing rooftop solar in
**Latur, Maharashtra**.

The site exists to do one job: turn a visitor into a WhatsApp message or a phone call.
Everything else — the subsidy explainer, the four-step process, the FAQ — is there to
remove the objections that stop someone tapping that button.

**Who it is built for.** Mid-range Android phones on 4G in a tier-3 Indian city. Every
technical decision in here follows from that: no framework, no web fonts over the
network, no map iframe until it is asked for. Marathi is the default language; English
is the alternate.

| | |
| --- | --- |
| **Live URL** | Not yet deployed — see [Deploying](#deploying-to-hostinger) |
| **Hosting** | Hostinger Business (shared) — static file upload, no server runtime |
| **Pages** | Home, Thank-you, Privacy, 404 — each in Marathi and English |
| **Lighthouse** | Performance 97 · Accessibility 100 · Best practices 100 · SEO 100 |

---

## Table of contents

- [Tech stack](#tech-stack)
- [Prerequisites](#prerequisites)
- [Installation](#installation)
- [Running the site](#running-the-site)
- [All commands](#all-commands)
- [Deploying to Hostinger](#deploying-to-hostinger)
- [Before you go live](#before-you-go-live)
- [Project structure](#project-structure)
- [How the site is put together](#how-the-site-is-put-together)

---

## Tech stack

**Runtime dependencies — two:**

| Package | Version | Why |
| --- | --- | --- |
| **Astro** | 5.18 | Static site generator. Ships zero JS by default |
| **@astrojs/sitemap** | 3.6 | `sitemap-index.xml` with `mr-IN` / `en-IN` hreflang |

**Build-time only:**

- **Tailwind CSS 4** via `@tailwindcss/vite` — CSS-first, so there is **no
  `tailwind.config.js`**. All design tokens live in `@theme` in `src/styles/global.css`
- **TypeScript** — `.astro` frontmatter and the i18n helpers
- **Python 3** + `fonttools` / `brotli` / `Pillow` — font subsetting and OG image
  generation. Optional; the outputs are committed
- **Lighthouse** + `puppeteer-core` — the audit and the three test scripts

**No UI framework.** No React, Vue, or Svelte. The ~4 KB of JavaScript that ships is
hand-written vanilla: sticky-bar visibility, the map facade swap, and form validation.
The FAQ uses native `<details>`/`<summary>`, not an accordion library.

---

## Prerequisites

| Requirement | Version | Needed for |
| --- | --- | --- |
| **Node.js** | 20 or newer (built on 24 LTS) | Everything |
| **npm** | 10+ (ships with Node) | Everything |
| **Python** | 3.10+ | Fonts and images only — **optional** |

### Installing Node

**Windows:**

```powershell
winget install --id OpenJS.NodeJS.LTS --exact
```

Close and reopen your terminal afterwards so `PATH` picks it up, then confirm:

```bash
node --version    # v24.19.0
npm --version     # 11.17.0
```

**macOS:** `brew install node` · **Linux:** use [nodesource](https://github.com/nodesource/distributions) or your distro's package manager.

### Python (optional)

Only needed if you re-subset the fonts or regenerate the OG image. The generated
`.woff2` and `.png` files are committed, so you can skip this entirely.

```bash
pip install fonttools brotli Pillow
```

---

## Installation

```bash
# 1. Go to the project
cd "f:/Sourabh  Mannoja group/ManojaSolar"

# 2. Install dependencies (~300 packages, about a minute)
npm install

# 3. Start the dev server
npm run dev
```

That is the whole setup. No database, no `.env` file, no API keys, no external services
to sign up for.

> **Note on npm 11+**: `esbuild` and `sharp` have install scripts that newer npm blocks
> by default. Astro works regardless — if you ever hit a missing-binary error, run
> `npm rebuild esbuild sharp`.

### Optional: regenerate fonts and images

Only if you change the copy's script coverage or the brand artwork:

```bash
npm run fonts:subset   # downloads + subsets webfonts into public/fonts
npm run images         # renders og-default.png and apple-touch-icon.png
```

---

## Running the site

### Development server

```bash
npm run dev
```

You should see:

```
> manoja-solar@0.1.0 dev
> astro dev

 astro  v5.18.2 ready in 497 ms

┃ Local    http://localhost:4321/
┃ Network  use --host to expose

watching for file changes...
```

| URL | Page |
| --- | --- |
| http://localhost:4321/ | Home — **Marathi** (default) |
| http://localhost:4321/en/ | Home — English |
| http://localhost:4321/thank-you/ | Post-submission page |
| http://localhost:4321/privacy | Privacy policy |

Hot reload is on: save any file and the browser updates. Stop the server with **Ctrl+C**.

### Testing on a real phone

Worth doing for this project specifically — the whole design targets a cheap Android
handset, and twenty seconds on a real device beats any emulator.

```bash
npm run dev -- --host
```

This prints a second address like `http://192.168.1.5:4321/`. Open that on a phone
connected to the same wifi.

### Building for production

```bash
npm run build     # → dist/
npm run preview   # serve the built dist/ locally, exactly as the host would
```

`npm run build` runs the font-coverage check first **on purpose**: a Marathi string
containing a glyph the subset cannot render fails the build, rather than shipping tofu
boxes (□□□) to a customer's phone.

The site itself builds to ~650 KB: 7 HTML pages, 220 KB of fonts, and the brand images.

> ⚠️ **`public/photos/` currently adds 1.5 MB on top of that.** The two reference
> photographs in there (`storefront-manoja-agencies.png`, `building-silver-tower.png`)
> are cited in a code comment as the source for the GSTIN and coordinates, but nothing
> on the site renders them. Because they live under `public/`, Astro copies them into
> `dist/` — so they would be uploaded and publicly reachable at
> `yourdomain.com/photos/…`, more than doubling the deploy for no visitor benefit.
>
> Move them to a folder outside `public/` (say `reference/`) to keep the provenance
> without shipping them.

### Running the tests

```bash
npm test          # all three suites
npm run audit     # Lighthouse mobile audit
```

---

## All commands

| Command | What it does |
| --- | --- |
| `npm run dev` | Dev server with hot reload on `localhost:4321` |
| `npm run dev -- --host` | Same, also exposed on your local network for phone testing |
| `npm run build` | Verifies font coverage, then builds to `dist/` |
| `npm run preview` | Serves the built `dist/` locally |
| `npm test` | Font coverage + Marathi overflow + sticky-bar behaviour |
| `npm run test:overflow` | Marathi padded +30% at 320/360/390/412px; also asserts Devanagari renders in Mukta |
| `npm run test:sticky` | Asserts the sticky bar never covers the footer or a focused field |
| `npm run audit` | Lighthouse mobile audit → `lighthouse/*.report.html` |
| `npm run shots` | Screenshots the built pages → `lighthouse/shots/` |
| `npm run fonts:subset` | Re-downloads and re-subsets all webfonts |
| `npm run fonts:verify` | Fails if a glyph used in `src/i18n` is missing from the subsets |
| `npm run images` | Regenerates `og-default.png` and `apple-touch-icon.png` |

---

## Deploying to Hostinger

The site is plain static files. **Nothing is installed on the server** — no Node, no
database, no build step.

1. **Build**

   ```bash
   npm run build
   ```

2. **Upload the *contents* of `dist/`** (not the folder itself) into `public_html/`,
   via hPanel → File Manager or over FTP.

3. **Confirm `.htaccess` made it across.** File Manager hides dotfiles until you turn on
   *"show hidden files"*. Without it you lose gzip, cache headers, the HTTPS redirect
   and the 404 page.

4. **Check compression** once the domain resolves:

   ```bash
   curl -sI https://yourdomain.com/ | grep -i "content-encoding\|cache-control"
   ```

   You want `content-encoding: gzip` (or `br`). Hostinger runs **LiteSpeed**, not stock
   Apache — it reads `.htaccess` but handles compression at the server level and may
   ignore the `mod_deflate` block. If the header is missing, enable compression in
   hPanel rather than editing `.htaccess`; LiteSpeed's own setting wins.

Every block in `public/.htaccess` is wrapped in `<IfModule>` guards, so an unavailable
module is skipped silently instead of throwing a 500.

### What the Business plan gives you

| Feature | Relevance here |
| --- | --- |
| **PHP 8** | Makes a `lead.php` form handler possible — no third-party service needed |
| **Free CDN** | Worth enabling; the 220 KB of fonts is cached for a year |
| **Free SSL** | **Required** — `.htaccess` force-redirects HTTP→HTTPS, so SSL must be active or you get a redirect loop |

---

## Before you go live

Everything below is marked `TODO(MNJ)` in the source — search for that string.

### Still placeholders — `src/data/business.ts`

| Field | Current value | Priority |
| --- | --- | --- |
| `whatsappE164` | `919999999999` | 🔴 **Critical** — this is the primary CTA on every screen. Must be a **mobile**; WhatsApp will not open on a landline |
| `phoneDisplay` / `phoneE164` | `02382 000 000` | 🔴 **Critical** — the design comp's placeholder |
| `email` | `info@manojaagencies.com` | 🟡 Confirm, or delete the field and its footer row |
| `hours` | Mon–Sat 10am–8pm | 🟡 Confirm the actual weekly off |
| `address.street` | `Ambajogai Road, Netaji Nagar` | 🟡 Add the shop/unit number if there is one |

Already filled in and verified: `gstin`, `geo`, `plusCode`, `mapsUrl`, `address`.

Also set the real domain in **`astro.config.mjs`** (`SITE`) and **`public/robots.txt`** —
both currently say `https://manojaagencies.com`, which is a guess. That string becomes
every canonical tag, hreflang and sitemap URL.

Because the footer, contact section and `LocalBusiness` JSON-LD all read from that one
file, the structured data can never claim an address that differs from what is printed
on the page.

### 🔴 The lead form has no backend

`src/components/sections/LeadForm.astro` posts to `/api/lead`, which does not exist on a
static host. **Right now every submitted lead is lost** — the form validates, shows its
loading state, then shows the network error.

To connect it, change the `action` attribute and nothing else. The script posts a normal
`FormData` with fields `name`, `phone`, `bill`, `roof`, `website` (honeypot), `locale`
and `submittedAt`, and redirects to the thank-you page on any 2xx response.

A `lead.php` in `public_html/` is the least-effort option, since the Business plan
includes PHP.

### Content and rights

- **Rupee figures** in the subsidy section are `₹ --,---` placeholders, deliberately.
  Real numbers are quoted per customer after a site visit — printing one figure would be
  wrong for most readers and is a compliance risk. The share bar carries the message as a
  picture, so the section still works with the blanks in.
- **Waaree** appears as plain text only — no badge, seal, or logo lockup anywhere,
  including in the JSON-LD, because usage rights are not in place. Do not "improve" the
  footer line into a badge.
- **Photography.** The hero and map use fixed-ratio striped placeholders. Real photos of
  finished Latur installs drop straight in with zero layout shift, because the aspect
  boxes are already reserved.
- **Marathi copy.** Strings taken from `design-baseline/` are the copywriter's own.
  Everything else in `src/i18n/mr.json` is drafted and **needs a native speaker's
  review** — particularly the FAQ answers and the privacy page.
- **Privacy policy** is accurate to what the build does, but has not been legally reviewed.

---

## Project structure

```
src/
  components/
    layout/       Header, Footer, StickyCta, LangToggle, Seo, JsonLd
    sections/     Hero, Subsidy, WhyUs, Process, LeadForm, Faq, Contact
    HomePage.astro, ThankYouPage.astro, SimplePage.astro
  data/
    business.ts   ← single source of truth for address, phone, GST, hours
  i18n/
    en.json       English strings (the shape of record)
    mr.json       Marathi strings (default locale)
    config.ts     locale helpers, URL building
    ui.ts         useTranslations / useList
  layouts/
    Base.astro    <html>, head, header, footer, sticky bar
  pages/
    index.astro, thank-you.astro, privacy.astro, 404.astro
    en/           the same pages in English
  styles/
    global.css    ← all design tokens (@theme), @font-face, base styles

public/          ← everything here is copied verbatim into dist/ and published
  fonts/          4 subsetted .woff2 files (220 KB total)
  photos/         ⚠️ reference shots, not site assets — see the warning above
  .htaccess       compression, caching, HTTPS redirect, 404
  robots.txt, favicon.svg, og-default.png, apple-touch-icon.png

scripts/
  subset_fonts.py    font subsetting + coverage verification
  make_images.py     OG image + touch icon via headless Chrome
  overflow-test.mjs  Marathi +30% layout test
  sticky-test.mjs    sticky bar behaviour test
  audit.mjs          Lighthouse runner
  shots.mjs          screenshot helper
```

---

## How the site is put together

### Languages

Marathi is the default and lives at `/`. English lives at `/en/`. This is deliberate:
Marathi is the default state, not a translation of an English site.

**No user-facing string is hardcoded in a component.** They all live in
`src/i18n/mr.json` and `src/i18n/en.json`. English is the shape of record — every key
there must exist in the Marathi file. A missing Marathi key falls back to English at
runtime and warns in the dev console, rather than rendering a raw key at a customer.

```astro
const t = useTranslations(locale);
<h1>{t("hero.headline")}</h1>
```

Use `useList` for arrays (FAQ entries, process steps).

The language toggle is two real links, not JavaScript, so the other language is a
crawlable URL with proper `hreflang`.

### Fonts

Mukta (Devanagari + Latin) at 400/600/800, and IBM Plex Mono 400 for the micro-labels.
All self-hosted from `public/fonts`, all `font-display: swap`. **Nothing is fetched from
Google at runtime.**

`scripts/subset_fonts.py` subsets by **Unicode range**, not by the exact characters in
today's copy. Devanagari conjuncts (क्ष, ज्ञ, ऱ्ह) are reached through GSUB
substitutions rather than codepoints, so text-based subsetting would silently drop the
glyphs a *future* string needs. All OpenType features are kept for the same reason.

Three weights, not the design's four: a Devanagari weight costs ~70 KB subsetted, and
700 is indistinguishable from 600/800 at the sizes it appeared at. Total shipped font
weight is 220 KB.

### Design tokens

All in `src/styles/global.css` under `@theme`. Tailwind 4 is CSS-first — there is no
`tailwind.config.js`.

Two things there are load-bearing and easy to undo by accident:

- **Spacing is 1px per unit** (`--spacing: 0.0625rem`), so `p-16` is 16px and utility
  names match the design values exactly. It is still in rem, so it scales with the user's
  root font size. The design is not on a 4pt grid — it uses optical values
  (7, 9, 11, 13, 18, 22, 26) — and rounding those into a 4pt grid loses the drawn rhythm.
- **There is no border radius anywhere.** Not a stylistic accident; the identity permits
  rounded corners only where a platform forces them (app icon, WhatsApp avatar).

Two rules from the identity doc that the code enforces:

- **Red is the CTA colour and nothing else.** The moment red appears twice in a view it
  stops meaning "press here". Secondary actions are green outlines. The one deliberate
  deviation from the design comp is the subsidy share bar, where the baseline filled the
  "you pay" segment with red — see the comment in `Subsidy.astro`.
- **Devanagari is never letterspaced.** Enforced globally via `:lang(mr)`, because the
  शिरोरेखा has to stay one unbroken line.

### Tests

`npm test` runs three suites, all against the built `dist/`:

1. **Font coverage** — every glyph used in either locale exists in the shipped subsets.
2. **Marathi overflow** — pads every Devanagari string to 130% of its length and checks
   four viewport widths (320/360/390/412) for horizontal page scroll, boxes pushed past
   the viewport edge, clipped text, and text escaping a fixed-height box. Marathi copy is
   not final and a rewrite will make it longer; this is what catches it.

   The same pass asserts that **every Devanagari run is rendered by Mukta**. IBM Plex
   Mono ships here as a Latin-only subset, so a Marathi string in a mono kicker falls
   back to whatever Devanagari font the phone has — which looks wrong but throws no
   error and is easy to miss in review. This caught exactly that in the section kickers
   and footer labels.
3. **Sticky bar** — asserts the bar never overlaps the footer, never covers a focused
   input or the form's own submit button, returns after blur, and is absent on desktop.

`npm run test:overflow -- --shots` writes full-page screenshots to `lighthouse/overflow/`.

### Performance

Measured on mobile, simulated slow 4G, against `dist/`:

| | Performance | Accessibility | Best practices | SEO |
| --- | --- | --- | --- | --- |
| `/` (Marathi) | **97** | 100 | 100 | 100 |
| `/en/` | **98** | 100 | 100 | 100 |

FCP 1.7s · LCP 2.4s · TBT 0ms · CLS 0.

Choices that account for most of that, and which are easy to undo without noticing:

- **The hero is type-only.** No image competes with the headline, so the headline, the
  WhatsApp button and the phone number paint almost immediately.
- **CSS is inlined into every page** (`inlineStylesheets: "always"`). Almost every visitor
  arrives from a WhatsApp forward, sees one page and leaves, so there is no second page
  to amortise a cached stylesheet over — and a linked file costs a render-blocking round
  trip on 4G.
- **The map is behind a tap.** A Google Maps iframe pulls roughly a megabyte from six
  third-party origins and is the single most common reason a local-business page fails a
  mobile audit. `Contact.astro` shows a facade and swaps in the real iframe on click.
- **The FAQ is native `<details>`/`<summary>`.** No accordion library, no JS.
- Total JS is the sticky bar, the map swap and the form validator — all vanilla, a few KB.

Run `npm run audit` after any significant change.


....
