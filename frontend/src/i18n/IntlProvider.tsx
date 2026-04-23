import { createContext, useContext, useState, useCallback, useMemo, useEffect, useRef, type ReactNode } from 'react';
import { IntlProvider as ReactIntlProvider } from 'react-intl';
import { fr, loadLocaleMessages, SUPPORTED_LOCALES } from './messages';

// Contexte pour le changement de langue
interface LocaleContextType {
  locale: string;
  setLocale: (locale: string) => void;
  supportedLocales: typeof SUPPORTED_LOCALES;
}

const LocaleContext = createContext<LocaleContextType>({
  locale: 'fr',
  setLocale: () => {},
  supportedLocales: SUPPORTED_LOCALES,
});

// Hook pour acceder au contexte de localisation
export function useLocale() {
  return useContext(LocaleContext);
}

// Detecte la langue preferee du navigateur
function detectBrowserLocale(): (typeof SUPPORTED_LOCALES)[number]["code"] {
  const browserLang = navigator.language.split("-")[0];
  const supported = SUPPORTED_LOCALES.map(l => l.code);
  return (supported as readonly string[]).includes(browserLang) ? (browserLang as (typeof SUPPORTED_LOCALES)[number]["code"]) : "fr";
}

// Fournisseur d'internationalisation pour l'application
export function AppIntlProvider({ children }: Readonly<{ children: ReactNode }>) {
  const [locale, setLocaleState] = useState<string>(() => {
    const saved = localStorage.getItem('factura-locale');
    return saved || detectBrowserLocale();
  });

  const [localeMessages, setLocaleMessages] = useState<Record<string, string>>(fr);
  const prevLocaleRef = useRef(locale);

  // Charger dynamiquement la locale si != fr
  useEffect(() => {
    // Ne rien faire si la locale n'a pas change
    if (prevLocaleRef.current === locale) return;
    prevLocaleRef.current = locale;

    if (locale === 'fr') {
      setLocaleMessages(fr); // eslint-disable-line react-hooks/set-state-in-effect
      return;
    }
    loadLocaleMessages(locale).then(setLocaleMessages);
  }, [locale]);

  const setLocale = useCallback((newLocale: string) => {
    setLocaleState(newLocale);
    localStorage.setItem('factura-locale', newLocale);
  }, []);

  // Fallback sur le francais si des cles manquent
  const messages = useMemo(() => ({ ...fr, ...localeMessages }), [localeMessages]);

  const localeContextValue = useMemo(() => ({ locale, setLocale, supportedLocales: SUPPORTED_LOCALES }), [locale, setLocale]);

  return (
    <LocaleContext.Provider value={localeContextValue}>
      <ReactIntlProvider
        locale={locale}
        messages={messages}
        defaultLocale="fr"
        onError={() => {/* Ignorer les erreurs de messages manquants */}}
      >
        {children}
      </ReactIntlProvider>
    </LocaleContext.Provider>
  );
}
