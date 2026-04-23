import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import './Landing.css';

/* ─── Scroll reveal ─── */
function useScrollReveal() {
  useEffect(() => {
    const obs = new IntersectionObserver(
      (entries) => entries.forEach((e) => {
        if (e.isIntersecting) { e.target.classList.add('reveal-visible'); obs.unobserve(e.target); }
      }),
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
    );
    document.querySelectorAll('.lp .reveal').forEach((el) => obs.observe(el));
    return () => obs.disconnect();
  }, []);
}

/* ─── Countdown → 1er sept 2026 ─── */
function useCountdown() {
  const target = new Date('2026-09-01T00:00:00+02:00').getTime();
  const calc = useCallback(() => {
    const d = Math.max(0, target - Date.now());
    return {
      days: Math.floor(d / 86_400_000),
      hours: Math.floor((d % 86_400_000) / 3_600_000),
      minutes: Math.floor((d % 3_600_000) / 60_000),
      seconds: Math.floor((d % 60_000) / 1_000),
    };
  }, [target]);
  const [time, setTime] = useState(calc);
  useEffect(() => { const id = setInterval(() => setTime(calc()), 1_000); return () => clearInterval(id); }, [calc]);
  return time;
}

/* ═══════════════════════════════════════════
   SVG ICONS (Lucide-style)
   ═══════════════════════════════════════════ */
function IconZap() {
  return <svg className="lp-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2" /></svg>;
}
function IconStarFilled() {
  return <svg className="lp-icon" width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" /></svg>;
}
function IconFlag() {
  return <svg className="lp-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" /><line x1="4" y1="22" x2="4" y2="15" /></svg>;
}
function IconXCircle() {
  return <svg className="lp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><line x1="15" y1="9" x2="9" y2="15" /><line x1="9" y1="9" x2="15" y2="15" /></svg>;
}
function IconCheckCircle() {
  return <svg className="lp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14" /><polyline points="22 4 12 14.01 9 11.01" /></svg>;
}
function IconLightbulb() {
  return <svg className="lp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M9 18h6" /><path d="M10 22h4" /><path d="M15.09 14c.18-.98.65-1.74 1.41-2.5A4.65 4.65 0 0 0 18 8 6 6 0 0 0 6 8c0 1 .23 2.23 1.5 3.5A4.61 4.61 0 0 1 8.91 14" /></svg>;
}
function IconPlay() {
  return <svg className="lp-icon" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="6 3 20 12 6 21 6 3" /></svg>;
}
function IconCheck() {
  return <svg className="lp-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12" /></svg>;
}
function IconAlertTriangle() {
  return <svg className="lp-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" /></svg>;
}
function IconLaptop() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m16 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16" /></svg>;
}
function IconTool() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z" /></svg>;
}
function IconBuilding() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect width="16" height="20" x="4" y="2" rx="2" ry="2" /><path d="M9 22v-4h6v4" /><path d="M8 6h.01" /><path d="M16 6h.01" /><path d="M12 6h.01" /><path d="M8 10h.01" /><path d="M16 10h.01" /><path d="M12 10h.01" /><path d="M8 14h.01" /><path d="M16 14h.01" /><path d="M12 14h.01" /></svg>;
}
function IconLandmark() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><line x1="3" x2="21" y1="22" y2="22" /><line x1="6" x2="6" y1="18" y2="11" /><line x1="10" x2="10" y1="18" y2="11" /><line x1="14" x2="14" y1="18" y2="11" /><line x1="18" x2="18" y1="18" y2="11" /><polygon points="12 2 20 7 4 7" /><line x1="2" x2="22" y1="18" y2="18" /></svg>;
}
function IconHome() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" /><polyline points="9 22 9 12 15 12 15 22" /></svg>;
}
function IconCode() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6" /><polyline points="8 6 2 12 8 18" /></svg>;
}
function IconShield() {
  return <svg className="lp-icon-lg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z" /></svg>;
}
function IconGithub() {
  return <svg className="lp-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 22v-4a4.8 4.8 0 0 0-1-3.5c3 0 6-2 6-5.5.08-1.25-.27-2.48-1-3.5.28-1.15.28-2.35 0-3.5 0 0-1 0-3 1.5-2.64-.5-5.36-.5-8 0C6 2 5 2 5 2c-.3 1.15-.3 2.35 0 3.5A5.403 5.403 0 0 0 4 9c0 3.5 3 5.5 6 5.5-.39.49-.68 1.05-.85 1.65-.17.6-.22 1.23-.15 1.85v4" /><path d="M9 18c-4.51 2-5-2-7-2" /></svg>;
}

function StarRating() {
  return (
    <div className="lp-testimonial-stars" aria-label="5 étoiles sur 5">
      {Array.from({ length: 5 }, (_, i) => (
        <svg key={`star-${i}`} width="16" height="16" viewBox="0 0 24 24" fill="currentColor" stroke="none" aria-hidden="true"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" /></svg>
      ))}
    </div>
  );
}

