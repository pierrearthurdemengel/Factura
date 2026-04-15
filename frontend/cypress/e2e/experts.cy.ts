// Tests E2E pour la page experts partenaires
describe('Experts', () => {
  it('affiche les experts par defaut si API indisponible', () => {
    cy.intercept('GET', '/api/partners*', { statusCode: 404 });

    cy.visit('/experts');
    cy.contains('Experts partenaires');
    cy.contains('Cabinet Dupont');
    cy.contains('Comptabilite');
    cy.contains('Claire Martin');
    cy.contains('Juridique');
  });

  it('charge les experts depuis l\'API', () => {
    cy.intercept('GET', '/api/partners*', {
      body: {
        'hydra:member': [
          {
            name: 'Expert API',
            specialty: 'Finance',
            description: 'Expert charge depuis API.',
            location: 'Marseille',
            email: 'api@expert.fr',
            website: 'https://expert-api.fr',
          },
        ],
      },
    }).as('getExperts');

    cy.visit('/experts');
    cy.wait('@getExperts');
    cy.contains('Expert API');
    cy.contains('Finance');
    cy.contains('Marseille');
  });
});
