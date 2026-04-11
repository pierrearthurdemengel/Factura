import { useState, useCallback } from 'react';
import './AppLayout.css';
import './ApiDocs.css';

// Groupe d'endpoints dans la navigation laterale
type EndpointGroup = 'invoices' | 'quotes' | 'clients' | 'company' | 'webhooks';

// Methode HTTP supportee par l'API
type HttpMethod = 'GET' | 'POST' | 'PUT' | 'DELETE';

// Definition d'un endpoint de l'API
interface Endpoint {
  method: HttpMethod;
  path: string;
  description: string;
  requestExample: string | null;
  responseExample: string;
  parameters: EndpointParameter[];
}

// Parametre d'un endpoint pour la section "Essayer"
interface EndpointParameter {
  name: string;
  type: 'string' | 'number' | 'boolean';
  required: boolean;
  placeholder: string;
  location: 'path' | 'query' | 'body';
}

// Libelles des groupes d'endpoints
const GROUP_LABELS: Record<EndpointGroup, string> = {
  invoices: 'Factures',
  quotes: 'Devis',
  clients: 'Clients',
  company: 'Entreprise',
  webhooks: 'Webhooks',
};

// Definition de tous les endpoints de l'API, regroupes par categorie
const ENDPOINTS: Record<EndpointGroup, Endpoint[]> = {
  invoices: [
    {
      method: 'GET',
      path: '/api/invoices',
      description: 'Recupere la liste paginee des factures de l\'entreprise. Supporte les filtres par statut, date et client.',
      requestExample: null,
      responseExample: JSON.stringify({
        'hydra:member': [
          {
            '@id': '/api/invoices/a1b2c3d4',
            invoiceNumber: 'FA-2026-0042',
            status: 'sent',
            totalHt: '1500.00',
            totalTtc: '1800.00',
            issuedAt: '2026-04-01T10:00:00+02:00',
            client: '/api/clients/e5f6g7h8',
          },
        ],
        'hydra:totalItems': 42,
      }, null, 2),
      parameters: [
        { name: 'status', type: 'string', required: false, placeholder: 'draft, sent, paid...', location: 'query' },
        { name: 'page', type: 'number', required: false, placeholder: '1', location: 'query' },
      ],
    },
    {
      method: 'POST',
      path: '/api/invoices',
      description: 'Cree une nouvelle facture en statut brouillon. Le numero sequentiel est genere automatiquement.',
      requestExample: JSON.stringify({
        client: '/api/clients/e5f6g7h8',
        issuedAt: '2026-04-08',
        dueAt: '2026-05-08',
        lines: [
          {
            description: 'Developpement application web',
            quantity: 10,
            unitPriceHt: '650.00',
            vatRate: '20.00',
          },
        ],
      }, null, 2),
      responseExample: JSON.stringify({
        '@id': '/api/invoices/new-uuid',
        invoiceNumber: 'FA-2026-0043',
        status: 'draft',
        totalHt: '6500.00',
        totalTtc: '7800.00',
        issuedAt: '2026-04-08T00:00:00+02:00',
        dueAt: '2026-05-08T00:00:00+02:00',
      }, null, 2),
      parameters: [
        { name: 'client', type: 'string', required: true, placeholder: '/api/clients/{id}', location: 'body' },
        { name: 'issuedAt', type: 'string', required: true, placeholder: '2026-04-08', location: 'body' },
        { name: 'dueAt', type: 'string', required: true, placeholder: '2026-05-08', location: 'body' },
      ],
    },
    {
      method: 'POST',
      path: '/api/invoices/{id}/send',
      description: 'Envoie la facture au client par email et via la PDP. La facture passe du statut brouillon a envoyee.',
      requestExample: JSON.stringify({
        sendEmail: true,
        emailMessage: 'Veuillez trouver ci-joint votre facture.',
      }, null, 2),
      responseExample: JSON.stringify({
        '@id': '/api/invoices/a1b2c3d4',
        status: 'sent',
        sentAt: '2026-04-08T14:30:00+02:00',
      }, null, 2),
      parameters: [
        { name: 'id', type: 'string', required: true, placeholder: 'a1b2c3d4', location: 'path' },
        { name: 'sendEmail', type: 'boolean', required: false, placeholder: 'true', location: 'body' },
      ],
    },
  ],
  quotes: [
    {
      method: 'GET',
      path: '/api/quotes',
      description: 'Recupere la liste paginee des devis. Filtrable par statut (brouillon, envoye, accepte, refuse).',
      requestExample: null,
      responseExample: JSON.stringify({
        'hydra:member': [
          {
            '@id': '/api/quotes/q1r2s3t4',
            quoteNumber: 'DE-2026-0015',
            status: 'accepted',
            totalHt: '3200.00',
            totalTtc: '3840.00',
            validUntil: '2026-05-01',
            client: '/api/clients/e5f6g7h8',
          },
        ],
        'hydra:totalItems': 15,
      }, null, 2),
      parameters: [
        { name: 'status', type: 'string', required: false, placeholder: 'draft, sent, accepted, refused', location: 'query' },
        { name: 'page', type: 'number', required: false, placeholder: '1', location: 'query' },
      ],
    },
    {
      method: 'POST',
      path: '/api/quotes/{id}/convert',
      description: 'Convertit un devis accepte en facture brouillon. Les lignes et le client sont automatiquement copies.',
      requestExample: null,
      responseExample: JSON.stringify({
        '@id': '/api/invoices/new-uuid',
        invoiceNumber: 'FA-2026-0044',
        status: 'draft',
        totalHt: '3200.00',
        totalTtc: '3840.00',
        sourceQuote: '/api/quotes/q1r2s3t4',
      }, null, 2),
      parameters: [
        { name: 'id', type: 'string', required: true, placeholder: 'q1r2s3t4', location: 'path' },
      ],
    },
  ],
  clients: [
    {
      method: 'GET',
      path: '/api/clients',
      description: 'Recupere la liste des clients de l\'entreprise avec pagination et recherche par nom ou SIREN.',
      requestExample: null,
      responseExample: JSON.stringify({
        'hydra:member': [
          {
            '@id': '/api/clients/e5f6g7h8',
            name: 'Acme SAS',
            siren: '443061841',
            email: 'comptabilite@acme.fr',
            address: '12 rue de la Paix, 75002 Paris',
          },
        ],
        'hydra:totalItems': 8,
      }, null, 2),
      parameters: [
        { name: 'search', type: 'string', required: false, placeholder: 'Acme', location: 'query' },
        { name: 'page', type: 'number', required: false, placeholder: '1', location: 'query' },
      ],
    },
    {
      method: 'POST',
      path: '/api/clients',
      description: 'Cree un nouveau client. Le SIREN est valide automatiquement (algorithme de Luhn).',
      requestExample: JSON.stringify({
        name: 'Dupont Consulting SARL',
        siren: '802954785',
        email: 'contact@dupont-consulting.fr',
        address: '45 avenue des Champs-Elysees, 75008 Paris',
        vatNumber: 'FR32802954785',
      }, null, 2),
      responseExample: JSON.stringify({
        '@id': '/api/clients/new-uuid',
        name: 'Dupont Consulting SARL',
        siren: '802954785',
        email: 'contact@dupont-consulting.fr',
        address: '45 avenue des Champs-Elysees, 75008 Paris',
        vatNumber: 'FR32802954785',
      }, null, 2),
      parameters: [
        { name: 'name', type: 'string', required: true, placeholder: 'Dupont Consulting SARL', location: 'body' },
        { name: 'siren', type: 'string', required: true, placeholder: '802954785', location: 'body' },
        { name: 'email', type: 'string', required: true, placeholder: 'contact@dupont.fr', location: 'body' },
      ],
    },
  ],
  company: [
    {
      method: 'GET',
      path: '/api/companies/me',
      description: 'Recupere les informations de l\'entreprise de l\'utilisateur connecte : raison sociale, SIREN, adresse, parametres de facturation.',
      requestExample: null,
      responseExample: JSON.stringify({
        '@id': '/api/companies/c1d2e3f4',
        name: 'Pierre-Arthur Demengel EI',
        siren: '123456789',
        siret: '12345678900012',
        address: '10 rue de Rivoli, 75001 Paris',
        vatNumber: 'FR00123456789',
        invoicePrefix: 'FA',
        defaultVatRate: '20.00',
        defaultPaymentTermsDays: 30,
      }, null, 2),
      parameters: [],
    },
  ],
  webhooks: [
    {
      method: 'POST',
      path: '/api/webhooks',
      description: 'Enregistre un nouveau webhook. Factura enverra une requete POST a l\'URL specifiee pour chaque evenement souscrit.',
      requestExample: JSON.stringify({
        url: 'https://example.com/webhooks/factura',
        events: ['invoice.created', 'invoice.paid', 'payment.received'],
        secret: 'whsec_votre_secret_hmac',
      }, null, 2),
      responseExample: JSON.stringify({
        '@id': '/api/webhooks/w1x2y3z4',
        url: 'https://example.com/webhooks/factura',
        events: ['invoice.created', 'invoice.paid', 'payment.received'],
        active: true,
        createdAt: '2026-04-08T10:00:00+02:00',
      }, null, 2),
      parameters: [
        { name: 'url', type: 'string', required: true, placeholder: 'https://example.com/webhook', location: 'body' },
        { name: 'events', type: 'string', required: true, placeholder: 'invoice.created, invoice.paid', location: 'body' },
        { name: 'secret', type: 'string', required: false, placeholder: 'whsec_...', location: 'body' },
      ],
    },
  ],
};

