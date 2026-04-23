import { useState } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import './AppLayout.css';

// Onglets disponibles dans les parametres API
type ApiTab = 'keys' | 'webhooks' | 'connectors';

// Types pour les cles API
interface ApiKey {
  id: string;
  name: string;
  keyPreview: string;
  scopes: string[];
  createdAt: string;
  lastUsedAt: string | null;
}

// Types pour les webhooks
interface Webhook {
  id: string;
  url: string;
  events: string[];
  secret: string;
  active: boolean;
  lastDeliveryStatus: 'success' | 'failure' | null;
  lastDeliveryAt: string | null;
}

// Types pour les connecteurs
interface Connector {
  id: string;
  name: string;
  description: string;
  available: boolean;
}

// Portees disponibles pour les cles API
const AVAILABLE_SCOPES = [
  { value: 'invoices:read', label: 'Factures (lecture)' },
  { value: 'invoices:write', label: 'Factures (ecriture)' },
  { value: 'clients:read', label: 'Clients (lecture)' },
  { value: 'clients:write', label: 'Clients (ecriture)' },
  { value: 'accounting:read', label: 'Comptabilite (lecture)' },
];

// Evenements disponibles pour les webhooks
const AVAILABLE_EVENTS = [
  { value: 'invoice.created', label: 'Facture creee' },
  { value: 'invoice.sent', label: 'Facture envoyee' },
  { value: 'invoice.paid', label: 'Facture payee' },
  { value: 'payment.received', label: 'Paiement recu' },
  { value: 'client.created', label: 'Client cree' },
];

// Connecteurs disponibles
const CONNECTORS: Connector[] = [
  { id: 'zapier', name: 'Zapier', description: 'Automatisation avec 5000+ applications. Declencheurs et actions sur les factures, clients et paiements.', available: true },
  { id: 'make', name: 'Make', description: 'Scenarios d\'automatisation avances avec logique conditionnelle et transformations de donnees.', available: true },
  { id: 'pennylane', name: 'Pennylane', description: 'Export automatique des ecritures comptables et synchronisation bidirectionnelle des factures.', available: true },
  { id: 'sage', name: 'Sage', description: 'Integration avec Sage 50, Sage 100 et Sage Business Cloud pour la comptabilite et la gestion commerciale.', available: false },
  { id: 'cegid', name: 'Cegid', description: 'Connexion a Cegid Loop et Cegid XRP pour les cabinets comptables et PME.', available: false },
  { id: 'acd', name: 'ACD', description: 'Transfert automatique des ecritures vers ACD (groupe Cegid) pour les experts-comptables.', available: false },
];

