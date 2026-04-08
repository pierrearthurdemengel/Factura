import { useState, useEffect } from 'react';
import api from '../api/factura';
import './AppLayout.css';

// Types pour les transactions bancaires
interface BankTransaction {
  id: string;
  date: string;
  label: string;
  amount: string;
  type: 'credit' | 'debit';
  reconciled: boolean;
  suggestedInvoice: string | null;
  category: string | null;
}

export default function Banking() {
  const [transactions, setTransactions] = useState<BankTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'unreconciled' | 'reconciled'>('all');

  useEffect(() => {
    api.get('/bank/transactions')
      .then((res) => setTransactions(res.data['hydra:member'] || []))
      .catch(() => {/* Pas de transactions */})
      .finally(() => setLoading(false));
  }, []);

  const filtered = filter === 'all' ? transactions
    : filter === 'reconciled' ? transactions.filter((t) => t.reconciled)
    : transactions.filter((t) => !t.reconciled);

  const totalCredit = transactions.filter((t) => t.type === 'credit').reduce((s, t) => s + parseFloat(t.amount), 0);
  const totalDebit = transactions.filter((t) => t.type === 'debit').reduce((s, t) => s + Math.abs(parseFloat(t.amount)), 0);
  const unreconciledCount = transactions.filter((t) => !t.reconciled).length;

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
      <h1 className="app-page-title">Banque</h1>

      {/* KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div className="app-card" style={{ textAlign: 'center', padding: '1rem' }}>
          <div style={{ fontSize: '1.3rem', fontWeight: 700, color: '#22c55e' }}>
            +{totalCredit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
          </div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Encaissements</div>
        </div>
        <div className="app-card" style={{ textAlign: 'center', padding: '1rem' }}>
          <div style={{ fontSize: '1.3rem', fontWeight: 700, color: '#ef4444' }}>
            -{totalDebit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
          </div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Decaissements</div>
        </div>
        <div className="app-card" style={{ textAlign: 'center', padding: '1rem' }}>
          <div style={{ fontSize: '1.3rem', fontWeight: 700, color: unreconciledCount > 0 ? '#f59e0b' : '#22c55e' }}>
            {unreconciledCount}
          </div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Non reconciliees</div>
        </div>
      </div>

      {/* Filtres */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem' }}>
        {[
          { key: 'all' as const, label: `Toutes (${transactions.length})` },
          { key: 'unreconciled' as const, label: `Non reconciliees (${unreconciledCount})` },
          { key: 'reconciled' as const, label: `Reconciliees (${transactions.length - unreconciledCount})` },
        ].map((f) => (
          <button
            key={f.key}
            onClick={() => setFilter(f.key)}
            style={{
              padding: '4px 12px', borderRadius: '1rem', border: 'none', cursor: 'pointer',
              background: filter === f.key ? 'var(--accent)' : 'var(--surface)',
              color: filter === f.key ? '#fff' : 'var(--text)', fontSize: '0.85rem',
            }}
          >
            {f.label}
          </button>
        ))}
      </div>

      {/* Liste des transactions */}
      {filtered.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
          <div style={{ fontSize: '2rem', marginBottom: '1rem' }}>🏦</div>
          <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>Aucune transaction</p>
          <p style={{ fontSize: '0.9rem' }}>Connectez votre banque via GoCardless pour synchroniser vos transactions.</p>
          <button className="app-btn-primary" style={{ marginTop: '1rem' }}>
            Connecter ma banque
          </button>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
          {filtered.map((tx) => (
            <div
              key={tx.id}
              className="app-card"
              style={{
                display: 'flex', justifyContent: 'space-between', alignItems: 'center',
                flexWrap: 'wrap', gap: '0.5rem',
                borderLeft: tx.suggestedInvoice ? '3px solid var(--accent)' : undefined,
              }}
            >
              <div style={{ flex: 1, minWidth: 200 }}>
                <div style={{ fontWeight: 500, color: 'var(--text-h)', fontSize: '0.9rem' }}>{tx.label}</div>
                <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.15rem' }}>
                  {new Date(tx.date).toLocaleDateString('fr-FR')}
                  {tx.category && <span style={{ marginLeft: '0.5rem', background: 'var(--accent-bg)', color: 'var(--accent)', padding: '1px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>{tx.category}</span>}
                </div>
              </div>
              <div style={{
                fontWeight: 600, fontSize: '0.95rem',
                color: tx.type === 'credit' ? '#22c55e' : '#ef4444',
              }}>
                {tx.type === 'credit' ? '+' : '-'}{Math.abs(parseFloat(tx.amount)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
              </div>
              <span style={{
                padding: '3px 8px', borderRadius: '1rem', fontSize: '0.75rem', fontWeight: 600,
                background: tx.reconciled ? 'rgba(34,197,94,0.1)' : tx.suggestedInvoice ? 'rgba(37,99,235,0.1)' : 'rgba(156,163,175,0.1)',
                color: tx.reconciled ? '#22c55e' : tx.suggestedInvoice ? '#2563eb' : '#9ca3af',
              }}>
                {tx.reconciled ? 'Reconciliee' : tx.suggestedInvoice ? 'Suggestion' : 'Non reconciliee'}
              </span>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
