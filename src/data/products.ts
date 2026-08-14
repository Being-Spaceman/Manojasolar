/**
 * Full catalogue, one supplier (Waaree), no prices, no stock badges — every
 * item routes to WhatsApp. Category order matches the four cards on the
 * homepage Products section (src/i18n/*.json "products.items"), so the
 * card-to-category mapping there stays aligned with this file by index.
 */
export interface ProductItem {
  family: string;
  watts?: number[];
  kw?: number[];
  sizes?: string[];
  brands?: string[];
  variants?: string[];
}

export interface ProductCategory {
  id: "panels" | "inverters" | "cables" | "accessories";
  items: ProductItem[];
}

export const categories: ProductCategory[] = [
  {
    id: "panels",
    items: [
      { family: "DCR Bi-facial", watts: [500, 520, 525, 530, 535, 540, 550] },
      { family: "TOPCon Bi-facial DCR", watts: [555, 560, 570, 580, 585, 590] },
      { family: "Non-DCR TOPCon", watts: [585, 590, 615] },
      { family: "Non-DCR Mono-PERC", watts: [545] },
    ],
  },
  {
    id: "inverters",
    items: [
      { family: "Single phase", kw: [3.3, 4, 5, 6] },
      { family: "Three phase", kw: [5, 6, 8, 10, 15, 30, 50, 100] },
    ],
  },
  {
    id: "cables",
    items: [
      {
        family: "DC cable",
        brands: ["WaCab", "Polycab"],
        sizes: ["2.5 sq.mm", "4 sq.mm", "6 sq.mm"],
      },
      { family: "LA cable (copper)", sizes: ["16 sq.mm"] },
      { family: "Earthing cable", sizes: ["6 sq.mm"] },
    ],
  },
  {
    id: "accessories",
    items: [
      { family: "AC DB", variants: ["Single phase 230V", "Three phase 440V"] },
      {
        family: "DCDB",
        variants: ["1-in-1-out 600V/1000V", "2-in-2-out 600V/1000V"],
      },
      { family: "Earthing kit", sizes: ["14mm", "17mm", "25mm", "50mm"] },
      { family: "Pit cover" },
      { family: "Chemical bag" },
      { family: "Generation meter", variants: ["Single phase", "Three phase"] },
      { family: "MC4 connector" },
      { family: "Cable tie", variants: ["PVC", "SS"] },
      { family: "Cable tray", sizes: ["45 × 45 mm"] },
    ],
  },
];

/** One readable line of specs for a product item, in whichever fields it has. */
export function specLine(item: ProductItem): string {
  const parts: string[] = [];
  if (item.watts) parts.push(item.watts.map((w) => `${w} W`).join(", "));
  if (item.kw) parts.push(item.kw.map((k) => `${k} kW`).join(", "));
  if (item.sizes) parts.push(item.sizes.join(", "));
  if (item.brands) parts.push(item.brands.join(" / "));
  if (item.variants) parts.push(item.variants.join(", "));
  return parts.join(" · ");
}
