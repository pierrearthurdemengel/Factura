// Tests E2E pour le flux de facturation.
describe('Factures', () => {
  beforeEach(() => {
    // Simuler une session authentifiee
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });
  });

  it('affiche la liste des factures', () => {
    cy.intercept('GET', '/api/invoices*', {
      body: {
        'hydra:member': [],
        'hydra:totalItems': 0,
      },
    }).as('getInvoices');

    cy.visit('/invoices');
    cy.wait('@getInvoices');
    cy.contains('Factures');
    cy.contains('Nouvelle facture');
  });

  it('affiche le formulaire de creation', () => {
    cy.intercept('GET', '/api/clients*', {
      body: { 'hydra:member': [] },
    });

    cy.visit('/invoices/new');
    cy.contains('Nouvelle facture');
  });

  it('affiche les filtres de statut', () => {
    cy.intercept('GET', '/api/invoices*', {
      body: {
        'hydra:member': [],
        'hydra:totalItems': 0,
      },
    });

    cy.visit('/invoices');
    cy.get('select').should('exist');
    cy.get('select option').should('have.length.at.least', 2);
  });

  it('affiche le detail d\'une facture', () => {
    const mockInvoice = {
      id: 'test-id',
      number: 'FA-2026-0001',
      status: 'DRAFT',
      issueDate: '2026-01-15',
      dueDate: '2026-02-15',
      currency: 'EUR',
      totalExcludingTax: '1000.00',
      totalTax: '200.00',
      totalIncludingTax: '1200.00',
      seller: { name: 'Ma Societe', siren: '123456789', addressLine1: '1 rue Test', postalCode: '75001', city: 'Paris' },
      buyer: { name: 'Client SARL', siren: '987654321', addressLine1: '2 av Client', postalCode: '69001', city: 'Lyon' },
      lines: [
        { id: 'line-1', position: 1, description: 'Prestation', quantity: '10', unit: 'HUR', unitPriceExcludingTax: '100.00', vatRate: '20', lineAmount: '1000.00', vatAmount: '200.00' },
      ],
      legalMention: null,
      paymentTerms: null,
      pdpReference: null,
    };

    cy.intercept('GET', '/api/invoices/test-id', { body: mockInvoice }).as('getInvoice');
    cy.intercept('GET', '/api/invoices/test-id/events', { body: [] }).as('getEvents');

    cy.visit('/invoices/test-id');
    cy.wait('@getInvoice');
    cy.contains('FA-2026-0001');
    cy.contains('Ma Societe');
    cy.contains('Client SARL');
    cy.contains('1200.00 EUR');
    cy.contains('Envoyer');
  });

  it('affiche les boutons de telechargement', () => {
    const mockInvoice = {
      id: 'test-id',
      number: 'FA-2026-0001',
      status: 'SENT',
      issueDate: '2026-01-15',
      dueDate: null,
      currency: 'EUR',
      totalExcludingTax: '1000.00',
      totalTax: '200.00',
      totalIncludingTax: '1200.00',
      seller: { name: 'Test', siren: '123456789', addressLine1: '1 rue', postalCode: '75001', city: 'Paris' },
      buyer: { name: 'Client', addressLine1: '2 av', postalCode: '69001', city: 'Lyon' },
      lines: [],
      legalMention: null,
      paymentTerms: null,
      pdpReference: null,
    };

    cy.intercept('GET', '/api/invoices/test-id', { body: mockInvoice });
    cy.intercept('GET', '/api/invoices/test-id/events', { body: [] });

    cy.visit('/invoices/test-id');
    cy.contains('Telecharger PDF');
    cy.contains('Telecharger XML CII');
    cy.contains('Telecharger XML UBL');
  });
});
