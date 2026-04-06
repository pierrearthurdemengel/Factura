// Tests E2E pour l'authentification et l'inscription.
describe('Authentification', () => {
  it('affiche la page de connexion', () => {
    cy.visit('/login');
    cy.contains('Connexion');
    cy.get('input[type="email"]').should('exist');
    cy.get('input[type="password"]').should('exist');
    cy.get('button[type="submit"]').contains('Se connecter');
  });

  it('affiche un lien vers inscription', () => {
    cy.visit('/login');
    cy.contains('Creer un compte').should('have.attr', 'href', '/register');
  });

  it('affiche la page d\'inscription', () => {
    cy.visit('/register');
    cy.contains('Creer un compte');
    cy.get('input[name="firstName"]').should('exist');
    cy.get('input[name="lastName"]').should('exist');
    cy.get('input[name="email"]').should('exist');
    cy.get('input[name="password"]').should('exist');
    cy.get('input[name="companyName"]').should('exist');
    cy.get('input[name="siren"]').should('exist');
    cy.get('select[name="legalForm"]').should('exist');
  });

  it('refuse la connexion avec des identifiants invalides', () => {
    cy.visit('/login');
    cy.get('input[type="email"]').type('invalid@example.com');
    cy.get('input[type="password"]').type('wrongpassword');
    cy.get('button[type="submit"]').click();
    cy.contains('Email ou mot de passe incorrect');
  });

  it('affiche la landing page publique', () => {
    cy.visit('/');
    cy.contains('Ma Facture Pro');
  });
});
