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
  if (isNaN(num)) return `${amount} EUR`;
  return num.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EUR';
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
      <div className="app-container" style={{ minHeight: '100vh' }}>
        <div style={{ textAlign: 'center', marginBottom: 'clamp(1.5rem, 4vw, 2.5rem)' }}>
          <div className="app-skeleton" style={{ width: '120px', height: '40px', borderRadius: '8px', margin: '0 auto 1rem' }} />
          <div className="app-skeleton app-skeleton-title" style={{ width: '50%', margin: '0 auto' }} />
        </div>
        <div className="app-grid">
          <div className="app-skeleton app-skeleton-card" />
          <div className="app-skeleton app-skeleton-card" />
        </div>
        <div className="app-skeleton app-skeleton-card" style={{ height: '80px', marginTop: '1.5rem', marginBottom: '1.5rem' }} />
        <div className="app-skeleton app-skeleton-title" style={{ width: '20%' }} />
        <div style={{ padding: '1rem' }}>
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton app-skeleton-table-row" />
          ))}
        </div>
        <div style={{ marginTop: '1.5rem', display: 'flex', justifyContent: 'flex-end' }}>
          <div className="app-skeleton" style={{ width: '200px', height: '60px', borderRadius: '8px' }} />
        </div>
      </div>
    );
  }

  // --- Etat d'erreur (token invalide ou facture introuvable) ---
  if (error || !portalData) {
    return (
      <div className="app-container" style={{ minHeight: '100vh', display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center' }}>
        <div style={{ textAlign: 'center', maxWidth: '480px' }}>
          <div style={{ fontSize: '4rem', marginBottom: '1rem', opacity: 0.3 }}>404</div>
          <h1 style={{ fontSize: 'clamp(1.25rem, 3vw, 1.75rem)', color: 'var(--text-h)', marginBottom: '1rem' }}>
            Facture introuvable
          </h1>
          <p style={{ color: 'var(--text)', lineHeight: 1.6 }}>
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
    SENT: { label: 'En attente', color: '#2563eb', bg: 'rgba(37,99,235,0.1)' },
    ACKNOWLEDGED: { label: 'Acceptee', color: '#22c55e', bg: 'rgba(34,197,94,0.1)' },
    REJECTED: { label: 'Rejetee', color: '#ef4444', bg: 'rgba(239,68,68,0.1)' },
    PAID: { label: 'Payee', color: '#10b981', bg: 'rgba(16,185,129,0.1)' },
    CANCELLED: { label: 'Annulee', color: '#9ca3af', bg: 'rgba(156,163,175,0.1)' },
  };

  const cfg = statusConfig[invoice.status] || statusConfig.SENT;

  return (
    <div style={{ minHeight: '100vh', background: 'var(--social-bg, #f9fafb)' }}>
      <div className="app-container" style={{ paddingTop: 'clamp(1.5rem, 4vw, 3rem)', paddingBottom: 'clamp(2rem, 5vw, 4rem)' }}>

        {/* En-tete avec logo du vendeur et numero de facture */}
        <div style={{ textAlign: 'center', marginBottom: 'clamp(1.5rem, 4vw, 2.5rem)' }}>
          {sellerLogo && (
            <img
              src={sellerLogo}
              alt={invoice.seller?.name || 'Logo'}
              style={{ maxHeight: '60px', maxWidth: '200px', objectFit: 'contain', marginBottom: '1rem' }}
            />
          )}
          <h1 className="app-page-title" style={{ margin: '0 0 0.75rem' }}>
            Facture {invoice.number || ''}
          </h1>
          <span style={{
            padding: '4px 14px',
            borderRadius: '1rem',
            fontSize: '0.85rem',
            fontWeight: 600,
            background: cfg.bg,
            color: cfg.color,
            display: 'inline-block',
          }}>
            {cfg.label}
          </span>
        </div>

        {/* Informations vendeur et acheteur */}
        <div className="app-grid" style={{ marginBottom: 'clamp(1rem, 3vw, 1.5rem)' }}>
          <div className="app-card">
            <h3 className="app-card-title">Emetteur</h3>
            <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontWeight: 600 }}>{invoice.seller?.name}</p>
            {invoice.seller?.siren && (
              <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>SIREN : {invoice.seller.siren}</p>
            )}
            {invoice.seller?.vatNumber && (
              <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>TVA : {invoice.seller.vatNumber}</p>
            )}
            <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>{invoice.seller?.addressLine1}</p>
            <p style={{ margin: 0, color: 'var(--text)', fontSize: '0.9rem' }}>{invoice.seller?.postalCode} {invoice.seller?.city}</p>
          </div>
          <div className="app-card">
            <h3 className="app-card-title">Destinataire</h3>
            <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontWeight: 600 }}>{invoice.buyer?.name}</p>
            {invoice.buyer?.siren && (
              <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>SIREN : {invoice.buyer.siren}</p>
            )}
            {invoice.buyer?.vatNumber && (
              <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>TVA : {invoice.buyer.vatNumber}</p>
            )}
            <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>{invoice.buyer?.addressLine1}</p>
            <p style={{ margin: 0, color: 'var(--text)', fontSize: '0.9rem' }}>{invoice.buyer?.postalCode} {invoice.buyer?.city}</p>
          </div>
        </div>

        {/* Dates et references */}
        <div className="app-card" style={{ marginBottom: 'clamp(1rem, 3vw, 1.5rem)' }}>
          <div style={{ display: 'flex', flexWrap: 'wrap', gap: 'clamp(1rem, 3vw, 2rem)' }}>
            <div>
              <p style={{ margin: '0 0 0.25rem', fontSize: '0.85rem', color: 'var(--text)' }}>Date d'emission</p>
              <p style={{ margin: 0, fontWeight: 600, color: 'var(--text-h)' }}>
                {new Date(invoice.issueDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
              </p>
            </div>
            {invoice.dueDate && (
              <div>
                <p style={{ margin: '0 0 0.25rem', fontSize: '0.85rem', color: 'var(--text)' }}>Date d'echeance</p>
                <p style={{ margin: 0, fontWeight: 600, color: 'var(--text-h)' }}>
                  {new Date(invoice.dueDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
                </p>
              </div>
            )}
            {invoice.currency && (
              <div>
                <p style={{ margin: '0 0 0.25rem', fontSize: '0.85rem', color: 'var(--text)' }}>Devise</p>
                <p style={{ margin: 0, fontWeight: 600, color: 'var(--text-h)' }}>{invoice.currency}</p>
              </div>
            )}
          </div>
        </div>

        {/* Tableau des lignes de facture */}
        <h2 className="app-section-title">Detail des prestations</h2>
        <div className="app-table-wrapper" style={{ marginBottom: 'clamp(1rem, 3vw, 1.5rem)' }}>
          <table className="app-table">
            <thead>
              <tr>
                <th>Description</th>
                <th style={{ textAlign: 'right' }}>Quantite</th>
                <th style={{ textAlign: 'center' }}>Unite</th>
                <th style={{ textAlign: 'right' }}>Prix unitaire HT</th>
                <th style={{ textAlign: 'right' }}>TVA</th>
                <th style={{ textAlign: 'right' }}>Montant HT</th>
              </tr>
            </thead>
            <tbody>
              {invoice.lines?.map((line) => (
                <tr key={line.id}>
                  <td>{line.description}</td>
                  <td style={{ textAlign: 'right' }}>{line.quantity}</td>
                  <td style={{ textAlign: 'center' }}>{line.unit}</td>
                  <td style={{ textAlign: 'right' }}>{formatEur(line.unitPriceExcludingTax)}</td>
                  <td style={{ textAlign: 'right' }}>{line.vatRate}%</td>
                  <td style={{ textAlign: 'right' }}>{formatEur(line.lineAmount)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Totaux */}
        <div className="app-card" style={{ marginBottom: 'clamp(1rem, 3vw, 1.5rem)' }}>
          <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'flex-end', gap: '0.5rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', maxWidth: '300px' }}>
              <span style={{ color: 'var(--text)' }}>Total HT</span>
              <strong style={{ color: 'var(--text-h)' }}>{formatEur(invoice.totalExcludingTax)}</strong>
            </div>
            <div style={{ display: 'flex', justifyContent: 'space-between', width: '100%', maxWidth: '300px' }}>
              <span style={{ color: 'var(--text)' }}>Total TVA</span>
              <strong style={{ color: 'var(--text-h)' }}>{formatEur(invoice.totalTax)}</strong>
            </div>
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              width: '100%',
              maxWidth: '300px',
              paddingTop: '0.5rem',
              borderTop: '2px solid var(--border)',
              fontSize: '1.15rem',
            }}>
              <span style={{ color: 'var(--text-h)', fontWeight: 600 }}>Total TTC</span>
              <strong style={{ color: 'var(--text-h)' }}>{formatEur(invoice.totalIncludingTax)}</strong>
            </div>
          </div>
        </div>

        {/* Mentions legales */}
        {invoice.legalMention && (
          <p style={{ fontStyle: 'italic', color: 'var(--text)', fontSize: '0.85rem', lineHeight: 1.6, marginBottom: 'clamp(1rem, 3vw, 1.5rem)' }}>
            {invoice.legalMention}
          </p>
        )}

        {/* Conditions de paiement */}
        {invoice.paymentTerms && (
          <div className="app-card" style={{ marginBottom: 'clamp(1rem, 3vw, 1.5rem)', background: 'rgba(37,99,235,0.04)' }}>
            <h3 className="app-card-title" style={{ fontSize: '0.95rem' }}>Conditions de paiement</h3>
            <p style={{ margin: 0, color: 'var(--text)', fontSize: '0.9rem', lineHeight: 1.5 }}>
              {invoice.paymentTerms}
            </p>
          </div>
        )}

        {/* Coordonnees bancaires du vendeur (si disponibles) */}
        {invoice.seller?.iban && (
          <div className="app-card" style={{ marginBottom: 'clamp(1.5rem, 4vw, 2rem)' }}>
            <h3 className="app-card-title" style={{ fontSize: '0.95rem' }}>Coordonnees bancaires</h3>
            <p style={{ margin: '0 0 0.25rem', color: 'var(--text)', fontSize: '0.9rem' }}>
              IBAN : <strong>{invoice.seller.iban}</strong>
            </p>
            {invoice.seller.bic && (
              <p style={{ margin: 0, color: 'var(--text)', fontSize: '0.9rem' }}>
                BIC : <strong>{invoice.seller.bic}</strong>
              </p>
            )}
          </div>
        )}

        {/* Boutons d'action : telecharger le PDF et payer */}
        <div style={{
          display: 'flex',
          gap: '0.75rem',
          flexWrap: 'wrap',
          justifyContent: 'center',
          marginTop: 'clamp(1rem, 3vw, 2rem)',
        }}>
          <button
            onClick={handleDownloadPdf}
            disabled={downloading}
            className="app-btn-primary"
            style={{ minWidth: '200px' }}
          >
            {downloading ? 'Telechargement...' : 'Telecharger le PDF'}
          </button>

          {/* Le bouton de paiement n'apparait que si un lien est fourni et que la facture n'est pas deja payee */}
          {paymentLink && invoice.status !== 'PAID' && invoice.status !== 'CANCELLED' && (
            <button
              onClick={handlePay}
              className="app-btn-primary"
              style={{
                minWidth: '200px',
                background: '#10b981',
              }}
            >
              Payer cette facture
            </button>
          )}
        </div>

        {/* Pied de page discret */}
        <div style={{
          textAlign: 'center',
          marginTop: 'clamp(2rem, 5vw, 4rem)',
          paddingTop: '1.5rem',
          borderTop: '1px solid var(--border)',
          color: 'var(--text)',
          fontSize: '0.8rem',
          opacity: 0.6,
        }}>
          <p style={{ margin: 0 }}>
            Ce document a ete genere par {invoice.seller?.name}
          </p>
        </div>
      </div>
    </div>
  );
}
