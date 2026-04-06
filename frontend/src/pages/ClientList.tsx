import { useEffect, useState } from 'react';
import { getClients, createClient, updateClient, deleteClient, type Client } from '../api/factura';
import EmptyState from '../components/EmptyState';
import ClientDataGrid from '../components/ClientDataGrid';
import Drawer from '../components/Drawer';
import { useToast } from '../context/ToastContext';
import './AppLayout.css';

// Page de gestion des clients.
export default function ClientList() {
  const [clients, setClients] = useState<Client[]>([]);
  const [showForm, setShowForm] = useState(false);
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
  };

  useEffect(() => { load(); }, []);

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
    // API Call (ClientDataGrid catches errors locally or throws them).
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

  return (
    <div className="app-container">
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 'clamp(1rem, 3vw, 2rem)', flexWrap: 'wrap', gap: '1rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Clients</h1>
        <button onClick={() => setShowForm(!showForm)} className="app-btn-outline-danger">
          {showForm ? 'Annuler' : 'Nouveau client'}
        </button>
      </div>

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

      {clients.length > 0 && (
        <ClientDataGrid 
          clients={clients} 
          onUpdateClient={handleUpdate} 
          onDeleteClient={handleDelete} 
        />
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
