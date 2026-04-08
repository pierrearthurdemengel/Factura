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

  // Couleur du statut d'invitation
  const getInvitationStatusColor = (status: Invitation['status']): string => {
    switch (status) {
      case 'pending': return '#f59e0b';
      case 'accepted': return '#22c55e';
      case 'expired': return '#ef4444';
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
        <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem' }}>
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton" style={{ width: 100, height: 36, borderRadius: 6 }} />
          ))}
        </div>
        {[1, 2, 3, 4].map((i) => (
          <div key={i} className="app-skeleton app-skeleton-card" style={{ marginBottom: '0.75rem' }} />
        ))}
      </div>
    );
  }

  return (
    <div className="app-container" style={{ textAlign: 'left' }}>
      <h1 className="app-page-title">Portail comptable</h1>
      <p style={{ color: 'var(--text)', marginBottom: '1.5rem', maxWidth: 650, lineHeight: 1.6 }}>
        Gerez les dossiers de vos clients, validez leurs ecritures comptables et invitez de nouveaux clients.
      </p>

      {/* Onglets */}
      <div style={{
        display: 'flex', gap: '0.5rem', marginBottom: '1.5rem',
        borderBottom: '1px solid var(--border)', paddingBottom: '0.5rem', overflowX: 'auto',
      }}>
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

      {/* Onglet : Mes clients */}
      {activeTab === 'clients' && (
        <div>
          {clients.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucun client pour le moment</p>
              <p style={{ fontSize: '0.9rem' }}>
                Invitez vos premiers clients depuis l'onglet Invitations.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {clients.map((client) => (
                <div
                  key={client.id}
                  className="app-card"
                  onClick={() => setSelectedClientId(client.id)}
                  style={{
                    cursor: 'pointer',
                    flexDirection: 'row', alignItems: 'center', gap: '1rem',
                    border: selectedClientId === client.id
                      ? '2px solid var(--accent)'
                      : '1px solid var(--border)',
                    transition: 'border-color 0.15s ease',
                  }}
                >
                  {/* Avatar avec initiale */}
                  <div style={{
                    width: 44, height: 44, borderRadius: '50%',
                    background: 'var(--accent-bg)', display: 'flex',
                    alignItems: 'center', justifyContent: 'center',
                    fontWeight: 700, color: 'var(--accent)', fontSize: '1.1rem', flexShrink: 0,
                  }}>
                    {client.name.charAt(0).toUpperCase()}
                  </div>

                  {/* Informations du client */}
                  <div style={{ flex: 1, minWidth: 0 }}>
                    <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.95rem' }}>
                      {client.name}
                    </div>
                    <div style={{
                      display: 'flex', gap: '0.75rem', flexWrap: 'wrap',
                      marginTop: '0.25rem', fontSize: '0.8rem', color: 'var(--text)',
                    }}>
                      {client.siren && <span>SIREN : {client.siren}</span>}
                      <span>{client.invoiceCount} facture{client.invoiceCount > 1 ? 's' : ''}</span>
                      {client.lastActivityAt && (
                        <span>
                          Derniere activite : {new Date(client.lastActivityAt).toLocaleDateString('fr-FR')}
                        </span>
                      )}
                    </div>
                  </div>

                  {/* Indicateur de selection */}
                  <div style={{
                    width: 8, height: 8, borderRadius: '50%', flexShrink: 0,
                    background: selectedClientId === client.id ? 'var(--accent)' : 'transparent',
                  }} />
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
          <div style={{
            display: 'flex', justifyContent: 'space-between', alignItems: 'center',
            flexWrap: 'wrap', gap: '0.75rem', marginBottom: '1rem',
          }}>
            <select
              value={selectedClientId || ''}
              onChange={(e) => setSelectedClientId(e.target.value || null)}
              className="app-input"
              style={{ width: 'auto', minWidth: 200 }}
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
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucune ecriture a afficher</p>
              <p style={{ fontSize: '0.9rem' }}>
                Les ecritures apparaitront ici lorsque vos clients emettront des factures.
              </p>
            </div>
          ) : (
            <div style={{ overflowX: 'auto' }}>
              <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
                <thead>
                  <tr style={{ borderBottom: '2px solid var(--border)', textAlign: 'left' }}>
                    <th style={{ padding: '0.5rem', width: 40 }}>
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
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Date</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Client</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Journal</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Compte</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Libelle</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Debit</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Credit</th>
                    <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'center' }}>Statut</th>
                  </tr>
                </thead>
                <tbody>
                  {entries.map((entry) => (
                    <tr
                      key={entry.id}
                      style={{
                        borderBottom: '1px solid var(--border)',
                        opacity: entry.validated ? 0.6 : 1,
                      }}
                    >
                      <td style={{ padding: '0.5rem' }}>
                        {!entry.validated && (
                          <input
                            type="checkbox"
                            checked={selectedEntryIds.has(entry.id)}
                            onChange={() => toggleEntrySelection(entry.id)}
                          />
                        )}
                      </td>
                      <td style={{ padding: '0.5rem', color: 'var(--text)', whiteSpace: 'nowrap' }}>
                        {new Date(entry.date).toLocaleDateString('fr-FR')}
                      </td>
                      <td style={{ padding: '0.5rem', color: 'var(--text-h)', fontWeight: 500 }}>
                        {entry.clientName}
                      </td>
                      <td style={{ padding: '0.5rem' }}>
                        <span style={{
                          background: 'var(--accent-bg)', color: 'var(--accent)',
                          padding: '2px 6px', borderRadius: '4px',
                          fontSize: '0.8rem', fontWeight: 600,
                        }}>
                          {entry.journalCode}
                        </span>
                      </td>
                      <td style={{ padding: '0.5rem', color: 'var(--text-h)', fontWeight: 500 }}>
                        {entry.accountNumber} — {entry.accountLabel}
                      </td>
                      <td style={{ padding: '0.5rem', color: 'var(--text)' }}>
                        {entry.label}
                        {entry.invoiceNumber && (
                          <span style={{ color: 'var(--accent)', marginLeft: '0.5rem', fontSize: '0.8rem' }}>
                            ({entry.invoiceNumber})
                          </span>
                        )}
                      </td>
                      <td style={{
                        padding: '0.5rem', textAlign: 'right',
                        color: parseFloat(entry.debit) > 0 ? 'var(--text-h)' : 'var(--text)',
                      }}>
                        {parseFloat(entry.debit) > 0
                          ? parseFloat(entry.debit).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                          : ''}
                      </td>
                      <td style={{
                        padding: '0.5rem', textAlign: 'right',
                        color: parseFloat(entry.credit) > 0 ? 'var(--text-h)' : 'var(--text)',
                      }}>
                        {parseFloat(entry.credit) > 0
                          ? parseFloat(entry.credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 })
                          : ''}
                      </td>
                      <td style={{ padding: '0.5rem', textAlign: 'center' }}>
                        <span style={{
                          padding: '2px 8px', borderRadius: '1rem',
                          fontSize: '0.75rem', fontWeight: 600,
                          background: entry.validated ? 'rgba(34,197,94,0.1)' : 'rgba(245,158,11,0.1)',
                          color: entry.validated ? '#22c55e' : '#f59e0b',
                        }}>
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
          <div className="app-card" style={{ marginBottom: '1.5rem', padding: '1.5rem' }}>
            <h2 style={{ margin: '0 0 0.5rem', color: 'var(--text-h)', fontSize: '1rem' }}>
              Inviter un client
            </h2>
            <p style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem' }}>
              Envoyez une invitation par email pour rattacher un client a votre cabinet.
            </p>

            <form onSubmit={handleInvite}>
              <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
                <input
                  type="email"
                  value={inviteEmail}
                  onChange={(e) => setInviteEmail(e.target.value)}
                  placeholder="Email du client"
                  className="app-input"
                  style={{ flex: 1, minWidth: 200 }}
                  required
                />
                <input
                  type="text"
                  value={inviteSiren}
                  onChange={(e) => setInviteSiren(e.target.value)}
                  placeholder="SIREN (9 chiffres)"
                  className="app-input"
                  style={{ flex: 1, minWidth: 160 }}
                  required
                />
                <button
                  type="submit"
                  disabled={inviting}
                  className="app-btn-primary"
                  style={{ whiteSpace: 'nowrap' }}
                >
                  {inviting ? 'Envoi...' : 'Envoyer l\'invitation'}
                </button>
              </div>

              {inviteError && (
                <div style={{ marginTop: '0.75rem', color: '#ef4444', fontSize: '0.85rem' }}>
                  {inviteError}
                </div>
              )}
              {inviteSuccess && (
                <div style={{ marginTop: '0.75rem', color: '#22c55e', fontSize: '0.85rem' }}>
                  {inviteSuccess}
                </div>
              )}
            </form>
          </div>

          {/* Liste des invitations en cours */}
          <h2 style={{ fontSize: '1rem', color: 'var(--text-h)', marginBottom: '0.75rem' }}>
            Invitations en cours
          </h2>

          {invitations.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '2rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucune invitation envoyee</p>
              <p style={{ fontSize: '0.9rem' }}>
                Utilisez le formulaire ci-dessus pour inviter votre premier client.
              </p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '2rem' }}>
              {invitations.map((inv) => (
                <div
                  key={inv.id}
                  className="app-card"
                  style={{
                    flexDirection: 'row', alignItems: 'center',
                    justifyContent: 'space-between', gap: '0.75rem', flexWrap: 'wrap',
                  }}
                >
                  <div style={{ flex: 1, minWidth: 200 }}>
                    <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.9rem' }}>
                      {inv.email}
                    </div>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.2rem' }}>
                      SIREN : {inv.siren} — Envoyee le {new Date(inv.sentAt).toLocaleDateString('fr-FR')}
                    </div>
                  </div>
                  <span style={{
                    padding: '3px 10px', borderRadius: '1rem',
                    fontSize: '0.75rem', fontWeight: 600,
                    background: `${getInvitationStatusColor(inv.status)}18`,
                    color: getInvitationStatusColor(inv.status),
                  }}>
                    {getInvitationStatusLabel(inv.status)}
                  </span>
                </div>
              ))}
            </div>
          )}

          {/* Section marque blanche */}
          <div style={{
            marginTop: '2rem', paddingTop: '2rem',
            borderTop: '1px solid var(--border)',
          }}>
            <h2 style={{ fontSize: '1rem', color: 'var(--text-h)', marginBottom: '0.5rem' }}>
              Personnalisation marque blanche
            </h2>
            <p style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem', maxWidth: 550 }}>
              Personnalisez l'apparence du portail avec les couleurs et le nom de votre cabinet.
              Vos clients verront cette identite visuelle sur les documents partages.
            </p>

            <div className="app-card" style={{ maxWidth: 500, padding: '1.5rem' }}>
              <div className="app-form-group">
                <label className="app-label" style={{ fontSize: '0.85rem', marginBottom: '0.35rem' }}>
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

              <div style={{ display: 'flex', gap: '1rem', flexWrap: 'wrap' }}>
                <div className="app-form-group" style={{ flex: 1, minWidth: 140 }}>
                  <label className="app-label" style={{ fontSize: '0.85rem', marginBottom: '0.35rem' }}>
                    Couleur principale
                  </label>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <input
                      type="color"
                      value={whiteLabel.primaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, primaryColor: e.target.value })}
                      style={{
                        width: 36, height: 36, border: '1px solid var(--border)',
                        borderRadius: 6, cursor: 'pointer', padding: 2,
                      }}
                    />
                    <input
                      type="text"
                      value={whiteLabel.primaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, primaryColor: e.target.value })}
                      className="app-input"
                      style={{ flex: 1 }}
                      maxLength={7}
                    />
                  </div>
                </div>

                <div className="app-form-group" style={{ flex: 1, minWidth: 140 }}>
                  <label className="app-label" style={{ fontSize: '0.85rem', marginBottom: '0.35rem' }}>
                    Couleur secondaire
                  </label>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '0.5rem' }}>
                    <input
                      type="color"
                      value={whiteLabel.secondaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, secondaryColor: e.target.value })}
                      style={{
                        width: 36, height: 36, border: '1px solid var(--border)',
                        borderRadius: 6, cursor: 'pointer', padding: 2,
                      }}
                    />
                    <input
                      type="text"
                      value={whiteLabel.secondaryColor}
                      onChange={(e) => setWhiteLabel({ ...whiteLabel, secondaryColor: e.target.value })}
                      className="app-input"
                      style={{ flex: 1 }}
                      maxLength={7}
                    />
                  </div>
                </div>
              </div>

              {/* Apercu des couleurs */}
              <div style={{
                display: 'flex', gap: '0.75rem', alignItems: 'center',
                padding: '0.75rem', borderRadius: 6, marginBottom: '1rem',
                background: 'var(--surface)', border: '1px solid var(--border)',
              }}>
                <div style={{
                  width: 32, height: 32, borderRadius: 6,
                  background: whiteLabel.primaryColor, flexShrink: 0,
                }} />
                <div style={{
                  width: 32, height: 32, borderRadius: 6,
                  background: whiteLabel.secondaryColor, flexShrink: 0,
                }} />
                <span style={{ fontSize: '0.8rem', color: 'var(--text)' }}>
                  Apercu : {whiteLabel.cabinetName || 'Votre cabinet'}
                </span>
              </div>

              <button
                onClick={handleSaveWhiteLabel}
                disabled={savingWhiteLabel}
                className="app-btn-primary"
                style={{ width: '100%' }}
              >
                {savingWhiteLabel ? 'Enregistrement...' : 'Enregistrer la personnalisation'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