export default function ApiSettings() {
  const [activeTab, setActiveTab] = useState<ApiTab>('keys');
  const { success, error } = useToast();

  // Etat des cles API
  const [apiKeys, setApiKeys] = useState<ApiKey[]>([]);
  const [showKeyForm, setShowKeyForm] = useState(false);
  const [newKeyName, setNewKeyName] = useState('');
  const [newKeyScopes, setNewKeyScopes] = useState<string[]>([]);
  const [generatedKey, setGeneratedKey] = useState<string | null>(null);
  const [loadingKey, setLoadingKey] = useState(false);

  // Etat des webhooks
  const [webhooks, setWebhooks] = useState<Webhook[]>([]);
  const [showWebhookForm, setShowWebhookForm] = useState(false);
  const [webhookUrl, setWebhookUrl] = useState('');
  const [webhookEvents, setWebhookEvents] = useState<string[]>([]);
  const [webhookSecret, setWebhookSecret] = useState('');
  const [loadingWebhook, setLoadingWebhook] = useState(false);

  // Bascule d'une portee dans la selection
  const toggleScope = (scope: string) => {
    setNewKeyScopes((prev) =>
      prev.includes(scope) ? prev.filter((s) => s !== scope) : [...prev, scope]
    );
  };

  // Bascule d'un evenement dans la selection webhook
  const toggleEvent = (event: string) => {
    setWebhookEvents((prev) =>
      prev.includes(event) ? prev.filter((e) => e !== event) : [...prev, event]
    );
  };

  // Genere une nouvelle cle API via le backend
  const handleGenerateKey = async () => {
    if (!newKeyName.trim()) {
      error('Veuillez saisir un nom pour la cle.');
      return;
    }
    if (newKeyScopes.length === 0) {
      error('Veuillez selectionner au moins une portee.');
      return;
    }

    setLoadingKey(true);
    try {
      const res = await api.post('/api-keys', {
        name: newKeyName.trim(),
        scopes: newKeyScopes,
      });
      const data = res.data;

      // La cle complete n'est visible qu'a la creation
      setGeneratedKey(data.plainKey || data.key);

      // Ajoute la cle a la liste locale
      setApiKeys((prev) => [
        ...prev,
        {
          id: data.id,
          name: data.name,
          keyPreview: data.keyPreview || '****',
          scopes: data.scopes,
          createdAt: data.createdAt || new Date().toISOString(),
          lastUsedAt: null,
        },
      ]);

      success('Cle API generee avec succes.');
    } catch {
      error('Erreur lors de la generation de la cle API.');
    } finally {
      setLoadingKey(false);
    }
  };

  // Copie la cle generee dans le presse-papier
  const handleCopyKey = async (key: string) => {
    try {
      await navigator.clipboard.writeText(key);
      success('Cle copiee dans le presse-papier.');
    } catch {
      error('Impossible de copier la cle.');
    }
  };

  // Ferme le formulaire et reinitialise les champs
  const resetKeyForm = () => {
    setShowKeyForm(false);
    setNewKeyName('');
    setNewKeyScopes([]);
    setGeneratedKey(null);
  };

  // Revoque une cle API
  const handleRevokeKey = async (keyId: string) => {
    try {
      await api.delete(`/api-keys/${keyId}`);
      setApiKeys((prev) => prev.filter((k) => k.id !== keyId));
      success('Cle API revoquee.');
    } catch {
      error('Erreur lors de la revocation de la cle.');
    }
  };

  // Cree un nouveau webhook
  const handleCreateWebhook = async () => {
    if (!webhookUrl.trim()) {
      error('Veuillez saisir une URL.');
      return;
    }
    if (webhookEvents.length === 0) {
      error('Veuillez selectionner au moins un evenement.');
      return;
    }

    setLoadingWebhook(true);
    try {
      const res = await api.post('/webhooks', {
        url: webhookUrl.trim(),
        events: webhookEvents,
        secret: webhookSecret.trim() || undefined,
      });
      const data = res.data;

      setWebhooks((prev) => [
        ...prev,
        {
          id: data.id,
          url: data.url,
          events: data.events,
          secret: data.secret || '',
          active: true,
          lastDeliveryStatus: null,
          lastDeliveryAt: null,
        },
      ]);

      // Reinitialise le formulaire
      setShowWebhookForm(false);
      setWebhookUrl('');
      setWebhookEvents([]);
      setWebhookSecret('');
      success('Webhook cree avec succes.');
    } catch {
      error('Erreur lors de la creation du webhook.');
    } finally {
      setLoadingWebhook(false);
    }
  };

  // Bascule l'etat actif/pause d'un webhook
  const handleToggleWebhook = async (webhookId: string) => {
    const webhook = webhooks.find((w) => w.id === webhookId);
    if (!webhook) return;

    try {
      await api.put(`/webhooks/${webhookId}`, { active: !webhook.active });
      setWebhooks((prev) =>
        prev.map((w) => (w.id === webhookId ? { ...w, active: !w.active } : w))
      );
      success(webhook.active ? 'Webhook mis en pause.' : 'Webhook reactive.');
    } catch {
      error('Erreur lors de la mise a jour du webhook.');
    }
  };

  // Supprime un webhook
  const handleDeleteWebhook = async (webhookId: string) => {
    try {
      await api.delete(`/webhooks/${webhookId}`);
      setWebhooks((prev) => prev.filter((w) => w.id !== webhookId));
      success('Webhook supprime.');
    } catch {
      error('Erreur lors de la suppression du webhook.');
    }
  };

  // Envoie un evenement de test au webhook
  const handleTestWebhook = async (webhookId: string) => {
    try {
      await api.post(`/webhooks/${webhookId}/test`);
      success('Evenement de test envoye.');
    } catch {
      error('Erreur lors de l\'envoi du test.');
    }
  };

  // Onglets de navigation
  const tabs: { key: ApiTab; label: string }[] = [
    { key: 'keys', label: 'Cles API' },
    { key: 'webhooks', label: 'Webhooks' },
    { key: 'connectors', label: 'Connecteurs' },
  ];

  return (
    <div className="app-container">
      <h1 className="app-page-title">API & Integrations</h1>

      {/* Onglets */}
      <div className="app-tabs">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`app-tab ${activeTab === tab.key ? 'app-tab--active' : ''}`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Onglet Cles API */}
      {activeTab === 'keys' && (
        <div>
          <div className="app-page-header">
            <div>
              <h2 className="app-section-title app-mt-0">Cles API</h2>
              <p className="app-desc" style={{ marginBottom: 0 }}>
                Gerez vos cles d'acces a l'API REST Factura.
              </p>
            </div>
            {!showKeyForm && (
              <button className="app-btn-primary" onClick={() => setShowKeyForm(true)}>
                Generer une cle
              </button>
            )}
          </div>

          {/* Formulaire de generation */}
          {showKeyForm && (
            <div className="app-card" style={{ marginBottom: '1.5rem' }}>
              {generatedKey ? (
                // Affichage de la cle generee (une seule fois)
                <div>
                  <h3 className="app-subsection-title app-mt-0">
                    Cle generee
                  </h3>
                  <div className="app-alert app-alert--warning">
                    Copiez cette cle maintenant. Elle ne sera plus visible apres fermeture.
                  </div>
                  <div className="app-integration-card">
                    <code className="app-list-item-info" style={{ wordBreak: 'break-all' }}>
                      {generatedKey}
                    </code>
                    <button
                      onClick={() => handleCopyKey(generatedKey)}
                      className="app-btn-outline app-btn-compact"
                    >
                      Copier
                    </button>
                  </div>
                  <div className="app-actions-row">
                    <button
                      onClick={resetKeyForm}
                      className="app-btn-primary"
                    >
                      Fermer
                    </button>
                  </div>
                </div>
              ) : (
                // Formulaire de creation
                <div>
                  <h3 className="app-subsection-title app-mt-0">
                    Nouvelle cle API
                  </h3>

                  <div className="app-form-group">
                    <label htmlFor="api-key-name" className="app-label">Nom de la cle</label>
                    <input
                      id="api-key-name"
                      value={newKeyName}
                      onChange={(e) => setNewKeyName(e.target.value)}
                      className="app-input"
                      placeholder="Ex : Integration comptable, Application mobile"
                      maxLength={100}
                    />
                  </div>

                  <div className="app-form-group">
                    <span className="app-label">Portees (scopes)</span>
                    <div className="app-list">
                      {AVAILABLE_SCOPES.map((scope) => (
                        <label
                          key={scope.value}
                          className="app-toggle-row"
                          style={{ cursor: 'pointer' }}
                        >
                          <input
                            type="checkbox"
                            checked={newKeyScopes.includes(scope.value)}
                            onChange={() => toggleScope(scope.value)}
                            style={{ accentColor: 'var(--accent)' }}
                          />
                          <span className="app-toggle-label">{scope.label}</span>
                          <code className="app-list-item-sub">
                            {scope.value}
                          </code>
                        </label>
                      ))}
                    </div>
                  </div>

                  <div className="app-actions-row" style={{ marginTop: '1rem' }}>
                    <button
                      onClick={handleGenerateKey}
                      disabled={loadingKey}
                      className="app-btn-primary"
                    >
                      {loadingKey ? 'Generation...' : 'Generer'}
                    </button>
                    <button
                      onClick={resetKeyForm}
                      className="app-btn-outline"
                    >
                      Annuler
                    </button>
                  </div>
                </div>
              )}
            </div>
          )}

          {/* Liste des cles */}
          {apiKeys.length === 0 && !showKeyForm ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucune cle API</p>
              <p className="app-empty-desc">
                Generez votre premiere cle pour integrer Factura a vos outils.
              </p>
            </div>
          ) : (
            <div className="app-table-wrapper">
              <table className="app-table">
                <thead>
                  <tr>
                    <th>Nom</th>
                    <th>Cle</th>
                    <th>Portees</th>
                    <th>Creee le</th>
                    <th>Derniere utilisation</th>
                    <th className="text-right"></th>
                  </tr>
                </thead>
                <tbody>
                  {apiKeys.map((key) => (
                    <tr key={key.id}>
                      <td>
                        {key.name}
                      </td>
                      <td>
                        <code className="app-badge app-badge-draft">
                          {key.keyPreview}
                        </code>
                      </td>
                      <td>
                        <div className="app-pills">
                          {key.scopes.map((scope) => (
                            <span
                              key={scope}
                              className="app-pill app-pill--active"
                              style={{ cursor: 'default' }}
                            >
                              {scope}
                            </span>
                          ))}
                        </div>
                      </td>
                      <td>
                        {new Date(key.createdAt).toLocaleDateString('fr-FR')}
                      </td>
                      <td>
                        {key.lastUsedAt ? new Date(key.lastUsedAt).toLocaleDateString('fr-FR') : 'Jamais'}
                      </td>
                      <td className="text-right">
                        <button
                          onClick={() => handleRevokeKey(key.id)}
                          className="app-btn-outline-danger app-btn-compact"
                        >
                          Revoquer
                        </button>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Onglet Webhooks */}
      {activeTab === 'webhooks' && (
        <div>
          <div className="app-page-header">
            <div>
              <h2 className="app-section-title app-mt-0">Webhooks</h2>
              <p className="app-desc" style={{ marginBottom: 0 }}>
                Recevez des notifications en temps reel sur vos evenements Factura.
              </p>
            </div>
            {!showWebhookForm && (
              <button className="app-btn-primary" onClick={() => setShowWebhookForm(true)}>
                Ajouter un webhook
              </button>
            )}
          </div>

          {/* Formulaire d'ajout de webhook */}
          {showWebhookForm && (
            <div className="app-card" style={{ marginBottom: '1.5rem' }}>
              <h3 className="app-subsection-title app-mt-0">
                Nouveau webhook
              </h3>

              <div className="app-form-group">
                <label htmlFor="webhook-url" className="app-label">URL de l'endpoint</label>
                <input
                  id="webhook-url"
                  value={webhookUrl}
                  onChange={(e) => setWebhookUrl(e.target.value)}
                  className="app-input"
                  placeholder="https://example.com/webhooks/factura"
                  type="url"
                />
              </div>

              <div className="app-form-group">
                <span className="app-label">Evenements</span>
                <div className="app-list">
                  {AVAILABLE_EVENTS.map((event) => (
                    <label
                      key={event.value}
                      className="app-toggle-row"
                      style={{ cursor: 'pointer' }}
                    >
                      <input
                        type="checkbox"
                        checked={webhookEvents.includes(event.value)}
                        onChange={() => toggleEvent(event.value)}
                        style={{ accentColor: 'var(--accent)' }}
                      />
                      <span className="app-toggle-label">{event.label}</span>
                      <code className="app-list-item-sub">
                        {event.value}
                      </code>
                    </label>
                  ))}
                </div>
              </div>

              <div className="app-form-group">
                <label htmlFor="webhook-secret" className="app-label">Secret de signature (optionnel)</label>
                <input
                  id="webhook-secret"
                  value={webhookSecret}
                  onChange={(e) => setWebhookSecret(e.target.value)}
                  className="app-input"
                  placeholder="Utilise pour verifier la signature HMAC-SHA256 des requetes"
                  type="text"
                />
                <span className="app-reminder-hint">
                  Si defini, chaque requete inclura un en-tete X-Factura-Signature.
                </span>
              </div>

              <div className="app-actions-row" style={{ marginTop: '0.5rem' }}>
                <button
                  onClick={handleCreateWebhook}
                  disabled={loadingWebhook}
                  className="app-btn-primary"
                >
                  {loadingWebhook ? 'Creation...' : 'Creer le webhook'}
                </button>
                <button
                  onClick={() => {
                    setShowWebhookForm(false);
                    setWebhookUrl('');
                    setWebhookEvents([]);
                    setWebhookSecret('');
                  }}
                  className="app-btn-outline"
                >
                  Annuler
                </button>
              </div>
            </div>
          )}

          {/* Liste des webhooks */}
          {webhooks.length === 0 && !showWebhookForm ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucun webhook configure</p>
              <p className="app-empty-desc">
                Ajoutez un endpoint pour recevoir les evenements en temps reel.
              </p>
            </div>
          ) : (
            <div className="app-list">
              {webhooks.map((webhook) => (
                <div key={webhook.id} className="app-card">
                  <div className="app-client-card-header" style={{ marginBottom: '0.5rem' }}>
                    <div className="app-list-item-info">
                      {/* URL et statut */}
                      <div className="app-toggle-row" style={{ marginBottom: '0.5rem' }}>
                        <code className="app-list-item-title" style={{ wordBreak: 'break-all' }}>
                          {webhook.url}
                        </code>
                        <span className={`app-status-pill ${webhook.active ? 'app-status-pill--connected' : 'app-status-pill--muted'}`}>
                          {webhook.active ? 'Actif' : 'En pause'}
                        </span>
                      </div>

                      {/* Evenements */}
                      <div className="app-pills">
                        {webhook.events.map((event) => (
                          <span
                            key={event}
                            className="app-pill app-pill--active"
                            style={{ cursor: 'default' }}
                          >
                            {event}
                          </span>
                        ))}
                      </div>

                      {/* Derniere livraison */}
                      <div className="app-list-item-sub" style={{ marginTop: '0.5rem' }}>
                        {webhook.lastDeliveryAt ? (
                          <span>
                            Derniere livraison : {new Date(webhook.lastDeliveryAt).toLocaleString('fr-FR')}
                            {' — '}
                            <span className={`app-status-pill ${webhook.lastDeliveryStatus === 'success' ? 'app-status-pill--connected' : 'app-status-pill--closed'}`}>
                              {webhook.lastDeliveryStatus === 'success' ? 'Succes' : 'Echec'}
                            </span>
                          </span>
                        ) : (
                          <span>Aucune livraison</span>
                        )}
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="app-client-card-actions">
                      <button
                        onClick={() => handleTestWebhook(webhook.id)}
                        className="app-btn-outline app-btn-compact"
                      >
                        Tester
                      </button>
                      <button
                        onClick={() => handleToggleWebhook(webhook.id)}
                        className="app-btn-outline app-btn-compact"
                      >
                        {webhook.active ? 'Pause' : 'Activer'}
                      </button>
                      <button
                        onClick={() => handleDeleteWebhook(webhook.id)}
                        className="app-btn-outline-danger app-btn-compact"
                      >
                        Supprimer
                      </button>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Onglet Connecteurs */}
      {activeTab === 'connectors' && (
        <div>
          <h2 className="app-section-title app-mt-0">Connecteurs</h2>
          <p className="app-desc">
            Connectez Factura a vos outils comptables et d'automatisation preferes.
          </p>

          <div className="app-cards-grid">
            {CONNECTORS.map((connector) => (
              <div
                key={connector.id}
                className="app-card"
                style={{ opacity: connector.available ? 1 : 0.7 }}
              >
                {/* En-tete avec nom et badge */}
                <div className="app-client-card-header" style={{ marginBottom: '0.75rem' }}>
                  <div className="app-health-indicator" style={{
                    borderRadius: '10px',
                    background: connector.available ? 'var(--accent-bg)' : 'var(--surface)',
                    color: connector.available ? 'var(--accent)' : 'var(--text)',
                  }}>
                    {connector.name.charAt(0)}
                  </div>
                  <span className={`app-status-pill ${connector.available ? 'app-status-pill--connected' : 'app-status-pill--muted'}`}>
                    {connector.available ? 'Disponible' : 'Bientot'}
                  </span>
                </div>

                {/* Nom et description */}
                <div className="app-integration-card-name">
                  {connector.name}
                </div>
                <p className="app-integration-card-desc" style={{ flex: 1 }}>
                  {connector.description}
                </p>

                {/* Bouton d'action */}
                <button
                  disabled={!connector.available}
                  className={connector.available ? 'app-btn-primary' : 'app-btn-outline'}
                  style={{ marginTop: '1rem', width: '100%' }}
                  onClick={() => {
                    if (connector.available) {
                      success(`Configuration de ${connector.name} en cours...`);
                    }
                  }}
                >
                  {connector.available ? 'Configurer' : 'Bientot disponible'}
                </button>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
