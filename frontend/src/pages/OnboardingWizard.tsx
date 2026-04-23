import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import api, { updateCompany, getCompany } from '../api/factura';
import './AppLayout.css';

// Formes juridiques disponibles pour l'etape 1
interface LegalFormOption {
  value: string;
  label: string;
  description: string;
}

const LEGAL_FORMS: LegalFormOption[] = [
  { value: 'AE', label: 'Auto-entrepreneur', description: 'Micro-entreprise, ideal pour demarrer' },
  { value: 'EI', label: 'EI', description: 'Entreprise individuelle au reel' },
  { value: 'EURL', label: 'EURL', description: 'Societe unipersonnelle a responsabilite limitee' },
  { value: 'SASU', label: 'SASU', description: 'Societe par actions simplifiee unipersonnelle' },
  { value: 'SARL', label: 'SARL', description: 'Societe a responsabilite limitee' },
  { value: 'SAS', label: 'SAS', description: 'Societe par actions simplifiee' },
  { value: 'SA', label: 'SA', description: 'Societe anonyme' },
  { value: 'SNC', label: 'SNC', description: 'Societe en nom collectif' },
  { value: 'SCI', label: 'SCI', description: 'Societe civile immobiliere' },
];

// Regimes de TVA
type VatRegime = 'franchise' | 'reel_simplifie' | 'reel_normal';

interface VatRegimeOption {
  value: VatRegime;
  label: string;
  description: string;
}

const VAT_REGIMES: VatRegimeOption[] = [
  {
    value: 'franchise',
    label: 'Franchise en base de TVA',
    description: 'Pas de TVA facturee ni collectee (art. 293B du CGI). Adapte aux auto-entrepreneurs et petites structures.',
  },
  {
    value: 'reel_simplifie',
    label: 'Reel simplifie',
    description: 'Declaration annuelle avec 2 acomptes semestriels. Pour un CA entre 36 800 et 254 000 € (services).',
  },
  {
    value: 'reel_normal',
    label: 'Reel normal',
    description: 'Declaration mensuelle ou trimestrielle de TVA. Obligatoire au-dela de 254 000 € de CA.',
  },
];

// Conditions de paiement par defaut
interface PaymentTermOption {
  value: string;
  label: string;
}

const PAYMENT_TERMS: PaymentTermOption[] = [
  { value: '30', label: '30 jours' },
  { value: '45', label: '45 jours' },
  { value: '60', label: '60 jours' },
  { value: '0', label: 'Paiement comptant' },
  { value: '15', label: '15 jours' },
];

// Nombre total d'etapes dans l'assistant
const TOTAL_STEPS = 4;

// Labels des etapes pour l'indicateur
const STEP_LABELS = [
  'Votre statut',
  'Informations fiscales',
  'Personnalisation',
  'Pret a facturer',
];

