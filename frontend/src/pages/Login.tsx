import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import './Auth.css';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';
import { prefetchRoute } from '../App';

export default function Login() {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const { login } = useAuth();
  const { success, error } = useToast();
  const navigate = useNavigate();

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);

    try {
      await login(email, password);
      // Prefetch core routes immediately after login for instant navigation
      prefetchRoute('/invoices');
      prefetchRoute('/clients');
      prefetchRoute('/quotes');
      prefetchRoute('/settings');
      success('Connexion reussie !');
      navigate('/');
    } catch {
      error('Email ou mot de passe incorrect.');
      setLoading(false);
    }
  };

  return (
    <div className="auth-page">
      <div className="auth-brand">
        <div className="auth-brand-content">
          <div className="auth-brand-logo">Ma Facture Pro</div>
          <p className="auth-brand-tagline">
            La facturation electronique conforme, simple et puissante pour les entreprises francaises.
          </p>
          <div className="auth-brand-features">
            <div className="auth-brand-feature">
              <div className="auth-brand-feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M9 12l2 2 4-4" /><circle cx="12" cy="12" r="10" />
                </svg>
              </div>
              <span>Conforme reforme DGFiP 2026</span>
            </div>
            <div className="auth-brand-feature">
              <div className="auth-brand-feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <rect x="2" y="3" width="20" height="14" rx="2" /><path d="M8 21h8" /><path d="M12 17v4" />
                </svg>
              </div>
              <span>Transmission automatique Chorus Pro</span>
            </div>
            <div className="auth-brand-feature">
              <div className="auth-brand-feature-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                  <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
                </svg>
              </div>
              <span>Archivage securise 10 ans</span>
            </div>
          </div>
        </div>
      </div>

      <div className="auth-panel">
        <div className="auth-container">
          <div className="auth-mobile-logo">Ma Facture Pro</div>

          <h1 className="auth-title">Connexion</h1>
          <p className="auth-desc">Retrouvez votre espace de facturation.</p>

          <form onSubmit={handleSubmit}>
            <div className="auth-form-group">
              <label className="auth-label" htmlFor="login-email">Email</label>
              <input
                id="login-email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="auth-input"
                placeholder="vous@entreprise.fr"
                autoComplete="email"
              />
            </div>
            <div className="auth-form-group">
              <label className="auth-label" htmlFor="login-password">Mot de passe</label>
              <input
                id="login-password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="auth-input"
                placeholder="Votre mot de passe"
                autoComplete="current-password"
              />
            </div>
            <button type="submit" className="auth-btn-submit" disabled={loading}>
              {loading ? 'Connexion en cours' : 'Se connecter'}
            </button>
          </form>

          <div className="auth-footer">
            Pas de compte ? <Link to="/register" className="auth-link">Creer un compte</Link>
          </div>

          <div className="auth-trust">
            <span className="auth-trust-item">
              <svg className="auth-trust-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" />
              </svg>
              Donnees chiffrees
            </span>
            <span className="auth-trust-item">
              <svg className="auth-trust-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round">
                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" />
              </svg>
              Heberge en France
            </span>
          </div>
        </div>
      </div>
    </div>
  );
}
