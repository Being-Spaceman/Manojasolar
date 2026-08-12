# Manoja Agencies — Site Audit

**Repo:** `ManojaSolar` · **Audited:** 12 August 2026 · **Branch:** `main` (initial commit, nothing pushed)

This document is written to be self-contained. A reader who has never seen the repository should be able to reason about the site from this file alone.

**One-line summary:** a technically excellent, fully-built, launch-blocked **B2C homeowner lead-generation site**. It is not a B2B site in any respect except one unused string. The build quality is high enough that a B2B revamp should reuse the machinery and replace the message, not start over.

---

## Section 1 — Inventory

### 1.1 Routes

Static output (`output: "static"`), 7 HTML pages. Marathi is the **default locale and carries no URL prefix**; English lives under `/en/`.

| Route | Source | Locale | Indexed | Purpose |
|---|---|---|---|---|
| `/` | `src/pages/index.astro` | mr | yes | Home / the entire sales argument. One long scroll. |
| `/en` | `src/pages/en/index.astro` | en | yes | Same page, English. |
| `/thank-you` | `src/pages/thank-you.astro` | mr | **noindex** | Post-form-submit confirmation. |
| `/en/thank-you` | `src/pages/en/thank-you.astro` | en | **noindex** | ditto |
| `/privacy` | `src/pages/privacy.astro` | mr | yes | Privacy policy. Hand-written, **not legally reviewed**. |
| `/en/privacy` | `src/pages/en/privacy.astro` | en | yes | ditto |
| `/404` | `src/pages/404.astro` | mr | **noindex** | Emitted as `/404.html`; Apache serves it via `public/.htaccess`. |

Both `index.astro` files are 3-line shims that render `<HomePage locale="mr|en" />`. All real content is in components — there is **one** home page implementation, not two.

Also generated: `sitemap-index.xml` + `sitemap-0.xml` (via `@astrojs/sitemap`, i18n-aware), `robots.txt`, `.htaccess`.

### 1.2 Components

**Layout / chrome (every page)**

| Component | Renders |
|---|---|
| `layouts/Base.astro` | The HTML shell: `<head>`, font preloads, skip-link, Header, `<main>` slot, Footer, conditional StickyCta. Owns `--sticky-bar-clearance`. |
| `layout/Seo.astro` | `<title>`, description, canonical, hreflang (mr-IN / en-IN / x-default), Open Graph, Twitter card. |
| `layout/JsonLd.astro` | `LocalBusiness` schema always; `FAQPage` schema when `withFaq`. Built entirely from `data/business.ts`. |
| `layout/Header.astro` | Green bar: `म` tile + stacked Devanagari/Latin wordmark, desktop-only anchor nav, LangToggle. |
| `layout/LangToggle.astro` | 2-segment मरा / ENG control. Two real crawlable `<a>` links, no JS. |
| `layout/Footer.astro` | Forest band: wordmark, blurb, trust block (address/phone/hours/GSTIN), Waaree partner line, copyright + privacy link. |
| `layout/StickyCta.astro` | Mobile-only fixed bottom bar: red WhatsApp button + 52px green outline call button. |

**Home page sections, in render order** (`components/HomePage.astro`)

| # | Component | Renders |
|---|---|---|
| 1 | `sections/Hero.astro` | Eyebrow chip, H1, subhead, cross-language line, red WhatsApp CTA, call link, 3 stats. Photo slot is a **striped CSS placeholder**. |
| 2 | `sections/Subsidy.astro` | Forest panel: headline, a 6:4 split bar (govt vs you), two `₹ --,---` figures, note. Then 3 icon "marks" (inline SVG) + red WhatsApp CTA. |
| 3 | `sections/WhyUs.astro` | Kicker, headline, cross-line, 4 hairline-separated trust points. No CTA. |
| 4 | `sections/Process.astro` | 4 numbered steps on a spine (१२३४ in Marathi). Step 3 inverts to a forest card with 3 bullets. Red WhatsApp CTA (desktop top / mobile bottom). |
| 5 | `sections/LeadForm.astro` | White card: 4 fields + honeypot, red submit, note, green WhatsApp fallback link. |
| 6 | `sections/Faq.astro` | 7 native `<details>` accordions. Zero JS. |
| 7 | `sections/Contact.astro` | Address/phone/hours/GSTIN list, WhatsApp + Directions buttons, click-to-load map facade. |

**Page shells**

| Component | Renders |
|---|---|
| `components/HomePage.astro` | Composes the 7 sections above inside `Base`. |
| `components/ThankYouPage.astro` | Green tick, headline, "what happens next" mist box, WhatsApp CTA, home link. `sticky={false}`. |
| `components/SimplePage.astro` | Generic prose wrapper (H1 + slot + home link). Used by privacy ×2 and 404. |

### 1.3 Full user-facing copy, in order

English is the shape of record. Marathi shown beneath where it differs meaningfully.

**Header**
- Wordmark: `मनोजा एजन्सीज` / `MANOJA AGENCIES`
- Nav (desktop only): Subsidy · Why us · How it works · Questions · Contact
  - mr: अनुदान · आम्हीच का · कसं होतं · प्रश्न · संपर्क
- Skip link: "Skip to content" / "थेट मजकुराकडे जा"

**1. Hero**
- Eyebrow chip: **"Authorised Waaree dealer · Latur"** / "अधिकृत वारी डीलर · लातूर"
- H1: **"Bill of ₹3,000? Bring it near ₹0."** / "वीजबिल ₹३,०००? आता ₹० करा."
- Subhead: "Waaree rooftop solar for homes and small shops — subsidy paperwork handled by us."
  - mr: "घर आणि दुकानासाठी वारी छतावरील सोलर — अनुदानासह."
