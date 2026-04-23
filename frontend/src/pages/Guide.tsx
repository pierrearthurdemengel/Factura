import { useEffect, useState, useRef, useCallback } from 'react';
import { Link } from 'react-router-dom';
import './Guide.css';

/* ═══════════════════════════════════════════
   SVG ICONS
   ═══════════════════════════════════════════ */
function IconCheck() {
  return (
    <svg className="guide-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="3" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polyline points="20 6 9 17 4 12" />
    </svg>
  );
}
function IconX() {
  return (
    <svg className="guide-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <line x1="18" y1="6" x2="6" y2="18" /><line x1="6" y1="6" x2="18" y2="18" />
    </svg>
  );
}
function IconWarning() {
  return (
    <svg className="guide-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z" /><line x1="12" y1="9" x2="12" y2="13" /><line x1="12" y1="17" x2="12.01" y2="17" />
    </svg>
  );
}
function IconInfo() {
  return (
    <svg className="guide-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <circle cx="12" cy="12" r="10" /><line x1="12" y1="16" x2="12" y2="12" /><line x1="12" y1="8" x2="12.01" y2="8" />
    </svg>
  );
}
function IconArrowRight() {
  return (
    <svg className="guide-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <line x1="5" y1="12" x2="19" y2="12" /><polyline points="12 5 19 12 12 19" />
    </svg>
  );
}
function IconCalendar() {
  return (
    <svg className="guide-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <rect width="18" height="18" x="3" y="4" rx="2" ry="2" /><line x1="16" y1="2" x2="16" y2="6" /><line x1="8" y1="2" x2="8" y2="6" /><line x1="3" y1="10" x2="21" y2="10" />
    </svg>
  );
}
function IconChevronDown() {
  return (
    <svg className="guide-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <polyline points="6 9 12 15 18 9" />
    </svg>
  );
}
function IconBookOpen() {
  return (
    <svg className="guide-icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" /><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" />
    </svg>
  );
}

/* ═══════════════════════════════════════════
   TOC DATA
   ═══════════════════════════════════════════ */
interface TocItem {
  id: string;
  label: string;
  children?: { id: string; label: string }[];
}

const tocData: TocItem[] = [
  {
    id: 'partie-1', label: 'Partie 1 — La réforme',
    children: [
      { id: 'section-1-1', label: '1.1 Qu\'est-ce que la facturation électronique ?' },
      { id: 'section-1-2', label: '1.2 Ce qui change concrètement' },
      { id: 'section-1-3', label: '1.3 Calendrier et dates clés' },
      { id: 'section-1-4', label: '1.4 Qui est concerné ?' },
      { id: 'section-1-5', label: '1.5 Qui n\'est PAS concerné ?' },
      { id: 'section-1-6', label: '1.6 Sanctions et amendes' },
      { id: 'section-1-7', label: '1.7 E-invoicing vs e-reporting' },
      { id: 'section-1-8', label: '1.8 Nouvelles mentions obligatoires' },
    ],
  },
  {
    id: 'partie-2', label: 'Partie 2 — Les formats',
    children: [
      { id: 'section-2-1', label: '2.1 Les 3 formats obligatoires' },
      { id: 'section-2-2', label: '2.2 Qu\'est-ce que le Factur-X ?' },
      { id: 'section-2-3', label: '2.3 UBL 2.1' },
      { id: 'section-2-4', label: '2.4 CII D16B' },
      { id: 'section-2-5', label: '2.5 Norme EN 16931' },
      { id: 'section-2-6', label: '2.6 Factur-X vs PDF classique' },
    ],
  },
  {
    id: 'partie-3', label: 'Partie 3 — Les plateformes',
    children: [
      { id: 'section-3-1', label: '3.1 PDP, PA, PPF : les acronymes' },
      { id: 'section-3-2', label: '3.2 Comment choisir sa plateforme ?' },
    ],
  },
  {
    id: 'partie-4', label: 'Partie 4 — Obligations par profil',
    children: [
      { id: 'section-4-1', label: '4.1 Auto-entrepreneur' },
      { id: 'section-4-2', label: '4.2 Freelance / profession libérale' },
      { id: 'section-4-3', label: '4.3 Artisan / commerçant' },
      { id: 'section-4-4', label: '4.4 TPE' },
      { id: 'section-4-5', label: '4.5 PME' },
      { id: 'section-4-6', label: '4.6 SCI / LMNP' },
      { id: 'section-4-7', label: '4.7 Expert-comptable' },
    ],
  },
  {
    id: 'partie-5', label: 'Partie 5 — Aspects techniques',
    children: [
      { id: 'section-5-1', label: '5.1 Piste d\'Audit Fiable (PAF)' },
      { id: 'section-5-2', label: '5.2 Archivage 10 ans' },
      { id: 'section-5-3', label: '5.3 Signature électronique' },
      { id: 'section-5-4', label: '5.4 Cycle de vie d\'une facture' },
      { id: 'section-5-5', label: '5.5 Interopérabilité' },
    ],
  },
  {
    id: 'partie-6', label: 'Partie 6 — Questions pratiques',
    children: [
      { id: 'section-6-1', label: '6.1 Comment se mettre en conformité ?' },
      { id: 'section-6-2', label: '6.2 Mon logiciel est-il compatible ?' },
      { id: 'section-6-3', label: '6.3 Combien ça coûte ?' },
      { id: 'section-6-4', label: '6.4 Et si je fais tout sur Excel ?' },
      { id: 'section-6-5', label: '6.5 Et si je suis en franchise de TVA ?' },
      { id: 'section-6-6', label: '6.6 Cas des acomptes et avoirs' },
      { id: 'section-6-7', label: '6.7 Multi-activité et multi-société' },
      { id: 'section-6-8', label: '6.8 Clients à l\'étranger' },
      { id: 'section-6-9', label: '6.9 Conservation et RGPD' },
      { id: 'section-6-10', label: '6.10 Migration depuis un autre outil' },
    ],
  },
  {
    id: 'partie-7', label: 'Partie 7 — Ma Facture Pro',
    children: [
      { id: 'section-7-1', label: '7.1 Pourquoi choisir Ma Facture Pro ?' },
      { id: 'section-7-2', label: '7.2 Fonctionnalités clés' },
      { id: 'section-7-3', label: '7.3 Intégration Chorus Pro' },
      { id: 'section-7-4', label: '7.4 Sécurité et hébergement' },
      { id: 'section-7-5', label: '7.5 Tarification' },
      { id: 'section-7-6', label: '7.6 Démarrer en 5 minutes' },
    ],
  },
];

/* ═══════════════════════════════════════════
   SCHEMA.ORG JSON-LD
   ═══════════════════════════════════════════ */
const articleSchema = {
  '@context': 'https://schema.org',
  '@type': 'Article',
  headline: 'Guide complet de la facturation électronique 2026 — Réforme, formats, plateformes et obligations',
  datePublished: '2026-04-11',
  dateModified: '2026-04-11',
  author: {
    '@type': 'Organization',
    name: 'Ma Facture Pro',
    url: 'https://mafacturepro.fr',
  },
  publisher: {
    '@type': 'Organization',
    name: 'Ma Facture Pro',
    url: 'https://mafacturepro.fr',
  },
  description: 'Guide exhaustif sur la facturation électronique obligatoire en France à partir du 1er septembre 2026 : calendrier, formats Factur-X / UBL / CII, plateformes agréées, sanctions, obligations par profil.',
  mainEntityOfPage: {
    '@type': 'WebPage',
    '@id': 'https://mafacturepro.fr/guide-facturation-electronique-2026',
  },
};

const faqSchema = {
  '@context': 'https://schema.org',
  '@type': 'FAQPage',
  mainEntity: [
    {
      '@type': 'Question',
      name: 'Qu\'est-ce que la facturation électronique obligatoire en 2026 ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'La facturation électronique est l\'obligation pour toutes les entreprises assujetties à la TVA en France d\'émettre et de recevoir des factures au format structuré (Factur-X, UBL 2.1 ou CII D16B) via une plateforme agréée par l\'État, à compter du 1er septembre 2026 pour la réception et progressivement jusqu\'en septembre 2027 pour l\'émission.',
      },
    },
    {
      '@type': 'Question',
      name: 'Quand la facturation électronique devient-elle obligatoire ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'La réception de factures électroniques est obligatoire pour toutes les entreprises dès le 1er septembre 2026. L\'émission est obligatoire dès le 1er septembre 2026 pour les grandes entreprises (GE) et les entreprises de taille intermédiaire (ETI), puis dès le 1er septembre 2027 pour les PME, TPE, micro-entreprises et auto-entrepreneurs.',
      },
    },
    {
      '@type': 'Question',
      name: 'Quels sont les formats de facture électronique acceptés ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Trois formats sont acceptés par l\'administration fiscale française : Factur-X (PDF/A-3 avec XML CII D16B embarqué), UBL 2.1 (XML pur conforme Peppol BIS Billing 3.0) et CII D16B (XML pur Cross Industry Invoice). Tous doivent respecter la norme européenne EN 16931.',
      },
    },
    {
      '@type': 'Question',
      name: 'Quelles sanctions en cas de non-conformité à la facturation électronique ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Les sanctions prévues par l\'article 1737 III du CGI comprennent : 50 euros par facture non conforme (plafond 15 000 euros par an), 500 euros puis 1 000 euros par trimestre sans plateforme agréée, et 500 euros par transmission e-reporting manquante (plafond 15 000 euros). Une clause de bienveillance exempte de sanction la première infraction si régularisation sous 30 jours.',
      },
    },
    {
      '@type': 'Question',
      name: 'Les auto-entrepreneurs sont-ils concernés par la facturation électronique ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Oui. Les auto-entrepreneurs (micro-entrepreneurs) sont concernés. Ils doivent pouvoir recevoir des factures électroniques dès le 1er septembre 2026. L\'obligation d\'émission en format électronique s\'applique à compter du 1er septembre 2027. Même les auto-entrepreneurs en franchise en base de TVA sont concernés.',
      },
    },
    {
      '@type': 'Question',
      name: 'Qu\'est-ce que le Factur-X ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Le Factur-X est un format hybride de facture électronique, composé d\'un PDF/A-3 lisible par l\'humain et d\'un fichier XML CII D16B embarqué lisible par les machines. Il est conforme à la norme européenne EN 16931 et accepté par l\'administration fiscale française. Le profil minimum requis pour la réforme 2026 est le profil EN 16931 (aussi appelé « Comfort »).',
      },
    },
    {
      '@type': 'Question',
      name: 'Quelle est la différence entre e-invoicing et e-reporting ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Le e-invoicing concerne les factures entre entreprises assujetties à la TVA établies en France (B2B domestique) : elles doivent transiter au format structuré via une plateforme agréée. Le e-reporting concerne les opérations B2C et internationales : les données de transaction doivent être déclarées à l\'administration fiscale, mais la facture elle-même n\'a pas besoin d\'être au format structuré.',
      },
    },
    {
      '@type': 'Question',
      name: 'Ma Facture Pro est-il gratuit pour la facturation électronique ?',
      acceptedAnswer: {
        '@type': 'Answer',
        text: 'Oui, Ma Facture Pro propose un plan gratuit permanent incluant jusqu\'à 10 factures par mois aux 3 formats réglementaires (Factur-X, UBL, CII), la transmission automatique à Chorus Pro, l\'e-reporting, et l\'archivage 10 ans conforme. Aucune carte bancaire n\'est requise.',
      },
    },
  ],
};

