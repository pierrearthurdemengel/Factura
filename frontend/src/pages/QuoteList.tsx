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
        <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginTop: '0.75rem' }} />
      ))}
    </div>
  );

  return (
    <div className="app-container">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.5rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Devis</h1>
        <Link to="/quotes/new" className="app-btn-primary" style={{ textDecoration: 'none' }}>
          + Nouveau devis
        </Link>
      </div>

      {/* Filtres par statut */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', flexWrap: 'wrap' }}>
        <button
          onClick={() => setFilter('')}
          style={{
            padding: '4px 12px', borderRadius: '1rem', border: 'none', cursor: 'pointer',
            background: !filter ? 'var(--accent)' : 'var(--surface)', color: !filter ? '#fff' : 'var(--text)',
            fontSize: '0.85rem', fontWeight: 500,
          }}
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
              style={{
                padding: '4px 12px', borderRadius: '1rem', border: 'none', cursor: 'pointer',
                background: filter === key ? cfg.bg : 'var(--surface)', color: filter === key ? cfg.color : 'var(--text)',
                fontSize: '0.85rem', fontWeight: 500,
              }}
            >
              {cfg.label} ({count})
            </button>
          );
        })}
      </div>

      {filtered.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
          <div style={{ fontSize: '2rem', marginBottom: '1rem' }}>📋</div>
          <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>Aucun devis</p>
          <p style={{ fontSize: '0.9rem' }}>Creez votre premier devis pour commencer.</p>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
          {filtered.map((quote) => {
            const cfg = STATUS_CONFIG[quote.status] || STATUS_CONFIG.DRAFT;
            return (
              <Link
                key={quote.id}
                to={`/quotes/${quote.id}`}
                className="app-card"
                style={{ textDecoration: 'none', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem' }}
              >
                <div style={{ flex: 1, minWidth: 150 }}>
                  <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.95rem' }}>
                    {quote.number || 'Brouillon'}
                  </div>
                  <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.15rem' }}>
                    {quote.buyer?.name}
                  </div>
                </div>
                <div style={{ fontSize: '0.85rem', color: 'var(--text)' }}>
                  {new Date(quote.issueDate).toLocaleDateString('fr-FR')}
                </div>
                <div style={{
                  padding: '3px 10px', borderRadius: '1rem', fontSize: '0.8rem', fontWeight: 600,
                  background: cfg.bg, color: cfg.color,
                }}>
                  {cfg.label}
                </div>
                <div style={{ fontWeight: 600, color: 'var(--text-h)', minWidth: 80, textAlign: 'right' }}>
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
