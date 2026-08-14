/**
 * Named image imports that get swapped for real photography later. Keeping
 * these here means the swap is a one-line edit (replace the file, keep the
 * name) instead of a hunt through every component that renders it.
 *
 * Imported from src/assets/ rather than referenced as a public/ path so
 * astro:assets (Picture/getImage) can generate the responsive WebP + JPEG
 * variants at build time — public/ files are served as-is, unprocessed.
 */
import heroStock from "../assets/images/hero-stock.jpg";
import productPanels from "../assets/images/product-panels.jpg";
import productInverters from "../assets/images/product-inverters.jpg";
import productCables from "../assets/images/product-cables.jpg";
import productAccessories from "../assets/images/product-accessories.jpg";

/** Aerial stock photo (licensed placeholder) — TODO(MNJ): replace with the
 *  owner's own Latur godown photography once it exists. See BUILD-REPORT.md. */
export const HERO_IMAGE = heroStock;

/** Stand-in stock photos (free-to-use, Pexels license), one per product
 *  category — TODO(MNJ): replace with official Waaree product photography. */
export const PRODUCT_IMAGES = {
  panels: productPanels,
  inverters: productInverters,
  cables: productCables,
  accessories: productAccessories,
} as const;
