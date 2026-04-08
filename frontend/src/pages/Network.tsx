import { useState, useEffect, useCallback } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import './AppLayout.css';

// Types pour les resultats de recherche dans l'annuaire
interface NetworkCompany {
  id: string;
  name: string;
  siren: string;
  city: string;
  verified: boolean;
  invoiceCount: number;
}

// Statistiques globales du reseau
interface NetworkStats {
  totalCompanies: number;
  totalInvoices: number;
  totalVolumeEur: number;
}

// Parrainage individuel
interface Referral {
  id: string;
  referredName: string;
  referredEmail: string;
  status: 'pending' | 'active' | 'expired';
  createdAt: string;
}

// Donnees de parrainage de l'utilisateur connecte
interface ReferralData {
  referralLink: string;
  referralCount: number;
  totalEarnings: number;
  referrals: Referral[];
}

// Onglets du module reseau
type NetworkTab = 'directory' | 'stats' | 'referral';

// Formate un montant en euros avec separateur de milliers
function formatEur(n: number): string {
  return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' \u20AC';
}

// Retourne le libelle et la couleur associes au statut d'un parrainage
function referralStatusLabel(status: Referral['status']): { label: string; color: string; bg: string } {
  switch (status) {
    case 'active':
      return { label: 'Actif', color: '#22c55e', bg: 'rgba(34, 197, 94, 0.1)' };
    case 'pending':
      return { label: 'En attente', color: '#ca8a04', bg: 'rgba(234, 179, 8, 0.15)' };
    case 'expired':
      return { label: 'Expire', color: '#9ca3af', bg: 'rgba(156, 163, 175, 0.1)' };
  }
}

