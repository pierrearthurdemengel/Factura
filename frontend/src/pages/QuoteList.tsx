import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/factura';
import './AppLayout.css';

// Type simplifie pour les devis
interface Quote {
  id: string;
  number: string | null;
  status: string;
  issueDate: string;
  validUntil: string | null;
  totalIncludingTax: string;
  buyer: { name: string };
}

// Libelles et couleurs des statuts
const STATUS_CONFIG: Record<string, { label: string; color: string; bg: string }> = {
  DRAFT: { label: 'Brouillon', color: '#6b7280', bg: 'rgba(107,114,128,0.1)' },
  SENT: { label: 'Envoye', color: '#2563eb', bg: 'rgba(37,99,235,0.1)' },
  ACCEPTED: { label: 'Accepte', color: '#22c55e', bg: 'rgba(34,197,94,0.1)' },
  REFUSED: { label: 'Refuse', color: '#ef4444', bg: 'rgba(239,68,68,0.1)' },
  EXPIRED: { label: 'Expire', color: '#f59e0b', bg: 'rgba(245,158,11,0.1)' },
  INVOICED: { label: 'Facture', color: '#8b5cf6', bg: 'rgba(139,92,246,0.1)' },
};

export default function QuoteList() {
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');

  useEffect(() => {
    api.get('/quotes')
      .then((res) => setQuotes(res.data['hydra:member'] || []))
      .catch(() => {/* Pas de devis disponibles */})
      .finally(() => setLoading(false));
  }, []);

  const filtered = filter
    ? quotes.filter((q) => q.status === filter)
    : quotes;

  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      {[1, 2, 3].map((i) => (
        <div key={i} className="app-skeleton app-skeleton-table-row" />
      ))}
    </div>
  );

  return (
    <div className="app-container">
      <div className="app-page-header">
        <h1 className="app-page-title">Devis</h1>
        <Link to="/quotes/new" className="app-btn-primary">
          + Nouveau devis
        </Link>
      </div>

      {/* Filtres par statut */}
      <div className="app-pills">
        <button
          onClick={() => setFilter('')}
          className={`app-pill${!filter ? ' app-pill--active' : ''}`}
        >
          Tous ({quotes.length})
        </button>
        {Object.entries(STATUS_CONFIG).map(([key, cfg]) => {
          const count = quotes.filter((q) => q.status === key).length;
          if (count === 0) return null;
          return (
            <button
              key={key}
              onClick={() => setFilter(key)}
              className={`app-pill${filter === key ? ' app-pill--active' : ''}`}
              style={filter === key ? { background: cfg.bg, color: cfg.color } : undefined}
            >
              {cfg.label} ({count})
            </button>
          );
        })}
      </div>

      {filtered.length === 0 ? (
        <div className="app-empty">
          <div className="app-empty-icon">📋</div>
          <p className="app-empty-title">Aucun devis</p>
          <p className="app-empty-desc">Creez votre premier devis pour commencer.</p>
        </div>
      ) : (
        <div className="app-list">
          {filtered.map((quote) => {
            const cfg = STATUS_CONFIG[quote.status] || STATUS_CONFIG.DRAFT;
            return (
              <Link
                key={quote.id}
                to={`/quotes/${quote.id}`}
                className="app-list-item"
              >
                <div className="app-list-item-info">
                  <div className="app-list-item-title">
                    {quote.number || 'Brouillon'}
                  </div>
                  <div className="app-list-item-sub">
                    {quote.buyer?.name}
                  </div>
                </div>
                <div className="app-list-item-sub">
                  {new Date(quote.issueDate).toLocaleDateString('fr-FR')}
                </div>
                <span
                  className="app-status-pill"
                  style={{ background: cfg.bg, color: cfg.color }}
                >
                  {cfg.label}
                </span>
                <div className="app-list-item-value">
                  {parseFloat(quote.totalIncludingTax).toLocaleString('fr-FR', { style: 'currency', currency: 'EUR' })}
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
