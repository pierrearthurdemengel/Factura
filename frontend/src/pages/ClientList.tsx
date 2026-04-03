import { useEffect, useState } from 'react';
import { getClients, createClient, type Client } from '../api/factura';

// Page de gestion des clients.
export default function ClientList() {
  const [clients, setClients] = useState<Client[]>([]);
  const [showForm, setShowForm] = useState(false);
  const [name, setName] = useState('');
  const [siren, setSiren] = useState('');
  const [addressLine1, setAddressLine1] = useState('');
  const [postalCode, setPostalCode] = useState('');
  const [city, setCity] = useState('');

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
    setName('');
    setSiren('');
    setAddressLine1('');
    setPostalCode('');
    setCity('');
    load();
  };

  return (
    <div>
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '20px' }}>
        <h1>Clients</h1>
        <button onClick={() => setShowForm(!showForm)}>
          {showForm ? 'Annuler' : 'Nouveau client'}
        </button>
      </div>

      {showForm && (
        <form onSubmit={handleSubmit} style={{ marginBottom: '20px', padding: '15px', border: '1px solid #e5e7eb', borderRadius: '8px' }}>
          <div style={{ marginBottom: '10px' }}>
            <label>Raison sociale</label>
            <input type="text" value={name} onChange={(e) => setName(e.target.value)} required style={{ width: '100%' }} />
          </div>
          <div style={{ marginBottom: '10px' }}>
            <label>SIREN (optionnel)</label>
            <input type="text" value={siren} onChange={(e) => setSiren(e.target.value)} style={{ width: '100%' }} />
          </div>
          <div style={{ marginBottom: '10px' }}>
            <label>Adresse</label>
            <input type="text" value={addressLine1} onChange={(e) => setAddressLine1(e.target.value)} required style={{ width: '100%' }} />
          </div>
          <div style={{ display: 'flex', gap: '10px', marginBottom: '10px' }}>
            <div>
              <label>Code postal</label>
              <input type="text" value={postalCode} onChange={(e) => setPostalCode(e.target.value)} required />
            </div>
            <div>
              <label>Ville</label>
              <input type="text" value={city} onChange={(e) => setCity(e.target.value)} required />
            </div>
          </div>
          <button type="submit">Creer</button>
        </form>
      )}

      <table style={{ width: '100%', borderCollapse: 'collapse' }}>
        <thead>
          <tr style={{ borderBottom: '2px solid #e5e7eb' }}>
            <th style={{ textAlign: 'left', padding: '8px' }}>Raison sociale</th>
            <th style={{ textAlign: 'left', padding: '8px' }}>SIREN</th>
            <th style={{ textAlign: 'left', padding: '8px' }}>Ville</th>
          </tr>
        </thead>
        <tbody>
          {clients.map((c) => (
            <tr key={c.id} style={{ borderBottom: '1px solid #f3f4f6' }}>
              <td style={{ padding: '8px' }}>{c.name}</td>
              <td style={{ padding: '8px' }}>{c.siren || '-'}</td>
              <td style={{ padding: '8px' }}>{c.city}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