/* ═══════════════════════════════════════════
   GUIDE COMPONENT
   ═══════════════════════════════════════════ */
export default function Guide() {
  const [tocOpen, setTocOpen] = useState(false);
  const [activeSection, setActiveSection] = useState<string>('');
  const [navScrolled, setNavScrolled] = useState(false);
  const [menuOpen, setMenuOpen] = useState(false);
  const contentRef = useRef<HTMLDivElement>(null);

  /* ─── Inject Schema.org JSON-LD ─── */
  useEffect(() => {
    const articleScript = document.createElement('script');
    articleScript.type = 'application/ld+json';
    articleScript.textContent = JSON.stringify(articleSchema);
    articleScript.id = 'guide-schema-article';
    document.head.appendChild(articleScript);

    const faqScript = document.createElement('script');
    faqScript.type = 'application/ld+json';
    faqScript.textContent = JSON.stringify(faqSchema);
    faqScript.id = 'guide-schema-faq';
    document.head.appendChild(faqScript);

    return () => {
      document.getElementById('guide-schema-article')?.remove();
      document.getElementById('guide-schema-faq')?.remove();
    };
  }, []);

  /* ─── Nav scroll detection ─── */
  useEffect(() => {
    const handleScroll = () => setNavScrolled(globalThis.scrollY > 20);
    globalThis.addEventListener('scroll', handleScroll, { passive: true });
    return () => globalThis.removeEventListener('scroll', handleScroll);
  }, []);

  /* ─── IntersectionObserver for active TOC section ─── */
  useEffect(() => {
    const allIds: string[] = [];
    tocData.forEach((p) => {
      allIds.push(p.id);
      p.children?.forEach((c) => allIds.push(c.id));
    });

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries.filter((e) => e.isIntersecting);
        if (visible.length > 0) {
          const topMost = visible.reduce((prev, curr) =>
            prev.boundingClientRect.top < curr.boundingClientRect.top ? prev : curr
          , visible[0]);
          setActiveSection(topMost.target.id);
        }
      },
      { rootMargin: '-80px 0px -60% 0px', threshold: 0 },
    );

    allIds.forEach((id) => {
      const el = document.getElementById(id);
      if (el) observer.observe(el);
    });

    return () => observer.disconnect();
  }, []);

  const scrollToSection = useCallback((id: string) => {
    const el = document.getElementById(id);
    if (el) {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
      setTocOpen(false);
    }
  }, []);

  return (
    <div className="guide">
      {/* ═══════════════════════════════════════════
          NAVBAR
          ═══════════════════════════════════════════ */}
      <nav className={`lp-nav${navScrolled ? ' scrolled' : ''}`} aria-label="Navigation principale">
        <Link to="/" className="lp-nav-logo" style={{ textDecoration: 'none' }}>Ma Facture Pro</Link>
        <div className="lp-nav-links">
          <Link to="/" className="lp-nav-link">Accueil</Link>
          <a href="#partie-1" className="lp-nav-link">La réforme</a>
          <a href="#partie-2" className="lp-nav-link">Les formats</a>
          <a href="#partie-6" className="lp-nav-link">Questions pratiques</a>
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
          <Link to="/" className="lp-mobile-link" onClick={() => setMenuOpen(false)}>Accueil</Link>
          <a href="#partie-1" className="lp-mobile-link" onClick={() => setMenuOpen(false)}>La réforme</a>
          <a href="#partie-2" className="lp-mobile-link" onClick={() => setMenuOpen(false)}>Les formats</a>
          <a href="#partie-6" className="lp-mobile-link" onClick={() => setMenuOpen(false)}>Questions pratiques</a>
          <Link to="/login" className="lp-mobile-link" onClick={() => setMenuOpen(false)}>Connexion</Link>
          <div className="lp-mobile-cta">
            <Link to="/register" className="lp-btn lp-btn-primary" style={{ width: '100%' }} onClick={() => setMenuOpen(false)}>Créer mon compte gratuit</Link>
          </div>
        </dialog>
      )}

      {/* ═══════════════════════════════════════════
          HERO
          ═══════════════════════════════════════════ */}
      <header className="guide-hero" aria-label="En-tête du guide">
        <div className="guide-hero-inner">
          <span className="guide-hero-badge">
            <IconCalendar /> Mis à jour le <time dateTime="2026-04-11">11 avril 2026</time>
          </span>
          <h1 className="guide-hero-title">Guide complet de la facturation électronique 2026</h1>
          <p className="guide-hero-subtitle">
            Tout ce que vous devez savoir sur la réforme, les formats, les plateformes et vos obligations.
            Mis à jour le <time dateTime="2026-04-11">11 avril 2026</time>.
          </p>
          <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
            Créer mon compte gratuit <IconArrowRight />
          </Link>
        </div>
      </header>

      {/* ═══════════════════════════════════════════
          BODY (TOC + CONTENT)
          ═══════════════════════════════════════════ */}
      <div className="guide-body">
        {/* ─── Mobile TOC toggle ─── */}
        <button
          className="guide-toc-toggle"
          onClick={() => setTocOpen(!tocOpen)}
          aria-expanded={tocOpen}
          aria-controls="guide-toc"
        >
          <IconBookOpen /> Sommaire <IconChevronDown />
        </button>

        {/* ─── Sidebar TOC ─── */}
        <aside className={`guide-toc${tocOpen ? ' open' : ''}`} id="guide-toc" aria-label="Sommaire">
          <nav aria-label="Sommaire">
            <h2 className="guide-toc-title">Sommaire</h2>
            <ul className="guide-toc-list">
              {tocData.map((partie) => (
                <li key={partie.id} className="guide-toc-partie">
                  <button
                    className={`guide-toc-link guide-toc-link--partie${activeSection === partie.id ? ' active' : ''}`}
                    onClick={() => scrollToSection(partie.id)}
                  >
                    {partie.label}
                  </button>
                  {partie.children && (
                    <ul className="guide-toc-sublist">
                      {partie.children.map((child) => (
                        <li key={child.id}>
                          <button
                            className={`guide-toc-link${activeSection === child.id ? ' active' : ''}`}
                            onClick={() => scrollToSection(child.id)}
                          >
                            {child.label}
                          </button>
                        </li>
                      ))}
                    </ul>
                  )}
                </li>
              ))}
            </ul>
          </nav>
        </aside>

        {/* ─── Main content ─── */}
        <article className="guide-content" ref={contentRef}>

          {/* ═══ TL;DR ═══ */}
          <div className="guide-tldr">
            <div className="guide-tldr-label"><IconInfo /> En résumé</div>
            <p>
              La facturation électronique devient obligatoire le 1er septembre 2026 pour la réception (toutes entreprises)
              et progressivement jusqu'en septembre 2027 pour l'émission (TPE/PME). Les factures doivent être au format
              Factur-X, UBL ou CII et transiter par une plateforme agréée. Ma Facture Pro est une solution gratuite et
              conforme, connectée à Chorus Pro.
            </p>
          </div>

          {/* ═══════════════════════════════════════
              PARTIE 1 — LA RÉFORME
              ═══════════════════════════════════════ */}
          <section id="partie-1" className="guide-partie">
            <h2 className="guide-partie-title">Partie 1 — La réforme</h2>

            {/* ─── 1.1 ─── */}
            <section id="section-1-1">
              <h3 className="guide-section-title">1.1 Qu'est-ce que la facturation électronique ?</h3>

              <p>
                La facturation électronique, ce n'est PAS un PDF envoyé par email. Une facture électronique, au sens de la
                réforme française issue de l'article 289 bis du Code général des impôts (CGI) et de l'ordonnance n° 2021-1190
                du 15 septembre 2021, est un document émis, transmis et reçu sous forme de données structurées, lisibles par
                une machine. Son cycle de vie est entièrement numérique, de la création à l'archivage.
              </p>

              <p>Concrètement, une facture électronique contient :</p>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> Un <strong>format structuré</strong> (Factur-X, UBL ou CII) que les systèmes informatiques peuvent traiter automatiquement.</li>
                <li><IconCheck /> Des <strong>données normées</strong> (vendeur, acheteur, lignes, TVA, totaux) conformes à la norme européenne EN 16931.</li>
                <li><IconCheck /> Un <strong>transit obligatoire</strong> via une plateforme agréée par l'État, qui transmet les données à l'administration fiscale.</li>
              </ul>

              <p>Ce qui n'est <strong>PAS</strong> une facture électronique :</p>
              <ul className="guide-list guide-list--x">
                <li><IconX /> Un <strong>PDF envoyé par email</strong> — c'est un document numérique, pas une facture électronique au sens de la réforme.</li>
                <li><IconX /> Une <strong>photo de facture papier</strong> — c'est une copie numérisée.</li>
                <li><IconX /> Un <strong>fichier Word ou Excel</strong> — ce sont des documents bureautiques, pas des formats structurés conformes.</li>
              </ul>

              <p>
                À partir du 1er septembre 2026, seuls les formats structurés (Factur-X, UBL 2.1, CII D16B) transitant
                par une plateforme agréée seront considérés comme des factures électroniques valides, conformément au
                décret n° 2022-1299 du 7 octobre 2022.
              </p>

              <div className="guide-cta-inline">
                <p>
                  Ma Facture Pro génère automatiquement vos factures aux 3 formats réglementaires et les transmet à Chorus Pro.
                  Gratuit jusqu'à 10 factures/mois.
                </p>
                <Link to="/register" className="lp-btn lp-btn-primary">
                  Créer mon compte gratuit <IconArrowRight />
                </Link>
              </div>
            </section>

            {/* ─── 1.2 ─── */}
            <section id="section-1-2">
              <h3 className="guide-section-title">1.2 Réforme 2026 : ce qui change concrètement</h3>

              <p>
                La réforme de la facturation électronique en France repose sur trois piliers définis par la loi de finances 2024
                (article 91) et précisés par le décret n° 2022-1299. Ces trois piliers transforment intégralement le circuit de
                facturation entre entreprises sur le territoire français.
              </p>

              <div className="guide-pillars">
                <div className="guide-pillar">
                  <div className="guide-pillar-number">1</div>
                  <h4>E-invoicing (facturation électronique B2B)</h4>
                  <p>
                    Toutes les factures entre entreprises assujetties à la TVA établies en France doivent utiliser un format
                    électronique structuré et transiter via une plateforme agréée par l'administration fiscale (AIFE). L'obligation
                    concerne environ 4 millions d'entreprises et 2 milliards de factures par an.
                  </p>
                </div>
                <div className="guide-pillar">
                  <div className="guide-pillar-number">2</div>
                  <h4>E-reporting (transmission de données)</h4>
                  <p>
                    Les opérations B2C (ventes aux particuliers) et les opérations internationales (import/export, intracommunautaire)
                    nécessitent une déclaration de données à l'administration fiscale. La facture elle-même n'a pas besoin d'être au
                    format structuré, mais les données de transaction doivent être transmises.
                  </p>
                </div>
                <div className="guide-pillar">
                  <div className="guide-pillar-number">3</div>
                  <h4>Annuaire centralisé</h4>
                  <p>
                    Chaque entreprise est enregistrée dans l'annuaire centralisé géré par l'AIFE (Agence pour l'informatique financière
                    de l'État). Cet annuaire permet d'identifier la plateforme de dématérialisation de chaque entreprise et d'acheminer
                    les factures vers le bon destinataire.
                  </p>
                </div>
              </div>

              <h4>Les objectifs de l'État</h4>
              <p>
                L'administration fiscale française poursuit trois objectifs principaux avec cette réforme, selon le rapport de la
                DGFiP de 2023 : lutter contre la fraude à la TVA (l'écart de TVA est estimé entre 20 et 25 milliards d'euros par
                an selon la Commission européenne), simplifier les obligations déclaratives des entreprises en pré-remplissant les
                déclarations de TVA, et disposer de données économiques en temps réel pour piloter la politique fiscale.
              </p>

              <h4>Ce qui change pour vous</h4>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> <strong>Choisir une plateforme</strong> avant septembre 2026 : vous devez vous inscrire sur une plateforme agréée (PA) ou utiliser une solution compatible comme Ma Facture Pro.</li>
                <li><IconCheck /> <strong>Factures aux 3 formats obligatoires</strong> : vos factures doivent être émises en Factur-X, UBL 2.1 ou CII D16B, conformément à la norme EN 16931.</li>
                <li><IconCheck /> <strong>Transmission automatique</strong> à l'administration fiscale : chaque facture est transmise en temps réel via la plateforme choisie.</li>
                <li><IconCheck /> <strong>4 nouvelles mentions obligatoires</strong> sur chaque facture : numéro SIREN du client, catégorie d'opération, option TVA sur les débits, adresse de livraison.</li>
                <li><IconCheck /> <strong>Archivage 10 ans</strong> : toutes les factures électroniques doivent être conservées pendant 10 ans dans un format garantissant leur intégrité (article L. 102 B du Livre des procédures fiscales).</li>
              </ul>
            </section>

            {/* ─── 1.3 ─── */}
            <section id="section-1-3">
              <h3 className="guide-section-title">1.3 Calendrier complet et dates clés</h3>

              <p>
                Le calendrier de la réforme a été fixé par la loi de finances 2024 et le décret n° 2024-266 du 25 mars 2024.
                Après plusieurs reports (initialement prévue pour 2023, puis 2024), la date définitive d'entrée en vigueur est
                le 1er septembre 2026.
              </p>

              <div className="guide-timeline">
                <div className="guide-timeline-item">
                  <div className="guide-timeline-date">Février 2025</div>
                  <div className="guide-timeline-content">
                    <strong>Annuaire centralisé AIFE disponible</strong>
                    <p>L'annuaire des entreprises est ouvert. Chaque entreprise peut s'y inscrire et déclarer sa plateforme de réception.</p>
                  </div>
                </div>
                <div className="guide-timeline-item">
                  <div className="guide-timeline-date">Juin 2025</div>
                  <div className="guide-timeline-content">
                    <strong>Premières 101 plateformes agréées publiées</strong>
                    <p>L'AIFE publie la liste des premières plateformes ayant obtenu l'agrément pour opérer en tant que Plateforme Agréée (PA).</p>
                  </div>
                </div>
                <div className="guide-timeline-item guide-timeline-item--major">
                  <div className="guide-timeline-date">1er septembre 2026</div>
                  <div className="guide-timeline-content">
                    <strong>Phase 1 : Réception obligatoire pour TOUTES les entreprises</strong>
                    <p>Toutes les entreprises assujetties à la TVA doivent pouvoir recevoir des factures électroniques. L'émission et l'e-reporting deviennent obligatoires pour les grandes entreprises (GE) et les entreprises de taille intermédiaire (ETI).</p>
                  </div>
                </div>
                <div className="guide-timeline-item guide-timeline-item--major">
                  <div className="guide-timeline-date">1er septembre 2027</div>
                  <div className="guide-timeline-content">
                    <strong>Phase 2 : Émission obligatoire pour TOUTES les entreprises</strong>
                    <p>L'obligation d'émission et d'e-reporting s'étend aux PME, TPE, micro-entreprises et auto-entrepreneurs.</p>
                  </div>
                </div>
              </div>

              <h4>Récapitulatif par taille d'entreprise</h4>
              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Taille d'entreprise</th>
                      <th>Réception</th>
                      <th>Émission</th>
                      <th>E-reporting</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><strong>GE</strong> (&gt; 5 000 salariés)</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2026</td>
                    </tr>
                    <tr>
                      <td><strong>ETI</strong> (250–5 000 salariés)</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2026</td>
                    </tr>
                    <tr>
                      <td><strong>PME</strong> (50–250 salariés)</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2027</td>
                      <td>1er sept. 2027</td>
                    </tr>
                    <tr>
                      <td><strong>TPE</strong> (10–50 salariés)</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2027</td>
                      <td>1er sept. 2027</td>
                    </tr>
                    <tr>
                      <td><strong>Micro-entreprise</strong> (&lt; 10 salariés)</td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2027</td>
                      <td>1er sept. 2027</td>
                    </tr>
                    <tr>
                      <td><strong>Auto-entrepreneurs</strong></td>
                      <td>1er sept. 2026</td>
                      <td>1er sept. 2027</td>
                      <td>1er sept. 2027</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div className="guide-callout guide-callout--warning">
                <IconWarning />
                <p>
                  <strong>Attention :</strong> même si votre obligation d'émission ne commence qu'en septembre 2027,
                  l'obligation de <strong>réception</strong> s'applique dès septembre 2026 pour toutes les entreprises sans exception.
                  Vous devez donc avoir une plateforme opérationnelle dès cette date.
                </p>
              </div>
            </section>

            {/* ─── 1.4 ─── */}
            <section id="section-1-4">
              <h3 className="guide-section-title">1.4 Qui est concerné ?</h3>

              <p>
                Toute entreprise assujettie à la TVA et établie en France est concernée par la réforme. Cela représente
                environ 8 millions d'acteurs économiques selon la DGFiP. La réponse courte : si vous avez un numéro SIREN
                et que vous êtes assujetti à la TVA en France, vous êtes concerné.
              </p>

              <p>Les entreprises et structures concernées sont :</p>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> <strong>Auto-entrepreneurs</strong> (micro-entrepreneurs) — y compris ceux en franchise en base de TVA (article 293 B du CGI).</li>
                <li><IconCheck /> <strong>Entreprises individuelles (EI)</strong> — commerçants, artisans, professions libérales exerçant en nom propre.</li>
                <li><IconCheck /> <strong>Professions libérales</strong> — avocats, médecins, architectes, consultants, développeurs, designers, etc.</li>
                <li><IconCheck /> <strong>Artisans et commerçants</strong> — inscrits au RNE (Registre national des entreprises).</li>
                <li><IconCheck /> <strong>SAS, SASU, SARL, EURL</strong> — toutes les sociétés commerciales, quelle que soit leur taille.</li>
                <li><IconCheck /> <strong>SCI soumises à la TVA</strong> — les sociétés civiles immobilières qui ont opté pour l'assujettissement à la TVA ou qui louent des locaux professionnels équipés.</li>
                <li><IconCheck /> <strong>LMNP soumis à la TVA</strong> — les loueurs meublés non professionnels exploitant des résidences de services avec TVA.</li>
                <li><IconCheck /> <strong>Associations assujetties à la TVA</strong> — les associations exerçant une activité lucrative assujettie.</li>
                <li><IconCheck /> <strong>Entreprises des DOM</strong> — Guadeloupe, Martinique, La Réunion, Guyane et Mayotte sont concernées.</li>
                <li><IconCheck /> <strong>Entreprises en franchise en base de TVA</strong> — même sans facturer la TVA, elles doivent recevoir et émettre des factures électroniques.</li>
              </ul>
            </section>

            {/* ─── 1.5 ─── */}
            <section id="section-1-5">
              <h3 className="guide-section-title">1.5 Qui n'est PAS concerné ?</h3>

              <p>
                Certaines opérations et certains acteurs échappent à l'obligation de facturation électronique (e-invoicing),
                mais peuvent tout de même être soumis à l'obligation d'e-reporting. La distinction est importante.
              </p>

              <ul className="guide-list guide-list--x">
                <li>
                  <IconX /> <strong>Ventes B2C</strong> (aux particuliers) — pas de facture électronique obligatoire, mais
                  l'<strong>e-reporting est obligatoire</strong> : les données de la transaction doivent être transmises à
                  l'administration fiscale.
                </li>
                <li>
                  <IconX /> <strong>Opérations internationales</strong> (export, intracommunautaire) — pas de facture électronique
                  au sens de la réforme, mais <strong>e-reporting obligatoire</strong> pour les données de transaction.
                </li>
                <li>
                  <IconX /> <strong>Certains territoires d'outre-mer</strong> — la Nouvelle-Calédonie, la Polynésie française,
                  Wallis-et-Futuna, Saint-Pierre-et-Miquelon et Saint-Barthélemy ne sont pas concernés (territoires hors du
                  champ de la TVA française).
                </li>
                <li>
                  <IconX /> <strong>Opérations exonérées de TVA sans droit à déduction</strong> — certaines opérations bancaires,
                  d'assurance et médicales exonérées en vertu de l'article 261 du CGI ne sont pas concernées par le e-invoicing.
                </li>
              </ul>

              <div className="guide-callout guide-callout--info">
                <IconInfo />
                <p>
                  <strong>Point clé :</strong> même si vous n'êtes pas concerné par le e-invoicing, vous pouvez être concerné
                  par le e-reporting. Les ventes B2C et les opérations internationales nécessitent une transmission de données
                  à l'administration fiscale.
                </p>
              </div>
            </section>

            {/* ─── 1.6 ─── */}
            <section id="section-1-6">
              <h3 className="guide-section-title">1.6 Sanctions et amendes</h3>

              <p>
                Le législateur a prévu des sanctions financières pour les entreprises qui ne respectent pas les obligations de
                facturation électronique. Ces sanctions sont codifiées aux articles 1737 et 1770 undecies du Code général des
                impôts (CGI), modifiés par la loi de finances 2024.
              </p>

              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Infraction</th>
                      <th>Amende</th>
                      <th>Plafond</th>
                      <th>Base légale</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Non-émission de facture électronique</td>
                      <td>50 euros par facture</td>
                      <td>15 000 euros / an</td>
                      <td>Art. 1737 III CGI</td>
                    </tr>
                    <tr>
                      <td>Absence de plateforme agréée</td>
                      <td>500 euros puis 1 000 euros / trimestre</td>
                      <td>—</td>
                      <td>Art. 1770 undecies CGI</td>
                    </tr>
                    <tr>
                      <td>Défaut d'e-reporting</td>
                      <td>500 euros par transmission manquante</td>
                      <td>15 000 euros / an</td>
                      <td>Art. 1770 undecies CGI</td>
                    </tr>
                    <tr>
                      <td>Caisse enregistreuse non certifiée</td>
                      <td>7 500 euros</td>
                      <td>75 000 euros (récidive)</td>
                      <td>Art. 1770 duodecies CGI</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <div className="guide-callout guide-callout--info">
                <IconInfo />
                <p>
                  <strong>Clause de bienveillance :</strong> l'administration fiscale a prévu une clause de tolérance pour la
                  première infraction. Si vous régularisez votre situation dans les 30 jours suivant la notification de
                  l'administration, aucune sanction ne sera appliquée. Cette mesure vise à accompagner la transition des
                  entreprises vers la facturation électronique (BOI-CF-INF-10-40-40, paragraphe 100).
                </p>
              </div>

              <p>
                Concrètement, un auto-entrepreneur qui émet 20 factures par mois sans utiliser le format électronique risque
                une amende de 1 000 euros par mois, soit 12 000 euros par an. Pour une PME émettant 300 factures par mois,
                l'amende maximale de 15 000 euros par an serait rapidement atteinte.
              </p>
            </section>

            {/* ─── 1.7 ─── */}
            <section id="section-1-7">
              <h3 className="guide-section-title">1.7 E-invoicing vs e-reporting : quelle différence ?</h3>

              <p>
                L'e-invoicing et l'e-reporting sont deux obligations distinctes de la réforme. Elles ne s'appliquent pas aux
                mêmes opérations et ne fonctionnent pas de la même manière, selon les articles 289 bis et 290 du CGI.
              </p>

              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Critère</th>
                      <th>E-invoicing</th>
                      <th>E-reporting</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><strong>Opérations concernées</strong></td>
                      <td>B2B domestique (France-France, assujettis TVA)</td>
                      <td>B2C + international + intracommunautaire</td>
                    </tr>
                    <tr>
                      <td><strong>Format de la facture</strong></td>
                      <td>Obligatoirement structuré (Factur-X, UBL, CII)</td>
                      <td>Libre (PDF, papier, etc.)</td>
                    </tr>
                    <tr>
                      <td><strong>Transit via plateforme</strong></td>
                      <td>Obligatoire</td>
                      <td>Transmission des données uniquement</td>
                    </tr>
                    <tr>
                      <td><strong>Données transmises au fisc</strong></td>
                      <td>Facture complète</td>
                      <td>Données agrégées ou par transaction</td>
                    </tr>
                    <tr>
                      <td><strong>Délai de transmission</strong></td>
                      <td>Temps réel (lors de l'émission)</td>
                      <td>Sous 48 heures maximum</td>
                    </tr>
                    <tr>
                      <td><strong>Exemple</strong></td>
                      <td>Facture d'un freelance à une SAS française</td>
                      <td>Ticket de caisse d'un commerce, facture à un client allemand</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p>
                Ma Facture Pro gère automatiquement les deux obligations. Lorsque vous créez une facture, Ma Facture Pro
                détecte si l'opération relève du e-invoicing ou du e-reporting et effectue la transmission appropriée sans
                action manuelle de votre part.
              </p>
            </section>

            {/* ─── 1.8 ─── */}
            <section id="section-1-8">
              <h3 className="guide-section-title">1.8 Nouvelles mentions obligatoires</h3>

              <p>
                Le décret n° 2022-1299 du 7 octobre 2022 introduit 4 nouvelles mentions obligatoires sur les factures
                électroniques, en complément des mentions existantes prévues par l'article 242 nonies A de l'annexe II du CGI.
              </p>

              <h4>Les 4 nouvelles mentions</h4>
              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Mention</th>
                      <th>Description</th>
                      <th>Exemple</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><strong>Numéro SIREN du client</strong></td>
                      <td>Identifiant à 9 chiffres du client dans le répertoire SIRENE de l'INSEE</td>
                      <td>823 456 789</td>
                    </tr>
                    <tr>
                      <td><strong>Catégorie d'opération</strong></td>
                      <td>Livraison de biens, prestation de services, ou mixte</td>
                      <td>Prestation de services</td>
                    </tr>
                    <tr>
                      <td><strong>Option TVA sur les débits</strong></td>
                      <td>Indication si le fournisseur a opté pour le paiement de la TVA d'après les débits</td>
                      <td>Oui / Non</td>
                    </tr>
                    <tr>
                      <td><strong>Adresse de livraison</strong></td>
                      <td>Adresse effective de livraison des biens si différente de l'adresse de facturation</td>
                      <td>12 rue de la Paix, 75002 Paris</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <h4>Rappel des mentions existantes obligatoires</h4>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> Date d'émission de la facture</li>
                <li><IconCheck /> Numéro de facture (séquentiel, sans trou)</li>
                <li><IconCheck /> Identité du vendeur (nom/raison sociale, adresse, SIREN, numéro TVA intracommunautaire)</li>
                <li><IconCheck /> Identité de l'acheteur (nom/raison sociale, adresse)</li>
                <li><IconCheck /> Désignation des biens ou services (nature, quantité, prix unitaire HT)</li>
                <li><IconCheck /> Taux de TVA applicable et montant de TVA</li>
                <li><IconCheck /> Montant total HT et TTC</li>
                <li><IconCheck /> Date de la livraison ou de la prestation</li>
                <li><IconCheck /> Conditions de paiement (délai, pénalités de retard, escompte)</li>
                <li><IconCheck /> Mention « TVA non applicable, art. 293 B du CGI » si franchise en base</li>
              </ul>

              <p>
                Ma Facture Pro pré-remplit automatiquement toutes les mentions obligatoires, y compris les 4 nouvelles
                mentions de la réforme 2026. Le numéro SIREN du client est vérifié en temps réel via l'API SIRENE de l'INSEE.
              </p>
            </section>

            {/* ─── CTA Partie 1 ─── */}
            <div className="guide-cta-block">
              <h3>Prêt à vous mettre en conformité ?</h3>
              <p>Ma Facture Pro vous accompagne dans la transition vers la facturation électronique obligatoire. Créez votre compte en 2 minutes.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Démarrer gratuitement <IconArrowRight />
              </Link>
            </div>
          </section>

          {/* ═══════════════════════════════════════
              PARTIE 2 — LES FORMATS
              ═══════════════════════════════════════ */}
          <section id="partie-2" className="guide-partie">
            <h2 className="guide-partie-title">Partie 2 — Les formats</h2>

            {/* ─── 2.1 ─── */}
            <section id="section-2-1">
              <h3 className="guide-section-title">2.1 Les 3 formats obligatoires</h3>

              <p>
                L'administration fiscale française accepte trois formats de facture électronique, tous conformes à la norme
                européenne EN 16931. Le choix du format est libre, mais la facture doit obligatoirement être émise dans l'un
                de ces trois formats, selon l'arrêté du 7 octobre 2022 pris en application de l'article 289 bis du CGI.
              </p>

              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Format</th>
                      <th>Type</th>
                      <th>Lisibilité humaine</th>
                      <th>Lisibilité machine</th>
                      <th>Usage principal</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td><strong>Factur-X</strong></td>
                      <td>Hybride (PDF/A-3 + XML)</td>
                      <td><IconCheck /> Oui (PDF)</td>
                      <td><IconCheck /> Oui (XML CII)</td>
                      <td>TPE, PME, freelances — format le plus accessible</td>
                    </tr>
                    <tr>
                      <td><strong>UBL 2.1</strong></td>
                      <td>XML pur</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Oui</td>
                      <td>Grands comptes, secteur public, Peppol</td>
                    </tr>
                    <tr>
                      <td><strong>CII D16B</strong></td>
                      <td>XML pur</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Oui</td>
                      <td>Industrie, grandes entreprises, international</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p>
                Ma Facture Pro génère automatiquement vos factures dans les trois formats simultanément. Vous n'avez pas
                à choisir : lors de la création d'une facture, Ma Facture Pro produit le Factur-X (pour l'envoi lisible
                au client), l'UBL 2.1 et le CII D16B (pour la transmission à la plateforme agréée).
              </p>
            </section>

            {/* ─── 2.2 ─── */}
            <section id="section-2-2">
              <h3 className="guide-section-title">2.2 Qu'est-ce que le Factur-X ?</h3>

              <p>
                Le Factur-X est le format de facture électronique le plus adapté aux TPE, PME et indépendants. C'est un format
                hybride franco-allemand (aussi connu sous le nom ZUGFeRD 2.1 en Allemagne), normalisé par le FNFE-MPE (Forum
                national de la facture électronique) et conforme à la norme européenne EN 16931.
              </p>

              <p>
                Concrètement, un Factur-X est un fichier PDF/A-3 (un PDF pérenne archivable) dans lequel est embarqué un fichier
                XML au format CII D16B. Le PDF est lisible par l'humain (il ressemble à une facture classique), tandis que le XML
                est lisible par les machines et permet le traitement automatisé.
              </p>

              <h4>Les profils Factur-X</h4>
              <p>
                Le Factur-X existe en plusieurs profils, du plus simple au plus complet. Le profil minimum requis pour la réforme
                2026 est le profil EN 16931 (aussi appelé « Comfort ») selon le décret n° 2022-1299.
              </p>

              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Profil</th>
                      <th>Données incluses</th>
                      <th>Conforme réforme 2026</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>MINIMUM</td>
                      <td>Données minimales (date, montant, vendeur)</td>
                      <td><IconX /> Non</td>
                    </tr>
                    <tr>
                      <td>BASIC WL</td>
                      <td>Données de base sans lignes de détail</td>
                      <td><IconX /> Non</td>
                    </tr>
                    <tr>
                      <td>BASIC</td>
                      <td>Données de base avec lignes</td>
                      <td><IconX /> Non</td>
                    </tr>
                    <tr className="guide-table-highlight">
                      <td><strong>EN 16931 (Comfort)</strong></td>
                      <td>Toutes les données requises par la norme EN 16931</td>
                      <td><IconCheck /> <strong>Oui — minimum requis</strong></td>
                    </tr>
                    <tr>
                      <td>EXTENDED</td>
                      <td>Données étendues (conditions de paiement détaillées, etc.)</td>
                      <td><IconCheck /> Oui</td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p>
                Ma Facture Pro génère systématiquement des Factur-X au profil EN 16931 (Comfort), garantissant la conformité
                avec la réforme 2026. Chaque facture inclut automatiquement toutes les données requises par la norme européenne.
              </p>
            </section>

            {/* ─── 2.3 ─── */}
            <section id="section-2-3">
              <h3 className="guide-section-title">2.3 UBL 2.1</h3>

              <p>
                L'UBL 2.1 (Universal Business Language, version 2.1) est un format XML pur développé par le consortium OASIS.
                Il est utilisé massivement dans le secteur public européen via le réseau Peppol (Pan-European Public Procurement
                Online) et est conforme à la spécification Peppol BIS Billing 3.0.
              </p>

              <p>
                L'UBL 2.1 ne produit pas de rendu visuel natif : c'est un fichier XML brut destiné aux systèmes d'information.
                Les grandes entreprises et les administrations publiques le privilégient pour son interopérabilité et sa capacité
                d'intégration dans les ERP (SAP, Oracle, Sage X3). Ma Facture Pro génère automatiquement les factures en UBL 2.1
                conforme Peppol BIS Billing 3.0.
              </p>
            </section>

            {/* ─── 2.4 ─── */}
            <section id="section-2-4">
              <h3 className="guide-section-title">2.4 CII D16B</h3>

              <p>
                Le CII D16B (Cross Industry Invoice, version D16B) est un format XML pur développé par UN/CEFACT (Centre des
                Nations Unies pour la facilitation du commerce). Il est utilisé principalement dans l'industrie et les échanges
                internationaux. Le CII D16B est le format XML embarqué dans les fichiers Factur-X.
              </p>

              <p>
                Comme l'UBL, le CII D16B est un format purement technique sans rendu visuel natif. Il est conforme à la norme
                EN 16931 et accepté par l'administration fiscale française pour la réforme 2026. Ma Facture Pro génère
                automatiquement les factures en CII D16B standalone en plus du Factur-X et de l'UBL 2.1.
              </p>
            </section>

            {/* ─── 2.5 ─── */}
            <section id="section-2-5">
              <h3 className="guide-section-title">2.5 Norme EN 16931</h3>

              <p>
                La norme EN 16931 est la norme européenne de facturation électronique, adoptée en 2017 par le Comité européen
                de normalisation (CEN) dans le cadre de la directive 2014/55/UE. Elle définit un modèle sémantique de données
                commun à tous les pays de l'Union européenne.
              </p>

              <p>
                Toute facture électronique émise dans le cadre de la réforme française 2026 doit être conforme à cette norme,
                quel que soit le format choisi (Factur-X, UBL ou CII). La norme EN 16931 garantit l'interopérabilité entre les
                systèmes de facturation de tous les pays européens. Ma Facture Pro valide automatiquement chaque facture générée
                contre les règles de la norme EN 16931 avant émission.
              </p>
            </section>

            {/* ─── 2.6 ─── */}
            <section id="section-2-6">
              <h3 className="guide-section-title">2.6 Factur-X vs PDF classique</h3>

              <p>
                Pour bien comprendre la différence entre une facture conforme et un simple PDF, voici un comparatif détaillé.
                Le PDF classique, même s'il est envoyé par email, ne constitue pas une facture électronique au sens de la réforme.
              </p>

              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Critère</th>
                      <th>PDF classique</th>
                      <th>Factur-X</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr>
                      <td>Lisible par l'humain</td>
                      <td><IconCheck /> Oui</td>
                      <td><IconCheck /> Oui (PDF/A-3)</td>
                    </tr>
                    <tr>
                      <td>Lisible par une machine</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Oui (XML CII D16B)</td>
                    </tr>
                    <tr>
                      <td>Conforme réforme 2026</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Oui</td>
                    </tr>
                    <tr>
                      <td>Archivable (PDF/A)</td>
                      <td><IconX /> Souvent non</td>
                      <td><IconCheck /> Oui (PDF/A-3)</td>
                    </tr>
                    <tr>
                      <td>Traitement automatique</td>
                      <td><IconX /> Impossible</td>
                      <td><IconCheck /> Natif</td>
                    </tr>
                    <tr>
                      <td>Données structurées</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Oui (norme EN 16931)</td>
                    </tr>
                    <tr>
                      <td>Intégrité vérifiable</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Signature électronique possible</td>
                    </tr>
                    <tr>
                      <td>Transit via plateforme agréée</td>
                      <td><IconX /> Non</td>
                      <td><IconCheck /> Obligatoire</td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </section>

            {/* ─── CTA Partie 2 ─── */}
            <div className="guide-cta-block">
              <h3>Vos factures aux 3 formats, automatiquement</h3>
              <p>Ma Facture Pro génère chaque facture en Factur-X, UBL et CII simultanément. Aucune manipulation technique requise.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Essayer Ma Facture Pro <IconArrowRight />
              </Link>
            </div>
          </section>

          {/* ═══════════════════════════════════════
              PARTIE 3 — LES PLATEFORMES
              ═══════════════════════════════════════ */}
          <section id="partie-3" className="guide-partie">
            <h2 className="guide-partie-title">Partie 3 — Les plateformes</h2>

            {/* ─── 3.1 ─── */}
            <section id="section-3-1">
              <h3 className="guide-section-title">3.1 PDP, PA, PPF : les acronymes</h3>

              <p>
                L'écosystème de la facturation électronique en France repose sur plusieurs types de plateformes. Le vocabulaire
                a évolué depuis les premières annonces de la réforme. Voici les termes à connaître.
              </p>

              <h4>PPF — Portail Public de Facturation (abandonné)</h4>
              <p>
                Le PPF (Portail Public de Facturation) était le projet initial de plateforme publique gratuite gérée par l'État,
                qui devait servir de plateforme par défaut pour toutes les entreprises. Le projet a été abandonné en octobre 2024
                au profit d'un modèle décentralisé basé sur les plateformes agréées privées. Chorus Pro reste disponible pour les
                marchés publics et sert de point de collecte pour l'administration fiscale.
              </p>

              <h4>PA — Plateforme Agréée (ex-PDP)</h4>
              <p>
                Les Plateformes Agréées (PA), anciennement appelées PDP (Plateformes de Dématérialisation Partenaires), sont des
                opérateurs privés agréés par l'AIFE pour émettre, transmettre et recevoir des factures électroniques. En juin 2025,
                101 plateformes ont obtenu cet agrément. Une PA assure la conformité des factures, leur transmission à
                l'administration fiscale et la gestion du cycle de vie (statuts, accusés de réception).
              </p>

              <h4>Solution compatible (dont Ma Facture Pro)</h4>
              <p>
                Une solution compatible est un logiciel de facturation qui se connecte à une ou plusieurs plateformes agréées pour
                assurer la transmission des factures. Ma Facture Pro est une solution compatible connectée à Chorus Pro et aux
                principales plateformes agréées. L'avantage d'une solution compatible : vous conservez votre interface de travail
                habituelle, et la conformité est gérée de manière transparente en arrière-plan.
              </p>

              <h4>Chorus Pro</h4>
              <p>
                Chorus Pro est la plateforme publique de facturation électronique de l'État français, opérée par l'AIFE. Elle est
                obligatoire pour les factures adressées au secteur public (État, collectivités, hôpitaux) depuis le 1er janvier 2020.
                Dans le cadre de la réforme 2026, Chorus Pro sert de point de collecte des données pour l'administration fiscale
                et reste la plateforme de référence pour les marchés publics.
              </p>
            </section>

            {/* ─── 3.2 ─── */}
            <section id="section-3-2">
              <h3 className="guide-section-title">3.2 Comment choisir sa plateforme ?</h3>

              <p>
                Le choix de votre plateforme de facturation électronique est une décision importante. Voici les 7 critères
                essentiels à évaluer avant de vous engager, selon les recommandations de la DGFiP et du FNFE-MPE.
              </p>

              <ol className="guide-list-numbered">
                <li>
                  <strong>Conformité réglementaire</strong> — La plateforme doit être agréée par l'AIFE ou connectée à une PA
                  agréée. Vérifiez que l'agrément est valide et à jour sur le site de l'AIFE.
                </li>
                <li>
                  <strong>Formats supportés</strong> — La plateforme doit supporter les 3 formats obligatoires (Factur-X, UBL 2.1,
                  CII D16B) et valider la conformité EN 16931 de chaque facture.
                </li>
                <li>
                  <strong>E-reporting intégré</strong> — L'e-reporting (B2C, international) doit être géré nativement, sans
                  manipulation manuelle supplémentaire.
                </li>
                <li>
                  <strong>Archivage conforme</strong> — L'archivage 10 ans doit être garanti avec intégrité, horodatage et
                  traçabilité, conformément à l'article L. 102 B du Livre des procédures fiscales.
                </li>
                <li>
                  <strong>Simplicité d'utilisation</strong> — L'interface doit être accessible aux non-techniciens. Un
                  auto-entrepreneur ne doit pas avoir besoin de connaître les spécifications XML pour émettre une facture conforme.
                </li>
                <li>
                  <strong>Tarification transparente</strong> — Méfiez-vous des coûts cachés (frais par facture, frais d'archivage,
                  frais de transmission). Comparez le coût total sur 12 mois.
                </li>
                <li>
                  <strong>Support et accompagnement</strong> — Un support réactif en français est indispensable, surtout pendant
                  la phase de transition.
                </li>
              </ol>

              <h4>Les pièges à éviter</h4>
              <ul className="guide-list guide-list--x">
                <li><IconX /> <strong>Engagement longue durée</strong> — Certaines plateformes imposent un engagement de 12 à 36 mois. Préférez les solutions sans engagement.</li>
                <li><IconX /> <strong>Frais par facture</strong> — Un coût de 0,50 à 2 euros par facture peut représenter un budget important pour une PME émettant 500 factures/mois.</li>
                <li><IconX /> <strong>Absence d'e-reporting</strong> — Si la plateforme ne gère pas l'e-reporting, vous devrez utiliser un second outil pour vos ventes B2C et internationales.</li>
                <li><IconX /> <strong>Données non exportables</strong> — Vérifiez que vous pouvez exporter vos données à tout moment dans un format standard (CSV, JSON, XML).</li>
              </ul>
            </section>

            {/* ─── CTA Partie 3 ─── */}
            <div className="guide-cta-block">
              <h3>Une solution conforme, sans engagement</h3>
              <p>Ma Facture Pro est connecté à Chorus Pro et aux plateformes agréées. Gratuit jusqu'à 10 factures/mois, sans engagement, sans frais cachés.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Créer mon compte gratuit <IconArrowRight />
              </Link>
            </div>
          </section>

          {/* ═══════════════════════════════════════
              PARTIE 4 — OBLIGATIONS PAR PROFIL
              ═══════════════════════════════════════ */}
          <section id="partie-4" className="guide-partie">
            <h2 className="guide-partie-title">Partie 4 — Obligations par profil</h2>

            {/* ─── 4.1 ─── */}
            <section id="section-4-1">
              <h3 className="guide-section-title">4.1 Auto-entrepreneur (micro-entrepreneur)</h3>

              <p>
                Les auto-entrepreneurs sont pleinement concernés par la réforme de la facturation électronique. L'obligation
                s'applique même aux auto-entrepreneurs en franchise en base de TVA (article 293 B du CGI), c'est-à-dire
                ceux qui ne facturent pas la TVA parce que leur chiffre d'affaires est inférieur aux seuils (77 700 euros pour
                les prestations de services, 188 700 euros pour le commerce, en 2026).
              </p>

              <h4>Calendrier spécifique</h4>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> <strong>1er septembre 2026 :</strong> obligation de recevoir des factures électroniques. Vous devez avoir choisi une plateforme et être inscrit dans l'annuaire AIFE.</li>
                <li><IconCheck /> <strong>1er septembre 2027 :</strong> obligation d'émettre des factures électroniques au format structuré et de transmettre les données d'e-reporting.</li>
              </ul>

              <h4>Ce que vous devez faire</h4>
              <ol className="guide-list-numbered">
                <li>Choisir une solution de facturation conforme (comme Ma Facture Pro) avant septembre 2026.</li>
                <li>Vous inscrire dans l'annuaire centralisé AIFE pour déclarer votre plateforme de réception.</li>
                <li>Vérifier que vos factures contiennent les 4 nouvelles mentions obligatoires.</li>
                <li>Si vous êtes en franchise en base de TVA : la mention « TVA non applicable, art. 293 B du CGI » reste obligatoire sur vos factures électroniques.</li>
              </ol>

              <h4>Combien ça coûte ?</h4>
              <p>
                Ma Facture Pro est gratuit pour les auto-entrepreneurs jusqu'à 10 factures par mois. Cela couvre les besoins de
                la grande majorité des auto-entrepreneurs. Aucune carte bancaire n'est requise à l'inscription.
              </p>

              <div className="guide-callout guide-callout--info">
                <IconInfo />
                <p>
                  <strong>Bon à savoir :</strong> un auto-entrepreneur en franchise en base de TVA est assujetti à la TVA mais
                  n'en est pas redevable. La nuance est importante : l'assujettissement suffit à déclencher l'obligation de
                  facturation électronique, même si vous ne facturez pas la TVA.
                </p>
              </div>
            </section>

            {/* ─── 4.2 ─── */}
            <section id="section-4-2">
              <h3 className="guide-section-title">4.2 Freelance / profession libérale</h3>

              <p>
                Les freelances et professions libérales (développeurs, designers, consultants, avocats, architectes, etc.)
                sont concernés par la réforme, qu'ils exercent en entreprise individuelle (EI), en EURL, en SASU ou sous tout
                autre statut juridique. Le calendrier dépend de la taille de l'entreprise.
              </p>

              <h4>Calendrier</h4>
              <p>
                La majorité des freelances et professions libérales sont des TPE ou micro-entreprises. Leur calendrier est :
                réception obligatoire le 1er septembre 2026, émission obligatoire le 1er septembre 2027.
              </p>

              <h4>Particularités des professions libérales réglementées</h4>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> <strong>Avocats :</strong> les honoraires d'avocat sont soumis à la TVA et donc à la facturation électronique. Le secret professionnel ne dispense pas de l'obligation.</li>
                <li><IconCheck /> <strong>Médecins et professionnels de santé :</strong> les actes médicaux exonérés de TVA (article 261-4-1° du CGI) ne sont pas concernés par le e-invoicing, mais les honoraires soumis à TVA (expertise, esthétique) le sont.</li>
                <li><IconCheck /> <strong>Architectes, experts-comptables, notaires :</strong> pleinement concernés pour leurs factures d'honoraires soumises à TVA.</li>
              </ul>

              <h4>Factures à l'étranger</h4>
              <p>
                Si vous facturez des clients étrangers (hors France), ces factures ne relèvent pas du e-invoicing mais du
                e-reporting. Ma Facture Pro gère automatiquement cette distinction : lors de la création de la facture, le
                système détecte si le client est français ou étranger et applique le traitement approprié.
              </p>
            </section>

            {/* ─── 4.3 ─── */}
            <section id="section-4-3">
              <h3 className="guide-section-title">4.3 Artisan / commerçant</h3>

              <p>
                Les artisans et commerçants inscrits au RNE (Registre national des entreprises) sont concernés par la réforme.
                Pour les artisans et commerçants TPE (moins de 10 salariés), le calendrier est identique aux auto-entrepreneurs :
                réception le 1er septembre 2026, émission le 1er septembre 2027. Les ventes au comptoir (B2C) ne nécessitent pas
                de facture électronique mais sont soumises à l'e-reporting.
              </p>
            </section>

            {/* ─── 4.4 ─── */}
            <section id="section-4-4">
              <h3 className="guide-section-title">4.4 TPE (10 à 50 salariés)</h3>

              <p>
                Les TPE (très petites entreprises, de 10 à 50 salariés selon la définition européenne) suivent le même calendrier
                que les micro-entreprises : réception obligatoire le 1er septembre 2026, émission et e-reporting obligatoires le
                1er septembre 2027. Les TPE doivent anticiper la mise en place d'une solution conforme bien avant l'échéance,
                car le volume de factures est généralement plus important qu'en micro-entreprise.
              </p>
            </section>

            {/* ─── 4.5 ─── */}
            <section id="section-4-5">
              <h3 className="guide-section-title">4.5 PME (50 à 250 salariés)</h3>

              <p>
                Les PME (50 à 250 salariés, chiffre d'affaires inférieur à 50 millions d'euros) suivent le même calendrier que les
                TPE : réception le 1er septembre 2026, émission et e-reporting le 1er septembre 2027. Les PME doivent porter une
                attention particulière à l'intégration de la solution de facturation électronique avec leur ERP ou logiciel de
                gestion existant. Ma Facture Pro propose une API REST complète pour faciliter cette intégration.
              </p>
            </section>

            {/* ─── 4.6 ─── */}
            <section id="section-4-6">
              <h3 className="guide-section-title">4.6 SCI / LMNP</h3>

              <p>
                Les SCI (Sociétés Civiles Immobilières) et les LMNP (Loueurs Meublés Non Professionnels) ne sont concernés par
                la facturation électronique que s'ils sont assujettis à la TVA. C'est le cas des SCI qui louent des locaux
                professionnels équipés ou qui ont opté pour la TVA (article 260-2° du CGI), et des LMNP qui exploitent des
                résidences de services avec TVA (résidences étudiantes, EHPAD, résidences de tourisme classées).
              </p>
            </section>

            {/* ─── 4.7 ─── */}
            <section id="section-4-7">
              <h3 className="guide-section-title">4.7 Expert-comptable</h3>

              <p>
                Les experts-comptables jouent un rôle central dans la réforme de la facturation électronique. D'une part, ils
                sont eux-mêmes concernés pour leurs propres factures d'honoraires. D'autre part, ils accompagnent leurs clients
                dans la mise en conformité. Ma Facture Pro propose un portail expert-comptable dédié permettant de gérer
                plusieurs dossiers clients depuis une interface unique, avec accès aux écritures comptables générées
                automatiquement (FEC).
              </p>
            </section>

            {/* ─── CTA Partie 4 ─── */}
            <div className="guide-cta-block">
              <h3>Quel que soit votre profil, Ma Facture Pro s'adapte</h3>
              <p>Auto-entrepreneur, freelance, PME ou expert-comptable : Ma Facture Pro propose une offre adaptée à votre activité et à votre volume de facturation.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Voir les offres <IconArrowRight />
              </Link>
            </div>
          </section>

          {/* ═══════════════════════════════════════
              PARTIE 5 — ASPECTS TECHNIQUES
              ═══════════════════════════════════════ */}
          <section id="partie-5" className="guide-partie">
            <h2 className="guide-partie-title">Partie 5 — Aspects techniques</h2>

            {/* ─── 5.1 ─── */}
            <section id="section-5-1">
              <h3 className="guide-section-title">5.1 Piste d'Audit Fiable (PAF)</h3>

              <p>
                La Piste d'Audit Fiable (PAF) est une obligation fiscale définie par l'article 289-VII du CGI et précisée par le
                BOI-TVA-DECLA-30-20-30-10. Elle impose à chaque entreprise de mettre en place des contrôles documentés établissant
                un lien entre la facture, la livraison du bien ou du service, et le paiement.
              </p>

              <p>
                Concrètement, la PAF consiste à documenter le processus de facturation de bout en bout : bon de commande,
                bon de livraison, facture, paiement. Chaque étape doit être traçable et vérifiable en cas de contrôle fiscal.
              </p>

              <h4>Comment Ma Facture Pro implémente la PAF</h4>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> <strong>Lien automatique devis-facture :</strong> chaque facture créée à partir d'un devis conserve le lien avec le document source.</li>
                <li><IconCheck /> <strong>Historique complet :</strong> toutes les modifications, envois, paiements et relances sont horodatés et tracés dans un journal d'audit immuable.</li>
                <li><IconCheck /> <strong>Statuts de facture normalisés :</strong> brouillon, émise, envoyée, reçue, acceptée, payée, refusée — chaque changement de statut est enregistré avec horodatage.</li>
                <li><IconCheck /> <strong>Rapprochement bancaire :</strong> Ma Facture Pro peut rapprocher automatiquement les paiements reçus avec les factures émises grâce à l'intégration bancaire.</li>
                <li><IconCheck /> <strong>Export PAF :</strong> un rapport de piste d'audit fiable peut être généré à tout moment pour présentation à l'administration fiscale.</li>
              </ul>
            </section>

            {/* ─── 5.2 ─── */}
            <section id="section-5-2">
              <h3 className="guide-section-title">5.2 Archivage 10 ans</h3>

              <p>
                L'article L. 102 B du Livre des procédures fiscales impose la conservation de toutes les factures pendant
                une durée de 6 ans à compter de la dernière opération mentionnée sur le livre ou le registre, ou de la date à
                laquelle les documents ont été établis. En pratique, l'administration fiscale recommande un archivage de 10 ans
                pour couvrir l'ensemble des délais de reprise et de prescription.
              </p>

              <p>
                L'archivage des factures électroniques ne se résume pas à sauvegarder des fichiers sur un disque dur. L'article
                L. 102 B exige que les factures archivées soient stockées dans des conditions garantissant leur intégrité, leur
                lisibilité et leur accessibilité pendant toute la durée de conservation.
              </p>

              <h4>Exigences réglementaires</h4>
              <ul className="guide-list guide-list--check">
                <li><IconCheck /> <strong>Intégrité :</strong> la facture archivée ne doit pas pouvoir être modifiée. Un mécanisme de scellement (hash cryptographique) doit garantir que le document n'a pas été altéré.</li>
                <li><IconCheck /> <strong>Lisibilité :</strong> la facture doit rester lisible pendant 10 ans. Le format PDF/A-3 (utilisé par Factur-X) est conçu pour l'archivage longue durée.</li>
                <li><IconCheck /> <strong>Accessibilité :</strong> les factures doivent être accessibles à tout moment en cas de contrôle fiscal. L'administration peut demander l'ensemble des factures émises et reçues sur une période donnée.</li>
                <li><IconCheck /> <strong>Horodatage :</strong> chaque facture archivée doit être horodatée de manière fiable (source de temps certifiée).</li>
              </ul>

              <p>
                Ma Facture Pro archive automatiquement toutes les factures pendant 10 ans sur des serveurs hébergés en France
                (OVHcloud, certifié ISO 27001 et HDS). L'intégrité de chaque facture est garantie par un hash SHA-256 et un
                horodatage certifié. L'accès aux archives est disponible à tout moment depuis votre compte.
              </p>
            </section>

            {/* ─── 5.3 ─── */}
            <section id="section-5-3">
              <h3 className="guide-section-title">5.3 Signature électronique</h3>

              <p>
                La signature électronique n'est pas obligatoire pour les factures transitant par une plateforme agréée (la
                plateforme assure elle-même l'authenticité et l'intégrité). Toutefois, l'article 289-V du CGI prévoit trois
                moyens de garantir l'authenticité de l'origine et l'intégrité du contenu d'une facture : la signature
                électronique qualifiée (RGS** ou eIDAS), l'EDI fiscal, et la piste d'audit fiable. Ma Facture Pro utilise la
                PAF et le transit via plateforme agréée comme mécanismes de conformité.
              </p>
            </section>

            {/* ─── 5.4 ─── */}
            <section id="section-5-4">
              <h3 className="guide-section-title">5.4 Cycle de vie d'une facture électronique</h3>

              <p>
                La réforme introduit des statuts obligatoires pour chaque facture électronique, transmis en temps réel via
                la plateforme agréée. Les statuts normalisés sont : brouillon, émise, transmise, reçue, acceptée, refusée,
                payée (partiellement ou totalement). Chaque changement de statut est horodaté et tracé. Ma Facture Pro
                affiche ces statuts en temps réel dans votre tableau de bord et vous notifie automatiquement des changements.
              </p>
            </section>

            {/* ─── 5.5 ─── */}
            <section id="section-5-5">
              <h3 className="guide-section-title">5.5 Interopérabilité</h3>

              <p>
                L'interopérabilité est la capacité des systèmes de facturation de différentes entreprises à échanger des factures
                de manière transparente. La norme EN 16931 et les 3 formats acceptés (Factur-X, UBL, CII) garantissent cette
                interopérabilité au niveau européen. Ma Facture Pro assure l'interopérabilité avec toutes les plateformes
                agréées françaises et le réseau Peppol pour les échanges européens.
              </p>
            </section>

            {/* ─── CTA Partie 5 ─── */}
            <div className="guide-cta-block">
              <h3>La technique, on s'en occupe</h3>
              <p>PAF, archivage 10 ans, formats conformes, statuts en temps réel : Ma Facture Pro gère toute la complexité technique pour vous.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Créer mon compte <IconArrowRight />
              </Link>
            </div>
          </section>

          {/* ═══════════════════════════════════════
              PARTIE 6 — QUESTIONS PRATIQUES
              ═══════════════════════════════════════ */}
          <section id="partie-6" className="guide-partie">
            <h2 className="guide-partie-title">Partie 6 — Questions pratiques</h2>

            {/* ─── 6.1 ─── */}
            <section id="section-6-1">
              <h3 className="guide-section-title">6.1 Comment se mettre en conformité ?</h3>

              <p>
                Se mettre en conformité avec la facturation électronique obligatoire nécessite 5 étapes concrètes. Voici
                le plan d'action recommandé par la DGFiP et le FNFE-MPE.
              </p>

              <ol className="guide-list-numbered">
                <li>
                  <strong>Auditer votre situation actuelle</strong> — Identifiez le nombre de factures émises et reçues par mois,
                  vos types de clients (B2B France, B2C, international), et votre logiciel de facturation actuel.
                </li>
                <li>
                  <strong>Choisir une solution conforme</strong> — Optez pour une plateforme agréée (PA) ou une solution
                  compatible comme Ma Facture Pro. Vérifiez les 7 critères détaillés en section 3.2.
                </li>
                <li>
                  <strong>S'inscrire dans l'annuaire AIFE</strong> — Déclarez votre plateforme de réception dans l'annuaire
                  centralisé. Ma Facture Pro effectue cette inscription automatiquement lors de la création de votre compte.
                </li>
                <li>
                  <strong>Mettre à jour vos modèles de facture</strong> — Ajoutez les 4 nouvelles mentions obligatoires (SIREN
                  client, catégorie d'opération, TVA sur les débits, adresse de livraison). Ma Facture Pro les inclut
                  automatiquement.
                </li>
                <li>
                  <strong>Tester et former</strong> — Émettez quelques factures de test pour valider le processus. Formez vos
                  collaborateurs à l'utilisation du nouveau système. Ma Facture Pro propose un mode bac à sable (sandbox) pour
                  les tests.
                </li>
              </ol>
            </section>

            {/* ─── 6.2 ─── */}
            <section id="section-6-2">
              <h3 className="guide-section-title">6.2 Mon logiciel est-il compatible ?</h3>

              <p>
                Pour savoir si votre logiciel de facturation actuel est compatible avec la réforme 2026, posez-vous ces
                5 questions essentielles.
              </p>

              <ol className="guide-list-numbered">
                <li><strong>Mon logiciel génère-t-il des factures en Factur-X, UBL ou CII ?</strong> — Si votre logiciel ne produit que des PDF classiques, il n'est pas conforme.</li>
                <li><strong>Mon logiciel est-il connecté à une plateforme agréée ?</strong> — La transmission via une PA est obligatoire. Un logiciel qui génère des Factur-X mais ne les transmet pas via une PA n'est pas suffisant.</li>
                <li><strong>Mon logiciel gère-t-il l'e-reporting ?</strong> — Si vous avez des clients B2C ou internationaux, l'e-reporting est obligatoire.</li>
                <li><strong>Mon logiciel intègre-t-il les 4 nouvelles mentions obligatoires ?</strong> — SIREN client, catégorie d'opération, TVA sur les débits, adresse de livraison.</li>
                <li><strong>Mon logiciel archive-t-il les factures 10 ans ?</strong> — L'archivage avec intégrité et horodatage est une obligation légale.</li>
              </ol>

              <p>
                Si vous répondez « non » à l'une de ces questions, votre logiciel actuel n'est pas conforme à la réforme 2026.
                Ma Facture Pro répond « oui » aux 5 questions.
              </p>
            </section>

            {/* ─── 6.3 ─── */}
            <section id="section-6-3">
              <h3 className="guide-section-title">6.3 Combien ça coûte ?</h3>

              <p>
                Le coût de la mise en conformité varie considérablement selon la solution choisie. Voici un comparatif
                des principales solutions disponibles sur le marché français en avril 2026.
              </p>

              <div className="guide-table-wrap">
                <table className="guide-table">
                  <thead>
                    <tr>
                      <th>Solution</th>
                      <th>Prix / mois</th>
                      <th>Factures incluses</th>
                      <th>E-reporting</th>
                      <th>Archivage 10 ans</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr className="guide-table-highlight">
                      <td><strong>Ma Facture Pro</strong></td>
                      <td><strong>Gratuit</strong></td>
                      <td>10 / mois</td>
                      <td><IconCheck /> Inclus</td>
                      <td><IconCheck /> Inclus</td>
                    </tr>
                    <tr className="guide-table-highlight">
                      <td><strong>Ma Facture Pro</strong> Pro</td>
                      <td><strong>14,90 euros</strong></td>
                      <td>Illimité</td>
                      <td><IconCheck /> Inclus</td>
                      <td><IconCheck /> Inclus</td>
                    </tr>
                    <tr>
                      <td>Pennylane</td>
                      <td>19 euros</td>
                      <td>Illimité</td>
                      <td><IconCheck /></td>
                      <td><IconCheck /></td>
                    </tr>
                    <tr>
                      <td>Indy</td>
                      <td>22 euros</td>
                      <td>Illimité</td>
                      <td><IconCheck /></td>
                      <td><IconCheck /></td>
                    </tr>
                    <tr>
                      <td>Sage</td>
                      <td>29 euros</td>
                      <td>Illimité</td>
                      <td><IconCheck /></td>
                      <td><IconCheck /></td>
                    </tr>
                    <tr>
                      <td>Cegid</td>
                      <td>39 euros</td>
                      <td>Illimité</td>
                      <td><IconCheck /></td>
                      <td><IconCheck /></td>
                    </tr>
                  </tbody>
                </table>
              </div>

              <p>
                Ma Facture Pro est la seule solution qui propose un plan gratuit permanent avec e-reporting et archivage 10 ans
                inclus. Le plan Pro à 14,90 euros/mois offre des factures illimitées, le portail expert-comptable et le
                rapprochement bancaire automatique.
              </p>
            </section>

            {/* ─── 6.4 ─── */}
            <section id="section-6-4">
              <h3 className="guide-section-title">6.4 Et si je fais tout sur Excel ?</h3>

              <p>
                Excel ne peut pas produire de factures au format structuré (Factur-X, UBL, CII) et ne peut pas se connecter
                à une plateforme agréée. Si vous utilisez actuellement Excel pour vos factures, vous devez migrer vers une
                solution conforme avant septembre 2026 (pour la réception) ou septembre 2027 (pour l'émission). Ma Facture Pro
                permet d'importer votre historique de facturation depuis Excel (fichiers CSV) pour faciliter la transition.
              </p>
            </section>

            {/* ─── 6.5 ─── */}
            <section id="section-6-5">
              <h3 className="guide-section-title">6.5 Et si je suis en franchise de TVA ?</h3>

              <p>
                Vous êtes concerné. Un entrepreneur en franchise en base de TVA (article 293 B du CGI) est assujetti à la TVA
                mais n'en est pas redevable. Cette distinction juridique fait que l'obligation de facturation électronique
                s'applique pleinement. Vos factures devront porter la mention « TVA non applicable, art. 293 B du CGI » au
                format structuré, et transiter par une plateforme agréée. Ma Facture Pro gère automatiquement cette mention
                en fonction de votre statut fiscal configuré lors de l'inscription.
              </p>
            </section>

            {/* ─── 6.6 ─── */}
            <section id="section-6-6">
              <h3 className="guide-section-title">6.6 Cas des acomptes et avoirs</h3>

              <p>
                Les factures d'acompte et les avoirs (notes de crédit) sont soumis aux mêmes obligations que les factures
                classiques. Ils doivent être émis au format structuré et transmis via la plateforme agréée. L'avoir doit
                référencer la facture initiale qu'il corrige. Ma Facture Pro génère automatiquement les avoirs avec le lien
                vers la facture d'origine et gère les factures d'acompte avec déduction automatique sur la facture finale.
              </p>
            </section>

            {/* ─── 6.7 ─── */}
            <section id="section-6-7">
              <h3 className="guide-section-title">6.7 Multi-activité et multi-société</h3>

              <p>
                Si vous gérez plusieurs activités ou sociétés, chacune doit être inscrite individuellement dans l'annuaire AIFE
                avec son propre SIREN. Chaque entité est soumise indépendamment aux obligations de facturation électronique.
                Ma Facture Pro permet de gérer plusieurs sociétés depuis un même compte utilisateur, avec un espace dédié par
                entité et une facturation consolidée.
              </p>
            </section>

            {/* ─── 6.8 ─── */}
            <section id="section-6-8">
              <h3 className="guide-section-title">6.8 Clients à l'étranger</h3>

              <p>
                Les factures adressées à des clients établis hors de France ne relèvent pas du e-invoicing (pas de format
                structuré obligatoire ni de transit via plateforme agréée). En revanche, ces opérations sont soumises au
                e-reporting : les données de la transaction doivent être transmises à l'administration fiscale dans un délai
                de 48 heures. Ma Facture Pro détecte automatiquement les clients étrangers et effectue la transmission
                e-reporting sans action manuelle.
              </p>
            </section>

            {/* ─── 6.9 ─── */}
            <section id="section-6-9">
              <h3 className="guide-section-title">6.9 Conservation et RGPD</h3>

              <p>
                La conservation des factures pendant 10 ans est une obligation fiscale (article L. 102 B du LPF). Cette
                obligation coexiste avec le RGPD (Règlement général sur la protection des données) qui impose la minimisation
                des données et le droit à l'effacement. L'obligation fiscale de conservation prévaut sur le droit à
                l'effacement RGPD pour les données figurant sur les factures. Ma Facture Pro conserve les factures 10 ans
                conformément à la loi tout en respectant les principes RGPD pour les données non fiscales.
              </p>
            </section>

            {/* ─── 6.10 ─── */}
            <section id="section-6-10">
              <h3 className="guide-section-title">6.10 Migration depuis un autre outil</h3>

              <p>
                Si vous utilisez actuellement un autre logiciel de facturation, Ma Facture Pro propose des outils de migration
                pour importer votre historique : import CSV/Excel, import depuis les API des principaux logiciels (Henrri,
                Freebe, Tiime, etc.), et import de factures PDF existantes avec extraction automatique des données par OCR.
                La migration peut être effectuée en quelques minutes pour les petits volumes, ou accompagnée par notre support
                pour les volumes importants.
              </p>
            </section>

            {/* ─── CTA Partie 6 ─── */}
            <div className="guide-cta-block">
              <h3>Des questions ? Ma Facture Pro a les réponses</h3>
              <p>Support en français, documentation complète, et un mode sandbox pour tester sans risque. Lancez-vous dès maintenant.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Tester gratuitement <IconArrowRight />
              </Link>
            </div>
          </section>

          {/* ═══════════════════════════════════════
              PARTIE 7 — MA FACTURE PRO
              ═══════════════════════════════════════ */}
          <section id="partie-7" className="guide-partie">
            <h2 className="guide-partie-title">Partie 7 — Ma Facture Pro</h2>

            {/* ─── 7.1 ─── */}
            <section id="section-7-1">
              <h3 className="guide-section-title">7.1 Pourquoi choisir Ma Facture Pro ?</h3>

              <p>
                Ma Facture Pro est une solution de facturation électronique conforme à la réforme 2026, conçue pour les
                indépendants, les TPE et les PME françaises. Voici les 6 raisons principales de choisir Ma Facture Pro.
              </p>

              <div className="guide-reasons">
                <div className="guide-reason">
                  <div className="guide-reason-number">1</div>
                  <div>
                    <h4>Gratuit jusqu'à 10 factures/mois</h4>
                    <p>
                      Ma Facture Pro propose un plan gratuit permanent, sans carte bancaire requise, incluant les 3 formats
                      réglementaires, l'e-reporting, et l'archivage 10 ans. C'est suffisant pour la majorité des
                      auto-entrepreneurs et freelances.
                    </p>
                  </div>
                </div>
                <div className="guide-reason">
                  <div className="guide-reason-number">2</div>
                  <div>
                    <h4>Conforme dès aujourd'hui</h4>
                    <p>
                      Ma Facture Pro génère des factures en Factur-X (profil EN 16931), UBL 2.1 et CII D16B. La conformité
                      n'est pas une option à activer : elle est native. Chaque facture est validée automatiquement contre la
                      norme EN 16931 avant émission.
                    </p>
                  </div>
                </div>
                <div className="guide-reason">
                  <div className="guide-reason-number">3</div>
                  <div>
                    <h4>Connecté à Chorus Pro</h4>
                    <p>
                      Ma Facture Pro est connecté à Chorus Pro et aux principales plateformes agréées. La transmission des
                      factures et de l'e-reporting se fait automatiquement, sans action manuelle.
                    </p>
                  </div>
                </div>
                <div className="guide-reason">
                  <div className="guide-reason-number">4</div>
                  <div>
                    <h4>Conçu et hébergé en France</h4>
                    <p>
                      Ma Facture Pro est développé en France et hébergé chez OVHcloud (certifié ISO 27001 et HDS). Vos données
                      restent sur le territoire français, conformément aux recommandations de la CNIL.
                    </p>
                  </div>
                </div>
                <div className="guide-reason">
                  <div className="guide-reason-number">5</div>
                  <div>
                    <h4>Open-source (licence MIT)</h4>
                    <p>
                      Le code source de Ma Facture Pro est disponible sur GitHub sous licence MIT. Vous pouvez auditer le code,
                      contribuer, ou l'auto-héberger. La transparence est une valeur fondamentale du projet.
                    </p>
                  </div>
                </div>
                <div className="guide-reason">
                  <div className="guide-reason-number">6</div>
                  <div>
                    <h4>Au-delà de la facturation</h4>
                    <p>
                      Ma Facture Pro intègre la gestion des devis, le suivi des paiements, le rapprochement bancaire, les
                      relances automatiques, le portail client, le portail expert-comptable, et l'export FEC. C'est un outil
                      de gestion complet, pas seulement un outil de facturation.
                    </p>
                  </div>
                </div>
              </div>
            </section>

            {/* ─── 7.2 ─── */}
            <section id="section-7-2">
              <h3 className="guide-section-title">7.2 Fonctionnalités clés</h3>

              <p>
                Ma Facture Pro offre un ensemble complet de fonctionnalités pour gérer votre facturation de A à Z. Parmi les
                fonctionnalités clés : création de factures et devis en 30 secondes, génération automatique aux 3 formats
                (Factur-X, UBL, CII), transmission automatique via Chorus Pro, e-reporting B2C et international, archivage
                10 ans conforme, portail client pour le suivi en temps réel, relances automatiques par email, rapprochement
                bancaire, export FEC pour l'expert-comptable, et API REST pour l'intégration avec vos outils existants.
              </p>
            </section>

            {/* ─── 7.3 ─── */}
            <section id="section-7-3">
              <h3 className="guide-section-title">7.3 Intégration Chorus Pro</h3>

              <p>
                Ma Facture Pro est connecté à Chorus Pro, la plateforme publique de facturation de l'État. Les factures sont
                transmises automatiquement lors de l'émission. Les statuts (reçue, acceptée, refusée, payée) sont synchronisés
                en temps réel dans votre tableau de bord. Pour les marchés publics, Ma Facture Pro gère le dépôt de factures
                sur Chorus Pro avec les codes engagement et les cadres de facturation spécifiques.
              </p>
            </section>

            {/* ─── 7.4 ─── */}
            <section id="section-7-4">
              <h3 className="guide-section-title">7.4 Sécurité et hébergement</h3>

              <p>
                Ma Facture Pro prend la sécurité de vos données au sérieux. Hébergement OVHcloud en France (certifié ISO 27001
                et HDS), chiffrement TLS 1.3 en transit et AES-256 au repos, authentification forte (2FA), sauvegardes
                quotidiennes géo-répliquées, et audits de sécurité réguliers. Les données fiscales ne quittent jamais le
                territoire français.
              </p>
            </section>

            {/* ─── 7.5 ─── */}
            <section id="section-7-5">
              <h3 className="guide-section-title">7.5 Tarification</h3>

              <p>
                Ma Facture Pro propose une tarification simple et transparente : un plan Gratuit (10 factures/mois, toutes les
                fonctionnalités de conformité incluses) et un plan Pro à 14,90 euros/mois (factures illimitées, portail
                expert-comptable, rapprochement bancaire, support prioritaire). Pas de frais cachés, pas de frais par facture,
                pas d'engagement. Vous pouvez changer de plan ou annuler à tout moment.
              </p>
            </section>

            {/* ─── 7.6 ─── */}
            <section id="section-7-6">
              <h3 className="guide-section-title">7.6 Démarrer en 5 minutes</h3>

              <p>
                Créer votre compte Ma Facture Pro et émettre votre première facture conforme prend moins de 5 minutes.
                Voici les étapes : créez votre compte (email + mot de passe), renseignez votre SIREN (les informations
                de votre entreprise sont pré-remplies via l'API SIRENE de l'INSEE), personnalisez votre modèle de facture
                (logo, couleurs, mentions), et créez votre première facture. Ma Facture Pro s'occupe du reste : format
                conforme, transmission à la plateforme, archivage.
              </p>
            </section>

            {/* ─── CTA Partie 7 ─── */}
            <div className="guide-cta-block">
              <h3>Prêt à simplifier votre facturation ?</h3>
              <p>Rejoignez des milliers d'entrepreneurs qui font confiance à Ma Facture Pro pour leur conformité. Gratuit, sans engagement.</p>
              <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
                Créer mon compte gratuit <IconArrowRight />
              </Link>
            </div>
          </section>

        </article>
      </div>

      {/* ═══════════════════════════════════════════
          FINAL CTA
          ═══════════════════════════════════════════ */}
      <section className="guide-final-cta">
        <div className="guide-final-cta-inner">
          <h2>La facturation électronique n'attend pas. Vous non plus.</h2>
          <p>
            Créez votre compte Ma Facture Pro en 2 minutes et émettez votre première facture conforme
            aux 3 formats réglementaires. Gratuit jusqu'à 10 factures/mois, sans carte bancaire.
          </p>
          <div className="guide-final-cta-actions">
            <Link to="/register" className="lp-btn lp-btn-primary lp-btn-primary-lg">
              Créer mon compte gratuit <IconArrowRight />
            </Link>
            <Link to="/" className="lp-btn lp-btn-outline">
              Découvrir Ma Facture Pro
            </Link>
          </div>
        </div>
      </section>

      {/* ═══════════════════════════════════════════
          FOOTER
          ═══════════════════════════════════════════ */}
      <footer className="guide-footer" aria-label="Pied de page du guide">
        <div className="guide-footer-inner">
          <div className="guide-footer-grid">
            <div className="guide-footer-brand">
              <div className="guide-footer-logo">Ma Facture Pro</div>
              <p>La facturation électronique conforme, simple et gratuite. Conçu et hébergé en France. Open-source (MIT) sur GitHub.</p>
            </div>
            <div className="guide-footer-col">
              <h4>Produit</h4>
              <ul>
                <li><Link to="/">Fonctionnalités</Link></li>
                <li><Link to="/pricing">Tarifs</Link></li>
                <li><Link to="/register">Créer un compte</Link></li>
              </ul>
            </div>
            <div className="guide-footer-col">
              <h4>Ressources</h4>
              <ul>
                <li><a href="#partie-1">Guide de la réforme</a></li>
                <li><a href="#partie-2">Les formats</a></li>
                <li><a href="#partie-6">Questions pratiques</a></li>
              </ul>
            </div>
            <div className="guide-footer-col">
              <h4>Légal</h4>
              <ul>
                <li><Link to="/mentions-legales">Mentions légales</Link></li>
                <li><Link to="/confidentialite">Politique de confidentialité</Link></li>
                <li><Link to="/cgu">CGU</Link></li>
              </ul>
            </div>
          </div>
          <div className="guide-footer-bottom">
            © 2026 Ma Facture Pro — Pierre-Arthur Demengel · Code source sous licence MIT
          </div>
        </div>
      </footer>
    </div>
  );
}
