// Tests E2E pour le dashboard et les pages principales
describe('Dashboard', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    cy.intercept('GET', '/api/companies/me', {
      body: { id: 'comp-1', name: 'Ma Societe', siren: '123456789' },
    });

    cy.intercept('GET', '/api/invoices*', {
      body: {
        'hydra:member': [
          {
            id: 'inv-1', number: 'FA-2026-001', status: 'PAID',
            issueDate: '2026-01-15', dueDate: '2026-02-15', currency: 'EUR',
            totalExcludingTax: '1000.00', totalTax: '200.00', totalIncludingTax: '1200.00',
            buyer: { name: 'Client A' }, seller: { name: 'Ma Societe' },
            lines: [], createdAt: '2026-01-15T10:00:00Z',
          },
          {
            id: 'inv-2', number: 'FA-2026-002', status: 'SENT',
            issueDate: '2026-02-01', dueDate: '2026-03-01', currency: 'EUR',
            totalExcludingTax: '2000.00', totalTax: '400.00', totalIncludingTax: '2400.00',
            buyer: { name: 'Client B' }, seller: { name: 'Ma Societe' },
            lines: [], createdAt: '2026-02-01T10:00:00Z',
          },
        ],
      },
    }).as('getInvoices');
  });

  it('affiche le dashboard avec les KPI', () => {
    cy.visit('/');
    cy.wait('@getInvoices');
    cy.contains('Tableau de bord');
  });

  it('affiche le graphique de revenus', () => {
    cy.visit('/');
    cy.wait('@getInvoices');
    // Le composant RevenueChart existe
    cy.get('svg').should('exist');
  });

  it('charge le dashboard sans erreurs console', () => {
    cy.visit('/', {
      onBeforeLoad(win) {
        cy.stub(win.console, 'error').as('consoleError');
      },
    });
    cy.wait('@getInvoices');
    // Pas d'erreurs fatales (les erreurs dev React sont acceptees)
    cy.get('@consoleError').then((stub) => {
      const calls = (stub as unknown as { args: string[][] }).args || [];
      const fatalErrors = calls.filter(
        (args: string[]) => !args[0]?.toString().includes('pendingLanes') && !args[0]?.toString().includes('startTime')
      );
      expect(fatalErrors).to.have.length(0);
    });
  });
});

describe('Navigation', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    cy.intercept('GET', '/api/companies/me', {
      body: { id: 'comp-1', name: 'Ma Societe', siren: '123456789' },
    });
  });

  it('redirige vers login si non authentifie', () => {
    cy.window().then((win) => {
      win.sessionStorage.removeItem('jwt_token');
    });
    cy.visit('/invoices');
    cy.url().should('include', '/login');
  });

  it('affiche la navbar avec les liens principaux', () => {
    cy.intercept('GET', '/api/invoices*', { body: { 'hydra:member': [] } });
    cy.visit('/');
    cy.get('.navbar').should('exist');
    cy.contains('Factures');
    cy.contains('Devis');
    cy.contains('Clients');
    cy.contains('Parametres');
  });

  it('ouvre le command menu avec Ctrl+K', () => {
    cy.intercept('GET', '/api/invoices*', { body: { 'hydra:member': [] } });
    cy.visit('/');
    cy.get('body').type('{ctrl}k');
    cy.get('.command-dialog').should('be.visible');
    cy.contains('Rechercher une facture ou naviguer');
  });
});
