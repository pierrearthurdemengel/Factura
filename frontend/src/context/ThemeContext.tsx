import React, { createContext, useContext, useEffect, useState } from 'react';

type Theme = 'light' | 'dark' | 'system';

interface ThemeContextType {
  theme: Theme;
  setTheme: (theme: Theme) => void;
  isDark: boolean;
}

const ThemeContext = createContext<ThemeContextType | undefined>(undefined);

export function ThemeProvider({ children }: Readonly<{ children: React.ReactNode }>) {
  const [theme, setTheme] = useState<Theme>(() => {
    return (localStorage.getItem('factura-ui-theme') as Theme) || 'system';
  });
  const [isDark, setIsDark] = useState(false);

  useEffect(() => {
    const root = document.documentElement;
    const mediaQuery = globalThis.matchMedia('(prefers-color-scheme: dark)');

    const applyTheme = () => {
      // Add the transition class safely
      document.body.classList.add('theme-switching');

      let resolvedDark = theme === 'dark';
      if (theme === 'system') {
        resolvedDark = mediaQuery.matches;
      }

      if (resolvedDark) {
        root.setAttribute('data-theme', 'dark');
      } else {
        root.removeAttribute('data-theme');
      }
      setIsDark(resolvedDark);

      // Remove the transition class after animation to restore 120fps interactions
      setTimeout(() => document.body.classList.remove('theme-switching'), 400);
    };

    applyTheme();

    if (theme === 'system') {
      mediaQuery.addEventListener('change', applyTheme);
      return () => mediaQuery.removeEventListener('change', applyTheme);
    }
  }, [theme]);

  // Sync to local storage
  useEffect(() => {
    localStorage.setItem('factura-ui-theme', theme);
  }, [theme]);

  return (
    <ThemeContext.Provider value={{ theme, setTheme, isDark }}>
      {children}
    </ThemeContext.Provider>
  );
}

export function useTheme() {
  const context = useContext(ThemeContext);
  if (!context) throw new Error('useTheme must be inside a ThemeProvider');
  return context;
}
