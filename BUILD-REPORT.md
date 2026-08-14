# Build report — Manoja Agencies repositioning

This is a status report on the 8-phase brief in `CLAUDE-CODE-PROMPT.md`, written
mid-build. Prior sessions had already completed a large share of this work
(see commits `a6cf728`, `b0eefdd`, `391225d`); this session ran a full audit
against the brief, then closed the highest-priority gaps it found. **The
brief is not fully complete** — see §6 below for exactly what's left.

## 0. Hero image (2026-08-14, follow-up session)

`hero-stock.jpg` is a **licensed stock placeholder** (aerial photo of a
solar array), not a photo of the Latur godown. It was dropped into
`dist/images/` by mistake (that directory is regenerated on every build and
would have silently lost the file) and was first moved to
`public/images/hero-stock.jpg` — then, in the image-delivery task
immediately after (§1a below), moved again to `src/assets/images/` so
`astro:assets` can generate its responsive WebP variants at build time
(files under `public/` are served as-is, unprocessed).

The path is now a single named constant, `HERO_IMAGE` in
`src/data/media.ts` (an image import, not a string path), referenced from
`Hero.astro` and `HeroPreload.astro` — swap the file at that path and rerun
the build once the owner's own godown photography exists; no component code
needs to change beyond that one file.

Note: the task that first requested this move specified `public/photos/`, but the
codebase's actual convention — and what `Hero.astro` already referenced —
is `public/images/` (`public/photos/` is reserved for real, non-stock
photography and is currently empty, having previously held street-view
captures that were deleted for licensing reasons in the Phase 1 truth
pass). Used `public/images/` so no component edit was needed, as requested.

## 0a. Image delivery (2026-08-14, same follow-up session)

Moved the hero and four product photos from `public/images/` into
`src/assets/images/` and switched every `<img>` for them to Astro's
built-in `<Picture>` (`astro:assets`), using the `sharp` transitive
dependency already installed for it — no new dependency added. Each renders
a `<picture>` with a WebP `<source>` (responsive `srcset`) and a JPEG
`<img>` fallback.

**Widths generated:** hero at 640/960/1440/1920px; product photos at
320/480/640px (the product grid never renders wider than ~248px on desktop
or the viewport width on mobile, so 640px already covers 2x-DPR phones —
1440/1920 variants would be transferred but never displayed there).

**`sizes` reflects actual rendered width**, not `100vw` everywhere:
- Hero: `(min-width: 64rem) 503px, 100vw` — matches the `.hero-grid`
  column at desktop; full-bleed on mobile where the layout stacks.
  *(This will change with the Task 2 hero rebuild to a full-bleed
  background, at which point `sizes` becomes `100vw` at every breakpoint —
  see §2 below.)*
- Product cards: `(min-width: 64rem) 248px, calc(100vw - 32px)` — the real
  4-column desktop card width and the real mobile single-column width minus
  the page gutters.