export default function OnboardingWizard() {
  const navigate = useNavigate();

  // Etape courante (indexee a partir de 0)
  const [currentStep, setCurrentStep] = useState(0);

  // Donnees de l'etape 1 : forme juridique
  const [selectedLegalForm, setSelectedLegalForm] = useState<string>('');

  // Donnees de l'etape 2 : regime TVA et dates d'exercice
  const [vatRegime, setVatRegime] = useState<VatRegime | ''>('');
  const [exerciseStartMonth, setExerciseStartMonth] = useState('01');
  const [exerciseEndMonth, setExerciseEndMonth] = useState('12');

  // Donnees de l'etape 3 : personnalisation
  const [logoPreview, setLogoPreview] = useState<string | null>(null);
  const [primaryColor, setPrimaryColor] = useState('#aa3bff');
  const [defaultPaymentTerms, setDefaultPaymentTerms] = useState('30');
  const fileInputRef = useRef<HTMLInputElement>(null);

  // Etat global
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  // Gestion de l'upload du logo
  const handleLogoUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
      setError('Le logo ne doit pas depasser 2 Mo.');
      return;
    }
    setError('');
    const reader = new FileReader();
    reader.onload = () => setLogoPreview(reader.result as string);
    reader.readAsDataURL(file);
  };

  // Validation de l'etape courante avant de passer a la suivante
  const canProceed = (): boolean => {
    switch (currentStep) {
      case 0:
        return selectedLegalForm !== '';
      case 1:
        return vatRegime !== '';
      case 2:
        return true;
      case 3:
        return true;
      default:
        return false;
    }
  };

  // Sauvegarde des preferences et redirection vers la creation de facture
  const handleFinish = async () => {
    setSaving(true);
    setError('');
    try {
      const res = await getCompany();
      const company = res.data;
      // Sauvegarder toutes les donnees collectees dans l'assistant
      const payload: Record<string, unknown> = {
        legalForm: selectedLegalForm,
      };
      // Les champs fiscaux et personnalisation sont sauvegardes dans les
      // preferences utilisateur cote serveur s'ils existent sur l'endpoint.
      // On les envoie tous : le backend ignorera ceux qu'il ne connait pas.
      if (vatRegime) payload.vatRegime = vatRegime;
      if (exerciseStartMonth) payload.exerciseStartMonth = exerciseStartMonth;
      if (exerciseEndMonth) payload.exerciseEndMonth = exerciseEndMonth;
      if (primaryColor) payload.primaryColor = primaryColor;
      if (defaultPaymentTerms) payload.defaultPaymentTerms = defaultPaymentTerms;
      await updateCompany(company.id, payload as Partial<import('../api/factura').Company>);

      // Upload du logo si present (base64 -> FormData)
      if (logoPreview) {
        try {
          const blob = await fetch(logoPreview).then(r => r.blob());
          const formData = new FormData();
          formData.append('logo', blob, 'logo.png');
          await api.post(`/companies/${company.id}/logo`, formData, {
            headers: { 'Content-Type': 'multipart/form-data' },
          });
        } catch {
          // Le logo est optionnel, on continue meme si l'upload echoue
        }
      }
      navigate('/invoices/new');
    } catch {
      setError('Erreur lors de la sauvegarde. Veuillez reessayer.');
    } finally {
      setSaving(false);
    }
  };

  // Navigation entre les etapes
  const goNext = () => {
    if (currentStep < TOTAL_STEPS - 1) {
      setCurrentStep(currentStep + 1);
      setError('');
    }
  };

  const goBack = () => {
    if (currentStep > 0) {
      setCurrentStep(currentStep - 1);
      setError('');
    }
  };

  // Nom du mois en francais
  const monthLabel = (month: string): string => {
    const months: Record<string, string> = {
      '01': 'Janvier', '02': 'Fevrier', '03': 'Mars', '04': 'Avril',
      '05': 'Mai', '06': 'Juin', '07': 'Juillet', '08': 'Aout',
      '09': 'Septembre', '10': 'Octobre', '11': 'Novembre', '12': 'Decembre',
    };
    return months[month] || month;
  };

  // Pourcentage de progression
  const progressPercent = ((currentStep + 1) / TOTAL_STEPS) * 100;

  return (
    <div className="app-container onboarding-container">
      {/* Titre de la page */}
      <h1 className="app-page-title onboarding-title">
        Configuration de votre compte
      </h1>

      {/* Barre de progression */}
      <div className="app-progress onboarding-progress-bar">
        <div
          className="app-progress-fill"
          style={{ width: `${progressPercent}%` }}
        />
      </div>

      {/* Indicateur d'etapes : cercles numerotes relies par une ligne */}
      <div className="onboarding-steps">
        {STEP_LABELS.map((label, index) => (
          <div key={label} className="onboarding-step">
            {/* Cercle numerote */}
            <div className="onboarding-step-col">
              <div className={`onboarding-step-circle${index <= currentStep ? ' onboarding-step-circle--active' : ''}`}>
                {index < currentStep ? (
                  // Coche pour les etapes completees
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                ) : (
                  index + 1
                )}
              </div>
              {/* Label de l'etape */}
              <span className={`onboarding-step-label${index === currentStep ? ' onboarding-step-label--active' : ''}${index < currentStep ? ' onboarding-step-label--done' : ''}`}>
                {label}
              </span>
            </div>
            {/* Ligne de connexion entre les cercles */}
            {index < TOTAL_STEPS - 1 && (
              <div className={`onboarding-step-connector${index < currentStep ? ' onboarding-step-connector--done' : ''}`} />
            )}
          </div>
        ))}
      </div>

      {/* Message d'erreur */}
      {error && (
        <div className="app-alert app-alert--error">
          {error}
        </div>
      )}

      {/* Etape 1 : Forme juridique */}
      {currentStep === 0 && (
        <div>
          <h2 className="app-section-title onboarding-title app-mt-0">
            Votre statut juridique
          </h2>
          <p className="app-desc onboarding-title">
            Selectionnez la forme juridique de votre entreprise. Ce choix peut etre modifie plus tard dans les parametres.
          </p>

          <div className="onboarding-legal-grid">
            {LEGAL_FORMS.map((form) => (
              <div
                key={form.value}
                onClick={() => setSelectedLegalForm(form.value)}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setSelectedLegalForm(form.value); } }}
                role="button"
                tabIndex={0}
                className={`app-card onboarding-selectable-card${selectedLegalForm === form.value ? ' onboarding-selectable-card--selected' : ''}`}
              >
                <div className="onboarding-card-header">
                  <span className="onboarding-card-label">
                    {form.label}
                  </span>
                  {selectedLegalForm === form.value && (
                    <div className="onboarding-card-check">
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </div>
                  )}
                </div>
                <span className="onboarding-card-desc">
                  {form.description}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Etape 2 : Informations fiscales */}
      {currentStep === 1 && (
        <div>
          <h2 className="app-section-title onboarding-title app-mt-0">
            Informations fiscales
          </h2>
          <p className="app-desc onboarding-title">
            Configurez votre regime de TVA et vos dates d'exercice comptable.
          </p>

          {/* Regime de TVA */}
          <div className="app-form-group app-mb-2">
            <span className="app-label">
              Regime de TVA
            </span>
            <div className="onboarding-vat-list">
              {VAT_REGIMES.map((regime) => (
                <div
                  key={regime.value}
                  onClick={() => setVatRegime(regime.value)}
                  onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); setVatRegime(regime.value); } }}
                  role="button"
                  tabIndex={0}
                  className={`app-card onboarding-radio-card${vatRegime === regime.value ? ' onboarding-radio-card--selected' : ''}`}
                >
                  {/* Indicateur radio */}
                  <div className={`onboarding-radio-dot${vatRegime === regime.value ? ' onboarding-radio-dot--selected' : ''}`} />
                  <div>
                    <div className="onboarding-radio-label">
                      {regime.label}
                    </div>
                    <div className="onboarding-radio-desc">
                      {regime.description}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Dates d'exercice comptable */}
          <div className="onboarding-exercise-group">
            <span className="app-label">
              Exercice comptable
            </span>
            <div className="app-form-row">
              <div className="app-form-group">
                <label htmlFor="onboarding-exercise-start" className="app-label">Debut</label>
                <select
                  id="onboarding-exercise-start"
                  value={exerciseStartMonth}
                  onChange={(e) => setExerciseStartMonth(e.target.value)}
                  className="app-select"
                >
                  {Array.from({ length: 12 }, (_, i) => {
                    const val = String(i + 1).padStart(2, '0');
                    return <option key={val} value={val}>{monthLabel(val)}</option>;
                  })}
                </select>
              </div>
              <div className="app-form-group">
                <label htmlFor="onboarding-exercise-end" className="app-label">Fin</label>
                <select
                  id="onboarding-exercise-end"
                  value={exerciseEndMonth}
                  onChange={(e) => setExerciseEndMonth(e.target.value)}
                  className="app-select"
                >
                  {Array.from({ length: 12 }, (_, i) => {
                    const val = String(i + 1).padStart(2, '0');
                    return <option key={val} value={val}>{monthLabel(val)}</option>;
                  })}
                </select>
              </div>
            </div>
            <p className="onboarding-hint">
              La plupart des entreprises utilisent l'annee civile (janvier a decembre).
            </p>
          </div>
        </div>
      )}

      {/* Etape 3 : Personnalisation */}
      {currentStep === 2 && (
        <div>
          <h2 className="app-section-title onboarding-title app-mt-0">
            Personnalisation
          </h2>
          <p className="app-desc onboarding-title">
            Ajoutez votre identite visuelle a vos factures.
          </p>

          {/* Upload du logo */}
          <div className="app-form-group app-mb-2">
            <span className="app-label">Logo de l'entreprise (optionnel)</span>
            <div className="onboarding-logo-row">
              <div
                onClick={() => fileInputRef.current?.click()}
                onKeyDown={(e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInputRef.current?.click(); } }}
                role="button"
                tabIndex={0}
                className="onboarding-logo-dropzone"
                style={logoPreview ? { background: `url(${logoPreview}) center/contain no-repeat` } : undefined}
              >
                {!logoPreview && <span>Cliquez pour<br />ajouter</span>}
              </div>
              <input
                ref={fileInputRef}
                type="file"
                accept="image/png,image/jpeg,image/svg+xml"
                onChange={handleLogoUpload}
                className="app-hidden"
              />
              <div className="onboarding-logo-meta">
                <p>PNG, JPG ou SVG</p>
                <p>Max 2 Mo, recommande : 400x200px</p>
                {logoPreview && (
                  <button
                    type="button"
                    onClick={() => setLogoPreview(null)}
                    className="app-btn-outline-danger app-btn-compact"
                  >
                    Supprimer
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Couleur primaire */}
          <div className="app-form-group app-mb-2">
            <label htmlFor="onboarding-primary-color" className="app-label">Couleur primaire des factures</label>
            <div className="onboarding-color-row">
              <input
                type="color"
                value={primaryColor}
                onChange={(e) => setPrimaryColor(e.target.value)}
                className="onboarding-color-swatch"
              />
              <input
                id="onboarding-primary-color"
                value={primaryColor}
                onChange={(e) => setPrimaryColor(e.target.value)}
                className="app-input onboarding-color-hex"
                maxLength={7}
              />
              {/* Previsualisation de la couleur */}
              <div
                className="onboarding-color-preview"
                style={{ background: primaryColor }}
              />
            </div>
          </div>

          {/* Conditions de paiement par defaut */}
          <div className="app-form-group">
            <label htmlFor="onboarding-payment-terms" className="app-label">Conditions de paiement par defaut</label>
            <select
              id="onboarding-payment-terms"
              value={defaultPaymentTerms}
              onChange={(e) => setDefaultPaymentTerms(e.target.value)}
              className="app-select"
            >
              {PAYMENT_TERMS.map((term) => (
                <option key={term.value} value={term.value}>{term.label}</option>
              ))}
            </select>
            <p className="onboarding-hint">
              Ce delai sera pre-rempli sur chaque nouvelle facture. Vous pourrez le modifier au cas par cas.
            </p>
          </div>
        </div>
      )}

      {/* Etape 4 : Recapitulatif */}
      {currentStep === 3 && (
        <div>
          <h2 className="app-section-title onboarding-title app-mt-0">
            Pret a facturer !
          </h2>
          <p className="app-desc onboarding-title">
            Voici un recapitulatif de vos choix. Vous pourrez tout modifier a tout moment dans les parametres.
          </p>

          {/* Recapitulatif sous forme de carte */}
          <div className="app-card onboarding-summary-card">
            {/* Ligne : forme juridique */}
            <div className="onboarding-summary-line">
              <span className="onboarding-summary-label">Forme juridique</span>
              <span className="onboarding-summary-value">
                {LEGAL_FORMS.find((f) => f.value === selectedLegalForm)?.label || '\u2014'}
              </span>
            </div>

            {/* Ligne : regime TVA */}
            <div className="onboarding-summary-line">
              <span className="onboarding-summary-label">Regime de TVA</span>
              <span className="onboarding-summary-value">
                {VAT_REGIMES.find((r) => r.value === vatRegime)?.label || '\u2014'}
              </span>
            </div>

            {/* Ligne : exercice comptable */}
            <div className="onboarding-summary-line">
              <span className="onboarding-summary-label">Exercice comptable</span>
              <span className="onboarding-summary-value">
                {monthLabel(exerciseStartMonth)} — {monthLabel(exerciseEndMonth)}
              </span>
            </div>

            {/* Ligne : logo */}
            <div className="onboarding-summary-line">
              <span className="onboarding-summary-label">Logo</span>
              {logoPreview ? (
                <img
                  src={logoPreview}
                  alt="Logo"
                  className="onboarding-summary-logo"
                />
              ) : (
                <span className="onboarding-summary-value">Non renseigne</span>
              )}
            </div>

            {/* Ligne : couleur primaire */}
            <div className="onboarding-summary-line">
              <span className="onboarding-summary-label">Couleur primaire</span>
              <div className="onboarding-summary-color-group">
                <div
                  className="onboarding-summary-color-swatch"
                  style={{ background: primaryColor }}
                />
                <span className="onboarding-summary-value">
                  {primaryColor}
                </span>
              </div>
            </div>

            {/* Ligne : conditions de paiement */}
            <div className="onboarding-summary-line">
              <span className="onboarding-summary-label">Conditions de paiement</span>
              <span className="onboarding-summary-value">
                {PAYMENT_TERMS.find((t) => t.value === defaultPaymentTerms)?.label || '\u2014'}
              </span>
            </div>
          </div>

          {/* Message de bienvenue */}
          <div className="onboarding-welcome-banner">
            <p className="onboarding-welcome-title">
              Tout est pret !
            </p>
            <p className="onboarding-welcome-desc">
              Cliquez sur le bouton ci-dessous pour creer votre premiere facture.
              Vous pouvez modifier ces parametres a tout moment depuis la page Parametres.
            </p>
          </div>
        </div>
      )}

      {/* Boutons de navigation */}
      <div className="onboarding-nav-footer">
        {/* Bouton Retour */}
        {currentStep > 0 ? (
          <button
            type="button"
            onClick={goBack}
            className="app-btn-outline onboarding-btn-back"
          >
            Retour
          </button>
        ) : (
          // Espace reserve pour maintenir l'alignement
          <div />
        )}

        {/* Bouton Suivant ou Terminer */}
        {currentStep < TOTAL_STEPS - 1 ? (
          <button
            type="button"
            onClick={goNext}
            disabled={!canProceed()}
            className="app-btn-primary"
          >
            Suivant
          </button>
        ) : (
          <button
            type="button"
            onClick={handleFinish}
            disabled={saving}
            className="app-btn-primary onboarding-btn-finish"
          >
            {saving ? 'Enregistrement...' : 'Creer ma premiere facture'}
          </button>
        )}
      </div>
    </div>
  );
}
