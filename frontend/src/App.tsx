import { useState, useEffect } from 'react';
import { BrowserRouter, Routes, Route, Link, Navigate, useLocation } from 'react-router-dom';
import { AnimatePresence } from 'framer-motion';
import { AuthProvider, useAuth } from './context/AuthContext';
import { ToastProvider } from './context/ToastContext';
import { AudioProvider } from './context/AudioScope';
import { ThemeProvider, useTheme } from './context/ThemeContext';
import PageTransition from './components/PageTransition';
import CommandMenu from './components/CommandMenu';
import FloatingActionButton from './components/FloatingActionButton';
import VoiceAssistant from './components/VoiceAssistant';
import AmbientBackground from './components/AmbientBackground';
import CustomCursor from './components/CustomCursor';
import CollaboratorCursor from './components/CollaboratorCursor';
import './components/NavBar.css';
import Landing from './pages/Landing';
import Dashboard from './pages/Dashboard';
import InvoiceList from './pages/InvoiceList';
import InvoiceCreate from './pages/InvoiceCreate';
import InvoiceDetail from './pages/InvoiceDetail';
import ClientList from './pages/ClientList';
import Settings from './pages/Settings';
import Login from './pages/Login';
import Register from './pages/Register';
import CompanyCreation from './pages/CompanyCreation';

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
function NavBar({ onOpenCommand }: { onOpenCommand: () => void }) {
  const { isAuthenticated } = useAuth();
  const { setTheme, isDark } = useTheme();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const location = useLocation();

  // Fermer le menu mobile lors d'un changement de page
  useEffect(() => {
    setMobileMenuOpen(false);
  }, [location.pathname]);

  if (!isAuthenticated) return null;

  return (
    <nav className="navbar">
      <Link to="/" className="navbar-brand">Factura</Link>

      {/* Barre de recherche desktop */}
      <button onClick={onOpenCommand} className="navbar-search">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <span>Rechercher...</span>
        <kbd>&#8984;K</kbd>
      </button>

      {/* Bouton theme */}
      <button
        onClick={() => setTheme(isDark ? 'light' : 'dark')}
        className="navbar-icon-btn"
        title={isDark ? "Activer le mode clair" : "Activer le mode sombre"}
      >
        {isDark ? (
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
        ) : (
          <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
        )}
      </button>

      {/* Liens desktop */}
      <div className="navbar-links">
        <Link to="/invoices" className="navbar-link">Factures</Link>
        <Link to="/clients" className="navbar-link">Clients</Link>
        <Link to="/settings" className="navbar-link">Parametres</Link>
      </div>

      {/* Bouton hamburger mobile */}
      <button
        className={`navbar-hamburger ${mobileMenuOpen ? 'open' : ''}`}
        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
        aria-label="Menu"
      >
        <span /><span /><span />
      </button>

      {/* Menu mobile plein ecran */}
      {mobileMenuOpen && (
        <div className="navbar-mobile-menu">
          <Link to="/" className="navbar-mobile-link">Tableau de bord</Link>
          <Link to="/invoices" className="navbar-mobile-link">Factures</Link>
          <Link to="/clients" className="navbar-mobile-link">Clients</Link>
          <Link to="/settings" className="navbar-mobile-link">Parametres</Link>
          <button onClick={() => { onOpenCommand(); setMobileMenuOpen(false); }} className="navbar-mobile-link" style={{ background: 'none', border: 'none', textAlign: 'left', cursor: 'pointer', font: 'inherit', color: 'inherit' }}>
            Rechercher
          </button>
        </div>
      )}
    </nav>
  );
}

function AnimatedAppCore() {
  const [cmdOpen, setCmdOpen] = useState(false);
  const location = useLocation();

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ignorer si on est dans un input
      const target = e.target as HTMLElement;
      const tName = target.tagName;
      const isInput = tName === 'INPUT' || tName === 'TEXTAREA' || tName === 'SELECT' || target.isContentEditable;

      // 1. Ouvrir le Command Menu avec Cmd+K ou Ctrl+K
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        setCmdOpen((o) => !o);
      }
      
      // 2. Prevent Default sur Cmd+S pour eviter d'enregistrer la page native
      if ((e.metaKey || e.ctrlKey) && e.key === 's') {
        e.preventDefault();
        // Une ecoute specifique locale gérera la logique
      }

      // 3. Raccourcis C pour creer (hors inputs)
      if (!isInput && e.key.toLowerCase() === 'c') {
        e.preventDefault();
        window.location.href = '/invoices/new';
      }
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  return (
    <>
      <CustomCursor />
      <CollaboratorCursor />
      <AmbientBackground />
      <VoiceAssistant />
      {location.pathname !== '/invoices/new' && (
        <>
          <NavBar onOpenCommand={() => setCmdOpen(true)} />
          <FloatingActionButton onOpenSearch={() => setCmdOpen(true)} />
        </>
      )}
      <CommandMenu isOpen={cmdOpen} onClose={() => setCmdOpen(false)} />
      <AnimatePresence mode="wait">
        <Routes location={location} key={location.pathname}>
          <Route path="/" element={<PageTransition><HomePage /></PageTransition>} />
          <Route path="/login" element={<PageTransition><Login /></PageTransition>} />
          <Route path="/register" element={<PageTransition><Register /></PageTransition>} />
          <Route path="/invoices" element={<ProtectedRoute><PageTransition><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><InvoiceList /></div></PageTransition></ProtectedRoute>} />
          <Route path="/invoices/new" element={<ProtectedRoute><PageTransition><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><InvoiceCreate /></div></PageTransition></ProtectedRoute>} />
          <Route path="/invoices/:id" element={<ProtectedRoute><PageTransition><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><InvoiceDetail /></div></PageTransition></ProtectedRoute>} />
          <Route path="/clients" element={<ProtectedRoute><PageTransition><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><ClientList /></div></PageTransition></ProtectedRoute>} />
          <Route path="/settings" element={<ProtectedRoute><PageTransition><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><Settings /></div></PageTransition></ProtectedRoute>} />
          <Route path="/creer-entreprise" element={<PageTransition><div style={{ padding: '20px', maxWidth: '1200px', margin: '0 auto' }}><CompanyCreation /></div></PageTransition>} />
        </Routes>
      </AnimatePresence>
    </>
  );
}

function App() {
  return (
    <ThemeProvider>
      <AuthProvider>
        <AudioProvider>
          <ToastProvider>
            <BrowserRouter>
              <AnimatedAppCore />
            </BrowserRouter>
          </ToastProvider>
        </AudioProvider>
      </AuthProvider>
    </ThemeProvider>
  );
}

export default App;
