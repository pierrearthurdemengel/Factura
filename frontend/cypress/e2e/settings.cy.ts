// Tests E2E pour la page parametres
describe('Parametres', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    cy.intercept('GET', '/api/companies/me', {
      body: {
        id: 'comp-1', name: 'Ma Societe', siren: '123456789', siret: '12345678900012',
        vatNumber: 'FR12345678901', legalForm: 'SAS', nafCode: '6201Z',
        addressLine1: '1 rue Test', addressLine2: null, postalCode: '75001', city: 'Paris',
        countryCode: 'FR', iban: 'FR7612345678901234567890123', bic: 'BNPAFRPP', defaultPdp: null,
      },
    }).as('getCompany');
  });

  it('affiche les informations entreprise', () => {
    cy.visit('/settings');
    cy.wait('@getCompany');
    cy.contains('Parametres');
    cy.get('input[name="name"]').should('have.value', 'Ma Societe');
    cy.get('input[name="siren"]').should('have.value', '123456789');
  });

  it('sauvegarde les informations entreprise', () => {
    cy.intercept('PUT', '/api/companies/comp-1', { statusCode: 200, body: {} }).as('updateCompany');

    cy.visit('/settings');
    cy.wait('@getCompany');
    cy.get('input[name="name"]').clear().type('Ma Societe Modifiee');
    cy.get('button[type="submit"]').first().click();
    cy.wait('@updateCompany');
    cy.contains('enregistrees');
  });

  it('affiche l\'onglet facturation avec plan', () => {
    cy.intercept('GET', '/api/invoices*', { body: { 'hydra:member': [] } });
    cy.intercept('GET', '/api/subscription/current', {
      body: { plan: 'pro' },
    }).as('getSubscription');

    cy.visit('/settings');
    cy.wait('@getCompany');
    // Cliquer sur l'onglet facturation
    cy.contains('Facturation').click();
    cy.wait('@getSubscription');
  });

  it('affiche l\'onglet personnalisation', () => {
    cy.visit('/settings');
    cy.wait('@getCompany');
    cy.contains('Personnalisation').click();
    cy.contains('Logo');
    cy.contains('Couleur principale');
  });

  it('affiche l\'onglet relances', () => {
    cy.visit('/settings');
    cy.wait('@getCompany');
    cy.contains('Relances').click();
    cy.contains('Activer les relances');
    cy.contains('Premiere relance');
  });
});
