/**
 * Single source of truth for the business's real-world details.
 *
 * The footer trust block, the contact section, the sticky CTA bar and the
 * LocalBusiness JSON-LD all read from here — so the schema can never drift out of
 * sync with what is printed on the page, which is exactly the kind of mismatch
 * Google penalises.
 *
 * ⚠️ EVERY VALUE MARKED `TODO(MNJ)` IS A PLACEHOLDER AND MUST BE REPLACED BEFORE LAUNCH.
 */

export const BUSINESS = {
  /** Registered Latin name. Singular, per the shopfront board. */
  legalName: "Manoja Agencies",
  /** Devanagari name. Singular, per the shopfront board — not मनोजा एजन्सीज (plural). */
  nameDevanagari: "मनोजा एजन्सी",

  // --- contact -------------------------------------------------------------
  // TODO(MNJ): real landline number — NOT REAL, obviously-fake placeholder so this
  // can never ship by accident.
  phoneDisplay: "TODO-PHONE-NOT-SET",
  phoneE164: "+910000000000",

  // TODO(MNJ): real WhatsApp mobile. WhatsApp will not open on a landline —
  // this must be a 10-digit mobile. Format: 91XXXXXXXXXX, no +, no spaces.
  // NOT REAL — obviously-fake placeholder so this can never ship by accident.
  whatsappE164: "910000000000",

  // TODO(MNJ): real email, or delete the field and the footer row that uses it.
  // NOT REAL — obviously-fake placeholder so this can never ship by accident.
  email: "TODO-EMAIL-NOT-SET@example.invalid",

  // --- address -------------------------------------------------------------
  // TODO(MNJ): add the shop/unit number if there is one — the building holds
  // several of the owner's businesses, so "Ambajogai Road" alone may not be
  // enough for a first-time visitor to pick the right shutter.
  address: {
    street: "Ambajogai Road, Netaji Nagar",
    locality: "Latur",
    region: "Maharashtra",
    postalCode: "413512",
    country: "IN",
  },

  /** Google Plus Code — a precise locator that works even without a street number. */
  plusCode: "CH37+9X Latur, Maharashtra",

  /** Verified against the Maps pin for the building (Ambajogai Rd / Silver Tower). */
  geo: { lat: 18.4034346, lng: 76.5649603 },

  // Coordinate query rather than a place URL: it drops a pin on the shop itself.
  // TODO(MNJ): if a Google Business Profile is created for Manoja Agencies,
  // swap this for that listing's share link so reviews and hours ride along.
  mapsUrl: "https://www.google.com/maps/search/?api=1&query=18.4034346%2C76.5649603",

  // --- registration --------------------------------------------------------
  /** Read off the shopfront board. */
  gstin: "27AEWPB7904E1ZD",

  /** Authorised Waaree Energies channel partner since. schema.org date format. */
  waareePartnerSince: "2025-01",

  // --- hours ---------------------------------------------------------------
  hours: {
    display: { mr: "सोम — शनि · सकाळी १० ते रात्री ९", en: "Mon–Sat · 10am – 9pm" },
    /** schema.org openingHours format, used by the JSON-LD. */
    schema: ["Mo-Sa 10:00-21:00"],
  },

  // --- social / profiles ---------------------------------------------------
  // TODO(MNJ): add real profile URLs, or leave empty — an empty array is fine and
  // is better than a `sameAs` pointing at a page that does not exist.
  sameAs: [] as string[],

  /**
   * Service area for the JSON-LD. Waaree does not permit a channel partner to
   * advertise coverage beyond its assigned territory — Latur only. Do not add
   * Marathwada, Maharashtra, or any district list here.
   */
  areaServed: ["Latur"],
} as const;

/** Digits only, for `wa.me` links. */
export function whatsappLink(prefilledMessage?: string): string {
  const base = `https://wa.me/${BUSINESS.whatsappE164}`;
  return prefilledMessage
    ? `${base}?text=${encodeURIComponent(prefilledMessage)}`
    : base;
}

export function telLink(): string {
  return `tel:${BUSINESS.phoneE164}`;
}

/**
 * Whether the placeholder phone/email have been replaced with real values.
 * A fake number visible on the page reads as an obvious placeholder to a
 * human; the same fake number published in LocalBusiness JSON-LD reads as
 * data to Google. JsonLd.astro uses these to omit the field entirely rather
 * than publish a placeholder as structured fact.
 */
export function hasRealPhone(): boolean {
  return BUSINESS.phoneE164 !== "+910000000000";
}

export function hasRealEmail(): boolean {
  return !BUSINESS.email.startsWith("TODO-EMAIL-NOT-SET");
}