**Hero preload** (`src/components/layout/HeroPreload.astro`) calls
`getImage()` with the same width list, format and quality as the `<Picture>`
in `Hero.astro` — imported from there, not duplicated as literals, so they
can't drift apart — and emits `<link rel="preload" as="image"
imagesrcset="..." imagesizes="...">` in `<head>`. Verified byte-for-byte
identical to the `<Picture>`'s own `srcset` in the built HTML, and the build
log confirms zero duplicate image transforms (32 optimized files, not 36+).

**Compression budget — one image needed extra work.** The hero source
(aerial photo of a densely-packed solar array) has very high-frequency
repetitive detail that WebP compresses poorly: even at quality 15 the 1920px
variant was 396KB, far over the 150KB budget. Rather than degrade it with
visible blocking artifacts, applied a mild Gaussian blur (sigma 8) to the
*source* file before Astro's own resize/encode pass. This is defensible
because (a) it will sit under a heavy dark scrim in the Task 2 hero rebuild,
where fine detail is invisible anyway, and (b) even without a scrim it's a
full-bleed background photo, not a subject the eye is meant to resolve at
pixel level. Result: 1920px WebP dropped to 108KB. No other image needed
this treatment — product photos hit budget with `quality={48}` alone.

**Compression results (final, largest variant actually shipped):**

| Image | Before | After (WebP) | Budget | 
|---|---|---|---|
| Hero, 1920px | 6047KB (original) | 108KB | 150KB ✓ |
| Product panels, 640px | 90KB | 6KB | 60KB ✓ |
| Product inverters, 640px | 156KB | 13KB | 60KB ✓ |
| Product cables, 640px | 64KB | 9KB | 60KB ✓ |
| Product accessories, 640px | 94KB | 15KB | 60KB ✓ |

The JPEG fallback for the hero also needed the explicit `width={1920}` prop
on `<Picture>` — without it, Astro's default `<img src>` fallback was the
*original, unconstrained* image size (Products had the same issue: the
inverter photo's fallback defaulted to its full 1000×1437 original, 69KB,
over budget, until `width={640}` was added).

**Loading strategy:** hero is `loading="eager" fetchpriority="high"` with
the matching preload; all four product photos are `loading="lazy"
decoding="async"` (unchanged from before, now on `<Picture>` instead of
plain `<img>`).

**Lighthouse — mobile, simulated slow 4G:**

| | Before | After |
|---|---|---|
| `/` Performance | 76 | **91** |
| `/en/` Performance | 78 | **93** |
| `/` LCP | 6.2s | **3.2s** |
| `/en/` LCP | 6.0s | **3.1s** |
| CLS (both) | 0 | **0** (unchanged — verified, not assumed) |
| Accessibility | 97 | **100** (see below) |
| Total image bytes transferred (mobile viewport) | not measured pre-change | **115.3KB** |

Targets from the task: **LCP under 2.5s, performance above 0.90.**
Performance target met on both locales (91/93). **LCP target not fully
met** (3.1–3.2s, down from 6.0–6.2s — a ~48% cut). The Lighthouse
`lcp-breakdown-insight` audit shows the image itself now contributes only
~100ms to LCP; the remaining time is dominated by time-to-first-byte and
document parse of the fully-inlined HTML+CSS payload under the "slow 4G"
simulated profile (~150ms+ RTT, ~1.6Mbps throughput) — not image weight.
Shrinking that further would mean revisiting `inlineStylesheets: "always"`
in `astro.config.mjs`, which was a deliberate, documented trade-off from an
earlier stage for a different reason (avoiding a second render-blocking
request on single-page WhatsApp-referral visits). Did not touch it this
session since Task 1 was scoped to image delivery only — flagging as a
**TODO** for a follow-up if 2.5s is a hard requirement rather than a
target.

**Accessibility regression caught and fixed:** the Lighthouse run surfaced
a `color-contrast` failure on the new "Built by Sartoria Systems" footer
credit added earlier this session (0.5 alpha white on `--color-forest`
measured 3.86:1, below AA's 4.5:1 for that text size). Raised to 0.72 alpha;
`color-contrast` audit now passes (binary score 1) sitewide.

## 0b. Hero rebuild — full-bleed (2026-08-14, same follow-up session)

Replaced the side-by-side hero (text left, image right) with a full-bleed
photo treatment. Below the fold is untouched — no rounded corners, glass or
gradients propagated into any other section.

**Structure:** full-bleed `<Picture>` background at `min-height: 100svh`
(`svh`, so the hero doesn't jump when mobile browser chrome collapses),
`object-fit: cover` with `object-position: 50% 65%` so the panel rows rather
than empty sky carry the crop; a gradient scrim over it; oversized display
headline; and a floating info card carrying the eyebrow, the three stats,
the amber CTA and the `tel:` link.

**Headline:** `clamp(3rem, 1.67rem + 5.93vw, 7rem)`, `line-height: 0.95`,
`-0.02em` tracking on Latin. Line breaks are set deliberately via `<br>` in
the i18n string so "held in Latur" and "the same day" each carry their own
line rather than reflowing arbitrarily. Devanagari gets `line-height: 1.15`
separately — at 7rem with Latin's 0.95 the matras above "साठा"/"त्याच"
collide with the शिरोरेखा of the line below. (Devanagari letter-spacing is
already globally zeroed by the existing `:lang(mr)` rule.) Centred on
desktop, left-aligned on mobile.

**Info card:** solid `rgb(11 31 23 / 0.92)` — no `backdrop-filter`, per the
constraint; square corners. Stats are a fixed 3-column grid so labels wrap
inside their own column and the row never becomes two (the regression fixed
in the previous session, deliberately preserved). Count-up animation moved
with them and still fires once on first view.

**Header:** on the homepage only, detaches to `position: fixed` with a 12px
inset and a translucent `rgb(11 31 23 / 0.55)` background, then switches to
solid `--color-ink` once the hero scrolls past — driven by an
`IntersectionObserver` on the hero section itself. Verified across the full
scroll cycle: translucent at load → translucent mid-hero → solid past hero →
translucent again on scroll back. All four nav items, the distinct "Home
solar" link and the मराठी/English toggle are unchanged, and every other page
keeps the normal solid sticky header (`floating` is opt-in per page).

**Contrast — measured from rendered pixels, not assumed.** Sampled the
actual screenshot buffer at each text location and computed WCAG ratios:

| Pair | Ratio | Requirement | |
|---|---|---|---|
| CTA: ink text on amber | **8.47:1** | 4.5:1 | ✓ |
| Card eyebrow: amber on card | **8.11:1** | 4.5:1 | ✓ |
| Card stat labels: white on card | **16.82:1** | 4.5:1 | ✓ |
| Headline: white on scrim, worst case | **4.12:1** | 3:1 (large text) | ✓ |

The headline measurement caught a real failure. With the originally
specified scrim (70% ink at bottom → 20% at top), the top headline line sat
over a bright sky patch measuring **1.98:1** — below even the 3:1 large-text
minimum. Fixed by strengthening the scrim to 80% → 48% and adding a
`text-shadow` to the headline. Both were needed: the scrim alone can't
guarantee AA against an *unknown future replacement photo*, which was the
stated purpose of having a scrim at all, so the text now also carries its
own contrast independent of what sits behind it. (A text-shadow on a few
large glyphs is cheap — unlike a `backdrop-filter`, which is a real GPU cost
and is not used anywhere.)

**Deviation from the brief, flagged:** the scrim opacities are 80%/48%, not
the specified 70%/20%. The specified values did not clear WCAG AA against
this photograph, and the constraint "WCAG AA on every text/background pair,
verified not assumed" was explicitly non-negotiable, so contrast won.

**Verification:** `npm test` passes (16 width/locale combinations, no
overflow at any width including 360px in both locales; 6/6 sticky-bar
checks). Reduced-motion confirmed by emulating `prefers-reduced-motion:
reduce` — stats render their final values immediately with no count-up.

**Lighthouse after the rebuild — mobile, simulated slow 4G:**

| | Before session | After images (§0a) | After hero rebuild |
|---|---|---|---|
| `/` Performance | 76 | 91 | **91** |
| `/en/` Performance | 78 | 93 | **93** |
| `/` LCP | 6.2s | 3.2s | **3.2s** |
| `/en/` LCP | 6.0s | 3.1s | **3.1s** |
| CLS both | 0 | 0 | **0** |
| Accessibility | 97 | 100 | **100** |

Definition of done met: build passes, mobile performance above 0.90 on both
locales, CLS at 0. The hero rebuild cost nothing measurable — the background
image is the same asset the preload already covers, and the only new script
is the header's IntersectionObserver toggle.

## 1. What changed this session

**Phase 1 — Truth pass**
- `src/data/business.ts`: real phone/WhatsApp (`8793716228` /
  `918793716228`) and the temporary Gmail address replaced the fake
  placeholders that were previously shipping. Hours now read "Closed Sunday"
  explicitly. Added empty placeholder fields (`instagram`, `facebook`,
  `youtube`, `gbpUrl`, `justdial`, `indiamart`) plus a `hasLink()` helper so
  components can render nothing until each is filled in.
- Confirmed already correct from prior sessions: singular name, `.in` domain
  in `astro.config.mjs`, `areaServed: ["Latur"]` only, street-view photos
  already deleted from `public/photos/`.

**Phase 2 — Design system**
- Added the brief's token set to `src/styles/global.css`:
  `--color-green-soft`, `--color-amber`, `--color-amber-dark`, `--color-sand`,
  `--color-white`, plus a separate `--color-error` for form validation only.
- Retired `--color-red` as the CTA color. Every CTA (`.btn-primary`, sticky
  bar, hero, stock panel, DCR panel, process, contact, lead-form submit) now
  uses amber background + ink text, which passes WCAG AA in both light and
  dark sections.
- Not done: the full light/dark section value-rhythm from §2 of the brief
  (sand → forest → sand → forest → green-soft, etc.) — sections still use the
  palette from the prior repositioning pass (mostly `--color-bg`/`--color-forest`),
  not the newly-added `--color-sand`/`--color-green-soft`. Visually close but
  not a token-for-token match to the brief's rhythm table.

**Phase 0/3 — Content correctness**
- Hero stats corrected to the brief's mandated figures — **250+ authorised
  selling partners / 15,000+ sites powered by material we supplied / 100+
  installations executed by our own team** — replacing placeholder figures
  (500+/Same-day/Full range) that were still live in both locales, in Latin
  digits.
- Removed the duplicated vendor/dispatch stat pair from the Stock panel now
  that the real stats live only in the hero (brief §4.4).
- Added the DCR/Non-DCR section (`src/components/sections/Dcr.astro`), which
  was entirely missing — full-bleed forest panel between Products and Why us,
  covering DCR (bi-facial/TOPCon bi-facial, 500–590 W) vs Non-DCR (TOPCon
  585–615 W, Mono-PERC 545 W), in both locales, wired into `HomePage.astro`.
- Trimmed the header nav to four items (Stock · Products · How it works ·
  Contact); Home solar now renders as a visually distinct trailing link
  (italic, separated by a divider) instead of a sixth nav peer.
- Added `/terms` and `/en/terms` pages (previously missing), linked from the
  footer.
- Added the "Built by Sartoria Systems" footer credit, linking to
  `https://sartoria.systems` with `rel="noopener"`.

