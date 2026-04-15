// Tests E2E pour le module devis (quotes) et acomptes (deposits)
describe('Devis', () => {
  beforeEach(() => {
    cy.window().then((win) => {
      win.sessionStorage.setItem('jwt_token', 'test-token');
    });

    // Intercepter les appels API communes
    cy.intercept('GET', '/api/companies/me', {
      body: { id: 'comp-1', name: 'Ma Societe', siren: '123456789' },
    });
  });

  it('affiche la liste des devis', () => {
    cy.intercept('GET', '/api/quotes*', {
      body: {
        'hydra:member': [
          {
            id: 'q-1', number: 'DV-2026-001', status: 'DRAFT',
            issueDate: '2026-01-15', validUntil: '2026-02-15',
            totalIncludingTax: '2400.00', buyer: { name: 'Client A' },
          },
        ],
      },
    }).as('getQuotes');

    cy.visit('/quotes');
    cy.wait('@getQuotes');
    cy.contains('Devis');
    cy.contains('DV-2026-001');
    cy.contains('Client A');
  });

  it('affiche le formulaire de creation avec acompte', () => {
    cy.intercept('GET', '/api/clients*', {
      body: {
        'hydra:member': [
          { '@id': '/api/clients/c1', id: 'c1', name: 'Client Test', siren: null, vatNumber: null, addressLine1: '1 rue', postalCode: '75001', city: 'Paris', countryCode: 'FR' },
        ],
      },
    }).as('getClients');

    cy.visit('/quotes/new');
    cy.wait('@getClients');
    cy.contains('Nouveau devis');

    // Verifier que le toggle acompte existe
    cy.get('#deposit-toggle').should('exist').and('not.be.checked');

    // Activer l'acompte
    cy.get('#deposit-toggle').check();
    cy.contains('Pourcentage');
    cy.contains("Montant de l'acompte TTC");
    cy.contains('Solde restant TTC');
  });

  it('calcule le montant de l\'acompte correctement', () => {
    cy.intercept('GET', '/api/clients*', {
      body: { 'hydra:member': [] },
    });

    cy.visit('/quotes/new');

    // Ajouter un prix
    cy.get('input[type="text"]').first().type('Prestation dev');
    cy.get('input[type="number"]').eq(0).clear().type('1');
    cy.get('input[type="number"]').eq(1).clear().type('1000');

    // Activer acompte a 30%
    cy.get('#deposit-toggle').check();
    // Le montant 30% de 1200 TTC (1000 HT + 200 TVA) = 360
    cy.contains('360.00 EUR');
    cy.contains('840.00 EUR');
  });

  it('affiche le detail avec acompte', () => {
    const mockQuote = {
      id: 'q-1', number: 'DV-2026-001', status: 'SENT',
      issueDate: '2026-01-15', validUntil: '2026-02-15',
      totalExcludingTax: '1000.00', totalTax: '200.00', totalIncludingTax: '1200.00',
      depositPercent: 30, depositAmount: '360.00',
      seller: { name: 'Ma Societe', siren: '123456789', addressLine1: '1 rue', postalCode: '75001', city: 'Paris' },
      buyer: { name: 'Client A', addressLine1: '2 av', postalCode: '69001', city: 'Lyon' },
      lines: [{ id: 'l1', position: 1, description: 'Dev', quantity: '10', unit: 'HUR', unitPriceExcludingTax: '100.00', vatRate: '20', lineAmount: '1000.00', vatAmount: '200.00' }],
      legalMention: null, paymentTerms: null, invoiceId: null,
    };

    cy.intercept('GET', '/api/quotes/q-1', { body: mockQuote }).as('getQuote');
    cy.visit('/quotes/q-1');
    cy.wait('@getQuote');

    cy.contains('Acompte demande');
    cy.contains('30%');
    cy.contains('360.00 EUR');
    cy.contains('840.00');
  });

  it('envoie un devis', () => {
    const mockQuote = {
      id: 'q-1', number: 'DV-2026-001', status: 'DRAFT',
      issueDate: '2026-01-15', validUntil: '2026-02-15',
      totalExcludingTax: '1000.00', totalTax: '200.00', totalIncludingTax: '1200.00',
      depositPercent: null, depositAmount: null,
      seller: { name: 'Test', siren: '123', addressLine1: '1', postalCode: '75001', city: 'Paris' },
      buyer: { name: 'Client', addressLine1: '2', postalCode: '69001', city: 'Lyon' },
      lines: [], legalMention: null, paymentTerms: null, invoiceId: null,
    };

    cy.intercept('GET', '/api/quotes/q-1', { body: mockQuote }).as('getQuote');
    cy.intercept('POST', '/api/quotes/q-1/send', { body: { ...mockQuote, status: 'SENT' } }).as('sendQuote');

    cy.visit('/quotes/q-1');
    cy.wait('@getQuote');
    cy.contains('Envoyer au client').click();
    cy.wait('@sendQuote');
  });
});
