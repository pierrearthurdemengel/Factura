import { useState, useEffect, useRef } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
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
  suggestedInvoiceNumber: string | null;
  category: string | null;
  receiptId: string | null;
}

type BankTab = 'transactions' | 'reconciliation' | 'receipts' | 'connect';

// Categories disponibles pour la categorisation
const CATEGORIES = [
  'Fournitures bureau', 'Logiciel/SaaS', 'Deplacement', 'Restauration',
  'Honoraires', 'Loyer', 'Telecommunication', 'Assurance', 'Formation',
  'Marketing', 'Materiel informatique', 'Frais bancaires', 'Autre',
];

export default function Banking() {
  const [transactions, setTransactions] = useState<BankTransaction[]>([]);
  const [loading, setLoading] = useState(true);
  const [filter, setFilter] = useState<'all' | 'unreconciled' | 'reconciled'>('all');
  const [activeTab, setActiveTab] = useState<BankTab>('transactions');
  const [selectedTx, setSelectedTx] = useState<string | null>(null);
  const [connectStep, setConnectStep] = useState(0);
  const [dragOver, setDragOver] = useState(false);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const { success, error } = useToast();

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

  // Reconciliation manuelle
  const handleReconcile = async (txId: string, invoiceId: string) => {
    try {
      await api.post(`/bank/transactions/${txId}/reconcile`, { invoiceId });
      setTransactions((prev) => prev.map((t) => t.id === txId ? { ...t, reconciled: true } : t));
      success('Transaction reconciliee.');
    } catch {
      error('Erreur lors de la reconciliation.');
    }
  };

  // Categorisation manuelle
  const handleCategorize = async (txId: string, category: string) => {
    try {
      await api.post(`/bank/transactions/${txId}/categorize`, { category });
      setTransactions((prev) => prev.map((t) => t.id === txId ? { ...t, category } : t));
      success('Categorie mise a jour.');
    } catch {
      error('Erreur lors de la categorisation.');
    }
  };

  // Upload de justificatif
  const handleReceiptUpload = async (files: FileList | null, txId?: string) => {
    if (!files || files.length === 0) return;
    const formData = new FormData();
    formData.append('file', files[0]);
    if (txId) formData.append('transactionId', txId);
    try {
      await api.post('/receipts/upload', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      success('Justificatif envoye.');
    } catch {
      error("Erreur lors de l'envoi du justificatif.");
    }
  };

  const tabs: { key: BankTab; label: string }[] = [
    { key: 'transactions', label: 'Transactions' },
    { key: 'reconciliation', label: 'Reconciliation' },
    { key: 'receipts', label: 'Justificatifs' },
    { key: 'connect', label: 'Connexion' },
  ];

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

      {/* Onglet Transactions */}
      {activeTab === 'transactions' && (
        <>
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

          {filtered.length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>Aucune transaction</p>
              <p style={{ fontSize: '0.9rem' }}>Connectez votre banque via GoCardless pour synchroniser vos transactions.</p>
              <button className="app-btn-primary" style={{ marginTop: '1rem' }} onClick={() => setActiveTab('connect')}>
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
                    cursor: 'pointer', background: selectedTx === tx.id ? 'var(--accent-bg)' : undefined,
                  }}
                  onClick={() => setSelectedTx(selectedTx === tx.id ? null : tx.id)}
                >
                  <div style={{ flex: 1, minWidth: 200 }}>
                    <div style={{ fontWeight: 500, color: 'var(--text-h)', fontSize: '0.9rem' }}>{tx.label}</div>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.15rem' }}>
                      {new Date(tx.date).toLocaleDateString('fr-FR')}
                      {tx.category && <span style={{ marginLeft: '0.5rem', background: 'var(--accent-bg)', color: 'var(--accent)', padding: '1px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>{tx.category}</span>}
                      {tx.receiptId && <span style={{ marginLeft: '0.5rem', background: 'rgba(34,197,94,0.1)', color: '#22c55e', padding: '1px 6px', borderRadius: '4px', fontSize: '0.75rem' }}>Justificatif</span>}
                    </div>
                  </div>
                  <div style={{ fontWeight: 600, fontSize: '0.95rem', color: tx.type === 'credit' ? '#22c55e' : '#ef4444' }}>
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
        </>
      )}

      {/* Onglet Reconciliation */}
      {activeTab === 'reconciliation' && (
        <div>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Rapprochez vos transactions bancaires avec vos factures. Les suggestions automatiques sont affichees a droite.
          </p>
          {transactions.filter((t) => !t.reconciled).length === 0 ? (
            <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
              <p style={{ fontWeight: 600, color: 'var(--text-h)' }}>Toutes les transactions sont reconciliees</p>
            </div>
          ) : (
            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              {transactions.filter((t) => !t.reconciled).map((tx) => (
                <div key={tx.id} style={{ display: 'grid', gridTemplateColumns: '1fr auto 1fr', gap: '1rem', alignItems: 'center' }}>
                  {/* Transaction a gauche */}
                  <div className="app-card" style={{ padding: '1rem' }}>
                    <div style={{ fontWeight: 500, color: 'var(--text-h)', fontSize: '0.9rem' }}>{tx.label}</div>
                    <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>
                      {new Date(tx.date).toLocaleDateString('fr-FR')}
                    </div>
                    <div style={{ fontWeight: 600, fontSize: '1rem', marginTop: '0.5rem', color: tx.type === 'credit' ? '#22c55e' : '#ef4444' }}>
                      {tx.type === 'credit' ? '+' : '-'}{Math.abs(parseFloat(tx.amount)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                    </div>
                    {/* Selecteur de categorie */}
                    <select
                      value={tx.category || ''}
                      onChange={(e) => handleCategorize(tx.id, e.target.value)}
                      className="app-select"
                      style={{ marginTop: '0.5rem', fontSize: '0.8rem' }}
                    >
                      <option value="">Categoriser...</option>
                      {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                    </select>
                  </div>

                  {/* Fleche au centre */}
                  <div style={{ fontSize: '1.5rem', color: 'var(--accent)' }}>→</div>

                  {/* Facture suggeree a droite */}
                  <div className="app-card" style={{ padding: '1rem', borderColor: tx.suggestedInvoice ? 'var(--accent)' : undefined, borderWidth: tx.suggestedInvoice ? 2 : undefined }}>
                    {tx.suggestedInvoice ? (
                      <>
                        <div style={{ fontSize: '0.85rem', color: 'var(--text-h)', fontWeight: 600 }}>
                          Facture {tx.suggestedInvoiceNumber || tx.suggestedInvoice}
                        </div>
                        <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginTop: '0.25rem' }}>
                          Correspondance detectee automatiquement
                        </div>
                        <button
                          onClick={() => handleReconcile(tx.id, tx.suggestedInvoice!)}
                          className="app-btn-primary"
                          style={{ marginTop: '0.5rem', fontSize: '0.8rem', padding: '4px 12px' }}
                        >
                          Valider
                        </button>
                      </>
                    ) : (
                      <div style={{ color: 'var(--text)', fontSize: '0.85rem' }}>
                        Aucune facture suggeree
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>
      )}

      {/* Onglet Justificatifs */}
      {activeTab === 'receipts' && (
        <div>
          <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.9rem' }}>
            Importez vos justificatifs (tickets, factures fournisseur) et associez-les a vos transactions.
          </p>

          {/* Zone d'upload drag & drop */}
          <div
            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(e) => { e.preventDefault(); setDragOver(false); handleReceiptUpload(e.dataTransfer.files); }}
            onClick={() => fileInputRef.current?.click()}
            style={{
              border: `2px dashed ${dragOver ? 'var(--accent)' : 'var(--border)'}`,
              borderRadius: '12px', padding: '3rem 1rem', textAlign: 'center',
              cursor: 'pointer', marginBottom: '2rem',
              background: dragOver ? 'var(--accent-bg)' : 'transparent',
              transition: 'all 0.2s',
            }}
          >
            <input ref={fileInputRef} type="file" accept="image/*,.pdf" style={{ display: 'none' }} onChange={(e) => handleReceiptUpload(e.target.files)} />
            <div style={{ fontSize: '2rem', marginBottom: '0.5rem' }}>📎</div>
            <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.25rem' }}>
              Deposez vos justificatifs ici
            </p>
            <p style={{ fontSize: '0.85rem', color: 'var(--text)' }}>
              ou cliquez pour parcourir (PDF, images)
            </p>
          </div>

          {/* Liste des transactions avec lien justificatif */}
          <h3 style={{ fontSize: '1rem', fontWeight: 600, color: 'var(--text-h)', marginBottom: '1rem' }}>
            Associer un justificatif a une transaction
          </h3>
          <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
            {transactions.slice(0, 20).map((tx) => (
              <div key={tx.id} className="app-card" style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', gap: '0.5rem', flexWrap: 'wrap' }}>
                <div style={{ flex: 1, minWidth: 200 }}>
                  <div style={{ fontWeight: 500, color: 'var(--text-h)', fontSize: '0.85rem' }}>{tx.label}</div>
                  <div style={{ fontSize: '0.75rem', color: 'var(--text)' }}>{new Date(tx.date).toLocaleDateString('fr-FR')} — {Math.abs(parseFloat(tx.amount)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR</div>
                </div>
                {tx.receiptId ? (
                  <span style={{ fontSize: '0.8rem', color: '#22c55e', fontWeight: 600 }}>Justificatif lie</span>
                ) : (
                  <label style={{ fontSize: '0.8rem', color: 'var(--accent)', cursor: 'pointer', fontWeight: 500 }}>
                    <input type="file" accept="image/*,.pdf" style={{ display: 'none' }} onChange={(e) => handleReceiptUpload(e.target.files, tx.id)} />
                    + Ajouter
                  </label>
                )}
              </div>
            ))}
          </div>
        </div>
      )}

      {/* Onglet Connexion bancaire */}
      {activeTab === 'connect' && (
        <div style={{ maxWidth: 500 }}>
          <h2 className="app-section-title" style={{ marginTop: 0 }}>Connecter votre banque</h2>
          <p style={{ color: 'var(--text)', marginBottom: '2rem', fontSize: '0.9rem', lineHeight: 1.6 }}>
            Synchronisez automatiquement vos transactions bancaires via GoCardless (anciennement Nordigen).
            La connexion est securisee et conforme a la DSP2.
          </p>

          {/* Etapes du wizard */}
          <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
            {/* Etape 1 */}
            <div className="app-card" style={{ padding: '1.5rem', opacity: connectStep >= 0 ? 1 : 0.5 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.75rem' }}>
                <span style={{ width: 28, height: 28, borderRadius: '50%', background: connectStep >= 1 ? '#22c55e' : 'var(--accent)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.85rem', fontWeight: 700 }}>
                  {connectStep >= 1 ? '✓' : '1'}
                </span>
                <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>Choisir votre banque</span>
              </div>
              {connectStep === 0 && (
                <div>
                  <select className="app-select" style={{ marginBottom: '1rem' }}>
                    <option value="">Selectionnez votre banque</option>
                    <option value="bnp">BNP Paribas</option>
                    <option value="sg">Societe Generale</option>
                    <option value="ca">Credit Agricole</option>
                    <option value="cm">Credit Mutuel</option>
                    <option value="lcl">LCL</option>
                    <option value="bp">Banque Populaire</option>
                    <option value="ce">Caisse d'Epargne</option>
                    <option value="boursorama">Boursorama</option>
                    <option value="qonto">Qonto</option>
                    <option value="shine">Shine</option>
                  </select>
                  <button className="app-btn-primary" onClick={() => setConnectStep(1)}>
                    Continuer
                  </button>
                </div>
              )}
            </div>

            {/* Etape 2 */}
            <div className="app-card" style={{ padding: '1.5rem', opacity: connectStep >= 1 ? 1 : 0.5 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.75rem' }}>
                <span style={{ width: 28, height: 28, borderRadius: '50%', background: connectStep >= 2 ? '#22c55e' : connectStep >= 1 ? 'var(--accent)' : 'var(--border)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.85rem', fontWeight: 700 }}>
                  {connectStep >= 2 ? '✓' : '2'}
                </span>
                <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>Authentification DSP2</span>
              </div>
              {connectStep === 1 && (
                <div>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem' }}>
                    Vous allez etre redirige vers votre banque pour autoriser l'acces en lecture seule a vos comptes.
                  </p>
                  <button className="app-btn-primary" onClick={() => setConnectStep(2)}>
                    Autoriser l'acces
                  </button>
                </div>
              )}
            </div>

            {/* Etape 3 */}
            <div className="app-card" style={{ padding: '1.5rem', opacity: connectStep >= 2 ? 1 : 0.5 }}>
              <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: '0.75rem' }}>
                <span style={{ width: 28, height: 28, borderRadius: '50%', background: connectStep >= 3 ? '#22c55e' : connectStep >= 2 ? 'var(--accent)' : 'var(--border)', color: '#fff', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '0.85rem', fontWeight: 700 }}>
                  {connectStep >= 3 ? '✓' : '3'}
                </span>
                <span style={{ fontWeight: 600, color: 'var(--text-h)' }}>Synchronisation</span>
              </div>
              {connectStep === 2 && (
                <div>
                  <p style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem' }}>
                    Vos transactions seront synchronisees quotidiennement. La premiere synchronisation importe les 90 derniers jours.
                  </p>
                  <button className="app-btn-primary" onClick={() => { setConnectStep(3); success('Banque connectee avec succes !'); }}>
                    Lancer la synchronisation
                  </button>
                </div>
              )}
              {connectStep === 3 && (
                <p style={{ fontSize: '0.85rem', color: '#22c55e', fontWeight: 600 }}>
                  Connexion etablie. Vos transactions arrivent.
                </p>
              )}
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
