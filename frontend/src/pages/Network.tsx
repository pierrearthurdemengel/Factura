import { useState, useEffect, useCallback } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
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
      return { label: 'Actif', color: 'var(--success)', bg: 'var(--success-bg)' };
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
    const cached = getCached<NetworkStats>('/network/stats');
    if (cached) {
      queueMicrotask(() => {
        setStats(cached);
        setStatsLoading(false);
      });
    } else {
      setStatsLoading(true);
    }
    api.get<NetworkStats>('/network/stats')
      .then((res) => {
        setStats(res.data);
        setCache('/network/stats', res.data);
      })
      .catch(() => { if (!cached) setStats(null); })
      .finally(() => setStatsLoading(false));
  }, [activeTab]);

  // Chargement des donnees de parrainage
  useEffect(() => {
    if (activeTab !== 'referral') return;
    const cached = getCached<ReferralData>('/referrals/me');
    if (cached) {
      queueMicrotask(() => {
        setReferralData(cached);
        setReferralLoading(false);
      });
    } else {
      setReferralLoading(true);
    }
    api.get<ReferralData>('/referrals/me')
      .then((res) => {
        setReferralData(res.data);
        setCache('/referrals/me', res.data);
      })
      .catch(() => { if (!cached) setReferralData(null); })
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

      {/* Onglet Annuaire */}
      {activeTab === 'directory' && (
        <div>
          <p className="app-desc">
            Recherchez une entreprise par son SIREN ou sa raison sociale pour consulter
            ses informations et son activite sur le reseau.
          </p>

          {/* Barre de recherche */}
          <form onSubmit={handleSearchSubmit} className="app-filter-bar">
            <input
              type="text"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="app-input"
              placeholder="SIREN ou raison sociale..."
            />
            <button
              type="submit"
              className="app-btn-primary"
              disabled={searchLoading || !searchQuery.trim()}
            >
              {searchLoading ? 'Recherche...' : 'Rechercher'}
            </button>
          </form>

          {/* Resultats */}
          {searchLoading && (
            <div className="app-cards-grid">
              {[1, 2, 3].map((i) => (
                <div key={i} className="app-skeleton app-skeleton-card" />
              ))}
            </div>
          )}

          {!searchLoading && hasSearched && searchResults.length === 0 && (
            <div className="app-empty">
              <p className="app-empty-title">Aucun resultat</p>
              <p className="app-empty-desc">
                Aucune entreprise ne correspond a votre recherche. Verifiez le SIREN ou le nom saisi.
              </p>
            </div>
          )}

          {!searchLoading && searchResults.length > 0 && (
            <div className="app-cards-grid">
              {searchResults.map((company) => (
                <div key={company.id} className="app-card app-client-card">
                  <div className="app-client-card-header">
                    <h3 className="app-client-card-name">
                      {company.name}
                    </h3>
                    {company.verified && (
                      <span className="app-status-pill app-status-pill--connected">
                        Entreprise verifiee
                      </span>
                    )}
                  </div>
                  <div className="app-client-card-sub">
                    <span className="app-stat-box-value">SIREN :</span> {company.siren}
                  </div>
                  <div className="app-client-card-sub">
                    <span className="app-stat-box-value">Ville :</span> {company.city}
                  </div>
                  <div className="app-client-card-meta">
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
          <p className="app-desc">
            Vue d'ensemble de l'activite du reseau Factura. Ces chiffres sont mis a jour quotidiennement.
          </p>

          {statsLoading && (
            <div className="app-kpi-grid">
              {[1, 2, 3].map((i) => (
                <div key={i} className="app-skeleton app-skeleton-card" />
              ))}
            </div>
          )}

          {!statsLoading && !stats && (
            <div className="app-alert app-alert--error">
              Impossible de charger les statistiques du reseau. Veuillez reessayer.
            </div>
          )}

          {!statsLoading && stats && (
            <>
              {/* Cartes KPI */}
              <div className="app-kpi-grid">
                <div className="app-card app-kpi-card">
                  <h3 className="app-card-title">Total entreprises</h3>
                  <p className="app-card-value">
                    {stats.totalCompanies.toLocaleString('fr-FR')}
                  </p>
                </div>
                <div className="app-card app-kpi-card">
                  <h3 className="app-card-title">Factures echangees</h3>
                  <p className="app-card-value">
                    {stats.totalInvoices.toLocaleString('fr-FR')}
                  </p>
                </div>
                <div className="app-card app-kpi-card">
                  <h3 className="app-card-title">Volume total</h3>
                  <p className="app-card-value">
                    {formatEur(stats.totalVolumeEur)}
                  </p>
                </div>
              </div>

              {/* Croissance du reseau */}
              <div className="app-card">
                <h3 className="app-card-title">Croissance du reseau</h3>
                <div style={{ padding: '1.5rem', textAlign: 'center' }}>
                  <div style={{ display: 'flex', justifyContent: 'center', gap: '2rem', marginBottom: '1rem' }}>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--accent)' }}>{stats.totalCompanies}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Entreprises</div>
                    </div>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>{stats.totalInvoices}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Factures</div>
                    </div>
                    <div>
                      <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>{formatEur(stats.totalVolumeEur)}</div>
                      <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Volume</div>
                    </div>
                  </div>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text)', margin: 0 }}>
                    Le graphique de tendance apparaitra lorsque suffisamment de donnees seront collectees.
                  </p>
                </div>
              </div>
            </>
          )}
        </div>
      )}

      {/* Onglet Parrainage */}
      {activeTab === 'referral' && (
        <div>
          <p className="app-desc">
            Invitez d'autres entreprises a rejoindre Factura et gagnez des avantages
            pour chaque inscription validee.
          </p>

          {referralLoading && (
            <div>
              <div className="app-skeleton app-skeleton-table-row" />
              <div className="app-kpi-grid">
                <div className="app-skeleton app-skeleton-card" />
                <div className="app-skeleton app-skeleton-card" />
              </div>
              {[1, 2, 3].map((i) => (
                <div key={i} className="app-skeleton app-skeleton-table-row" />
              ))}
            </div>
          )}

          {!referralLoading && !referralData && (
            <div className="app-alert app-alert--error">
              Impossible de charger vos informations de parrainage. Veuillez reessayer.
            </div>
          )}

          {!referralLoading && referralData && (
            <>
              {/* Lien de parrainage */}
              <div className="app-card" style={{ marginBottom: '1.5rem' }}>
                <label className="app-label">Votre lien de parrainage</label>
                <div className="app-filter-bar">
                  <input
                    type="text"
                    value={referralData.referralLink}
                    readOnly
                    className="app-input"
                    style={{ background: 'var(--social-bg)' }}
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
              <div className="app-kpi-grid">
                <div className="app-card app-kpi-card">
                  <h3 className="app-card-title">Nombre de parrainages</h3>
                  <p className="app-card-value">{referralData.referralCount}</p>
                </div>
                <div className="app-card app-kpi-card">
                  <h3 className="app-card-title">Gains cumules</h3>
                  <p className="app-card-value">{formatEur(referralData.totalEarnings)}</p>
                </div>
              </div>

              {/* Liste des parrainages */}
              <h2 className="app-section-title">Historique des parrainages</h2>

              {referralData.referrals.length === 0 ? (
                <div className="app-empty">
                  <p className="app-empty-title">Aucun parrainage pour le moment</p>
                  <p className="app-empty-desc">
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
                            <td className="app-list-item-title">
                              {referral.referredName}
                            </td>
                            <td>
                              {referral.referredEmail}
                            </td>
                            <td>
                              {new Date(referral.createdAt).toLocaleDateString('fr-FR')}
                            </td>
                            <td>
                              <span
                                className="app-status-pill"
                                style={{ background: statusInfo.bg, color: statusInfo.color }}
                              >
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
