import { useEffect, useState, useRef } from 'react';
import { useParams } from 'react-router-dom';
import api, { type Invoice } from '../api/factura';
import './AppLayout.css';

// Interface pour les donnees du portail client (reponse API publique)
interface PortalData {
  invoice: Invoice;
  paymentLink: string | null;
  sellerLogo: string | null;
}

// Telecharge un blob en fichier local
function downloadBlob(blob: Blob, filename: string) {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  URL.revokeObjectURL(url);
}

// Formate un montant en euros avec separateur de milliers
function formatEur(amount: string): string {
  const num = parseFloat(amount);
  if (isNaN(num)) return `${amount} €`;
  return num.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
}

export default function ClientPortal() {
  const { token } = useParams<{ token: string }>();
  const [portalData, setPortalData] = useState<PortalData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [downloading, setDownloading] = useState(false);
  // Evite de signaler la vue plusieurs fois
  const viewSent = useRef(false);

  // Charge les donnees de la facture via le lien de partage
  useEffect(() => {
    if (!token) {
      setError(true);
      setLoading(false);
      return;
    }

    api
      .get<PortalData>(`/portal/${token}`)
      .then((res) => {
        setPortalData(res.data);
      })
      .catch(() => {
        setError(true);
      })
      .finally(() => {
        setLoading(false);
      });
  }, [token]);

  // Signale automatiquement que la facture a ete vue par le client
  useEffect(() => {
    if (!token || !portalData || viewSent.current) return;
    viewSent.current = true;
    api.post(`/portal/${token}/view`).catch(() => {
      // Pas critique si l'appel echoue, on ne bloque pas l'affichage
    });
  }, [token, portalData]);

  // Telecharge le PDF de la facture via le portail public
  const handleDownloadPdf = async () => {
    if (!token) return;
    setDownloading(true);
    try {
      const res = await api.get(`/portal/${token}/pdf`, {
        responseType: 'blob',
        headers: { Accept: 'application/pdf' },
      });
      const filename = portalData?.invoice.number
        ? `${portalData.invoice.number}.pdf`
        : 'facture.pdf';
      downloadBlob(new Blob([res.data], { type: 'application/pdf' }), filename);
    } catch {
      // Erreur silencieuse, le bouton revient a l'etat normal
    } finally {
      setDownloading(false);
    }
  };

  // Redirige vers le lien de paiement externe
  const handlePay = () => {
    if (portalData?.paymentLink) {
      window.location.href = portalData.paymentLink;
    }
  };

  // --- Etat de chargement avec squelette ---
  if (loading) {
    return (
      <div className="app-container portal-loading">
        <div className="portal-skeleton-header">
          <div className="app-skeleton portal-skeleton-logo" />
          <div className="app-skeleton app-skeleton-title portal-skeleton-title" />
        </div>
        <div className="app-grid">
          <div className="app-skeleton app-skeleton-card" />
          <div className="app-skeleton app-skeleton-card" />
        </div>
        <div className="app-skeleton app-skeleton-card portal-skeleton-dates" />
        <div className="app-skeleton app-skeleton-title portal-skeleton-section-title" />
        <div className="portal-skeleton-rows">
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton app-skeleton-table-row" />
          ))}
        </div>
        <div className="portal-skeleton-totals">
          <div className="app-skeleton portal-skeleton-totals-box" />
        </div>
      </div>
    );
  }

  // --- Etat d'erreur (token invalide ou facture introuvable) ---
  if (error || !portalData) {
    return (
      <div className="app-container portal-error-wrapper">
        <div className="portal-error-inner">
          <div className="portal-error-code">404</div>
          <h1 className="portal-error-title">
            Facture introuvable
          </h1>
          <p className="portal-error-desc">
            Ce lien est invalide ou a expire. Veuillez contacter l'emetteur de la facture
            pour obtenir un nouveau lien de partage.
          </p>
        </div>
      </div>
    );
  }

  const { invoice, paymentLink, sellerLogo } = portalData;

  // Configuration des badges de statut
  const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
    DRAFT: { label: 'Brouillon', color: '#6b7280', bg: 'rgba(107,114,128,0.1)' },
    SENT: { label: 'En attente', color: 'var(--accent)', bg: 'var(--accent-bg)' },
    ACKNOWLEDGED: { label: 'Acceptee', color: 'var(--success)', bg: 'var(--success-bg)' },
    REJECTED: { label: 'Rejetee', color: 'var(--danger)', bg: 'var(--danger-bg)' },
    PAID: { label: 'Payee', color: 'var(--success)', bg: 'var(--success-bg)' },
    CANCELLED: { label: 'Annulee', color: '#9ca3af', bg: 'rgba(156,163,175,0.1)' },
  };

  const cfg = statusConfig[invoice.status] || statusConfig.SENT;

  return (
    <div className="portal-bg">
      <div className="app-container portal-container">

        {/* En-tete avec logo du vendeur et numero de facture */}
        <div className="portal-header">
          {sellerLogo && (
            <img
              src={sellerLogo}
              alt={invoice.seller?.name || 'Logo'}
              className="portal-seller-logo"
            />
          )}
          <h1 className="app-page-title portal-invoice-title">
            Facture {invoice.number || ''}
          </h1>
          <span
            className="app-status-pill"
            style={{ background: cfg.bg, color: cfg.color }}
          >
            {cfg.label}
          </span>
        </div>

        {/* Informations vendeur et acheteur */}
        <div className="app-grid portal-entities-row">
          <div className="app-card">
            <h3 className="app-card-title">Emetteur</h3>
            <p className="app-card-text">{invoice.seller?.name}</p>
            {invoice.seller?.siren && (
              <p className="app-card-text">SIREN : {invoice.seller.siren}</p>
            )}
            {invoice.seller?.vatNumber && (
              <p className="app-card-text">TVA : {invoice.seller.vatNumber}</p>
            )}
            <p className="app-card-text">{invoice.seller?.addressLine1}</p>
            <p className="app-card-text">{invoice.seller?.postalCode} {invoice.seller?.city}</p>
          </div>
          <div className="app-card">
            <h3 className="app-card-title">Destinataire</h3>
            <p className="app-card-text">{invoice.buyer?.name}</p>
            {invoice.buyer?.siren && (
              <p className="app-card-text">SIREN : {invoice.buyer.siren}</p>
            )}
            {invoice.buyer?.vatNumber && (
              <p className="app-card-text">TVA : {invoice.buyer.vatNumber}</p>
            )}
            <p className="app-card-text">{invoice.buyer?.addressLine1}</p>
            <p className="app-card-text">{invoice.buyer?.postalCode} {invoice.buyer?.city}</p>
          </div>
        </div>

        {/* Dates et references */}
        <div className="app-card portal-dates-card">
          <div className="portal-dates-row">
            <div>
              <p className="portal-date-label">Date d'emission</p>
              <p className="portal-date-value">
                {new Date(invoice.issueDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
              </p>
            </div>
            {invoice.dueDate && (
              <div>
                <p className="portal-date-label">Date d'echeance</p>
                <p className="portal-date-value">
                  {new Date(invoice.dueDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
                </p>
              </div>
            )}
            {invoice.currency && (
              <div>
                <p className="portal-date-label">Devise</p>
                <p className="portal-date-value">{invoice.currency}</p>
              </div>
            )}
          </div>
        </div>

        {/* Tableau des lignes de facture */}
        <h2 className="app-section-title">Detail des prestations</h2>
        <div className="app-table-wrapper portal-table-section">
          <table className="app-table">
            <thead>
              <tr>
                <th>Description</th>
                <th className="text-right">Quantite</th>
                <th className="text-center">Unite</th>
                <th className="text-right">Prix unitaire HT</th>
                <th className="text-right">TVA</th>
                <th className="text-right">Montant HT</th>
              </tr>
            </thead>
            <tbody>
              {invoice.lines?.map((line) => (
                <tr key={line.id}>
                  <td>{line.description}</td>
                  <td className="text-right">{line.quantity}</td>
                  <td className="text-center">{line.unit}</td>
                  <td className="text-right">{formatEur(line.unitPriceExcludingTax)}</td>
                  <td className="text-right">{line.vatRate}%</td>
                  <td className="text-right">{formatEur(line.lineAmount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Totaux */}
        <div className="app-card portal-totals-card">
          <div className="portal-totals-inner">
            <div className="portal-totals-line">
              <span className="portal-totals-line-label">Total HT</span>
              <strong className="portal-totals-line-value">{formatEur(invoice.totalExcludingTax)}</strong>
            </div>
            <div className="portal-totals-line">
              <span className="portal-totals-line-label">Total TVA</span>
              <strong className="portal-totals-line-value">{formatEur(invoice.totalTax)}</strong>
            </div>
            <div className="portal-totals-line portal-totals-grand">
              <span className="portal-totals-line-label">Total TTC</span>
              <strong className="portal-totals-line-value">{formatEur(invoice.totalIncludingTax)}</strong>
            </div>
          </div>
        </div>

        {/* Mentions legales */}
        {invoice.legalMention && (
          <p className="portal-legal">
            {invoice.legalMention}
          </p>
        )}

        {/* Conditions de paiement */}
        {invoice.paymentTerms && (
          <div className="app-card portal-payment-terms-card">
            <h3 className="app-card-title portal-payment-terms-title">Conditions de paiement</h3>
            <p className="app-card-text">
              {invoice.paymentTerms}
            </p>
          </div>
        )}

        {/* Coordonnees bancaires du vendeur (si disponibles) */}
        {invoice.seller?.iban && (
          <div className="app-card portal-bank-card">
            <h3 className="app-card-title portal-bank-title">Coordonnees bancaires</h3>
            <p className="app-card-text">
              IBAN : <strong>{invoice.seller.iban}</strong>
            </p>
            {invoice.seller.bic && (
              <p className="app-card-text">
                BIC : <strong>{invoice.seller.bic}</strong>
              </p>
            )}
          </div>
        )}

        {/* Boutons d'action : telecharger le PDF et payer */}
        <div className="portal-actions">
          <button
            onClick={handleDownloadPdf}
            disabled={downloading}
            className="app-btn-primary portal-btn-download"
          >
            {downloading ? 'Telechargement...' : 'Telecharger le PDF'}
          </button>

          {/* Le bouton de paiement n'apparait que si un lien est fourni et que la facture n'est pas deja payee */}
          {paymentLink && invoice.status !== 'PAID' && invoice.status !== 'CANCELLED' && (
            <button
              onClick={handlePay}
              className="app-btn-primary portal-btn-pay"
            >
              Payer cette facture
            </button>
          )}
        </div>

        {/* Pied de page discret */}
        <div className="portal-footer">
          <p>
            Ce document a ete genere par {invoice.seller?.name}
          </p>
        </div>
      </div>
    </div>
  );
}
