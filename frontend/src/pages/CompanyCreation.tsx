import { useState } from 'react';
import '../pages/AppLayout.css';
import './CompanyCreation.css';

// Arbre decisionnel simplifie pour aider le freelance a choisir son statut
type Step = 'start' | 'activity' | 'ca' | 'result-micro' | 'result-ei' | 'result-sasu' | 'checklist';

interface DecisionState {
  activity: 'liberal' | 'commercial' | 'artisanal' | null;
  caEstimate: 'low' | 'high' | null;
}

export default function CompanyCreation() {
  const [step, setStep] = useState<Step>('start');
  const [decision, setDecision] = useState<DecisionState>({ activity: null, caEstimate: null });

  const handleActivity = (activity: DecisionState['activity']) => {
    setDecision({ ...decision, activity });
    setStep('ca');
  };

  const handleCa = (caEstimate: DecisionState['caEstimate']) => {
    setDecision({ ...decision, caEstimate });
    if (caEstimate === 'low') {
      setStep('result-micro');
    } else if (decision.activity === 'liberal') {
      setStep('result-ei');
    } else {
      setStep('result-sasu');
    }
  };

  const reset = () => {
    setStep('start');
    setDecision({ activity: null, caEstimate: null });
  };

  return (
    <div className="app-container cc-page">
      <h1 className="app-page-title">Creer mon entreprise</h1>
      <p className="cc-intro">
        Ce guide vous aide a choisir le statut adapte a votre situation.
        Toutes les formalites se font en ligne sur le Guichet Unique de l'INPI.
      </p>

      {/* Arbre decisionnel */}
      {step === 'start' && (
        <div className="cc-card">
          <h2 className="cc-card-title">Quel type d'activite exercez-vous ?</h2>
          <div className="cc-choices">
            <button className="cc-choice" onClick={() => handleActivity('liberal')}>
              <span className="cc-choice-icon">&#x1f4bc;</span>
              <span className="cc-choice-label">Activite liberale</span>
              <span className="cc-choice-desc">Conseil, developpement, design, formation...</span>
            </button>
            <button className="cc-choice" onClick={() => handleActivity('commercial')}>
              <span className="cc-choice-icon">&#x1f6d2;</span>
              <span className="cc-choice-label">Activite commerciale</span>
              <span className="cc-choice-desc">Vente de biens, e-commerce, negoce...</span>
            </button>
            <button className="cc-choice" onClick={() => handleActivity('artisanal')}>
              <span className="cc-choice-icon">&#x1f528;</span>
              <span className="cc-choice-label">Activite artisanale</span>
              <span className="cc-choice-desc">Plomberie, electricite, boulangerie...</span>
            </button>
          </div>
        </div>
      )}

      {step === 'ca' && (
        <div className="cc-card">
          <h2 className="cc-card-title">Quel chiffre d'affaires prevoyez-vous ?</h2>
          <p className="cc-hint">
            {decision.activity === 'commercial'
              ? 'Seuil micro-entreprise : 188 700 €/an pour les activites commerciales'
              : 'Seuil micro-entreprise : 77 700 €/an pour les prestations de services'}
          </p>
          <div className="cc-choices">
            <button className="cc-choice" onClick={() => handleCa('low')}>
              <span className="cc-choice-icon">&#x1f331;</span>
              <span className="cc-choice-label">
                Moins de {decision.activity === 'commercial' ? '188 700' : '77 700'} €/an
              </span>
              <span className="cc-choice-desc">Regime micro-entreprise possible</span>
            </button>
            <button className="cc-choice" onClick={() => handleCa('high')}>
              <span className="cc-choice-icon">&#x1f680;</span>
              <span className="cc-choice-label">
                Plus de {decision.activity === 'commercial' ? '188 700' : '77 700'} €/an
              </span>
              <span className="cc-choice-desc">Regime reel obligatoire</span>
            </button>
          </div>
          <button className="cc-back" onClick={() => setStep('start')}>Retour</button>
        </div>
      )}

      {step === 'result-micro' && (
        <div className="cc-card cc-result">
          <h2 className="cc-card-title">Micro-entreprise (auto-entrepreneur)</h2>
          <div className="cc-badge-green">Recommande pour demarrer</div>
          <ul className="cc-list">
            <li>Comptabilite ultra-simplifiee (livre des recettes)</li>
            <li>Cotisations sociales proportionnelles au CA (21,1% a 23,1%)</li>
            <li>Franchise de TVA possible (art. 293B du CGI)</li>
            <li>Pas de bilan comptable obligatoire</li>
            <li>Option pour le versement liberatoire de l'impot sur le revenu</li>
          </ul>
          <div className="cc-actions">
            <a
              href="https://formalites.entreprises.gouv.fr"
              target="_blank"
              rel="noopener noreferrer"
              className="cc-btn-primary"
            >
              Creer sur le Guichet Unique INPI
            </a>
            <button className="cc-btn-outline" onClick={() => setStep('checklist')}>
              Voir la checklist post-creation
            </button>
          </div>
          <button className="cc-back" onClick={reset}>Recommencer</button>
        </div>
      )}

      {step === 'result-ei' && (
        <div className="cc-card cc-result">
          <h2 className="cc-card-title">Entreprise Individuelle (EI au reel)</h2>
          <div className="cc-badge-blue">Adapte aux activites liberales a fort CA</div>
          <ul className="cc-list">
            <li>Deduction des charges reelles (loyer, materiel, deplacement...)</li>
            <li>Cotisations TNS (~45% du benefice)</li>
            <li>Comptabilite complete obligatoire</li>
            <li>TVA collectee et deductible</li>
            <li>Patrimoine personnel protege depuis 2022</li>
          </ul>
          <div className="cc-actions">
            <a
              href="https://formalites.entreprises.gouv.fr"
              target="_blank"
              rel="noopener noreferrer"
              className="cc-btn-primary"
            >
              Creer sur le Guichet Unique INPI
            </a>
            <button className="cc-btn-outline" onClick={() => setStep('checklist')}>
              Voir la checklist post-creation
            </button>
          </div>
          <button className="cc-back" onClick={reset}>Recommencer</button>
        </div>
      )}

      {step === 'result-sasu' && (
        <div className="cc-card cc-result">
          <h2 className="cc-card-title">SASU ou EURL</h2>
          <div className="cc-badge-purple">Pour structurer et optimiser</div>
          <ul className="cc-list">
            <li>SASU : president assimile salarie, protection sociale du regime general</li>
            <li>EURL : gerant TNS, cotisations moins elevees</li>
            <li>Impot sur les societes (IS) : 15% jusqu'a 42 500 €, 25% au-dela</li>
            <li>Possibilite de se verser des dividendes (flat tax 30%)</li>
            <li>Responsabilite limitee aux apports</li>
          </ul>
          <div className="cc-actions">
            <a
              href="https://formalites.entreprises.gouv.fr"
              target="_blank"
              rel="noopener noreferrer"
              className="cc-btn-primary"
            >
              Creer sur le Guichet Unique INPI
            </a>
            <button className="cc-btn-outline" onClick={() => setStep('checklist')}>
              Voir la checklist post-creation
            </button>
          </div>
          <button className="cc-back" onClick={reset}>Recommencer</button>
        </div>
      )}

      {step === 'checklist' && (
        <div className="cc-card">
          <h2 className="cc-card-title">Checklist post-creation</h2>
          <p className="cc-hint">Actions a realiser dans les 30 jours suivant la creation :</p>
          <ol className="cc-checklist">
            <li>
              <strong>Ouvrir un compte bancaire professionnel</strong>
              <span>Obligatoire pour les societes, recommande pour les EI.</span>
            </li>
            <li>
              <strong>S'inscrire sur l'URSSAF</strong>
              <span>Votre espace sera cree automatiquement apres immatriculation.</span>
            </li>
            <li>
              <strong>Choisir un logiciel de facturation conforme</strong>
              <span>Ma Facture Pro est conforme aux obligations 2026.</span>
            </li>
            <li>
              <strong>Souscrire une assurance RC Pro</strong>
              <span>Obligatoire pour certaines professions liberales et artisanales.</span>
            </li>
            <li>
              <strong>Declarer votre activite aux impots</strong>
              <span>Option TVA et regime d'imposition a choisir dans les 15 jours.</span>
            </li>
            <li>
              <strong>Domicilier votre entreprise</strong>
              <span>Votre adresse personnelle, une pepiniere ou un espace de coworking.</span>
            </li>
            <li>
              <strong>Commander un cachet / tampon (optionnel)</strong>
              <span>Non obligatoire mais utile pour les devis et bons de commande.</span>
            </li>
            <li>
              <strong>Configurer Ma Facture Pro</strong>
              <span>Renseignez votre SIREN, vos coordonnees et parametrez la TVA.</span>
            </li>
          </ol>

          <div className="cc-links">
            <h3>Liens utiles</h3>
            <ul className="cc-list">
              <li><a href="https://formalites.entreprises.gouv.fr" target="_blank" rel="noopener noreferrer">Guichet Unique INPI — Creer mon entreprise</a></li>
              <li><a href="https://www.urssaf.fr/accueil/creer.html" target="_blank" rel="noopener noreferrer">URSSAF — Espace createur</a></li>
              <li><a href="https://www.impots.gouv.fr/professionnel" target="_blank" rel="noopener noreferrer">Impots.gouv.fr — Espace professionnel</a></li>
              <li><a href="https://www.infogreffe.fr" target="_blank" rel="noopener noreferrer">Infogreffe — Registre du commerce</a></li>
            </ul>
          </div>

          <button className="cc-back" onClick={reset}>Recommencer le questionnaire</button>
        </div>
      )}
    </div>
  );
}
