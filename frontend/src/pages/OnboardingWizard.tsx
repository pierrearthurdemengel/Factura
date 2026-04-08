import { useState, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { updateCompany, getCompany } from '../api/factura';
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
    description: 'Declaration annuelle avec 2 acomptes semestriels. Pour un CA entre 36 800 et 254 000 EUR (services).',
  },
  {
    value: 'reel_normal',
    label: 'Reel normal',
    description: 'Declaration mensuelle ou trimestrielle de TVA. Obligatoire au-dela de 254 000 EUR de CA.',
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
      await updateCompany(company.id, {
        legalForm: selectedLegalForm,
      });
      navigate('/invoices/new');
    } catch {
      setError('Erreur lors de la sauvegarde. Veuillez reessayer.');
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
    <div className="app-container" style={{ maxWidth: 800 }}>
      {/* Titre de la page */}
      <h1 className="app-page-title" style={{ textAlign: 'center' }}>
        Configuration de votre compte
      </h1>

      {/* Barre de progression */}
      <div style={{
        width: '100%',
        height: 6,
        background: 'var(--border)',
        borderRadius: 3,
        marginBottom: '2rem',
        overflow: 'hidden',
      }}>
        <div style={{
          width: `${progressPercent}%`,
          height: '100%',
          background: 'var(--accent)',
          borderRadius: 3,
          transition: 'width 0.3s ease',
        }} />
      </div>

      {/* Indicateur d'etapes : cercles numerotes relies par une ligne */}
      <div style={{
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        marginBottom: '2.5rem',
        gap: 0,
      }}>
        {STEP_LABELS.map((label, index) => (
          <div key={index} style={{ display: 'flex', alignItems: 'center' }}>
            {/* Cercle numerote */}
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', minWidth: 60 }}>
              <div style={{
                width: 36,
                height: 36,
                borderRadius: '50%',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                fontWeight: 700,
                fontSize: '0.9rem',
                background: index <= currentStep ? 'var(--accent)' : 'var(--border)',
                color: index <= currentStep ? '#fff' : 'var(--text)',
                transition: 'background 0.3s, color 0.3s',
                flexShrink: 0,
              }}>
                {index < currentStep ? (
                  // Coche pour les etapes completees
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3">
                    <polyline points="20 6 9 17 4 12" />
                  </svg>
                ) : (
                  index + 1
                )}
              </div>
              {/* Label de l'etape (masque sur mobile pour gagner de la place) */}
              <span style={{
                fontSize: '0.7rem',
                color: index <= currentStep ? 'var(--text-h)' : 'var(--text)',
                marginTop: '0.35rem',
                textAlign: 'center',
                fontWeight: index === currentStep ? 600 : 400,
                display: 'none',
              }}>
                {label}
              </span>
              {/* Label visible a partir de 640px (tablette+) */}
              <span style={{
                fontSize: '0.7rem',
                color: index <= currentStep ? 'var(--text-h)' : 'var(--text)',
                marginTop: '0.35rem',
                textAlign: 'center',
                fontWeight: index === currentStep ? 600 : 400,
              }}
                className="onboarding-step-label"
              >
                {label}
              </span>
            </div>
            {/* Ligne de connexion entre les cercles */}
            {index < TOTAL_STEPS - 1 && (
              <div style={{
                height: 2,
                width: 'clamp(20px, 8vw, 60px)',
                background: index < currentStep ? 'var(--accent)' : 'var(--border)',
                transition: 'background 0.3s',
                flexShrink: 0,
              }} />
            )}
          </div>
        ))}
      </div>

      {/* Message d'erreur */}
      {error && (
        <div style={{
          color: '#ef4444',
          padding: '1rem',
          background: 'rgba(239, 68, 68, 0.1)',
          borderRadius: '6px',
          marginBottom: '1rem',
          fontSize: '0.9rem',
        }}>
          {error}
        </div>
      )}

      {/* Etape 1 : Forme juridique */}
      {currentStep === 0 && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0, textAlign: 'center' }}>
            Votre statut juridique
          </h2>
          <p style={{ color: 'var(--text)', textAlign: 'center', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Selectionnez la forme juridique de votre entreprise. Ce choix peut etre modifie plus tard dans les parametres.
          </p>

          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))',
            gap: '0.75rem',
          }}>
            {LEGAL_FORMS.map((form) => (
              <div
                key={form.value}
                onClick={() => setSelectedLegalForm(form.value)}
                className="app-card"
                style={{
                  cursor: 'pointer',
                  border: selectedLegalForm === form.value
                    ? '2px solid var(--accent)'
                    : '2px solid var(--border)',
                  transition: 'border-color 0.2s, box-shadow 0.2s',
                  boxShadow: selectedLegalForm === form.value
                    ? '0 0 0 3px var(--accent-bg)'
                    : 'none',
                  padding: '1rem',
                  textAlign: 'left',
                }}
              >
                <div style={{
                  display: 'flex',
                  justifyContent: 'space-between',
                  alignItems: 'center',
                  marginBottom: '0.35rem',
                }}>
                  <span style={{
                    fontWeight: 700,
                    fontSize: '1.05rem',
                    color: 'var(--text-h)',
                  }}>
                    {form.label}
                  </span>
                  {selectedLegalForm === form.value && (
                    <div style={{
                      width: 22,
                      height: 22,
                      borderRadius: '50%',
                      background: 'var(--accent)',
                      display: 'flex',
                      alignItems: 'center',
                      justifyContent: 'center',
                      flexShrink: 0,
                    }}>
                      <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#fff" strokeWidth="3">
                        <polyline points="20 6 9 17 4 12" />
                      </svg>
                    </div>
                  )}
                </div>
                <span style={{ fontSize: '0.8rem', color: 'var(--text)', lineHeight: 1.4 }}>
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
          <h2 className="app-section-title" style={{ marginTop: 0, textAlign: 'center' }}>
            Informations fiscales
          </h2>
          <p style={{ color: 'var(--text)', textAlign: 'center', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Configurez votre regime de TVA et vos dates d'exercice comptable.
          </p>

          {/* Regime de TVA */}
          <div style={{ marginBottom: '2rem' }}>
            <label className="app-label" style={{ marginBottom: '0.75rem', display: 'block' }}>
              Regime de TVA
            </label>
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {VAT_REGIMES.map((regime) => (
                <div
                  key={regime.value}
                  onClick={() => setVatRegime(regime.value)}
                  className="app-card"
                  style={{
                    cursor: 'pointer',
                    border: vatRegime === regime.value
                      ? '2px solid var(--accent)'
                      : '2px solid var(--border)',
                    transition: 'border-color 0.2s, box-shadow 0.2s',
                    boxShadow: vatRegime === regime.value
                      ? '0 0 0 3px var(--accent-bg)'
                      : 'none',
                    padding: '1rem',
                    flexDirection: 'row',
                    alignItems: 'flex-start',
                    gap: '0.75rem',
                  }}
                >
                  {/* Indicateur radio */}
                  <div style={{
                    width: 20,
                    height: 20,
                    borderRadius: '50%',
                    border: vatRegime === regime.value
                      ? '6px solid var(--accent)'
                      : '2px solid var(--border)',
                    flexShrink: 0,
                    transition: 'border 0.2s',
                    marginTop: 2,
                  }} />
                  <div>
                    <div style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.25rem' }}>
                      {regime.label}
                    </div>
                    <div style={{ fontSize: '0.85rem', color: 'var(--text)', lineHeight: 1.5 }}>
                      {regime.description}
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </div>

          {/* Dates d'exercice comptable */}
          <div style={{ marginBottom: '1rem' }}>
            <label className="app-label" style={{ marginBottom: '0.75rem', display: 'block' }}>
              Exercice comptable
            </label>
            <div className="app-form-row">
              <div className="app-form-group">
                <label className="app-label" style={{ fontSize: '0.85rem' }}>Debut</label>
                <select
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
                <label className="app-label" style={{ fontSize: '0.85rem' }}>Fin</label>
                <select
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
            <p style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>
              La plupart des entreprises utilisent l'annee civile (janvier a decembre).
            </p>
          </div>
        </div>
      )}

      {/* Etape 3 : Personnalisation */}
      {currentStep === 2 && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0, textAlign: 'center' }}>
            Personnalisation
          </h2>
          <p style={{ color: 'var(--text)', textAlign: 'center', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Ajoutez votre identite visuelle a vos factures.
          </p>

          {/* Upload du logo */}
          <div className="app-form-group" style={{ marginBottom: '2rem' }}>
            <label className="app-label">Logo de l'entreprise (optionnel)</label>
            <div style={{
              display: 'flex',
              alignItems: 'center',
              gap: '1rem',
              flexWrap: 'wrap',
            }}>
              <div
                onClick={() => fileInputRef.current?.click()}
                style={{
                  width: 120,
                  height: 120,
                  border: '2px dashed var(--border)',
                  borderRadius: '8px',
                  display: 'flex',
                  alignItems: 'center',
                  justifyContent: 'center',
                  cursor: 'pointer',
                  background: logoPreview
                    ? `url(${logoPreview}) center/contain no-repeat`
                    : 'var(--social-bg)',
                  color: 'var(--text)',
                  fontSize: '0.8rem',
                  textAlign: 'center',
                  transition: 'border-color 0.2s',
                }}
              >
                {!logoPreview && <span>Cliquez pour<br />ajouter</span>}
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
                    type="button"
                    onClick={() => setLogoPreview(null)}
                    className="app-btn-outline-danger"
                    style={{
                      marginTop: '0.5rem',
                      fontSize: '0.8rem',
                      padding: '4px 10px',
                    }}
                  >
                    Supprimer
                  </button>
                )}
              </div>
            </div>
          </div>

          {/* Couleur primaire */}
          <div className="app-form-group" style={{ marginBottom: '2rem' }}>
            <label className="app-label">Couleur primaire des factures</label>
            <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem' }}>
              <input
                type="color"
                value={primaryColor}
                onChange={(e) => setPrimaryColor(e.target.value)}
                style={{
                  width: 44,
                  height: 44,
                  border: 'none',
                  borderRadius: '8px',
                  cursor: 'pointer',
                  padding: 0,
                }}
              />
              <input
                value={primaryColor}
                onChange={(e) => setPrimaryColor(e.target.value)}
                className="app-input"
                style={{ width: 110 }}
                maxLength={7}
              />
              {/* Previsualisation de la couleur */}
              <div style={{
                height: 8,
                flex: 1,
                background: primaryColor,
                borderRadius: 4,
              }} />
            </div>
          </div>

          {/* Conditions de paiement par defaut */}
          <div className="app-form-group">
            <label className="app-label">Conditions de paiement par defaut</label>
            <select
              value={defaultPaymentTerms}
              onChange={(e) => setDefaultPaymentTerms(e.target.value)}
              className="app-select"
            >
              {PAYMENT_TERMS.map((term) => (
                <option key={term.value} value={term.value}>{term.label}</option>
              ))}
            </select>
            <p style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.35rem' }}>
              Ce delai sera pre-rempli sur chaque nouvelle facture. Vous pourrez le modifier au cas par cas.
            </p>
          </div>
        </div>
      )}

      {/* Etape 4 : Recapitulatif */}
      {currentStep === 3 && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0, textAlign: 'center' }}>
            Pret a facturer !
          </h2>
          <p style={{ color: 'var(--text)', textAlign: 'center', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Voici un recapitulatif de vos choix. Vous pourrez tout modifier a tout moment dans les parametres.
          </p>

          {/* Recapitulatif sous forme de carte */}
          <div className="app-card" style={{ padding: '1.5rem', marginBottom: '1.5rem' }}>
            {/* Ligne : forme juridique */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              paddingBottom: '1rem',
              borderBottom: '1px solid var(--border)',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>Forme juridique</span>
              <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>
                {LEGAL_FORMS.find((f) => f.value === selectedLegalForm)?.label || '—'}
              </span>
            </div>

            {/* Ligne : regime TVA */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              padding: '1rem 0',
              borderBottom: '1px solid var(--border)',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>Regime de TVA</span>
              <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>
                {VAT_REGIMES.find((r) => r.value === vatRegime)?.label || '—'}
              </span>
            </div>

            {/* Ligne : exercice comptable */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              padding: '1rem 0',
              borderBottom: '1px solid var(--border)',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>Exercice comptable</span>
              <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>
                {monthLabel(exerciseStartMonth)} — {monthLabel(exerciseEndMonth)}
              </span>
            </div>

            {/* Ligne : logo */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              padding: '1rem 0',
              borderBottom: '1px solid var(--border)',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>Logo</span>
              {logoPreview ? (
                <img
                  src={logoPreview}
                  alt="Logo"
                  style={{ maxHeight: 32, maxWidth: 100, borderRadius: 4 }}
                />
              ) : (
                <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>Non renseigne</span>
              )}
            </div>

            {/* Ligne : couleur primaire */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              padding: '1rem 0',
              borderBottom: '1px solid var(--border)',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>Couleur primaire</span>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                <div style={{
                  width: 20,
                  height: 20,
                  borderRadius: 4,
                  background: primaryColor,
                  border: '1px solid var(--border)',
                }} />
                <span style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.9rem' }}>
                  {primaryColor}
                </span>
              </div>
            </div>

            {/* Ligne : conditions de paiement */}
            <div style={{
              display: 'flex',
              justifyContent: 'space-between',
              alignItems: 'center',
              paddingTop: '1rem',
              flexWrap: 'wrap',
              gap: '0.5rem',
            }}>
              <span style={{ fontSize: '0.9rem', color: 'var(--text)' }}>Conditions de paiement</span>
              <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>
                {PAYMENT_TERMS.find((t) => t.value === defaultPaymentTerms)?.label || '—'}
              </span>
            </div>
          </div>

          {/* Message de bienvenue */}
          <div style={{
            textAlign: 'center',
            padding: '1.5rem',
            background: 'var(--accent-bg)',
            borderRadius: 8,
            marginBottom: '1rem',
          }}>
            <p style={{
              fontSize: '1.1rem',
              fontWeight: 600,
              color: 'var(--text-h)',
              margin: '0 0 0.5rem',
            }}>
              Tout est pret !
            </p>
            <p style={{ fontSize: '0.9rem', color: 'var(--text)', margin: 0, lineHeight: 1.5 }}>
              Cliquez sur le bouton ci-dessous pour creer votre premiere facture.
              Vous pouvez modifier ces parametres a tout moment depuis la page Parametres.
            </p>
          </div>
        </div>
      )}

      {/* Boutons de navigation */}
      <div style={{
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginTop: '2rem',
        paddingTop: '1.5rem',
        borderTop: '1px solid var(--border)',
        gap: '0.75rem',
        flexWrap: 'wrap',
      }}>
        {/* Bouton Retour */}
        {currentStep > 0 ? (
          <button
            type="button"
            onClick={goBack}
            className="app-btn-outline-danger"
            style={{
              color: 'var(--text)',
              borderColor: 'var(--border)',
            }}
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
            className="app-btn-primary"
            style={{ minWidth: 200 }}
          >
            {saving ? 'Enregistrement...' : 'Creer ma premiere facture'}
          </button>
        )}
      </div>
    </div>
  );
}
