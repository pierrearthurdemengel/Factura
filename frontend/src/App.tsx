import { useState, useEffect, useRef, lazy, Suspense } from 'react';
import { BrowserRouter, Routes, Route, Link, Navigate, useLocation } from 'react-router-dom';
import { useIntl } from 'react-intl';
import api from './api/factura';
import { AnimatePresence } from 'framer-motion';
import { AuthProvider, useAuth } from './context/AuthContext';
import { ToastProvider } from './context/ToastContext';
import { AudioProvider } from './context/AudioScope';
import { ThemeProvider, useTheme } from './context/ThemeContext';
import { AppIntlProvider, useLocale } from './i18n/IntlProvider';
import PageTransition from './components/PageTransition';
import CommandMenu from './components/CommandMenu';
import FloatingActionButton from './components/FloatingActionButton';
import VoiceAssistant from './components/VoiceAssistant';
import AmbientBackground from './components/AmbientBackground';
import ErrorBoundary from './components/ErrorBoundary';
import { startProgress, doneProgress } from './utils/progress';

import './components/NavBar.css';

// Pages critiques chargees immediatement (parcours principal)
import Landing from './pages/Landing';
import Dashboard from './pages/Dashboard';
import Login from './pages/Login';
import Register from './pages/Register';

// Pages secondaires chargees a la demande (code splitting)
// Les imports sont stockes pour permettre le prefetch on-hover
const lazyImports = {
  InvoiceList: () => import('./pages/InvoiceList'),
  InvoiceCreate: () => import('./pages/InvoiceCreate'),
  InvoiceDetail: () => import('./pages/InvoiceDetail'),
  ClientList: () => import('./pages/ClientList'),
  Settings: () => import('./pages/Settings'),
  CompanyCreation: () => import('./pages/CompanyCreation'),
  Experts: () => import('./pages/Experts'),
  QuoteList: () => import('./pages/QuoteList'),
  QuoteCreate: () => import('./pages/QuoteCreate'),
  QuoteDetail: () => import('./pages/QuoteDetail'),
  Pricing: () => import('./pages/Pricing'),
  AdminHub: () => import('./pages/AdminHub'),
  Accounting: () => import('./pages/Accounting'),
  Declarations: () => import('./pages/Declarations'),
  Banking: () => import('./pages/Banking'),
  Simulators: () => import('./pages/Simulators'),
  Unpaid: () => import('./pages/Unpaid'),
  AccountantPortal: () => import('./pages/AccountantPortal'),
  ApiSettings: () => import('./pages/ApiSettings'),
  AssistantPage: () => import('./pages/AssistantPage'),
  ClientPortal: () => import('./pages/ClientPortal'),
  Benchmarks: () => import('./pages/Benchmarks'),
  AutopilotConfig: () => import('./pages/AutopilotConfig'),
  Network: () => import('./pages/Network'),
  OnboardingWizard: () => import('./pages/OnboardingWizard'),
  CameraScanner: () => import('./pages/CameraScanner'),
  ApiDocs: () => import('./pages/ApiDocs'),
  Guide: () => import('./pages/Guide'),
};

const InvoiceList = lazy(lazyImports.InvoiceList);
const InvoiceCreate = lazy(lazyImports.InvoiceCreate);
const InvoiceDetail = lazy(lazyImports.InvoiceDetail);
const ClientList = lazy(lazyImports.ClientList);
const Settings = lazy(lazyImports.Settings);
const CompanyCreation = lazy(lazyImports.CompanyCreation);
const Experts = lazy(lazyImports.Experts);
const QuoteList = lazy(lazyImports.QuoteList);
const QuoteCreate = lazy(lazyImports.QuoteCreate);
const QuoteDetail = lazy(lazyImports.QuoteDetail);
const Pricing = lazy(lazyImports.Pricing);
const AdminHub = lazy(lazyImports.AdminHub);
const Accounting = lazy(lazyImports.Accounting);
const Declarations = lazy(lazyImports.Declarations);
const Banking = lazy(lazyImports.Banking);
const Simulators = lazy(lazyImports.Simulators);
const Unpaid = lazy(lazyImports.Unpaid);
const AccountantPortal = lazy(lazyImports.AccountantPortal);
const ApiSettings = lazy(lazyImports.ApiSettings);
const AssistantPage = lazy(lazyImports.AssistantPage);
const ClientPortal = lazy(lazyImports.ClientPortal);
const Benchmarks = lazy(lazyImports.Benchmarks);
const AutopilotConfig = lazy(lazyImports.AutopilotConfig);
const Network = lazy(lazyImports.Network);
const OnboardingWizard = lazy(lazyImports.OnboardingWizard);
const CameraScanner = lazy(lazyImports.CameraScanner);
const ApiDocs = lazy(lazyImports.ApiDocs);
const Guide = lazy(lazyImports.Guide);

