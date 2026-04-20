import { useEffect, useState, useRef } from 'react';
import { useIntl } from 'react-intl';
import { useAuth } from '../context/AuthContext';
import api, { getCompany, updateCompany, getStripePortalUrl, getInvoices, type Company, type Invoice } from '../api/factura';
import './AppLayout.css';

// Onglets disponibles dans les parametres
type SettingsTab = 'company' | 'customization' | 'reminders' | 'integrations' | 'factoring' | 'billing';

export default function Settings() {
  const { logout } = useAuth();
  const intl = useIntl();
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

  // Charger les factures et le plan pour l'onglet facturation
  useEffect(() => {
    if (activeTab === 'billing') {
      getInvoices()
        .then(res => setBillingInvoices(res.data['hydra:member']))
        .catch(() => setBillingInvoices([]));
      api.get<{ plan: 'free' | 'pro' | 'success' }>('/subscription/current')
        .then(res => {
          if (res.data?.plan) setCurrentPlan(res.data.plan);
        })
        .catch(() => {/* Plan par defaut si endpoint non disponible */});
    }
  }, [activeTab]);

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
      <div className="app-skeleton app-skeleton-title" />
      <div className="app-form-row">
        <div className="app-skeleton app-skeleton-table-row flex-2" />
        <div className="app-skeleton app-skeleton-table-row" />
      </div>
      <div className="app-form-row">
        <div className="app-skeleton app-skeleton-table-row" />
        <div className="app-skeleton app-skeleton-table-row" />
      </div>
      <div className="app-skeleton app-skeleton-table-row" />
      <div className="app-skeleton" />
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
    { key: 'company', label: intl.formatMessage({ id: 'settings.company', defaultMessage: 'Entreprise' }) },
    { key: 'customization', label: intl.formatMessage({ id: 'settings.customization', defaultMessage: 'Personnalisation' }) },
    { key: 'reminders', label: intl.formatMessage({ id: 'settings.reminders', defaultMessage: 'Relances' }) },
    { key: 'integrations', label: intl.formatMessage({ id: 'settings.integrations', defaultMessage: 'Integrations' }) },
    { key: 'factoring', label: intl.formatMessage({ id: 'settings.factoring', defaultMessage: 'Affacturage' }) },
    { key: 'billing', label: intl.formatMessage({ id: 'settings.billing', defaultMessage: 'Facturation' }) },
  ];

  // Calcul du montant facture cette annee
  const currentYear = new Date().getFullYear();
  const yearInvoices = billingInvoices.filter(inv => new Date(inv.issueDate).getFullYear() === currentYear);
  const yearTotal = yearInvoices.reduce((s, inv) => s + parseFloat(inv.totalExcludingTax), 0);
  // Estimation des frais selon le plan
  const estimatedFees = currentPlan === 'free' ? 0 : currentPlan === 'pro' ? 14.90 * 12 : Math.max(29, yearTotal * 0.001);

  return (
    <div className="app-container">
      <h1 className="app-page-title">{intl.formatMessage({ id: 'settings.title', defaultMessage: 'Parametres' })}</h1>

      {/* Onglets */}
      <div className="app-tabs">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`app-tab ${activeTab === tab.key ? 'app-tab--active' : ''}`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {message && <div className="app-alert app-alert--success">{message}</div>}
      {error && <div className="app-alert app-alert--error">{error}</div>}

      {/* Onglet Entreprise */}
      {activeTab === 'company' && (
      <form onSubmit={handleSave}>
        <h2 className="app-section-title app-mt-0">Informations entreprise</h2>

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

        <div className="app-mt-2">
          <button type="submit" disabled={saving} className="app-btn-primary">
            {saving ? intl.formatMessage({ id: 'settings.saving', defaultMessage: 'Enregistrement...' }) : intl.formatMessage({ id: 'settings.save', defaultMessage: 'Enregistrer' })}
          </button>
        </div>

      <div className="app-section-separator">
        <h2 className="app-section-title app-mt-0">Abonnement</h2>
        <p className="app-plan-info">Plan actuel : <strong>Gratuit</strong> (30 factures/mois)</p>
        <p className="app-plan-detail">
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
          className="app-btn-secondary"
        >
          Gerer mon abonnement
        </button>
      </div>

      <div className="app-section-separator">
        <button type="button" onClick={() => { void logout(); }} className="app-btn-outline-danger">
          Se deconnecter
        </button>
      </div>
      </form>
      )}

      {/* Onglet Personnalisation */}
      {activeTab === 'customization' && (
        <div>
          <h2 className="app-section-title app-mt-0">Personnalisation des factures</h2>
          <p className="app-desc">
            Personnalisez l'apparence de vos factures PDF avec votre logo, vos couleurs et votre pied de page.
          </p>

          {/* Upload logo */}
          <div className="app-form-group app-mb-2">
            <label className="app-label">Logo de l'entreprise</label>
            <div className="app-logo-upload-row">
              <div
                onClick={() => fileInputRef.current?.click()}
                className="app-logo-dropzone"
                style={logoPreview ? { backgroundImage: `url(${logoPreview})` } : undefined}
              >
                {!logoPreview && <span>Cliquez ou<br/>deposez un<br/>fichier</span>}
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                onChange={handleLogoUpload}
                className="app-hidden"
              />
              <div className="app-logo-meta">
                <p>PNG, JPG ou SVG</p>
                <p>Max 2 Mo, recommande : 400x200px</p>
                {logoPreview && (
                  <button
                    onClick={() => setLogoPreview(null)}
                    className="app-logo-remove-btn"
                  >
                    Supprimer le logo
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Couleurs */}
          <div className="app-form-row app-mb-2">
            <div className="app-form-group">
              <label className="app-label">Couleur primaire</label>
              <div className="app-color-picker-row">
                <input
                  type="color"
                  value={primaryColor}
                  onChange={(e) => setPrimaryColor(e.target.value)}
                  className="app-color-swatch"
                />
                <input
                  value={primaryColor}
                  onChange={(e) => setPrimaryColor(e.target.value)}
                  className="app-input app-color-hex-input"
                  maxLength={7}
                />
              </div>
            </div>
            <div className="app-form-group">
              <label className="app-label">Couleur secondaire</label>
              <div className="app-color-picker-row">
                <input
                  type="color"
                  value={secondaryColor}
                  onChange={(e) => setSecondaryColor(e.target.value)}
                  className="app-color-swatch"
                />
                <input
                  value={secondaryColor}
                  onChange={(e) => setSecondaryColor(e.target.value)}
                  className="app-input app-color-hex-input"
                  maxLength={7}
                />
              </div>
            </div>
          </div>

          {/* Pied de page */}
          <div className="app-form-group app-mb-2">
            <label className="app-label">Pied de page des factures</label>
            <textarea
              value={footerText}
              onChange={(e) => setFooterText(e.target.value)}
              className="app-input"
              rows={3}
              placeholder="Ex: Merci pour votre confiance. Paiement par virement sous 30 jours."
            />
          </div>

          {/* Previsualisation */}
          <div className="app-mb-2">
            <label className="app-label">Previsualisation</label>
            <div className="app-invoice-preview">
              <div className="app-invoice-preview-header">
                <div>
                  {logoPreview ? (
                    <img src={logoPreview} alt="Logo" className="app-logo-preview-img" />
                  ) : (
                    <div className="app-invoice-preview-logo-placeholder">Logo</div>
                  )}
                </div>
                <div className="app-invoice-preview-right">
                  <div className="app-invoice-preview-title" style={{ color: primaryColor }}>FACTURE</div>
                  <div>FA-2026-0001</div>
                  <div>08/04/2026</div>
                </div>
              </div>
              <div className="app-invoice-preview-separator" style={{ background: primaryColor }} />
              <div className="app-invoice-preview-line">
                <span>Prestation de developpement</span>
                <span className="app-invoice-preview-amount" style={{ color: secondaryColor }}>1 200,00 EUR</span>
              </div>
              {footerText && (
                <div className="app-invoice-preview-footer">
                  {footerText}
                </div>
              )}
            </div>
          </div>

          <button
            className="app-btn-primary"
            disabled={saving}
            onClick={async () => {
              if (!company) return;
              setSaving(true);
              setMessage('');
              setError('');
              try {
                await updateCompany(company.id, {
                  primaryColor,
                  secondaryColor,
                  footerText,
                } as unknown as Partial<Company>);

                // Upload du logo si present
                if (logoPreview && logoPreview.startsWith('data:')) {
                  const blob = await fetch(logoPreview).then(r => r.blob());
                  const formData = new FormData();
                  formData.append('logo', blob, 'logo.png');
                  await api.post(`/companies/${company.id}/logo`, formData, {
                    headers: { 'Content-Type': 'multipart/form-data' },
                  });
                }
                setMessage('Personnalisation enregistree.');
              } catch {
                setError('Erreur lors de la sauvegarde de la personnalisation.');
              } finally {
                setSaving(false);
              }
            }}
          >
            {saving ? intl.formatMessage({ id: 'settings.saving', defaultMessage: 'Enregistrement...' }) : intl.formatMessage({ id: 'settings.save', defaultMessage: 'Enregistrer' })}
          </button>
        </div>
      )}

      {/* Onglet Relances */}
      {activeTab === 'reminders' && (
        <div>
          <h2 className="app-section-title app-mt-0">Relances automatiques</h2>
          <p className="app-desc">
            Configurez les relances envoyees automatiquement lorsqu'une facture n'est pas payee a echeance.
          </p>

          {/* Activation */}
          <div className="app-toggle-row app-mb-2">
            <button
              onClick={() => setRemindersEnabled(!remindersEnabled)}
              className={`app-toggle ${remindersEnabled ? 'app-toggle--on' : ''}`}
            >
              <span className="app-toggle-knob" />
            </button>
            <span className="app-toggle-label">
              {remindersEnabled ? 'Relances activees' : 'Relances desactivees'}
            </span>
          </div>

          {remindersEnabled && (
            <>
              {/* Delais */}
              <div className="app-form-row app-mb-2">
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
                  <span className="app-reminder-hint">Ton amical</span>
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
                  <span className="app-reminder-hint">Ton ferme</span>
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
                  <span className="app-reminder-hint">Ton formel</span>
                </div>
              </div>

              {/* Apercu chronologique */}
              <div className="app-mb-2">
                <label className="app-label">Chronologie des relances</label>
                <div className="app-reminder-timeline">
                  <span className="app-reminder-pill app-reminder-pill--due">
                    Echeance
                  </span>
                  <span className="app-reminder-arrow">&rarr;</span>
                  <span className="app-reminder-pill app-reminder-pill--first">
                    J+{reminderDelays.first} Relance 1
                  </span>
                  <span className="app-reminder-arrow">&rarr;</span>
                  <span className="app-reminder-pill app-reminder-pill--second">
                    J+{reminderDelays.second} Relance 2
                  </span>
                  <span className="app-reminder-arrow">&rarr;</span>
                  <span className="app-reminder-pill app-reminder-pill--formal">
                    J+{reminderDelays.formal} Mise en demeure
                  </span>
                </div>
              </div>

              <button
                className="app-btn-primary"
                disabled={saving}
                onClick={async () => {
                  if (!company) return;
                  setSaving(true);
                  setMessage('');
                  setError('');
                  try {
                    await api.put(`/companies/${company.id}/reminders`, {
                      enabled: remindersEnabled,
                      firstDelay: parseInt(reminderDelays.first),
                      secondDelay: parseInt(reminderDelays.second),
                      formalDelay: parseInt(reminderDelays.formal),
                    });
                    setMessage('Parametres de relance enregistres.');
                  } catch {
                    setError('Erreur lors de la sauvegarde des relances.');
                  } finally {
                    setSaving(false);
                  }
                }}
              >
                {saving ? intl.formatMessage({ id: 'settings.saving', defaultMessage: 'Enregistrement...' }) : intl.formatMessage({ id: 'settings.save', defaultMessage: 'Enregistrer' })}
              </button>
            </>
          )}
        </div>
      )}

      {/* Onglet Integrations */}
      {activeTab === 'integrations' && (
        <div>
          <h2 className="app-section-title app-mt-0">Integrations</h2>
          <p className="app-desc">
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
              className="app-integration-card"
            >
              <div className="app-integration-card-info">
                <div className="app-integration-card-name">{integration.name}</div>
                <div className="app-integration-card-desc">{integration.desc}</div>
              </div>
              <div className={`app-status-pill ${integration.connected ? 'app-status-pill--connected' : 'app-status-pill--muted'}`}>
                {integration.status}
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Onglet Facturation */}
      {activeTab === 'billing' && (
        <div>
          <h2 className="app-section-title app-mt-0">Tableau de bord facturation</h2>

          {/* Resume annuel */}
          <div className="app-card app-billing-summary">
            <div className="app-billing-summary-label">Cette annee ({currentYear})</div>
            <div className="app-billing-summary-values">
              <span className="app-billing-summary-total">{yearTotal.toFixed(0)} EUR</span>
              <span className="app-billing-summary-unit">factures HT</span>
              <span className="app-billing-summary-arrow">&rarr;</span>
              <span className="app-billing-summary-fees">{estimatedFees.toFixed(2)} EUR</span>
              <span className="app-billing-summary-unit">de frais {currentPlan === 'pro' ? 'annuels' : currentPlan === 'success' ? 'estimes' : ''}</span>
            </div>
            <div className="app-billing-summary-count">
              {yearInvoices.length} facture(s) emise(s) en {currentYear}
            </div>
          </div>

          {/* Selecteur de plan */}
          <h3 className="app-subsection-title">Choisir un plan</h3>
          <div className="app-kpi-grid app-mb-2">
            {[
              { key: 'free' as const, name: 'Gratuit', price: '0 EUR', desc: '30 factures/mois', features: ['Factures illimitees (30/mois)', 'Export PDF Factur-X', 'Support email'] },
              { key: 'pro' as const, name: 'Pro', price: '14,90 EUR/mois', desc: 'ou 149 EUR/an', features: ['Factures illimitees', 'Relances automatiques', 'Rapprochement bancaire', 'Support prioritaire'] },
              { key: 'success' as const, name: 'Succes', price: '0,1% du CA', desc: 'min 29 EUR/an', features: ['Tout le plan Pro', 'Affacturage', 'Expert-comptable', 'API et webhooks'] },
            ].map((plan) => (
              <div
                key={plan.key}
                onClick={() => setCurrentPlan(plan.key)}
                className={`app-card app-plan-card ${currentPlan === plan.key ? 'app-plan-card--selected' : ''}`}
              >
                {currentPlan === plan.key && (
                  <div className="app-plan-card-check">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3"><polyline points="20 6 9 17 4 12"></polyline></svg>
                  </div>
                )}
                <div className="app-plan-card-name">{plan.name}</div>
                <div className="app-plan-card-price">{plan.price}</div>
                <div className="app-plan-card-desc">{plan.desc}</div>
                <ul className="app-plan-card-features">
                  {plan.features.map((f, i) => (
                    <li key={i}>&#10003; {f}</li>
                  ))}
                </ul>
              </div>
            ))}
          </div>

          {/* Option paiement mensuel/annuel */}
          <div className="app-card app-payment-mode-row">
            <div>
              <div className="app-payment-mode-info-title">Mode de paiement</div>
              <div className="app-payment-mode-info-desc">Payez annuellement et economisez 2 mois</div>
            </div>
            <div className="app-payment-mode-buttons">
              <button className="app-btn-outline-danger app-btn-compact">Mensuel</button>
              <button className="app-btn-primary app-btn-compact">Annuel (-17%)</button>
            </div>
          </div>

          {/* Historique facturation */}
          <h3 className="app-subsection-title">Historique de facturation</h3>
          <div className="app-empty-history">
            <p>Aucune facture Factura emise pour le moment.</p>
            <p>Vos factures d'abonnement apparaitront ici.</p>
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
        <div className="app-factoring-container">
          <h2 className="app-section-title app-mt-0">Affacturage</h2>
          <p className="app-desc">
            L'affacturage permet de recevoir le paiement de vos factures avant leur echeance,
            moyennant une commission. Vous pouvez activer ou desactiver cette fonctionnalite ici.
          </p>

          <div className="app-card app-mb-2">
            <div className="app-toggle-row app-mb-2">
              <span className="app-toggle-label">Activer l'affacturage</span>
              <button
                type="button"
                className="app-toggle app-toggle--on"
                aria-pressed="true"
              >
                <span className="app-toggle-knob" />
              </button>
            </div>
            <p className="app-factoring-desc">
              Lorsque l'affacturage est active, un bouton "Recevoir le paiement maintenant" apparait
              sur les factures en attente de paiement. Commission standard : 5%.
            </p>
          </div>

          <h3 className="app-subsection-title">Historique des financements</h3>
          <div className="app-empty-history">
            <p>Aucun financement demande pour le moment.</p>
            <p>
              Les demandes de financement et leur statut apparaitront ici.
            </p>
          </div>
        </div>
      )}
    </div>
  );
}
