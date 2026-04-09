import { useEffect, useState, useMemo } from 'react';
import { getClients, getInvoices, createClient, updateClient, deleteClient, type Client, type Invoice } from '../api/factura';
import EmptyState from '../components/EmptyState';
import ClientDataGrid from '../components/ClientDataGrid';
import Drawer from '../components/Drawer';
import { useToast } from '../context/ToastContext';
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
  const totalAmount = clientInvoices.reduce((s, inv) => s + parseFloat(inv.totalIncludingTax), 0);
  const paid = clientInvoices.filter(inv => inv.status === 'PAID');
  const paidAmount = paid.reduce((s, inv) => s + parseFloat(inv.totalIncludingTax), 0);
  const pendingInvs = clientInvoices.filter(inv => inv.status === 'SENT' || inv.status === 'ACKNOWLEDGED');
  const pendingAmount = pendingInvs.reduce((s, inv) => s + parseFloat(inv.totalIncludingTax), 0);

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

// Couleur selon le score de sante
function healthColor(score: number): string {
  if (score >= 80) return '#22c55e';
  if (score >= 50) return '#f59e0b';
  return '#ef4444';
}

// Page de gestion des clients enrichie avec KPIs et scoring.
export default function ClientList() {
  const [clients, setClients] = useState<Client[]>([]);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
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

  const load = () => {
    getClients()
      .then((res) => setClients(res.data['hydra:member']))
      .catch(() => setClients([]));
    getInvoices()
      .then((res) => setInvoices(res.data['hydra:member']))
      .catch(() => setInvoices([]));
  };

  useEffect(() => { load(); }, []);

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
      (c.siren && c.siren.includes(search)) ||
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
    if (!window.confirm("Êtes-vous sûr de vouloir supprimer ce client ?")) return;
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

  return (
    <div className="app-container">
      {/* En-tete avec KPIs */}
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem', flexWrap: 'wrap', gap: '1rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Clients</h1>
        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          <button onClick={handleExportCsv} className="app-btn-outline-danger" style={{ fontSize: '0.85rem' }}>
            Exporter CSV
          </button>
          <button onClick={() => setShowForm(!showForm)} className="app-btn-primary">
            {showForm ? 'Annuler' : 'Nouveau client'}
          </button>
        </div>
      </div>

      {/* KPIs globaux */}
      {clients.length > 0 && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))', gap: '0.75rem', marginBottom: '1.5rem' }}>
          {[
            { label: 'Clients', value: globalKpis.totalClients.toString(), sub: `${globalKpis.activeClients} actifs` },
            { label: 'CA total TTC', value: `${globalKpis.totalRevenue.toFixed(0)} €`, sub: 'Toutes factures' },
            { label: 'En attente', value: `${globalKpis.totalPending.toFixed(0)} €`, sub: 'Non regles' },
          ].map((kpi) => (
            <div key={kpi.label} className="app-card" style={{ padding: '1rem', textAlign: 'center' }}>
              <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginBottom: '0.25rem' }}>{kpi.label}</div>
              <div style={{ fontSize: '1.3rem', fontWeight: 700, color: 'var(--text-h)' }}>{kpi.value}</div>
              <div style={{ fontSize: '0.75rem', color: 'var(--text)', marginTop: '0.15rem' }}>{kpi.sub}</div>
            </div>
          ))}
        </div>
      )}

      {/* Barre de recherche et filtres */}
      {clients.length > 0 && (
        <div style={{ display: 'flex', gap: '0.75rem', marginBottom: '1rem', flexWrap: 'wrap', alignItems: 'center' }}>
          <input
            type="text"
            placeholder="Rechercher un client (nom, SIREN, ville)..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="app-input"
            style={{ flex: 1, minWidth: 200 }}
          />
          <select value={sortBy} onChange={(e) => setSortBy(e.target.value as 'name' | 'amount' | 'health')} className="app-select" style={{ width: 'auto' }}>
            <option value="name">Trier : Nom</option>
            <option value="amount">Trier : CA</option>
            <option value="health">Trier : Sante</option>
          </select>
          <div style={{ display: 'flex', borderRadius: '6px', overflow: 'hidden', border: '1px solid var(--border)' }}>
            <button
              onClick={() => setViewMode('cards')}
              style={{ padding: '6px 12px', border: 'none', background: viewMode === 'cards' ? 'var(--accent)' : 'var(--surface)', color: viewMode === 'cards' ? '#fff' : 'var(--text)', cursor: 'pointer', fontSize: '0.8rem' }}
            >Cartes</button>
            <button
              onClick={() => setViewMode('grid')}
              style={{ padding: '6px 12px', border: 'none', borderLeft: '1px solid var(--border)', background: viewMode === 'grid' ? 'var(--accent)' : 'var(--surface)', color: viewMode === 'grid' ? '#fff' : 'var(--text)', cursor: 'pointer', fontSize: '0.8rem' }}
            >Tableau</button>
          </div>
        </div>
      )}

      <Drawer isOpen={showForm} onClose={() => setShowForm(false)} title="Ajouter un client">
        <form onSubmit={handleSubmit} style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem', height: '100%' }}>

          <div className="app-form-group">
            <label className="app-label">Raison sociale</label>
            <input type="text" value={name} onChange={(e) => setName(e.target.value)} required className="app-input" placeholder="Ex: Acme Corp" />
          </div>
          <div className="app-form-group">
            <label className="app-label">SIREN (optionnel)</label>
            <input type="text" value={siren} onChange={(e) => setSiren(e.target.value)} className="app-input" placeholder="Ex: 123 456 789" />
          </div>

          <div className="app-form-group">
            <label className="app-label">Adresse</label>
            <input type="text" value={addressLine1} onChange={(e) => setAddressLine1(e.target.value)} required className="app-input" placeholder="123 Rue de la Paix" />
          </div>

          <div style={{ display: 'flex', gap: '1rem' }}>
            <div className="app-form-group" style={{ flex: 1 }}>
              <label className="app-label">Code postal</label>
              <input type="text" value={postalCode} onChange={(e) => setPostalCode(e.target.value)} required className="app-input" placeholder="75000" />
            </div>
            <div className="app-form-group" style={{ flex: 2 }}>
              <label className="app-label">Ville</label>
              <input type="text" value={city} onChange={(e) => setCity(e.target.value)} required className="app-input" placeholder="Paris" />
            </div>
          </div>

          <div style={{ display: 'flex', justifyContent: 'flex-end', marginTop: 'auto', paddingTop: '2rem', borderTop: '1px solid var(--border)' }}>
            <button type="submit" className="app-btn-primary" style={{ width: '100%' }}>Créer le client</button>
          </div>
        </form>
      </Drawer>

      {/* Vue Cartes enrichies */}
      {viewMode === 'cards' && filteredClients.length > 0 && (
        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(300px, 1fr))', gap: '1rem' }}>
          {filteredClients.map(client => {
            const stats = clientStats.get(client.id);
            const score = stats?.healthScore || 0;
            return (
              <div key={client.id} className="app-card" style={{ padding: '1.25rem', display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start' }}>
                  <div>
                    <div style={{ fontWeight: 700, fontSize: '1rem', color: 'var(--text-h)' }}>{client.name}</div>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '2px' }}>
                      {client.siren ? `SIREN ${client.siren}` : 'Pas de SIREN'} — {client.city}
                    </div>
                  </div>
                  {/* Indicateur de sante */}
                  <div style={{
                    width: 36, height: 36, borderRadius: '50%', display: 'flex', alignItems: 'center', justifyContent: 'center',
                    background: `${healthColor(score)}20`, color: healthColor(score), fontWeight: 700, fontSize: '0.8rem',
                  }}>
                    {score}
                  </div>
                </div>

                {/* Statistiques */}
                <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '0.5rem', fontSize: '0.8rem' }}>
                  <div style={{ padding: '0.5rem', background: 'var(--social-bg)', borderRadius: '6px' }}>
                    <div style={{ color: 'var(--text)' }}>Factures</div>
                    <div style={{ fontWeight: 600, color: 'var(--text-h)' }}>{stats?.totalInvoices || 0}</div>
                  </div>
                  <div style={{ padding: '0.5rem', background: 'var(--social-bg)', borderRadius: '6px' }}>
                    <div style={{ color: 'var(--text)' }}>CA TTC</div>
                    <div style={{ fontWeight: 600, color: 'var(--text-h)' }}>{(stats?.totalAmount || 0).toFixed(0)} EUR</div>
                  </div>
                  <div style={{ padding: '0.5rem', background: 'var(--social-bg)', borderRadius: '6px' }}>
                    <div style={{ color: 'var(--text)' }}>En attente</div>
                    <div style={{ fontWeight: 600, color: stats?.pendingAmount ? '#f59e0b' : 'var(--text-h)' }}>
                      {(stats?.pendingAmount || 0).toFixed(0)} EUR
                    </div>
                  </div>
                  <div style={{ padding: '0.5rem', background: 'var(--social-bg)', borderRadius: '6px' }}>
                    <div style={{ color: 'var(--text)' }}>Delai moy.</div>
                    <div style={{ fontWeight: 600, color: 'var(--text-h)' }}>{stats?.avgPaymentDelay || 0}j</div>
                  </div>
                </div>

                {/* Derniere facture */}
                {stats?.lastInvoiceDate && (
                  <div style={{ fontSize: '0.75rem', color: 'var(--text)' }}>
                    Derniere facture : {new Date(stats.lastInvoiceDate).toLocaleDateString('fr-FR')}
                  </div>
                )}

                {/* Actions */}
                <div style={{ display: 'flex', gap: '0.5rem', marginTop: 'auto' }}>
                  <button
                    onClick={() => handleDelete(client.id)}
                    style={{ background: 'none', border: 'none', color: '#ef4444', cursor: 'pointer', fontSize: '0.8rem', fontWeight: 600, padding: 0 }}
                  >
                    Supprimer
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
        <div style={{ textAlign: 'center', padding: '3rem', color: 'var(--text)' }}>
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