// Prefetch map: route prefix -> lazy import key
const prefetchMap: Record<string, (keyof typeof lazyImports)[]> = {
  '/invoices': ['InvoiceList', 'InvoiceCreate', 'InvoiceDetail'],
  '/quotes': ['QuoteList', 'QuoteCreate', 'QuoteDetail'],
  '/clients': ['ClientList'],
  '/settings': ['Settings'],
  '/accounting': ['Accounting'],
  '/banking': ['Banking'],
  '/declarations': ['Declarations'],
  '/unpaid': ['Unpaid'],
  '/admin-hub': ['AdminHub'],
  '/guide': ['Guide'],
};

const prefetched = new Set<string>();

export function prefetchRoute(path: string) {
  for (const [prefix, keys] of Object.entries(prefetchMap)) {
    if (path.startsWith(prefix)) {
      keys.forEach(key => {
        if (!prefetched.has(key)) {
          prefetched.add(key);
          lazyImports[key]();
        }
      });
    }
  }
}

// Fallback de chargement elegant pour les pages lazy
function LazyFallback() {
  useEffect(() => {
    startProgress();
    return () => doneProgress();
  }, []);

  return (
    <div className="lazy-fallback">
      <div className="lazy-fallback-content">
        <div className="lazy-spinner" />
      </div>
    </div>
  );
}

