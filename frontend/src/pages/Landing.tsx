import { useEffect, useState, useCallback } from 'react';
import { Link } from 'react-router-dom';
import './Landing.css';

/* ─── Scroll reveal hook ─── */
function useScrollReveal() {
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('reveal-visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' },
    );
    document.querySelectorAll('.lp .reveal').forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, []);
}

/* ─── Countdown hook → 1er septembre 2026 ─── */
function useCountdown() {
  const target = new Date('2026-09-01T00:00:00+02:00').getTime();
  const calc = useCallback(() => {
    const diff = Math.max(0, target - Date.now());
    return {
      days: Math.floor(diff / 86_400_000),
      hours: Math.floor((diff % 86_400_000) / 3_600_000),
      minutes: Math.floor((diff % 3_600_000) / 60_000),
      seconds: Math.floor((diff % 60_000) / 1_000),
    };
  }, [target]);

  const [time, setTime] = useState(calc);

  useEffect(() => {
    const id = setInterval(() => setTime(calc()), 1_000);
    return () => clearInterval(id);
  }, [calc]);

  return time;
}

/* ─── FAQ data ─── */
const faqItems = [
  {
    q: 'Est-ce que Ma Facture Pro est vraiment gratuit ?',
    a: 'Oui. Le plan Gratuit vous donne 10 factures par mois, la connexion Chorus Pro, l\'archivage 10 ans et les 3 formats réglementaires. Sans carte bancaire, sans engagement, sans limite de durée.',
  },
  {
    q: 'Suis-je obligé de passer à la facturation électronique ?',
    a: 'À partir du 1er septembre 2026, toutes les entreprises françaises doivent pouvoir recevoir des factures électroniques. L\'émission devient obligatoire progressivement jusqu\'en septembre 2027. Environ 8 millions d\'acteurs sont concernés.',
  },
  {
    q: 'Qu\'est-ce que le Factur-X exactement ?',
    a: 'C\'est un format hybride : un PDF lisible par un humain avec un fichier XML embarqué lisible par une machine. C\'est le format recommandé par la DGFiP pour les PME. Ma Facture Pro le génère automatiquement à chaque facture.',
  },
  {
    q: 'Comment fonctionne la connexion à Chorus Pro ?',
    a: 'Ma Facture Pro est connecté à Chorus Pro via l\'API PISTE (OAuth2). Quand vous émettez une facture, elle est transmise automatiquement. Vous suivez le statut en temps réel dans votre dashboard. Aucune manipulation n\'est nécessaire.',
  },
  {
    q: 'Mes données sont-elles en sécurité ?',
    a: 'Vos factures sont archivées sur des serveurs S3 en France (Scaleway), avec hash SHA-256, versioning activé et conservation 10 ans. L\'authentification est sécurisée par JWT RS256. Le code source est ouvert et auditable par tous.',
  },
  {
    q: 'Je suis auto-entrepreneur, c\'est adapté pour moi ?',
    a: 'C\'est conçu pour vous. La mention « TVA non applicable — art. 293 B du CGI » est pré-remplie automatiquement. La numérotation séquentielle est gérée. Le plan Gratuit couvre largement les besoins d\'un auto-entrepreneur.',
  },
  {
    q: 'Puis-je migrer depuis un autre outil ?',
    a: 'Oui. Ma Facture Pro lit les factures Factur-X entrantes. Vous pouvez aussi saisir vos clients et produits manuellement — comptez 15 à 20 minutes pour être opérationnel.',
  },
  {
    q: 'C\'est quoi le label DGFiP « Solution compatible » ?',
    a: 'C\'est un label délivré par l\'administration fiscale qui certifie qu\'un logiciel respecte toutes les exigences de la réforme (formats, données obligatoires, raccordement PDP, archivage). Notre dossier de candidature est constitué et déposé.',
  },
];