/* ─── Tab data ─── */
const tabsData = [
  {
    label: 'Facturation conforme',
    title: 'Vos factures respectent la loi à la virgule près',
    items: [
      'Factur-X PDF/A-3 avec XML CII D16B embarqué',
      'UBL 2.1 conforme Peppol BIS Billing 3.0',
      'CII D16B standalone',
      'Profil EN 16931 garanti',
      'Numérotation séquentielle sans trou (FA-2026-0001)',
      'Mentions légales auto-détectées selon votre statut : micro-entrepreneur, EI, SAS, SARL, SCI...',
      'Multi-taux TVA (0 %, 2,1 %, 5,5 %, 10 %, 20 %)',
    ],
    cta: 'Créer ma première facture →',
    visual: 'Formulaire de création de facture',
  },
  {
    label: 'Transmission auto',
    title: 'Chorus Pro intégré. Zéro action manuelle.',
    items: [
      'Connexion directe à Chorus Pro via API PISTE (OAuth2)',
      'Transmission automatique à l\'émission de la facture',
      'Suivi du statut en temps réel : Brouillon → Envoyée → Reçue → Payée',
      'Webhook de callback à chaque changement de statut',
      'Mode sandbox pour tester avant la production',
    ],
    cta: 'En savoir plus sur Chorus Pro →',
    visual: 'Timeline des statuts de transmission',
  },
  {
    label: 'Archivage & sécurité',
    title: '10 ans d\'archivage légal. Inclus dans tous les plans.',
    items: [
      'Stockage S3 en France (Scaleway, région Paris)',
      'Hash SHA-256 sur chaque document pour l\'intégrité',
      'Versioning activé : aucune suppression possible',
      'Piste d\'audit fiable immutable (chaque action horodatée)',
      'Conforme à l\'article 289 VII du Code Général des Impôts',
      'Authentification JWT RS256 + isolation multi-tenant',
    ],
    cta: 'Consulter notre politique de sécurité →',
    visual: 'Timeline de la piste d\'audit fiable',
  },
  {
    label: 'Comptabilité & pilotage',
    title: 'Bien plus qu\'un outil de facturation.',
    items: [
      'Devis convertibles en facture en 1 clic',
      'Relances automatiques (J-3, J+1, J+7, J+30)',
      'Synchronisation bancaire (Open Banking)',
      'Réconciliation intelligente paiements ↔ factures',
      'Comptabilité automatisée (plan comptable PCG)',
      'Export FEC conforme pour votre comptable',
      'Déclarations TVA (CA3/CA12) et URSSAF pré-remplies',
      'Dashboard trésorerie prédictive à J+30, J+60, J+90',
    ],
    cta: 'Découvrir tous les plans →',
    visual: 'Dashboard CA et trésorerie prédictive',
  },
];

/* ─── FAQ data ─── */
const faqItems = [
  { q: 'Est-ce vraiment gratuit ?', a: '10 factures par mois, Chorus Pro, archivage 10 ans, les 3 formats réglementaires — sans carte bancaire, sans engagement, sans durée limitée. Le plan Gratuit n\'est pas un essai : c\'est un vrai plan permanent.' },
  { q: 'Suis-je obligé de passer à la facturation électronique ?', a: 'Oui. Le décret impose la réception dès le 1er septembre 2026 et l\'émission progressivement jusqu\'en septembre 2027. Toutes les entreprises françaises sont concernées, quelle que soit leur taille ou leur statut.' },
  { q: 'Qu\'est-ce que le Factur-X ?', a: 'Un format hybride : un PDF lisible par un humain avec un fichier XML lisible par une machine, embarqué dedans. C\'est le format recommandé par la DGFiP pour les PME. Ma Facture Pro le génère automatiquement à chaque facture.' },
  { q: 'Je suis auto-entrepreneur, c\'est adapté ?', a: 'C\'est conçu pour vous. La mention « TVA non applicable, art. 293 B du CGI » est pré-remplie. Le plan Gratuit couvre largement 5 à 10 factures par mois.' },
  { q: 'Comment fonctionne la connexion à Chorus Pro ?', a: 'Ma Facture Pro est connecté via l\'API PISTE (OAuth2). Quand vous émettez une facture, elle est transmise automatiquement. Le statut se met à jour en temps réel. Aucune manipulation de votre part.' },
  { q: 'Mes données sont-elles en sécurité ?', a: 'Archivage S3 en France (Scaleway Paris). Hash SHA-256. Versioning activé. Conservation 10 ans. Authentification JWT RS256. Code source ouvert et auditable sur GitHub.' },
  { q: 'Puis-je migrer depuis un autre outil ?', a: 'Oui. Ma Facture Pro lit les factures Factur-X entrantes. Comptez 15 à 20 minutes pour saisir vos clients et produits. Nous travaillons sur un import automatique.' },
  { q: 'C\'est quoi le label DGFiP « Solution compatible » ?', a: 'Un label de l\'administration fiscale qui certifie qu\'un logiciel respecte toutes les exigences de la réforme. Notre dossier de candidature est déposé.' },
];

/* ─── Profile cards ─── */
const profiles = [
  { icon: <IconLaptop />, title: 'Freelance / Auto-entrepreneur', sub: 'Mention art. 293B pré-remplie. Plan gratuit suffisant.', link: 'Découvrir le plan Gratuit' },
  { icon: <IconTool />, title: 'Artisan / Commerçant', sub: 'Multi-taux TVA. Relances automatiques.', link: 'Découvrir le plan Pro' },
  { icon: <IconBuilding />, title: 'TPE / PME (1-50 salariés)', sub: 'Multi-utilisateurs. Comptabilité automatisée.', link: 'Découvrir le plan Équipe' },
  { icon: <IconLandmark />, title: 'Cabinet comptable', sub: 'Portail multi-clients. Export FEC groupé. White-label.', link: 'Découvrir le plan Cabinet' },
  { icon: <IconHome />, title: 'SCI / LMNP', sub: 'Factures fournisseurs conformes. Archivage inclus.', link: 'Découvrir le plan Gratuit' },
];

/* ═══════════════════════════════════════════
   COMPONENT
   ═══════════════════════════════════════════ */
