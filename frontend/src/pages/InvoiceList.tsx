import { useEffect, useState } from 'react';
import { getInvoices, type Invoice } from '../api/factura';
import { Link, useNavigate } from 'react-router-dom';

// Badges de statut
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

const PAGE_SIZE = 20;

// Page liste des factures avec filtres, tri et pagination.
export default function InvoiceList() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const navigate = useNavigate();

  useEffect(() => {
    let cancelled = false;
    const params: Record<string, string> = {
      page: String(page),
      itemsPerPage: String(PAGE_SIZE),
    };
    if (statusFilter) params['status'] = statusFilter;

    getInvoices(params)
      .then((res) => {
        if (cancelled) return;
        setInvoices(res.data['hydra:member']);
        const total = (res.data as Record<string, unknown>)['hydra:totalItems'];
        setTotalItems(typeof total === 'number' ? total : 0);
      })
      .catch(() => { if (!cancelled) setInvoices([]); })
      .finally(() => { if (!cancelled) setLoading(false); });

    return () => { cancelled = true; };
  }, [statusFilter, page]);

  // Reinitialiser la page quand le filtre change
  const handleFilterChange = (value: string) => {
    setStatusFilter(value);
    setPage(1);
  };

  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  if (loading) return <p>Chargement...</p>;

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h1>Factures</h1>
        <button onClick={() => navigate('/invoices/new')} style={{ padding: '8px 16px', cursor: 'pointer' }}>
          Nouvelle facture
        </button>
      </div>

      <div style={{ marginBottom: '20px' }}>
        <label>Filtrer par statut : </label>
        <select value={statusFilter} onChange={(e) => handleFilterChange(e.target.value)}>
          <option value="">Tous</option>
          <option value="DRAFT">Brouillon</option>
          <option value="SENT">Envoyee</option>
          <option value="ACKNOWLEDGED">Acceptee</option>
          <option value="REJECTED">Rejetee</option>
          <option value="PAID">Payee</option>
          <option value="CANCELLED">Annulee</option>
        </select>
      </div>

      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ borderBottom: '2px solid #e5e7eb' }}>
            <th style={{ textAlign: 'left', padding: '8px' }}>Numero</th>
            <th style={{ textAlign: 'left', padding: '8px' }}>Client</th>
            <th style={{ textAlign: 'left', padding: '8px' }}>Date</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>HT</th>
            <th style={{ textAlign: 'right', padding: '8px' }}>TTC</th>
            <th style={{ textAlign: 'center', padding: '8px' }}>Statut</th>
          </tr>
        </thead>
        <tbody>
          {invoices.map((inv) => (
            <tr key={inv.id} style={{ borderBottom: '1px solid #f3f4f6', cursor: 'pointer' }}>
              <td style={{ padding: '8px' }}>
                <Link to={`/invoices/${inv.id}`}>{inv.number || 'Brouillon'}</Link>
              </td>
              <td style={{ padding: '8px' }}>{inv.buyer?.name}</td>
              <td style={{ padding: '8px' }}>{new Date(inv.issueDate).toLocaleDateString('fr-FR')}</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{inv.totalExcludingTax} EUR</td>
              <td style={{ textAlign: 'right', padding: '8px' }}>{inv.totalIncludingTax} EUR</td>
              <td style={{ textAlign: 'center', padding: '8px' }}>{statusBadge(inv.status)}</td>
            </tr>
          ))}
        </tbody>
      </table>

      {invoices.length === 0 && <p style={{ textAlign: 'center', marginTop: '20px' }}>Aucune facture trouvee.</p>}

      {totalPages > 1 && (
        <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '12px', marginTop: '20px' }}>
          <button
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page <= 1}
            style={{ padding: '6px 12px', cursor: page <= 1 ? 'not-allowed' : 'pointer' }}
          >
            Precedent
          </button>
          <span>Page {page} / {totalPages}</span>
          <button
            onClick={() => setPage(p => Math.min(totalPages, p + 1))}
            disabled={page >= totalPages}
            style={{ padding: '6px 12px', cursor: page >= totalPages ? 'not-allowed' : 'pointer' }}
          >
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