- Cross-language line (renders the *other* language, so nobody has to touch the toggle)
- **CTA (red): "Ask on WhatsApp"** / "व्हॉट्सअ‍ॅपवर दर विचारा"
- Call link: "or call · 02382 000 000"
- Stats: `240+ roofs done` · `25 yrs Waaree warranty` · `7 days installation`
  - mr uses Devanagari numerals: २४०+ / २५ वर्षे / ७ दिवस
- Photo alt: "A Waaree rooftop solar array installed on a house in Latur" *(no photo exists yet)*

**2. Subsidy**
- Kicker chip: "Government scheme" / "सरकारी योजना"
- H2: **"The government covers a large part of the cost."** / "सोलरचा मोठा खर्च सरकार उचलतं."
- Bar segments: "Government pays" (60%) | "You pay" (40%)
- Figures: `₹ --,---` ×2, captioned "Subsidy · direct to your bank" and "Your share"
- Note: "The figures depend on your roof size — we work out your exact numbers."
- Marks: "Waaree panels installed" / "We file the application" / "Your bill comes down"
- **CTA (red): "Ask what my subsidy is"** / "माझं अनुदान किती? विचारा"
- CTA note: "Send a photo of your electricity bill on WhatsApp — we will tell you the figure."

**3. Why us**
- Kicker: "Why Manoja Agencies"
- H2: **"A local shop, not a call centre."** / "इथलंच दुकान. कॉल सेंटर नाही."
- 4 points: "Our own installation crew" · "The paperwork is ours" · "Waaree equipment throughout" · "Service stays local"
- **No CTA in this section.**

**4. Process**
- Eyebrow chip: "How it works"
- H2: **"Four steps. We handle the hard part."** / "चार पायऱ्या. अवघड भाग आमच्याकडे."
- Steps: 1 Free site survey · 2 Installation · 3 **Subsidy paperwork — entirely our responsibility** (bullets: PM Surya Ghar portal application / Net meter and MSEDCL approval / Subsidy paid straight into your bank account) · 4 Ongoing service
- **CTA (red): "Book a survey — WhatsApp"**

**5. Lead form**
- Kicker: "Free site survey"
- H2: **"Tell us four things."** / "फक्त चार गोष्टी सांगा."
- Labels: Your name · Mobile number (+91) · Average monthly electricity bill (₹) · Roof type
- Roof options: Concrete (RCC) slab · Metal sheet · Tiled roof · Something else
- **Submit (red): "Book a free site visit"**
- Note: "No cost, no obligation. We call you back — we do not turn up unannounced."
- Fallback link (green outline): "Or send it on WhatsApp instead"

**6. FAQ** — H2: **"What people ask us first."**
1. How much does a rooftop system cost?
2. Do I have to apply for the subsidy myself?
3. How long does installation take?
4. What happens when it rains, or at night?
5. What if a panel stops working?
6. How much roof do I need?
7. Can a shop or small business get this?

**7. Contact**
- Kicker "Contact" · H2: **"Come to the shop, or we come to you."**
- Rows: Address · Phone · Open · GSTIN
- **Buttons: "WhatsApp" (red) · "Get directions" (green outline)**
- Map facade button: "Show map"

**Sticky bar (mobile):** "WhatsApp us" (red) + "CALL" (green outline)

**Footer:** wordmark · "Rooftop solar supply, installation and subsidy paperwork across Latur and Marathwada." · trust rows · "Authorised Channel Partner — Waaree Energies" · "© 2026 Manoja Agencies, Latur." · "Privacy policy"

**Thank-you page:** "Got it. We will call you." → "What happens next" (callback within one working day; free site visit if the roof suits) → "Message us on WhatsApp" → "Back to the home page"

**404:** "That page is not here." / "The link may be old or mistyped."

### 1.4 i18n keys and Marathi status

Mechanism: `src/i18n/ui.ts` exposes `useTranslations(locale)` → `t("dot.path")` and `useList(locale)` for arrays. **English is the shape of record**; a missing `mr` key silently falls back to English and warns in dev only.

Key groups: `site`, `meta` (home/thankYou/privacy/notFound), `nav`, `lang`, `hero`, `subsidy`, `why`, `process`, `faq`, `form`, `contact`, `sticky`, `footer`, `thankYou`, `notFound`, `common`.

**Marathi coverage: 100%. Every key in `en.json` has a real Marathi value in `mr.json` — there are no untranslated stubs and no runtime fallbacks firing.** Quality caveats:

- `mr.json` line 2 states plainly that everything except the design-baseline strings is *drafted* and **needs native review before launch**. Translated-but-unreviewed, not missing.
- Four `cross` keys are **intentionally** the opposite language (`hero.cross`, `subsidy.cross`, `why.cross`, `process.cross`, `form.cross`). Not a bug — the design shows the other language under the headline.
- Some Marathi values keep English inline on purpose: `subsidy.marks[].sub` ("Portal, net meter, MSEDCL approval"), `process.steps[].kicker`.
- `form.billPlaceholder` is "उदा. 3000" — Latin digits, while the hero uses Devanagari (₹३,०००). Deliberate (users type Latin digits) but inconsistent on the surface.

**Dead keys — defined in both dictionaries, rendered nowhere:**

