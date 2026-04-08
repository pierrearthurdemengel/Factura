import { useState, useRef, useEffect, type FormEvent } from 'react';
import api from '../api/factura';
import './AppLayout.css';

// Types pour les messages de l'assistant fiscal
interface Source {
  label: string;
  url?: string;
}

interface Action {
  label: string;
  url?: string;
}

interface AssistantResponse {
  content: string;
  sources: Source[];
  actions: Action[];
}

interface Message {
  id: string;
  role: 'user' | 'assistant';
  content: string;
  sources?: Source[];
  actions?: Action[];
}

// Actions rapides proposees a l'utilisateur pour demarrer une conversation
const quickActions = [
  { label: 'Simuler micro vs reel', message: 'Peux-tu simuler la difference entre micro-entreprise et regime reel pour mon activite ?' },
  { label: 'Estimer ma TVA', message: 'Peux-tu estimer le montant de TVA que je dois declarer ce trimestre ?' },
  { label: 'Verifier mes echeances', message: 'Quelles sont mes prochaines echeances fiscales et sociales ?' },
];

// Formate le contenu d'un message en mettant en gras les montants et pourcentages
function formatContent(content: string): React.ReactNode[] {
  // Decoupe le texte pour mettre en evidence les montants (ex: 1 234,56 EUR, 20%, etc.)
  const parts = content.split(/(\*\*[^*]+\*\*)/g);
  return parts.map((part, i) => {
    if (part.startsWith('**') && part.endsWith('**')) {
      return (
        <strong key={i} style={{ color: 'var(--text-h)' }}>
          {part.slice(2, -2)}
        </strong>
      );
    }
    return <span key={i}>{part}</span>;
  });
}

// Indicateur de saisie en cours (trois points animes)
function TypingIndicator() {
  return (
    <div style={{
      display: 'flex',
      gap: '4px',
      alignItems: 'center',
      padding: '0.75rem 1rem',
    }}>
      {[0, 1, 2].map((i) => (
        <span
          key={i}
          style={{
            width: '8px',
            height: '8px',
            borderRadius: '50%',
            background: 'var(--text)',
            opacity: 0.4,
            animation: `assistant-typing 1.2s ease-in-out ${i * 0.2}s infinite`,
          }}
        />
      ))}
      <style>{`
        @keyframes assistant-typing {
          0%, 60%, 100% { opacity: 0.2; transform: scale(0.8); }
          30% { opacity: 0.8; transform: scale(1); }
        }
      `}</style>
    </div>
  );
}

