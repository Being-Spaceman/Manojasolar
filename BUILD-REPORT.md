# Build report — Manoja Agencies repositioning

This is a status report on the 8-phase brief in `CLAUDE-CODE-PROMPT.md`, written
mid-build. Prior sessions had already completed a large share of this work
(see commits `a6cf728`, `b0eefdd`, `391225d`); this session ran a full audit
against the brief, then closed the highest-priority gaps it found. **The
brief is not fully complete** — see §6 below for exactly what's left.

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