| Key | Note |
|---|---|
| `site.tagline` | **"Solar trade supply · Latur" / "सोलर ट्रेड सप्लाय · लातूर"** — see Section 4. The only B2B string in the codebase, and it is not on the site. |
| `lang.switchTo`, `lang.label` | LangToggle uses `LOCALE_FULL_LABEL` from `config.ts` instead. |
| `nav.menuClose` | No mobile menu exists; nav is desktop-only. |
| `contact.mapAria` | The iframe title is taken from the button's sub-label instead. |
| `form.submitting` | Button shows a CSS spinner; "Sending…" never renders. |
| `common.loading`, `common.required`, `common.openInNewTab` | Never referenced. |

---

## Section 2 — Technical state

### 2.1 Framework and build

| | |
|---|---|
| Framework | **Astro 5.16.2**, `output: "static"` |
| Package | `manoja-solar` v0.1.0, ESM, private |
| Runtime deps | `astro`, `@astrojs/sitemap` ^3.6.0 — that is all |
| Dev deps | `tailwindcss` + `@tailwindcss/vite` ^4.1.18, `lighthouse` ^13.4.1 |
| Build | `npm run build` = `python scripts/subset_fonts.py --verify && astro build` |
| Test | `npm test` = font verify + overflow test + sticky test (Playwright-less custom scripts in `scripts/`) |
| Deploy target | Static Apache — `public/.htaccess` present, comments reference Hostinger |
| Domain | **`https://manojaagencies.com` — placeholder**, flagged TODO in `astro.config.mjs:6`. Feeds sitemap, canonical, hreflang, OG URLs. |
| i18n routing | `defaultLocale: "mr"`, `prefixDefaultLocale: false` |
| CSS strategy | `inlineStylesheets: "always"` + `cssCodeSplit: false` — **zero CSS files ship**; all styles inline in each HTML doc |

**Build works.** Verified: 7 pages in ~1.1 s.

### 2.2 "Tailwind config" — important correction

**There is no `tailwind.config.js` and effectively no Tailwind usage.** Tailwind v4 is installed as a Vite plugin, and `src/styles/global.css` opens with `@import "tailwindcss"` — but the design system is expressed as a **`@theme` block of CSS custom properties**, and every component styles itself with **scoped plain CSS in Astro `<style>` blocks**. Across the whole `src/` tree there is not one utility-class-driven layout. Tailwind is present as a token/reset host, not as a styling method.

This matters for a revamp: **there is no utility layer to fight, and no config file to edit.** All design tokens live in one place, `src/styles/global.css`.

**Colour palette** (`@theme`, `global.css:49-66`)

| Token | Hex | Role |
|---|---|---|
| `--color-green` | `#007b3c` | Brand field, header bar, secondary strokes, focus ring |
| `--color-forest` | `#04492a` | Headings, dark bands, footer |
| `--color-ink` | `#14171a` | Body text |
| `--color-red` | `#e1261c` | **CTA only** — the system's hardest rule |
| `--color-mist` | `#eaf3ed` | Green-tint plates, step tiles |
| `--color-spine` | `#d6e5dc` | Process connector line |
| `--color-hair` | `#edebe4` | Hairline dividers |
| `--color-edge` | `#e2e0d9` | Card borders, sticky-bar top border |
| `--color-field` | `#d8d5cc` | Input borders |
| `--color-paper` | `#f7f6f2` | Alternate page background |
| `--color-body` | `#4c5054` | Secondary body copy |
| `--color-muted` | `#55595d` | Captions, form labels |
| `--color-micro` | `#6c7075` | Mono micro-labels |

Documented invariants: no gradients, tints or screens; every pair clears 4.5:1; **red appears at most once per viewport**.

**Type scale** — 20 named sizes, each with a paired line-height because Devanagari needs more leading than Latin at the same size:

`micro` 11px · `caption` 12.5 · `chip` 13 · `small` 14 · `body` 15 · `cross` 15.5 · `base` 16 · `lead` 16.5 · `cta-sm` 17 · `cta` 18 · `subhead` 19 · `step` 20 · `stat` 22 · `h3` 27 · `h2` 29 · `h1` 34 · `h2-lg` 34 · `h1-lg` 40

**Spacing:** `--spacing: 0.0625rem` — a 1px-per-unit scale, so design values map 1:1. Deliberately **not** a 4pt grid (the design uses optical values 7/9/11/13/18/22/26).

**Radius:** `--radius-none: 0px`. There is not one rounded corner in the product UI, by rule.

