import { useEffect, useState } from 'react';
import { useAuth } from '../context/AuthContext';
import { getCompany, updateCompany, type Company } from '../api/factura';

// Page parametres : informations entreprise, PDP, abonnement.
export default function Settings() {
  const { logout } = useAuth();
  const [company, setCompany] = useState<Company | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  // Formulaire entreprise
  const [form, setForm] = useState({
    name: '',
    siren: '',
    siret: '',
    vatNumber: '',
    legalForm: '',
    nafCode: '',
    addressLine1: '',
    addressLine2: '',
    postalCode: '',
    city: '',
    countryCode: 'FR',
    iban: '',
    bic: '',
    defaultPdp: '',
  });

  useEffect(() => {
    getCompany()
      .then((res) => {
        const c = res.data;
        setCompany(c);
        setForm({
          name: c.name || '',
          siren: c.siren || '',
          siret: c.siret || '',
          vatNumber: c.vatNumber || '',
          legalForm: c.legalForm || '',
          nafCode: c.nafCode || '',
          addressLine1: c.addressLine1 || '',
          addressLine2: c.addressLine2 || '',
          postalCode: c.postalCode || '',
          city: c.city || '',
          countryCode: c.countryCode || 'FR',
          iban: c.iban || '',
          bic: c.bic || '',
          defaultPdp: c.defaultPdp || '',
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

  const inputStyle = { width: '100%', padding: '8px', boxSizing: 'border-box' as const };
  const fieldStyle = { marginBottom: '12px' };

  if (loading) return <p>Chargement...</p>;

  return (
    <div>
      <h1>Parametres</h1>

      {message && <p style={{ color: '#22c55e', marginBottom: '12px' }}>{message}</p>}
      {error && <p style={{ color: '#ef4444', marginBottom: '12px' }}>{error}</p>}

      <form onSubmit={handleSave}>
        <h2 style={{ marginTop: '20px' }}>Informations entreprise</h2>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 2 }}>
            <label>Raison sociale</label>
            <input name="name" value={form.name} onChange={handleChange} required style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Forme juridique</label>
            <select name="legalForm" value={form.legalForm} onChange={handleChange} style={inputStyle}>
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

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>SIREN</label>
            <input name="siren" value={form.siren} onChange={handleChange} required maxLength={9} style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>SIRET</label>
            <input name="siret" value={form.siret} onChange={handleChange} maxLength={14} style={inputStyle} />
          </div>
        </div>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>N° TVA intracommunautaire</label>
            <input name="vatNumber" value={form.vatNumber} onChange={handleChange} maxLength={30} style={inputStyle} placeholder="FR00123456789" />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Code NAF</label>
            <input name="nafCode" value={form.nafCode} onChange={handleChange} maxLength={5} style={inputStyle} placeholder="6201Z" />
          </div>
        </div>

        <div style={fieldStyle}>
          <label>Adresse (ligne 1)</label>
          <input name="addressLine1" value={form.addressLine1} onChange={handleChange} required style={inputStyle} />
        </div>

        <div style={fieldStyle}>
          <label>Adresse (ligne 2)</label>
          <input name="addressLine2" value={form.addressLine2} onChange={handleChange} style={inputStyle} />
        </div>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Code postal</label>
            <input name="postalCode" value={form.postalCode} onChange={handleChange} required maxLength={5} style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 2 }}>
            <label>Ville</label>
            <input name="city" value={form.city} onChange={handleChange} required style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Pays</label>
            <input name="countryCode" value={form.countryCode} onChange={handleChange} maxLength={2} style={inputStyle} />
          </div>
        </div>

        <h3 style={{ marginTop: '20px' }}>Coordonnees bancaires</h3>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 2 }}>
            <label>IBAN</label>
            <input name="iban" value={form.iban} onChange={handleChange} maxLength={34} style={inputStyle} placeholder="FR7630001007941234567890185" />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>BIC</label>
            <input name="bic" value={form.bic} onChange={handleChange} maxLength={11} style={inputStyle} placeholder="BNPAFRPPXXX" />
          </div>
        </div>

        <h2 style={{ marginTop: '30px' }}>Connexion PDP</h2>

        <div style={fieldStyle}>
          <label>Plateforme de dematerialisation</label>
          <select name="defaultPdp" value={form.defaultPdp} onChange={handleChange} style={inputStyle}>
            <option value="">Aucune (mode brouillon uniquement)</option>
            <option value="chorus_pro">Chorus Pro (B2G)</option>
          </select>
        </div>

        <button
          type="submit"
          disabled={saving}
          style={{ padding: '10px 24px', cursor: saving ? 'not-allowed' : 'pointer', marginTop: '16px' }}
        >
          {saving ? 'Enregistrement...' : 'Enregistrer'}
        </button>
      </form>

      <div style={{ marginTop: '40px', paddingTop: '20px', borderTop: '1px solid #e5e7eb' }}>
        <h2>Abonnement</h2>
        <p style={{ marginBottom: '12px' }}>Plan actuel : <strong>Gratuit</strong> (30 factures/mois)</p>
        <p style={{ color: '#6b7280', fontSize: '14px' }}>
          Le plan Pro (12 EUR/mois) offre des factures illimitees et l'acces aux exports avances.
        </p>
      </div>

      <div style={{ marginTop: '40px', paddingTop: '20px', borderTop: '1px solid #e5e7eb' }}>
        <button onClick={logout} style={{ color: '#ef4444', cursor: 'pointer', background: 'none', border: '1px solid #ef4444', padding: '8px 16px' }}>
          Se deconnecter
        </button>
      </div>
    </div>
  );
}
