import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { register } from '../api/factura';
import { useToast } from '../context/ToastContext';
import './Auth.css';

export default function Register() {
  const navigate = useNavigate();
  const { error, success } = useToast();
  const [loading, setLoading] = useState(false);

  const [form, setForm] = useState({
    email: '',
    password: '',
    firstName: '',
    lastName: '',
    companyName: '',
    siren: '',
    legalForm: 'SAS',
    addressLine1: '',
    postalCode: '',
    city: '',
  });

  const handleChange = (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) => {
    setForm({ ...form, [e.target.name]: e.target.value });
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await register(form);
      success('Compte cree avec succes ! Vous pouvez vous connecter.');
      navigate('/login');
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { errors?: string[]; error?: string } } };
      if (axiosErr.response?.data?.errors) {
        error(axiosErr.response.data.errors.join(', '));
      } else if (axiosErr.response?.data?.error) {
        error(axiosErr.response.data.error);
      } else {
        error('Erreur lors de la creation du compte.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="auth-brand">
        <div className="auth-brand-content">
          <div className="auth-brand-logo">Ma Facture Pro</div>
          <p className="auth-brand-tagline">
            Rejoignez des milliers d'entreprises qui simplifient leur facturation electronique.
          </p>
          <div className="auth-brand-features">
            <div className="auth-brand-feature">
              <div className="auth-brand-feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z" />
                </svg>
              </div>
              <span>Inscription en 2 minutes</span>
            </div>
            <div className="auth-brand-feature">
              <div className="auth-brand-feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <circle cx="12" cy="12" r="10" /><path d="M12 6v6l4 2" />
                </svg>
              </div>
              <span>Premiere facture en 5 minutes</span>
            </div>
            <div className="auth-brand-feature">
              <div className="auth-brand-feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6" />
                </svg>
              </div>
              <span>Gratuit jusqu'a 5 factures / mois</span>
            </div>
          </div>
        </div>
      </div>

      <div className="auth-panel">
        <div className="auth-container">
          <div className="auth-mobile-logo">Ma Facture Pro</div>

          <h1 className="auth-title">Creer un compte</h1>
          <p className="auth-desc">
            Commencez a facturer en conformite avec la reforme DGFiP.
          </p>

          <form onSubmit={handleSubmit}>
            <h3 className="auth-section-title" style={{ marginTop: 0 }}>Informations personnelles</h3>

            <div className="auth-row">
              <div className="auth-form-group">
                <label className="auth-label" htmlFor="reg-firstName">Prenom</label>
                <input id="reg-firstName" name="firstName" value={form.firstName} onChange={handleChange} required className="auth-input" placeholder="Jean" autoComplete="given-name" />
              </div>
              <div className="auth-form-group">
                <label className="auth-label" htmlFor="reg-lastName">Nom</label>
                <input id="reg-lastName" name="lastName" value={form.lastName} onChange={handleChange} required className="auth-input" placeholder="Dupont" autoComplete="family-name" />
              </div>
            </div>

            <div className="auth-form-group">
              <label className="auth-label" htmlFor="reg-email">Email</label>
              <input id="reg-email" name="email" type="email" value={form.email} onChange={handleChange} required className="auth-input" placeholder="vous@entreprise.fr" autoComplete="email" />
            </div>

            <div className="auth-form-group">
              <label className="auth-label" htmlFor="reg-password">Mot de passe</label>
              <input id="reg-password" name="password" type="password" value={form.password} onChange={handleChange} required minLength={8} className="auth-input" placeholder="Minimum 8 caracteres" autoComplete="new-password" />
            </div>

            <h3 className="auth-section-title">Entreprise</h3>

            <div className="auth-form-group">
              <label className="auth-label" htmlFor="reg-companyName">Raison sociale</label>
              <input id="reg-companyName" name="companyName" value={form.companyName} onChange={handleChange} required className="auth-input" placeholder="Ma Societe SAS" autoComplete="organization" />
            </div>

            <div className="auth-row">
              <div className="auth-form-group">
                <label className="auth-label" htmlFor="reg-siren">SIREN (9 chiffres)</label>
                <input id="reg-siren" name="siren" value={form.siren} onChange={handleChange} required pattern="[0-9]{9}" maxLength={9} className="auth-input" placeholder="123456789" inputMode="numeric" />
              </div>
              <div className="auth-form-group">
                <label className="auth-label" htmlFor="reg-legalForm">Forme juridique</label>
                <select id="reg-legalForm" name="legalForm" value={form.legalForm} onChange={handleChange} className="auth-select">
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

            <div className="auth-form-group">
              <label className="auth-label" htmlFor="reg-address">Adresse</label>
              <input id="reg-address" name="addressLine1" value={form.addressLine1} onChange={handleChange} required className="auth-input" placeholder="12 rue de la Paix" autoComplete="street-address" />
            </div>

            <div className="auth-row auth-row-2-1">
              <div className="auth-form-group">
                <label className="auth-label" htmlFor="reg-postalCode">Code postal</label>
                <input id="reg-postalCode" name="postalCode" value={form.postalCode} onChange={handleChange} required maxLength={5} className="auth-input" placeholder="75001" inputMode="numeric" autoComplete="postal-code" />
              </div>
              <div className="auth-form-group">
                <label className="auth-label" htmlFor="reg-city">Ville</label>
                <input id="reg-city" name="city" value={form.city} onChange={handleChange} required className="auth-input" placeholder="Paris" autoComplete="address-level2" />
              </div>
            </div>

            <button
              type="submit"
              disabled={loading}
              className="auth-btn-submit"
            >
              {loading ? 'Creation en cours' : 'Creer mon compte'}
            </button>
          </form>

          <div className="auth-footer">
            Deja un compte ? <Link to="/login" className="auth-link">Se connecter</Link>
          </div>
        </div>
      </div>
    </div>
  );
}
