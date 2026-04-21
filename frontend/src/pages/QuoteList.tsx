import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { useIntl } from 'react-intl';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
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

// Couleurs des statuts et cles i18n
const STATUS_CONFIG: Record<string, { intlId: string; defaultMessage: string; color: string; bg: string }> = {
  DRAFT: { intlId: 'invoice.draft', defaultMessage: 'Brouillon', color: '#6b7280', bg: 'rgba(107,114,128,0.1)' },
  SENT: { intlId: 'invoice.sent', defaultMessage: 'Envoyee', color: 'var(--accent)', bg: 'var(--accent-bg)' },
  ACCEPTED: { intlId: 'invoice.acknowledged', defaultMessage: 'Acceptee', color: 'var(--success)', bg: 'var(--success-bg)' },
  REJECTED: { intlId: 'quote.rejected', defaultMessage: 'Refuse', color: 'var(--danger)', bg: 'var(--danger-bg)' },
  EXPIRED: { intlId: 'quote.expired', defaultMessage: 'Expire', color: 'var(--warning)', bg: 'var(--warning-bg)' },
  CONVERTED: { intlId: 'quote.converted', defaultMessage: 'Converti', color: '#8b5cf6', bg: 'rgba(139,92,246,0.1)' },
};

export default function QuoteList() {
  const intl = useIntl();
  const [quotes, setQuotes] = useState<Quote[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState('');
  const { error: toastError } = useToast();

  useEffect(() => {
    // SWR: show cached data instantly
    const cached = getCached<{ 'hydra:member': Quote[] }>('/quotes');
    if (cached) {
      setQuotes(cached['hydra:member'] || []);
      setLoading(false);
    }

    api.get('/quotes')
      .then((res) => {
        setQuotes(res.data['hydra:member'] || []);
        setCache('/quotes', res.data);
      })
      .catch(() => toastError(intl.formatMessage({ id: 'quote.loadError', defaultMessage: 'Impossible de charger la liste des devis.' })))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
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
        <h1 className="app-page-title">{intl.formatMessage({ id: 'nav.quotes', defaultMessage: 'Devis' })}</h1>
        <Link to="/quotes/new" className="app-btn-primary">
          + {intl.formatMessage({ id: 'quote.new', defaultMessage: 'Nouveau devis' })}
        </Link>
      </div>

      {/* Filtres par statut */}
      <div className="app-pills">
        <button
          onClick={() => setFilter('')}
          className={`app-pill${!filter ? ' app-pill--active' : ''}`}
        >
          {intl.formatMessage({ id: 'common.all', defaultMessage: 'Tous' })} ({quotes.length})
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
              {intl.formatMessage({ id: cfg.intlId, defaultMessage: cfg.defaultMessage })} ({count})
            </button>
          );
        })}
      </div>

      {filtered.length === 0 ? (
        <div className="app-empty">
          <div className="app-empty-icon">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" />
              <polyline points="14 2 14 8 20 8" />
              <line x1="16" y1="13" x2="8" y2="13" />
              <line x1="16" y1="17" x2="8" y2="17" />
            </svg>
          </div>
          <p className="app-empty-title">{intl.formatMessage({ id: 'quote.empty.title', defaultMessage: 'Aucun devis' })}</p>
          <p className="app-empty-desc">{intl.formatMessage({ id: 'quote.empty.description', defaultMessage: 'Creez votre premier devis pour commencer.' })}</p>
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
                    {quote.number || intl.formatMessage({ id: 'invoice.draft', defaultMessage: 'Brouillon' })}
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
                  {intl.formatMessage({ id: cfg.intlId, defaultMessage: cfg.defaultMessage })}
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
