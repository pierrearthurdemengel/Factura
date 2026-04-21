import { useEffect, useState, useMemo } from 'react';
import { useIntl } from 'react-intl';
import { getClients, getInvoices, createClient, updateClient, deleteClient, type Client, type Invoice } from '../api/factura';
import EmptyState from '../components/EmptyState';
import ClientDataGrid from '../components/ClientDataGrid';
import Drawer from '../components/Drawer';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
import './AppLayout.css';

// Donnees enrichies par client
interface ClientStats {
  totalInvoices: number;
  totalAmount: number;
  paidAmount: number;
  pendingAmount: number;
  avgPaymentDelay: number;
  // Score de sante : 0-100
  healthScore: number;
  lastInvoiceDate: string | null;
}

// Calcule le score de sante d'un client a partir de ses factures
function computeClientStats(clientId: string, invoices: Invoice[]): ClientStats {
  const clientInvoices = invoices.filter(inv => inv.buyer?.id === clientId);
  const total = clientInvoices.length;
  const totalAmount = clientInvoices.reduce((s, inv) => s + Number.parseFloat(inv.totalIncludingTax), 0);
  const paid = clientInvoices.filter(inv => inv.status === 'PAID');
  const paidAmount = paid.reduce((s, inv) => s + Number.parseFloat(inv.totalIncludingTax), 0);
  const pendingInvs = clientInvoices.filter(inv => inv.status === 'SENT' || inv.status === 'ACKNOWLEDGED');
  const pendingAmount = pendingInvs.reduce((s, inv) => s + Number.parseFloat(inv.totalIncludingTax), 0);

  // Delai moyen de paiement (jours entre issueDate et now pour les payees)
  let avgDelay = 0;
  if (paid.length > 0) {
    const delays = paid.map(inv => {
      const issue = new Date(inv.issueDate).getTime();
      const now = Date.now();
      return Math.max(0, Math.round((now - issue) / (1000 * 60 * 60 * 24)));
    });
    avgDelay = Math.round(delays.reduce((a, b) => a + b, 0) / delays.length);
  }

  // Score de sante : bonus si bien paye, malus si beaucoup d'impayes
  const paidRatio = total > 0 ? paid.length / total : 1;
  const overdue = pendingInvs.filter(inv => inv.dueDate && new Date(inv.dueDate) < new Date()).length;
  const overdueRatio = total > 0 ? overdue / total : 0;
  const healthScore = Math.max(0, Math.min(100, Math.round(paidRatio * 80 + 20 - overdueRatio * 40)));

  const sorted = [...clientInvoices].sort((a, b) => new Date(b.issueDate).getTime() - new Date(a.issueDate).getTime());

  return {
    totalInvoices: total, totalAmount, paidAmount, pendingAmount,
    avgPaymentDelay: avgDelay, healthScore,
    lastInvoiceDate: sorted[0]?.issueDate || null,
  };
}

// Couleur selon le score de sante (utilise les variables CSS du design system)
function healthColor(score: number): string {
  if (score >= 80) return 'var(--success)';
  if (score >= 50) return 'var(--warning)';
  return 'var(--danger)';
}

