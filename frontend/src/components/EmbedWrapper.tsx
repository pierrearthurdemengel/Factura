import { useEffect, useState } from 'react';

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
export default function EmbedWrapper({ children }: { children: React.ReactNode }) {
  const [theme, setTheme] = useState<EmbedTheme>(defaultTheme);
  const [isEmbed, setIsEmbed] = useState(false);

  useEffect(() => {
    // Detecter si on est en mode embed via le parametre URL
    const params = new URLSearchParams(window.location.search);
    const embedMode = params.get('embed') === '1';
    setIsEmbed(embedMode);

    if (!embedMode) return;

    // Appliquer le theming depuis les parametres URL
    const urlTheme: Partial<EmbedTheme> = {};
    const pc = params.get('primaryColor');
    if (pc && /^#[0-9a-fA-F]{6}$/.test(pc)) urlTheme.primaryColor = pc;
    const bg = params.get('backgroundColor');
    if (bg && /^#[0-9a-fA-F]{6}$/.test(bg)) urlTheme.backgroundColor = bg;
    const tc = params.get('textColor');
    if (tc && /^#[0-9a-fA-F]{6}$/.test(tc)) urlTheme.textColor = tc;

    setTheme({ ...defaultTheme, ...urlTheme });

    // Ecouter les messages du parent pour le theming dynamique
    const handleMessage = (event: MessageEvent) => {
      if (event.data?.type === 'mfp:theme') {
        setTheme((prev) => ({ ...prev, ...event.data.theme }));
      }
    };

    window.addEventListener('message', handleMessage);

    // Notifier le parent que l'iframe est prete
    window.parent.postMessage({ type: 'mfp:ready' }, '*');

    return () => window.removeEventListener('message', handleMessage);
  }, []);

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
  if (window.parent !== window) {
    window.parent.postMessage({ type: `mfp:${eventType}`, ...data }, '*');
  }
}
