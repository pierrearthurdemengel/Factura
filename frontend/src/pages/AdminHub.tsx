import { useState } from 'react';
import api from '../api/factura';
import './AppLayout.css';

// Services administratifs disponibles
const services = [
  {
    id: 'urssaf', name: 'URSSAF', category: 'Social',
    description: 'Calcul et simulation des cotisations sociales auto-entrepreneur',
    url: 'https://www.autoentrepreneur.urssaf.fr', status: 'integrated',
  },
  {
    id: 'dgfip_tva', name: 'DGFiP — TVA', category: 'Fiscal',
    description: 'Declaration et calcul de TVA (CA3, CA12)',
    url: 'https://www.impots.gouv.fr/professionnel', status: 'integrated',
  },
  {
    id: 'dgfip_fec', name: 'DGFiP — FEC', category: 'Fiscal',
    description: 'Export du Fichier des Ecritures Comptables',
    url: 'https://www.impots.gouv.fr/professionnel', status: 'integrated',
  },
  {
    id: 'insee_sirene', name: 'INSEE Sirene', category: 'Donnees',
    description: 'Recherche d\'entreprise par SIREN/SIRET',
    url: 'https://api.insee.fr', status: 'integrated',
  },
  {
    id: 'inpi', name: 'INPI — Guichet Unique', category: 'Juridique',
    description: 'Formalites de creation, modification et cessation d\'entreprise',
    url: 'https://procedures.inpi.fr', status: 'link_only',
  },
  {
    id: 'chorus_pro', name: 'Chorus Pro', category: 'Fiscal',
    description: 'Transmission de factures electroniques au secteur public',
    url: 'https://chorus-pro.gouv.fr', status: 'integrated',
  },
  {
    id: 'cfe', name: 'CFE — Cotisation Fonciere', category: 'Fiscal',
    description: 'Informations sur la Cotisation Fonciere des Entreprises',
    url: 'https://www.impots.gouv.fr/professionnel', status: 'link_only',
  },
];

// Recherche INSEE
interface InseeResult {
  siren: string;
  denomination: string;
  siret: string;
  codeNaf: string;
  adresse: string;
  etatAdministratif: string;
}

