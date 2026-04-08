import { useState, useEffect } from 'react';
import api from '../api/factura';
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

  useEffect(() => {
    api.get('/accounting/entries', { params: { period: periodFilter } })
      .then((res) => setEntries(res.data['hydra:member'] || []))
      .catch(() => {/* Pas d'ecritures */})
      .finally(() => setLoading(false));
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
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '1.5rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Comptabilite</h1>
        <input
          type="month"
          value={periodFilter}
          onChange={(e) => setPeriodFilter(e.target.value)}
          className="app-input"
          style={{ width: 'auto' }}
        />
      </div>

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
              borderRadius: '6px', cursor: 'pointer', fontWeight: activeTab === tab.key ? 600 : 400,
              fontSize: '0.9rem', whiteSpace: 'nowrap',
            }}
          >
            {tab.label}
          </button>
        ))}
      </div>

      {/* Journal */}
      {activeTab === 'journal' && (
        <div style={{ overflowX: 'auto' }}>
          {entries.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucune ecriture sur cette periode</p>
              <p style={{ fontSize: '0.9rem' }}>Les ecritures sont generees automatiquement lors de l'emission des factures.</p>
            </div>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
              <thead>
                <tr style={{ borderBottom: '2px solid var(--border)', textAlign: 'left' }}>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Date</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Journal</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Compte</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Libelle</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Debit</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Credit</th>
                </tr>
              </thead>
              <tbody>
                {entries.map((entry) => (
                  <tr key={entry.id} style={{ borderBottom: '1px solid var(--border)' }}>
                    <td style={{ padding: '0.5rem', color: 'var(--text)' }}>{new Date(entry.date).toLocaleDateString('fr-FR')}</td>
                    <td style={{ padding: '0.5rem' }}>
                      <span style={{ background: 'var(--accent-bg)', color: 'var(--accent)', padding: '2px 6px', borderRadius: '4px', fontSize: '0.8rem', fontWeight: 600 }}>
                        {entry.journalCode}
                      </span>
                    </td>
                    <td style={{ padding: '0.5rem', color: 'var(--text-h)', fontWeight: 500 }}>{entry.accountNumber} — {entry.accountLabel}</td>
                    <td style={{ padding: '0.5rem', color: 'var(--text)' }}>{entry.label}</td>
                    <td style={{ padding: '0.5rem', textAlign: 'right', color: parseFloat(entry.debit) > 0 ? 'var(--text-h)' : 'var(--text)' }}>
                      {parseFloat(entry.debit) > 0 ? parseFloat(entry.debit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) : ''}
                    </td>
                    <td style={{ padding: '0.5rem', textAlign: 'right', color: parseFloat(entry.credit) > 0 ? 'var(--text-h)' : 'var(--text)' }}>
                      {parseFloat(entry.credit) > 0 ? parseFloat(entry.credit).toLocaleString('fr-FR', { minimumFractionDigits: 2 }) : ''}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      )}

      {/* Grand livre */}
      {activeTab === 'ledger' && (
        <div>
          {Object.keys(ledger).length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucun compte sur cette periode</p>
            </div>
          ) : (
            Object.entries(ledger).sort(([a], [b]) => a.localeCompare(b)).map(([num, data]) => (
              <div key={num} className="app-card" style={{ marginBottom: '0.75rem' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '0.5rem' }}>
                  <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>{num} — {data.label}</span>
                  <span style={{
                    fontWeight: 600, fontSize: '0.9rem',
                    color: data.totalDebit - data.totalCredit >= 0 ? 'var(--text-h)' : '#ef4444',
                  }}>
                    Solde : {(data.totalDebit - data.totalCredit).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                  </span>
                </div>
                <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>
                  {data.entries.length} ecriture(s) — Debit : {data.totalDebit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR — Credit : {data.totalCredit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                </div>
              </div>
            ))
          )}
        </div>
      )}

      {/* Balance */}
      {activeTab === 'balance' && (
        <div style={{ overflowX: 'auto' }}>
          {balance.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Aucune donnee de balance</p>
            </div>
          ) : (
            <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.85rem' }}>
              <thead>
                <tr style={{ borderBottom: '2px solid var(--border)', textAlign: 'left' }}>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Compte</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Libelle</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Total debit</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Total credit</th>
                  <th style={{ padding: '0.5rem', color: 'var(--text-h)', textAlign: 'right' }}>Solde</th>
                </tr>
              </thead>
              <tbody>
                {balance.map((b) => (
                  <tr key={b.accountNumber} style={{ borderBottom: '1px solid var(--border)' }}>
                    <td style={{ padding: '0.5rem', fontWeight: 600, color: 'var(--text-h)' }}>{b.accountNumber}</td>
                    <td style={{ padding: '0.5rem', color: 'var(--text)' }}>{b.accountLabel}</td>
                    <td style={{ padding: '0.5rem', textAlign: 'right', color: 'var(--text-h)' }}>{b.totalDebit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td style={{ padding: '0.5rem', textAlign: 'right', color: 'var(--text-h)' }}>{b.totalCredit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}</td>
                    <td style={{ padding: '0.5rem', textAlign: 'right', fontWeight: 600, color: b.solde >= 0 ? 'var(--text-h)' : '#ef4444' }}>
                      {b.solde.toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                    </td>
                  </tr>
                ))}
              </tbody>
              <tfoot>
                <tr style={{ borderTop: '2px solid var(--border)', fontWeight: 700 }}>
                  <td colSpan={2} style={{ padding: '0.5rem', color: 'var(--text-h)' }}>Total</td>
                  <td style={{ padding: '0.5rem', textAlign: 'right', color: 'var(--text-h)' }}>
                    {balance.reduce((s, b) => s + b.totalDebit, 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                  </td>
                  <td style={{ padding: '0.5rem', textAlign: 'right', color: 'var(--text-h)' }}>
                    {balance.reduce((s, b) => s + b.totalCredit, 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                  </td>
                  <td style={{ padding: '0.5rem', textAlign: 'right', color: 'var(--text-h)' }}>
                    {balance.reduce((s, b) => s + b.solde, 0).toLocaleString('fr-FR', { minimumFractionDigits: 2 })}
                  </td>
                </tr>
              </tfoot>
            </table>
          )}
        </div>
      )}

      {/* Export FEC */}
      {activeTab === 'fec' && (
        <div>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Export FEC</h2>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem', maxWidth: 600, lineHeight: 1.6 }}>
            Le Fichier des Ecritures Comptables (FEC) est un export obligatoire en cas de controle fiscal.
            Il contient toutes les ecritures comptables de l'exercice au format normalise.
          </p>
          <div className="app-card" style={{ maxWidth: 400, padding: '1.5rem', textAlign: 'center' }}>
            <div style={{ fontSize: '2.5rem', marginBottom: '0.75rem' }}>📊</div>
            <div style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>
              FEC {periodFilter.split('-')[0]}
            </div>
            <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem' }}>
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