**Phase 4 — Motion**
- Added count-up animation to the three hero stats: `IntersectionObserver`,
  ease-out over 1200ms, fires once on first scroll into view. Real figures
  are always server-rendered first, so nothing depends on the script running
  — reduced-motion and no-JS visitors simply see the final numbers with no
  animation.
- Not done: scroll-reveal fade-up on section entry (brief §5.1) is not yet
  implemented anywhere.

**Phase 8 — Launch readiness**
- `robots.txt` now blocks all crawlers (`Disallow: /`) with a `TODO:` to
  reopen it once the owner approves launch — it was previously open
  (`Allow: /`), the inverse of what an unlaunched site should ship.

All changes build cleanly (`npm run build` passes) and were committed in two
commits this session.

## 2. Lighthouse

Not re-run this session — the `lighthouse/` reports in the repo predate
these changes and should not be quoted as current. Needs a fresh mobile audit
on both locales before launch, per brief §9.

## 3. Client JS

Unchanged in kind: the new count-up script is inlined into each page's HTML
(same `inlineStylesheets`/scripts strategy already in place) rather than
shipped as a separate bundle, so it adds only a few hundred bytes of inline
script, not a new network request.

## 4. TODOs left in code

- `src/data/business.ts`: `email` is a temporary Gmail address (`TODO(MNJ)`
  comment on the field) — replace with a domain mailbox.
