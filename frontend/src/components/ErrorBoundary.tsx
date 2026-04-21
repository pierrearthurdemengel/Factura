import { Component } from 'react';
import type { ReactNode, ErrorInfo } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error: Error | null;
}

export default class ErrorBoundary extends Component<Props, State> {
  constructor(props: Props) {
    super(props);
    this.state = { hasError: false, error: null };
  }

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('ErrorBoundary caught:', error, info.componentStack);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div style={{
          display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center',
          minHeight: '60vh', padding: '2rem', textAlign: 'center',
        }}>
          <h1 style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)', marginBottom: '1rem' }}>
            Une erreur est survenue
          </h1>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', maxWidth: 500 }}>
            L'application a rencontre un probleme inattendu. Veuillez recharger la page.
          </p>
          {this.state.error && (
            <pre style={{
              background: 'var(--social-bg)', padding: '1rem', borderRadius: '8px',
              fontSize: '0.8rem', color: 'var(--text)', maxWidth: 600, overflow: 'auto',
              marginBottom: '1.5rem', textAlign: 'left',
            }}>
              {this.state.error.message}
            </pre>
          )}
          <button
            onClick={() => globalThis.location.reload()}
            style={{
              padding: '0.75rem 1.5rem', background: 'var(--accent)', color: '#fff',
              border: 'none', borderRadius: '8px', cursor: 'pointer', fontWeight: 600,
            }}
          >
            Recharger la page
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