export default function Network() {
  const [activeTab, setActiveTab] = useState<NetworkTab>('directory');
  const { success, error: toastError } = useToast();

  // Etat de l'onglet annuaire
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<NetworkCompany[]>([]);
  const [searchLoading, setSearchLoading] = useState(false);
  const [hasSearched, setHasSearched] = useState(false);

  // Etat de l'onglet statistiques
  const [stats, setStats] = useState<NetworkStats | null>(null);
  const [statsLoading, setStatsLoading] = useState(true);

  // Etat de l'onglet parrainage
  const [referralData, setReferralData] = useState<ReferralData | null>(null);
  const [referralLoading, setReferralLoading] = useState(true);

  // Recherche dans l'annuaire (SIREN ou raison sociale)
  const handleSearch = useCallback(async () => {
    const q = searchQuery.trim();
    if (!q) return;
    setSearchLoading(true);
    setHasSearched(true);
    try {
      const res = await api.get<{ 'hydra:member': NetworkCompany[] }>('/network/search', {
        params: { q },
      });
      setSearchResults(res.data['hydra:member'] || []);
    } catch {
      toastError('Impossible d\'effectuer la recherche. Veuillez reessayer.');
      setSearchResults([]);
    } finally {
      setSearchLoading(false);
    }
  }, [searchQuery, toastError]);

  // Soumission du formulaire de recherche
  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    handleSearch();
  };

  // Chargement des statistiques reseau
  useEffect(() => {
    if (activeTab !== 'stats') return;
    setStatsLoading(true);
    api.get<NetworkStats>('/network/stats')
      .then((res) => setStats(res.data))
      .catch(() => setStats(null))
      .finally(() => setStatsLoading(false));
  }, [activeTab]);

  // Chargement des donnees de parrainage
  useEffect(() => {
    if (activeTab !== 'referral') return;
    setReferralLoading(true);
    api.get<ReferralData>('/referrals/me')
      .then((res) => setReferralData(res.data))
      .catch(() => setReferralData(null))
      .finally(() => setReferralLoading(false));
  }, [activeTab]);

  // Copie le lien de parrainage dans le presse-papier
  const handleCopyReferralLink = async () => {
    if (!referralData?.referralLink) return;
    try {
      await navigator.clipboard.writeText(referralData.referralLink);
      success('Lien de parrainage copie dans le presse-papier.');
    } catch {
      toastError('Impossible de copier le lien.');
    }
  };

  const tabs: { key: NetworkTab; label: string }[] = [
    { key: 'directory', label: 'Annuaire' },
    { key: 'stats', label: 'Statistiques reseau' },
    { key: 'referral', label: 'Parrainage' },
  ];

  return (
    <div className="app-container">
      <h1 className="app-page-title">Reseau</h1>

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

      {/* Onglet Annuaire */}
      {activeTab === 'directory' && (
        <div>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem', maxWidth: 600, lineHeight: 1.6 }}>
            Recherchez une entreprise par son SIREN ou sa raison sociale pour consulter
            ses informations et son activite sur le reseau.
          </p>

          {/* Barre de recherche */}
          <form onSubmit={handleSearchSubmit} style={{ display: 'flex', gap: '0.75rem', marginBottom: '2rem', flexWrap: 'wrap' }}>
            <div className="app-form-group" style={{ flex: 1, minWidth: '200px', marginBottom: 0 }}>
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="app-input"
                placeholder="SIREN ou raison sociale..."
                style={{ marginBottom: 0 }}
              />
            </div>
            <button
              type="submit"
              className="app-btn-primary"
              disabled={searchLoading || !searchQuery.trim()}
              style={{ alignSelf: 'flex-end' }}
            >
              {searchLoading ? 'Recherche...' : 'Rechercher'}
            </button>
          </form>

          {/* Resultats */}
          {searchLoading && (
            <div style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
              gap: 'clamp(1rem, 2vw, 1.5rem)',
            }}>
              {[1, 2, 3].map((i) => (
                <div key={i} className="app-skeleton app-skeleton-card" style={{ height: 160 }} />
              ))}
            </div>
          )}

          {!searchLoading && hasSearched && searchResults.length === 0 && (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucun resultat</p>
              <p style={{ fontSize: '0.9rem' }}>
                Aucune entreprise ne correspond a votre recherche. Verifiez le SIREN ou le nom saisi.
              </p>
            </div>
          )}

          {!searchLoading && searchResults.length > 0 && (
            <div style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fill, minmax(280px, 1fr))',
              gap: 'clamp(1rem, 2vw, 1.5rem)',
            }}>
              {searchResults.map((company) => (
                <div key={company.id} className="app-card">
                  <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', marginBottom: '0.75rem' }}>
                    <h3 style={{ margin: 0, fontSize: '1rem', fontWeight: 600, color: 'var(--text-h)' }}>
                      {company.name}
                    </h3>
                    {company.verified && (
                      <span style={{
                        padding: '2px 8px', borderRadius: '1rem', fontSize: '0.7rem', fontWeight: 600,
                        background: 'rgba(34, 197, 94, 0.1)', color: '#22c55e', whiteSpace: 'nowrap',
                      }}>
                        Entreprise verifiee
                      </span>
                    )}
                  </div>
                  <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '0.5rem' }}>
                    <span style={{ fontWeight: 500, color: 'var(--text-h)' }}>SIREN :</span> {company.siren}
                  </div>
                  <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '0.75rem' }}>
                    <span style={{ fontWeight: 500, color: 'var(--text-h)' }}>Ville :</span> {company.city}
                  </div>
                  <div style={{
                    marginTop: 'auto', paddingTop: '0.75rem', borderTop: '1px solid var(--border)',
                    fontSize: '0.8rem', color: 'var(--text)',
                  }}>
                    {company.invoiceCount} facture(s) traitee(s)
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Onglet Statistiques reseau */}
      {activeTab === 'stats' && (
        <div>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem', maxWidth: 600, lineHeight: 1.6 }}>
            Vue d'ensemble de l'activite du reseau Factura. Ces chiffres sont mis a jour quotidiennement.
          </p>

          {statsLoading && (
            <div style={{
              display: 'grid',
              gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
              gap: 'clamp(1rem, 2vw, 1.5rem)',
              marginBottom: '2rem',
            }}>
              {[1, 2, 3].map((i) => (
                <div key={i} className="app-skeleton app-skeleton-card" />
              ))}
            </div>
          )}

          {!statsLoading && !stats && (
            <div style={{
              padding: '1rem', borderRadius: '6px',
              background: 'rgba(239, 68, 68, 0.1)', color: '#ef4444',
              marginBottom: '1.5rem', fontSize: '0.9rem',
            }}>
              Impossible de charger les statistiques du reseau. Veuillez reessayer.
            </div>
          )}

          {!statsLoading && stats && (
            <>
              {/* Cartes KPI */}
              <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(240px, 1fr))',
                gap: 'clamp(1rem, 2vw, 1.5rem)',
                marginBottom: '2rem',
              }}>
                <div className="app-card">
                  <h3 className="app-card-title">Total entreprises</h3>
                  <p className="app-card-value">
                    {stats.totalCompanies.toLocaleString('fr-FR')}
                  </p>
                </div>
                <div className="app-card">
                  <h3 className="app-card-title">Factures echangees</h3>
                  <p className="app-card-value">
                    {stats.totalInvoices.toLocaleString('fr-FR')}
                  </p>
                </div>
                <div className="app-card">
                  <h3 className="app-card-title">Volume total</h3>
                  <p className="app-card-value">
                    {formatEur(stats.totalVolumeEur)}
                  </p>
                </div>
              </div>

              {/* Espace reserve pour le graphique de croissance */}
              <div className="app-card" style={{ padding: '2rem', textAlign: 'center' }}>
                <h3 className="app-card-title" style={{ marginBottom: '1rem' }}>Croissance du reseau</h3>
                <div style={{
                  padding: '3rem 1rem', borderRadius: '8px',
                  background: 'var(--social-bg)', border: '1px dashed var(--border)',
                  color: 'var(--text)', fontSize: '0.9rem',
                }}>
                  Graphique de croissance a venir. Les donnees sont en cours de collecte
                  pour afficher l'evolution mensuelle du nombre d'entreprises et du volume de factures.
                </div>
              </div>
            </>
          )}
        </div>
      )}

      {/* Onglet Parrainage */}
      {activeTab === 'referral' && (
        <div>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem', maxWidth: 600, lineHeight: 1.6 }}>
            Invitez d'autres entreprises a rejoindre Factura et gagnez des avantages
            pour chaque inscription validee.
          </p>

          {referralLoading && (
            <div>
              <div className="app-skeleton" style={{ width: '100%', height: 60, borderRadius: '8px', marginBottom: '1.5rem' }} />
              <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
                gap: 'clamp(1rem, 2vw, 1.5rem)',
                marginBottom: '1.5rem',
              }}>
                <div className="app-skeleton app-skeleton-card" />
                <div className="app-skeleton app-skeleton-card" />
              </div>
              {[1, 2, 3].map((i) => (
                <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginBottom: '0.5rem' }} />
              ))}
            </div>
          )}

          {!referralLoading && !referralData && (
            <div style={{
              padding: '1rem', borderRadius: '6px',
              background: 'rgba(239, 68, 68, 0.1)', color: '#ef4444',
              marginBottom: '1.5rem', fontSize: '0.9rem',
            }}>
              Impossible de charger vos informations de parrainage. Veuillez reessayer.
            </div>
          )}

          {!referralLoading && referralData && (
            <>
              {/* Lien de parrainage */}
              <div className="app-card" style={{ marginBottom: '1.5rem' }}>
                <label className="app-label" style={{ marginBottom: '0.5rem' }}>Votre lien de parrainage</label>
                <div style={{ display: 'flex', gap: '0.75rem', flexWrap: 'wrap' }}>
                  <input
                    type="text"
                    value={referralData.referralLink}
                    readOnly
                    className="app-input"
                    style={{ flex: 1, minWidth: '200px', marginBottom: 0, background: 'var(--social-bg)' }}
                  />
                  <button
                    onClick={handleCopyReferralLink}
                    className="app-btn-primary"
                  >
                    Copier
                  </button>
                </div>
              </div>

              {/* Statistiques de parrainage */}
              <div style={{
                display: 'grid',
                gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
                gap: 'clamp(1rem, 2vw, 1.5rem)',
                marginBottom: '2rem',
              }}>
                <div className="app-card">
                  <h3 className="app-card-title">Nombre de parrainages</h3>
                  <p className="app-card-value">{referralData.referralCount}</p>
                </div>
                <div className="app-card">
                  <h3 className="app-card-title">Gains cumules</h3>
                  <p className="app-card-value">{formatEur(referralData.totalEarnings)}</p>
                </div>
              </div>

              {/* Liste des parrainages */}
              <h2 className="app-section-title">Historique des parrainages</h2>

              {referralData.referrals.length === 0 ? (
                <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
                  <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucun parrainage pour le moment</p>
                  <p style={{ fontSize: '0.9rem' }}>
                    Partagez votre lien de parrainage pour commencer a inviter des entreprises.
                  </p>
                </div>
              ) : (
                <div className="app-table-wrapper">
                  <table className="app-table">
                    <thead>
                      <tr>
                        <th>Entreprise</th>
                        <th>Email</th>
                        <th>Date</th>
                        <th>Statut</th>
                      </tr>
                    </thead>
                    <tbody>
                      {referralData.referrals.map((referral) => {
                        const statusInfo = referralStatusLabel(referral.status);
                        return (
                          <tr key={referral.id}>
                            <td style={{ fontWeight: 500, color: 'var(--text-h)' }}>
                              {referral.referredName}
                            </td>
                            <td style={{ color: 'var(--text)' }}>
                              {referral.referredEmail}
                            </td>
                            <td style={{ color: 'var(--text)' }}>
                              {new Date(referral.createdAt).toLocaleDateString('fr-FR')}
                            </td>
                            <td>
                              <span style={{
                                padding: '2px 10px', borderRadius: '1rem', fontSize: '0.8rem', fontWeight: 600,
                                background: statusInfo.bg, color: statusInfo.color,
                              }}>
                                {statusInfo.label}
                              </span>
                            </td>
                          </tr>
                        );
                      })}
                    </tbody>
                  </table>
                </div>
              )}
            </>
          )}
        </div>
      )}
    </div>
  );
}