- `src/data/business.ts`: `instagram`/`facebook`/`youtube`/`gbpUrl`/
  `justdial`/`indiamart` are empty pending real profile URLs.
- `public/robots.txt`: blocks all crawlers pending owner launch approval.
- `src/pages/privacy.astro`, `src/pages/en/privacy.astro`,
  `src/pages/terms.astro`, `src/pages/en/terms.astro`: all carry inline TODO
  comments that legal content needs lawyer review against DPDP Act 2023
  before launch (privacy TODO pre-existing; terms TODO added this session).

## 5. Ambiguous calls made, and why

- Terms of use content was written from scratch (the brief specified the
  fields to cover — no prices/stock guarantee, product/warranty pass-through,
  accuracy disclaimer — but not exact copy). Kept deliberately short and
  conservative, matching the privacy policy's tone and its "not legal advice,
  needs review" disclaimer.
- The footer's third link row now points to `#faq` (an in-page anchor) rather
  than a standalone `/faq` route, since no such page exists and the brief
  only asked for FAQ to move to a footer link, not to become its own page.
- DCR section placed between Products and Why us, matching the brief's
  explicit ordering. No product photography exists for DCR/Non-DCR
  specifically, so the section is spec-only (no image slot) — consistent
  with how Stock.astro already handles its panel (icons, not photos).

