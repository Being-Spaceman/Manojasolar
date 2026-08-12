export const LOCALES = ["mr", "en"] as const;
export type Locale = (typeof LOCALES)[number];

export const DEFAULT_LOCALE: Locale = "mr";

/** BCP-47 tags for <html lang>, hreflang and JSON-LD. */
export const HREFLANG: Record<Locale, string> = {
  mr: "mr-IN",
  en: "en-IN",
};

/** What each language calls itself, for the toggle. Never translated. */
export const LOCALE_LABEL: Record<Locale, string> = {
  mr: "मरा",
  en: "ENG",
};

export const LOCALE_FULL_LABEL: Record<Locale, string> = {
  mr: "मराठी",
  en: "English",
};

export function isLocale(value: string): value is Locale {
  return (LOCALES as readonly string[]).includes(value);
}

/**
 * Locale for a URL path. Marathi is the default and carries no prefix, so
 * anything that is not under /en/ is Marathi.
 */
export function localeFromPath(pathname: string): Locale {
  const seg = pathname.split("/").filter(Boolean)[0];
  return seg && isLocale(seg) ? seg : DEFAULT_LOCALE;
}

/**
 * Build a path in the given locale. `/` and `/thank-you` for Marathi,
 * `/en/` and `/en/thank-you` for English.
 */
export function localePath(path: string, locale: Locale): string {
  const clean = "/" + path.replace(/^\/+|\/+$/g, "");
  const bare = clean === "/" ? "" : clean;
  return locale === DEFAULT_LOCALE ? bare || "/" : `/${locale}${bare}`;
}

/**
 * Strip the locale prefix off a path, so the language toggle can swap locale
 * while staying on the same page.
 */
export function stripLocale(pathname: string): string {
  const parts = pathname.split("/").filter(Boolean);
  if (parts.length && isLocale(parts[0])) parts.shift();
  return "/" + parts.join("/");
}
