import { useAuth } from '../context/AuthContext';

// Page parametres : informations entreprise, PDP, abonnement.
export default function Settings() {
  const { logout } = useAuth();

  return (
    <div>
      <h1>Parametres</h1>

      <div style={{ marginTop: '20px' }}>
        <h2>Informations entreprise</h2>
        <p>Configuration SIREN, TVA, RIB via l'API /companies.</p>
      </div>

      <div style={{ marginTop: '20px' }}>
        <h2>Connexion PDP</h2>
        <p>Configuration de la plateforme de dematerialisation partenaire (Chorus Pro).</p>
      </div>

      <div style={{ marginTop: '20px' }}>
        <h2>Abonnement</h2>
        <p>Plan actuel : Gratuit (30 factures/mois)</p>
        <button>Passer au plan Pro (12 EUR/mois)</button>
      </div>

      <div style={{ marginTop: '40px' }}>
        <button onClick={logout} style={{ color: 'red', cursor: 'pointer' }}>
          Se deconnecter
        </button>
      </div>
    </div>
  );
}