// Applique une coloration syntaxique basique sur du JSON
function highlightJson(json: string): React.ReactNode[] {
  const lines = json.split('\n');
  return lines.map((line, lineIndex) => {
    const parts: React.ReactNode[] = [];
    let remaining = line;
    let partIndex = 0;

    // Colore les cles JSON (texte entre guillemets suivi de ":")
    while (remaining.length > 0) {
      const keyMatch = remaining.match(/^(\s*)"([^"]+)"(\s*:\s*)/);
      if (keyMatch) {
        parts.push(<span key={partIndex++}>{keyMatch[1]}</span>);
        parts.push(<span key={partIndex++} className="api-json-key">"{keyMatch[2]}"</span>);
        parts.push(<span key={partIndex++}>{keyMatch[3]}</span>);
        remaining = remaining.slice(keyMatch[0].length);
        continue;
      }

      // Colore les valeurs chaunes de caracteres
      const stringMatch = remaining.match(/^"([^"]*)"/);
      if (stringMatch) {
        parts.push(<span key={partIndex++} className="api-json-string">"{stringMatch[1]}"</span>);
        remaining = remaining.slice(stringMatch[0].length);
        continue;
      }

      // Colore les nombres
      const numberMatch = remaining.match(/^(-?\d+\.?\d*)/);
      if (numberMatch) {
        parts.push(<span key={partIndex++} className="api-json-number">{numberMatch[1]}</span>);
        remaining = remaining.slice(numberMatch[0].length);
        continue;
      }

      // Colore les booleens et null
      const boolMatch = remaining.match(/^(true|false|null)/);
      if (boolMatch) {
        parts.push(<span key={partIndex++} className="api-json-bool">{boolMatch[1]}</span>);
        remaining = remaining.slice(boolMatch[0].length);
        continue;
      }

      // Caractere suivant (accolades, crochets, virgules, espaces)
      parts.push(<span key={partIndex++}>{remaining[0]}</span>);
      remaining = remaining.slice(1);
    }

    return (
      <span key={lineIndex}>
        {parts}
        {lineIndex < lines.length - 1 ? '\n' : ''}
      </span>
    );
  });
}