// Page de l'assistant fiscal : interface de conversation pour les questions fiscales
export default function AssistantPage() {
  const [messages, setMessages] = useState<Message[]>([]);
  const [inputValue, setInputValue] = useState('');
  const [loading, setLoading] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  // Defilement automatique vers le dernier message
  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, loading]);

  // Envoie un message a l'assistant et traite la reponse
  const sendMessage = async (text: string) => {
    if (!text.trim() || loading) return;

    const userMessage: Message = {
      id: crypto.randomUUID(),
      role: 'user',
      content: text.trim(),
    };

    setMessages((prev) => [...prev, userMessage]);
    setInputValue('');
    setLoading(true);

    try {
      const response = await api.post<AssistantResponse>('/assistant/chat', {
        message: text.trim(),
      });

      const assistantMessage: Message = {
        id: crypto.randomUUID(),
        role: 'assistant',
        content: response.data.content,
        sources: response.data.sources,
        actions: response.data.actions,
      };

      setMessages((prev) => [...prev, assistantMessage]);
    } catch {
      const errorMessage: Message = {
        id: crypto.randomUUID(),
        role: 'assistant',
        content: 'Desole, une erreur est survenue. Veuillez reessayer dans quelques instants.',
      };
      setMessages((prev) => [...prev, errorMessage]);
    } finally {
      setLoading(false);
      inputRef.current?.focus();
    }
  };

  // Soumission du formulaire de saisie
  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    sendMessage(inputValue);
  };

  // Gestion du clic sur une action rapide
  const handleQuickAction = (message: string) => {
    sendMessage(message);
  };

  return (
    <div
      className="app-container"
      style={{
        display: 'flex',
        flexDirection: 'column',
        height: 'calc(100vh - 64px)',
        padding: 0,
        maxWidth: '100%',
      }}
    >
      {/* En-tete */}
      <div style={{
        padding: 'clamp(0.75rem, 2vw, 1.25rem) clamp(1rem, 3vw, 2rem)',
        borderBottom: '1px solid var(--border)',
        flexShrink: 0,
      }}>
        <h1 className="app-page-title" style={{ margin: 0, fontSize: 'clamp(1.25rem, 3vw, 1.75rem)' }}>
          Assistant fiscal
        </h1>
        <p style={{ margin: '0.25rem 0 0', color: 'var(--text)', fontSize: '0.85rem' }}>
          Posez vos questions sur la fiscalite, la TVA, les echeances et les regimes fiscaux.
        </p>
      </div>

      {/* Actions rapides — visibles uniquement si la conversation est vide */}
      {messages.length === 0 && (
        <div style={{
          padding: 'clamp(0.75rem, 2vw, 1rem) clamp(1rem, 3vw, 2rem)',
          borderBottom: '1px solid var(--border)',
          display: 'flex',
          gap: '0.5rem',
          flexWrap: 'wrap',
          flexShrink: 0,
        }}>
          {quickActions.map((qa) => (
            <button
              key={qa.label}
              onClick={() => handleQuickAction(qa.message)}
              style={{
                padding: '0.5rem 1rem',
                background: 'var(--social-bg)',
                border: '1px solid var(--border)',
                borderRadius: '2rem',
                color: 'var(--text-h)',
                fontSize: '0.85rem',
                cursor: 'pointer',
                fontWeight: 500,
                transition: 'all 0.2s',
                whiteSpace: 'nowrap',
              }}
              onMouseEnter={(e) => {
                e.currentTarget.style.borderColor = 'var(--accent)';
                e.currentTarget.style.color = 'var(--accent)';
              }}
              onMouseLeave={(e) => {
                e.currentTarget.style.borderColor = 'var(--border)';
                e.currentTarget.style.color = 'var(--text-h)';
              }}
            >
              {qa.label}
            </button>
          ))}
        </div>
      )}

      {/* Zone des messages — occupe tout l'espace disponible */}
      <div style={{
        flex: 1,
        overflowY: 'auto',
        padding: 'clamp(0.75rem, 2vw, 1.5rem) clamp(1rem, 3vw, 2rem)',
        display: 'flex',
        flexDirection: 'column',
        gap: '1rem',
      }}>
        {/* Etat vide : message d'accueil */}
        {messages.length === 0 && !loading && (
          <div style={{
            flex: 1,
            display: 'flex',
            flexDirection: 'column',
            alignItems: 'center',
            justifyContent: 'center',
            textAlign: 'center',
            color: 'var(--text)',
            gap: '0.75rem',
            padding: '2rem 1rem',
          }}>
            <div style={{ fontSize: '2.5rem' }}>
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                <path d="M8 10h.01" />
                <path d="M12 10h.01" />
                <path d="M16 10h.01" />
              </svg>
            </div>
            <p style={{ fontSize: '1.1rem', fontWeight: 600, color: 'var(--text-h)' }}>
              Comment puis-je vous aider ?
            </p>
            <p style={{ fontSize: '0.9rem', maxWidth: 400, lineHeight: 1.5 }}>
              Selectionnez une question rapide ci-dessus ou posez directement votre question sur la fiscalite francaise.
            </p>
          </div>
        )}

        {/* Liste des messages */}
        {messages.map((msg) => (
          <div
            key={msg.id}
            style={{
              display: 'flex',
              justifyContent: msg.role === 'user' ? 'flex-end' : 'flex-start',
              width: '100%',
            }}
          >
            <div
              style={{
                maxWidth: 'min(85%, 600px)',
                padding: '0.75rem 1rem',
                borderRadius: msg.role === 'user' ? '1rem 1rem 0.25rem 1rem' : '1rem 1rem 1rem 0.25rem',
                background: msg.role === 'user' ? 'var(--accent)' : 'var(--social-bg)',
                color: msg.role === 'user' ? '#fff' : 'var(--text)',
                fontSize: '0.9rem',
                lineHeight: 1.6,
                wordBreak: 'break-word',
              }}
            >
              {/* Contenu du message avec mise en forme */}
              <div>{formatContent(msg.content)}</div>

              {/* Sources juridiques affichees sous forme de badges cliquables */}
              {msg.sources && msg.sources.length > 0 && (
                <div style={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: '0.4rem',
                  marginTop: '0.75rem',
                  paddingTop: '0.5rem',
                  borderTop: '1px solid var(--border)',
                }}>
                  {msg.sources.map((source, i) => (
                    <a
                      key={i}
                      href={source.url || '#'}
                      target={source.url ? '_blank' : undefined}
                      rel={source.url ? 'noopener noreferrer' : undefined}
                      style={{
                        display: 'inline-block',
                        padding: '0.2rem 0.6rem',
                        background: 'rgba(59, 130, 246, 0.1)',
                        color: 'var(--accent)',
                        borderRadius: '1rem',
                        fontSize: '0.75rem',
                        fontWeight: 600,
                        textDecoration: 'none',
                        cursor: source.url ? 'pointer' : 'default',
                        transition: 'background 0.2s',
                        border: '1px solid rgba(59, 130, 246, 0.2)',
                      }}
                      onMouseEnter={(e) => {
                        if (source.url) e.currentTarget.style.background = 'rgba(59, 130, 246, 0.2)';
                      }}
                      onMouseLeave={(e) => {
                        e.currentTarget.style.background = 'rgba(59, 130, 246, 0.1)';
                      }}
                    >
                      {source.label}
                    </a>
                  ))}
                </div>
              )}

              {/* Boutons d'action proposes par l'assistant */}
              {msg.actions && msg.actions.length > 0 && (
                <div style={{
                  display: 'flex',
                  flexWrap: 'wrap',
                  gap: '0.5rem',
                  marginTop: '0.75rem',
                }}>
                  {msg.actions.map((action, i) => (
                    <a
                      key={i}
                      href={action.url || '#'}
                      style={{
                        display: 'inline-flex',
                        alignItems: 'center',
                        gap: '0.3rem',
                        padding: '0.4rem 0.8rem',
                        background: 'var(--accent)',
                        color: '#fff',
                        borderRadius: '6px',
                        fontSize: '0.8rem',
                        fontWeight: 600,
                        textDecoration: 'none',
                        cursor: 'pointer',
                        transition: 'opacity 0.2s',
                      }}
                      onMouseEnter={(e) => { e.currentTarget.style.opacity = '0.85'; }}
                      onMouseLeave={(e) => { e.currentTarget.style.opacity = '1'; }}
                    >
                      {action.label}
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                        <polyline points="9 18 15 12 9 6" />
                      </svg>
                    </a>
                  ))}
                </div>
              )}
            </div>
          </div>
        ))}

        {/* Indicateur de chargement */}
        {loading && (
          <div style={{ display: 'flex', justifyContent: 'flex-start' }}>
            <div style={{
              padding: '0.5rem',
              borderRadius: '1rem 1rem 1rem 0.25rem',
              background: 'var(--social-bg)',
            }}>
              <TypingIndicator />
            </div>
          </div>
        )}

        {/* Ancre pour le defilement automatique */}
        <div ref={messagesEndRef} />
      </div>

      {/* Barre de saisie fixee en bas */}
      <form
        onSubmit={handleSubmit}
        style={{
          padding: 'clamp(0.5rem, 1.5vw, 0.75rem) clamp(1rem, 3vw, 2rem)',
          borderTop: '1px solid var(--border)',
          background: 'var(--bg)',
          display: 'flex',
          gap: '0.5rem',
          alignItems: 'center',
          flexShrink: 0,
        }}
      >
        <input
          ref={inputRef}
          type="text"
          className="app-input"
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          placeholder="Posez votre question fiscale..."
          disabled={loading}
          style={{
            flex: 1,
            borderRadius: '2rem',
            padding: '0.65rem 1.25rem',
          }}
        />
        <button
          type="submit"
          className="app-btn-primary"
          disabled={loading || !inputValue.trim()}
          style={{
            borderRadius: '50%',
            width: '42px',
            height: '42px',
            padding: 0,
            flexShrink: 0,
            display: 'flex',
            alignItems: 'center',
            justifyContent: 'center',
          }}
          title="Envoyer"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
            <line x1="22" y1="2" x2="11" y2="13" />
            <polygon points="22 2 15 22 11 13 2 9 22 2" />
          </svg>
        </button>
      </form>
    </div>
  );
}
