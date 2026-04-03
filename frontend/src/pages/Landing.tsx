import { Link } from 'react-router-dom';

// Couleurs et styles de la landing page
const colors = {
  primary: '#2563eb',
  primaryDark: '#1d4ed8',
  primaryLight: '#dbeafe',
  dark: '#111827',
  gray: '#6b7280',
  grayLight: '#f9fafb',
  border: '#e5e7eb',
  white: '#ffffff',
  green: '#059669',
  greenLight: '#d1fae5',
};

const styles = {
  // En-tete
  header: {
    display: 'flex',
    justifyContent: 'space-between',
    alignItems: 'center',
    padding: '16px 40px',
    borderBottom: `1px solid ${colors.border}`,
    background: colors.white,
  } as React.CSSProperties,
  logo: {
    fontSize: '24px',
    fontWeight: 700,
    color: colors.primary,
    textDecoration: 'none',
  } as React.CSSProperties,
  headerLinks: {
    display: 'flex',
    gap: '24px',
    alignItems: 'center',
  } as React.CSSProperties,
  headerLink: {
    color: colors.gray,
    textDecoration: 'none',
    fontSize: '15px',
  } as React.CSSProperties,
  ctaButton: {
    background: colors.primary,
    color: colors.white,
    padding: '10px 24px',
    borderRadius: '8px',
    textDecoration: 'none',
    fontWeight: 600,
    fontSize: '15px',
    border: 'none',
    cursor: 'pointer',
  } as React.CSSProperties,
  ctaButtonOutline: {
    background: 'transparent',
    color: colors.primary,
    padding: '10px 24px',
    borderRadius: '8px',
    textDecoration: 'none',
    fontWeight: 600,
    fontSize: '15px',
    border: `2px solid ${colors.primary}`,
    cursor: 'pointer',
  } as React.CSSProperties,

  // Hero
  hero: {
    padding: '80px 40px',
    textAlign: 'center' as const,
    background: `linear-gradient(180deg, ${colors.white} 0%, ${colors.primaryLight} 100%)`,
  } as React.CSSProperties,
  heroTitle: {
    fontSize: '48px',
    fontWeight: 700,
    color: colors.dark,
    margin: '0 0 24px',
    lineHeight: 1.2,
    maxWidth: '700px',
    marginLeft: 'auto',
    marginRight: 'auto',
  } as React.CSSProperties,
  heroSubtitle: {
    fontSize: '20px',
    color: colors.gray,
    margin: '0 0 40px',
    maxWidth: '600px',
    marginLeft: 'auto',
    marginRight: 'auto',
    lineHeight: 1.6,
  } as React.CSSProperties,
  heroCtas: {
    display: 'flex',
    gap: '16px',
    justifyContent: 'center',
    flexWrap: 'wrap' as const,
  } as React.CSSProperties,
  badge: {
    display: 'inline-block',
    background: colors.greenLight,
    color: colors.green,
    padding: '6px 16px',
    borderRadius: '20px',
    fontSize: '14px',
    fontWeight: 600,
    marginBottom: '24px',
  } as React.CSSProperties,

  // Section generique
  section: {
    padding: '80px 40px',
  } as React.CSSProperties,
  sectionAlt: {
    padding: '80px 40px',
    background: colors.grayLight,
  } as React.CSSProperties,
  sectionTitle: {
    fontSize: '32px',
    fontWeight: 700,
    color: colors.dark,
    margin: '0 0 16px',
    textAlign: 'center' as const,
  } as React.CSSProperties,
  sectionSubtitle: {
    fontSize: '18px',
    color: colors.gray,
    margin: '0 0 48px',
    textAlign: 'center' as const,
    maxWidth: '600px',
    marginLeft: 'auto',
    marginRight: 'auto',
  } as React.CSSProperties,

  // Grille de fonctionnalites
  featuresGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
    gap: '32px',
    maxWidth: '1000px',
    margin: '0 auto',
  } as React.CSSProperties,
  featureCard: {
    background: colors.white,
    border: `1px solid ${colors.border}`,
    borderRadius: '12px',
    padding: '32px',
    textAlign: 'left' as const,
  } as React.CSSProperties,
  featureIcon: {
    fontSize: '32px',
    marginBottom: '16px',
  } as React.CSSProperties,
  featureTitle: {
    fontSize: '18px',
    fontWeight: 600,
    color: colors.dark,
    margin: '0 0 8px',
  } as React.CSSProperties,
  featureDesc: {
    fontSize: '15px',
    color: colors.gray,
    margin: 0,
    lineHeight: 1.6,
  } as React.CSSProperties,

  // Tableau de prix
  pricingGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))',
    gap: '24px',
    maxWidth: '900px',
    margin: '0 auto',
  } as React.CSSProperties,
  pricingCard: {
    background: colors.white,
    border: `1px solid ${colors.border}`,
    borderRadius: '12px',
    padding: '32px',
    textAlign: 'center' as const,
  } as React.CSSProperties,
  pricingCardFeatured: {
    background: colors.white,
    border: `2px solid ${colors.primary}`,
    borderRadius: '12px',
    padding: '32px',
    textAlign: 'center' as const,
    position: 'relative' as const,
  } as React.CSSProperties,
  pricingName: {
    fontSize: '20px',
    fontWeight: 600,
    color: colors.dark,
    margin: '0 0 8px',
  } as React.CSSProperties,
  pricingPrice: {
    fontSize: '40px',
    fontWeight: 700,
    color: colors.dark,
    margin: '16px 0 4px',
  } as React.CSSProperties,
  pricingPeriod: {
    fontSize: '15px',
    color: colors.gray,
    margin: '0 0 24px',
  } as React.CSSProperties,
  pricingFeatures: {
    listStyle: 'none',
    padding: 0,
    margin: '0 0 32px',
    textAlign: 'left' as const,
  } as React.CSSProperties,
  pricingFeature: {
    padding: '8px 0',
    fontSize: '15px',
    color: colors.gray,
    borderBottom: `1px solid ${colors.border}`,
  } as React.CSSProperties,

  // Conformite
  conformiteGrid: {
    display: 'grid',
    gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))',
    gap: '24px',
    maxWidth: '800px',
    margin: '0 auto',
  } as React.CSSProperties,
  conformiteItem: {
    textAlign: 'center' as const,
    padding: '24px',
  } as React.CSSProperties,
  conformiteValue: {
    fontSize: '28px',
    fontWeight: 700,
    color: colors.primary,
    margin: '0 0 8px',
  } as React.CSSProperties,
  conformiteLabel: {
    fontSize: '14px',
    color: colors.gray,
    margin: 0,
  } as React.CSSProperties,

  // Footer
  footer: {
    padding: '40px',
    borderTop: `1px solid ${colors.border}`,
    textAlign: 'center' as const,
    color: colors.gray,
    fontSize: '14px',
  } as React.CSSProperties,
};

