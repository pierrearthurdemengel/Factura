import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { register } from '../api/factura';
import { useToast } from '../context/ToastContext';
import './Auth.css';

// Page d'inscription : creation d'un compte utilisateur + entreprise.
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
    <div className="auth-container">
      <h1 className="auth-title">Creer un compte</h1>
      <p className="auth-desc">
        Commencez a facturer en conformite avec la reforme DGFiP.
      </p>

      <form onSubmit={handleSubmit}>
        <h3 className="auth-section-title" style={{ marginTop: 0 }}>Informations personnelles</h3>

        <div className="auth-row">
          <div className="auth-form-group">
            <label className="auth-label">Prenom</label>
            <input name="firstName" value={form.firstName} onChange={handleChange} required className="auth-input" />
          </div>
          <div className="auth-form-group">
            <label className="auth-label">Nom</label>
            <input name="lastName" value={form.lastName} onChange={handleChange} required className="auth-input" />
          </div>
        </div>

        <div className="auth-form-group">
          <label className="auth-label">Email</label>
          <input name="email" type="email" value={form.email} onChange={handleChange} required className="auth-input" />
        </div>

        <div className="auth-form-group">
          <label className="auth-label">Mot de passe</label>
          <input name="password" type="password" value={form.password} onChange={handleChange} required minLength={8} className="auth-input" />
        </div>

        <h3 className="auth-section-title">Entreprise</h3>

        <div className="auth-form-group">
          <label className="auth-label">Raison sociale</label>
          <input name="companyName" value={form.companyName} onChange={handleChange} required className="auth-input" />
        </div>

        <div className="auth-row">
          <div className="auth-form-group">
            <label className="auth-label">SIREN (9 chiffres)</label>
            <input name="siren" value={form.siren} onChange={handleChange} required pattern="[0-9]{9}" maxLength={9} className="auth-input" />
          </div>
          <div className="auth-form-group">
            <label className="auth-label">Forme juridique</label>
            <select name="legalForm" value={form.legalForm} onChange={handleChange} className="auth-select">
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
          <label className="auth-label">Adresse</label>
          <input name="addressLine1" value={form.addressLine1} onChange={handleChange} required className="auth-input" />
        </div>

        <div className="auth-row auth-row-2-1">
          <div className="auth-form-group">
            <label className="auth-label">Code postal</label>
            <input name="postalCode" value={form.postalCode} onChange={handleChange} required maxLength={5} className="auth-input" />
          </div>
          <div className="auth-form-group">
            <label className="auth-label">Ville</label>
            <input name="city" value={form.city} onChange={handleChange} required className="auth-input" />
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          className="auth-btn-submit"
        >
          {loading ? 'Creation en cours...' : 'Creer mon compte'}
        </button>
      </form>

      <div className="auth-footer">
        Deja un compte ? <Link to="/login" className="auth-link">Se connecter</Link>
      </div>
    </div>
  );
}
