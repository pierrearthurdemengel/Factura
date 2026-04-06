import { useEffect } from 'react';
import { Link } from 'react-router-dom';
import './Landing.css';

// Hook pour les micro-animations au defilement de page
function useScrollReveal() {
  useEffect(() => {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('reveal-visible');
          // Desactiver l'option d'unobserve si on veut que l'animation se repete
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });

    document.querySelectorAll('.reveal').forEach((el) => observer.observe(el));

    return () => observer.disconnect();
  }, []);
}

// Page d'accueil publique - Version Haut de Gamme Minimaliste
export default function Landing() {
  useScrollReveal();

  return (
    <div>
      {/* En-tete */}
      <header className="l-header">
        <span className="l-logo">Ma Facture Pro</span>
        <div className="l-header-links">
          <a href="#fonctionnalites" className="l-header-link">Fonctionnalites</a>
          <a href="#conformite" className="l-header-link">Conformite</a>
          <a href="#tarifs" className="l-header-link">Tarifs</a>
          <Link to="/login" className="l-header-link">Connexion</Link>
          <Link to="/register" className="l-btn-primary">Essai gratuit</Link>
        </div>
      </header>

      {/* Hero */}
      <section className="l-hero">
        <div className="l-badge reveal">L'avenir de la facturation</div>
        <h1 className="l-hero-title reveal reveal-delay-1">
          L'evidence de la facturation<br/>electronique
        </h1>
        <p className="l-hero-subtitle reveal reveal-delay-2">
          Design epure, emission simple, conformite exigeante.
          Creez et recevez vos factures au format Factur-X et UBL, 
          directement raccorde a Chorus Pro.
        </p>
        <div className="l-hero-ctas reveal reveal-delay-3">
          <Link to="/register" className="l-btn-primary">Demarrer gratuitement</Link>
          <a href="#fonctionnalites" className="l-btn-outline">Explorer</a>
        </div>
      </section>

      {/* Conformite en chiffres */}
      <section className="l-section-alt" id="conformite">
        <h2 className="l-section-title reveal">Conformite garantie</h2>
        <p className="l-section-subtitle reveal reveal-delay-1">
          Un respect absolu du standard europeen EN 16931 et des exigences de la DGFiP en France.
        </p>
        <div className="l-grid-conformite">
          <div className="l-conformite-item reveal reveal-delay-1">
            <p className="l-conformite-val">EN 16931</p>
            <p className="l-conformite-lbl">Norme europeenne</p>
          </div>
          <div className="l-conformite-item reveal reveal-delay-2">
            <p className="l-conformite-val">Factur-X</p>
            <p className="l-conformite-lbl">PDF/A-3 embarque</p>
          </div>
          <div className="l-conformite-item reveal reveal-delay-3">
            <p className="l-conformite-val">UBL 2.1</p>
            <p className="l-conformite-lbl">Peppol BIS Billing</p>
          </div>
          <div className="l-conformite-item reveal reveal-delay-4">
            <p className="l-conformite-val">10 ans</p>
            <p className="l-conformite-lbl">Archivage legal</p>
          </div>
        </div>
      </section>

      {/* Fonctionnalites */}
      <section className="l-section" id="fonctionnalites">
        <h2 className="l-section-title reveal">L'excellence operationnelle</h2>
        <p className="l-section-subtitle reveal reveal-delay-1">
          De la creation a l'archivage legitime, tout est pense pour votre souverainete et votre fluidite professionnelle.
        </p>
        <div className="l-grid-features">
          <div className="l-feature-card reveal reveal-delay-1">
            <h3 className="l-feature-title">Creation guidee</h3>
            <p className="l-feature-desc">
              Saisie intuitive avec calcul immediat des montants. Multi-taux TVA.
            </p>
          </div>
          <div className="l-feature-card reveal reveal-delay-2">
            <h3 className="l-feature-title">Formats conformes</h3>
            <p className="l-feature-desc">
              Generation hybride automatique Factur-X et UBL 2.1 garantissant le standard EN 16931.
            </p>
          </div>
          <div className="l-feature-card reveal reveal-delay-3">
            <h3 className="l-feature-title">Emission directe</h3>
            <p className="l-feature-desc">
              Transmission automatique via Chorus Pro. Statuts traduits en temps reel (deposee, payee).
            </p>
          </div>
          <div className="l-feature-card reveal reveal-delay-4">
            <h3 className="l-feature-title">Piste d'audit fiable</h3>
            <p className="l-feature-desc">
              Journalisation immutable de vos evenements. Validite garantie par hachage cryptographique.
            </p>
          </div>
          <div className="l-feature-card reveal reveal-delay-5">
            <h3 className="l-feature-title">Coffre-fort legal</h3>
            <p className="l-feature-desc">
              Un stockage securise localise en France, protegeant vos archives pendant 10 annees incompressibles.
            </p>
          </div>
          <div className="l-feature-card reveal reveal-delay-6">
            <h3 className="l-feature-title">API unifiee</h3>
            <p className="l-feature-desc">
              L'entierete de la plateforme accessible via notre API REST documentee (OpenAPI 3.1).
            </p>
          </div>
        </div>
      </section>

      {/* Tarifs */}
      <section className="l-section-alt" id="tarifs">
        <h2 className="l-section-title reveal">Un modele cristallin</h2>
        <p className="l-section-subtitle reveal reveal-delay-1">
          Des grilles tarifaires transparentes, grandissant harmonieusement avec vous.
        </p>
        <div className="l-grid-pricing">
          <div className="l-pricing-card reveal reveal-delay-1">
            <h3 className="l-pricing-name">Fondation</h3>
            <p className="l-feature-desc">Pour explorer et initier</p>
            <p className="l-pricing-price">0 &euro;</p>
            <p className="l-pricing-period">mensuel</p>
            <ul className="l-pricing-features">
              <li className="l-pricing-feature">30 factures / mois</li>
              <li className="l-pricing-feature">Formats hybrides standardises</li>
              <li className="l-pricing-feature">Emission Chorus Pro native</li>
              <li className="l-pricing-feature">Archivage standard</li>
            </ul>
            <Link to="/register" className="l-btn-outline">Demarrer</Link>
          </div>
          <div className="l-pricing-card featured reveal reveal-delay-2">
            <h3 className="l-pricing-name">Independant</h3>
            <p className="l-feature-desc">Liberte absolue</p>
            <p className="l-pricing-price">14,90 &euro;</p>
            <p className="l-pricing-period">mensuel, HT</p>
            <ul className="l-pricing-features">
              <li className="l-pricing-feature">Volume de factures illimite</li>
              <li className="l-pricing-feature">Formats complets</li>
              <li className="l-pricing-feature">Emission en un clic</li>
              <li className="l-pricing-feature">Coffre-fort legal 10 ans</li>
              <li className="l-pricing-feature">Assistance prioritaire</li>
            </ul>
            <Link to="/register" className="l-btn-primary">S'engager sans frais 30 jours</Link>
          </div>
          <div className="l-pricing-card reveal reveal-delay-3">
            <h3 className="l-pricing-name">Entreprise</h3>
            <p className="l-feature-desc">Ecosysteme avance</p>
            <p className="l-pricing-price">29,90 &euro;</p>
            <p className="l-pricing-period">mensuel, HT</p>
            <ul className="l-pricing-features">
              <li className="l-pricing-feature">Acces multi-collaborateurs</li>
              <li className="l-pricing-feature">Integration API debridee</li>
              <li className="l-pricing-feature">Exports comptables (FEC)</li>
              <li className="l-pricing-feature">Support applicatif dedie</li>
            </ul>
            <Link to="/register" className="l-btn-outline">Demander un contact</Link>
          </div>
        </div>
      </section>

      {/* CTA final */}
      <section className="l-section">
        <h2 className="l-section-title reveal">Le futur commence maintenant</h2>
        <p className="l-section-subtitle reveal reveal-delay-1" style={{ marginBottom: '2rem' }}>
          N'attendez pas la reforme de 2026. Transitionnez avec serenite grace a Ma Facture Pro.
        </p>
        <div className="reveal reveal-delay-2">
          <Link to="/register" className="l-btn-primary">Creer mon espace</Link>
        </div>
      </section>

      {/* Footer */}
      <footer className="l-footer">
        <p>&copy; 2026 Ma Facture Pro — Tous droits reserves.</p>
        <p>15 rue de la Paix, 75002 Paris — SIREN 930 538 111</p>
      </footer>
    </div>
  );
}
