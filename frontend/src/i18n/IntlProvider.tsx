import { createContext, useContext, useState, useCallback, type ReactNode } from 'react';
import { IntlProvider as ReactIntlProvider } from 'react-intl';
import { allMessages, fr, SUPPORTED_LOCALES } from './messages';

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
export function AppIntlProvider({ children }: { children: ReactNode }) {
  const [locale, setLocaleState] = useState<string>(() => {
    const saved = localStorage.getItem('factura-locale');
    return saved || detectBrowserLocale();
  });

  const setLocale = useCallback((newLocale: string) => {
    setLocaleState(newLocale);
    localStorage.setItem('factura-locale', newLocale);
  }, []);

  // Fallback sur le francais si des cles manquent
  const messages = { ...fr, ...(allMessages[locale] || {}) };

  return (
    <LocaleContext.Provider value={{ locale, setLocale, supportedLocales: SUPPORTED_LOCALES }}>
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
