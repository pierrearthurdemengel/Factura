import { useState, useCallback } from 'react';
import './AppLayout.css';

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

// Couleurs associees aux methodes HTTP
const METHOD_COLORS: Record<HttpMethod, string> = {
  GET: '#22c55e',
  POST: '#3b82f6',
  PUT: '#f59e0b',
  DELETE: '#ef4444',
};

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

// Styles partages pour les blocs de code
const codeBlockStyle: React.CSSProperties = {
  background: '#1e1e2e',
  color: '#cdd6f4',
  borderRadius: '8px',
  padding: '1rem',
  fontSize: '0.8rem',
  lineHeight: 1.6,
  overflowX: 'auto',
  margin: 0,
  fontFamily: '"Fira Code", "Consolas", "Monaco", monospace',
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
        parts.push(<span key={partIndex++} style={{ color: '#89b4fa' }}>"{keyMatch[2]}"</span>);
        parts.push(<span key={partIndex++}>{keyMatch[3]}</span>);
        remaining = remaining.slice(keyMatch[0].length);
        continue;
      }

      // Colore les valeurs chaunes de caracteres
      const stringMatch = remaining.match(/^"([^"]*)"/);
      if (stringMatch) {
        parts.push(<span key={partIndex++} style={{ color: '#a6e3a1' }}>"{stringMatch[1]}"</span>);
        remaining = remaining.slice(stringMatch[0].length);
        continue;
      }

      // Colore les nombres
      const numberMatch = remaining.match(/^(-?\d+\.?\d*)/);
      if (numberMatch) {
        parts.push(<span key={partIndex++} style={{ color: '#fab387' }}>{numberMatch[1]}</span>);
        remaining = remaining.slice(numberMatch[0].length);
        continue;
      }

      // Colore les booleens et null
      const boolMatch = remaining.match(/^(true|false|null)/);
      if (boolMatch) {
        parts.push(<span key={partIndex++} style={{ color: '#f38ba8' }}>{boolMatch[1]}</span>);
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
    <div style={{ marginBottom: '0.75rem' }}>
      <div style={{
        display: 'flex', justifyContent: 'space-between', alignItems: 'center',
        marginBottom: '0.25rem',
      }}>
        <span style={{ fontSize: '0.8rem', fontWeight: 600, color: 'var(--text)', textTransform: 'uppercase', letterSpacing: '0.05em' }}>
          {label}
        </span>
        <button
          onClick={handleCopy}
          style={{
            padding: '3px 10px', borderRadius: '4px', border: '1px solid var(--border)',
            background: copied ? 'rgba(34,197,94,0.1)' : 'var(--bg)',
            color: copied ? '#22c55e' : 'var(--text-h)',
            cursor: 'pointer', fontSize: '0.75rem', fontWeight: 500,
            transition: 'all 0.2s',
          }}
        >
          {copied ? 'Copie !' : 'Copier'}
        </button>
      </div>
      <pre style={codeBlockStyle}>
        <code>{highlightJson(code)}</code>
      </pre>
    </div>
  );
}

