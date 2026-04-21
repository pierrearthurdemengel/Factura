import { useEffect, useMemo, useState } from 'react';

// Configuration de theming envoyee par le parent via postMessage
interface EmbedTheme {
  primaryColor: string;
  backgroundColor: string;
  textColor: string;
  borderRadius: number;
  fontFamily: string;
  logoUrl: string;
}

const defaultTheme: EmbedTheme = {
  primaryColor: '#2563eb',
  backgroundColor: '#ffffff',
  textColor: '#111827',
  borderRadius: 8,
  fontFamily: 'system-ui',
  logoUrl: '',
};

/**
 * Wrapper pour le mode iframe embarque (white-label).
 * Ecoute les messages postMessage du parent pour appliquer le theming.
 * Envoie des evenements au parent (navigation, actions).
 */
export default function EmbedWrapper({ children }: Readonly<{ children: React.ReactNode }>) {
  // Determine le mode embed depuis les parametres URL (ne change pas apres init)
  const isEmbed = useMemo(() => new URLSearchParams(globalThis.location.search).get('embed') === '1', []);

  const [theme, setTheme] = useState<EmbedTheme>(() => {
    if (!isEmbed) return defaultTheme;
    const params = new URLSearchParams(globalThis.location.search);
    const urlTheme: Partial<EmbedTheme> = {};
    const pc = params.get('primaryColor');
    if (pc && /^#[0-9a-fA-F]{6}$/.test(pc)) urlTheme.primaryColor = pc;
    const bg = params.get('backgroundColor');
    if (bg && /^#[0-9a-fA-F]{6}$/.test(bg)) urlTheme.backgroundColor = bg;
    const tc = params.get('textColor');
    if (tc && /^#[0-9a-fA-F]{6}$/.test(tc)) urlTheme.textColor = tc;
    return { ...defaultTheme, ...urlTheme };
  });

  useEffect(() => {
    if (!isEmbed) return;

    // Notifier le parent que l'iframe est prete
    globalThis.parent.postMessage({ type: 'mfp:ready' }, '*');

    // Ecouter les messages du parent pour le theming dynamique
    const handleMessage = (event: MessageEvent) => {
      if (event.data?.type === 'mfp:theme') {
        setTheme((prev) => ({ ...prev, ...event.data.theme }));
      }
    };

    globalThis.addEventListener('message', handleMessage);
    return () => globalThis.removeEventListener('message', handleMessage);
  }, [isEmbed]);

  if (!isEmbed) {
    return <>{children}</>;
  }

  // Appliquer les variables CSS de theming
  const style: React.CSSProperties = {
    '--embed-primary': theme.primaryColor,
    '--embed-bg': theme.backgroundColor,
    '--embed-text': theme.textColor,
    '--embed-radius': `${theme.borderRadius}px`,
    '--embed-font': theme.fontFamily,
    fontFamily: theme.fontFamily,
    backgroundColor: theme.backgroundColor,
    color: theme.textColor,
    minHeight: '100vh',
  } as React.CSSProperties;

  return (
    <div className="embed-wrapper" style={style}>
      {children}
    </div>
  );
}

/**
 * Envoie un evenement au parent de l'iframe.
 * Utilise par les composants embarques pour signaler les actions.
 */
export function notifyParent(eventType: string, data: Record<string, unknown> = {}): void {
  if (globalThis.parent !== window) {
    globalThis.parent.postMessage({ type: `mfp:${eventType}`, ...data }, '*');
  }
}