/* ─── Component ─── */
export default function Landing() {
  useScrollReveal();
  const countdown = useCountdown();
  const [menuOpen, setMenuOpen] = useState(false);
  const [openFaq, setOpenFaq] = useState<number | null>(null);
  const [scrolled, setScrolled] = useState(false);

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 20);
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  // Close mobile menu on anchor click
  const closeMenu = () => setMenuOpen(false);

  const pad = (n: number) => String(n).padStart(2, '0');

  return (
    <div className="lp">
      {/* Skip nav */}
      <a href="#main-content" className="lp-skip-nav">
        Aller au contenu principal
      </a>

      {/* ───── 1. NAVBAR ───── */}
      <nav className={`lp-nav${scrolled ? ' scrolled' : ''}`} aria-label="Navigation principale">
        <span className="lp-nav-logo">Ma Facture Pro</span>

        <div className="lp-nav-links">
          <a href="#fonctionnalites" className="lp-nav-link">Fonctionnalités</a>
          <a href="#tarifs" className="lp-nav-link">Tarifs</a>
          <a href="#faq" className="lp-nav-link">FAQ</a>
        </div>

        <div className="lp-nav-actions">
          <Link to="/login" className="lp-nav-login">Connexion</Link>
          <Link to="/register" className="lp-btn lp-btn-primary">Créer mon compte gratuit</Link>
        </div>

        <button
          className={`lp-hamburger${menuOpen ? ' open' : ''}`}
          onClick={() => setMenuOpen(!menuOpen)}
          aria-label="Ouvrir le menu"
          aria-expanded={menuOpen}
        >
          <span /><span /><span />
        </button>
      </nav>

      {/* Mobile menu */}
      {menuOpen && (
        <div className="lp-mobile-menu" role="dialog" aria-label="Menu de navigation">
          <a href="#fonctionnalites" className="lp-mobile-link" onClick={closeMenu}>Fonctionnalités</a>
          <a href="#tarifs" className="lp-mobile-link" onClick={closeMenu}>Tarifs</a>
          <a href="#faq" className="lp-mobile-link" onClick={closeMenu}>FAQ</a>
          <Link to="/login" className="lp-mobile-link" onClick={closeMenu}>Connexion</Link>
          <div className="lp-mobile-cta">
            <Link to="/register" className="lp-btn lp-btn-primary" style={{ width: '100%' }} onClick={closeMenu}>
              Créer mon compte gratuit
            </Link>
          </div>
        </div>
      )}

      <main id="main-content">
        {/* ───── 2. HERO ───── */}
        <section className="lp-hero">
          <div className="lp-hero-content">
            <div className="lp-hero-badge reveal">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round"><polyline points="20 6 9 17 4 12" /></svg>
              Candidat au label DGFiP « Solution compatible »
            </div>
            <h1 className="reveal reveal-d1">
              La facturation électronique<br />conforme, simple et gratuite.
            </h1>
            <p className="lp-hero-sub reveal reveal-d2">
              Créez vos factures Factur-X, UBL et CII en quelques clics.
              Transmettez-les automatiquement via Chorus Pro.
              Soyez prêt pour la réforme de septembre 2026 — sans effort.
            </p>
            <div className="lp-hero-ctas reveal reveal-d3">
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Créer mon compte gratuit →
              </Link>
              <a href="#demo" className="lp-hero-demo">
                Voir une démo en 90 secondes ▶
              </a>
            </div>
            <p className="lp-hero-trust reveal reveal-d4">
              Gratuit jusqu'à 10 factures/mois
              <span> · </span>Sans carte bancaire
              <span> · </span>Prêt en 2 minutes
            </p>
          </div>
        </section>

        {/* ───── 3. URGENCY BANNER ───── */}
        <section className="lp-urgency" aria-label="Compte à rebours réforme">
          <div className="lp-urgency-inner">
            <p className="lp-urgency-text">
              <strong>Obligation légale dans {countdown.days} jours</strong> — Le 1er septembre 2026, toutes les entreprises françaises devront recevoir des factures électroniques.
            </p>
            <div className="lp-countdown" aria-hidden="true">
              <div className="lp-countdown-block">
                <span className="lp-countdown-val">{pad(countdown.days)}</span>
                <span className="lp-countdown-label">Jours</span>
              </div>
              <div className="lp-countdown-block">
                <span className="lp-countdown-val">{pad(countdown.hours)}</span>
                <span className="lp-countdown-label">Heures</span>
              </div>
              <div className="lp-countdown-block">
                <span className="lp-countdown-val">{pad(countdown.minutes)}</span>
                <span className="lp-countdown-label">Min</span>
              </div>
              <div className="lp-countdown-block">
                <span className="lp-countdown-val">{pad(countdown.seconds)}</span>
                <span className="lp-countdown-label">Sec</span>
              </div>
            </div>
            <Link to="/register" className="lp-btn">Se mettre en conformité →</Link>
          </div>
        </section>

        {/* ───── 4. SOCIAL PROOF ───── */}
        <section className="lp-social">
          <div className="lp-social-inner">
            <p className="lp-social-title reveal">Ils nous font déjà confiance</p>
            <div className="lp-stats reveal reveal-d1">
              <div className="lp-stat">
                <div className="lp-stat-val">8M</div>
                <div className="lp-stat-label">d'entreprises concernées</div>
              </div>
              <div className="lp-stat">
                <div className="lp-stat-val">3</div>
                <div className="lp-stat-label">formats réglementaires</div>
              </div>
              <div className="lp-stat">
                <div className="lp-stat-val">10 ans</div>
                <div className="lp-stat-label">d'archivage conforme</div>
              </div>
              <div className="lp-stat">
                <div className="lp-stat-val">0 €</div>
                <div className="lp-stat-label">pour démarrer</div>
              </div>
            </div>
            <div className="lp-badges reveal reveal-d2">
              <span className="lp-badge-item">Chorus Pro</span>
              <span className="lp-badge-item">EN 16931</span>
              <span className="lp-badge-item">Factur-X</span>
              <span className="lp-badge-item">Open-source MIT</span>
            </div>
          </div>
        </section>

        {/* ───── 5. PROBLEM → SOLUTION ───── */}
        <section className="lp-problem">
          <div className="lp-problem-inner">
            <h2 className="reveal">La réforme arrive. Votre outil de facturation est-il prêt ?</h2>
            <p className="lp-problem-subtitle reveal reveal-d1">
              À partir de septembre 2026, chaque facture que vous émettez ou recevez doit passer par une plateforme agréée, dans un format électronique normé.
            </p>
            <div className="lp-problem-grid">
              <div className="lp-problem-col reveal reveal-d2">
                <h3>Les outils classiques ne suffisent plus</h3>
                <ul className="lp-problem-list">
                  <li><span className="icon" aria-hidden="true">&#10060;</span>Votre logiciel ne génère pas de Factur-X ni d'UBL</li>
                  <li><span className="icon" aria-hidden="true">&#10060;</span>Vos factures PDF ne sont pas transmises à Chorus Pro</li>
                  <li><span className="icon" aria-hidden="true">&#10060;</span>Vous n'avez aucune piste d'audit fiable</li>
                  <li><span className="icon" aria-hidden="true">&#10060;</span>Vous risquez des pénalités en cas de contrôle fiscal</li>
                </ul>
              </div>
              <div className="lp-problem-col solution reveal reveal-d3">
                <h3>Ma Facture Pro vous met en conformité en 2 minutes</h3>
                <ul className="lp-problem-list">
                  <li><span className="icon" aria-hidden="true">&#10004;&#65039;</span>Génération automatique Factur-X, UBL 2.1 et CII</li>
                  <li><span className="icon" aria-hidden="true">&#10004;&#65039;</span>Transmission directe à Chorus Pro (zéro action manuelle)</li>
                  <li><span className="icon" aria-hidden="true">&#10004;&#65039;</span>Piste d'audit fiable immutable + archivage 10 ans</li>
                  <li><span className="icon" aria-hidden="true">&#10004;&#65039;</span>Toutes les mentions DGFiP obligatoires pré-remplies</li>
                  <li><span className="icon" aria-hidden="true">&#10004;&#65039;</span>Numérotation séquentielle sans trou</li>
                </ul>
              </div>
            </div>

            {/* Flow */}
            <div className="lp-flow reveal reveal-d4">
              <div className="lp-flow-step"><span className="step-icon" aria-hidden="true">&#9997;&#65039;</span> Vous créez la facture</div>
              <span className="lp-flow-arrow" aria-hidden="true">→</span>
              <div className="lp-flow-step"><span className="step-icon" aria-hidden="true">&#128196;</span> Génération Factur-X</div>
              <span className="lp-flow-arrow" aria-hidden="true">→</span>
              <div className="lp-flow-step"><span className="step-icon" aria-hidden="true">&#128640;</span> Transmission Chorus Pro</div>
              <span className="lp-flow-arrow" aria-hidden="true">→</span>
              <div className="lp-flow-step"><span className="step-icon" aria-hidden="true">&#128274;</span> Archivage 10 ans</div>
            </div>
          </div>
        </section>

        {/* ───── 6. FEATURES ───── */}
        <section className="lp-features" id="fonctionnalites">
          <div className="lp-features-inner">
            <h2 className="reveal">Tout ce qu'il vous faut pour facturer en toute sérénité</h2>
            <div className="lp-features-grid">
              <div className="lp-feature-card reveal reveal-d1">
                <div className="lp-feature-icon" aria-hidden="true">&#128737;&#65039;</div>
                <h3>Conforme dès le premier jour</h3>
                <p>
                  Factur-X PDF/A-3, UBL 2.1, CII D16B — les trois formats imposés par la réforme.
                  Profil EN 16931 garanti. Toutes les mentions légales obligatoires sont pré-remplies
                  selon votre statut (micro, EI, SAS, SARL).
                </p>
              </div>
              <div className="lp-feature-card reveal reveal-d2">
                <div className="lp-feature-icon" aria-hidden="true">&#9889;</div>
                <h3>Zéro tâche manuelle</h3>
                <p>
                  Vos factures sont transmises automatiquement à Chorus Pro.
                  Les statuts se mettent à jour en temps réel.
                  Relances automatiques à J-3, J+1, J+7, J+30.
                  Réconciliation bancaire intelligente.
                </p>
              </div>
              <div className="lp-feature-card reveal reveal-d3">
                <div className="lp-feature-icon" aria-hidden="true">&#128274;</div>
                <h3>Archivage légal 10 ans</h3>
                <p>
                  Stockage S3 en France. Hash SHA-256 pour l'intégrité.
                  Piste d'audit fiable immutable.
                  Aucune facture émise ne peut être modifiée ou supprimée.
                  Conforme à l'article 289 VII du CGI.
                </p>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 7. DEMO ───── */}
        <section className="lp-demo" id="demo">
          <div className="lp-demo-inner">
            <h2 className="reveal">Créez et envoyez une facture conforme en 90 secondes</h2>
            <p className="lp-demo-sub reveal reveal-d1">
              Pas de tutoriel à lire. Pas de paramétrage complexe.
              Renseignez votre client, ajoutez vos lignes, cliquez sur « Envoyer ».
              Ma Facture Pro s'occupe du reste.
            </p>
            <div className="lp-demo-visual reveal reveal-d2">
              <div className="lp-demo-placeholder">
                <div className="play-icon" role="button" aria-label="Lancer la démo" tabIndex={0}>▶</div>
                <span>Démo interactive — bientôt disponible</span>
              </div>
            </div>
            <Link to="/register" className="lp-btn lp-btn-primary reveal reveal-d3">
              Essayer maintenant — c'est gratuit →
            </Link>
          </div>
        </section>

        {/* ───── 8. COMPARISON ───── */}
        <section className="lp-compare">
          <div className="lp-compare-inner">
            <h2 className="reveal">Pourquoi les freelances et PME nous choisissent</h2>
            <div className="reveal reveal-d1">
              <table className="lp-compare-table" role="table">
                <thead>
                  <tr>
                    <th scope="col">Critère</th>
                    <th scope="col">Ma Facture Pro</th>
                    <th scope="col">Solutions classiques</th>
                  </tr>
                </thead>
                <tbody>
                  <tr><td>Prix d'entrée</td><td>0 €/mois</td><td>15–50 €/mois</td></tr>
                  <tr><td>Formats réglementaires</td><td>Factur-X + UBL + CII</td><td>Souvent 1 seul</td></tr>
                  <tr><td>Connexion Chorus Pro</td><td>Automatique</td><td>Manuelle ou absente</td></tr>
                  <tr><td>Piste d'audit fiable</td><td>Immutable, certifiée</td><td>Souvent absente</td></tr>
                  <tr><td>Archivage légal</td><td>S3 France, 10 ans</td><td>Variable</td></tr>
                  <tr><td>Code source</td><td>Open-source (MIT)</td><td>Propriétaire, opaque</td></tr>
                  <tr><td>Label DGFiP</td><td>Candidature déposée</td><td>Rarement</td></tr>
                </tbody>
              </table>
            </div>
            <p className="lp-compare-note reveal reveal-d2">
              Pas de mauvaise surprise. Pas de fonctionnalité cachée derrière un paywall.<br />
              La conformité est incluse dans tous les plans, y compris le gratuit.
            </p>
          </div>
        </section>

        {/* ───── 9. PRICING ───── */}
        <section className="lp-pricing" id="tarifs">
          <div className="lp-pricing-inner">
            <div className="lp-pricing-header">
              <h2 className="reveal">Un prix juste. Pas de surprise.</h2>
              <p className="reveal reveal-d1">
                La conformité est incluse dans tous les plans — même le gratuit.
                Changez de plan ou annulez à tout moment.
              </p>
            </div>

            <div className="lp-pricing-grid">
              {/* Gratuit */}
              <div className="lp-price-card reveal reveal-d1">
                <div className="lp-price-name">Gratuit</div>
                <div className="lp-price-desc">Pour démarrer sereinement</div>
                <div className="lp-price-amount">
                  <span className="lp-price-val">0</span>
                  <span className="lp-price-cur">€</span>
                </div>
                <div className="lp-price-period">pour toujours</div>
                <ul className="lp-price-features">
                  <li><span className="check" aria-hidden="true">&#10003;</span>10 factures / mois</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>1 utilisateur</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Factur-X + UBL + CII</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Chorus Pro intégré</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Archivage 10 ans</li>
                </ul>
                <Link to="/register" className="lp-btn lp-btn-outline" style={{ width: '100%' }}>
                  Commencer gratuitement
                </Link>
              </div>

              {/* Pro */}
              <div className="lp-price-card featured reveal reveal-d2">
                <div className="lp-price-popular">Le plus populaire</div>
                <div className="lp-price-name">Pro</div>
                <div className="lp-price-desc">Pour les indépendants ambitieux</div>
                <div className="lp-price-amount">
                  <span className="lp-price-val">14</span>
                  <span className="lp-price-cur">€/mois</span>
                </div>
                <div className="lp-price-period">HT, sans engagement</div>
                <ul className="lp-price-features">
                  <li><span className="check" aria-hidden="true">&#10003;</span>Factures illimitées</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>3 utilisateurs · 3 entreprises</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>PDF personnalisé (logo, couleurs)</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Relances automatiques</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Synchronisation bancaire</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Support prioritaire</li>
                </ul>
                <Link to="/register" className="lp-btn lp-btn-primary" style={{ width: '100%' }}>
                  Essayer Pro gratuitement
                </Link>
                <div className="lp-price-trial">14 jours d'essai gratuit</div>
              </div>

              {/* Équipe */}
              <div className="lp-price-card reveal reveal-d3">
                <div className="lp-price-name">Équipe</div>
                <div className="lp-price-desc">Pour les structures qui grandissent</div>
                <div className="lp-price-amount">
                  <span className="lp-price-val">34</span>
                  <span className="lp-price-cur">€/mois</span>
                </div>
                <div className="lp-price-period">HT, sans engagement</div>
                <ul className="lp-price-features">
                  <li><span className="check" aria-hidden="true">&#10003;</span>Tout le plan Pro, plus :</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>10 utilisateurs · 5 entreprises</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Comptabilité automatisée</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Déclarations TVA &amp; URSSAF</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Assistant IA fiscal</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>API publique + Webhooks</li>
                  <li><span className="check" aria-hidden="true">&#10003;</span>Export FEC comptable</li>
                </ul>
                <Link to="/register" className="lp-btn lp-btn-outline" style={{ width: '100%' }}>
                  Démarrer avec Équipe
                </Link>
              </div>
            </div>

            {/* Extra boxes */}
            <div className="lp-pricing-extras reveal reveal-d4">
              <div className="lp-pricing-extra">
                <strong>&#128202; Vous êtes comptable ou cabinet ?</strong>
                Le plan Cabinet commence à 79 €/mois + 2 €/client actif au-delà de 20.
                Un cabinet de 150 clients : 339 €/mois — soit 2,26 €/client.
                <br /><a href="mailto:contact@mafacturepro.fr">Contactez-nous pour une démo dédiée →</a>
              </div>
              <div className="lp-pricing-extra">
                <strong>&#128161; Vous préférez payer au succès ?</strong>
                Le plan Succès Partagé : 0 € + 0,1 % de votre CA facturé
                au-delà de 50 000 €/an. Plafonné à 49 €/mois. Aucun risque.
                <br /><a href="mailto:contact@mafacturepro.fr">En savoir plus →</a>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 10. TESTIMONIALS ───── */}
        <section className="lp-testimonials">
          <div className="lp-testimonials-inner">
            <h2 className="reveal">Ce qu'en disent nos utilisateurs</h2>
            <div className="lp-testimonials-grid">
              <div className="lp-testimonial-card reveal reveal-d1">
                <p className="lp-testimonial-quote">
                  « J'ai migré en 20 minutes. Mes factures Factur-X passent sur Chorus Pro sans que je touche à rien. »
                </p>
                <div className="lp-testimonial-author">
                  <div className="lp-testimonial-avatar" style={{ background: '#6366f1' }}>SM</div>
                  <div>
                    <div className="lp-testimonial-name">Sophie M.</div>
                    <div className="lp-testimonial-role">Développeuse freelance, Lyon</div>
                  </div>
                </div>
              </div>
              <div className="lp-testimonial-card reveal reveal-d2">
                <p className="lp-testimonial-quote">
                  « Notre cabinet gère 120 clients dessus. Le portail comptable et l'export FEC groupé nous font gagner 2 jours par mois. »
                </p>
                <div className="lp-testimonial-author">
                  <div className="lp-testimonial-avatar" style={{ background: '#0891b2' }}>MD</div>
                  <div>
                    <div className="lp-testimonial-name">Marc D.</div>
                    <div className="lp-testimonial-role">Expert-comptable, Paris</div>
                  </div>
                </div>
              </div>
              <div className="lp-testimonial-card reveal reveal-d3">
                <p className="lp-testimonial-quote">
                  « Le plan gratuit m'a convaincu. Quand j'ai dépassé 10 factures, passer au Pro était une évidence. »
                </p>
                <div className="lp-testimonial-author">
                  <div className="lp-testimonial-avatar" style={{ background: '#d97706' }}>KB</div>
                  <div>
                    <div className="lp-testimonial-name">Karim B.</div>
                    <div className="lp-testimonial-role">Artisan plombier, Toulouse</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>

        {/* ───── 11. FAQ ───── */}
        <section className="lp-faq" id="faq">
          <div className="lp-faq-inner">
            <h2 className="reveal">Questions fréquentes</h2>
            <div className="lp-faq-list">
              {faqItems.map((item, i) => (
                <div key={i} className={`lp-faq-item${openFaq === i ? ' open' : ''}`}>
                  <button
                    className="lp-faq-q"
                    onClick={() => setOpenFaq(openFaq === i ? null : i)}
                    aria-expanded={openFaq === i}
                  >
                    <span>{item.q}</span>
                    <svg className="lp-faq-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
                      <polyline points="6 9 12 15 18 9" />
                    </svg>
                  </button>
                  <div className="lp-faq-a" role="region">
                    <p>{item.a}</p>
                  </div>
                </div>
              ))}
            </div>
          </div>
        </section>

        {/* ───── 12. FINAL CTA ───── */}
        <section className="lp-final-cta">
          <div className="lp-final-cta-inner">
            <h2 className="reveal">La réforme n'attend pas. Votre compte non plus.</h2>
            <p className="reveal reveal-d1">
              Créez votre premier Factur-X conforme en moins de 3 minutes.
              Gratuit. Sans carte bancaire. Sans engagement.
            </p>
            <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg reveal reveal-d2">
              Créer mon compte gratuit →
            </Link>
            <p className="lp-final-reassurance reveal reveal-d3">
              Rejoignez les entreprises qui ont déjà anticipé la réforme.
            </p>
          </div>
        </section>
      </main>

      {/* ───── 13. FOOTER ───── */}
      <footer className="lp-footer">
        <div className="lp-footer-inner">
          <div className="lp-footer-grid">
            <div className="lp-footer-brand">
              <div className="lp-footer-logo">Ma Facture Pro</div>
              <p>
                La facturation électronique conforme,<br />
                simple et gratuite.<br />
                Développé avec soin en France.
              </p>
            </div>
            <div className="lp-footer-col">
              <h4>Produit</h4>
              <ul>
                <li><a href="#fonctionnalites">Fonctionnalités</a></li>
                <li><a href="#tarifs">Tarifs</a></li>
                <li><a href="#faq">Sécurité</a></li>
                <li><a href="#faq">Changelog</a></li>
              </ul>
            </div>
            <div className="lp-footer-col">
              <h4>Ressources</h4>
              <ul>
                <li><a href="#faq">Guide de la réforme 2026</a></li>
                <li><a href="#faq">Qu'est-ce que le Factur-X ?</a></li>
                <li><a href="#faq">Centre d'aide</a></li>
              </ul>
            </div>
            <div className="lp-footer-col">
              <h4>Légal</h4>
              <ul>
                <li><a href="#faq">Mentions légales</a></li>
                <li><a href="#faq">Politique de confidentialité</a></li>
                <li><a href="#faq">CGU</a></li>
              </ul>
            </div>
          </div>
          <div className="lp-footer-bottom">
            © 2026 Ma Facture Pro · Code source sur GitHub (MIT)
          </div>
        </div>
      </footer>
    </div>
  );
}
