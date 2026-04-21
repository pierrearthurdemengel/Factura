import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { getQuote, sendQuote, convertQuoteToInvoice, type Quote } from '../api/factura';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
import './AppLayout.css';

// Configuration des statuts
const STATUS_CONFIG: Record<string, { label: string; color: string; bg: string }> = {
  DRAFT: { label: 'Brouillon', color: '#6b7280', bg: 'rgba(107,114,128,0.1)' },
  SENT: { label: 'Envoye', color: 'var(--accent)', bg: 'var(--accent-bg)' },
  ACCEPTED: { label: 'Accepte', color: 'var(--success)', bg: 'var(--success-bg)' },
  REJECTED: { label: 'Refuse', color: 'var(--danger)', bg: 'var(--danger-bg)' },
  EXPIRED: { label: 'Expire', color: 'var(--warning)', bg: 'var(--warning-bg)' },
  CONVERTED: { label: 'Converti', color: '#8b5cf6', bg: 'rgba(139,92,246,0.1)' },
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
    // SWR: show cached quote instantly
    const cached = getCached<Quote>(`/quotes/${id}`);
    if (cached) {
      queueMicrotask(() => {
        setQuote(cached);
        setLoading(false);
      });
    } else {
      setLoading(true);
    }
    getQuote(id)
      .then((res) => { setQuote(res.data); setCache(`/quotes/${id}`, res.data); })
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
      const invoiceId = res.data.id || res.data['@id']?.split('/').pop();
      navigate(`/invoices/${invoiceId}`);
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
      <div className="app-page-header">
        <h1 className="app-page-title">Devis {quote.number || 'Brouillon'}</h1>
        <span
          className="app-status-pill"
          style={{ background: cfg.bg, color: cfg.color }}
        >
          {cfg.label}
        </span>
      </div>

      {/* Badge "Issu du devis" si le devis a ete converti */}
      {quote.convertedInvoice && (
        <div className="app-alert app-alert--info">
          <span className="app-alert-title">
            Facture generee
          </span>
          <Link to={`/invoices/${quote.convertedInvoice.split('/').pop()}`}>
            Voir la facture
          </Link>
        </div>
      )}

      <div className="app-grid">
        <div className="app-card">
          <h3 className="app-card-title">Vendeur</h3>
          <p className="app-card-text">{quote.seller?.name}</p>
          <p className="app-card-text">SIREN : {quote.seller?.siren}</p>
          <p className="app-card-text">{quote.seller?.addressLine1}</p>
          <p className="app-card-text">{quote.seller?.postalCode} {quote.seller?.city}</p>
        </div>
        <div className="app-card">
          <h3 className="app-card-title">Client</h3>
          <p className="app-card-text">{quote.buyer?.name}</p>
          {quote.buyer?.siren && <p className="app-card-text">SIREN : {quote.buyer.siren}</p>}
          <p className="app-card-text">{quote.buyer?.addressLine1}</p>
          <p className="app-card-text">{quote.buyer?.postalCode} {quote.buyer?.city}</p>
        </div>
      </div>

      <div className="app-card app-meta-card">
        <p className="app-meta-line">Date d'emission : <strong>{new Date(quote.issueDate).toLocaleDateString('fr-FR')}</strong></p>
        {quote.validUntil && (
          <p className="app-meta-line">Valide jusqu'au : <strong>{new Date(quote.validUntil).toLocaleDateString('fr-FR')}</strong></p>
        )}
      </div>

      <h2 className="app-section-title">Lignes</h2>
      <div className="app-table-wrapper">
        <table className="app-table">
          <thead>
            <tr>
              <th>Description</th>
              <th className="text-right">Quantite</th>
              <th className="text-center">Unite</th>
              <th className="text-right">Prix HT</th>
              <th className="text-right">TVA</th>
              <th className="text-right">Total HT</th>
            </tr>
          </thead>
          <tbody>
            {quote.lines?.map((line) => (
              <tr key={line.id}>
                <td>{line.description}</td>
                <td className="text-right">{line.quantity}</td>
                <td className="text-center">{line.unit}</td>
                <td className="text-right">{parseFloat(line.unitPriceExcludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</td>
                <td className="text-right">{line.vatRate}%</td>
                <td className="text-right">{parseFloat(line.lineAmount).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="app-totals">
        <p>Total HT : <strong>{parseFloat(quote.totalExcludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong></p>
        <p>Total TVA : <strong>{parseFloat(quote.totalTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong></p>
        <p className="app-totals-grand">Total TTC : <strong>{parseFloat(quote.totalIncludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong></p>
      </div>

      {quote.depositPercent != null && quote.depositPercent > 0 && (
        <div className="app-card app-mt-lg">
          <h3 className="app-card-title">Acompte demande</h3>
          <div className="app-kpi-grid">
            <div className="app-kpi-card">
              <div className="app-card-sub">Pourcentage</div>
              <div className="app-card-value">{quote.depositPercent}%</div>
            </div>
            <div className="app-kpi-card">
              <div className="app-card-sub">Montant acompte TTC</div>
              <div className="app-card-value" style={{ color: 'var(--accent)' }}>
                {parseFloat(quote.depositAmount || String(parseFloat(quote.totalIncludingTax) * quote.depositPercent / 100)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
              </div>
            </div>
            <div className="app-kpi-card">
              <div className="app-card-sub">Solde restant TTC</div>
              <div className="app-card-value">
                {(parseFloat(quote.totalIncludingTax) - (parseFloat(quote.depositAmount || '0') || parseFloat(quote.totalIncludingTax) * quote.depositPercent / 100)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
              </div>
            </div>
          </div>
        </div>
      )}

      {quote.legalMention && (
        <p className="app-legal">{quote.legalMention}</p>
      )}

      {/* Actions */}
      <div className="app-actions-row">
        {quote.status === 'DRAFT' && (
          <button onClick={handleSend} disabled={actionLoading} className="app-btn-primary">
            Envoyer au client
          </button>
        )}
        {['SENT', 'ACCEPTED'].includes(quote.status) && !quote.convertedInvoice && (
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
