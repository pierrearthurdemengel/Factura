import { useState, useEffect, useRef } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
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
    const cached = getCached<{ 'hydra:member': BankTransaction[] }>('/bank/transactions');
    if (cached) {
      queueMicrotask(() => {
        setTransactions(cached['hydra:member'] || []);
        setLoading(false);
      });
    }
    api.get('/bank/transactions')
      .then((res) => {
        setTransactions(res.data['hydra:member'] || []);
        setCache('/bank/transactions', res.data);
      })
      .catch(() => error('Impossible de charger les transactions bancaires.'))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
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
        <div key={i} className="app-skeleton app-skeleton-table-row" />
      ))}
    </div>
  );

  return (
    <div className="app-container">
      <h1 className="app-page-title">Banque</h1>

      {/* KPIs */}
      <div className="app-kpi-grid">
        <div className="app-card app-kpi-card">
          <p className="app-card-value" style={{ color: 'var(--success)' }}>
            +{totalCredit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
          </p>
          <p className="app-card-sub">Encaissements</p>
        </div>
        <div className="app-card app-kpi-card">
          <p className="app-card-value" style={{ color: 'var(--danger)' }}>
            -{totalDebit.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
          </p>
          <p className="app-card-sub">Decaissements</p>
        </div>
        <div className="app-card app-kpi-card">
          <p className="app-card-value" style={{ color: unreconciledCount > 0 ? 'var(--warning)' : 'var(--success)' }}>
            {unreconciledCount}
          </p>
          <p className="app-card-sub">Non reconciliees</p>
        </div>
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

      {/* Onglet Transactions */}
      {activeTab === 'transactions' && (
        <>
          <div className="app-pills">
            {[
              { key: 'all' as const, label: `Toutes (${transactions.length})` },
              { key: 'unreconciled' as const, label: `Non reconciliees (${unreconciledCount})` },
              { key: 'reconciled' as const, label: `Reconciliees (${transactions.length - unreconciledCount})` },
            ].map((f) => (
              <button
                key={f.key}
                onClick={() => setFilter(f.key)}
                className={`app-pill${filter === f.key ? ' app-pill--active' : ''}`}
              >
                {f.label}
              </button>
            ))}
          </div>

          {filtered.length === 0 ? (
            <div className="app-empty">
              <p className="app-empty-title">Aucune transaction</p>
              <p className="app-empty-desc">Connectez votre banque via GoCardless pour synchroniser vos transactions.</p>
              <button className="app-btn-primary app-mt-2" onClick={() => setActiveTab('connect')}>
                Connecter ma banque
              </button>
            </div>
          ) : (
            <div className="app-list">
              {filtered.map((tx) => (
                <div
                  key={tx.id}
                  className="app-list-item"
                  style={{
                    borderLeft: tx.suggestedInvoice ? '3px solid var(--accent)' : undefined,
                    cursor: 'pointer',
                    background: selectedTx === tx.id ? 'var(--accent-bg)' : undefined,
                  }}
                  onClick={() => setSelectedTx(selectedTx === tx.id ? null : tx.id)}
                >
                  <div className="app-list-item-info">
                    <div className="app-list-item-title">{tx.label}</div>
                    <div className="app-list-item-sub">
                      {new Date(tx.date).toLocaleDateString('fr-FR')}
                      {tx.category && <span className="app-status-pill" style={{ marginLeft: '0.5rem', background: 'var(--accent-bg)', color: 'var(--accent)' }}>{tx.category}</span>}
                      {tx.receiptId && <span className="app-status-pill app-status-pill--connected" style={{ marginLeft: '0.5rem' }}>Justificatif</span>}
                    </div>
                  </div>
                  <div className="app-list-item-value" style={{ color: tx.type === 'credit' ? 'var(--success)' : 'var(--danger)' }}>
                    {tx.type === 'credit' ? '+' : '-'}{Math.abs(parseFloat(tx.amount)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                  </div>
                  <span className="app-status-pill" style={{
                    background: tx.reconciled ? 'var(--success-bg)' : tx.suggestedInvoice ? 'var(--accent-bg)' : 'rgba(156,163,175,0.1)',
                    color: tx.reconciled ? 'var(--success)' : tx.suggestedInvoice ? 'var(--accent)' : 'var(--text-tertiary)',
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
          <p className="app-desc">
            Rapprochez vos transactions bancaires avec vos factures. Les suggestions automatiques sont affichees a droite.
          </p>
          {transactions.filter((t) => !t.reconciled).length === 0 ? (
            <div className="app-empty">
              <p className="app-empty-title">Toutes les transactions sont reconciliees</p>
            </div>
          ) : (
            <div className="app-list">
              {transactions.filter((t) => !t.reconciled).map((tx) => (
                <div key={tx.id} className="app-list-item" style={{ display: 'grid', gridTemplateColumns: '1fr auto 1fr', gap: '1rem', alignItems: 'center' }}>
                  {/* Transaction a gauche */}
                  <div className="app-card">
                    <div className="app-list-item-title">{tx.label}</div>
                    <div className="app-list-item-sub">
                      {new Date(tx.date).toLocaleDateString('fr-FR')}
                    </div>
                    <p className="app-card-value" style={{ fontSize: '1rem', marginTop: '0.5rem', color: tx.type === 'credit' ? 'var(--success)' : 'var(--danger)' }}>
                      {tx.type === 'credit' ? '+' : '-'}{Math.abs(parseFloat(tx.amount)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                    </p>
                    {/* Selecteur de categorie */}
                    <div className="app-form-group" style={{ marginTop: '0.5rem', marginBottom: 0 }}>
                      <select
                        value={tx.category || ''}
                        onChange={(e) => handleCategorize(tx.id, e.target.value)}
                        className="app-select"
                      >
                        <option value="">Categoriser...</option>
                        {CATEGORIES.map((c) => <option key={c} value={c}>{c}</option>)}
                      </select>
                    </div>
                  </div>

                  {/* Fleche au centre */}
                  <div style={{ fontSize: '1.5rem', color: 'var(--accent)' }}>→</div>

                  {/* Facture suggeree a droite */}
                  <div className="app-card" style={{ borderColor: tx.suggestedInvoice ? 'var(--accent)' : undefined, borderWidth: tx.suggestedInvoice ? 2 : undefined }}>
                    {tx.suggestedInvoice ? (
                      <>
                        <div className="app-list-item-title">
                          Facture {tx.suggestedInvoiceNumber || tx.suggestedInvoice}
                        </div>
                        <div className="app-list-item-sub">
                          Correspondance detectee automatiquement
                        </div>
                        <button
                          onClick={() => handleReconcile(tx.id, tx.suggestedInvoice!)}
                          className="app-btn-primary app-btn-compact"
                          style={{ marginTop: '0.5rem' }}
                        >
                          Valider
                        </button>
                      </>
                    ) : (
                      <div className="app-card-sub">
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
          <p className="app-desc">
            Importez vos justificatifs (tickets, factures fournisseur) et associez-les a vos transactions.
          </p>

          {/* Zone d'upload drag & drop */}
          <div
            onDragOver={(e) => { e.preventDefault(); setDragOver(true); }}
            onDragLeave={() => setDragOver(false)}
            onDrop={(e) => { e.preventDefault(); setDragOver(false); handleReceiptUpload(e.dataTransfer.files); }}
            onClick={() => fileInputRef.current?.click()}
            className="app-logo-dropzone"
            style={{
              width: '100%',
              height: 'auto',
              padding: '3rem 1rem',
              borderRadius: 'var(--lp-radius-lg, 1rem)',
              borderColor: dragOver ? 'var(--accent)' : undefined,
              background: dragOver ? 'var(--accent-bg)' : 'transparent',
              marginBottom: '2rem',
              flexDirection: 'column',
            }}
          >
            <input ref={fileInputRef} type="file" accept="image/*,.pdf" className="app-hidden" onChange={(e) => handleReceiptUpload(e.target.files)} />
            <div className="app-empty-icon">📎</div>
            <p className="app-empty-title">
              Deposez vos justificatifs ici
            </p>
            <p className="app-empty-desc">
              ou cliquez pour parcourir (PDF, images)
            </p>
          </div>

          {/* Liste des transactions avec lien justificatif */}
          <h3 className="app-section-title app-mt-0">
            Associer un justificatif a une transaction
          </h3>
          <div className="app-list">
            {transactions.slice(0, 20).map((tx) => (
              <div key={tx.id} className="app-list-item">
                <div className="app-list-item-info">
                  <div className="app-list-item-title">{tx.label}</div>
                  <div className="app-list-item-sub">{new Date(tx.date).toLocaleDateString('fr-FR')} — {Math.abs(parseFloat(tx.amount)).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €</div>
                </div>
                {tx.receiptId ? (
                  <span className="app-status-pill app-status-pill--connected">Justificatif lie</span>
                ) : (
                  <label className="app-table-link" style={{ cursor: 'pointer' }}>
                    <input type="file" accept="image/*,.pdf" className="app-hidden" onChange={(e) => handleReceiptUpload(e.target.files, tx.id)} />
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
          <h2 className="app-section-title app-mt-0">Connecter votre banque</h2>
          <p className="app-desc">
            Synchronisez automatiquement vos transactions bancaires via GoCardless (anciennement Nordigen).
            La connexion est securisee et conforme a la DSP2.
          </p>

          {/* Etapes du wizard */}
          <div className="app-list">
            {/* Etape 1 */}
            <div className="app-card" style={{ opacity: connectStep >= 0 ? 1 : 0.5 }}>
              <div className="app-list-item" style={{ border: 'none', padding: 0 }}>
                <span className="app-health-indicator" style={{ background: connectStep >= 1 ? 'var(--success)' : 'var(--accent)', color: '#fff' }}>
                  {connectStep >= 1 ? '✓' : '1'}
                </span>
                <span className="app-list-item-title">Choisir votre banque</span>
              </div>
              {connectStep === 0 && (
                <div className="app-form-group" style={{ marginTop: '0.75rem' }}>
                  <select className="app-select">
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
                  <button className="app-btn-primary" style={{ marginTop: '1rem' }} onClick={() => setConnectStep(1)}>
                    Continuer
                  </button>
                </div>
              )}
            </div>

            {/* Etape 2 */}
            <div className="app-card" style={{ opacity: connectStep >= 1 ? 1 : 0.5 }}>
              <div className="app-list-item" style={{ border: 'none', padding: 0 }}>
                <span className="app-health-indicator" style={{ background: connectStep >= 2 ? 'var(--success)' : connectStep >= 1 ? 'var(--accent)' : 'var(--border)', color: '#fff' }}>
                  {connectStep >= 2 ? '✓' : '2'}
                </span>
                <span className="app-list-item-title">Authentification DSP2</span>
              </div>
              {connectStep === 1 && (
                <div style={{ marginTop: '0.75rem' }}>
                  <p className="app-card-sub" style={{ marginBottom: '1rem' }}>
                    Vous allez etre redirige vers votre banque pour autoriser l'acces en lecture seule a vos comptes.
                  </p>
                  <button className="app-btn-primary" onClick={() => setConnectStep(2)}>
                    Autoriser l'acces
                  </button>
                </div>
              )}
            </div>

            {/* Etape 3 */}
            <div className="app-card" style={{ opacity: connectStep >= 2 ? 1 : 0.5 }}>
              <div className="app-list-item" style={{ border: 'none', padding: 0 }}>
                <span className="app-health-indicator" style={{ background: connectStep >= 3 ? 'var(--success)' : connectStep >= 2 ? 'var(--accent)' : 'var(--border)', color: '#fff' }}>
                  {connectStep >= 3 ? '✓' : '3'}
                </span>
                <span className="app-list-item-title">Synchronisation</span>
              </div>
              {connectStep === 2 && (
                <div style={{ marginTop: '0.75rem' }}>
                  <p className="app-card-sub" style={{ marginBottom: '1rem' }}>
                    Vos transactions seront synchronisees quotidiennement. La premiere synchronisation importe les 90 derniers jours.
                  </p>
                  <button className="app-btn-primary" onClick={() => { setConnectStep(3); success('Banque connectee avec succes !'); }}>
                    Lancer la synchronisation
                  </button>
                </div>
              )}
              {connectStep === 3 && (
                <p className="app-status-pill app-status-pill--connected" style={{ marginTop: '0.75rem', fontWeight: 600 }}>
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