// Bloc de code avec coloration syntaxique et bouton "Copier"
function CodeBlock({ code, label }: { code: string; label: string }) {
  const [copied, setCopied] = useState(false);

  // Copie le contenu dans le presse-papier avec retour visuel
  const handleCopy = useCallback(async () => {
    try {
      await navigator.clipboard.writeText(code);
      setCopied(true);
      setTimeout(() => setCopied(false), 2000);
    } catch {
      // Fallback silencieux si le presse-papier n'est pas disponible
    }
  }, [code]);

  return (
    <div className="api-code-block-wrapper">
      <div className="api-code-block-header">
        <span className="api-code-block-label">
          {label}
        </span>
        <button
          onClick={handleCopy}
          className={`api-copy-btn ${copied ? 'api-copy-btn--copied' : ''}`}
        >
          {copied ? 'Copie !' : 'Copier'}
        </button>
      </div>
      <pre className="api-code-block">
        <code>{highlightJson(code)}</code>
      </pre>
    </div>
  );
}

// Badge affichant la methode HTTP avec la couleur correspondante
function MethodBadge({ method }: { method: HttpMethod }) {
  return (
    <span className={`app-status-pill api-method-badge api-method-badge--${method.toLowerCase()}`}>
      {method}
    </span>
  );
}

