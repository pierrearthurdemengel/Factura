import { useState, useEffect } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
import './AppLayout.css';

// Types pour le journal comptable
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
}

// Onglets du module comptabilite
type AccountingTab = 'journal' | 'ledger' | 'balance' | 'fec';

export default function Accounting() {
  const [activeTab, setActiveTab] = useState<AccountingTab>('journal');
  const [entries, setEntries] = useState<AccountingEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [periodFilter, setPeriodFilter] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  });
  const { error: toastError } = useToast();

  useEffect(() => {
    const params = { period: periodFilter };
    const cached = getCached<{ 'hydra:member': AccountingEntry[] }>('/accounting/entries', params);
    if (cached) {
      queueMicrotask(() => {
        setEntries(cached['hydra:member'] || []);
        setLoading(false);
      });
    }
    api.get('/accounting/entries', { params })
      .then((res) => {
        setEntries(res.data['hydra:member'] || []);
        setCache('/accounting/entries', res.data, params);
      })
      .catch(() => toastError('Impossible de charger les ecritures comptables.'))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [periodFilter]);

  // Calcul du grand livre par compte
  const ledger = entries.reduce<Record<string, { label: string; totalDebit: number; totalCredit: number; entries: AccountingEntry[] }>>((acc, e) => {
    if (!acc[e.accountNumber]) {
      acc[e.accountNumber] = { label: e.accountLabel, totalDebit: 0, totalCredit: 0, entries: [] };
    }
    acc[e.accountNumber].totalDebit += parseFloat(e.debit || '0');
    acc[e.accountNumber].totalCredit += parseFloat(e.credit || '0');
    acc[e.accountNumber].entries.push(e);
    return acc;
  }, {});

  // Calcul de la balance
  const balance = Object.entries(ledger).map(([num, data]) => ({
    accountNumber: num,
    accountLabel: data.label,
    totalDebit: data.totalDebit,
    totalCredit: data.totalCredit,
    solde: data.totalDebit - data.totalCredit,
  })).sort((a, b) => a.accountNumber.localeCompare(b.accountNumber));

  const tabs: { key: AccountingTab; label: string }[] = [
    { key: 'journal', label: 'Journal' },
    { key: 'ledger', label: 'Grand livre' },
    { key: 'balance', label: 'Balance' },
    { key: 'fec', label: 'Export FEC' },
  ];

  const handleExportFec = async () => {
    try {
      const res = await api.get('/fec/export', {
        params: { year: periodFilter.split('-')[0] },
        responseType: 'blob',
      });
      const url = URL.createObjectURL(new Blob([res.data]));
      const a = document.createElement('a');
      a.href = url;
      a.download = `FEC_${periodFilter.split('-')[0]}.txt`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      // Erreur export FEC
    }
  };

  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      {[1, 2, 3, 4].map((i) => (
        <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginTop: '0.75rem' }} />
      ))}
    </div>
  );

  return (
    <div className="app-container">
      <div className="app-page-header">
        <h1 className="app-page-title">Comptabilite</h1>
        <input
          type="month"
          value={periodFilter}
          onChange={(e) => setPeriodFilter(e.target.value)}
          className="app-input"
          style={{ width: 'auto' }}
        />
      </div>

      {/* Onglets */}
      <div className="app-tabs">
        {tabs.map((tab) => (
          <button
            key={tab.key}
            onClick={() => setActiveTab(tab.key)}
            className={`app-tab${activeTab === tab.key ? ' app-tab--active' : ''}`}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Journal */}
      {activeTab === 'journal' && (
        entries.length === 0 ? (
          <div className="app-empty">
            <p className="app-empty-title">Aucune ecriture sur cette periode</p>
            <p className="app-empty-desc">Les ecritures sont generees automatiquement lors de l'emission des factures.</p>
          </div>
        ) : (
          <div className="app-table-wrapper">
            <table className="app-table">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Journal</th>
                  <th>Compte</th>
                  <th>Libelle</th>
                  <th className="text-right">Debit</th>
                  <th className="text-right">Credit</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id}>
                    <td>{new Date(entry.date).toLocaleDateString('fr-FR')}</td>
                    <td>
                      <span className="app-status-pill" style={{ background: 'var(--accent-bg)', color: 'var(--accent)' }}>
                        {entry.journalCode}
                      </span>
                    </td>
                    <td style={{ fontWeight: 500, color: 'var(--text-h)' }}>{entry.accountNumber} — {entry.accountLabel}</td>
                    <td>{entry.label}</td>
                    <td className="text-right" style={{ color: parseFloat(entry.debit) > 0 ? 'var(--text-h)' : undefined }}>
                      {parseFloat(entry.debit) > 0 ? parseFloat(entry.debit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) : ''}
                    </td>
                    <td className="text-right" style={{ color: parseFloat(entry.credit) > 0 ? 'var(--text-h)' : undefined }}>
                      {parseFloat(entry.credit) > 0 ? parseFloat(entry.credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) : ''}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )
      )}

      {/* Grand livre */}
      {activeTab === 'ledger' && (
        <div>
          {Object.keys(ledger).length === 0 ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucun compte sur cette periode</p>
            </div>
          ) : (
            Object.entries(ledger).sort(([a], [b]) => a.localeCompare(b)).map(([num, data]) => (
              <div key={num} className="app-card" style={{ marginBottom: '0.75rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                  <span className="app-list-item-title">{num} — {data.label}</span>
                  <span style={{
                    fontWeight: 600, fontSize: '0.9rem',
                    color: data.totalDebit - data.totalCredit >= 0 ? 'var(--text-h)' : 'var(--danger)',
                  }}>
                    Solde : {(data.totalDebit - data.totalCredit).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                  </span>
                </div>
                <div className="app-card-sub">
                  {data.entries.length} ecriture(s) — Debit : {data.totalDebit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} € — Credit : {data.totalCredit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Balance */}
      {activeTab === 'balance' && (
        balance.length === 0 ? (
          <div className="app-empty">
            <p className="app-empty-title">Aucune donnee de balance</p>
          </div>
        ) : (
          <div className="app-table-wrapper">
            <table className="app-table">
              <thead>
                <tr>
                  <th>Compte</th>
                  <th>Libelle</th>
                  <th className="text-right">Total debit</th>
                  <th className="text-right">Total credit</th>
                  <th className="text-right">Solde</th>
                </tr>
              </thead>
              <tbody>
                {balance.map((b) => (
                  <tr key={b.accountNumber}>
                    <td style={{ fontWeight: 600, color: 'var(--text-h)' }}>{b.accountNumber}</td>
                    <td>{b.accountLabel}</td>
                    <td className="text-right" style={{ color: 'var(--text-h)' }}>{b.totalDebit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td className="text-right" style={{ color: 'var(--text-h)' }}>{b.totalCredit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td className="text-right" style={{ fontWeight: 600, color: b.solde >= 0 ? 'var(--text-h)' : 'var(--danger)' }}>
                      {b.solde.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr style={{ fontWeight: 700 }}>
                  <td colSpan={2}>Total</td>
                  <td className="text-right">
                    {balance.reduce((s, b) => s + b.totalDebit, 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                  </td>
                  <td className="text-right">
                    {balance.reduce((s, b) => s + b.totalCredit, 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                  </td>
                  <td className="text-right">
                    {balance.reduce((s, b) => s + b.solde, 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                  </td>
                </tr>
              </tfoot>
            </table>
          </div>
        )
      )}

      {/* Export FEC */}
      {activeTab === 'fec' && (
        <div>
          <h2 className="app-section-title app-mt-0">Export FEC</h2>
          <p className="app-desc" style={{ maxWidth: 600 }}>
            Le Fichier des Ecritures Comptables (FEC) est un export obligatoire en cas de controle fiscal.
            Il contient toutes les ecritures comptables de l'exercice au format normalise.
          </p>
          <div className="app-card app-kpi-card" style={{ maxWidth: 400 }}>
            <div style={{ fontSize: '2.5rem', marginBottom: '0.75rem' }}>📊</div>
            <div className="app-list-item-title" style={{ marginBottom: '0.5rem' }}>
              FEC {periodFilter.split('-')[0]}
            </div>
            <div className="app-card-sub" style={{ marginBottom: '1rem' }}>
              {entries.length} ecriture(s) comptable(s)
            </div>
            <button onClick={handleExportFec} className="app-btn-primary">
              Telecharger le FEC
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
