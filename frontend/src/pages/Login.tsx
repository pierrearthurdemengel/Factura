import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import './Auth.css';
import { useAuth } from '../context/AuthContext';
import { useToast } from '../context/ToastContext';

// Page de connexion.
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
      success('Connexion reussie !');
      navigate('/');
    } catch {
      error('Email ou mot de passe incorrect.');
      setLoading(false);
    }
  };

  return (
    <div className="auth-container">
      <h1 className="auth-title">Connexion</h1>

      <form onSubmit={handleSubmit}>
        <div className="auth-form-group">
          <label className="auth-label">Email</label>
          <input
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            className="auth-input"
          />
        </div>
        <div className="auth-form-group">
          <label className="auth-label">Mot de passe</label>
          <input
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            className="auth-input"
          />
        </div>
        <button type="submit" className="auth-btn-submit" disabled={loading}>
          {loading ? 'Connexion en cours...' : 'Se connecter'}
        </button>
      </form>

      <div className="auth-footer">
        Pas de compte ? <Link to="/register" className="auth-link">Creer un compte</Link>
      </div>
    </div>
  );
}
