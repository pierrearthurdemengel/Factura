import { useEffect, useState } from 'react';
import { getInvoices, sendInvoice, payInvoice, cancelInvoice, type Invoice } from '../api/factura';
import { Link, useNavigate } from 'react-router-dom';
import { useToast } from '../context/ToastContext';
import EmptyState from '../components/EmptyState';
import InvoiceKanbanBoard from '../components/InvoiceKanbanBoard';
import './AppLayout.css';

// Badges de statut
const statusBadge = (status: string) => {
  const classes: Record<string, string> = {
    DRAFT: 'app-badge-draft',
    SENT: 'app-badge-sent',
    ACKNOWLEDGED: 'app-badge-acknowledged',
    REJECTED: 'app-badge-rejected',
    PAID: 'app-badge-paid',
    CANCELLED: 'app-badge-cancelled',
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
    <span className={`app-badge ${classes[status] || 'app-badge-draft'}`}>
      {labels[status] || status}
    </span>
  );
};

const PAGE_SIZE = 20;

export default function InvoiceList() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('');
  const [page, setPage] = useState(1);
  const [totalItems, setTotalItems] = useState(0);
  const [viewMode, setViewMode] = useState<'list' | 'kanban'>('list');
  const navigate = useNavigate();
  const { success, error } = useToast();

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

  const handleFilterChange = (value: string) => {
    setStatusFilter(value);
    setPage(1);
  };

  const handleStateChange = async (id: string, newStatus: string) => {
    // Optimistic Update
    const previousInvoices = [...invoices];
    setInvoices((prev) => prev.map(inv => inv.id === id ? { ...inv, status: newStatus } : inv));
    
    try {
      const inv = previousInvoices.find(i => i.id === id);
      if (!inv) return;
      
      if (inv.status === 'DRAFT' && newStatus === 'SENT') {
        await sendInvoice(id);
      } else if (['SENT', 'ACKNOWLEDGED'].includes(inv.status) && newStatus === 'PAID') {
        await payInvoice(id);
      } else if (newStatus === 'CANCELLED') {
        await cancelInvoice(id);
      } else {
        error("Transition de statut non supportee.");
        setInvoices(previousInvoices);
        return;
      }
      
      success(`Facture deplacee en ${newStatus}`);
      // Background Full Refresh
      const params: Record<string, string> = { page: String(page), itemsPerPage: String(PAGE_SIZE) };
      if (statusFilter) params['status'] = statusFilter;
      getInvoices(params).then(res => setInvoices(res.data['hydra:member']));
    } catch {
      error("Erreur de MAJ de la facture");
      setInvoices(previousInvoices);
    }
  };

  const totalPages = Math.max(1, Math.ceil(totalItems / PAGE_SIZE));

  if (loading) return (
    <div className="app-container">
      <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '2rem' }}>
        <div className="app-skeleton app-skeleton-title" style={{ margin: 0, width: '200px' }} />
        <div className="app-skeleton" style={{ width: '150px', height: '40px', borderRadius: '6px' }} />
      </div>
      <div className="app-skeleton" style={{ width: '300px', height: '40px', borderRadius: '6px', marginBottom: '2rem' }} />
      <div className="app-table-wrapper" style={{ padding: '1rem', border: 'none' }}>
        {[1,2,3,4,5,6,7,8].map(i => <div key={i} className="app-skeleton app-skeleton-table-row" />)}
      </div>
    </div>
  );

  return (
    <div className="app-container">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'clamp(1rem, 3vw, 2rem)', flexWrap: 'wrap', gap: '1rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Factures</h1>
        <div style={{ display: 'flex', gap: '1rem' }}>
          <div style={{ display: 'flex', background: 'var(--social-bg)', borderRadius: '8px', padding: '4px' }}>
            <button onClick={() => setViewMode('list')} style={{ border: 'none', background: viewMode === 'list' ? 'var(--bg)' : 'transparent', color: viewMode === 'list' ? 'var(--text-h)' : 'var(--text)', padding: '6px 12px', borderRadius: '6px', cursor: 'pointer', fontWeight: viewMode === 'list' ? 600 : 400, boxShadow: viewMode === 'list' ? '0 2px 5px rgba(0,0,0,0.1)' : 'none' }}>
              Liste
            </button>
            <button onClick={() => setViewMode('kanban')} style={{ border: 'none', background: viewMode === 'kanban' ? 'var(--bg)' : 'transparent', color: viewMode === 'kanban' ? 'var(--text-h)' : 'var(--text)', padding: '6px 12px', borderRadius: '6px', cursor: 'pointer', fontWeight: viewMode === 'kanban' ? 600 : 400, boxShadow: viewMode === 'kanban' ? '0 2px 5px rgba(0,0,0,0.1)' : 'none' }}>
              Kanban
            </button>
          </div>
          <button onClick={() => navigate('/invoices/new')} className="app-btn-primary">
            Nouvelle facture
          </button>
        </div>
      </div>

      <div className="app-form-group" style={{ maxWidth: '300px', marginBottom: '2rem' }}>
        <label className="app-label">Filtrer par statut : </label>
        <select value={statusFilter} onChange={(e) => handleFilterChange(e.target.value)} className="app-select">
          <option value="">Tous</option>
          <option value="DRAFT">Brouillon</option>
          <option value="SENT">Envoyee</option>
          <option value="ACKNOWLEDGED">Acceptee</option>
          <option value="REJECTED">Rejetee</option>
          <option value="PAID">Payee</option>
          <option value="CANCELLED">Annulee</option>
        </select>
      </div>

      {invoices.length > 0 && viewMode === 'kanban' && (
        <InvoiceKanbanBoard invoices={invoices} onStateChange={handleStateChange} disabled={loading} />
      )}

      {invoices.length > 0 && viewMode === 'list' && (
        <div className="app-table-wrapper">
        <table className="app-table">
          <thead>
            <tr>
              <th>Numero</th>
              <th>Client</th>
              <th>Date</th>
              <th style={{ textAlign: 'right' }}>HT</th>
              <th style={{ textAlign: 'right' }}>TTC</th>
              <th style={{ textAlign: 'center' }}>Statut</th>
            </tr>
          </thead>
          <tbody>
            {invoices.map((inv) => (
              <tr key={inv.id} style={{ cursor: 'pointer' }} onClick={() => navigate(`/invoices/${inv.id}`)}>
                <td>
                  <Link to={`/invoices/${inv.id}`} style={{ color: 'var(--accent)', textDecoration: 'none', fontWeight: 500 }}>{inv.number || 'Brouillon'}</Link>
                </td>
                <td>{inv.buyer?.name}</td>
                <td>{new Date(inv.issueDate).toLocaleDateString('fr-FR')}</td>
                <td style={{ textAlign: 'right' }}>{inv.totalExcludingTax} EUR</td>
                <td style={{ textAlign: 'right' }}>{inv.totalIncludingTax} EUR</td>
                <td style={{ textAlign: 'center' }}>{statusBadge(inv.status)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      )}

      {invoices.length === 0 && (
        <EmptyState
          title="Aucune facture"
          description="Vous n'avez aucune facture correspondant a ces criteres."
          action={<button onClick={() => navigate('/invoices/new')} className="app-btn-primary">Nouvelle facture</button>}
          icon={
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
              <polyline points="10 9 9 9 8 9"></polyline>
            </svg>
          }
        />
      )}

      {totalPages > 1 && (
        <div style={{ display: 'flex', justifyContent: 'center', alignItems: 'center', gap: '12px', marginTop: 'clamp(1.5rem, 4vw, 2rem)' }}>
          <button
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page <= 1}
            className="app-btn-primary"
          >
            Precedent
          </button>
          <span style={{ fontWeight: 500 }}>Page {page} / {totalPages}</span>
          <button
            onClick={() => setPage(p => Math.min(totalPages, p + 1))}
            disabled={page >= totalPages}
            className="app-btn-primary"
          >
            Suivant
          </button>
        </div>
      )}
    </div>
  );
}
