import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api, { getInvoice, sendInvoice, cancelInvoice, payInvoice, downloadPdf, downloadFacturX, downloadUbl, getInvoiceEvents, type Invoice, type InvoiceEvent } from '../api/factura';
import { useToast } from '../context/ToastContext';
import StatusDropdown from '../components/StatusDropdown';
import { downloadLocalPdf } from '../utils/pdfGenerator';
import { getCached, setCache } from '../utils/apiCache';
import './AppLayout.css';

// Telecharge un blob en fichier
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

const eventLabel = (type: string): string => {
  const labels: Record<string, string> = {
    CREATED: 'Creation',
    STATUS_CHANGED: 'Changement de statut',
    TRANSMITTED_TO_PDP: 'Transmise a la PDP',
    RECEIVED_BY_PDP: 'Recue par la PDP',
    ACKNOWLEDGED: 'Acceptee',
    REJECTED: 'Rejetee',
    PAID: 'Payee',
    ARCHIVED: 'Archivee',
    VIEWED_BY_BUYER: 'Vue par le client',
    REMINDER_SENT: 'Relance envoyee',
    FORMAL_NOTICE_SENT: 'Mise en demeure',
  };
  return labels[type] || type;
};

const eventColorClass = (type: string): string => {
  const classes: Record<string, string> = {
    CREATED: 'app-timeline-dot--created',
    STATUS_CHANGED: 'app-timeline-dot--status',
    TRANSMITTED_TO_PDP: 'app-timeline-dot--info',
    RECEIVED_BY_PDP: 'app-timeline-dot--info',
    ACKNOWLEDGED: 'app-timeline-dot--success',
    REJECTED: 'app-timeline-dot--danger',
    PAID: 'app-timeline-dot--success',
    ARCHIVED: 'app-timeline-dot--status',
    VIEWED_BY_BUYER: 'app-timeline-dot--warning',
    REMINDER_SENT: 'app-timeline-dot--warning',
    FORMAL_NOTICE_SENT: 'app-timeline-dot--danger',
  };
  return classes[type] || 'app-timeline-dot--created';
};

const eventLabelClass = (type: string): string => {
  return eventColorClass(type).replace('dot', 'label');
};

