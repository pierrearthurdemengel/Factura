import { useState, useEffect, useCallback } from 'react';
import api from '../api/factura';
import './AppLayout.css';

// Types du portail comptable
interface ManagedClient {
  id: string;
  name: string;
  siren: string | null;
  invoiceCount: number;
  lastActivityAt: string | null;
}

interface AccountingEntry {
  id: string;
  date: string;
  journalCode: string;
  accountNumber: string;
  accountLabel: string;
  label: string;
  debit: string;
  credit: string;
  invoiceNumber: string | null;
  clientName: string;
  validated: boolean;
}

interface Invitation {
  id: string;
  email: string;
  siren: string;
  status: 'pending' | 'accepted' | 'expired';
  sentAt: string;
}

interface WhiteLabelConfig {
  cabinetName: string;
  primaryColor: string;
  secondaryColor: string;
  logoUrl: string;
}

// Onglets disponibles
type PortalTab = 'clients' | 'entries' | 'invitations';

export default function AccountantPortal() {
  const [activeTab, setActiveTab] = useState<PortalTab>('clients');
  const [loading, setLoading] = useState(true);

  // Etat de l'onglet clients
  const [clients, setClients] = useState<ManagedClient[]>([]);
  const [selectedClientId, setSelectedClientId] = useState<string | null>(null);

  // Etat de l'onglet ecritures
  const [entries, setEntries] = useState<AccountingEntry[]>([]);
  const [selectedEntryIds, setSelectedEntryIds] = useState<Set<string>>(new Set());
  const [validating, setValidating] = useState(false);

  // Etat de l'onglet invitations
  const [invitations, setInvitations] = useState<Invitation[]>([]);
  const [inviteEmail, setInviteEmail] = useState('');
  const [inviteSiren, setInviteSiren] = useState('');
  const [inviting, setInviting] = useState(false);
  const [inviteError, setInviteError] = useState('');
  const [inviteSuccess, setInviteSuccess] = useState('');

  // Configuration marque blanche
  const [whiteLabel, setWhiteLabel] = useState<WhiteLabelConfig>({
    cabinetName: '',
    primaryColor: '#2563eb',
    secondaryColor: '#1e40af',
    logoUrl: '',
  });
  const [savingWhiteLabel, setSavingWhiteLabel] = useState(false);

  // Chargement des clients du cabinet
  const loadClients = useCallback(async () => {
    try {
      const res = await api.get('/accountant/clients');
      setClients(res.data['hydra:member'] || []);
    } catch {
      setClients([]);
    }
  }, []);

  // Chargement des ecritures a valider
  const loadEntries = useCallback(async () => {
    try {
      const params: Record<string, string> = {};
      if (selectedClientId) {
        params.client = selectedClientId;
      }
      const res = await api.get('/accountant/entries', { params });
      setEntries(res.data['hydra:member'] || []);
    } catch {
      setEntries([]);
    }
  }, [selectedClientId]);

  // Chargement des invitations en cours
  const loadInvitations = useCallback(async () => {
    try {
      const res = await api.get('/accountant/invitations');
      setInvitations(res.data['hydra:member'] || []);
    } catch {
      setInvitations([]);
    }
  }, []);

  // Chargement de la configuration marque blanche
  const loadWhiteLabel = useCallback(async () => {
    try {
      const res = await api.get('/accountant/white-label');
      setWhiteLabel(res.data);
    } catch {
      // Configuration par defaut conservee
    }
  }, []);

  // Chargement initial selon l'onglet actif
  useEffect(() => {
    setLoading(true);

    const loadData = async () => {
      switch (activeTab) {
        case 'clients':
          await loadClients();
          break;
        case 'entries':
          await loadEntries();
          break;
        case 'invitations':
          await Promise.all([loadInvitations(), loadWhiteLabel()]);
          break;
      }
      setLoading(false);
    };

    loadData();
  }, [activeTab, loadClients, loadEntries, loadInvitations, loadWhiteLabel]);

  // Rechargement des ecritures quand on change de client
  useEffect(() => {
    if (activeTab === 'entries') {
      setLoading(true);
      loadEntries().finally(() => setLoading(false));
    }
  }, [selectedClientId, activeTab, loadEntries]);

  // Gestion de la selection des ecritures
  const toggleEntrySelection = (id: string) => {
    setSelectedEntryIds((prev) => {
      const next = new Set(prev);
      if (next.has(id)) {
        next.delete(id);
      } else {
        next.add(id);
      }
      return next;
    });
  };

  // Selection / deselection de toutes les ecritures non validees
  const toggleSelectAll = () => {
    const unvalidated = entries.filter((e) => !e.validated);
    if (selectedEntryIds.size === unvalidated.length) {
      setSelectedEntryIds(new Set());
    } else {
      setSelectedEntryIds(new Set(unvalidated.map((e) => e.id)));
    }
  };

  // Validation par lot des ecritures selectionnees
  const handleValidateEntries = async () => {
    if (selectedEntryIds.size === 0) return;
    setValidating(true);

    try {
      await api.post('/accountant/entries/validate', {
        entryIds: Array.from(selectedEntryIds),
      });
      setSelectedEntryIds(new Set());
      await loadEntries();
    } catch {
      // Erreur de validation
    } finally {
      setValidating(false);
    }
  };

  // Envoi d'une invitation client
  const handleInvite = async (e: React.FormEvent) => {
    e.preventDefault();
    setInviteError('');
    setInviteSuccess('');

    if (!inviteEmail.trim() || !inviteSiren.trim()) {
      setInviteError('L\'email et le SIREN sont obligatoires.');
      return;
    }

    // Verification format SIREN (9 chiffres)
    const cleanedSiren = inviteSiren.replace(/\s/g, '');
    if (!/^\d{9}$/.test(cleanedSiren)) {
      setInviteError('Le SIREN doit contenir exactement 9 chiffres.');
      return;
    }

    setInviting(true);

    try {
      await api.post('/accountant/invitations', {
        email: inviteEmail.trim(),
        siren: cleanedSiren,
      });
      setInviteEmail('');
      setInviteSiren('');
      setInviteSuccess('Invitation envoyee avec succes.');
      await loadInvitations();
    } catch {
      setInviteError('Impossible d\'envoyer l\'invitation. Verifiez les informations.');
    } finally {
      setInviting(false);
    }
  };

  // Sauvegarde de la configuration marque blanche
  const handleSaveWhiteLabel = async () => {
    setSavingWhiteLabel(true);

    try {
      await api.put('/accountant/white-label', whiteLabel);
    } catch {
      // Erreur de sauvegarde
    } finally {
      setSavingWhiteLabel(false);
    }
  };

  // Libelle du statut d'invitation
  const getInvitationStatusLabel = (status: Invitation['status']): string => {
    switch (status) {
      case 'pending': return 'En attente';
      case 'accepted': return 'Acceptee';
      case 'expired': return 'Expiree';
    }
  };

  // Classe CSS du statut d'invitation
  const getInvitationStatusClass = (status: Invitation['status']): string => {
    switch (status) {
      case 'pending': return 'app-status-pill app-status-pill--muted';
      case 'accepted': return 'app-status-pill app-status-pill--connected';
      case 'expired': return 'app-status-pill app-status-pill--closed';
    }
  };

  const tabs: { key: PortalTab; label: string }[] = [
    { key: 'clients', label: 'Mes clients' },
    { key: 'entries', label: 'Ecritures' },
    { key: 'invitations', label: 'Invitations' },
  ];

  // Squelette de chargement
  if (loading) {
    return (
      <div className="app-container">
        <div className="app-skeleton app-skeleton-title" />
        <div className="app-skeleton-pills app-tabs">
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton app-tab" />
          ))}
        </div>
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="app-skeleton app-skeleton-card" />
        ))}
      </div>
    );
  }

  return (
    <div className="app-container">
      <h1 className="app-page-title">Portail comptable</h1>
      <p className="app-desc">
        Gerez les dossiers de vos clients, validez leurs ecritures comptables et invitez de nouveaux clients.
      </p>

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

      {/* Onglet : Mes clients */}
      {activeTab === 'clients' && (
        <div>
          {clients.length === 0 ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucun client pour le moment</p>
              <p className="app-empty-desc">
                Invitez vos premiers clients depuis l'onglet Invitations.
              </p>
            </div>
          ) : (
            <div className="app-cards-grid">
              {clients.map((client) => (
                <div
                  key={client.id}
                  className={`app-card app-client-card ${selectedClientId === client.id ? 'app-plan-card--selected' : ''}`}
                  onClick={() => setSelectedClientId(client.id)}
                  style={{ cursor: 'pointer' }}
                >
                  <div className="app-client-card-header">
                    {/* Avatar avec initiale */}
                    <div className="app-health-indicator" style={{ background: 'var(--accent-bg)', color: 'var(--accent)' }}>
                      {client.name.charAt(0).toUpperCase()}
                    </div>

                    {/* Indicateur de selection */}
                    {selectedClientId === client.id && (
                      <div className="app-health-indicator" style={{ width: 10, height: 10, background: 'var(--accent)' }} />
                    )}
                  </div>

                  {/* Informations du client */}
                  <div className="app-client-card-name">{client.name}</div>
                  <div className="app-client-card-sub">
                    {client.siren && <span>SIREN : {client.siren}</span>}
                    {client.siren && ' — '}
                    <span>{client.invoiceCount} facture{client.invoiceCount > 1 ? 's' : ''}</span>
                  </div>

                  {client.lastActivityAt && (
                    <div className="app-client-card-meta">
                      Derniere activite : {new Date(client.lastActivityAt).toLocaleDateString('fr-FR')}
                    </div>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Onglet : Ecritures comptables */}
      {activeTab === 'entries' && (
        <div>
          {/* Filtre par client */}
          <div className="app-filter-bar">
            <select
              value={selectedClientId || ''}
              onChange={(e) => setSelectedClientId(e.target.value || null)}
              className="app-select"
            >
              <option value="">Tous les clients</option>
              {clients.map((c) => (
                <option key={c.id} value={c.id}>{c.name}</option>
              ))}
            </select>

            {selectedEntryIds.size > 0 && (
              <button
                onClick={handleValidateEntries}
                disabled={validating}
                className="app-btn-primary"
              >
                {validating
                  ? 'Validation...'
                  : `Valider la selection (${selectedEntryIds.size})`
                }
              </button>
            )}
          </div>

          {entries.length === 0 ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucune ecriture a afficher</p>
              <p className="app-empty-desc">
                Les ecritures apparaitront ici lorsque vos clients emettront des factures.
              </p>
            </div>
          ) : (
            <div className="app-table-wrapper">
              <table className="app-table">
                <thead>
                  <tr>
                    <th>
                      <input
                        type="checkbox"
                        checked={
                          entries.filter((e) => !e.validated).length > 0 &&
                          selectedEntryIds.size === entries.filter((e) => !e.validated).length
                        }
                        onChange={toggleSelectAll}
                        title="Tout selectionner"
                      />
                    </th>
                    <th>Date</th>
                    <th>Client</th>
                    <th>Journal</th>
                    <th>Compte</th>
                    <th>Libelle</th>
                    <th className="text-right">Debit</th>
                    <th className="text-right">Credit</th>
                    <th className="text-center">Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {entries.map((entry) => (
                    <tr
                      key={entry.id}
                      style={{ opacity: entry.validated ? 0.6 : 1 }}
                    >
                      <td>
                        {!entry.validated && (
                          <input
                            type="checkbox"
                            checked={selectedEntryIds.has(entry.id)}
                            onChange={() => toggleEntrySelection(entry.id)}
                          />
                        )}
                      </td>
                      <td>
                        {new Date(entry.date).toLocaleDateString('fr-FR')}
                      </td>
                      <td>
                        {entry.clientName}
                      </td>
                      <td>
                        <span className="app-status-pill" style={{ background: 'var(--accent-bg)', color: 'var(--accent)' }}>
                          {entry.journalCode}
                        </span>
                      </td>
                      <td>
                        {entry.accountNumber} — {entry.accountLabel}
                      </td>
                      <td>
                        {entry.label}
                        {entry.invoiceNumber && (
                          <span className="app-badge app-badge-sent">
                            ({entry.invoiceNumber})
                          </span>
                        )}
                      </td>
                      <td className="text-right">
                        {parseFloat(entry.debit) > 0
                          ? parseFloat(entry.debit).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                          : ''}
                      </td>
                      <td className="text-right">
                        {parseFloat(entry.credit) > 0
                          ? parseFloat(entry.credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                          : ''}
                      </td>
                      <td className="text-center">
                        <span className={`app-status-pill ${entry.validated ? 'app-status-pill--connected' : 'app-status-pill--muted'}`}>
                          {entry.validated ? 'Validee' : 'A valider'}
                        </span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      )}

      {/* Onglet : Invitations */}
      {activeTab === 'invitations' && (
        <div>
          {/* Formulaire d'invitation */}
          <div className="app-card" style={{ marginBottom: '1.5rem' }}>
            <h2 className="app-subsection-title app-mt-0">
              Inviter un client
            </h2>
            <p className="app-desc" style={{ marginBottom: '1rem' }}>
              Envoyez une invitation par email pour rattacher un client a votre cabinet.
            </p>

            <form onSubmit={handleInvite}>
              <div className="app-form-row">
                <div className="app-form-group flex-2">
                  <input
                    type="email"
                    value={inviteEmail}
                    onChange={(e) => setInviteEmail(e.target.value)}
                    placeholder="Email du client"
                    className="app-input"
                    required
                  />
                </div>
                <div className="app-form-group">
                  <input
                    type="text"
                    value={inviteSiren}
                    onChange={(e) => setInviteSiren(e.target.value)}
                    placeholder="SIREN (9 chiffres)"
                    className="app-input"
                    required
                  />
                </div>
                <div className="app-form-group" style={{ flex: 'none' }}>
                  <button
                    type="submit"
                    disabled={inviting}
                    className="app-btn-primary"
                  >
                    {inviting ? 'Envoi...' : 'Envoyer l\'invitation'}
                  </button>
                </div>
              </div>

              {inviteError && (
                <div className="app-alert app-alert--error">
                  {inviteError}
                </div>
              )}
              {inviteSuccess && (
                <div className="app-alert app-alert--success">
                  {inviteSuccess}
                </div>
              )}
            </form>
          </div>

          {/* Liste des invitations en cours */}
          <h2 className="app-subsection-title">
            Invitations en cours
          </h2>

          {invitations.length === 0 ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucune invitation envoyee</p>
              <p className="app-empty-desc">
                Utilisez le formulaire ci-dessus pour inviter votre premier client.
              </p>
            </div>
          ) : (
            <div className="app-list app-mb-2">
              {invitations.map((inv) => (
                <div
                  key={inv.id}
                  className="app-list-item"
                >
                  <div className="app-list-item-info">
                    <div className="app-list-item-title">
                      {inv.email}
                    </div>
                    <div className="app-list-item-sub">
                      SIREN : {inv.siren} — Envoyee le {new Date(inv.sentAt).toLocaleDateString('fr-FR')}
                    </div>
                  </div>
                  <span className={getInvitationStatusClass(inv.status)}>
                    {getInvitationStatusLabel(inv.status)}
                  </span>
                </div>
              ))}
            </div>
          )}

          {/* Section marque blanche */}
          <div className="app-section-separator">
            <h2 className="app-subsection-title">
              Personnalisation marque blanche
            </h2>
            <p className="app-desc">
              Personnalisez l'apparence du portail avec les couleurs et le nom de votre cabinet.
              Vos clients verront cette identite visuelle sur les documents partages.
            </p>

            <div className="app-card app-factoring-container">
              <div className="app-form-group">
                <label className="app-label">
                  Nom du cabinet
                </label>
                <input
                  type="text"
                  value={whiteLabel.cabinetName}
                  onChange={(e) => setWhiteLabel({ ...whiteLabel, cabinetName: e.target.value })}
                  placeholder="Ex: Cabinet Dupont & Associes"
                  className="app-input"
                />
              </div>

              <div className="app-form-row">
                <div className="app-form-group">
                  <label className="app-label">
                    Couleur principale
                  </label>
                  <div className="app-color-picker-row">
                    <input
                      type="color"
                      value={whiteLabel.primaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, primaryColor: e.target.value })}
                      className="app-color-swatch"
                    />
                    <input
                      type="text"
                      value={whiteLabel.primaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, primaryColor: e.target.value })}
                      className="app-input app-color-hex-input"
                      maxLength={7}
                    />
                  </div>
                </div>

                <div className="app-form-group">
                  <label className="app-label">
                    Couleur secondaire
                  </label>
                  <div className="app-color-picker-row">
                    <input
                      type="color"
                      value={whiteLabel.secondaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, secondaryColor: e.target.value })}
                      className="app-color-swatch"
                    />
                    <input
                      type="text"
                      value={whiteLabel.secondaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, secondaryColor: e.target.value })}
                      className="app-input app-color-hex-input"
                      maxLength={7}
                    />
                  </div>
                </div>
              </div>

              {/* Apercu des couleurs */}
              <div className="app-integration-card">
                <div style={{
                  width: 32, height: 32, borderRadius: 6,
                  background: whiteLabel.primaryColor, flexShrink: 0,
                }} />
                <div style={{
                  width: 32, height: 32, borderRadius: 6,
                  background: whiteLabel.secondaryColor, flexShrink: 0,
                }} />
                <span className="app-list-item-sub">
                  Apercu : {whiteLabel.cabinetName || 'Votre cabinet'}
                </span>
              </div>

              <div className="app-form-actions">
                <button
                  onClick={handleSaveWhiteLabel}
                  disabled={savingWhiteLabel}
                  className="app-btn-primary"
                >
                  {savingWhiteLabel ? 'Enregistrement...' : 'Enregistrer la personnalisation'}
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
