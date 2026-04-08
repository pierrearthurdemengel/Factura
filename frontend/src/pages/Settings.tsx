import { useEffect, useState, useRef } from 'react';
import { useAuth } from '../context/AuthContext';
import { getCompany, updateCompany, getStripePortalUrl, getInvoices, type Company, type Invoice } from '../api/factura';
import './AppLayout.css';

// Onglets disponibles dans les parametres
type SettingsTab = 'company' | 'customization' | 'reminders' | 'integrations' | 'factoring' | 'billing';

export default function Settings() {
  const { logout } = useAuth();
  const [company, setCompany] = useState<Company | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');
  const [activeTab, setActiveTab] = useState<SettingsTab>('company');

  // Personnalisation
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [primaryColor, setPrimaryColor] = useState('#2563eb');
  const [secondaryColor, setSecondaryColor] = useState('#1e40af');
  const [footerText, setFooterText] = useState('');
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Relances
  const [remindersEnabled, setRemindersEnabled] = useState(true);
  const [reminderDelays, setReminderDelays] = useState({ first: '7', second: '30', formal: '60' });

  // Facturation / Billing
  const [billingInvoices, setBillingInvoices] = useState<Invoice[]>([]);
  const [currentPlan, setCurrentPlan] = useState<'free' | 'pro' | 'success'>('free');

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

  // Gestion de l'upload du logo
  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      setError('Le logo ne doit pas depasser 2 Mo.');
      return;
    }
    const reader = new FileReader();
    reader.onload = () => setLogoPreview(reader.result as string);
    reader.readAsDataURL(file);
  };

  // Onglets de navigation
  const tabs: { key: SettingsTab; label: string }[] = [
    { key: 'company', label: 'Entreprise' },
    { key: 'customization', label: 'Personnalisation' },
    { key: 'reminders', label: 'Relances' },
    { key: 'integrations', label: 'Integrations' },
    { key: 'factoring', label: 'Affacturage' },
    { key: 'billing', label: 'Facturation' },
  ];

  // Charger les factures pour l'onglet facturation
  useEffect(() => {
    if (activeTab === 'billing') {
      getInvoices().then(res => setBillingInvoices(res.data['hydra:member'])).catch(() => setBillingInvoices([]));
    }
  }, [activeTab]);

  // Calcul du montant facture cette annee
  const currentYear = new Date().getFullYear();
  const yearInvoices = billingInvoices.filter(inv => new Date(inv.issueDate).getFullYear() === currentYear);
  const yearTotal = yearInvoices.reduce((s, inv) => s + parseFloat(inv.totalExcludingTax), 0);
  // Estimation des frais selon le plan
  const estimatedFees = currentPlan === 'free' ? 0 : currentPlan === 'pro' ? 14.90 * 12 : Math.max(29, yearTotal * 0.001);

  return (
    <div className="app-container">
      <h1 className="app-page-title">Parametres</h1>

      {/* Onglets */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem', overflowX: 'auto' }}>
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            style={{
              padding: '0.5rem 1rem', border: 'none', background: activeTab === tab.key ? 'var(--accent)' : 'transparent',
              color: activeTab === tab.key ? '#fff' : 'var(--text)', borderRadius: '6px', cursor: 'pointer',
              fontWeight: activeTab === tab.key ? 600 : 400, fontSize: '0.9rem', whiteSpace: 'nowrap',
            }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {message && <div style={{ color: '#22c55e', padding: '1rem', background: 'rgba(34,197,94,0.1)', borderRadius: '6px', marginBottom: '1rem' }}>{message}</div>}
      {error && <div style={{ color: '#ef4444', padding: '1rem', background: 'rgba(239,68,68,0.1)', borderRadius: '6px', marginBottom: '1rem' }}>{error}</div>}

      {/* Onglet Entreprise */}
      {activeTab === 'company' && (
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

      <div style={{ marginTop: '3rem', paddingTop: '2rem', borderTop: '1px solid var(--border)' }}>
        <h2 className="app-section-title" style={{ marginTop: 0 }}>Abonnement</h2>
        <p style={{ marginBottom: '1rem', color: 'var(--text-h)' }}>Plan actuel : <strong>Gratuit</strong> (30 factures/mois)</p>
        <p style={{ color: 'var(--text)', fontSize: '0.95rem', marginBottom: '1.5rem', lineHeight: 1.5 }}>
          Le plan Pro (14,90 EUR/mois HT) offre des factures illimitees et l'acces aux exports avances.
        </p>
        <button
          type="button"
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
        <button type="button" onClick={logout} className="app-btn-outline-danger">
          Se deconnecter
        </button>
      </div>
      </form>
      )}

      {/* Onglet Personnalisation */}
      {activeTab === 'customization' && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Personnalisation des factures</h2>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Personnalisez l'apparence de vos factures PDF avec votre logo, vos couleurs et votre pied de page.
          </p>

          {/* Upload logo */}
          <div className="app-form-group" style={{ marginBottom: '2rem' }}>
            <label className="app-label">Logo de l'entreprise</label>
            <div style={{ display: 'flex', alignItems: 'center', gap: '1rem', flexWrap: 'wrap' }}>
              <div
                onClick={() => fileInputRef.current?.click()}
                style={{
                  width: 120, height: 120, border: '2px dashed var(--border)', borderRadius: '8px',
                  display: 'flex', alignItems: 'center', justifyContent: 'center', cursor: 'pointer',
                  background: logoPreview ? `url(${logoPreview}) center/contain no-repeat` : 'var(--surface)',
                  color: 'var(--text)', fontSize: '0.8rem', textAlign: 'center',
                  transition: 'border-color 0.2s',
                }}
              >
                {!logoPreview && <span>Cliquez ou<br/>deposez un<br/>fichier</span>}
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                onChange={handleLogoUpload}
                style={{ display: 'none' }}
              />
              <div style={{ fontSize: '0.85rem', color: 'var(--text)' }}>
                <p style={{ margin: 0 }}>PNG, JPG ou SVG</p>
                <p style={{ margin: '0.25rem 0 0', opacity: 0.7 }}>Max 2 Mo, recommande : 400x200px</p>
                {logoPreview && (
                  <button
                    onClick={() => setLogoPreview(null)}
                    style={{ marginTop: '0.5rem', background: 'none', border: 'none', color: 'var(--danger)', cursor: 'pointer', padding: 0, fontSize: '0.85rem' }}
                  >
                    Supprimer le logo
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Couleurs */}
          <div className="app-form-row" style={{ marginBottom: '2rem' }}>
            <div className="app-form-group">
              <label className="app-label">Couleur primaire</label>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <input
                  type="color"
                  value={primaryColor}
                  onChange={(e) => setPrimaryColor(e.target.value)}
                  style={{ width: 40, height: 40, border: 'none', borderRadius: '6px', cursor: 'pointer', padding: 0 }}
                />
                <input
                  value={primaryColor}
                  onChange={(e) => setPrimaryColor(e.target.value)}
                  className="app-input"
                  style={{ width: 100 }}
                  maxLength={7}
                />
              </div>
            </div>
            <div className="app-form-group">
              <label className="app-label">Couleur secondaire</label>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <input
                  type="color"
                  value={secondaryColor}
                  onChange={(e) => setSecondaryColor(e.target.value)}
                  style={{ width: 40, height: 40, border: 'none', borderRadius: '6px', cursor: 'pointer', padding: 0 }}
                />
                <input
                  value={secondaryColor}
                  onChange={(e) => setSecondaryColor(e.target.value)}
                  className="app-input"
                  style={{ width: 100 }}
                  maxLength={7}
                />
              </div>
            </div>
          </div>

          {/* Pied de page */}
          <div className="app-form-group" style={{ marginBottom: '2rem' }}>
            <label className="app-label">Pied de page des factures</label>
            <textarea
              value={footerText}
              onChange={(e) => setFooterText(e.target.value)}
              className="app-input"
              rows={3}
              placeholder="Ex: Merci pour votre confiance. Paiement par virement sous 30 jours."
              style={{ resize: 'vertical' }}
            />
          </div>

          {/* Previsualisation */}
          <div style={{ marginBottom: '2rem' }}>
            <label className="app-label">Previsualisation</label>
            <div style={{
              border: '1px solid var(--border)', borderRadius: '8px', padding: '1.5rem',
              background: '#fff', color: '#000', maxWidth: 500,
            }}>
              <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '1rem' }}>
                <div>
                  {logoPreview ? (
                    <img src={logoPreview} alt="Logo" style={{ maxHeight: 50, maxWidth: 150 }} />
                  ) : (
                    <div style={{ width: 100, height: 30, background: '#e5e7eb', borderRadius: '4px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.7rem', color: '#9ca3af' }}>Logo</div>
                  )}
                </div>
                <div style={{ textAlign: 'right', fontSize: '0.8rem', color: '#6b7280' }}>
                  <div style={{ fontWeight: 700, color: primaryColor, fontSize: '1rem' }}>FACTURE</div>
                  <div>FA-2026-0001</div>
                  <div>08/04/2026</div>
                </div>
              </div>
              <div style={{ height: 1, background: primaryColor, margin: '0.5rem 0 1rem' }} />
              <div style={{ fontSize: '0.75rem', color: '#6b7280' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                  <span>Prestation de developpement</span>
                  <span style={{ fontWeight: 600, color: secondaryColor }}>1 200,00 EUR</span>
                </div>
              </div>
              {footerText && (
                <div style={{ marginTop: '1rem', paddingTop: '0.5rem', borderTop: '1px solid #e5e7eb', fontSize: '0.7rem', color: '#9ca3af' }}>
                  {footerText}
                </div>
              )}
            </div>
          </div>

          <button
            className="app-btn-primary"
            onClick={() => setMessage('Personnalisation enregistree.')}
          >
            Enregistrer la personnalisation
          </button>
        </div>
      )}

      {/* Onglet Relances */}
      {activeTab === 'reminders' && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Relances automatiques</h2>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Configurez les relances envoyees automatiquement lorsqu'une facture n'est pas payee a echeance.
          </p>

          {/* Activation */}
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '2rem' }}>
            <button
              onClick={() => setRemindersEnabled(!remindersEnabled)}
              style={{
                width: 44, height: 24, borderRadius: 12, border: 'none', cursor: 'pointer',
                background: remindersEnabled ? 'var(--accent)' : 'var(--border)',
                position: 'relative', transition: 'background 0.2s',
              }}
            >
              <span style={{
                width: 18, height: 18, borderRadius: '50%', background: '#fff', position: 'absolute',
                top: 3, left: remindersEnabled ? 23 : 3, transition: 'left 0.2s',
              }} />
            </button>
            <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>
              {remindersEnabled ? 'Relances activees' : 'Relances desactivees'}
            </span>
          </div>

          {remindersEnabled && (
            <>
              {/* Delais */}
              <div className="app-form-row" style={{ marginBottom: '2rem' }}>
                <div className="app-form-group">
                  <label className="app-label">1re relance (jours apres echeance)</label>
                  <input
                    type="number"
                    min="1"
                    max="90"
                    value={reminderDelays.first}
                    onChange={(e) => setReminderDelays({ ...reminderDelays, first: e.target.value })}
                    className="app-input"
                  />
                  <span style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>Ton amical</span>
                </div>
                <div className="app-form-group">
                  <label className="app-label">2e relance (jours)</label>
                  <input
                    type="number"
                    min="1"
                    max="90"
                    value={reminderDelays.second}
                    onChange={(e) => setReminderDelays({ ...reminderDelays, second: e.target.value })}
                    className="app-input"
                  />
                  <span style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>Ton ferme</span>
                </div>
                <div className="app-form-group">
                  <label className="app-label">Mise en demeure (jours)</label>
                  <input
                    type="number"
                    min="1"
                    max="180"
                    value={reminderDelays.formal}
                    onChange={(e) => setReminderDelays({ ...reminderDelays, formal: e.target.value })}
                    className="app-input"
                  />
                  <span style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>Ton formel</span>
                </div>
              </div>

              {/* Apercu chronologique */}
              <div style={{ marginBottom: '2rem' }}>
                <label className="app-label">Chronologie des relances</label>
                <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap', fontSize: '0.85rem' }}>
                  <span style={{ background: 'var(--accent-bg)', color: 'var(--accent)', padding: '4px 10px', borderRadius: '1rem', fontWeight: 600 }}>
                    Echeance
                  </span>
                  <span style={{ color: 'var(--text)' }}>→</span>
                  <span style={{ background: 'rgba(234, 179, 8, 0.15)', color: '#ca8a04', padding: '4px 10px', borderRadius: '1rem', fontWeight: 600 }}>
                    J+{reminderDelays.first} Relance 1
                  </span>
                  <span style={{ color: 'var(--text)' }}>→</span>
                  <span style={{ background: 'rgba(249, 115, 22, 0.15)', color: '#ea580c', padding: '4px 10px', borderRadius: '1rem', fontWeight: 600 }}>
                    J+{reminderDelays.second} Relance 2
                  </span>
                  <span style={{ color: 'var(--text)' }}>→</span>
                  <span style={{ background: 'rgba(239, 68, 68, 0.15)', color: '#dc2626', padding: '4px 10px', borderRadius: '1rem', fontWeight: 600 }}>
                    J+{reminderDelays.formal} Mise en demeure
                  </span>
                </div>
              </div>

              <button
                className="app-btn-primary"
                onClick={() => setMessage('Parametres de relance enregistres.')}
              >
                Enregistrer les relances
              </button>
            </>
          )}
        </div>
      )}

      {/* Onglet Integrations */}
      {activeTab === 'integrations' && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Integrations</h2>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Connectez Factura a vos outils et services externes.
          </p>

          {[
            { name: 'Chorus Pro', desc: 'Transmission de factures au secteur public', status: 'Connecte', connected: true },
            { name: 'Stripe', desc: 'Paiement en ligne et gestion d\'abonnement', status: 'Connecte', connected: true },
            { name: 'GoCardless', desc: 'Synchronisation bancaire (Open Banking)', status: 'A configurer', connected: false },
            { name: 'Zapier', desc: 'Automatisation avec 5000+ applications', status: 'Bientot disponible', connected: false },
            { name: 'Pennylane', desc: 'Export automatique des ecritures comptables', status: 'Bientot disponible', connected: false },
          ].map((integration) => (
            <div
              key={integration.name}
              className="app-card"
              style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.75rem' }}
            >
              <div>
                <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.95rem' }}>{integration.name}</div>
                <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>{integration.desc}</div>
              </div>
              <div style={{
                padding: '4px 12px', borderRadius: '1rem', fontSize: '0.8rem', fontWeight: 600,
                background: integration.connected ? 'rgba(34,197,94,0.1)' : 'rgba(156,163,175,0.1)',
                color: integration.connected ? '#22c55e' : 'var(--text)',
              }}>
                {integration.status}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Onglet Facturation */}
      {activeTab === 'billing' && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Tableau de bord facturation</h2>

          {/* Resume annuel */}
          <div className="app-card" style={{ padding: '1.5rem', marginBottom: '1.5rem', background: 'linear-gradient(135deg, var(--accent-bg) 0%, var(--surface) 100%)' }}>
            <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '0.5rem' }}>Cette annee ({currentYear})</div>
            <div style={{ display: 'flex', alignItems: 'baseline', gap: '0.5rem', flexWrap: 'wrap' }}>
              <span style={{ fontSize: '1.8rem', fontWeight: 700, color: 'var(--text-h)' }}>{yearTotal.toFixed(0)} EUR</span>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>factures HT</span>
              <span style={{ fontSize: '1.2rem', color: 'var(--text)', margin: '0 0.25rem' }}>→</span>
              <span style={{ fontSize: '1.4rem', fontWeight: 700, color: 'var(--accent)' }}>{estimatedFees.toFixed(2)} EUR</span>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>de frais {currentPlan === 'pro' ? 'annuels' : currentPlan === 'success' ? 'estimes' : ''}</span>
            </div>
            <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.5rem' }}>
              {yearInvoices.length} facture(s) emise(s) en {currentYear}
            </div>
          </div>

          {/* Selecteur de plan */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, color: 'var(--text-h)', marginBottom: '1rem' }}>Choisir un plan</h3>
          <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))', gap: '1rem', marginBottom: '2rem' }}>
            {[
              { key: 'free' as const, name: 'Gratuit', price: '0 EUR', desc: '30 factures/mois', features: ['Factures illimitees (30/mois)', 'Export PDF Factur-X', 'Support email'] },
              { key: 'pro' as const, name: 'Pro', price: '14,90 EUR/mois', desc: 'ou 149 EUR/an', features: ['Factures illimitees', 'Relances automatiques', 'Rapprochement bancaire', 'Support prioritaire'] },
              { key: 'success' as const, name: 'Succes', price: '0,1% du CA', desc: 'min 29 EUR/an', features: ['Tout le plan Pro', 'Affacturage', 'Expert-comptable', 'API et webhooks'] },
            ].map((plan) => (
              <div
                key={plan.key}
                onClick={() => setCurrentPlan(plan.key)}
                className="app-card"
                style={{
                  padding: '1.25rem', cursor: 'pointer', position: 'relative',
                  border: currentPlan === plan.key ? '2px solid var(--accent)' : '2px solid transparent',
                  transition: 'border-color 0.2s',
                }}
              >
                {currentPlan === plan.key && (
                  <div style={{ position: 'absolute', top: 8, right: 8, width: 20, height: 20, borderRadius: '50%', background: 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </div>
                )}
                <div style={{ fontWeight: 700, fontSize: '1.1rem', color: 'var(--text-h)', marginBottom: '0.25rem' }}>{plan.name}</div>
                <div style={{ fontWeight: 600, color: 'var(--accent)', marginBottom: '0.25rem' }}>{plan.price}</div>
                <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginBottom: '0.75rem' }}>{plan.desc}</div>
                <ul style={{ listStyle: 'none', padding: 0, margin: 0, fontSize: '0.8rem' }}>
                  {plan.features.map((f, i) => (
                    <li key={i} style={{ padding: '2px 0', color: 'var(--text-h)' }}>✓ {f}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          {/* Option paiement mensuel/annuel */}
          <div className="app-card" style={{ padding: '1rem', marginBottom: '1.5rem', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.75rem' }}>
            <div>
              <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.9rem' }}>Mode de paiement</div>
              <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Payez annuellement et economisez 2 mois</div>
            </div>
            <div style={{ display: 'flex', gap: '0.5rem' }}>
              <button className="app-btn-outline-danger" style={{ fontSize: '0.8rem', padding: '6px 14px' }}>Mensuel</button>
              <button className="app-btn-primary" style={{ fontSize: '0.8rem', padding: '6px 14px' }}>Annuel (-17%)</button>
            </div>
          </div>

          {/* Historique facturation */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, color: 'var(--text-h)', marginBottom: '1rem' }}>Historique de facturation</h3>
          <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text)' }}>
            <p style={{ fontSize: '0.9rem' }}>Aucune facture Factura emise pour le moment.</p>
            <p style={{ fontSize: '0.8rem', marginTop: '0.5rem' }}>Vos factures d'abonnement apparaitront ici.</p>
          </div>

          <button
            className="app-btn-primary"
            onClick={async () => {
              try {
                const res = await getStripePortalUrl();
                window.location.href = res.data.url;
              } catch {
                setError('Impossible d\'acceder au portail de facturation.');
              }
            }}
          >
            Gerer l'abonnement via Stripe
          </button>
        </div>
      )}

      {/* Onglet Affacturage */}
      {activeTab === 'factoring' && (
        <div style={{ maxWidth: 600 }}>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Affacturage</h2>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem', lineHeight: 1.6 }}>
            L'affacturage permet de recevoir le paiement de vos factures avant leur echeance,
            moyennant une commission. Vous pouvez activer ou desactiver cette fonctionnalite ici.
          </p>

          <div className="app-card" style={{ padding: '1.5rem', marginBottom: '1.5rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>Activer l'affacturage</span>
              <label style={{ position: 'relative', display: 'inline-block', width: 44, height: 24, cursor: 'pointer' }}>
                <input type="checkbox" defaultChecked style={{ opacity: 0, width: 0, height: 0 }} />
                <span style={{
                  position: 'absolute', inset: 0, borderRadius: '12px',
                  background: 'var(--accent)', transition: 'background 0.2s',
                }}>
                  <span style={{
                    position: 'absolute', left: '22px', top: '2px',
                    width: 20, height: 20, borderRadius: '50%', background: '#fff',
                    transition: 'left 0.2s', boxShadow: '0 1px 3px rgba(0,0,0,0.2)',
                  }} />
                </span>
              </label>
            </div>
            <p style={{ fontSize: '0.85rem', color: 'var(--text)', margin: 0 }}>
              Lorsque l'affacturage est active, un bouton "Recevoir le paiement maintenant" apparait
              sur les factures en attente de paiement. Commission standard : 5%.
            </p>
          </div>

          <h3 style={{ fontSize: '1rem', fontWeight: 600, color: 'var(--text-h)', marginBottom: '1rem' }}>Historique des financements</h3>
          <div style={{ textAlign: 'center', padding: '2rem', color: 'var(--text)' }}>
            <p style={{ fontSize: '0.9rem' }}>Aucun financement demande pour le moment.</p>
            <p style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.5rem' }}>
              Les demandes de financement et leur statut apparaitront ici.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
