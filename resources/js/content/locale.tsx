import { createContext, useContext, useEffect, useState, type ReactNode } from 'react';
export type Locale = 'fr' | 'en';
const Context = createContext<{ locale: Locale; setLocale: (locale: Locale) => void }>({ locale: 'fr', setLocale: () => {} });
export function LocaleProvider({ children }: { children: ReactNode }) {
    const [locale, setLocale] = useState<Locale>(() => { try { return localStorage.getItem('pa-interface-locale') === 'en' ? 'en' : 'fr'; } catch { return 'fr'; } });
    useEffect(() => { document.documentElement.lang = locale; try { localStorage.setItem('pa-interface-locale', locale); } catch { /* Storage can be unavailable in private mode. */ } }, [locale]);
    return <Context.Provider value={{ locale, setLocale }}>{children}</Context.Provider>;
}
export const useLocale = () => useContext(Context);
export function LanguageSelect() { const { locale, setLocale } = useLocale(); return <div className="ds-language" aria-label="Langue / Language">{(['fr', 'en'] as const).map(value => <button type="button" key={value} lang={value} aria-label={value === 'fr' ? 'Français' : 'English'} aria-pressed={locale === value} onClick={() => setLocale(value)}>{value.toUpperCase()}</button>)}</div>; }