export default function InvoiceDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { success, error } = useToast();
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [events, setEvents] = useState<InvoiceEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const [factoringRate, setFactoringRate] = useState(5);

  const load = () => {
    if (!id) return;

    // SWR: show cached invoice instantly
    const cachedInv = getCached<Invoice>(`/invoices/${id}`);
    if (cachedInv) {
      queueMicrotask(() => {
        setInvoice(cachedInv);
        setLoading(false);
      });
    } else {
      setLoading(true);
    }

    Promise.all([
      getInvoice(id),
      getInvoiceEvents(id).catch(() => ({ data: [] as InvoiceEvent[] })),
      api.get<{ commissionRate: number }>('/factoring/rates').catch(() => null),
    ])
      .then(([invoiceRes, eventsRes, factoringRes]) => {
        setInvoice(invoiceRes.data);
        setEvents(eventsRes.data);
        setCache(`/invoices/${id}`, invoiceRes.data);
        if (factoringRes?.data?.commissionRate) {
          setFactoringRate(factoringRes.data.commissionRate);
        }
      })
      .catch(() => navigate('/invoices'))
      .finally(() => setLoading(false));
  };

  // eslint-disable-next-line react-hooks/exhaustive-deps
  useEffect(() => { load(); }, [id]);

  const handleAction = async (action: (id: string) => Promise<unknown>) => {
    if (!id) return;
    setActionLoading(true);
    try {
      await action(id);
      success("L'action a bien ete prise en compte.");
      load();
    } catch {
      error('Erreur lors de l\'action.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDownload = async (format: 'pdf' | 'facturx' | 'ubl') => {
    if (!id || !invoice) return;
    try {
      if (format === 'pdf') {
        try {
          success('Génération SVG/PDF Ultra-HD en cours...');
          await downloadLocalPdf('.app-container', `${invoice.number || 'brouillon'}.pdf`);
          return;
        } catch {
          // Fallback au serveur si erreur
          const res = await downloadPdf(id);
          downloadBlob(new Blob([res.data], { type: 'application/pdf' }), `${invoice.number || 'brouillon'}.pdf`);
        }
      } else {
        const fn = format === 'facturx' ? downloadFacturX : downloadUbl;
        const res = await fn(id);
        downloadBlob(new Blob([res.data]), `${invoice.number || 'brouillon'}-${format}.xml`);
      }
      success("Format telecharge avec succes !");
    } catch {
      error('Erreur lors du telechargement d\'un document.');
    }
  };

  if (loading || !invoice) return (
    <div className="app-container">
      <div className="app-page-header">
        <div className="app-skeleton app-skeleton-title" />
        <div className="app-skeleton app-badge" />
      </div>
      <div className="app-grid">
        <div className="app-skeleton app-skeleton-card" />
        <div className="app-skeleton app-skeleton-card" />
      </div>
      <div className="app-skeleton app-skeleton-card" />
      <div className="app-skeleton app-skeleton-title" />
      <div className="app-table-wrapper">
        {[1,2,3].map(i => <div key={i} className="app-skeleton app-skeleton-table-row" />)}
      </div>
    </div>
  );

  const statusLabels: Record<string, string> = {
    DRAFT: 'Brouillon',
    SENT: 'Envoyee',
    ACKNOWLEDGED: 'Acceptee',
    REJECTED: 'Rejetee',
    PAID: 'Payee',
    CANCELLED: 'Annulee',
  };

  const statusClasses: Record<string, string> = {
    DRAFT: 'app-badge-draft',
    SENT: 'app-badge-sent',
    ACKNOWLEDGED: 'app-badge-acknowledged',
    REJECTED: 'app-badge-rejected',
    PAID: 'app-badge-paid',
    CANCELLED: 'app-badge-cancelled',
  };

  const availableActions = [];
  if (invoice.status === 'DRAFT') {
    availableActions.push({ label: 'Envoyer au client', icon: '📨', onClick: () => handleAction(sendInvoice) });
  }
  if (['SENT', 'ACKNOWLEDGED'].includes(invoice.status)) {
    availableActions.push({ label: 'Marquer payee', icon: '✅', onClick: () => handleAction(payInvoice) });
  }
  if (['SENT', 'ACKNOWLEDGED'].includes(invoice.status)) {
    availableActions.push({ label: 'Relancer manuellement', icon: '🔔', onClick: () => handleAction((invoiceId: string) => api.post(`/invoices/${invoiceId}/remind`)) });
  }
  if (['DRAFT', 'SENT', 'ACKNOWLEDGED'].includes(invoice.status)) {
    availableActions.push({ label: 'Annuler la facture', icon: '❌', onClick: () => handleAction(cancelInvoice), danger: true });
  }

  return (
    <div className="app-container">
      <div className="app-page-header">
        <h1 className="app-page-title">Facture {invoice.number || 'Brouillon'}</h1>
        <StatusDropdown
          statusLabel={statusLabels[invoice.status] || invoice.status}
          statusClass={statusClasses[invoice.status] || 'app-badge-draft'}
          disabled={actionLoading}
          actions={availableActions}
        />
      </div>

      {/* Badge "Issu du devis" si la facture provient d'un devis */}
      {invoice.sourceQuote && (
        <div className="app-alert app-alert--info">
          <span className="app-alert-title">
            Issu du devis
          </span>
          <Link to={`/quotes/${invoice.sourceQuote.split('/').pop()}`}>
            Voir le devis
          </Link>
        </div>
      )}

      {/* Badge de relance si des relances ont ete envoyees */}
      {(() => {
        const reminderEvents = events.filter((e) => e.eventType === 'REMINDER_SENT' || e.eventType === 'FORMAL_NOTICE_SENT');
        if (reminderEvents.length === 0) return null;
        const lastReminder = reminderEvents[reminderEvents.length - 1];
        const daysSinceReminder = Math.floor((Date.now() - new Date(lastReminder.occurredAt).getTime()) / (1000 * 60 * 60 * 24));
        return (
          <div className="app-alert app-alert--warning">
            <span className="app-alert-title">
              Relance envoyee (J+{daysSinceReminder})
            </span>
            <span className="app-alert-sub">
              — {reminderEvents.length} relance(s) au total, derniere le {new Date(lastReminder.occurredAt).toLocaleDateString('fr-FR')}
            </span>
          </div>
        );
      })()}

      <div className="app-grid">
        <div className="app-card">
          <h3 className="app-card-title">Vendeur</h3>
          <p className="app-card-text">{invoice.seller?.name}</p>
          <p className="app-card-text">SIREN : {invoice.seller?.siren}</p>
          <p className="app-card-text">{invoice.seller?.addressLine1}</p>
          <p className="app-card-text">{invoice.seller?.postalCode} {invoice.seller?.city}</p>
        </div>
        <div className="app-card">
          <h3 className="app-card-title">Client</h3>
          <p className="app-card-text">{invoice.buyer?.name}</p>
          {invoice.buyer?.siren && <p className="app-card-text">SIREN : {invoice.buyer.siren}</p>}
          <p className="app-card-text">{invoice.buyer?.addressLine1}</p>
          <p className="app-card-text">{invoice.buyer?.postalCode} {invoice.buyer?.city}</p>
        </div>
      </div>

      <div className="app-card app-meta-card">
        <p className="app-meta-line">Date d'emission : <strong>{new Date(invoice.issueDate).toLocaleDateString('fr-FR')}</strong></p>
        {invoice.dueDate && <p className="app-meta-line">Date d'echeance : <strong>{new Date(invoice.dueDate).toLocaleDateString('fr-FR')}</strong></p>}
        {invoice.pdpReference && <p className="app-meta-line">Reference PDP : <strong>{invoice.pdpReference}</strong></p>}
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
            {invoice.lines?.map((line) => (
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
        <p>Total HT : <strong>{parseFloat(invoice.totalExcludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong></p>
        <p>Total TVA : <strong>{parseFloat(invoice.totalTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong></p>
        <p className="app-totals-grand">Total TTC : <strong>{parseFloat(invoice.totalIncludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</strong></p>
      </div>

      {invoice.legalMention && (
        <p className="app-legal">{invoice.legalMention}</p>
      )}

      {/* Affacturage : proposition de financement pour les factures non payees */}
      {['SENT', 'ACKNOWLEDGED'].includes(invoice.status) && parseFloat(invoice.totalIncludingTax) > 0 && (
        <div className="app-card app-factoring-card">
          <div className="app-factoring-inner">
            <div>
              <div className="app-factoring-title">
                Recevoir le paiement maintenant
              </div>
              <div className="app-factoring-desc">
                Financez cette facture et recevez {(parseFloat(invoice.totalIncludingTax) * (1 - factoringRate / 100)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € sous 48h
                <span className="app-factoring-commission"> (commission de {factoringRate}%)</span>
              </div>
            </div>
            <button
              onClick={() => {
                api.post(`/factoring/request`, { invoiceId: invoice.id }).then(() => {
                  success('Demande de financement envoyee.');
                }).catch(() => {
                  error('Erreur lors de la demande de financement.');
                });
              }}
              className="app-btn-primary"
            >
              Financer cette facture
            </button>
          </div>
        </div>
      )}

      <div className="app-actions-row">
        <button onClick={() => handleDownload('pdf')} className="app-btn-primary">
          Telecharger PDF
        </button>
        <button onClick={() => handleDownload('facturx')} className="app-btn-outline">
          XML CII (Factur-X)
        </button>
        <button onClick={() => handleDownload('ubl')} className="app-btn-outline">
          XML UBL
        </button>
      </div>

      {events.length > 0 && (
        <div className="app-paf-section">
          <h2 className="app-section-title">Piste d'audit (PAF)</h2>
          <div className="app-timeline">
            {events.map((event) => (
              <div key={event.id} className="app-timeline-item">
                <div className={`app-timeline-dot ${eventColorClass(event.eventType)}`} />
                <div className="app-timeline-item-row">
                  <span className={`app-timeline-label ${eventLabelClass(event.eventType)}`}>
                    {eventLabel(event.eventType)}
                  </span>
                  <span className="app-timeline-date">
                    {new Date(event.occurredAt).toLocaleString('fr-FR')}
                  </span>
                </div>
                {event.metadata && Object.keys(event.metadata).length > 0 && (
                  <div className="app-timeline-meta">
                    {event.metadata.from && event.metadata.to && (
                      <span>{event.metadata.from} → {event.metadata.to}</span>
                    )}
                  </div>
                )}
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
