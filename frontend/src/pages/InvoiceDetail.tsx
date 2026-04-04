import { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { getInvoice, sendInvoice, cancelInvoice, payInvoice, downloadPdf, downloadFacturX, downloadUbl, getInvoiceEvents, type Invoice, type InvoiceEvent } from '../api/factura';

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

// Labels des evenements PAF
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
  };
  return labels[type] || type;
};

// Couleurs des evenements PAF
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
  };
  return colors[type] || '#6b7280';
};

// Page detail d'une facture avec timeline PAF et actions.
export default function InvoiceDetail() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
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

  // eslint-disable-next-line react-hooks/exhaustive-deps -- load change a chaque render, on ne depend que de id
  useEffect(() => { load(); }, [id]);

  const handleAction = async (action: (id: string) => Promise<unknown>) => {
    if (!id) return;
    setActionLoading(true);
    try {
      await action(id);
      load();
    } catch {
      alert('Erreur lors de l\'action.');
    } finally {
      setActionLoading(false);
    }
  };

  const handleDownload = async (format: 'pdf' | 'facturx' | 'ubl') => {
    if (!id || !invoice) return;
    try {
      if (format === 'pdf') {
        const res = await downloadPdf(id);
        downloadBlob(new Blob([res.data], { type: 'application/pdf' }), `${invoice.number || 'brouillon'}.pdf`);
      } else {
        const fn = format === 'facturx' ? downloadFacturX : downloadUbl;
        const res = await fn(id);
        downloadBlob(new Blob([res.data]), `${invoice.number || 'brouillon'}-${format}.xml`);
      }
    } catch {
      alert('Erreur lors du telechargement.');
    }
  };

  if (loading || !invoice) return <p>Chargement...</p>;

  const statusLabels: Record<string, string> = {
    DRAFT: 'Brouillon',
    SENT: 'Envoyee',
    ACKNOWLEDGED: 'Acceptee',
    REJECTED: 'Rejetee',
    PAID: 'Payee',
    CANCELLED: 'Annulee',
  };

  const statusColors: Record<string, string> = {
    DRAFT: '#6b7280',
    SENT: '#3b82f6',
    ACKNOWLEDGED: '#22c55e',
    REJECTED: '#ef4444',
    PAID: '#10b981',
    CANCELLED: '#9ca3af',
  };

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <h1>Facture {invoice.number || 'Brouillon'}</h1>
        <span style={{
          fontSize: '14px',
          fontWeight: 'bold',
          backgroundColor: statusColors[invoice.status] || '#6b7280',
          color: 'white',
          padding: '4px 12px',
          borderRadius: '4px',
        }}>
          {statusLabels[invoice.status] || invoice.status}
        </span>
      </div>

      <div style={{ display: 'flex', gap: '30px', marginTop: '20px' }}>
        <div style={{ flex: 1 }}>
          <h3>Vendeur</h3>
          <p>{invoice.seller?.name}</p>
          <p>SIREN : {invoice.seller?.siren}</p>
          <p>{invoice.seller?.addressLine1}</p>
          <p>{invoice.seller?.postalCode} {invoice.seller?.city}</p>
        </div>
        <div style={{ flex: 1 }}>
          <h3>Client</h3>
          <p>{invoice.buyer?.name}</p>
          {invoice.buyer?.siren && <p>SIREN : {invoice.buyer.siren}</p>}
          <p>{invoice.buyer?.addressLine1}</p>
          <p>{invoice.buyer?.postalCode} {invoice.buyer?.city}</p>
        </div>
      </div>

      <div style={{ marginTop: '20px' }}>
        <p>Date d'emission : {new Date(invoice.issueDate).toLocaleDateString('fr-FR')}</p>
        {invoice.dueDate && <p>Date d'echeance : {new Date(invoice.dueDate).toLocaleDateString('fr-FR')}</p>}
        {invoice.pdpReference && <p>Reference PDP : {invoice.pdpReference}</p>}
      </div>

      <h2 style={{ marginTop: '30px' }}>Lignes</h2>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ borderBottom: '2px solid #e5e7eb' }}>
            <th style={{ textAlign: 'left', padding: '8px' }}>Description</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>Quantite</th>
            <th style={{ textAlign: 'center', padding: '8px' }}>Unite</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>Prix HT</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>TVA</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>Total HT</th>
          </tr>
        </thead>
        <tbody>
          {invoice.lines?.map((line) => (
            <tr key={line.id} style={{ borderBottom: '1px solid #f3f4f6' }}>
              <td style={{ padding: '8px' }}>{line.description}</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{line.quantity}</td>
              <td style={{ textAlign: 'center', padding: '8px' }}>{line.unit}</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{line.unitPriceExcludingTax} EUR</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{line.vatRate}%</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{line.lineAmount} EUR</td>
            </tr>
          ))}
        </tbody>
      </table>

      <div style={{ marginTop: '20px', textAlign: 'right' }}>
        <p>Total HT : <strong>{invoice.totalExcludingTax} EUR</strong></p>
        <p>Total TVA : <strong>{invoice.totalTax} EUR</strong></p>
        <p style={{ fontSize: '18px' }}>Total TTC : <strong>{invoice.totalIncludingTax} EUR</strong></p>
      </div>

      {invoice.legalMention && (
        <p style={{ marginTop: '20px', fontStyle: 'italic' }}>{invoice.legalMention}</p>
      )}

      <div style={{ marginTop: '30px', display: 'flex', gap: '10px', flexWrap: 'wrap' }}>
        {invoice.status === 'DRAFT' && (
          <button onClick={() => handleAction(sendInvoice)} disabled={actionLoading}>
            Envoyer
          </button>
        )}
        {['DRAFT', 'SENT', 'ACKNOWLEDGED'].includes(invoice.status) && (
          <button onClick={() => handleAction(cancelInvoice)} disabled={actionLoading}>
            Annuler
          </button>
        )}
        {['SENT', 'ACKNOWLEDGED'].includes(invoice.status) && (
          <button onClick={() => handleAction(payInvoice)} disabled={actionLoading}>
            Marquer payee
          </button>
        )}

        <span style={{ borderLeft: '1px solid #e5e7eb', margin: '0 4px' }} />

        <button onClick={() => handleDownload('pdf')} style={{ cursor: 'pointer' }}>
          Telecharger PDF
        </button>
        <button onClick={() => handleDownload('facturx')} style={{ cursor: 'pointer' }}>
          Telecharger XML CII
        </button>
        <button onClick={() => handleDownload('ubl')} style={{ cursor: 'pointer' }}>
          Telecharger XML UBL
        </button>
      </div>

      {events.length > 0 && (
        <div style={{ marginTop: '30px' }}>
          <h2>Piste d'audit (PAF)</h2>
          <div style={{ borderLeft: '2px solid #e5e7eb', paddingLeft: '16px', marginTop: '10px' }}>
            {events.map((event) => (
              <div key={event.id} style={{ marginBottom: '12px', position: 'relative' }}>
                <div style={{
                  width: '10px', height: '10px', borderRadius: '50%',
                  backgroundColor: eventColor(event.eventType),
                  position: 'absolute', left: '-22px', top: '4px',
                }} />
                <div style={{ display: 'flex', gap: '10px', alignItems: 'center' }}>
                  <span style={{
                    fontSize: '12px',
                    fontWeight: 'bold',
                    backgroundColor: eventColor(event.eventType),
                    color: 'white',
                    padding: '2px 8px',
                    borderRadius: '4px',
                  }}>
                    {eventLabel(event.eventType)}
                  </span>
                  <span style={{ fontSize: '12px', color: '#6b7280' }}>
                    {new Date(event.occurredAt).toLocaleString('fr-FR')}
                  </span>
                </div>
                {event.metadata && Object.keys(event.metadata).length > 0 && (
                  <div style={{ fontSize: '12px', color: '#9ca3af', marginTop: '4px' }}>
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
