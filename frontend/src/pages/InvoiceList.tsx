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

const STATUSES = [
  { value: '', label: 'Tous' },
  { value: 'DRAFT', label: 'Brouillon' },
  { value: 'SENT', label: 'Envoyee' },
  { value: 'ACKNOWLEDGED', label: 'Acceptee' },
  { value: 'REJECTED', label: 'Rejetee' },
  { value: 'PAID', label: 'Payee' },
  { value: 'CANCELLED', label: 'Annulee' },
];

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
      <div className="app-skeleton-header">
        <div className="app-skeleton app-skeleton-title" />
        <div className="app-skeleton" />
      </div>
      <div className="app-skeleton app-skeleton-pills" />
      <div className="app-table-wrapper app-skeleton-table">
        {[1,2,3,4,5,6,7,8].map(i => <div key={i} className="app-skeleton app-skeleton-table-row" />)}
      </div>
    </div>
  );

  return (
    <div className="app-container">
      <div className="app-page-header">
        <h1 className="app-page-title">Factures</h1>
        <div className="app-page-header-actions">
          <div className="app-view-toggle">
            <button
              onClick={() => setViewMode('list')}
              className={`app-view-toggle-btn${viewMode === 'list' ? ' app-view-toggle-btn--active' : ''}`}
            >
              Liste
            </button>
            <button
              onClick={() => setViewMode('kanban')}
              className={`app-view-toggle-btn${viewMode === 'kanban' ? ' app-view-toggle-btn--active' : ''}`}
            >
              Kanban
            </button>
          </div>
          <button onClick={() => navigate('/invoices/new')} className="app-btn-primary">
            Nouvelle facture
          </button>
        </div>
      </div>

      <div className="app-pills">
        {STATUSES.map((s) => (
          <button
            key={s.value}
            className={`app-pill${statusFilter === s.value ? ' app-pill--active' : ''}`}
            onClick={() => handleFilterChange(s.value)}
          >
            {s.label}
          </button>
        ))}
      </div>

      {invoices.length > 0 && viewMode === 'kanban' && (
        <InvoiceKanbanBoard invoices={invoices} onStateChange={handleStateChange} disabled={loading} />
      )}

      {invoices.length > 0 && viewMode === 'list' && (
        <>
          {/* Mobile: card-based list */}
          <div className="app-list app-mobile-only">
            {invoices.map((inv) => (
              <Link key={inv.id} to={`/invoices/${inv.id}`} className="app-list-item">
                <div className="app-list-item-info">
                  <div className="app-list-item-title">{inv.number || 'Brouillon'}</div>
                  <div className="app-list-item-sub">
                    {inv.buyer?.name} &middot; {new Date(inv.issueDate).toLocaleDateString('fr-FR')}
                  </div>
                </div>
                <div className="app-list-item-value">{inv.totalIncludingTax} EUR</div>
                {statusBadge(inv.status)}
              </Link>
            ))}
          </div>

          {/* Desktop: full table */}
          <div className="app-table-wrapper app-desktop-only">
            <table className="app-table">
              <thead>
                <tr>
                  <th>Numero</th>
                  <th>Client</th>
                  <th>Date</th>
                  <th className="text-right">HT</th>
                  <th className="text-right">TTC</th>
                  <th className="text-center">Statut</th>
                </tr>
              </thead>
              <tbody>
                {invoices.map((inv) => (
                  <tr key={inv.id} className="app-table-row-link" onClick={() => navigate(`/invoices/${inv.id}`)}>
                    <td>
                      <Link to={`/invoices/${inv.id}`} className="app-table-link">{inv.number || 'Brouillon'}</Link>
                    </td>
                    <td>{inv.buyer?.name}</td>
                    <td>{new Date(inv.issueDate).toLocaleDateString('fr-FR')}</td>
                    <td className="text-right">{inv.totalExcludingTax} EUR</td>
                    <td className="text-right">{inv.totalIncludingTax} EUR</td>
                    <td className="text-center">{statusBadge(inv.status)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </>
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
        <div className="app-pagination">
          <button
            onClick={() => setPage(p => Math.max(1, p - 1))}
            disabled={page <= 1}
            className="app-btn-primary"
          >
            Precedent
          </button>
          <span className="app-pagination-info">Page {page} / {totalPages}</span>
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