export default function AdminHub() {
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<InseeResult[]>([]);
  const [searching, setSearching] = useState(false);
  const [searchError, setSearchError] = useState('');
  const [categoryFilter, setCategoryFilter] = useState('');

  const categories = [...new Set(services.map((s) => s.category))];
  const filtered = categoryFilter ? services.filter((s) => s.category === categoryFilter) : services;

  // Recherche par SIREN dans l'API INSEE
  const handleSearch = async () => {
    if (!searchQuery.trim()) return;
    setSearching(true);
    setSearchError('');
    setSearchResults([]);

    try {
      // Si c'est un SIREN (9 chiffres), recherche directe
      const cleaned = searchQuery.replace(/\s/g, '');
      if (/^\d{9}$/.test(cleaned)) {
        const res = await api.get(`/admin-hub/insee/siren/${cleaned}`);
        setSearchResults([res.data]);
      } else if (/^\d{14}$/.test(cleaned)) {
        const res = await api.get(`/admin-hub/insee/siret/${cleaned}`);
        setSearchResults([res.data]);
      } else {
        const res = await api.get('/admin-hub/insee/search', { params: { q: searchQuery, limit: 10 } });
        setSearchResults(res.data.results || []);
      }
    } catch {
      setSearchError('Recherche impossible. Verifiez votre connexion ou le format du SIREN/SIRET.');
    } finally {
      setSearching(false);
    }
  };

  return (
    <div className="app-container" style={{ textAlign: 'left' }}>
      <h1 className="app-page-title">Hub administratif</h1>
      <p style={{ color: 'var(--text)', marginBottom: '2rem', maxWidth: 650, lineHeight: 1.6 }}>
        Accedez a tous les services administratifs depuis un point d'entree unique.
      </p>

      {/* Recherche INSEE */}
      <div className="app-card" style={{ marginBottom: '2rem', padding: '1.5rem' }}>
        <h2 style={{ margin: '0 0 0.5rem', color: 'var(--text-h)', fontSize: '1rem' }}>
          Recherche INSEE Sirene
        </h2>
        <p style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem' }}>
          Recherchez une entreprise par SIREN, SIRET ou denomination.
        </p>
        <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
          <input
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            placeholder="Ex: 443061841 ou GOOGLE FRANCE"
            className="app-input"
            style={{ flex: 1, minWidth: 200 }}
          />
          <button onClick={handleSearch} disabled={searching} className="app-btn-primary">
            {searching ? 'Recherche...' : 'Rechercher'}
          </button>
        </div>

        {searchError && (
          <div style={{ marginTop: '0.75rem', color: '#ef4444', fontSize: '0.85rem' }}>{searchError}</div>
        )}

        {searchResults.length > 0 && (
          <div style={{ marginTop: '1rem', display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            {searchResults.map((r) => (
              <div key={r.siret || r.siren} style={{
                padding: '0.75rem', background: 'var(--surface)', borderRadius: '6px',
                border: '1px solid var(--border)', fontSize: '0.9rem',
              }}>
                <div style={{ fontWeight: 600, color: 'var(--text-h)' }}>{r.denomination || 'Inconnu'}</div>
                <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap', marginTop: '0.25rem', fontSize: '0.8rem', color: 'var(--text)' }}>
                  <span>SIREN : {r.siren}</span>
                  {r.siret && <span>SIRET : {r.siret}</span>}
                  {r.codeNaf && <span>NAF : {r.codeNaf}</span>}
                  {r.adresse && <span>{r.adresse}</span>}
                  <span style={{
                    color: r.etatAdministratif === 'A' ? '#22c55e' : '#ef4444',
                    fontWeight: 600,
                  }}>
                    {r.etatAdministratif === 'A' ? 'Active' : 'Fermee'}
                  </span>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Filtres par categorie */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', flexWrap: 'wrap' }}>
        <button
          onClick={() => setCategoryFilter('')}
          style={{
            padding: '4px 12px', borderRadius: '1rem', border: 'none', cursor: 'pointer',
            background: !categoryFilter ? 'var(--accent)' : 'var(--surface)',
            color: !categoryFilter ? '#fff' : 'var(--text)', fontSize: '0.85rem',
          }}
        >
          Tous
        </button>
        {categories.map((cat) => (
          <button
            key={cat}
            onClick={() => setCategoryFilter(cat)}
            style={{
              padding: '4px 12px', borderRadius: '1rem', border: 'none', cursor: 'pointer',
              background: categoryFilter === cat ? 'var(--accent)' : 'var(--surface)',
              color: categoryFilter === cat ? '#fff' : 'var(--text)', fontSize: '0.85rem',
            }}
          >
            {cat}
          </button>
        ))}
      </div>

      {/* Liste des services */}
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
        {filtered.map((service) => (
          <div key={service.id} className="app-card" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem' }}>
            <div style={{ flex: 1, minWidth: 200 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>{service.name}</span>
                <span style={{
                  padding: '2px 8px', borderRadius: '1rem', fontSize: '0.7rem', fontWeight: 600,
                  background: service.status === 'integrated' ? 'rgba(34,197,94,0.1)' : 'rgba(156,163,175,0.1)',
                  color: service.status === 'integrated' ? '#22c55e' : '#9ca3af',
                }}>
                  {service.status === 'integrated' ? 'Integre' : 'Lien'}
                </span>
              </div>
              <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>
                {service.description}
              </div>
            </div>
            <a
              href={service.url}
              target="_blank"
              rel="noopener noreferrer"
              className="app-btn-outline"
              style={{ textDecoration: 'none', fontSize: '0.85rem', whiteSpace: 'nowrap' }}
            >
              Acceder
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}
