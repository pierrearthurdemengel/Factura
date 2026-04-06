import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { getCompany, updateCompany, getStripePortalUrl, type Company } from '../api/factura';
import './AppLayout.css';

export default function Settings() {
  const { logout } = useAuth();
  const [company, setCompany] = useState<Company | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  const [form, setForm] = useState({
    name: '', siren: '', siret: '', vatNumber: '', legalForm: '', nafCode: '',
    addressLine1: '', addressLine2: '', postalCode: '', city: '', countryCode: 'FR',
    iban: '', bic: '', defaultPdp: '',
  });

  useEffect(() => {
    getCompany()
      .then((res) => {
        const c = res.data;
        setCompany(c);
        setForm({
          name: c.name || '', siren: c.siren || '', siret: c.siret || '',
          vatNumber: c.vatNumber || '', legalForm: c.legalForm || '',
          nafCode: c.nafCode || '', addressLine1: c.addressLine1 || '',
          addressLine2: c.addressLine2 || '', postalCode: c.postalCode || '',
          city: c.city || '', countryCode: c.countryCode || 'FR',
          iban: c.iban || '', bic: c.bic || '', defaultPdp: c.defaultPdp || '',
        });
      })
      .catch(() => setError('Impossible de charger les informations entreprise.'))
      .finally(() => setLoading(false));
  }, []);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!company) return;
    setSaving(true);
    setMessage('');
    setError('');

    try {
      await updateCompany(company.id, form as unknown as Partial<Company>);
      setMessage('Informations enregistrees.');
    } catch {
      setError('Erreur lors de la sauvegarde.');
    } finally {
      setSaving(false);
    }
  };

  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      <div className="app-skeleton app-skeleton-title" style={{ width: '30%', marginTop: '2rem' }} />
      <div className="app-form-row">
        <div className="app-skeleton app-skeleton-table-row" style={{ flex: 2 }} />
        <div className="app-skeleton app-skeleton-table-row" style={{ flex: 1 }} />
      </div>
      <div className="app-form-row">
        <div className="app-skeleton app-skeleton-table-row" />
        <div className="app-skeleton app-skeleton-table-row" />
      </div>
      <div className="app-skeleton app-skeleton-table-row" />
      <div className="app-skeleton" style={{ width: '150px', height: '40px', borderRadius: '6px', marginTop: '1rem' }} />
    </div>
  );

  return (
    <div className="app-container">
      <h1 className="app-page-title">Parametres</h1>

      {message && <div style={{ color: '#22c55e', padding: '1rem', background: 'rgba(34,197,94,0.1)', borderRadius: '6px', marginBottom: '1rem' }}>{message}</div>}
      {error && <div style={{ color: '#ef4444', padding: '1rem', background: 'rgba(239,68,68,0.1)', borderRadius: '6px', marginBottom: '1rem' }}>{error}</div>}

      <form onSubmit={handleSave}>
        <h2 className="app-section-title" style={{ marginTop: 0 }}>Informations entreprise</h2>

        <div className="app-form-row">
          <div className="app-form-group flex-2">
            <label className="app-label">Raison sociale</label>
            <input name="name" value={form.name} onChange={handleChange} required className="app-input" />
          </div>
          <div className="app-form-group">
            <label className="app-label">Forme juridique</label>
            <select name="legalForm" value={form.legalForm} onChange={handleChange} className="app-select">
              <option value="EI">EI</option>
              <option value="EIRL">EIRL</option>
              <option value="EURL">EURL</option>
              <option value="SARL">SARL</option>
              <option value="SAS">SAS</option>
              <option value="SASU">SASU</option>
              <option value="SA">SA</option>
              <option value="SNC">SNC</option>
              <option value="SCI">SCI</option>
            </select>
          </div>
        </div>

        <div className="app-form-row">
          <div className="app-form-group">
            <label className="app-label">SIREN</label>
            <input name="siren" value={form.siren} onChange={handleChange} required maxLength={9} className="app-input" />
          </div>
          <div className="app-form-group">
            <label className="app-label">SIRET</label>
            <input name="siret" value={form.siret} onChange={handleChange} maxLength={14} className="app-input" />
          </div>
        </div>

        <div className="app-form-row">
          <div className="app-form-group">
            <label className="app-label">N° TVA intracommunautaire</label>
            <input name="vatNumber" value={form.vatNumber} onChange={handleChange} maxLength={30} className="app-input" placeholder="FR00123456789" />
          </div>
          <div className="app-form-group">
            <label className="app-label">Code NAF</label>
            <input name="nafCode" value={form.nafCode} onChange={handleChange} maxLength={5} className="app-input" placeholder="6201Z" />
          </div>
        </div>

        <div className="app-form-group">
          <label className="app-label">Adresse (ligne 1)</label>
          <input name="addressLine1" value={form.addressLine1} onChange={handleChange} required className="app-input" />
        </div>

        <div className="app-form-group">
          <label className="app-label">Adresse (ligne 2)</label>
          <input name="addressLine2" value={form.addressLine2} onChange={handleChange} className="app-input" />
        </div>

        <div className="app-form-row">
          <div className="app-form-group">
            <label className="app-label">Code postal</label>
            <input name="postalCode" value={form.postalCode} onChange={handleChange} required maxLength={5} className="app-input" />
          </div>
          <div className="app-form-group flex-2">
            <label className="app-label">Ville</label>
            <input name="city" value={form.city} onChange={handleChange} required className="app-input" />
          </div>
          <div className="app-form-group">
            <label className="app-label">Pays</label>
            <input name="countryCode" value={form.countryCode} onChange={handleChange} maxLength={2} className="app-input" />
          </div>
        </div>

        <h3 className="app-section-title">Coordonnees bancaires</h3>

        <div className="app-form-row">
          <div className="app-form-group flex-2">
            <label className="app-label">IBAN</label>
            <input name="iban" value={form.iban} onChange={handleChange} maxLength={34} className="app-input" placeholder="FR76...185" />
          </div>
          <div className="app-form-group">
            <label className="app-label">BIC</label>
            <input name="bic" value={form.bic} onChange={handleChange} maxLength={11} className="app-input" placeholder="BNPAFRPPXXX" />
          </div>
        </div>

        <h2 className="app-section-title">Connexion PDP</h2>

        <div className="app-form-group">
          <label className="app-label">Plateforme de dematerialisation</label>
          <select name="defaultPdp" value={form.defaultPdp} onChange={handleChange} className="app-select">
            <option value="">Aucune (mode brouillon uniquement)</option>
            <option value="chorus_pro">Chorus Pro (B2G)</option>
          </select>
        </div>

        <div style={{ marginTop: '2rem' }}>
          <button type="submit" disabled={saving} className="app-btn-primary">
            {saving ? 'Enregistrement...' : 'Enregistrer'}
          </button>
        </div>
      </form>

      <div style={{ marginTop: '3rem', paddingTop: '2rem', borderTop: '1px solid var(--border)' }}>
        <h2 className="app-section-title" style={{ marginTop: 0 }}>Abonnement</h2>
        <p style={{ marginBottom: '1rem', color: 'var(--text-h)' }}>Plan actuel : <strong>Gratuit</strong> (30 factures/mois)</p>
        <p style={{ color: 'var(--text)', fontSize: '0.95rem', marginBottom: '1.5rem', lineHeight: 1.5 }}>
          Le plan Pro (14,90 EUR/mois HT) offre des factures illimitees et l'acces aux exports avances.
        </p>
        <button
          onClick={async () => {
            try {
              const res = await getStripePortalUrl();
              window.location.href = res.data.url;
            } catch {
              setError('Impossible d\'acceder au portail de facturation.');
            }
          }}
          className="app-btn-primary"
          style={{ background: 'var(--text-h)', color: 'var(--bg)' }}
        >
          Gerer mon abonnement
        </button>
      </div>

      <div style={{ marginTop: '3rem', paddingTop: '2rem', borderTop: '1px solid var(--border)' }}>
        <button onClick={logout} className="app-btn-outline-danger">
          Se deconnecter
        </button>
      </div>
    </div>
  );
}
