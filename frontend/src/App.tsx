import { BrowserRouter, Routes, Route, Link, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import Landing from './pages/Landing';
import Dashboard from './pages/Dashboard';
import InvoiceList from './pages/InvoiceList';
import InvoiceCreate from './pages/InvoiceCreate';
import InvoiceDetail from './pages/InvoiceDetail';
import ClientList from './pages/ClientList';
import Settings from './pages/Settings';
import Login from './pages/Login';
import Register from './pages/Register';

// Route protegee : redirige vers /login si non authentifie
function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  if (!isAuthenticated) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

// Page d'accueil : landing si non connecte, dashboard si connecte
function HomePage() {
  const { isAuthenticated } = useAuth();
  if (isAuthenticated) return <Dashboard />;
  return <Landing />;
}

// Barre de navigation principale (affichee uniquement pour les utilisateurs connectes)
function NavBar() {
  const { isAuthenticated } = useAuth();
  if (!isAuthenticated) return null;

  return (
    <nav style={{ padding: '10px 20px', borderBottom: '1px solid #e5e7eb', display: 'flex', gap: '20px' }}>
      <Link to="/" style={{ fontWeight: 'bold', textDecoration: 'none' }}>Ma Facture Pro</Link>
      <Link to="/invoices">Factures</Link>
      <Link to="/clients">Clients</Link>
      <Link to="/settings">Parametres</Link>
    </nav>
  );
}

function App() {
  return (
    <AuthProvider>
      <BrowserRouter>
        <NavBar />
        <Routes>
          <Route path="/" element={<HomePage />} />
          <Route path="/login" element={<Login />} />
          <Route path="/register" element={<Register />} />
          <Route path="/invoices" element={<ProtectedRoute><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><InvoiceList /></div></ProtectedRoute>} />
          <Route path="/invoices/new" element={<ProtectedRoute><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><InvoiceCreate /></div></ProtectedRoute>} />
          <Route path="/invoices/:id" element={<ProtectedRoute><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><InvoiceDetail /></div></ProtectedRoute>} />
          <Route path="/clients" element={<ProtectedRoute><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><ClientList /></div></ProtectedRoute>} />
          <Route path="/settings" element={<ProtectedRoute><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><Settings /></div></ProtectedRoute>} />
        </Routes>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
