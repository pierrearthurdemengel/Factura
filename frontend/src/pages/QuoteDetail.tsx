import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { getQuote, sendQuote, convertQuoteToInvoice, type Quote } from '../api/factura';
import { useToast } from '../context/ToastContext';
import './AppLayout.css';

// Configuration des statuts
const STATUS_CONFIG: Record<string, { label: string; color: string; bg: string }> = {
  DRAFT: { label: 'Brouillon', color: '#6b7280', bg: 'rgba(107,114,128,0.1)' },
  SENT: { label: 'Envoye', color: '#2563eb', bg: 'rgba(37,99,235,0.1)' },
  ACCEPTED: { label: 'Accepte', color: '#22c55e', bg: 'rgba(34,197,94,0.1)' },
  REFUSED: { label: 'Refuse', color: '#ef4444', bg: 'rgba(239,68,68,0.1)' },
  EXPIRED: { label: 'Expire', color: '#f59e0b', bg: 'rgba(245,158,11,0.1)' },
  INVOICED: { label: 'Facture', color: '#8b5cf6', bg: 'rgba(139,92,246,0.1)' },
};

export default function QuoteDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { success, error } = useToast();
  const [quote, setQuote] = useState<Quote | null>(null);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  const load = () => {
    if (!id) return;
    setLoading(true);
    getQuote(id)
      .then((res) => setQuote(res.data))
      .catch(() => navigate('/quotes'))
      .finally(() => setLoading(false));
  };

  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { load(); }, [id]);

  const handleSend = async () => {
    if (!id) return;
    setActionLoading(true);
    try {
      await sendQuote(id);
      success('Devis envoye au client.');
      load();
    } catch {
      error("Erreur lors de l'envoi du devis.");
    } finally {
      setActionLoading(false);
    }
  };

  const handleConvert = async () => {
    if (!id) return;
    setActionLoading(true);
    try {
      const res = await convertQuoteToInvoice(id);
      success('Facture creee a partir du devis.');
      navigate(`/invoices/${res.data.id}`);
    } catch {
      error('Erreur lors de la conversion en facture.');
    } finally {
      setActionLoading(false);
    }
  };

  if (loading || !quote) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      <div className="app-grid">
        <div className="app-skeleton app-skeleton-card" />
        <div className="app-skeleton app-skeleton-card" />
      </div>
      <div className="app-skeleton app-skeleton-card" style={{ height: '80px' }} />
    </div>
  );

  const cfg = STATUS_CONFIG[quote.status] || STATUS_CONFIG.DRAFT;

  return (
    <div className="app-container">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.5rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Devis {quote.number || 'Brouillon'}</h1>
        <span style={{ padding: '4px 14px', borderRadius: '1rem', fontSize: '0.85rem', fontWeight: 600, background: cfg.bg, color: cfg.color }}>
          {cfg.label}
        </span>
      </div>

      {/* Badge "Issu du devis" si le devis a ete converti */}
      {quote.invoiceId && (
        <div style={{ marginBottom: '1.5rem', padding: '0.75rem', background: 'rgba(139,92,246,0.1)', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <span style={{ fontSize: '0.9rem', color: '#8b5cf6', fontWeight: 600 }}>
            Facture generee
          </span>
          <Link to={`/invoices/${quote.invoiceId}`} style={{ color: '#8b5cf6', textDecoration: 'underline', fontSize: '0.9rem' }}>
            Voir la facture
          </Link>
        </div>
      )}

      <div className="app-grid">
        <div className="app-card">
          <h3 className="app-card-title">Vendeur</h3>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{quote.seller?.name}</p>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>SIREN : {quote.seller?.siren}</p>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{quote.seller?.addressLine1}</p>
          <p style={{ margin: 0, color: 'var(--text)' }}>{quote.seller?.postalCode} {quote.seller?.city}</p>
        </div>
        <div className="app-card">
          <h3 className="app-card-title">Client</h3>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{quote.buyer?.name}</p>
          {quote.buyer?.siren && <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>SIREN : {quote.buyer.siren}</p>}
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{quote.buyer?.addressLine1}</p>
          <p style={{ margin: 0, color: 'var(--text)' }}>{quote.buyer?.postalCode} {quote.buyer?.city}</p>
        </div>
      </div>

      <div className="app-card" style={{ marginBottom: '1.5rem' }}>
        <p style={{ margin: '0 0 0.5rem', color: 'var(--text-h)' }}>Date d'emission : <strong>{new Date(quote.issueDate).toLocaleDateString('fr-FR')}</strong></p>
        {quote.validUntil && (
          <p style={{ margin: 0, color: 'var(--text-h)' }}>Valide jusqu'au : <strong>{new Date(quote.validUntil).toLocaleDateString('fr-FR')}</strong></p>
        )}
      </div>

      <h2 className="app-section-title">Lignes</h2>
      <div className="app-table-wrapper">
        <table className="app-table">
          <thead>
            <tr>
              <th>Description</th>
              <th style={{ textAlign: 'right' }}>Quantite</th>
              <th style={{ textAlign: 'center' }}>Unite</th>
              <th style={{ textAlign: 'right' }}>Prix HT</th>
              <th style={{ textAlign: 'right' }}>TVA</th>
              <th style={{ textAlign: 'right' }}>Total HT</th>
            </tr>
          </thead>
          <tbody>
            {quote.lines?.map((line) => (
              <tr key={line.id}>
                <td>{line.description}</td>
                <td style={{ textAlign: 'right' }}>{line.quantity}</td>
                <td style={{ textAlign: 'center' }}>{line.unit}</td>
                <td style={{ textAlign: 'right' }}>{line.unitPriceExcludingTax} EUR</td>
                <td style={{ textAlign: 'right' }}>{line.vatRate}%</td>
                <td style={{ textAlign: 'right' }}>{line.lineAmount} EUR</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div style={{ marginTop: '1.5rem', textAlign: 'right', color: 'var(--text)' }}>
        <p style={{ marginBottom: '0.5rem' }}>Total HT : <strong style={{ color: 'var(--text-h)' }}>{quote.totalExcludingTax} EUR</strong></p>
        <p style={{ marginBottom: '0.5rem' }}>Total TVA : <strong style={{ color: 'var(--text-h)' }}>{quote.totalTax} EUR</strong></p>
        <p style={{ fontSize: '1.15rem' }}>Total TTC : <strong style={{ color: 'var(--text-h)' }}>{quote.totalIncludingTax} EUR</strong></p>
      </div>

      {quote.legalMention && (
        <p style={{ marginTop: '1.5rem', fontStyle: 'italic', color: 'var(--text)' }}>{quote.legalMention}</p>
      )}

      {/* Actions */}
      <div style={{ marginTop: '2rem', display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
        {quote.status === 'DRAFT' && (
          <button onClick={handleSend} disabled={actionLoading} className="app-btn-primary">
            Envoyer au client
          </button>
        )}
        {['SENT', 'ACCEPTED'].includes(quote.status) && !quote.invoiceId && (
          <button onClick={handleConvert} disabled={actionLoading} className="app-btn-primary">
            Convertir en facture
          </button>
        )}
        <button onClick={() => navigate('/quotes')} className="app-btn-outline">
          Retour aux devis
        </button>
      </div>
    </div>
  );
}