export default function Landing() {
  useScrollReveal();
  const countdown = useCountdown();
  const [menuOpen, setMenuOpen] = useState(false);
  const [openFaq, setOpenFaq] = useState<number | null>(null);
  const [scrolled, setScrolled] = useState(false);
  const [topbarVisible, setTopbarVisible] = useState(true);
  const [activeTab, setActiveTab] = useState(0);

  useEffect(() => {
    const onScroll = () => setScrolled(globalThis.scrollY > 20);
    globalThis.addEventListener('scroll', onScroll, { passive: true });
    return () => globalThis.removeEventListener('scroll', onScroll);
  }, []);

  const closeMenu = () => setMenuOpen(false);

  return (
    <div className="lp">
      <a href="#main-content" className="lp-skip-nav">Aller au contenu principal</a>

      {/* ───── 1. TOPBAR ───── */}
      {topbarVisible && (
        <div className="lp-topbar">
          <div className="lp-topbar-inner">
            <span><IconZap /> Réforme facturation électronique : obligation légale dès le 1er septembre 2026. Êtes-vous prêt ?</span>
            <Link to="/register">Vérifier ma conformité gratuitement →</Link>
          </div>
          <button className="lp-topbar-close" onClick={() => setTopbarVisible(false)} aria-label="Fermer le bandeau">×</button>
        </div>
      )}

      {/* ───── 2. NAVBAR ───── */}
      <nav className={`lp-nav${scrolled ? ' scrolled' : ''}`} aria-label="Navigation principale">
        <span className="lp-nav-logo">Ma Facture Pro</span>
        <div className="lp-nav-links">
          <a href="#fonctionnalites" className="lp-nav-link">Fonctionnalités</a>
          <a href="#tarifs" className="lp-nav-link">Tarifs</a>
          <a href="#conformite" className="lp-nav-link">Conformité</a>
          <a href="#faq" className="lp-nav-link">FAQ</a>
        </div>
        <div className="lp-nav-actions">
          <Link to="/login" className="lp-nav-login">Connexion</Link>
          <Link to="/register" className="lp-btn lp-btn-primary">Créer mon compte gratuit</Link>
        </div>
        <button className={`lp-hamburger${menuOpen ? ' open' : ''}`} onClick={() => setMenuOpen(!menuOpen)} aria-label="Menu" aria-expanded={menuOpen}>
          <span /><span /><span />
        </button>
      </nav>

      {menuOpen && (
        <dialog className="lp-mobile-menu" open aria-label="Menu de navigation">
          <a href="#fonctionnalites" className="lp-mobile-link" onClick={closeMenu}>Fonctionnalités</a>
          <a href="#tarifs" className="lp-mobile-link" onClick={closeMenu}>Tarifs</a>
          <a href="#conformite" className="lp-mobile-link" onClick={closeMenu}>Conformité</a>
          <a href="#faq" className="lp-mobile-link" onClick={closeMenu}>FAQ</a>
          <Link to="/login" className="lp-mobile-link" onClick={closeMenu}>Connexion</Link>
          <div className="lp-mobile-cta">
            <Link to="/register" className="lp-btn lp-btn-primary" style={{ width: '100%' }} onClick={closeMenu}>Créer mon compte gratuit</Link>
          </div>
        </dialog>
      )}

      <main id="main-content">
        {/* ───── 3. HERO ───── */}
        <section className="lp-hero">
          <div className="lp-hero-inner">
            <div className="lp-hero-text">
              <div className="lp-hero-badge reveal">
                <IconCheckCircle />
                Candidat au label DGFiP « Solution compatible »
              </div>
              <h1 className="reveal reveal-d1">
                Facturation électronique conforme.<br />Gratuit dès maintenant
              </h1>
              <p className="lp-hero-sub reveal reveal-d2">
                Générez vos factures Factur-X, UBL et CII en quelques clics.
                Transmettez-les automatiquement via Chorus Pro.
                Archivage légal 10 ans inclus — même dans le plan gratuit.
              </p>
              <div className="lp-hero-ctas reveal reveal-d3">
                <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">Créer ma première facture conforme →</Link>
                <a href="#demo" className="lp-hero-demo-link"><IconPlay /> Voir le produit en action — démo 90 secondes</a>
              </div>
              <p className="lp-hero-trust reveal reveal-d4">
                Gratuit jusqu'à 10 factures/mois · Sans carte bancaire · Prêt en 2 minutes
              </p>
              <div className="lp-hero-social reveal reveal-d5">
                <span><IconStarFilled /> 4.9 Trustpilot</span>
                <span><IconGithub /> Open-source sur GitHub</span>
                <span><IconFlag /> Hébergé en France</span>
              </div>
            </div>

            {/* Mockup facture réaliste */}
            <div className="lp-hero-visual reveal reveal-d2">
              <div className="lp-hero-mockup-bar">
                <span className="lp-hero-mockup-dot" style={{ background: '#ef4444' }} />
                <span className="lp-hero-mockup-dot" style={{ background: '#f59e0b' }} />
                <span className="lp-hero-mockup-dot" style={{ background: '#22c55e' }} />
                <span className="lp-mockup-bar-title">Facture — Ma Facture Pro</span>
              </div>
              <div className="lp-hero-mockup-content">
                {/* En-tête facture */}
                <div className="lp-mockup-top">
                  <div className="lp-mockup-brand">
                    <span className="lp-mockup-brand-logo">SM</span>
                    <div className="lp-mockup-brand-info">
                      <span className="lp-mockup-company">Sophie Martin</span>
                      <span className="lp-mockup-small">Conseil &amp; Formation</span>
                      <span className="lp-mockup-small">18 rue des Lilas, 75011 Paris</span>
                      <span className="lp-mockup-small">SIRET 823 745 213 00017 · TVA FR 82 823745213</span>
                    </div>
                  </div>
                  <div className="lp-mockup-meta">
                    <span className="lp-mockup-id">FA-2026-0042</span>
                    <span className="lp-mockup-date">11 avril 2026</span>
                    <span className="lp-mockup-badge-fx"><IconCheck /> Factur-X</span>
                  </div>
                </div>

                {/* Client */}
                <div className="lp-mockup-client">
                  <span className="lp-mockup-label">Client</span>
                  <span className="lp-mockup-client-name">Boulangerie Maison Leroy</span>
                  <span className="lp-mockup-small">12 rue du Commerce, 69002 Lyon</span>
                  <span className="lp-mockup-small">SIREN 451 283 907</span>
                </div>

                {/* Lignes de facture */}
                <table className="lp-mockup-table">
                  <thead>
                    <tr>
                      <th>Description</th>
                      <th>Qté</th>
                      <th>PU HT</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Accompagnement stratégique</td>
                      <td>3j</td>
                      <td>450,00 €</td>
                      <td>1 350,00 €</td>
                    </tr>
                    <tr>
                      <td>Formation équipe (sur site)</td>
                      <td>1</td>
                      <td>680,00 €</td>
                      <td>680,00 €</td>
                    </tr>
                    <tr>
                      <td>Frais de déplacement</td>
                      <td>1</td>
                      <td>85,00 €</td>
                      <td>85,00 €</td>
                    </tr>
                  </tbody>
                </table>

                {/* Totaux */}
                <div className="lp-mockup-totals">
                  <div className="lp-mockup-total-row">
                    <span>Sous-total HT</span>
                    <span>2 115,00 €</span>
                  </div>
                  <div className="lp-mockup-total-row">
                    <span>TVA 20 %</span>
                    <span>423,00 €</span>
                  </div>
                  <div className="lp-mockup-total-row lp-mockup-total-final">
                    <span>Total TTC</span>
                    <span>2 538,00 €</span>
                  </div>
                </div>

                {/* Paiement */}
                <div className="lp-mockup-payment-info">
                  <span className="lp-mockup-small">Échéance : 11 mai 2026</span>
                  <span className="lp-mockup-small">IBAN : FR76 3000 4028 3700 0100 0425 082</span>
                  <span className="lp-mockup-small">Conditions : paiement à 30 jours</span>
                </div>

                {/* Badges statut */}
                <div className="lp-mockup-footer">
                  <div className="lp-mockup-stamp">
                    <IconCheckCircle />
                    <span className="lp-mockup-stamp-text">
                      <span className="lp-mockup-stamp-label">Transmise à</span>
                      <span className="lp-mockup-stamp-value">Chorus Pro</span>
                    </span>
                  </div>
                  <div className="lp-mockup-stamp lp-mockup-stamp-archive">
                    <svg className="lp-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect width="18" height="11" x="3" y="11" rx="2" ry="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                    <span className="lp-mockup-stamp-text">
                      <span className="lp-mockup-stamp-label">Archivée 10 ans</span>
                      <span className="lp-mockup-stamp-value">SHA-256 · France</span>
                    </span>
                  </div>
                </div>

                {/* Conformité */}
                <div className="lp-mockup-conformity">
                  Conforme DGFiP · Décret 2022-1299 · EN 16931
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 4. TRUST BANNER ───── */}
        <section className="lp-trust" id="conformite">
          <p className="lp-trust-title">Conforme aux exigences de la réforme</p>
          <div className="lp-trust-badges">
            <span className="lp-trust-badge">Chorus Pro</span>
            <span className="lp-trust-badge">Factur-X</span>
            <span className="lp-trust-badge">EN 16931</span>
            <span className="lp-trust-badge">UBL 2.1</span>
            <span className="lp-trust-badge">Stripe</span>
            <span className="lp-trust-badge">Open Source MIT</span>
          </div>
        </section>

        {/* ───── 5. PROBLEM ───── */}
        <section className="lp-problem">
          <div className="lp-problem-inner">
            <h2 className="reveal">Dans {countdown.days} jours, vos factures PDF ne suffiront plus.</h2>
            <p className="lp-problem-subtitle reveal reveal-d1">
              La réforme de la facturation électronique entre en vigueur le 1er septembre 2026. Voici ce que ça change pour vous.
            </p>
            <div className="lp-problem-grid">
              <div className="lp-problem-col bad reveal reveal-d2">
                <h3>Ce qui ne sera plus accepté</h3>
                <ul className="lp-problem-list">
                  <li><span className="icon icon-red"><IconXCircle /></span>Les factures PDF classiques envoyées par email</li>
                  <li><span className="icon icon-red"><IconXCircle /></span>Les logiciels qui ne génèrent pas de Factur-X ou d'UBL</li>
                  <li><span className="icon icon-red"><IconXCircle /></span>L'absence de connexion à une plateforme agréée (PDP)</li>
                  <li><span className="icon icon-red"><IconXCircle /></span>Les factures sans piste d'audit fiable</li>
                  <li><span className="icon icon-red"><IconXCircle /></span>L'archivage « dans un dossier sur mon ordinateur »</li>
                </ul>
              </div>
              <div className="lp-problem-col good reveal reveal-d3">
                <h3>Ce que la loi impose désormais</h3>
                <ul className="lp-problem-list">
                  <li><span className="icon icon-green"><IconCheckCircle /></span>Un format structuré : Factur-X, UBL 2.1 ou CII D16B</li>
                  <li><span className="icon icon-green"><IconCheckCircle /></span>La transmission via une Plateforme de Dématérialisation Partenaire</li>
                  <li><span className="icon icon-green"><IconCheckCircle /></span>Une piste d'audit fiable et immutable</li>
                  <li><span className="icon icon-green"><IconCheckCircle /></span>Un archivage légal de 10 ans minimum</li>
                  <li><span className="icon icon-green"><IconCheckCircle /></span>Les 25+ mentions obligatoires du Décret 2022-1299</li>
                </ul>
              </div>
            </div>
            <div className="lp-callout reveal reveal-d4">
              <strong><IconLightbulb />{' '}Qui est concerné ?</strong>{' '}Toutes les entreprises françaises : freelances, auto-entrepreneurs, TPE, PME, professions libérales, artisans, SCI, LMNP. Environ 8 millions d'acteurs économiques.
            </div>
            <div style={{ textAlign: 'center', marginTop: '1.5rem' }} className="reveal reveal-d5">
              <Link to="/register" className="lp-btn lp-btn-primary">Vérifier si mon outil actuel est conforme →</Link>
            </div>
          </div>
        </section>

        {/* ───── 6. STEPS ───── */}
        <section className="lp-steps">
          <div className="lp-steps-inner">
            <h2 className="reveal">Conforme en 3 étapes. Prêt en 2 minutes.</h2>
            <div className="lp-steps-grid">
              <div className="lp-step-card reveal reveal-d1">
                <div className="lp-step-num">01</div>
                <h3>Créez votre compte</h3>
                <p>Renseignez votre SIREN, votre statut juridique et vos coordonnées bancaires. Ma Facture Pro pré-remplit automatiquement vos mentions légales obligatoires. Temps estimé : 2 minutes.</p>
              </div>
              <div className="lp-step-card reveal reveal-d2">
                <div className="lp-step-num">02</div>
                <h3>Créez votre facture</h3>
                <p>Ajoutez votre client, vos lignes de prestation. Ma Facture Pro calcule la TVA, génère le Factur-X, l'UBL et le CII automatiquement. Vous n'avez rien à configurer.</p>
              </div>
              <div className="lp-step-card reveal reveal-d3">
                <div className="lp-step-num">03</div>
                <h3>On s'occupe du reste</h3>
                <p>Un clic sur « Envoyer ». Votre facture est transmise à Chorus Pro, archivée 10 ans en France, et tracée dans votre piste d'audit fiable. Votre client la reçoit instantanément.</p>
              </div>
            </div>
            <div style={{ textAlign: 'center', marginTop: '2rem' }} className="reveal reveal-d4">
              <Link to="/register" className="lp-btn lp-btn-white lp-btn-primary-lg">Créer mon compte gratuit →</Link>
            </div>
          </div>
        </section>

        {/* ───── 7. FEATURES TABS ───── */}
        <section className="lp-features" id="fonctionnalites">
          <div className="lp-features-inner">
            <h2 className="reveal">Tout ce qu'il vous faut. Rien de superflu.</h2>
            <div className="lp-tabs reveal reveal-d1" role="tablist">
              {tabsData.map((tab, i) => (
                <button
                  key={tab.label}
                  role="tab"
                  aria-selected={activeTab === i}
                  className={`lp-tab${activeTab === i ? ' active' : ''}`}
                  onClick={() => setActiveTab(i)}
                >
                  {tab.label}
                </button>
              ))}
            </div>
            <div className="lp-tab-content reveal reveal-d2" role="tabpanel">
              <div className="lp-tab-text">
                <h3>{tabsData[activeTab].title}</h3>
                <ul className="lp-tab-list">
                  {tabsData[activeTab].items.map((item) => <li key={item}>{item}</li>)}
                </ul>
                <Link to="/register" className="lp-btn lp-btn-primary">{tabsData[activeTab].cta}</Link>
              </div>
              <div className="lp-tab-visual">
                {tabsData[activeTab].visual}
              </div>
            </div>
          </div>
        </section>

        {/* ───── 8. DEMO ───── */}
        <section className="lp-demo" id="demo">
          <div className="lp-demo-inner">
            <h2 className="reveal">Votre première facture Factur-X en 90 secondes</h2>
            <p className="lp-demo-sub reveal reveal-d1">Pas de formation. Pas de paramétrage. Vous renseignez, on génère, Chorus Pro reçoit.</p>
            <div className="lp-demo-visual reveal reveal-d2">
              <div className="lp-demo-placeholder">
                <button className="play-icon" aria-label="Lancer la démo"><IconPlay /></button>
                <span>Démo interactive — bientôt disponible</span>
              </div>
            </div>
            <Link to="/register" className="lp-btn lp-btn-white reveal reveal-d3">Faire la même chose maintenant — gratuit →</Link>
          </div>
        </section>

        {/* ───── 9. COMPARISON ───── */}
        <section className="lp-compare">
          <div className="lp-compare-inner">
            <h2 className="reveal">Nous proposons une solution complète</h2>
            <div className="reveal reveal-d1">
              <table className="lp-compare-table" role="table">
                <thead>
                  <tr>
                    <th scope="col">Critère</th>
                    <th scope="col">Ma Facture Pro</th>
                    <th scope="col">Outils classiques</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>Prix d'entrée</td><td>0 €/mois</td><td>9–50 €/mois</td></tr>
                  <tr><td>Factur-X + UBL + CII</td><td><span className="lp-status-yes"><IconCheckCircle /> Les 3 formats</span></td><td><span className="lp-status-no"><IconXCircle /> Souvent 1 seul</span></td></tr>
                  <tr><td>Connexion Chorus Pro</td><td><span className="lp-status-yes"><IconCheckCircle /> Automatique</span></td><td><span className="lp-status-no"><IconXCircle /> Manuelle ou absente</span></td></tr>
                  <tr><td>Piste d'audit fiable</td><td><span className="lp-status-yes"><IconCheckCircle /> Immutable</span></td><td><span className="lp-status-no"><IconXCircle /> Souvent absente</span></td></tr>
                  <tr><td>Archivage légal 10 ans</td><td><span className="lp-status-yes"><IconCheckCircle /> S3 France</span></td><td><span className="lp-status-warn"><IconAlertTriangle /> Variable</span></td></tr>
                  <tr><td>Mentions DGFiP auto</td><td><span className="lp-status-yes"><IconCheckCircle /> Pré-remplies</span></td><td><span className="lp-status-no"><IconXCircle /> Saisie manuelle</span></td></tr>
                  <tr><td>Label DGFiP</td><td><span className="lp-status-yes"><IconCheckCircle /> Candidature active</span></td><td><span className="lp-status-no"><IconXCircle /> Rarement</span></td></tr>
                  <tr><td>Code source</td><td><span className="lp-status-yes"><IconCheckCircle /> Open-source (MIT)</span></td><td><span className="lp-status-no"><IconXCircle /> Propriétaire</span></td></tr>
                  <tr><td>Hébergement France</td><td><span className="lp-status-yes"><IconCheckCircle /> Scaleway Paris</span></td><td><span className="lp-status-warn"><IconAlertTriangle /> Pas toujours</span></td></tr>
                </tbody>
              </table>
            </div>
            <p className="lp-compare-note reveal reveal-d2">
              Nous ne nommons personne. Vérifiez par vous-même : demandez à votre outil actuel s'il génère du Factur-X EN 16931 et s'il transmet automatiquement à Chorus Pro.
            </p>
          </div>
        </section>

        {/* ───── 10. PROFILES ───── */}
        <section className="lp-profiles">
          <div className="lp-profiles-inner">
            <h2 className="reveal">Connecté en toute situation</h2>
            <div className="lp-profiles-grid">
              {profiles.map((p, i) => (
                <Link to="/register" key={p.title} className={`lp-profile-card reveal reveal-d${Math.min(i + 1, 5)}`}>
                  <div className="lp-profile-icon">{p.icon}</div>
                  <h3>{p.title}</h3>
                  <p>{p.sub}</p>
                  <span className="lp-profile-link">{p.link} →</span>
                </Link>
              ))}
            </div>
          </div>
        </section>

        {/* ───── 11. PRICING ───── */}
        <section className="lp-pricing" id="tarifs">
          <div className="lp-pricing-inner">
            <div className="lp-pricing-header">
              <h2 className="reveal">Des prix clairs. Pas de « dès ». Pas de surprise.</h2>
              <p className="reveal reveal-d1">La conformité est incluse dans tous les plans — y compris le gratuit. Changez d'avis ? Changez de plan en 1 clic, à tout moment.</p>
            </div>

            <div className="lp-pricing-grid">
              {/* Gratuit */}
              <div className="lp-price-card reveal reveal-d1">
                <div className="lp-price-name">Gratuit</div>
                <div className="lp-price-amount"><span className="lp-price-val">0</span><span className="lp-price-cur"> €</span></div>
                <div className="lp-price-period">pour toujours</div>
                <ul className="lp-price-features">
                  <li><span className="check"><IconCheck /></span>10 factures / mois</li>
                  <li><span className="check"><IconCheck /></span>Factur-X + UBL + CII</li>
                  <li><span className="check"><IconCheck /></span>Chorus Pro intégré</li>
                  <li><span className="check"><IconCheck /></span>Archivage légal 10 ans</li>
                  <li><span className="check"><IconCheck /></span>Piste d'audit fiable</li>
                  <li><span className="check"><IconCheck /></span>1 utilisateur · 1 entreprise</li>
                  <li><span className="check"><IconCheck /></span>Mentions DGFiP pré-remplies</li>
                </ul>
                <Link to="/register" className="lp-btn lp-btn-outline" style={{ width: '100%' }}>Commencer gratuitement</Link>
                <div className="lp-price-trial">Sans carte bancaire</div>
              </div>

              {/* Pro */}
              <div className="lp-price-card featured reveal reveal-d2">
                <div className="lp-price-popular">Le plus populaire</div>
                <div className="lp-price-name">Pro</div>
                <div className="lp-price-amount"><span className="lp-price-val">14</span><span className="lp-price-cur"> €/mois</span></div>
                <div className="lp-price-period">HT, sans engagement</div>
                <div className="lp-price-desc">Tout le plan Gratuit, plus :</div>
                <ul className="lp-price-features">
                  <li><span className="check"><IconCheck /></span>Factures illimitées</li>
                  <li><span className="check"><IconCheck /></span>3 utilisateurs · 3 entreprises</li>
                  <li><span className="check"><IconCheck /></span>PDF personnalisé (votre logo, vos couleurs)</li>
                  <li><span className="check"><IconCheck /></span>Relances automatiques (J-3, J+1, J+7, J+30)</li>
                  <li><span className="check"><IconCheck /></span>Synchronisation bancaire</li>
                  <li><span className="check"><IconCheck /></span>Réconciliation paiements ↔ factures</li>
                  <li><span className="check"><IconCheck /></span>Devis convertibles en facture</li>
                  <li><span className="check"><IconCheck /></span>Support prioritaire</li>
                </ul>
                <Link to="/register" className="lp-btn lp-btn-primary" style={{ width: '100%' }}>Essayer Pro — 14 jours gratuits</Link>
                <div className="lp-price-trial">Sans engagement</div>
              </div>

              {/* Équipe */}
              <div className="lp-price-card reveal reveal-d3">
                <div className="lp-price-name">Équipe</div>
                <div className="lp-price-amount"><span className="lp-price-val">34</span><span className="lp-price-cur"> €/mois</span></div>
                <div className="lp-price-period">HT, sans engagement</div>
                <div className="lp-price-desc">Tout le plan Pro, plus :</div>
                <ul className="lp-price-features">
                  <li><span className="check"><IconCheck /></span>10 utilisateurs · 5 entreprises</li>
                  <li><span className="check"><IconCheck /></span>Comptabilité automatisée (PCG)</li>
                  <li><span className="check"><IconCheck /></span>Déclarations TVA (CA3/CA12) pré-remplies</li>
                  <li><span className="check"><IconCheck /></span>Déclarations URSSAF pré-remplies</li>
                  <li><span className="check"><IconCheck /></span>Export FEC conforme</li>
                  <li><span className="check"><IconCheck /></span>Assistant IA fiscal</li>
                  <li><span className="check"><IconCheck /></span>API publique (10 000 req/h)</li>
                  <li><span className="check"><IconCheck /></span>Webhooks + intégrations</li>
                </ul>
                <Link to="/register" className="lp-btn lp-btn-outline" style={{ width: '100%' }}>Démarrer avec Équipe</Link>
                <div className="lp-price-trial">Sans engagement</div>
              </div>
            </div>

            {/* Cabinet — dark card */}
            <div className="lp-pricing-alt lp-pricing-alt--cabinet reveal reveal-d4">
              <div className="lp-pricing-alt-content">
                <div className="lp-pricing-alt-badge">Plan Cabinet</div>
                <h3>Vous êtes expert-comptable ?</h3>
                <p className="lp-pricing-alt-desc">
                  Gérez tous vos clients depuis un seul portail. Export FEC groupé, white-label, utilisateurs illimités.
                </p>
                <ul className="lp-pricing-alt-perks">
                  <li><IconCheck /> Portail multi-clients</li>
                  <li><IconCheck /> Export FEC groupé</li>
                  <li><IconCheck /> White-label</li>
                  <li><IconCheck /> Utilisateurs illimités</li>
                </ul>
                <a href="mailto:contact@mafacturepro.fr" className="lp-btn lp-btn-white">Demander une démo Cabinet →</a>
              </div>
              <div className="lp-pricing-alt-aside">
                <div className="lp-pricing-alt-price-block">
                  <span className="lp-pricing-alt-val">79</span>
                  <span className="lp-pricing-alt-unit">€/mois</span>
                </div>
                <p className="lp-pricing-alt-sub">+ 2 €/client actif au-delà de 20</p>
                <div className="lp-pricing-alt-sim">
                  <div className="lp-pricing-alt-sim-row">
                    <span>150 clients</span>
                    <span className="lp-pricing-alt-sim-arrow">→</span>
                    <strong>339 €/mois</strong>
                  </div>
                  <span className="lp-pricing-alt-sim-note">soit 2,26 €/client</span>
                </div>
              </div>
            </div>

            {/* Succès — gradient border card */}
            <div className="lp-pricing-alt lp-pricing-alt--success reveal reveal-d5">
              <div className="lp-pricing-alt-content">
                <div className="lp-pricing-alt-badge lp-pricing-alt-badge--success">Plan Succès Partagé</div>
                <h3>Vous préférez payer au succès ?</h3>
                <p className="lp-pricing-alt-desc">
                  Zéro frais fixe. Vous ne payez qu'un micro-pourcentage de votre CA facturé — plafonné pour rester prévisible.
                </p>
                <div className="lp-pricing-alt-cap"><IconShield /> Plafonné à 49 €/mois</div>
                <a href="mailto:contact@mafacturepro.fr" className="lp-btn lp-btn-primary">Simuler mon prix →</a>
              </div>
              <div className="lp-pricing-alt-aside">
                <div className="lp-pricing-alt-price-block">
                  <span className="lp-pricing-alt-val">0</span>
                  <span className="lp-pricing-alt-unit">€ fixe</span>
                </div>
                <p className="lp-pricing-alt-sub">+ 0,1 % du CA au-delà de 50 000 €/an</p>
                <div className="lp-pricing-alt-sim">
                  <div className="lp-pricing-alt-sim-row">
                    <span>CA 80 000 €/an</span>
                    <span className="lp-pricing-alt-sim-arrow">→</span>
                    <strong>30 €/an</strong>
                  </div>
                  <span className="lp-pricing-alt-sim-note">soit 2,50 €/mois</span>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 12. TESTIMONIALS ───── */}
        <section className="lp-testimonials">
          <div className="lp-testimonials-inner">
            <h2 className="reveal">Ils ont anticipé la réforme</h2>
            <div className="lp-testimonials-grid">
              <div className="lp-testimonial-card reveal reveal-d1">
                <StarRating />
                <p className="lp-testimonial-quote">
                  « Inscription en 2 minutes, première facture Factur-X envoyée dans la foulée. Chorus Pro reçoit tout automatiquement. Je ne m'en occupe plus. »
                </p>
                <div className="lp-testimonial-author">
                  <div className="lp-testimonial-avatar" style={{ background: 'linear-gradient(135deg, var(--accent), var(--accent-hover))' }}>SM</div>
                  <div>
                    <div className="lp-testimonial-name">Sophie M.</div>
                    <div className="lp-testimonial-role">Développeuse freelance · Lyon</div>
                  </div>
                </div>
              </div>
              <div className="lp-testimonial-card reveal reveal-d2">
                <StarRating />
                <p className="lp-testimonial-quote">
                  « 120 clients migrés en une semaine. Le portail comptable et l'export FEC groupé me font gagner 2 jours par mois. À 2,26 €/client, c'est imbattable. »
                </p>
                <div className="lp-testimonial-author">
                  <div className="lp-testimonial-avatar" style={{ background: 'linear-gradient(135deg, #0891b2, #22d3ee)' }}>MD</div>
                  <div>
                    <div className="lp-testimonial-name">Marc D.</div>
                    <div className="lp-testimonial-role">Expert-comptable · Paris</div>
                  </div>
                </div>
              </div>
              <div className="lp-testimonial-card reveal reveal-d3">
                <StarRating />
                <p className="lp-testimonial-quote">
                  « Je suis plombier, pas informaticien. J'ai compris le produit en 5 minutes. La TVA se calcule toute seule, les factures partent toutes seules. Parfait. »
                </p>
                <div className="lp-testimonial-author">
                  <div className="lp-testimonial-avatar" style={{ background: 'linear-gradient(135deg, #d97706, #fbbf24)' }}>KB</div>
                  <div>
                    <div className="lp-testimonial-name">Karim B.</div>
                    <div className="lp-testimonial-role">Artisan plombier · Toulouse</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 13. DIFFERENTIATION ───── */}
        <section className="lp-diff">
          <div className="lp-diff-inner">
            <h2 className="reveal">Ce qui nous rend différents</h2>
            <div className="lp-diff-grid">
              <div className="lp-diff-card reveal reveal-d1">
                <div className="lp-diff-icon"><IconCode /></div>
                <h3>Open-source. Auditable. Transparent.</h3>
                <p>Notre code est public sur GitHub sous licence MIT. Pas de boîte noire. Pas de mauvaise surprise. Vous savez exactement comment vos données sont traitées.</p>
              </div>
              <div className="lp-diff-card reveal reveal-d2">
                <div className="lp-diff-icon"><IconShield /></div>
                <h3>Conçu pour la réforme. Pas adapté après coup.</h3>
                <p>Ma Facture Pro n'est pas un outil de comptabilité qui a « ajouté » la facturation électronique. C'est un outil de facturation électronique qui inclut la comptabilité. La nuance change tout.</p>
              </div>
              <div className="lp-diff-card reveal reveal-d3">
                <div className="lp-diff-icon"><IconFlag /></div>
                <h3>Vos factures restent en France. Point.</h3>
                <p>Archivage S3 chez Scaleway, région Paris. Hash SHA-256. Versioning activé. Conforme RGPD et article 289 VII du CGI. Pas de serveur aux États-Unis. Pas de zone grise.</p>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 14. FAQ ───── */}
        <section className="lp-faq" id="faq">
          <div className="lp-faq-inner">
            <h2 className="reveal">Vos questions, nos réponses</h2>
            <div className="lp-faq-grid">
              {[faqItems.slice(0, Math.ceil(faqItems.length / 2)), faqItems.slice(Math.ceil(faqItems.length / 2))].map((col, colIdx) => (
                <div key={colIdx === 0 ? 'faq-left' : 'faq-right'} className="lp-faq-col">
                  {col.map((item, i) => {
                    const idx = colIdx * Math.ceil(faqItems.length / 2) + i;
                    return (
                      <div key={item.q} className={`lp-faq-item${openFaq === idx ? ' open' : ''}`}>
                        <button className="lp-faq-q" onClick={() => setOpenFaq(openFaq === idx ? null : idx)} aria-expanded={openFaq === idx}>
                          <span>{item.q}</span>
                          <svg className="lp-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><polyline points="6 9 12 15 18 9" /></svg>
                        </button>
                        <section className="lp-faq-a" aria-label={item.q}><p>{item.a}</p></section>
                      </div>
                    );
                  })}
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ───── 15. FINAL CTA ───── */}
        <section className="lp-final-cta">
          <div className="lp-final-cta-inner">
            <h2 className="reveal">La réforme n'attend pas.<br />Votre première facture conforme, si.</h2>
            <p className="reveal reveal-d1">
              2 minutes pour créer votre compte. 90 secondes pour votre premier Factur-X.
              0 € pour commencer. 0 excuse pour attendre.
            </p>
            <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg reveal reveal-d2">Créer ma première facture conforme →</Link>
            <p className="lp-final-reassurance reveal reveal-d3">
              Gratuit · Sans carte bancaire · Open-source · Hébergé en France
            </p>
          </div>
        </section>
      </main>

      {/* ───── 16. FOOTER ───── */}
      <footer className="lp-footer">
        <div className="lp-footer-inner">
          <div className="lp-footer-grid">
            <div className="lp-footer-brand">
              <div className="lp-footer-logo">Ma Facture Pro</div>
              <p>La facturation électronique conforme, simple et gratuite.<br /><IconFlag /> Conçu et hébergé en France.<br />Open-source (MIT) sur GitHub.</p>
            </div>
            <div className="lp-footer-col">
              <h4>Produit</h4>
              <ul>
                <li><a href="#fonctionnalites">Fonctionnalités</a></li>
                <li><a href="#tarifs">Tarifs</a></li>
                <li><a href="#conformite">Sécurité & conformité</a></li>
                <li><a href="#faq">Documentation API</a></li>
                <li><a href="#faq">Changelog</a></li>
              </ul>
            </div>
            <div className="lp-footer-col">
              <h4>Ressources</h4>
              <ul>
                <li><Link to="/guide">Guide de la réforme 2026</Link></li>
                <li><a href="#faq">Qu'est-ce que le Factur-X ?</a></li>
                <li><a href="#faq">Centre d'aide</a></li>
                <li><a href="#faq">Statut des services</a></li>
              </ul>
            </div>
            <div className="lp-footer-col">
              <h4>Légal</h4>
              <ul>
                <li><a href="#faq">Mentions légales</a></li>
                <li><a href="#faq">Politique de confidentialité</a></li>
                <li><a href="#faq">Conditions générales</a></li>
              </ul>
            </div>
          </div>
          <div className="lp-footer-bottom">
            © 2026 Ma Facture Pro — Pierre-Arthur Demengel · Code source sous licence MIT
          </div>
        </div>
      </footer>
    </div>
  );
}
