// Tests E2E pour le module declarations fiscales
describe('Declarations fiscales', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    cy.intercept('GET', '/api/companies/me', {
      body: { id: 'comp-1', name: 'Ma Societe', siren: '123456789' },
    });
  });

  it('affiche le calendrier des echeances', () => {
    cy.intercept('GET', '/api/declarations/deadlines*', { statusCode: 404 });
    cy.intercept('GET', '/api/tax/vat/balance*', { statusCode: 404 });
    cy.intercept('GET', '/api/tax/urssaf/contributions*', { statusCode: 404 });

    cy.visit('/declarations');
    cy.contains('Declarations fiscales');
    cy.contains('Calendrier');
    cy.contains('TVA');
    cy.contains('URSSAF');
    // Verifie qu'au moins des echeances par defaut sont affichees
    cy.contains('Declaration TVA CA3');
  });

  it('charge les echeances depuis l\'API quand disponibles', () => {
    const customDeadlines = [
      { id: 'api-1', type: 'tva', label: 'TVA personnalisee', dueDate: '2026-06-24', status: 'pending', amount: null },
    ];

    cy.intercept('GET', '/api/declarations/deadlines*', {
      body: { 'hydra:member': customDeadlines },
    }).as('getDeadlines');
    cy.intercept('GET', '/api/tax/vat/balance*', { statusCode: 404 });
    cy.intercept('GET', '/api/tax/urssaf/contributions*', { statusCode: 404 });

    cy.visit('/declarations');
    cy.wait('@getDeadlines');
    cy.contains('TVA personnalisee');
  });

  it('affiche la situation TVA', () => {
    cy.intercept('GET', '/api/declarations/deadlines*', { statusCode: 404 });
    cy.intercept('GET', '/api/tax/vat/balance*', {
      body: { collected: '5000.00', deductible: '1200.00', balance: '3800.00', period: '2026-04' },
    }).as('getVat');
    cy.intercept('GET', '/api/tax/urssaf/contributions*', { statusCode: 404 });

    cy.visit('/declarations');
    // Basculer vers l'onglet TVA
    cy.contains('TVA').click();
    cy.wait('@getVat');
    cy.contains('5 000,00 EUR');
    cy.contains('TVA collectee');
    cy.contains('TVA a payer');
  });

  it('affiche les cotisations URSSAF', () => {
    cy.intercept('GET', '/api/declarations/deadlines*', { statusCode: 404 });
    cy.intercept('GET', '/api/tax/vat/balance*', { statusCode: 404 });
    cy.intercept('GET', '/api/tax/urssaf/contributions*', {
      body: { totalContributions: '8500.00' },
    }).as('getUrssaf');

    cy.visit('/declarations');
    cy.contains('URSSAF').click();
    cy.wait('@getUrssaf');
    cy.contains('8 500,00 EUR');
    cy.contains('Estimation');
  });
});