**Breakpoints:** sm 480 · md 768 · **lg 1024 (the only one that matters — every section's desktop layout keys off `@media (min-width: 64rem)`)** · xl 1120 (container cap).

**Tap targets:** 56px standard, 52px sticky bar, 44px text-only call link.

### 2.3 Fonts

Self-hosted, subsetted, `font-display: swap`. **Nothing is fetched from Google at runtime.**

| File | Size | Weight | Scripts |
|---|---|---|---|
| `mukta-400.woff2` | 71.4 KB | 400 | Latin + Devanagari |
| `mukta-600.woff2` | 75.6 KB | 600 | Latin + Devanagari |
| `mukta-800.woff2` | 70.9 KB | 800 | Latin + Devanagari |
| `plexmono-400.woff2` | 7.3 KB | 400 | **Latin only** |

Total ~225 KB. Only `mukta-800` and `mukta-400` are `<link rel="preload">`ed (the two that paint above the fold).

**Is the Devanagari subset complete? Yes, and it is enforced.** `scripts/subset_fonts.py` keeps the **entire U+0900–U+097F block** plus ZWNJ/ZWJ (U+200C/D) and dotted-circle (U+25CC), rather than subsetting to the characters in today's copy — because Devanagari conjuncts (क्ष, ज्ञ, ऱ्ह, eyelash-ल) are reached through GSUB substitutions, not codepoints, so a `--text` subset would silently break *future* strings. `--verify` runs as part of `npm run build` and **fails the build** if any glyph in `src/i18n` is uncovered. This is unusually careful and should be preserved through any rewrite.

Two deliberate reductions from the original design: only 3 Mukta weights (not 4 — 700 folds into 600/800, saving ~70 KB), and Tiro Devanagari Marathi was dropped entirely (cross-language lines are set in Mukta 400).

Note: Mukta is deliberately placed **inside** the mono stack (`--font-mono: "Plex Mono", "Mukta", …`) so Devanagari in mono-styled kickers falls back to a loaded face rather than whatever the phone ships.

### 2.4 Client-side JavaScript

**No dependency ships any JS to the client. There are zero `.js` files in `dist/`.** Total client JS is two small inline `<script type="module">` blocks that Astro inlined:

| Script | Where | ~Size | Why |
|---|---|---|---|
| Lead-form controller | `LeadForm.astro:374-496` | ~2 KB | Validation, error rendering, busy state, honeypot check, `fetch` POST, redirect. |
| Sticky-bar controller | `StickyCta.astro:109-170` | ~1 KB | Hides the bar when the footer is visible / an input is focused / the visual viewport shrinks (Android keyboard). |

Everything else is HTML/CSS by design: the FAQ uses native `<details>` (no accordion library), the language toggle is two real links (no JS), the map is a CSS facade that constructs an `<iframe>` only on tap, the process spine and FAQ +/− marks are drawn in CSS, and the subsidy icons are inline SVG.

### 2.5 Lighthouse

Real reports are committed in `lighthouse/`. Both were run against a local static server.

| Page | Perf | A11y | Best practices | SEO |
|---|---|---|---|---|
| `/` (mr) | **0.97** | **1.00** | **1.00** | **1.00** |
| `/en` | **0.98** | **1.00** | **1.00** | **1.00** |

Metrics (mr / en): FCP 1.7 s / 1.7 s · LCP 2.4 s / 2.3 s · **TBT 0 ms** · CLS **0** / 0.021 · Speed Index 1.7 s.

Page weight: `dist/index.html` 81.7 KB, `dist/en/index.html` 71.9 KB (inclusive of inlined CSS and JS).

**Remaining performance liabilities** — all latent, none currently firing:

1. **The two image placeholders.** The hero photo slot and the map facade are CSS stripes today. Real photographs are the one thing that can move LCP and CLS. Both boxes have fixed `aspect-ratio`, so dropping images in causes *zero* layout shift **provided** that discipline is kept.
2. **~225 KB of fonts** is the single largest asset class. Unavoidable for bilingual Devanagari, and already cut once.
3. **The Google Maps iframe** pulls roughly a megabyte from ~6 third-party origins — deliberately deferred behind a tap. Making it eager would be the fastest way to destroy the score.
4. **Inlined CSS is not cached across pages.** Correct for the actual traffic pattern (one page from a WhatsApp forward, no second navigation). Would become wrong if the revamp adds a real multi-page catalogue.
5. LCP at ~2.3–2.4 s is local-server-measured; on real 4G with a real hero photo it will be materially worse.

---

## Section 3 — Conversion mechanics

### 3.1 Every CTA

Colour rule: **red = the one primary action in view; green outline = secondary.** Never two reds in one viewport.

| # | Section | Label | Type | Target | `data-cta` |
|---|---|---|---|---|---|
| 1 | Hero | Ask on WhatsApp | **PRIMARY (red, 58px)** | `wa.me` prefilled | `hero-whatsapp` |
| 2 | Hero | or call · {phone} | secondary (text, 44px) | `tel:` | `hero-call` |
| 3 | Subsidy | Ask what my subsidy is | **PRIMARY (red, 56px)** | `wa.me` prefilled | `subsidy-whatsapp` |
| 4 | Process | Book a survey — WhatsApp | **PRIMARY (red, 54px)** desktop only | `wa.me` prefilled | `process-whatsapp-top` |
| 5 | Process | Book a survey — WhatsApp | **PRIMARY (red, 56px)** mobile only | `wa.me` prefilled | `process-whatsapp` |
| 6 | Lead form | Book a free site visit | **PRIMARY (red, 56px)** | form submit | `lead-submit` |
| 7 | Lead form | Or send it on WhatsApp instead | secondary (green, 52px) | `wa.me` prefilled | `lead-whatsapp` |
| 8 | Contact | WhatsApp | **red, 56px** | `wa.me` **no message** | `contact-whatsapp` |
| 9 | Contact | Get directions | secondary (green, 56px) | `BUSINESS.mapsUrl` | `contact-directions` |
| 10 | Contact | Show map | inline (facade button) | swaps in iframe | — |
| 11 | Sticky bar | WhatsApp us | **PRIMARY (red, 52px)** mobile only | `wa.me` prefilled | `sticky-whatsapp` |
| 12 | Sticky bar | CALL | secondary (green, 52×52) | `tel:` | `sticky-call` |
| 13 | Thank-you | Message us on WhatsApp | secondary (green, 56px) | `wa.me` prefilled | `thankyou-whatsapp` |
| 14 | Thank-you | Back to the home page | tertiary (text) | `/` | — |

**Note:** every `data-cta` attribute is present and consistently named, but **nothing reads them.** There is no analytics, no GTM, no event listener. The instrumentation hooks were built and never wired to anything — so there is currently **zero conversion measurement on this site.**

**Count: 8 of 14 CTAs go to WhatsApp.** WhatsApp is unambiguously the primary conversion channel; the form is the secondary one.

### 3.2 The lead form

`src/components/sections/LeadForm.astro`

**Fields — four, plus a honeypot:**

| Field | `name` | Type | Attributes | Validation |
|---|---|---|---|---|
| Your name | `name` | text | `autocomplete="name"`, `enterkeyhint="next"` | non-empty after trim |
| Mobile number | `phone` | tel | `inputmode="numeric"`, `autocomplete="tel-national"`, `maxlength="10"`, `+91` prefix chip | `/^[6-9]\d{9}$/` — Indian mobile, first digit 6–9 |
| Average monthly electricity bill | `bill` | text | `inputmode="numeric"`, `maxlength="7"`, `₹` prefix chip | `/^\d{1,7}$/` after stripping spaces/commas |
| Roof type | `roof` | select | required | must not be empty |
| *(hidden)* Website | `website` | text | off-screen, `tabindex="-1"`, `aria-hidden` | **honeypot** |

Roof options: `rcc` · `sheet` · `tile` · `other`.

**Behaviour:**
- `novalidate` — all validation is custom, so error copy is localised and consistent.
- Errors set `aria-invalid="true"`, render into `role="alert"` boxes, and **focus the first bad field**.
- Errors clear on `input` as soon as the user starts fixing.
- `phone` and `bill` strip non-digits live.
- On submit: `preventDefault` → validate → **honeypot check (if filled, redirect to thank-you and send nothing — the bot logs a win)** → set busy/spinner → `POST FormData` → redirect to `/thank-you` (locale-aware) on ok, else show network error and scroll it into view.
- Payload adds `locale` and `submittedAt` (ISO) beyond the four fields.
- Submit button is deliberately **not** disabled while busy — a greyed button reads as failure on a slow connection.

**🔴 Submit target: `action="/api/lead"` — this endpoint does not exist.** The site is static; there is no server. Flagged at `LeadForm.astro:477`. **Every form submission currently fails** and shows the network error. Suggested options in the comment: a `lead.php` on Hostinger, Formspree, or a Google Apps Script URL. **This is the single hardest launch blocker on the site.**

**Success behaviour:** `window.location.assign("/thank-you")` (or `/en/thank-you`). The thank-you page is `noindex`, has no sticky bar, and promises a callback within one working day.

### 3.3 WhatsApp links

Constructed by `whatsappLink(msg)` in `src/data/business.ts`:

```
https://wa.me/{BUSINESS.whatsappE164}[?text={encodeURIComponent(msg)}]
```

**🔴 `whatsappE164` is `"919999999999"` — a placeholder. Every WhatsApp button on the site currently goes to a fake number.** Second-hardest launch blocker.

Prefilled messages are locale-aware and **section-specific** — a genuinely good touch, because the rep can tell from the first message which part of the page converted:

| Location | Marathi | English |
|---|---|---|
| Hero | नमस्कार, मला छतावरील सोलरचे दर हवे आहेत. | Hello, I would like a price for rooftop solar. |
| Subsidy | नमस्कार, माझं अनुदान किती मिळेल? माझं वीजबिल पाठवतो. | Hello, how much subsidy would I get? I will send my electricity bill. |
| Process | नमस्कार, मला मोफत साइट सर्व्हे बुक करायचा आहे. | Hello, I would like to book a free site survey. |
| Lead form | नमस्कार, मला मोफत साइट व्हिजिट हवी आहे. | Hello, I would like a free site visit. |
| Sticky bar | नमस्कार, मला छतावरील सोलरबद्दल माहिती हवी आहे. | Hello, I would like to know about rooftop solar. |
| Thank-you | नमस्कार, मी नुकतीच वेबसाइटवर माहिती भरली आहे. | Hello, I have just submitted my details on the website. |
| **Contact** | **— none —** | **— none —** |

Contact calls `whatsappLink()` with no argument, so it opens a blank chat. Inconsistent with the other seven; likely an oversight.

Phone links use `telLink()` → `tel:{phoneE164}` → **`+912382000000`, also a placeholder.**

### 3.4 Every price / cost / rupee figure in the codebase

**There is not one real price anywhere in this repository.** This is a deliberate, documented policy, not an oversight — `Subsidy.astro:22-29` explains that amounts depend on roof size, usage and system size, that a printed figure would be wrong for most readers, and that it is a compliance risk. Quotes are given per-customer over WhatsApp after a site visit.

Complete inventory:

| Location | Content | Status |
|---|---|---|
| `en.json:50` / `mr.json:50` | `"Bill of ₹3,000? Bring it near ₹0."` / `"वीजबिल ₹३,०००? आता ₹० करा."` | **The only concrete rupee figures on the site.** Illustrative of a household bill, not a price. |
| `en.json:69` / `mr.json:69` | `subsidy.amountPlaceholder: "₹ --,---"` | Placeholder, rendered **twice** in the subsidy panel (govt share and your share) |
| `en.json:195` / `mr.json:195` | `form.billPrefix: "₹"` | Input prefix chip |
| `en.json:194` | `form.billPlaceholder: "e.g. 3000"` / "उदा. 3000" | Input hint |
| `en.json:65` | "The government covers a large part of the cost." | Qualitative |
| `en.json:154-155` | FAQ 1 — "How much does a rooftop system cost?" → explicitly refuses to print a price | Policy, stated to the customer |
| `en.json:206` | "No cost, no obligation." | Qualitative |
| `en.json:213` | "Enter the bill amount in rupees, numbers only." | Error string |
| `mr.json:179` | FAQ 7 — commercial "खर्च लवकर भरून निघतो … कोटेशन आम्ही वेगळं देतो" | Qualitative payback claim |
| `Subsidy.astro:23-29` | TODO comment explaining the whole policy | Comment |
| `scripts/make_images.py:96` | `<h1>वीजबिल ₹३,०००?<br>आता ₹० करा.</h1>` | OG-image generator, mirrors the hero |
| `subset_fonts.py:68` | `U+20B9` kept in the font subset | Build config |

The 6:4 split bar in the subsidy section communicates the *ratio* visually without ever stating an amount — the design was explicitly built to work with the blanks in place.

---

## Section 4 — Audience read

Asked bluntly, answered bluntly.

**This is a B2C homeowner site. Not "mostly" — structurally, at every level.** The suspicion in the brief is correct and if anything understated. The most damning evidence is not the copy, it is the **product architecture**: the entire second section, the heaviest step of the process, and the FAQ's centre of gravity are built on **PM Surya Ghar, a subsidy that is only available on domestic electricity connections.** A distributor cannot claim it. Roughly a third of the page's persuasive surface is inapplicable to a B2B buyer as a matter of scheme eligibility, not tone.

| Section | Verdict | Reasoning |
|---|---|---|
| **Header** | **Neutral** | Wordmark, anchor nav, language toggle. Nav *labels* inherit the B2C section names ("Subsidy") but the chrome itself is audience-agnostic. |
| **Hero** | **B2C — hard** | "Bill of ₹3,000? Bring it near ₹0." is a household electricity bill. A distributor does not have a ₹3,000 bill and is not buying to cut it. "homes and small shops". Stats are end-customer proof (`240+ roofs done`, `7 days installation`) — installation throughput, not supply capability. CTA "Ask on WhatsApp" / "दर विचारा" = a retail price enquiry. |
| **Subsidy** | **B2C — hardest** | PM Surya Ghar is **domestic-connection-only**. "Subsidy · direct to your bank", "Send a photo of your electricity bill". Entirely inapplicable to a reseller. This section cannot be rewritten for B2B — it has to be replaced. |
| **Why us** | **B2C, softly** | Reads as installer-differentiation to a nervous homeowner: "our own installation crew", "you do not visit a single office", "walk into the shop", "repairs do not wait on a ticket queue". A distributor cares about stock depth, margin, credit terms, dispatch time, warranty claim turnaround — none of which appear. **Closest of the four content sections to being salvageable**, because the underlying claims (local, own crew, authorised, service) have real B2B analogues. |
| **Process** | **B2C** | The four steps describe **one homeowner's single installation**: site survey → install → subsidy filing → ongoing service. A repeat wholesale order has no site survey and no subsidy step. Step 3 — the deliberately weighted "anxiety step" — is the subsidy paperwork, which is the most B2C moment on the page. |
| **Lead form** | **B2C — hard** | The four fields are a homeowner profile. "Average monthly electricity bill" is a residential sizing proxy and meaningless to a distributor. "Roof type: RCC / sheet / tile" presumes the buyer owns the roof. A B2B form needs firm name, GSTIN, city/territory, monthly volume, product mix — **zero overlap**. |
| **FAQ** | **B2C, 6 of 7** | Q1 cost, Q2 "do I apply for the subsidy myself", Q3 install time, Q4 rain/night, Q5 broken panel, Q6 "how much roof do I need" — all end-customer anxieties. **Q7 is the single B2B-adjacent string on the site** ("Can a shop or small business get this?") and even it answers by explaining that the *subsidy* does not apply, then offers a separate quote. That is a B2C site politely deflecting a commercial enquiry. |
| **Contact** | **Neutral** | Address, phone, hours, GSTIN, map, directions. Works for any audience. |
| **Sticky CTA** | **Neutral** | WhatsApp + call. Channel, not message. |
| **Footer** | **Neutral, leaning B2C** | "Rooftop solar supply, installation and subsidy paperwork across Latur and Marathwada" — "installation and subsidy paperwork" is retail service language; "supply … across Marathwada" is the one phrase with distributor flavour. The Waaree partner line is genuinely dual-purpose and is arguably the strongest B2B asset on the page. |
| **Thank-you** | **B2C** | "we fix a free site visit", "if the roof looks suitable". Homeowner journey. |
| **Privacy / 404** | **Neutral** | Privacy enumerates the four B2C form fields, so it follows whatever the form becomes. |

**Tally:** 5 sections hard/soft B2C, 4 neutral (all of them chrome — header, contact, sticky, footer), **0 B2B**.

### The one exception, and it is telling

`site.tagline` is defined in both dictionaries as **"Solar trade supply · Latur" / "सोलर ट्रेड सप्लाय · लातूर"**.

"Trade supply" is unambiguously B2B language — it is the phrase a distributor uses. **It is rendered nowhere on the site.** It exists in the dictionary and no component calls it.

Read that as an artefact: at some point the business was described as trade supply, and the site that got built around it is a homeowner installation funnel. Whether that was a deliberate pivot or a drift is the most important question in Section 6 — and the answer determines whether the revamp is a repositioning or a second site.

---

## Section 5 — Reusable vs rewrite

Assuming the target is a B2B / distributor-facing site.

### Bucket A — reusable as-is (no content change)

| Item | Reasoning |
|---|---|
| **Entire design token system** (`global.css`) | Palette, type scale, spacing, radius rule, breakpoints, tap targets. Audience-neutral, documented, accessibility-verified. Nothing here is B2C. |
| **Font pipeline** (`subset_fonts.py`, `@font-face`) | Whole-block Devanagari subsetting with build-time verification. Expensive to rebuild, works, and survives any copy change — which is exactly what a rewrite needs. |
| **`Base.astro`** | Shell, preloads, skip link, sticky clearance. Structural. |
| **`Seo.astro`** | Canonical, hreflang, OG, Twitter. Takes props; content-agnostic. |
| **`LangToggle.astro`** | Two crawlable links. Perfect as-is. |
| **`SimplePage.astro`** | Generic prose wrapper. |
| **i18n machinery** (`ui.ts`, `config.ts`) | `t()`/`list()` with English-as-record fallback, locale path helpers. Good code, no copy in it. |
| **`StickyCta.astro`** (component) | The hide-on-footer / hide-on-keyboard logic is genuinely well-built. Labels change; mechanism does not. |
| **`Faq.astro`** (component) | Native `<details>`, zero JS. Questions change; component does not. |
| **`data/business.ts`** | Single source of truth feeding footer, contact, JSON-LD. Just finish filling it in. |
| **`Contact.astro`** | Address, hours, map facade, directions. A distributor needs the same block. |
| **Build config** | Astro static, inlined CSS, sitemap, i18n routing. |
| **The Waaree partner line + `LocalBusiness` JSON-LD** | Authorised-channel-partner status is *more* valuable B2B than B2C. |

### Bucket B — reusable with copy changes only

| Item | What changes | Reasoning |
|---|---|---|
| **`Hero.astro`** | Headline, subhead, eyebrow, CTA label, **all three stats** | The layout — chip / H1 / subhead / cross-line / red CTA / call link / 3 stats / photo — is a perfectly good B2B hero. Swap "Bill of ₹3,000" for a supply proposition and swap the stats to distributor metrics (dealers served, dispatch time, stock lines, years as channel partner). Structure survives intact. |
| **`WhyUs.astro`** | All 4 point titles and bodies | Four hairline-separated trust points is the right shape for B2B differentiators. Replace crew/paperwork/warranty/local-service with stock depth, dispatch, credit terms, claim turnaround. |
| **`Faq.astro` content** | All 7 Q&As | Component reuses; questions are 100% end-customer. New set: MOQ, pricing tiers, credit, dispatch, warranty claims, territory, technical support. |
| **`Footer.astro`** | `builtNote` blurb | "installation and subsidy paperwork" → trade supply language. Everything else structural. |
| **`ThankYouPage.astro`** | Body copy | "free site visit" → "our territory manager will call". Layout fine. |
| **`Header.astro`** | Nav labels | Nav keys follow whatever the sections become. Component untouched. |
| **Privacy pages** | Field enumeration | Must list whatever the new form collects. **Needs legal review either way — currently unreviewed.** |
| **`Process.astro`** *(borderline B/C)* | All 4 steps, and reconsider the "heavy" step | The 4-step spine with one weighted step is strong and reusable **if** a B2B journey is genuinely four steps (enquiry → quote/terms → dispatch → support). But step 3's weight exists because subsidy paperwork was the *anxiety* step; the B2B anxiety is different (credit terms? stock availability?). Reusable as a component, but requires a real decision about what the weighted step is — otherwise it becomes decoration. |

### Bucket C — needs structural rebuild

| Item | Reasoning |
|---|---|
| **`Subsidy.astro`** | **Delete or replace wholesale.** PM Surya Ghar is domestic-only; a distributor is ineligible. The 6:4 bar, the two `₹ --,---` figures, the three marks and the "send your electricity bill" CTA are all built on a scheme that does not apply. Copy edits cannot save it. The *slot* — a forest-panel hero-within-the-page carrying the strongest commercial argument — is worth keeping and refilling (margin structure? volume pricing? scheme support *for their* customers?). |
| **`LeadForm.astro`** | **Fields, validation and payload rebuilt; shell and controller reused.** Name/phone/bill/roof → firm name, contact, GSTIN, city/territory, monthly volume, product interest. Bill and roof-type validation deleted; GSTIN format validation added. The card layout, error handling, honeypot, busy state and redirect are all genuinely reusable — this is a rebuild of the *contents*, not the machinery. |
| **The lead backend** | **Does not exist.** `/api/lead` is a 404 on a static host. Must be built regardless of audience, and B2B leads need routing/notification a static form does not provide. |
| **Analytics** | **Does not exist.** 14 `data-cta` hooks, nothing reading them. A B2B revamp without conversion measurement repeats the current mistake: no way to know whether the repositioning worked. |
| **Conversion channel strategy** | 8 of 14 CTAs go to WhatsApp with prefilled *retail* messages ("मला दर हवे आहेत"). WhatsApp is right for Indian B2B too, but every prefill needs rewriting, and a distributor funnel probably needs a callable line and a quote/pricelist path that the current single-channel design does not have. |
| **Hero photo + shop photos** | Placeholders. Two real photos now exist in `public/photos/` (building exterior, shopfront) but **are wired into nothing**, and both appear to be Google Street View captures — see Section 6. |
| **Page architecture** | The whole site is one long scroll. That is correct for a WhatsApp-forwarded B2C landing page. A distributor evaluating a supplier will want a product/pricelist page, a terms page, and possibly a dealer login — a genuinely multi-page structure. **If that happens, revisit `inlineStylesheets: "always"`**, which is optimised for a single-page visit and becomes a liability across a real multi-page site. |

---

## Section 6 — Open questions

### Blocking — the site cannot launch as-is

1. **The lead form has no backend.** `action="/api/lead"` does not exist on a static host. Every submission fails. Decide: Hostinger `lead.php`, Formspree, Google Apps Script, or a serverless function — and whether leads go to email, a sheet, or WhatsApp.
2. **The WhatsApp number is fake** (`919999999999`). Eight CTAs point at it. Must be a **10-digit mobile** — WhatsApp will not open on a landline.
3. **The phone number is fake** (`02382 000 000` / `+912382000000`). Appears in hero, footer, contact, sticky bar, both privacy pages and the JSON-LD.
4. **The domain is a placeholder** (`https://manojaagencies.com` in `astro.config.mjs`). It is baked into sitemap, canonicals, hreflang and OG URLs — wrong here means wrong everywhere for SEO.
5. **The email is a placeholder** (`info@manojaagencies.com`), and it ships in the `LocalBusiness` schema. Either make it real or delete the field.

### Data still unconfirmed

6. **Opening hours.** `business.ts` says Mon–Sat 10:00–20:00; the Google listing for the building says it closes at 22:00. Which is the shop's, and what is the weekly off?
7. **Shop / unit number.** The address is now `Ambajogai Road, Netaji Nagar, Latur 413512` with verified coordinates (18.4034346, 76.5649603) and Plus Code CH37+9X. But the owner runs **several businesses in the same building**, and the ground floor also shows a "BRIVIN" sign. A first-time visitor cannot tell which shutter is Manoja Agencies.
8. **The business name is inconsistent.** The site says `मनोजा एजन्सीज`; the shopfront board says `मनोजा एजन्सी` (singular). The board is the more authoritative signal. Affects the wordmark, footer, JSON-LD `name`/`alternateName` and every meta title.
9. **No Google Business Profile.** The Maps pin found is "Silver Tower" — the building, not the business. `mapsUrl` currently points at raw coordinates. A real GBP listing is the single largest local-SEO gap and would let the Directions button carry reviews and hours.
10. **`sameAs` is empty.** No social or directory profiles in the schema. Fine if none exist; a gap if they do.
11. **GSTIN was read off a photograph** (`27AEWPB7904E1ZD`, from the shopfront board). Format-valid and plausible, but confirm against the certificate — it ships in the JSON-LD as `taxID`.

### Half-built or unwired

12. **Two photos exist and are used nowhere.** `public/photos/building-silver-tower.png` and `public/photos/storefront-manoja-agencies.png` are in the repo; the hero photo slot and map facade are still CSS stripes. **Both appear to be Google Street View captures** (a Google watermark is visible on the building shot) — the business is theirs, but the photograph is Google's. Own photos should be taken before either is published.
13. **Zero analytics.** 14 `data-cta` attributes, consistently named, read by nothing.
14. **Eight dead i18n keys** (listed in §1.4), including `site.tagline`.
15. **Contact's WhatsApp button has no prefilled message** while the other seven do.
16. **`form.submitting` ("Sending…") never renders** — the button shows only a spinner.
17. **Privacy policy is unreviewed.** Both language versions carry a TODO saying so. It describes the build accurately but has had no legal check — particularly retention period and who data is shared with.
18. **Marathi copy is unreviewed by a native speaker.** `mr.json` says so on line 2. Coverage is complete; quality is not signed off.

### Decisions needed before a B2B revamp

19. **What is the business actually selling, and to whom?** The dead `site.tagline` says "Solar trade supply". The entire built site says rooftop installation for homeowners. **These are different businesses with different buyers, different economics and different proof.** Everything below depends on this answer.
20. **One site or two?** B2C homeowner and B2B distributor funnels rarely coexist well on one page — the subsidy argument that converts a homeowner is irrelevant to a reseller, and trade pricing language actively repels a homeowner. Options: replace, add a second site/subdomain, or split into two clear paths from a shared home page. **This is the biggest structural decision.**
21. **If B2B: what replaces the subsidy section?** It occupies the strongest slot on the page and is scheme-ineligible for distributors. What is the equivalent single most persuasive commercial argument — margin, volume pricing, credit terms, stock depth?
22. **What does the B2B lead form collect,** and does it need GSTIN validation, a territory field, or a volume band?
23. **Does the B2B buyer get prices?** The current no-price policy is well-reasoned for B2C. Trade buyers usually expect at least indicative pricing or a downloadable pricelist — possibly gated. This inverts the site's most deliberate content decision.
24. **Is WhatsApp still the primary channel?** It works for Indian B2B, but the current prefilled messages are all retail-flavoured and there is no callable-desk or quote-request path.
25. **Does the process section survive?** It needs a genuine four-beat B2B journey and a real answer to which step is the anxiety step — otherwise it is a strong component carrying no argument.
26. **Does Marathi-first still hold?** Correct for Latur homeowners. A distributor audience across Marathwada may skew more English/Hindi. The whole routing model assumes Marathi is the default state, not a translation.
27. **Does the site stay single-page?** If a B2B revamp adds product, pricelist and terms pages, revisit `inlineStylesheets: "always"` — it is tuned for one-page visits and becomes a caching liability across many.