// Route protegee : redirige vers /login si non authentifie
function ProtectedRoute({ children }: Readonly<{ children: React.ReactNode }>) {
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
function NavBar({ onOpenCommand }: Readonly<{ onOpenCommand: () => void }>) {
  const { isAuthenticated } = useAuth();
  const { setTheme, isDark } = useTheme();
  const { locale, setLocale, supportedLocales } = useLocale();
  const intl = useIntl();
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [companySelectorOpen, setCompanySelectorOpen] = useState(false);
  const [localeSelectorOpen, setLocaleSelectorOpen] = useState(false);
  const [companies, setCompanies] = useState<{ id: string; name: string; siren: string }[]>([]);
  const [activeCompany, setActiveCompany] = useState<{ id: string; name: string; siren: string } | null>(null);
  const location = useLocation();
  const companyRef = useRef<HTMLDivElement>(null);
  const localeRef = useRef<HTMLDivElement>(null);

  // Fermer le menu mobile lors d'un changement de page
  useEffect(() => {
    setMobileMenuOpen(false); // eslint-disable-line react-hooks/set-state-in-effect
  }, [location.pathname]);

  // Fermer les dropdowns au clic exterieur
  useEffect(() => {
    const handleClickOutside = (e: MouseEvent) => {
      if (companyRef.current && !companyRef.current.contains(e.target as Node)) {
        setCompanySelectorOpen(false);
      }
      if (localeRef.current && !localeRef.current.contains(e.target as Node)) {
        setLocaleSelectorOpen(false);
      }
    };
    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Charger les entreprises de l'utilisateur
  useEffect(() => {
    if (!isAuthenticated) return;
    api.get('/companies/me').then((res) => {
      const c = res.data;
      const comp = { id: c.id, name: c.name, siren: c.siren };
      setCompanies([comp]);
      setActiveCompany(comp);
    }).catch(() => {/* Pas d'entreprise */});
  }, [isAuthenticated]);

  const handleSwitchCompany = (company: { id: string; name: string; siren: string }) => {
    setActiveCompany(company);
    setCompanySelectorOpen(false);
    // Appel API pour changer l'entreprise active
    api.post(`/companies/${company.id}/switch`).catch(() => {/* Erreur switch */});
  };

  if (!isAuthenticated) return null;

  return (
    <nav className="navbar">
      <Link to="/" className="navbar-brand">Factura</Link>

      {/* Selecteur d'entreprise */}
      {activeCompany && (
        <div ref={companyRef} style={{ position: 'relative', marginLeft: '0.5rem' }}>
          <button
            onClick={() => setCompanySelectorOpen(!companySelectorOpen)}
            style={{
              display: 'flex', alignItems: 'center', gap: '0.4rem',
              padding: '4px 10px', borderRadius: '6px', border: '1px solid var(--border)',
              background: 'var(--surface)', cursor: 'pointer', fontSize: '0.8rem',
              color: 'var(--text-h)', fontWeight: 500, maxWidth: 180,
            }}
          >
            <span style={{ width: 8, height: 8, borderRadius: '50%', background: 'var(--accent)', flexShrink: 0 }} />
            <span style={{ overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{activeCompany.name}</span>
            <svg viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" strokeWidth="2"><polyline points="6 9 12 15 18 9"></polyline></svg>
          </button>
          {companySelectorOpen && (
            <div style={{
              position: 'absolute', top: '100%', left: 0, marginTop: '0.25rem',
              background: 'var(--surface)', border: '1px solid var(--border)',
              borderRadius: '8px', boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
              minWidth: 220, zIndex: 1000, padding: '0.25rem',
            }}>
              {companies.map((c) => (
                <button
                  key={c.id}
                  onClick={() => handleSwitchCompany(c)}
                  style={{
                    display: 'flex', alignItems: 'center', gap: '0.5rem', width: '100%',
                    padding: '0.5rem 0.75rem', border: 'none', background: c.id === activeCompany.id ? 'var(--accent-bg)' : 'transparent',
                    borderRadius: '6px', cursor: 'pointer', fontSize: '0.85rem', color: 'var(--text-h)',
                    textAlign: 'left',
                  }}
                >
                  <span style={{ width: 8, height: 8, borderRadius: '50%', background: c.id === activeCompany.id ? 'var(--accent)' : 'var(--border)' }} />
                  <div>
                    <div style={{ fontWeight: 500 }}>{c.name}</div>
                    <div style={{ fontSize: '0.75rem', color: 'var(--text)' }}>{c.siren}</div>
                  </div>
                </button>
              ))}
              <div style={{ borderTop: '1px solid var(--border)', marginTop: '0.25rem', paddingTop: '0.25rem' }}>
                <Link
                  to="/settings"
                  onClick={() => setCompanySelectorOpen(false)}
                  style={{
                    display: 'block', padding: '0.5rem 0.75rem', borderRadius: '6px',
                    fontSize: '0.85rem', color: 'var(--accent)', textDecoration: 'none',
                    fontWeight: 500,
                  }}
                >
                  + Ajouter une entreprise
                </Link>
              </div>
            </div>
          )}
        </div>
      )}

      {/* Barre de recherche desktop */}
      <button onClick={onOpenCommand} className="navbar-search">
        <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line></svg>
        <span>{intl.formatMessage({ id: 'nav.search', defaultMessage: 'Rechercher...' })}</span>
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

      {/* Selecteur de langue */}
      <div ref={localeRef} style={{ position: 'relative' }}>
        <button
          onClick={() => setLocaleSelectorOpen(!localeSelectorOpen)}
          className="navbar-icon-btn"
          title="Langue"
          style={{ fontSize: '0.85rem', fontWeight: 600 }}
        >
          {supportedLocales.find(l => l.code === locale)?.flag || '🌐'}
        </button>
        {localeSelectorOpen && (
          <div style={{
            position: 'absolute', top: '100%', right: 0, marginTop: '0.25rem',
            background: 'var(--surface)', border: '1px solid var(--border)',
            borderRadius: '8px', boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
            minWidth: 160, zIndex: 1000, padding: '0.25rem',
          }}>
            {supportedLocales.map((l) => (
              <button
                key={l.code}
                onClick={() => { setLocale(l.code); setLocaleSelectorOpen(false); }}
                style={{
                  display: 'flex', alignItems: 'center', gap: '0.5rem', width: '100%',
                  padding: '0.5rem 0.75rem', border: 'none',
                  background: locale === l.code ? 'var(--accent-bg)' : 'transparent',
                  borderRadius: '6px', cursor: 'pointer', fontSize: '0.85rem',
                  color: 'var(--text-h)', textAlign: 'left',
                }}
              >
                <span>{l.flag}</span>
                <span>{l.label}</span>
              </button>
            ))}
          </div>
        )}
      </div>

      {/* Liens desktop — prefetch on hover */}
      <div className="navbar-links">
        <Link to="/invoices" onMouseEnter={() => prefetchRoute('/invoices')} onFocus={() => prefetchRoute('/invoices')} className={`navbar-link${location.pathname.startsWith('/invoices') ? ' active' : ''}`}>{intl.formatMessage({ id: 'nav.invoices', defaultMessage: 'Factures' })}</Link>
        <Link to="/quotes" onMouseEnter={() => prefetchRoute('/quotes')} onFocus={() => prefetchRoute('/quotes')} className={`navbar-link${location.pathname.startsWith('/quotes') ? ' active' : ''}`}>{intl.formatMessage({ id: 'nav.quotes', defaultMessage: 'Devis' })}</Link>
        <Link to="/clients" onMouseEnter={() => prefetchRoute('/clients')} onFocus={() => prefetchRoute('/clients')} className={`navbar-link${location.pathname.startsWith('/clients') ? ' active' : ''}`}>{intl.formatMessage({ id: 'nav.clients', defaultMessage: 'Clients' })}</Link>
        <Link to="/settings" onMouseEnter={() => prefetchRoute('/settings')} onFocus={() => prefetchRoute('/settings')} className={`navbar-link${location.pathname === '/settings' ? ' active' : ''}`}>{intl.formatMessage({ id: 'nav.settings', defaultMessage: 'Parametres' })}</Link>
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
          <Link to="/" className="navbar-mobile-link">{intl.formatMessage({ id: 'nav.dashboard', defaultMessage: 'Tableau de bord' })}</Link>
          <Link to="/invoices" onTouchStart={() => prefetchRoute('/invoices')} className="navbar-mobile-link">{intl.formatMessage({ id: 'nav.invoices', defaultMessage: 'Factures' })}</Link>
          <Link to="/quotes" onTouchStart={() => prefetchRoute('/quotes')} className="navbar-mobile-link">{intl.formatMessage({ id: 'nav.quotes', defaultMessage: 'Devis' })}</Link>
          <Link to="/clients" onTouchStart={() => prefetchRoute('/clients')} className="navbar-mobile-link">{intl.formatMessage({ id: 'nav.clients', defaultMessage: 'Clients' })}</Link>
          <Link to="/admin-hub" onTouchStart={() => prefetchRoute('/admin-hub')} className="navbar-mobile-link">Hub admin</Link>
          <Link to="/settings" onTouchStart={() => prefetchRoute('/settings')} className="navbar-mobile-link">{intl.formatMessage({ id: 'nav.settings', defaultMessage: 'Parametres' })}</Link>
          <button onClick={() => { onOpenCommand(); setMobileMenuOpen(false); }} className="navbar-mobile-link" style={{ background: 'none', border: 'none', textAlign: 'left', cursor: 'pointer', font: 'inherit', color: 'inherit' }}>
            {intl.formatMessage({ id: 'nav.search', defaultMessage: 'Rechercher' })}
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

      // 3. Raccourcis Shift+C pour creer (hors inputs)
      if (!isInput && e.shiftKey && e.key === 'C') {
        e.preventDefault();
        globalThis.location.href = '/invoices/new';
      }
    };
    globalThis.addEventListener('keydown', handleKeyDown);
    return () => globalThis.removeEventListener('keydown', handleKeyDown);
  }, []);

  return (
    <>
      <AmbientBackground />
      <VoiceAssistant />
      {location.pathname !== '/invoices/new' && location.pathname !== '/login' && location.pathname !== '/register' && (
        <>
          <NavBar onOpenCommand={() => setCmdOpen(true)} />
          <FloatingActionButton onOpenSearch={() => setCmdOpen(true)} />
        </>
      )}
      <CommandMenu isOpen={cmdOpen} onClose={() => setCmdOpen(false)} />
      <Suspense fallback={<LazyFallback />}>
      <AnimatePresence mode="wait">
        <Routes location={location} key={location.pathname}>
          <Route path="/" element={<PageTransition><HomePage /></PageTransition>} />
          <Route path="/login" element={<PageTransition><Login /></PageTransition>} />
          <Route path="/register" element={<PageTransition><Register /></PageTransition>} />
          <Route path="/invoices" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><InvoiceList /></div></PageTransition></ProtectedRoute>} />
          <Route path="/invoices/new" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><InvoiceCreate /></div></PageTransition></ProtectedRoute>} />
          <Route path="/invoices/:id" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><InvoiceDetail /></div></PageTransition></ProtectedRoute>} />
          <Route path="/clients" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><ClientList /></div></PageTransition></ProtectedRoute>} />
          <Route path="/settings" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Settings /></div></PageTransition></ProtectedRoute>} />
          <Route path="/quotes" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><QuoteList /></div></PageTransition></ProtectedRoute>} />
          <Route path="/quotes/new" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><QuoteCreate /></div></PageTransition></ProtectedRoute>} />
          <Route path="/quotes/:id" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><QuoteDetail /></div></PageTransition></ProtectedRoute>} />
          <Route path="/pricing" element={<PageTransition><div className="page-wrapper"><Pricing /></div></PageTransition>} />
          <Route path="/admin-hub" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><AdminHub /></div></PageTransition></ProtectedRoute>} />
          <Route path="/accounting" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Accounting /></div></PageTransition></ProtectedRoute>} />
          <Route path="/declarations" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Declarations /></div></PageTransition></ProtectedRoute>} />
          <Route path="/banking" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Banking /></div></PageTransition></ProtectedRoute>} />
          <Route path="/unpaid" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Unpaid /></div></PageTransition></ProtectedRoute>} />
          <Route path="/simulators" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Simulators /></div></PageTransition></ProtectedRoute>} />
          <Route path="/accountant" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><AccountantPortal /></div></PageTransition></ProtectedRoute>} />
          <Route path="/api-settings" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><ApiSettings /></div></PageTransition></ProtectedRoute>} />
          <Route path="/assistant" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><AssistantPage /></div></PageTransition></ProtectedRoute>} />
          <Route path="/benchmarks" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Benchmarks /></div></PageTransition></ProtectedRoute>} />
          <Route path="/autopilot" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><AutopilotConfig /></div></PageTransition></ProtectedRoute>} />
          <Route path="/network" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><Network /></div></PageTransition></ProtectedRoute>} />
          <Route path="/portal/:token" element={<PageTransition><div className="page-wrapper"><ClientPortal /></div></PageTransition>} />
          <Route path="/creer-entreprise" element={<PageTransition><div className="page-wrapper"><CompanyCreation /></div></PageTransition>} />
          <Route path="/onboarding" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><OnboardingWizard /></div></PageTransition></ProtectedRoute>} />
          <Route path="/scanner" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><CameraScanner /></div></PageTransition></ProtectedRoute>} />
          <Route path="/api-docs" element={<ProtectedRoute><PageTransition><div className="page-wrapper"><ApiDocs /></div></PageTransition></ProtectedRoute>} />
          <Route path="/experts" element={<PageTransition><div className="page-wrapper"><Experts /></div></PageTransition>} />
          <Route path="/guide" element={<PageTransition><Guide /></PageTransition>} />
        </Routes>
      </AnimatePresence>
      </Suspense>
    </>
  );
}

function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider>
        <AppIntlProvider>
          <AuthProvider>
            <AudioProvider>
              <ToastProvider>
                <BrowserRouter>
                  <AnimatedAppCore />
                </BrowserRouter>
              </ToastProvider>
            </AudioProvider>
          </AuthProvider>
        </AppIntlProvider>
      </ThemeProvider>
    </ErrorBoundary>
  );
}

export default App;