// Badge affichant la methode HTTP avec la couleur correspondante
function MethodBadge({ method }: { method: HttpMethod }) {
  return (
    <span style={{
      display: 'inline-block',
      padding: '2px 8px',
      borderRadius: '4px',
      fontSize: '0.75rem',
      fontWeight: 700,
      fontFamily: 'monospace',
      color: '#fff',
      background: METHOD_COLORS[method],
      minWidth: '3.2rem',
      textAlign: 'center',
    }}>
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
    <div
      className="app-card"
      style={{ padding: 0, overflow: 'hidden', marginBottom: '0.75rem' }}
    >
      {/* En-tete cliquable : methode + chemin + description courte */}
      <button
        onClick={() => setExpanded(!expanded)}
        style={{
          width: '100%', padding: '1rem 1.25rem',
          display: 'flex', alignItems: 'center', gap: '0.75rem',
          background: 'transparent', border: 'none', cursor: 'pointer',
          textAlign: 'left', color: 'var(--text-h)',
          flexWrap: 'wrap',
        }}
      >
        <MethodBadge method={endpoint.method} />
        <code style={{ fontSize: '0.9rem', fontWeight: 600, color: 'var(--text-h)', wordBreak: 'break-all' }}>
          {endpoint.path}
        </code>
        <span style={{
          fontSize: '0.85rem', color: 'var(--text)', flex: 1, minWidth: '150px',
        }}>
          {endpoint.description.length > 80
            ? endpoint.description.slice(0, 80) + '...'
            : endpoint.description}
        </span>
        <span style={{
          fontSize: '1.1rem', color: 'var(--text)',
          transform: expanded ? 'rotate(180deg)' : 'rotate(0)',
          transition: 'transform 0.2s',
          flexShrink: 0,
        }}>
          &#9662;
        </span>
      </button>

      {/* Contenu detaille, visible uniquement si l'endpoint est deplie */}
      {expanded && (
        <div style={{ padding: '0 1.25rem 1.25rem', borderTop: '1px solid var(--border)' }}>
          {/* Description complete */}
          <p style={{ fontSize: '0.9rem', color: 'var(--text)', lineHeight: 1.6, marginTop: '1rem' }}>
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
            <div style={{
              marginTop: '1rem', padding: '1rem',
              borderRadius: '8px', border: '1px solid var(--border)',
              background: 'var(--social-bg)',
            }}>
              <h4 style={{
                margin: '0 0 0.75rem', fontSize: '0.9rem', fontWeight: 600,
                color: 'var(--text-h)',
              }}>
                Essayer
              </h4>

              <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
                gap: '0.75rem',
                marginBottom: '1rem',
              }}>
                {endpoint.parameters.map((param) => (
                  <div key={param.name} className="app-form-group" style={{ marginBottom: 0 }}>
                    <label className="app-label" style={{ fontSize: '0.8rem' }}>
                      {param.name}
                      {param.required && (
                        <span style={{ color: '#ef4444', marginLeft: '0.25rem' }}>*</span>
                      )}
                      <span style={{
                        marginLeft: '0.5rem', fontSize: '0.7rem',
                        color: 'var(--text)', fontWeight: 400,
                      }}>
                        ({param.location})
                      </span>
                    </label>
                    <input
                      className="app-input"
                      style={{ fontSize: '0.85rem' }}
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
                style={{ fontSize: '0.85rem' }}
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
      <div style={{
        display: 'flex', alignItems: 'center', gap: '0.75rem',
        marginBottom: '0.5rem', flexWrap: 'wrap',
      }}>
        <h1 className="app-page-title" style={{ marginBottom: 0 }}>
          Documentation API
        </h1>
        <span style={{
          padding: '3px 10px', borderRadius: '1rem',
          fontSize: '0.75rem', fontWeight: 700,
          background: 'var(--accent)', color: '#fff',
        }}>
          v1.0
        </span>
      </div>

      <p style={{
        color: 'var(--text)', marginBottom: '1.5rem',
        maxWidth: 650, lineHeight: 1.6, fontSize: '0.95rem',
      }}>
        Reference complete de l'API REST Factura. Toutes les requetes necessitent
        un token JWT Bearer valide sauf indication contraire.
      </p>

      {/* Section authentification */}
      <div className="app-card" style={{ marginBottom: '1.5rem', padding: '1.25rem' }}>
        <h2 className="app-section-title" style={{ marginTop: 0, marginBottom: '0.75rem', fontSize: '1.1rem' }}>
          Authentification
        </h2>
        <p style={{ fontSize: '0.9rem', color: 'var(--text)', lineHeight: 1.6, margin: '0 0 1rem' }}>
          Chaque requete doit inclure un en-tete <code style={{
            background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
            borderRadius: '4px', fontSize: '0.8rem',
          }}>Authorization</code> contenant votre token JWT.
          Obtenez un token via l'endpoint <code style={{
            background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
            borderRadius: '4px', fontSize: '0.8rem',
          }}>POST /api/login</code> avec vos identifiants,
          ou utilisez une cle API generee depuis les parametres.
        </p>
        <pre style={{ ...codeBlockStyle, marginBottom: 0 }}>
          <code>
            <span style={{ color: '#89b4fa' }}>Authorization</span>
            <span>: </span>
            <span style={{ color: '#a6e3a1' }}>Bearer eyJhbGciOiJSUzI1NiJ9.eyJpYXQiOjE3...</span>
          </code>
        </pre>
      </div>

      {/* Section limites de debit */}
      <div className="app-card" style={{ marginBottom: '2rem', padding: '1.25rem' }}>
        <h2 className="app-section-title" style={{ marginTop: 0, marginBottom: '0.75rem', fontSize: '1.1rem' }}>
          Limites de debit (Rate Limiting)
        </h2>
        <p style={{ fontSize: '0.9rem', color: 'var(--text)', lineHeight: 1.6, margin: 0 }}>
          L'API applique des limites de debit pour garantir la stabilite du service.
        </p>
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fill, minmax(180px, 1fr))',
          gap: '0.75rem', marginTop: '1rem',
        }}>
          {[
            { label: 'Requetes / minute', value: '60' },
            { label: 'Requetes / heure', value: '1 000' },
            { label: 'Requetes / jour', value: '10 000' },
          ].map((limit) => (
            <div key={limit.label} style={{
              padding: '0.75rem', borderRadius: '6px',
              border: '1px solid var(--border)', textAlign: 'center',
            }}>
              <div style={{ fontSize: '1.25rem', fontWeight: 700, color: 'var(--text-h)' }}>
                {limit.value}
              </div>
              <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>
                {limit.label}
              </div>
            </div>
          ))}
        </div>
        <p style={{ fontSize: '0.85rem', color: 'var(--text)', lineHeight: 1.6, marginTop: '1rem', marginBottom: 0 }}>
          Les en-tetes <code style={{
            background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
            borderRadius: '4px', fontSize: '0.8rem',
          }}>X-RateLimit-Limit</code>, <code style={{
            background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
            borderRadius: '4px', fontSize: '0.8rem',
          }}>X-RateLimit-Remaining</code> et <code style={{
            background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
            borderRadius: '4px', fontSize: '0.8rem',
          }}>X-RateLimit-Reset</code> sont inclus dans chaque reponse.
          En cas de depassement, l'API retourne un statut <strong>429 Too Many Requests</strong>.
        </p>
      </div>

      {/* Navigation par onglets + contenu des endpoints */}
      <h2 className="app-section-title" style={{ marginTop: 0 }}>Endpoints</h2>

      {/* Onglets de navigation des groupes */}
      <div style={{
        display: 'flex', gap: '0.5rem', marginBottom: '1.5rem',
        borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem',
        overflowX: 'auto',
      }}>
        {groups.map((group) => (
          <button
            key={group.key}
            onClick={() => setActiveGroup(group.key)}
            style={{
              padding: '0.5rem 1rem', border: 'none',
              background: activeGroup === group.key ? 'var(--accent)' : 'transparent',
              color: activeGroup === group.key ? '#fff' : 'var(--text)',
              borderRadius: '6px', cursor: 'pointer',
              fontWeight: activeGroup === group.key ? 600 : 400,
              fontSize: '0.9rem', whiteSpace: 'nowrap',
              display: 'flex', alignItems: 'center', gap: '0.4rem',
              transition: 'all 0.15s',
            }}
          >
            {group.label}
            <span style={{
              background: activeGroup === group.key ? 'rgba(255,255,255,0.25)' : 'var(--social-bg)',
              padding: '1px 6px', borderRadius: '1rem',
              fontSize: '0.7rem', fontWeight: 600,
              color: activeGroup === group.key ? '#fff' : 'var(--text)',
            }}>
              {group.count}
            </span>
          </button>
        ))}
      </div>

      {/* Liste des endpoints du groupe actif */}
      <div>
        {ENDPOINTS[activeGroup].map((endpoint, index) => (
          <EndpointCard key={`${endpoint.method}-${endpoint.path}-${index}`} endpoint={endpoint} />
        ))}
      </div>

      {/* Note de bas de page sur le format de l'API */}
      <div style={{
        marginTop: '2rem', padding: '1rem 1.25rem',
        borderRadius: '8px', background: 'var(--social-bg)',
        border: '1px solid var(--border)',
        fontSize: '0.85rem', color: 'var(--text)', lineHeight: 1.6,
        maxWidth: 700,
      }}>
        <strong style={{ color: 'var(--text-h)' }}>Format des reponses</strong>
        <br />
        L'API suit la specification JSON-LD / Hydra (API Platform).
        Toutes les reponses incluent les proprietes <code style={{
          background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
          borderRadius: '4px', fontSize: '0.8rem',
        }}>@id</code> et <code style={{
          background: '#1e1e2e', color: '#cdd6f4', padding: '2px 6px',
          borderRadius: '4px', fontSize: '0.8rem',
        }}>@type</code> conformement au standard.
        Les collections sont paginees avec 30 elements par page par defaut.
      </div>
    </div>
  );
}
