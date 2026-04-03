import { useEffect, useState } from 'react';
import { getInvoices, type Invoice } from '../api/factura';
import { Link } from 'react-router-dom';

// Badges de statut avec couleurs
const statusBadge = (status: string) => {
  const colors: Record<string, string> = {
    DRAFT: '#6b7280',
    SENT: '#3b82f6',
    ACKNOWLEDGED: '#22c55e',
    REJECTED: '#ef4444',
    PAID: '#10b981',
    CANCELLED: '#9ca3af',
  };
  const labels: Record<string, string> = {
    DRAFT: 'Brouillon',
    SENT: 'Envoyee',
    ACKNOWLEDGED: 'Acceptee',
    REJECTED: 'Rejetee',
    PAID: 'Payee',
    CANCELLED: 'Annulee',
  };
  return (
    <span
      style={{
        backgroundColor: colors[status] || '#6b7280',
        color: 'white',
        padding: '2px 8px',
        borderRadius: '4px',
        fontSize: '12px',
      }}
    >
      {labels[status] || status}
    </span>
  );
};

// Page tableau de bord : compteurs et dernieres factures.
export default function Dashboard() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getInvoices()
      .then((res) => setInvoices(res.data['hydra:member']))
      .catch(() => setInvoices([]))
      .finally(() => setLoading(false));
  }, []);

  const thisMonth = invoices.filter((inv) => {
    const d = new Date(inv.issueDate);
    const now = new Date();
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });

  const totalHt = thisMonth.reduce((sum, inv) => sum + parseFloat(inv.totalExcludingTax), 0);
  const pending = invoices.filter((inv) => inv.status === 'SENT' || inv.status === 'ACKNOWLEDGED');

  if (loading) return <p>Chargement...</p>;

  return (
    <div>
      <h1>Tableau de bord</h1>

      <div style={{ display: 'flex', gap: '20px', marginBottom: '30px' }}>
        <div style={{ padding: '20px', border: '1px solid #e5e7eb', borderRadius: '8px', flex: 1 }}>
          <h3>Factures du mois</h3>
          <p style={{ fontSize: '24px', fontWeight: 'bold' }}>{thisMonth.length}</p>
        </div>
        <div style={{ padding: '20px', border: '1px solid #e5e7eb', borderRadius: '8px', flex: 1 }}>
          <h3>CA HT du mois</h3>
          <p style={{ fontSize: '24px', fontWeight: 'bold' }}>{totalHt.toFixed(2)} EUR</p>
        </div>
        <div style={{ padding: '20px', border: '1px solid #e5e7eb', borderRadius: '8px', flex: 1 }}>
          <h3>En attente</h3>
          <p style={{ fontSize: '24px', fontWeight: 'bold' }}>{pending.length}</p>
        </div>
      </div>

      <h2>Dernieres factures</h2>
      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ borderBottom: '2px solid #e5e7eb' }}>
            <th style={{ textAlign: 'left', padding: '8px' }}>Numero</th>
            <th style={{ textAlign: 'left', padding: '8px' }}>Client</th>
            <th style={{ textAlign: 'left', padding: '8px' }}>Date</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>Montant TTC</th>
            <th style={{ textAlign: 'center', padding: '8px' }}>Statut</th>
          </tr>
        </thead>
        <tbody>
          {invoices.slice(0, 10).map((inv) => (
            <tr key={inv.id} style={{ borderBottom: '1px solid #f3f4f6' }}>
              <td style={{ padding: '8px' }}>
                <Link to={`/invoices/${inv.id}`}>{inv.number || 'Brouillon'}</Link>
              </td>
              <td style={{ padding: '8px' }}>{inv.buyer?.name}</td>
              <td style={{ padding: '8px' }}>{new Date(inv.issueDate).toLocaleDateString('fr-FR')}</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{inv.totalIncludingTax} EUR</td>
              <td style={{ textAlign: 'center', padding: '8px' }}>{statusBadge(inv.status)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
