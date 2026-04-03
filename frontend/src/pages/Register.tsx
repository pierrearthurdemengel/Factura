import { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { register } from '../api/factura';

// Page d'inscription : creation d'un compte utilisateur + entreprise.
export default function Register() {
  const navigate = useNavigate();
  const [error, setError] = useState('');
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
    setError('');
    setLoading(true);

    try {
      await register(form);
      navigate('/login');
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { errors?: string[]; error?: string } } };
      if (axiosErr.response?.data?.errors) {
        setError(axiosErr.response.data.errors.join(', '));
      } else if (axiosErr.response?.data?.error) {
        setError(axiosErr.response.data.error);
      } else {
        setError('Erreur lors de la creation du compte.');
      }
    } finally {
      setLoading(false);
    }
  };

  const inputStyle = { width: '100%', padding: '8px', boxSizing: 'border-box' as const };
  const fieldStyle = { marginBottom: '12px' };

  return (
    <div style={{ maxWidth: '500px', margin: '40px auto', padding: '0 20px' }}>
      <h1>Creer un compte</h1>
      <p style={{ color: '#6b7280', marginBottom: '24px' }}>
        Commencez a facturer en conformite avec la reforme DGFiP.
      </p>

      {error && <p style={{ color: '#ef4444', marginBottom: '12px' }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <h3 style={{ marginBottom: '12px' }}>Informations personnelles</h3>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Prenom</label>
            <input name="firstName" value={form.firstName} onChange={handleChange} required style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Nom</label>
            <input name="lastName" value={form.lastName} onChange={handleChange} required style={inputStyle} />
          </div>
        </div>

        <div style={fieldStyle}>
          <label>Email</label>
          <input name="email" type="email" value={form.email} onChange={handleChange} required style={inputStyle} />
        </div>

        <div style={fieldStyle}>
          <label>Mot de passe</label>
          <input name="password" type="password" value={form.password} onChange={handleChange} required minLength={8} style={inputStyle} />
        </div>

        <h3 style={{ marginTop: '24px', marginBottom: '12px' }}>Entreprise</h3>

        <div style={fieldStyle}>
          <label>Raison sociale</label>
          <input name="companyName" value={form.companyName} onChange={handleChange} required style={inputStyle} />
        </div>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>SIREN (9 chiffres)</label>
            <input name="siren" value={form.siren} onChange={handleChange} required pattern="[0-9]{9}" maxLength={9} style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Forme juridique</label>
            <select name="legalForm" value={form.legalForm} onChange={handleChange} style={inputStyle}>
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

        <div style={fieldStyle}>
          <label>Adresse</label>
          <input name="addressLine1" value={form.addressLine1} onChange={handleChange} required style={inputStyle} />
        </div>

        <div style={{ display: 'flex', gap: '12px' }}>
          <div style={{ ...fieldStyle, flex: 1 }}>
            <label>Code postal</label>
            <input name="postalCode" value={form.postalCode} onChange={handleChange} required maxLength={5} style={inputStyle} />
          </div>
          <div style={{ ...fieldStyle, flex: 2 }}>
            <label>Ville</label>
            <input name="city" value={form.city} onChange={handleChange} required style={inputStyle} />
          </div>
        </div>

        <button
          type="submit"
          disabled={loading}
          style={{ width: '100%', padding: '12px', cursor: loading ? 'not-allowed' : 'pointer', marginTop: '16px' }}
        >
          {loading ? 'Creation en cours...' : 'Creer mon compte'}
        </button>
      </form>

      <p style={{ textAlign: 'center', marginTop: '20px' }}>
        Deja un compte ? <Link to="/login">Se connecter</Link>
      </p>
    </div>
  );
}
