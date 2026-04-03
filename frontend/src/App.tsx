import { BrowserRouter, Routes, Route, Link, Navigate } from 'react-router-dom';
import { AuthProvider, useAuth } from './context/AuthContext';
import Dashboard from './pages/Dashboard';
import InvoiceList from './pages/InvoiceList';
import InvoiceCreate from './pages/InvoiceCreate';
import InvoiceDetail from './pages/InvoiceDetail';
import ClientList from './pages/ClientList';
import Settings from './pages/Settings';
import Login from './pages/Login';

// Route protegee : redirige vers /login si non authentifie
function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated } = useAuth();
  if (!isAuthenticated) return <Navigate to="/login" replace />;
  return <>{children}</>;
}

// Barre de navigation principale
function NavBar() {
  const { isAuthenticated } = useAuth();
  if (!isAuthenticated) return null;

  return (
    <nav style={{ padding: '10px 20px', borderBottom: '1px solid #e5e7eb', display: 'flex', gap: '20px' }}>
      <Link to="/" style={{ fontWeight: 'bold', textDecoration: 'none' }}>Factura</Link>
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
        <div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}>
          <Routes>
            <Route path="/login" element={<Login />} />
            <Route path="/" element={<ProtectedRoute><Dashboard /></ProtectedRoute>} />
            <Route path="/invoices" element={<ProtectedRoute><InvoiceList /></ProtectedRoute>} />
            <Route path="/invoices/new" element={<ProtectedRoute><InvoiceCreate /></ProtectedRoute>} />
            <Route path="/invoices/:id" element={<ProtectedRoute><InvoiceDetail /></ProtectedRoute>} />
            <Route path="/clients" element={<ProtectedRoute><ClientList /></ProtectedRoute>} />
            <Route path="/settings" element={<ProtectedRoute><Settings /></ProtectedRoute>} />
          </Routes>
        </div>
      </BrowserRouter>
    </AuthProvider>
  );
}

export default App;
