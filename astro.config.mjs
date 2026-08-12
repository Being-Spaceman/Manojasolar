// @ts-check
import { defineConfig } from "astro/config";
import sitemap from "@astrojs/sitemap";
import tailwindcss from "@tailwindcss/vite";

const SITE = "https://manojaagencies.in";

export default defineConfig({
  site: SITE,
  output: "static",
  trailingSlash: "ignore",

  // Marathi is the default and lives at /, English at /en/. This is a deliberate reading
  // of the design: "Marathi is the default state, not a translation."
  i18n: {
    locales: ["mr", "en"],
    defaultLocale: "mr",
    routing: {
      prefixDefaultLocale: false,
      redirectToDefaultLocale: false,
    },
  },

  integrations: [
    sitemap({
      i18n: {
        defaultLocale: "mr",
        locales: { mr: "mr-IN", en: "en-IN" },
      },
    }),
  ],

  build: {
    // Inline the CSS into every page instead of linking it.
    //
    // Re-measured for Stage 7 now that the site is genuinely multi-page
    // (the B2B <-> /home-solar cross-links): with `"always"`, / is 104,977
    // bytes and /home-solar is 93,478 — no second request, ever. Switching to
    // `"never"` externalises one shared 47,368-byte CSS file: a single-page
    // visit costs almost exactly the same total bytes (105,019) but as *two*
    // requests instead of one, and only a visitor who crosses to the second
    // page recovers that cost via caching (two-page total 151,171 vs
    // 198,455 — about 24% less). The dominant traffic pattern is still one
    // page from a WhatsApp forward with no second navigation, so paying a
    // guaranteed extra round trip on every visit to maybe save bytes on the
    // minority that click through to /home-solar is the wrong trade. Revisit
    // if analytics (Stage 8) show cross-page navigation is common.
    inlineStylesheets: "always",
    format: "directory",
  },

  vite: {
    plugins: [tailwindcss()],
    build: {
      cssCodeSplit: false,
      // Same reasoning as inlineStylesheets above: almost every visitor never
      // navigates past their first page, so a script is cheaper to inline on each
      // page than to fetch as a second render-blocking request. Astro inlines a
      // hoisted <script> only if its bundled size is under this limit (default
      // 4 KB, Vite's normal asset-inlining threshold) — raised here so the lead
      // form's validation script, which crossed 4 KB once it grew to cover both
      // audiences, stays inlined instead of becoming a separate _astro/*.js file.
      assetsInlineLimit: 24576,
    },
  },
});
