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
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem', overflowX: 'auto' }}>
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            style={{
              padding: '0.5rem 1rem', border: 'none',
              background: activeTab === tab.key ? 'var(--accent)' : 'transparent',
              color: activeTab === tab.key ? '#fff' : 'var(--text)',
              borderRadius: '6px', cursor: 'pointer',
              fontWeight: activeTab === tab.key ? 600 : 400,
              fontSize: '0.9rem', whiteSpace: 'nowrap',
            }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Onglet Cles API */}
      {activeTab === 'keys' && (
        <div>
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '0.75rem' }}>
            <div>
              <h2 className="app-section-title" style={{ marginTop: 0, marginBottom: '0.25rem' }}>Cles API</h2>
              <p style={{ color: 'var(--text)', fontSize: '0.9rem', margin: 0 }}>
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
            <div className="app-card" style={{ marginBottom: '1.5rem', padding: '1.5rem' }}>
              {generatedKey ? (
                // Affichage de la cle generee (une seule fois)
                <div>
                  <h3 style={{ marginTop: 0, marginBottom: '0.75rem', color: 'var(--text-h)', fontSize: '1rem' }}>
                    Cle generee
                  </h3>
                  <p style={{ fontSize: '0.85rem', color: '#f59e0b', marginBottom: '1rem', lineHeight: 1.5 }}>
                    Copiez cette cle maintenant. Elle ne sera plus visible apres fermeture.
                  </p>
                  <div style={{
                    display: 'flex', alignItems: 'center', gap: '0.5rem',
                    background: 'var(--surface)', padding: '0.75rem 1rem',
                    borderRadius: '6px', border: '1px solid var(--border)',
                    flexWrap: 'wrap',
                  }}>
                    <code style={{ flex: 1, fontSize: '0.85rem', color: 'var(--text-h)', wordBreak: 'break-all' }}>
                      {generatedKey}
                    </code>
                    <button
                      onClick={() => handleCopyKey(generatedKey)}
                      style={{
                        padding: '4px 12px', borderRadius: '6px', border: '1px solid var(--border)',
                        background: 'var(--bg)', color: 'var(--text-h)', cursor: 'pointer',
                        fontSize: '0.85rem', whiteSpace: 'nowrap',
                      }}
                    >
                      Copier
                    </button>
                  </div>
                  <button
                    onClick={resetKeyForm}
                    className="app-btn-primary"
                    style={{ marginTop: '1rem' }}
                  >
                    Fermer
                  </button>
                </div>
              ) : (
                // Formulaire de creation
                <div>
                  <h3 style={{ marginTop: 0, marginBottom: '1rem', color: 'var(--text-h)', fontSize: '1rem' }}>
                    Nouvelle cle API
                  </h3>

                  <div className="app-form-group" style={{ marginBottom: '1rem' }}>
                    <label className="app-label">Nom de la cle</label>
                    <input
                      value={newKeyName}
                      onChange={(e) => setNewKeyName(e.target.value)}
                      className="app-input"
                      placeholder="Ex : Integration comptable, Application mobile"
                      maxLength={100}
                    />
                  </div>

                  <div className="app-form-group" style={{ marginBottom: '1.25rem' }}>
                    <label className="app-label">Portees (scopes)</label>
                    <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                      {AVAILABLE_SCOPES.map((scope) => (
                        <label
                          key={scope.value}
                          style={{
                            display: 'flex', alignItems: 'center', gap: '0.5rem',
                            cursor: 'pointer', fontSize: '0.9rem', color: 'var(--text-h)',
                          }}
                        >
                          <input
                            type="checkbox"
                            checked={newKeyScopes.includes(scope.value)}
                            onChange={() => toggleScope(scope.value)}
                            style={{ accentColor: 'var(--accent)' }}
                          />
                          <span>{scope.label}</span>
                          <code style={{ fontSize: '0.75rem', color: 'var(--text)', marginLeft: '0.25rem' }}>
                            {scope.value}
                          </code>
                        </label>
                      ))}
                    </div>
                  </div>

                  <div style={{ display: 'flex', gap: '0.75rem' }}>
                    <button
                      onClick={handleGenerateKey}
                      disabled={loadingKey}
                      className="app-btn-primary"
                    >
                      {loadingKey ? 'Generation...' : 'Generer'}
                    </button>
                    <button
                      onClick={resetKeyForm}
                      style={{
                        padding: '0.5rem 1rem', border: '1px solid var(--border)',
                        background: 'transparent', color: 'var(--text)', borderRadius: '6px',
                        cursor: 'pointer', fontSize: '0.9rem',
                      }}
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
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>Aucune cle API</p>
              <p style={{ fontSize: '0.9rem' }}>
                Generez votre premiere cle pour integrer Factura a vos outils.
              </p>
            </div>
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.9rem' }}>
                <thead>
                  <tr style={{ borderBottom: '1px solid var(--border)' }}>
                    <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600, fontSize: '0.8rem', whiteSpace: 'nowrap' }}>Nom</th>
                    <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600, fontSize: '0.8rem', whiteSpace: 'nowrap' }}>Cle</th>
                    <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600, fontSize: '0.8rem', whiteSpace: 'nowrap' }}>Portees</th>
                    <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600, fontSize: '0.8rem', whiteSpace: 'nowrap' }}>Creee le</th>
                    <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600, fontSize: '0.8rem', whiteSpace: 'nowrap' }}>Derniere utilisation</th>
                    <th style={{ textAlign: 'right', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600, fontSize: '0.8rem' }}></th>
                  </tr>
                </thead>
                <tbody>
                  {apiKeys.map((key) => (
                    <tr key={key.id} style={{ borderBottom: '1px solid var(--border)' }}>
                      <td style={{ padding: '0.75rem 0.5rem', color: 'var(--text-h)', fontWeight: 500 }}>
                        {key.name}
                      </td>
                      <td style={{ padding: '0.75rem 0.5rem' }}>
                        <code style={{ fontSize: '0.8rem', color: 'var(--text)', background: 'var(--surface)', padding: '2px 6px', borderRadius: '4px' }}>
                          {key.keyPreview}
                        </code>
                      </td>
                      <td style={{ padding: '0.75rem 0.5rem' }}>
                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem' }}>
                          {key.scopes.map((scope) => (
                            <span
                              key={scope}
                              style={{
                                background: 'var(--accent-bg)', color: 'var(--accent)',
                                padding: '1px 6px', borderRadius: '4px', fontSize: '0.75rem', fontWeight: 500,
                              }}
                            >
                              {scope}
                            </span>
                          ))}
                        </div>
                      </td>
                      <td style={{ padding: '0.75rem 0.5rem', color: 'var(--text)', fontSize: '0.85rem', whiteSpace: 'nowrap' }}>
                        {new Date(key.createdAt).toLocaleDateString('fr-FR')}
                      </td>
                      <td style={{ padding: '0.75rem 0.5rem', color: 'var(--text)', fontSize: '0.85rem', whiteSpace: 'nowrap' }}>
                        {key.lastUsedAt ? new Date(key.lastUsedAt).toLocaleDateString('fr-FR') : 'Jamais'}
                      </td>
                      <td style={{ padding: '0.75rem 0.5rem', textAlign: 'right' }}>
                        <button
                          onClick={() => handleRevokeKey(key.id)}
                          style={{
                            padding: '4px 10px', borderRadius: '6px', border: '1px solid rgba(239,68,68,0.3)',
                            background: 'rgba(239,68,68,0.1)', color: '#ef4444', cursor: 'pointer',
                            fontSize: '0.8rem', fontWeight: 500,
                          }}
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
          <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1.5rem', flexWrap: 'wrap', gap: '0.75rem' }}>
            <div>
              <h2 className="app-section-title" style={{ marginTop: 0, marginBottom: '0.25rem' }}>Webhooks</h2>
              <p style={{ color: 'var(--text)', fontSize: '0.9rem', margin: 0 }}>
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
            <div className="app-card" style={{ marginBottom: '1.5rem', padding: '1.5rem' }}>
              <h3 style={{ marginTop: 0, marginBottom: '1rem', color: 'var(--text-h)', fontSize: '1rem' }}>
                Nouveau webhook
              </h3>

              <div className="app-form-group" style={{ marginBottom: '1rem' }}>
                <label className="app-label">URL de l'endpoint</label>
                <input
                  value={webhookUrl}
                  onChange={(e) => setWebhookUrl(e.target.value)}
                  className="app-input"
                  placeholder="https://example.com/webhooks/factura"
                  type="url"
                />
              </div>

              <div className="app-form-group" style={{ marginBottom: '1rem' }}>
                <label className="app-label">Evenements</label>
                <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                  {AVAILABLE_EVENTS.map((event) => (
                    <label
                      key={event.value}
                      style={{
                        display: 'flex', alignItems: 'center', gap: '0.5rem',
                        cursor: 'pointer', fontSize: '0.9rem', color: 'var(--text-h)',
                      }}
                    >
                      <input
                        type="checkbox"
                        checked={webhookEvents.includes(event.value)}
                        onChange={() => toggleEvent(event.value)}
                        style={{ accentColor: 'var(--accent)' }}
                      />
                      <span>{event.label}</span>
                      <code style={{ fontSize: '0.75rem', color: 'var(--text)', marginLeft: '0.25rem' }}>
                        {event.value}
                      </code>
                    </label>
                  ))}
                </div>
              </div>

              <div className="app-form-group" style={{ marginBottom: '1.25rem' }}>
                <label className="app-label">Secret de signature (optionnel)</label>
                <input
                  value={webhookSecret}
                  onChange={(e) => setWebhookSecret(e.target.value)}
                  className="app-input"
                  placeholder="Utilise pour verifier la signature HMAC-SHA256 des requetes"
                  type="text"
                />
                <span style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem', display: 'block' }}>
                  Si defini, chaque requete inclura un en-tete X-Factura-Signature.
                </span>
              </div>

              <div style={{ display: 'flex', gap: '0.75rem' }}>
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
                  style={{
                    padding: '0.5rem 1rem', border: '1px solid var(--border)',
                    background: 'transparent', color: 'var(--text)', borderRadius: '6px',
                    cursor: 'pointer', fontSize: '0.9rem',
                  }}
                >
                  Annuler
                </button>
              </div>
            </div>
          )}

          {/* Liste des webhooks */}
          {webhooks.length === 0 && !showWebhookForm ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>Aucun webhook configure</p>
              <p style={{ fontSize: '0.9rem' }}>
                Ajoutez un endpoint pour recevoir les evenements en temps reel.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {webhooks.map((webhook) => (
                <div key={webhook.id} className="app-card" style={{ padding: '1.25rem' }}>
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', flexWrap: 'wrap', gap: '0.75rem' }}>
                    <div style={{ flex: 1, minWidth: 250 }}>
                      {/* URL et statut */}
                      <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem', marginBottom: '0.5rem', flexWrap: 'wrap' }}>
                        <code style={{ fontSize: '0.85rem', color: 'var(--text-h)', wordBreak: 'break-all' }}>
                          {webhook.url}
                        </code>
                        <span style={{
                          padding: '2px 8px', borderRadius: '1rem', fontSize: '0.75rem', fontWeight: 600,
                          background: webhook.active ? 'rgba(34,197,94,0.1)' : 'rgba(156,163,175,0.1)',
                          color: webhook.active ? '#22c55e' : '#9ca3af',
                        }}>
                          {webhook.active ? 'Actif' : 'En pause'}
                        </span>
                      </div>

                      {/* Evenements */}
                      <div style={{ display: 'flex', flexWrap: 'wrap', gap: '0.25rem', marginBottom: '0.5rem' }}>
                        {webhook.events.map((event) => (
                          <span
                            key={event}
                            style={{
                              background: 'var(--accent-bg)', color: 'var(--accent)',
                              padding: '1px 6px', borderRadius: '4px', fontSize: '0.75rem', fontWeight: 500,
                            }}
                          >
                            {event}
                          </span>
                        ))}
                      </div>

                      {/* Derniere livraison */}
                      <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>
                        {webhook.lastDeliveryAt ? (
                          <span>
                            Derniere livraison : {new Date(webhook.lastDeliveryAt).toLocaleString('fr-FR')}
                            {' — '}
                            <span style={{ color: webhook.lastDeliveryStatus === 'success' ? '#22c55e' : '#ef4444', fontWeight: 600 }}>
                              {webhook.lastDeliveryStatus === 'success' ? 'Succes' : 'Echec'}
                            </span>
                          </span>
                        ) : (
                          <span>Aucune livraison</span>
                        )}
                      </div>
                    </div>

                    {/* Actions */}
                    <div style={{ display: 'flex', gap: '0.5rem', flexWrap: 'wrap' }}>
                      <button
                        onClick={() => handleTestWebhook(webhook.id)}
                        style={{
                          padding: '4px 10px', borderRadius: '6px', border: '1px solid var(--border)',
                          background: 'var(--bg)', color: 'var(--text-h)', cursor: 'pointer',
                          fontSize: '0.8rem', fontWeight: 500,
                        }}
                      >
                        Tester
                      </button>
                      <button
                        onClick={() => handleToggleWebhook(webhook.id)}
                        style={{
                          padding: '4px 10px', borderRadius: '6px', border: '1px solid var(--border)',
                          background: 'var(--bg)', color: 'var(--text-h)', cursor: 'pointer',
                          fontSize: '0.8rem', fontWeight: 500,
                        }}
                      >
                        {webhook.active ? 'Pause' : 'Activer'}
                      </button>
                      <button
                        onClick={() => handleDeleteWebhook(webhook.id)}
                        style={{
                          padding: '4px 10px', borderRadius: '6px', border: '1px solid rgba(239,68,68,0.3)',
                          background: 'rgba(239,68,68,0.1)', color: '#ef4444', cursor: 'pointer',
                          fontSize: '0.8rem', fontWeight: 500,
                        }}
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
          <h2 className="app-section-title" style={{ marginTop: 0, marginBottom: '0.25rem' }}>Connecteurs</h2>
          <p style={{ color: 'var(--text)', fontSize: '0.9rem', marginBottom: '1.5rem' }}>
            Connectez Factura a vos outils comptables et d'automatisation preferes.
          </p>

          <div style={{
            display: 'grid',
            gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
            gap: '1rem',
          }}>
            {CONNECTORS.map((connector) => (
              <div
                key={connector.id}
                className="app-card"
                style={{
                  padding: '1.5rem',
                  display: 'flex', flexDirection: 'column',
                  opacity: connector.available ? 1 : 0.7,
                }}
              >
                {/* En-tete avec nom et badge */}
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
                  <div style={{
                    width: 44, height: 44, borderRadius: '10px',
                    background: connector.available ? 'var(--accent-bg)' : 'var(--surface)',
                    display: 'flex', alignItems: 'center', justifyContent: 'center',
                    fontWeight: 700, fontSize: '1rem',
                    color: connector.available ? 'var(--accent)' : 'var(--text)',
                  }}>
                    {connector.name.charAt(0)}
                  </div>
                  <span style={{
                    padding: '3px 10px', borderRadius: '1rem', fontSize: '0.75rem', fontWeight: 600,
                    background: connector.available ? 'rgba(34,197,94,0.1)' : 'rgba(234,179,8,0.15)',
                    color: connector.available ? '#22c55e' : '#ca8a04',
                  }}>
                    {connector.available ? 'Disponible' : 'Bientot'}
                  </span>
                </div>

                {/* Nom et description */}
                <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '1rem', marginBottom: '0.5rem' }}>
                  {connector.name}
                </div>
                <p style={{ fontSize: '0.85rem', color: 'var(--text)', margin: 0, flex: 1, lineHeight: 1.5 }}>
                  {connector.description}
                </p>

                {/* Bouton d'action */}
                <button
                  disabled={!connector.available}
                  className={connector.available ? 'app-btn-primary' : ''}
                  style={{
                    marginTop: '1rem', width: '100%',
                    padding: '0.5rem 1rem', borderRadius: '6px',
                    fontSize: '0.9rem', cursor: connector.available ? 'pointer' : 'not-allowed',
                    ...(connector.available ? {} : {
                      background: 'var(--surface)', color: 'var(--text)',
                      border: '1px solid var(--border)',
                    }),
                  }}
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
