import { useEffect, useState } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import api, { getInvoice, sendInvoice, cancelInvoice, payInvoice, downloadPdf, downloadFacturX, downloadUbl, getInvoiceEvents, type Invoice, type InvoiceEvent } from '../api/factura';
import { useToast } from '../context/ToastContext';
import StatusDropdown from '../components/StatusDropdown';
import { downloadLocalPdf } from '../utils/pdfGenerator';
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

const eventColor = (type: string): string => {
  const colors: Record<string, string> = {
    CREATED: '#6b7280',
    STATUS_CHANGED: '#3b82f6',
    TRANSMITTED_TO_PDP: '#8b5cf6',
    RECEIVED_BY_PDP: '#06b6d4',
    ACKNOWLEDGED: '#22c55e',
    REJECTED: '#ef4444',
    PAID: '#10b981',
    ARCHIVED: '#6366f1',
    VIEWED_BY_BUYER: '#f59e0b',
    REMINDER_SENT: '#f97316',
    FORMAL_NOTICE_SENT: '#dc2626',
  };
  return colors[type] || '#6b7280';
};

export default function InvoiceDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { success, error } = useToast();
  const [invoice, setInvoice] = useState<Invoice | null>(null);
  const [events, setEvents] = useState<InvoiceEvent[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);

  const load = () => {
    if (!id) return;
    setLoading(true);
    Promise.all([
      getInvoice(id),
      getInvoiceEvents(id).catch(() => ({ data: [] as InvoiceEvent[] })),
    ])
      .then(([invoiceRes, eventsRes]) => {
        setInvoice(invoiceRes.data);
        setEvents(eventsRes.data);
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
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '2rem' }}>
        <div className="app-skeleton app-skeleton-title" style={{ margin: 0, width: '30%' }} />
        <div className="app-skeleton" style={{ width: '100px', height: '30px', borderRadius: '15px' }} />
      </div>
      <div className="app-grid">
        <div className="app-skeleton app-skeleton-card" />
        <div className="app-skeleton app-skeleton-card" />
      </div>
      <div className="app-skeleton app-skeleton-card" style={{ height: '80px', marginBottom: '2rem' }} />
      <div className="app-skeleton app-skeleton-title" style={{ width: '20%' }} />
      <div className="app-table-wrapper" style={{ padding: '1rem', border: 'none' }}>
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
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: 'clamp(1rem, 3vw, 2rem)' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Facture {invoice.number || 'Brouillon'}</h1>
        <StatusDropdown 
          statusLabel={statusLabels[invoice.status] || invoice.status}
          statusClass={statusClasses[invoice.status] || 'app-badge-draft'}
          disabled={actionLoading}
          actions={availableActions}
        />
      </div>

      {/* Badge "Issu du devis" si la facture provient d'un devis */}
      {invoice.sourceQuote && (
        <div style={{ marginBottom: '1rem', padding: '0.75rem', background: 'rgba(139,92,246,0.1)', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
          <span style={{ fontSize: '0.9rem', color: '#8b5cf6', fontWeight: 600 }}>
            Issu du devis {invoice.sourceQuote.number || ''}
          </span>
          <Link to={`/quotes/${invoice.sourceQuote.id}`} style={{ color: '#8b5cf6', textDecoration: 'underline', fontSize: '0.9rem' }}>
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
          <div style={{ marginBottom: '1rem', padding: '0.75rem', background: 'rgba(249,115,22,0.1)', borderRadius: '6px', display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
            <span style={{ fontSize: '0.9rem', color: '#f97316', fontWeight: 600 }}>
              Relance envoyee (J+{daysSinceReminder})
            </span>
            <span style={{ fontSize: '0.8rem', color: 'var(--text)' }}>
              — {reminderEvents.length} relance(s) au total, derniere le {new Date(lastReminder.occurredAt).toLocaleDateString('fr-FR')}
            </span>
          </div>
        );
      })()}

      <div className="app-grid">
        <div className="app-card">
          <h3 className="app-card-title">Vendeur</h3>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{invoice.seller?.name}</p>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>SIREN : {invoice.seller?.siren}</p>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{invoice.seller?.addressLine1}</p>
          <p style={{ margin: 0, color: 'var(--text)' }}>{invoice.seller?.postalCode} {invoice.seller?.city}</p>
        </div>
        <div className="app-card">
          <h3 className="app-card-title">Client</h3>
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{invoice.buyer?.name}</p>
          {invoice.buyer?.siren && <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>SIREN : {invoice.buyer.siren}</p>}
          <p style={{ margin: '0 0 0.25rem', color: 'var(--text)' }}>{invoice.buyer?.addressLine1}</p>
          <p style={{ margin: 0, color: 'var(--text)' }}>{invoice.buyer?.postalCode} {invoice.buyer?.city}</p>
        </div>
      </div>

      <div className="app-card" style={{ marginBottom: 'clamp(1.5rem, 3vw, 2rem)' }}>
        <p style={{ margin: '0 0 0.5rem', color: 'var(--text-h)' }}>Date d'emission : <strong>{new Date(invoice.issueDate).toLocaleDateString('fr-FR')}</strong></p>
        {invoice.dueDate && <p style={{ margin: '0 0 0.5rem', color: 'var(--text-h)' }}>Date d'echeance : <strong>{new Date(invoice.dueDate).toLocaleDateString('fr-FR')}</strong></p>}
        {invoice.pdpReference && <p style={{ margin: 0, color: 'var(--text-h)' }}>Reference PDP : <strong>{invoice.pdpReference}</strong></p>}
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
            {invoice.lines?.map((line) => (
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

      <div style={{ marginTop: 'clamp(1rem, 3vw, 2rem)', textAlign: 'right', color: 'var(--text)' }}>
        <p style={{ marginBottom: '0.5rem' }}>Total HT : <strong style={{ color: 'var(--text-h)' }}>{invoice.totalExcludingTax} EUR</strong></p>
        <p style={{ marginBottom: '0.5rem' }}>Total TVA : <strong style={{ color: 'var(--text-h)' }}>{invoice.totalTax} EUR</strong></p>
        <p style={{ fontSize: '1.15rem' }}>Total TTC : <strong style={{ color: 'var(--text-h)' }}>{invoice.totalIncludingTax} EUR</strong></p>
      </div>

      {invoice.legalMention && (
        <p style={{ marginTop: 'clamp(1rem, 3vw, 2rem)', fontStyle: 'italic', color: 'var(--text)' }}>{invoice.legalMention}</p>
      )}

      <div style={{ marginTop: 'clamp(1.5rem, 4vw, 2.5rem)', display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
        <button onClick={() => handleDownload('pdf')} className="app-btn-primary">
          Telecharger PDF
        </button>
        <button onClick={() => handleDownload('facturx')} className="app-btn-primary">
          Telecharger XML CII
        </button>
        <button onClick={() => handleDownload('ubl')} className="app-btn-primary">
          Telecharger XML UBL
        </button>
      </div>

      {events.length > 0 && (
        <div style={{ marginTop: 'clamp(2rem, 5vw, 3rem)' }}>
          <h2 className="app-section-title">Piste d'audit (PAF)</h2>
          <div style={{ borderLeft: '2px solid var(--border)', paddingLeft: 'clamp(1rem, 3vw, 1.5rem)', marginTop: '1rem', marginLeft: '0.5rem' }}>
            {events.map((event) => (
              <div key={event.id} style={{ marginBottom: '1.25rem', position: 'relative' }}>
                <div style={{
                  width: '12px', height: '12px', borderRadius: '50%',
                  backgroundColor: eventColor(event.eventType),
                  position: 'absolute', left: 'calc(-12px / 2 - clamp(1rem, 3vw, 1.5rem) - 1px)', top: '4px',
                }} />
                <div style={{ display: 'flex', gap: '10px', alignItems: 'center', flexWrap: 'wrap' }}>
                  <span style={{
                    fontSize: '0.8rem',
                    fontWeight: 'bold',
                    backgroundColor: eventColor(event.eventType),
                    color: 'white',
                    padding: '2px 8px',
                    borderRadius: '4px',
                  }}>
                    {eventLabel(event.eventType)}
                  </span>
                  <span style={{ fontSize: '0.85rem', color: 'var(--text)' }}>
                    {new Date(event.occurredAt).toLocaleString('fr-FR')}
                  </span>
                </div>
                {event.metadata && Object.keys(event.metadata).length > 0 && (
                  <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.5rem' }}>
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
