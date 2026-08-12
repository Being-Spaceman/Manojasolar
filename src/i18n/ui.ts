import en from "./en.json";
import mr from "./mr.json";
import { DEFAULT_LOCALE, type Locale } from "./config";

const DICTS = { en, mr } as const;

/** The English file is the shape of record — every key must exist there. */
export type Dict = typeof en;

type Primitive = string | number | boolean;

function lookup(dict: unknown, path: string): unknown {
  return path
    .split(".")
    .reduce<unknown>(
      (node, key) =>
        node && typeof node === "object"
          ? (node as Record<string, unknown>)[key]
          : undefined,
      dict,
    );
}

/**
 * Translator for a locale.
 *
 *   const t = useTranslations(locale);
 *   t("hero.headline")
 *   t("footer.copyright", { year: 2026 })
 *
 * Marathi copy is still being finalised, so a missing `mr` key falls back to
 * English rather than rendering the raw key at a customer. In dev that fallback
 * warns, so gaps surface during the build instead of on the phone.
 */
export function useTranslations(locale: Locale) {
  const dict = DICTS[locale] ?? DICTS[DEFAULT_LOCALE];

  return function t(key: string, vars?: Record<string, Primitive>): string {
    let value = lookup(dict, key);

    if (typeof value !== "string") {
      const fallback = lookup(DICTS.en, key);
      if (typeof fallback === "string") {
        if (import.meta.env.DEV && locale !== "en") {
          console.warn(`[i18n] missing ${locale}: ${key} — falling back to en`);
        }
        value = fallback;
      } else {
        if (import.meta.env.DEV) {
          console.warn(`[i18n] unknown key: ${key}`);
        }
        return key;
      }
    }

    if (!vars) return value;
    return value.replace(/\{(\w+)\}/g, (m, name) =>
      name in vars ? String(vars[name]) : m,
    );
  };
}

/**
 * Read a list (FAQ entries, process steps, bullet lists) with the same
 * fallback behaviour as `t`.
 */
export function useList(locale: Locale) {
  const dict = DICTS[locale] ?? DICTS[DEFAULT_LOCALE];

  return function list<T = Record<string, string>>(key: string): T[] {
    const value = lookup(dict, key);
    if (Array.isArray(value)) return value as T[];
    const fallback = lookup(DICTS.en, key);
    if (Array.isArray(fallback)) {
      if (import.meta.env.DEV && locale !== "en") {
        console.warn(`[i18n] missing list ${locale}: ${key} — falling back to en`);
      }
      return fallback as T[];
    }
    if (import.meta.env.DEV) console.warn(`[i18n] unknown list key: ${key}`);
    return [];
  };
}