// Page d'accueil publique
export default function Landing() {
  return (
    <div>
      {/* En-tete */}
      <header style={styles.header}>
        <span style={styles.logo}>Factura</span>
        <div style={styles.headerLinks}>
          <a href="#fonctionnalites" style={styles.headerLink}>Fonctionnalites</a>
          <a href="#conformite" style={styles.headerLink}>Conformite</a>
          <a href="#tarifs" style={styles.headerLink}>Tarifs</a>
          <Link to="/login" style={styles.headerLink}>Connexion</Link>
          <Link to="/login" style={styles.ctaButton}>Essai gratuit</Link>
        </div>
      </header>

      {/* Hero */}
      <section style={styles.hero}>
        <div style={styles.badge}>Conforme a la reforme 2026</div>
        <h1 style={styles.heroTitle}>
          La facturation electronique
          simple et conforme
        </h1>
        <p style={styles.heroSubtitle}>
          Creez, emettez et recevez vos factures electroniques
          au format Factur-X et UBL. Raccorde a Chorus Pro,
          conforme aux exigences DGFiP.
        </p>
        <div style={styles.heroCtas}>
          <Link to="/login" style={styles.ctaButton}>Commencer gratuitement</Link>
          <a href="#fonctionnalites" style={styles.ctaButtonOutline}>Decouvrir</a>
        </div>
      </section>

      {/* Conformite en chiffres */}
      <section style={styles.sectionAlt} id="conformite">
        <h2 style={styles.sectionTitle}>Conforme des le premier jour</h2>
        <p style={styles.sectionSubtitle}>
          Factura respecte toutes les exigences de la reforme de la facturation
          electronique francaise et du standard europeen EN 16931.
        </p>
        <div style={styles.conformiteGrid}>
          <div style={styles.conformiteItem}>
            <p style={styles.conformiteValue}>EN 16931</p>
            <p style={styles.conformiteLabel}>Norme europeenne respectee</p>
          </div>
          <div style={styles.conformiteItem}>
            <p style={styles.conformiteValue}>Factur-X</p>
            <p style={styles.conformiteLabel}>PDF/A-3 + XML CII embarque</p>
          </div>
          <div style={styles.conformiteItem}>
            <p style={styles.conformiteValue}>UBL 2.1</p>
            <p style={styles.conformiteLabel}>Peppol BIS Billing 3.0</p>
          </div>
          <div style={styles.conformiteItem}>
            <p style={styles.conformiteValue}>10 ans</p>
            <p style={styles.conformiteLabel}>Archivage legal en France</p>
          </div>
        </div>
      </section>

      {/* Fonctionnalites */}
      <section style={styles.section} id="fonctionnalites">
        <h2 style={styles.sectionTitle}>Tout ce qu'il faut pour facturer</h2>
        <p style={styles.sectionSubtitle}>
          De la creation a l'archivage, Factura gere l'integralite
          du cycle de vie de vos factures.
        </p>
        <div style={styles.featuresGrid}>
          <div style={styles.featureCard}>
            <div style={styles.featureIcon}>&#128221;</div>
            <h3 style={styles.featureTitle}>Creation guidee</h3>
            <p style={styles.featureDesc}>
              Saisie intuitive avec calcul automatique des montants HT, TVA et TTC.
              Multi-taux TVA (0%, 5.5%, 10%, 20%).
            </p>
          </div>
          <div style={styles.featureCard}>
            <div style={styles.featureIcon}>&#128196;</div>
            <h3 style={styles.featureTitle}>Formats conformes</h3>
            <p style={styles.featureDesc}>
              Generation automatique en Factur-X (PDF/A-3 + XML) et UBL 2.1.
              Profil EN 16931 garanti.
            </p>
          </div>
          <div style={styles.featureCard}>
            <div style={styles.featureIcon}>&#128640;</div>
            <h3 style={styles.featureTitle}>Emission automatique</h3>
            <p style={styles.featureDesc}>
              Transmission directe via Chorus Pro (PDP). Suivi du statut
              en temps reel : deposee, acceptee, rejetee, payee.
            </p>
          </div>
          <div style={styles.featureCard}>
            <div style={styles.featureIcon}>&#128274;</div>
            <h3 style={styles.featureTitle}>Piste d'audit fiable</h3>
            <p style={styles.featureDesc}>
              Journal immutable de chaque evenement. Hash SHA-256 pour
              l'integrite. Conforme aux exigences PAF de la DGFiP.
            </p>
          </div>
          <div style={styles.featureCard}>
            <div style={styles.featureIcon}>&#128451;</div>
            <h3 style={styles.featureTitle}>Archivage 10 ans</h3>
            <p style={styles.featureDesc}>
              Stockage securise en France (Scaleway). Versioning S3, aucune
              suppression possible. Conformite article 289 VII du CGI.
            </p>
          </div>
          <div style={styles.featureCard}>
            <div style={styles.featureIcon}>&#128170;</div>
            <h3 style={styles.featureTitle}>API ouverte</h3>
            <p style={styles.featureDesc}>
              API REST documentee (OpenAPI 3.1) pour integrer Factura
              a vos outils existants : ERP, comptabilite, CRM.
            </p>
          </div>
        </div>
      </section>

      {/* Tarifs */}
      <section style={styles.sectionAlt} id="tarifs">
        <h2 style={styles.sectionTitle}>Tarifs simples et transparents</h2>
        <p style={styles.sectionSubtitle}>
          Commencez gratuitement, passez au plan Pro quand votre activite grandit.
        </p>
        <div style={styles.pricingGrid}>
          <div style={styles.pricingCard}>
            <h3 style={styles.pricingName}>Gratuit</h3>
            <p style={{ ...styles.featureDesc, margin: 0 }}>Pour demarrer</p>
            <p style={styles.pricingPrice}>0 &euro;</p>
            <p style={styles.pricingPeriod}>par mois</p>
            <ul style={styles.pricingFeatures}>
              <li style={styles.pricingFeature}>30 factures / mois</li>
              <li style={styles.pricingFeature}>Factur-X + UBL</li>
              <li style={styles.pricingFeature}>Emission via Chorus Pro</li>
              <li style={styles.pricingFeature}>Archivage 10 ans</li>
            </ul>
            <Link to="/login" style={styles.ctaButtonOutline}>Commencer</Link>
          </div>
          <div style={styles.pricingCardFeatured}>
            <h3 style={styles.pricingName}>Pro</h3>
            <p style={{ ...styles.featureDesc, margin: 0 }}>Pour les independants</p>
            <p style={styles.pricingPrice}>14,90 &euro;</p>
            <p style={styles.pricingPeriod}>par mois, HT</p>
            <ul style={styles.pricingFeatures}>
              <li style={styles.pricingFeature}>Factures illimitees</li>
              <li style={styles.pricingFeature}>Factur-X + UBL</li>
              <li style={styles.pricingFeature}>Emission via Chorus Pro</li>
              <li style={styles.pricingFeature}>Archivage 10 ans</li>
              <li style={styles.pricingFeature}>Support prioritaire</li>
            </ul>
            <Link to="/login" style={styles.ctaButton}>Essai gratuit 30 jours</Link>
          </div>
          <div style={styles.pricingCard}>
            <h3 style={styles.pricingName}>Equipe</h3>
            <p style={{ ...styles.featureDesc, margin: 0 }}>Pour les PME</p>
            <p style={styles.pricingPrice}>29,90 &euro;</p>
            <p style={styles.pricingPeriod}>par mois, HT</p>
            <ul style={styles.pricingFeatures}>
              <li style={styles.pricingFeature}>Factures illimitees</li>
              <li style={styles.pricingFeature}>Multi-utilisateurs</li>
              <li style={styles.pricingFeature}>API illimitee</li>
              <li style={styles.pricingFeature}>Export FEC comptable</li>
              <li style={styles.pricingFeature}>Support dedie</li>
            </ul>
            <Link to="/login" style={styles.ctaButtonOutline}>Nous contacter</Link>
          </div>
        </div>
      </section>

      {/* CTA final */}
      <section style={{ ...styles.section, textAlign: 'center' }}>
        <h2 style={styles.sectionTitle}>Pret pour la facturation electronique ?</h2>
        <p style={{ ...styles.sectionSubtitle, marginBottom: '32px' }}>
          La reforme entre en vigueur le 1er septembre 2026.
          Anticipez des maintenant avec Factura.
        </p>
        <Link to="/login" style={styles.ctaButton}>Creer mon compte gratuitement</Link>
      </section>

      {/* Footer */}
      <footer style={styles.footer}>
        <p>&copy; 2026 Factura SAS — Tous droits reserves</p>
        <p style={{ marginTop: '8px' }}>
          15 rue de la Paix, 75002 Paris — SIREN 930 538 111
        </p>
      </footer>
    </div>
  );
}
