// Tests E2E pour le module impayes
describe('Impayes', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    cy.intercept('GET', '/api/companies/me', {
      body: { id: 'comp-1', name: 'Ma Societe', siren: '123456789' },
    });
  });

  it('affiche la page sans factures en retard', () => {
    cy.intercept('GET', '/api/invoices*', {
      body: { 'hydra:member': [] },
    }).as('getOverdue');

    cy.visit('/unpaid');
    cy.wait('@getOverdue');
    cy.contains('Impayes');
    cy.contains('Aucun impaye');
    cy.contains('Toutes vos factures sont a jour');
  });

  it('affiche les factures en retard avec KPIs', () => {
    const pastDate = new Date();
    pastDate.setDate(pastDate.getDate() - 20);

    cy.intercept('GET', '/api/invoices*', {
      body: {
        'hydra:member': [
          {
            id: 'inv-1', number: 'FA-2026-005', status: 'SENT',
            dueDate: pastDate.toISOString().split('T')[0],
            totalIncludingTax: '3600.00',
            buyer: { name: 'Client Retard' },
          },
        ],
      },
    }).as('getOverdue');

    // Les relances pour cette facture
    cy.intercept('GET', '/api/invoices/inv-1/reminders', {
      body: {
        'hydra:member': [
          { id: 'r1', type: 'email', sentAt: '2026-04-01T10:00:00Z', channel: 'email' },
        ],
      },
    }).as('getReminders');

    cy.visit('/unpaid');
    cy.wait('@getOverdue');

    cy.contains('Total impayes');
    cy.contains('3 600,00 EUR');
    cy.contains('Factures en retard');
    cy.contains('FA-2026-005');
    cy.contains('Client Retard');
    cy.contains('1 relance');
  });

  it('affiche les tranches de retard', () => {
    const d15 = new Date(); d15.setDate(d15.getDate() - 10);
    const d45 = new Date(); d45.setDate(d45.getDate() - 45);

    cy.intercept('GET', '/api/invoices*', {
      body: {
        'hydra:member': [
          { id: 'inv-a', number: 'FA-A', status: 'SENT', dueDate: d15.toISOString().split('T')[0], totalIncludingTax: '1000.00', buyer: { name: 'A' } },
          { id: 'inv-b', number: 'FA-B', status: 'SENT', dueDate: d45.toISOString().split('T')[0], totalIncludingTax: '2000.00', buyer: { name: 'B' } },
        ],
      },
    }).as('getOverdue');

    cy.intercept('GET', '/api/invoices/*/reminders', { body: { 'hydra:member': [] } });

    cy.visit('/unpaid');
    cy.wait('@getOverdue');

    cy.contains('1-15 jours (1)');
    cy.contains('31-60 jours (1)');
  });
});