## 6. Not completed — blocking items for the next session

These are still open against the brief and were not attempted this session
due to scope:

1. **Full value-rhythm section redesign** (brief §2) — sections don't yet
   alternate through the newly-added `--color-sand`/`--color-green-soft`
   tokens in the exact sequence specified.
2. **Scroll-reveal fade-up** on section entry (brief §5.1) — not built.
3. **`src/data/products.ts`** as a discrete data module (brief §4.6) —
   product data still lives inline in `Products.astro` + i18n JSON rather
   than the dedicated file the brief specifies. Functionally equivalent
   (four categories, no mounting structures, already confirmed correct by
   the audit) but not structured as asked.
4. **FAQ reorder** — brief wants exactly 7 questions, B2B first, homeowner
   last. Current `en.json` has 13 `"q":` entries across the whole file (home
   page + /home-solar combined) — needs a per-page split and recount, not
   yet done.
5. **Solfin financing block** on `/home-solar` (brief §8, Phase 7) — not
   found anywhere in the codebase; needs to be added (name + number only, no
   calculator/application form).
6. **PHP backend** (`server/`) — present and structurally complete per the
   audit (lead.php, auth.php, db.php, cron, migrations all exist) but **not
   code-reviewed line by line** this session for correctness against the
   brief's validation/rate-limit/CSRF rules. Treat as unverified, not done.
7. **Territory sweep** (brief §2, Phase 1) — not re-run this session; the
   original repositioning commits claim this was done, but it wasn't
   independently re-checked here.
8. **Analytics**: `GA_MEASUREMENT_ID` is still the placeholder
   (`G-TODO-NOT-SET`), so analytics stays off by design — needs a real GA4
   property ID before it starts firing.
9. Fresh Lighthouse mobile audit on both locales (§2 above).

## 7. Manual steps still required (unchanged from brief §10.7)

- hPanel database creation for the PHP backend
- Cron job setup for `server/cron/digest.php` and `monthly-archive.php`
  (expressions documented in `server/README.md`)
- Populate `server/config.php` from `server/config.example.php` with real
  DB credentials and bcrypt hashes for the `desk`/`admin` roles
- Upload mapping: `dist/*` → `public_html/`, `server/api` → `public_html/api`,
  `server/leads` → `public_html/leads`, `server/admin` → `public_html/admin`
- Real domain mailbox to replace the temporary Gmail address
- GA4 property creation, then set `GA_MEASUREMENT_ID`
- Remove the `robots.txt` crawler block once the owner approves launch
