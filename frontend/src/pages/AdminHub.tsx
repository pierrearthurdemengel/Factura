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
      const cleaned = searchQuery.replaceAll(/\s/g, '');
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
    <div className="app-container">
      <h1 className="app-page-title">Hub administratif</h1>
      <p className="app-desc">
        Accedez a tous les services administratifs depuis un point d'entree unique.
      </p>

      {/* Recherche INSEE */}
      <div className="app-card" style={{ marginBottom: '2rem' }}>
        <h2 className="app-subsection-title app-mt-0">
          Recherche INSEE Sirene
        </h2>
        <p className="app-card-text">
          Recherchez une entreprise par SIREN, SIRET ou denomination.
        </p>
        <div className="app-filter-bar">
          <input
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
            placeholder="Ex: 443061841 ou GOOGLE FRANCE"
            className="app-input"
          />
          <button onClick={handleSearch} disabled={searching} className="app-btn-primary">
            {searching ? 'Recherche...' : 'Rechercher'}
          </button>
        </div>

        {searchError && (
          <div className="app-alert app-alert--error">{searchError}</div>
        )}

        {searchResults.length > 0 && (
          <div className="app-list">
            {searchResults.map((r) => (
              <div key={r.siret || r.siren} className="app-list-item">
                <div className="app-list-item-info">
                  <div className="app-list-item-title">{r.denomination || 'Inconnu'}</div>
                  <div className="app-list-item-sub">
                    <span>SIREN : {r.siren}</span>
                    {r.siret && <span> &middot; SIRET : {r.siret}</span>}
                    {r.codeNaf && <span> &middot; NAF : {r.codeNaf}</span>}
                    {r.adresse && <span> &middot; {r.adresse}</span>}
                  </div>
                </div>
                <span className={`app-status-pill ${r.etatAdministratif === 'A' ? 'app-status-pill--connected' : 'app-status-pill--closed'}`}>
                  {r.etatAdministratif === 'A' ? 'Active' : 'Fermee'}
                </span>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Filtres par categorie */}
      <div className="app-pills">
        <button
          onClick={() => setCategoryFilter('')}
          className={`app-pill ${categoryFilter ? '' : 'app-pill--active'}`}
        >
          Tous
        </button>
        {categories.map((cat) => (
          <button
            key={cat}
            onClick={() => setCategoryFilter(cat)}
            className={`app-pill ${categoryFilter === cat ? 'app-pill--active' : ''}`}
          >
            {cat}
          </button>
        ))}
      </div>

      {/* Liste des services */}
      <div className="app-list">
        {filtered.map((service) => (
          <div key={service.id} className="app-list-item">
            <div className="app-list-item-info">
              <div className="app-list-item-title">
                {service.name}
                {' '}
                <span className={`app-status-pill ${service.status === 'integrated' ? 'app-status-pill--connected' : 'app-status-pill--muted'}`}>
                  {service.status === 'integrated' ? 'Integre' : 'Lien'}
                </span>
              </div>
              <div className="app-list-item-sub">
                {service.description}
              </div>
            </div>
            <a
              href={service.url}
              target="_blank"
              rel="noopener noreferrer"
              className="app-btn-outline"
            >
              Acceder
            </a>
          </div>
        ))}
      </div>
    </div>
  );
}
