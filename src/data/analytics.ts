/**
 * GA4 wiring for the 14 `data-cta` hooks already on every page. Analytics
 * stays entirely off — no script tag, no third-party request — until a real
 * measurement ID replaces the placeholder below, so this never ships a
 * broken snippet or starts sending junk events by accident.
 *
 * TODO(MNJ): replace with the real GA4 Measurement ID (format `G-XXXXXXXXXX`)
 * once a GA4 property exists for manojaagencies.in.
 */
export const GA_MEASUREMENT_ID = "G-TODO-NOT-SET";

export function analyticsEnabled(): boolean {
  return /^G-[A-Z0-9]{6,}$/.test(GA_MEASUREMENT_ID) && GA_MEASUREMENT_ID !== "G-TODO-NOT-SET";
}