// Page de gestion des clients enrichie avec KPIs et scoring.
export default function ClientList() {
  const [clients, setClients] = useState<Client[]>([]);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [search, setSearch] = useState('');
  const [sortBy, setSortBy] = useState<'name' | 'amount' | 'health'>('name');
  const [viewMode, setViewMode] = useState<'grid' | 'cards'>('cards');
  const [name, setName] = useState('');
  const [siren, setSiren] = useState('');
  const [addressLine1, setAddressLine1] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const [city, setCity] = useState('');
  const { success, error } = useToast();
  const intl = useIntl();

  const load = () => {
    // SWR: show cached data instantly
    const cachedClients = getCached<{ 'hydra:member': Client[] }>('/clients');
    const cachedInvoices = getCached<{ 'hydra:member': Invoice[] }>('/invoices');
    if (cachedClients && cachedInvoices) {
      queueMicrotask(() => {
        setClients(cachedClients['hydra:member']);
        setInvoices(cachedInvoices['hydra:member']);
        setLoading(false);
      });
    } else {
      setLoading(true);
    }

    Promise.all([
      getClients().then((res) => { setClients(res.data['hydra:member']); setCache('/clients', res.data); }).catch(() => setClients([])),
      getInvoices().then((res) => { setInvoices(res.data['hydra:member']); setCache('/invoices', res.data); }).catch(() => setInvoices([])),
    ]).finally(() => setLoading(false));
  };

  useEffect(() => { load(); }, []); // eslint-disable-line react-hooks/set-state-in-effect

  // Stats par client
  const clientStats = useMemo(() => {
    const map = new Map<string, ClientStats>();
    clients.forEach(c => map.set(c.id, computeClientStats(c.id, invoices)));
    return map;
  }, [clients, invoices]);

  // KPIs globaux
  const globalKpis = useMemo(() => {
    const totalClients = clients.length;
    const activeClients = clients.filter(c => (clientStats.get(c.id)?.totalInvoices || 0) > 0).length;
    const totalRevenue = Array.from(clientStats.values()).reduce((s, st) => s + st.totalAmount, 0);
    const totalPending = Array.from(clientStats.values()).reduce((s, st) => s + st.pendingAmount, 0);
    return { totalClients, activeClients, totalRevenue, totalPending };
  }, [clients, clientStats]);

  // Filtrage et tri
  const filteredClients = useMemo(() => {
    const result = clients.filter(c =>
      c.name.toLowerCase().includes(search.toLowerCase()) ||
      c.siren?.includes(search) ||
      c.city.toLowerCase().includes(search.toLowerCase())
    );
    if (sortBy === 'name') result.sort((a, b) => a.name.localeCompare(b.name));
    else if (sortBy === 'amount') result.sort((a, b) => (clientStats.get(b.id)?.totalAmount || 0) - (clientStats.get(a.id)?.totalAmount || 0));
    else if (sortBy === 'health') result.sort((a, b) => (clientStats.get(b.id)?.healthScore || 0) - (clientStats.get(a.id)?.healthScore || 0));
    return result;
  }, [clients, search, sortBy, clientStats]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await createClient({ name, siren: siren || undefined, addressLine1, postalCode, city });
    setShowForm(false);
    success('Client ajouté.');
    setName('');
    setSiren('');
    setAddressLine1('');
    setPostalCode('');
    setCity('');
    load();
  };

  const handleUpdate = async (id: string, data: Partial<Client>) => {
    await updateClient(id, data);
    load();
  };

  const handleDelete = async (id: string) => {
    if (!globalThis.confirm("Êtes-vous sûr de vouloir supprimer ce client ?")) return;
    try {
      await deleteClient(id);
      success("Client supprimé avec succès.");
      setClients(clients.filter(c => c.id !== id));
    } catch {
      error("Impossible de supprimer le client, peut-être lié à une facture.");
    }
  };

  // Export CSV de la liste clients
  const handleExportCsv = () => {
    const header = 'Raison sociale;SIREN;Ville;Factures;CA TTC;En attente;Score sante\n';
    const rows = filteredClients.map(c => {
      const st = clientStats.get(c.id);
      return `${c.name};${c.siren || ''};${c.city};${st?.totalInvoices || 0};${(st?.totalAmount || 0).toFixed(2)};${(st?.pendingAmount || 0).toFixed(2)};${st?.healthScore || 0}`;
    }).join('\n');
    const blob = new Blob([header + rows], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url; a.download = 'clients-factura.csv'; a.click();
    URL.revokeObjectURL(url);
  };

  /* Squelette de chargement affiché pendant la récupération des données */
  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton-header">
        <div className="app-skeleton app-skeleton-title" />
        <div className="app-skeleton" style={{ width: '140px', height: '36px' }} />
      </div>
      <div className="app-kpi-grid">
        {[1, 2, 3].map(i => <div key={i} className="app-skeleton app-skeleton-card" />)}
      </div>
      <div className="app-cards-grid">
        {[1, 2, 3, 4].map(i => <div key={i} className="app-skeleton app-skeleton-card" style={{ height: '200px' }} />)}
      </div>
    </div>
  );

  return (
    <div className="app-container">
      {/* En-tete avec KPIs */}
      <div className="app-page-header">
        <h1 className="app-page-title">{intl.formatMessage({ id: 'nav.clients', defaultMessage: 'Clients' })}</h1>
        <div className="app-page-header-actions">
          <button onClick={handleExportCsv} className="app-btn-outline-danger">
            Exporter CSV
          </button>
          <button onClick={() => setShowForm(!showForm)} className="app-btn-primary">
            {showForm ? intl.formatMessage({ id: 'common.cancel', defaultMessage: 'Annuler' }) : intl.formatMessage({ id: 'client.new', defaultMessage: 'Nouveau client' })}
          </button>
        </div>
      </div>

      {/* KPIs globaux */}
      {clients.length > 0 && (
        <div className="app-kpi-grid">
          {[
            { label: 'Clients', value: globalKpis.totalClients.toString(), sub: `${globalKpis.activeClients} actifs` },
            { label: 'CA total TTC', value: `${globalKpis.totalRevenue.toFixed(0)} €`, sub: 'Toutes factures' },
            { label: 'En attente', value: `${globalKpis.totalPending.toFixed(0)} €`, sub: 'Non regles' },
          ].map((kpi) => (
            <div key={kpi.label} className="app-card app-kpi-card">
              <div className="app-card-title">{kpi.label}</div>
              <div className="app-card-value">{kpi.value}</div>
              <div className="app-card-sub">{kpi.sub}</div>
            </div>
          ))}
        </div>
      )}

      {/* Barre de recherche et filtres */}
      {clients.length > 0 && (
        <div className="app-filter-bar">
          <input
            type="text"
            placeholder={intl.formatMessage({ id: 'client.search', defaultMessage: 'Rechercher un client...' })}
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="app-input"
          />
          <select value={sortBy} onChange={(e) => setSortBy(e.target.value as 'name' | 'amount' | 'health')} className="app-select">
            <option value="name">Trier : Nom</option>
            <option value="amount">Trier : CA</option>
            <option value="health">Trier : Sante</option>
          </select>
          <div className="app-view-toggle">
            <button
              onClick={() => setViewMode('cards')}
              className={`app-view-toggle-btn${viewMode === 'cards' ? ' app-view-toggle-btn--active' : ''}`}
            >Cartes</button>
            <button
              onClick={() => setViewMode('grid')}
              className={`app-view-toggle-btn${viewMode === 'grid' ? ' app-view-toggle-btn--active' : ''}`}
            >Tableau</button>
          </div>
        </div>
      )}

      <Drawer isOpen={showForm} onClose={() => setShowForm(false)} title="Ajouter un client">
        <form onSubmit={handleSubmit} className="app-drawer-form">

          <div className="app-form-group">
            <label htmlFor="client-name" className="app-label">{intl.formatMessage({ id: 'client.name', defaultMessage: 'Raison sociale' })}</label>
            <input id="client-name" type="text" value={name} onChange={(e) => setName(e.target.value)} required className="app-input" placeholder="Ex: Acme Corp" />
          </div>
          <div className="app-form-group">
            <label htmlFor="client-siren" className="app-label">{intl.formatMessage({ id: 'client.siren', defaultMessage: 'SIREN' })} (optionnel)</label>
            <input id="client-siren" type="text" value={siren} onChange={(e) => setSiren(e.target.value)} className="app-input" placeholder="Ex: 123 456 789" />
          </div>

          <div className="app-form-group">
            <label htmlFor="client-address" className="app-label">{intl.formatMessage({ id: 'client.address', defaultMessage: 'Adresse' })}</label>
            <input id="client-address" type="text" value={addressLine1} onChange={(e) => setAddressLine1(e.target.value)} required className="app-input" placeholder="123 Rue de la Paix" />
          </div>

          <div className="app-form-row">
            <div className="app-form-group">
              <label htmlFor="client-postal-code" className="app-label">{intl.formatMessage({ id: 'client.postalCode', defaultMessage: 'Code postal' })}</label>
              <input id="client-postal-code" type="text" value={postalCode} onChange={(e) => setPostalCode(e.target.value)} required className="app-input" placeholder="75000" />
            </div>
            <div className="app-form-group flex-2">
              <label htmlFor="client-city" className="app-label">{intl.formatMessage({ id: 'client.city', defaultMessage: 'Ville' })}</label>
              <input id="client-city" type="text" value={city} onChange={(e) => setCity(e.target.value)} required className="app-input" placeholder="Paris" />
            </div>
          </div>

          <div className="app-form-actions">
            <button type="submit" className="app-btn-primary">Créer le client</button>
          </div>
        </form>
      </Drawer>

      {/* Vue Cartes enrichies */}
      {viewMode === 'cards' && filteredClients.length > 0 && (
        <div className="app-cards-grid">
          {filteredClients.map(client => {
            const stats = clientStats.get(client.id);
            const score = stats?.healthScore || 0;
            return (
              <div key={client.id} className="app-card app-client-card">
                <div className="app-client-card-header">
                  <div>
                    <div className="app-client-card-name">{client.name}</div>
                    <div className="app-client-card-sub">
                      {client.siren ? `SIREN ${client.siren}` : 'Pas de SIREN'} — {client.city}
                    </div>
                  </div>
                  {/* Indicateur de sante */}
                  <div
                    className="app-health-indicator"
                    style={{ background: `${healthColor(score)}20`, color: healthColor(score) }}
                  >
                    {score}
                  </div>
                </div>

                {/* Statistiques */}
                <div className="app-stat-grid">
                  <div className="app-stat-box">
                    <div className="app-stat-box-label">Factures</div>
                    <div className="app-stat-box-value">{stats?.totalInvoices || 0}</div>
                  </div>
                  <div className="app-stat-box">
                    <div className="app-stat-box-label">CA TTC</div>
                    <div className="app-stat-box-value">{(stats?.totalAmount || 0).toFixed(0)} €</div>
                  </div>
                  <div className="app-stat-box">
                    <div className="app-stat-box-label">En attente</div>
                    <div className="app-stat-box-value" style={{ color: stats?.pendingAmount ? 'var(--warning)' : undefined }}>
                      {(stats?.pendingAmount || 0).toFixed(0)} €
                    </div>
                  </div>
                  <div className="app-stat-box">
                    <div className="app-stat-box-label">Delai moy.</div>
                    <div className="app-stat-box-value">{stats?.avgPaymentDelay || 0}j</div>
                  </div>
                </div>

                {/* Derniere facture */}
                {stats?.lastInvoiceDate && (
                  <div className="app-client-card-meta">
                    Derniere facture : {new Date(stats.lastInvoiceDate).toLocaleDateString('fr-FR')}
                  </div>
                )}

                {/* Actions */}
                <div className="app-client-card-actions">
                  <button
                    onClick={() => handleDelete(client.id)}
                    className="app-text-link-danger"
                  >
                    {intl.formatMessage({ id: 'client.delete', defaultMessage: 'Supprimer' })}
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* Vue Tableau (DataGrid classique) */}
      {viewMode === 'grid' && filteredClients.length > 0 && (
        <ClientDataGrid
          clients={filteredClients}
          onUpdateClient={handleUpdate}
          onDeleteClient={handleDelete}
        />
      )}

      {filteredClients.length === 0 && clients.length > 0 && (
        <div className="app-no-results">
          Aucun client ne correspond a la recherche.
        </div>
      )}

      {clients.length === 0 && (
        <EmptyState
          title="Aucun client"
          description="Vous n'avez pas encore de clients dans votre repertoire."
          icon={
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
              <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
              <circle cx="12" cy="7" r="4"></circle>
            </svg>
          }
        />
      )}
    </div>
  );
}
