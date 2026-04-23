import { useState, useRef, useEffect, type FormEvent, type ReactNode } from 'react';
import api from '../api/factura';
import './AppLayout.css';
import './AssistantPage.css';

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
function formatContent(content: string): ReactNode[] {
  // Decoupe le texte pour mettre en evidence les montants (ex: 1 234,56 EUR, 20%, etc.)
  const parts = content.split(/(\*\*[^*]+\*\*)/g);
  return parts.map((part, i) => {
    const key = `${part.slice(0, 20)}-${i}`;
    if (part.startsWith('**') && part.endsWith('**')) {
      return (
        <strong key={key} className="assistant-bold">
          {part.slice(2, -2)}
        </strong>
      );
    }
    return <span key={key}>{part}</span>;
  });
}

// Indicateur de saisie en cours (trois points animes)
function TypingIndicator() {
  return (
    <div className="assistant-typing">
      <span className="assistant-typing-dot" />
      <span className="assistant-typing-dot" />
      <span className="assistant-typing-dot" />
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
    <div className="app-container assistant-layout">
      {/* En-tete */}
      <div className="assistant-header">
        <h1 className="app-page-title">
          Assistant fiscal
        </h1>
        <p className="app-desc" style={{ margin: '0.25rem 0 0' }}>
          Posez vos questions sur la fiscalite, la TVA, les echeances et les regimes fiscaux.
        </p>
      </div>

      {/* Actions rapides — visibles uniquement si la conversation est vide */}
      {messages.length === 0 && (
        <div className="assistant-quick-actions">
          {quickActions.map((qa) => (
            <button
              key={qa.label}
              onClick={() => handleQuickAction(qa.message)}
              className="assistant-quick-btn"
            >
              {qa.label}
            </button>
          ))}
        </div>
      )}

      {/* Zone des messages — occupe tout l'espace disponible */}
      <div className="assistant-messages">
        {/* Etat vide : message d'accueil */}
        {messages.length === 0 && !loading && (
          <div className="assistant-empty">
            <div className="assistant-empty-icon">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="var(--accent)" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />
                <path d="M8 10h.01" />
                <path d="M12 10h.01" />
                <path d="M16 10h.01" />
              </svg>
            </div>
            <p className="assistant-empty-title">
              Comment puis-je vous aider ?
            </p>
            <p className="assistant-empty-desc">
              Selectionnez une question rapide ci-dessus ou posez directement votre question sur la fiscalite francaise.
            </p>
          </div>
        )}

        {/* Liste des messages */}
        {messages.map((msg) => (
          <div
            key={msg.id}
            className={`assistant-msg-row ${msg.role === 'user' ? 'assistant-msg-row--user' : 'assistant-msg-row--assistant'}`}
          >
            <div className={`assistant-bubble ${msg.role === 'user' ? 'assistant-bubble--user' : 'assistant-bubble--assistant'}`}>
              {/* Contenu du message avec mise en forme */}
              <div>{formatContent(msg.content)}</div>

              {/* Sources juridiques affichees sous forme de badges cliquables */}
              {msg.sources && msg.sources.length > 0 && (
                <div className="assistant-sources">
                  {msg.sources.map((source) => (
                    <a
                      key={source.label}
                      href={source.url || '#'}
                      target={source.url ? '_blank' : undefined}
                      rel={source.url ? 'noopener noreferrer' : undefined}
                      className={`assistant-source-link ${!source.url ? 'assistant-source-link--static' : ''}`}
                    >
                      {source.label}
                    </a>
                  ))}
                </div>
              )}

              {/* Boutons d'action proposes par l'assistant */}
              {msg.actions && msg.actions.length > 0 && (
                <div className="assistant-actions">
                  {msg.actions.map((action) => (
                    <a
                      key={action.label}
                      href={action.url || '#'}
                      className="assistant-action-link"
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
          <div className="assistant-loading-row">
            <div className="assistant-loading-bubble">
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
        className="assistant-input-bar"
      >
        <input
          ref={inputRef}
          type="text"
          className="app-input"
          value={inputValue}
          onChange={(e) => setInputValue(e.target.value)}
          placeholder="Posez votre question fiscale..."
          disabled={loading}
        />
        <button
          type="submit"
          className="app-btn-primary assistant-send-btn"
          disabled={loading || !inputValue.trim()}
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