// Carte detaillee d'un endpoint avec exemples et section "Essayer"
function EndpointCard({ endpoint }: { endpoint: Endpoint }) {
  const [expanded, setExpanded] = useState(false);
  const [tryValues, setTryValues] = useState<Record<string, string>>({});

  // Met a jour la valeur d'un parametre dans la section "Essayer"
  const handleParamChange = (name: string, value: string) => {
    setTryValues((prev) => ({ ...prev, [name]: value }));
  };

  // Envoie les parametres dans la console (demonstration uniquement)
  const handleExecute = () => {
    const payload = {
      method: endpoint.method,
      path: endpoint.path,
      parameters: tryValues,
    };
    console.log('[Factura API - Essai]', payload);
  };

  return (
    <div className="app-card api-endpoint-card">
      {/* En-tete cliquable : methode + chemin + description courte */}
      <button
        onClick={() => setExpanded(!expanded)}
        className="api-endpoint-header"
      >
        <MethodBadge method={endpoint.method} />
        <code className="api-endpoint-path">
          {endpoint.path}
        </code>
        <span className="api-endpoint-summary">
          {endpoint.description.length > 80
            ? endpoint.description.slice(0, 80) + '...'
            : endpoint.description}
        </span>
        <span className={`api-endpoint-chevron ${expanded ? 'api-endpoint-chevron--open' : ''}`}>
          &#9662;
        </span>
      </button>

      {/* Contenu detaille, visible uniquement si l'endpoint est deplie */}
      {expanded && (
        <div className="api-endpoint-body">
          {/* Description complete */}
          <p className="app-desc">
            {endpoint.description}
          </p>

          {/* Exemple de requete (si applicable) */}
          {endpoint.requestExample && (
            <CodeBlock code={endpoint.requestExample} label="Requete" />
          )}

          {/* Exemple de reponse */}
          <CodeBlock code={endpoint.responseExample} label="Reponse" />

          {/* Section "Essayer" : formulaire de parametres */}
          {endpoint.parameters.length > 0 && (
            <div className="api-try-section">
              <h4 className="api-try-title">
                Essayer
              </h4>

              <div className="api-try-params-grid">
                {endpoint.parameters.map((param) => (
                  <div key={param.name} className="app-form-group" style={{ marginBottom: 0 }}>
                    <label className="app-label">
                      {param.name}
                      {param.required && (
                        <span className="api-param-required">*</span>
                      )}
                      <span className="api-param-location">
                        ({param.location})
                      </span>
                    </label>
                    <input
                      className="app-input"
                      placeholder={param.placeholder}
                      value={tryValues[param.name] || ''}
                      onChange={(e) => handleParamChange(param.name, e.target.value)}
                    />
                  </div>
                ))}
              </div>

              <button
                className="app-btn-primary"
                onClick={handleExecute}
              >
                Executer
              </button>
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// Page principale de documentation interactive de l'API REST Factura
export default function ApiDocs() {
  const [activeGroup, setActiveGroup] = useState<EndpointGroup>('invoices');

  // Groupes de navigation
  const groups: { key: EndpointGroup; label: string; count: number }[] = (
    Object.keys(GROUP_LABELS) as EndpointGroup[]
  ).map((key) => ({
    key,
    label: GROUP_LABELS[key],
    count: ENDPOINTS[key].length,
  }));

  return (
    <div className="app-container">
      {/* En-tete avec titre et badge de version */}
      <div className="api-header-row">
        <h1 className="app-page-title" style={{ marginBottom: 0 }}>
          Documentation API
        </h1>
        <span className="api-version-badge">
          v1.0
        </span>
      </div>

      <p className="app-desc">
        Reference complete de l'API REST Factura. Toutes les requetes necessitent
        un token JWT Bearer valide sauf indication contraire.
      </p>

      {/* Section authentification */}
      <div className="app-card api-section-card">
        <h2 className="app-section-title" style={{ marginTop: 0 }}>
          Authentification
        </h2>
        <p className="app-desc" style={{ margin: '0 0 1rem' }}>
          Chaque requete doit inclure un en-tete <code className="api-inline-code">Authorization</code> contenant votre token JWT.
          Obtenez un token via l'endpoint <code className="api-inline-code">POST /api/login</code> avec vos identifiants,
          ou utilisez une cle API generee depuis les parametres.
        </p>
        <pre className="api-code-block">
          <code>
            <span className="api-json-key">Authorization</span>
            <span>: </span>
            <span className="api-json-string">Bearer eyJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3...</span>
          </code>
        </pre>
      </div>

      {/* Section limites de debit */}
      <div className="app-card api-section-card api-section-card--large">
        <h2 className="app-section-title" style={{ marginTop: 0 }}>
          Limites de debit (Rate Limiting)
        </h2>
        <p className="app-desc" style={{ marginBottom: 0 }}>
          L'API applique des limites de debit pour garantir la stabilite du service.
        </p>
        <div className="api-rate-limit-grid">
          {[
            { label: 'Requetes / minute', value: '60' },
            { label: 'Requetes / heure', value: '1 000' },
            { label: 'Requetes / jour', value: '10 000' },
          ].map((limit) => (
            <div key={limit.label} className="api-rate-limit-card">
              <div className="api-rate-limit-value">
                {limit.value}
              </div>
              <div className="api-rate-limit-label">
                {limit.label}
              </div>
            </div>
          ))}
        </div>
        <p className="app-desc" style={{ marginTop: '1rem', marginBottom: 0 }}>
          Les en-tetes <code className="api-inline-code">X-RateLimit-Limit</code>, <code className="api-inline-code">X-RateLimit-Remaining</code> et <code className="api-inline-code">X-RateLimit-Reset</code> sont inclus dans chaque reponse.
          En cas de depassement, l'API retourne un statut <strong>429 Too Many Requests</strong>.
        </p>
      </div>

      {/* Navigation par onglets + contenu des endpoints */}
      <h2 className="app-section-title" style={{ marginTop: 0 }}>Endpoints</h2>

      {/* Onglets de navigation des groupes */}
      <div className="app-tabs">
        {groups.map((group) => (
          <button
            key={group.key}
            onClick={() => setActiveGroup(group.key)}
            className={`app-tab ${activeGroup === group.key ? 'app-tab--active' : ''}`}
          >
            {group.label}
            <span className={`api-tab-count ${activeGroup === group.key ? 'api-tab-count--active' : 'api-tab-count--inactive'}`}>
              {group.count}
            </span>
          </button>
        ))}
      </div>

      {/* Liste des endpoints du groupe actif */}
      <div className="app-list">
        {ENDPOINTS[activeGroup].map((endpoint, index) => (
          <EndpointCard key={`${endpoint.method}-${endpoint.path}-${index}`} endpoint={endpoint} />
        ))}
      </div>

      {/* Note de bas de page sur le format de l'API */}
      <div className="api-footer-note">
        <strong>Format des reponses</strong>
        <br />
        L'API suit la specification JSON-LD / Hydra (API Platform).
        Toutes les reponses incluent les proprietes <code className="api-inline-code">@id</code> et <code className="api-inline-code">@type</code> conformement au standard.
        Les collections sont paginees avec 30 elements par page par defaut.
      </div>
    </div>
  );
}
