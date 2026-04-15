// Tests E2E pour les simulateurs fiscaux
describe('Simulateurs', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    cy.intercept('GET', '/api/companies/me', {
      body: { id: 'comp-1', name: 'Ma Societe', siren: '123456789' },
    });

    // Config fiscale par defaut ou depuis API
    cy.intercept('GET', '/api/tax/config', { statusCode: 404 });
  });

  it('affiche les onglets des simulateurs', () => {
    cy.visit('/simulators');
    cy.contains('Simulateurs');
    cy.contains('Micro vs Reel');
    cy.contains('Estimation IR');
    cy.contains('EI vs SASU');
  });

  it('simule micro vs reel', () => {
    cy.visit('/simulators');
    cy.contains('Micro-entreprise vs Regime reel');
    cy.contains('Micro-entreprise');
    cy.contains('Regime reel');
    // Verifie que les resultats s'affichent
    cy.contains('EUR');
    cy.contains('plus avantageuse');
  });

  it('simule l\'estimation IR', () => {
    cy.visit('/simulators');
    cy.contains('Estimation IR').click();
    cy.contains('Estimation impot sur le revenu');
    cy.contains('Revenu net imposable');
    cy.contains('Nombre de parts');
    cy.contains('Impot estime');
  });

  it('simule EI vs SASU', () => {
    cy.visit('/simulators');
    cy.contains('EI vs SASU').click();
    cy.contains('Entreprise individuelle vs SASU');
    cy.contains('EI (micro)');
    cy.contains('SASU');
    cy.contains('Remuneration');
    cy.contains('Dividendes');
  });

  it('charge la config fiscale depuis l\'API', () => {
    cy.intercept('GET', '/api/tax/config', {
      body: {
        microAbatementService: 0.50,
        microAbatementVente: 0.71,
        microUrssafService: 0.22,
        microUrssafVente: 0.123,
        reelUrssafRate: 0.45,
        sasuChargesRate: 0.30,
        sasuRemunerationRate: 0.60,
        sasuISRate: 0.25,
        eiUrssafRate: 0.22,
        irBrackets: [
          { limit: 11497, rate: 0 },
          { limit: 29315, rate: 0.11 },
          { limit: 82341, rate: 0.30 },
          { limit: 177106, rate: 0.41 },
          { limit: 999999999, rate: 0.45 },
        ],
        year: 2027,
      },
    }).as('getTaxConfig');

    cy.visit('/simulators');
    cy.wait('@getTaxConfig');
    cy.contains('bareme 2027');
  });
});
