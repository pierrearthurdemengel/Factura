import { useState, useEffect, useMemo } from 'react';
import { useNavigate } from 'react-router-dom';
import { createQuote, getClients, type Client } from '../api/factura';
import { useToast } from '../context/ToastContext';
import DatePicker from '../components/DatePicker';
import './AppLayout.css';

interface LineForm {
  id: string;
  description: string;
  quantity: string;
  unit: string;
  unitPriceExcludingTax: string;
  vatRate: string;
}

export default function QuoteCreate() {
  const navigate = useNavigate();
  const [clients, setClients] = useState<Client[]>([]);
  const [selectedClient, setSelectedClient] = useState('');
  const [issueDate, setIssueDate] = useState(new Date().toISOString().split('T')[0]);
  const [validUntil, setValidUntil] = useState(() => {
    const d = new Date();
    d.setDate(d.getDate() + 30);
    return d.toISOString().split('T')[0];
  });
  const [legalMention, setLegalMention] = useState('');
  const [paymentTerms, setPaymentTerms] = useState('');
  const [depositEnabled, setDepositEnabled] = useState(false);
  const [depositPercent, setDepositPercent] = useState('30');
  const [lines, setLines] = useState<LineForm[]>([
    { id: crypto.randomUUID(), description: '', quantity: '1', unit: 'EA', unitPriceExcludingTax: '', vatRate: '20' },
  ]);
  const { error, success } = useToast();

  useEffect(() => {
    getClients()
      .then((res) => setClients(res.data['hydra:member']))
      .catch(() => setClients([]));
  }, []);

  const computeLineTotal = (line: LineForm) => {
    const qty = parseFloat(line.quantity) || 0;
    const price = parseFloat(line.unitPriceExcludingTax) || 0;
    const ht = qty * price;
    const vat = isNaN(parseFloat(line.vatRate)) ? 0 : ht * parseFloat(line.vatRate) / 100;
    return { ht, vat, ttc: ht + vat };
  };

  const totals = lines.reduce(
    (acc, line) => {
      const t = computeLineTotal(line);
      return { ht: acc.ht + t.ht, vat: acc.vat + t.vat, ttc: acc.ttc + t.ttc };
    },
    { ht: 0, vat: 0, ttc: 0 },
  );

  const addLine = () => {
    setLines([...lines, { id: crypto.randomUUID(), description: '', quantity: '1', unit: 'EA', unitPriceExcludingTax: '', vatRate: '20' }]);
  };

  const removeLine = (id: string) => {
    setLines(lines.filter(l => l.id !== id));
  };

  const updateLine = (id: string, field: keyof LineForm, value: string) => {
    setLines(lines.map(l => l.id === id ? { ...l, [field]: value } : l));
  };

  const completionRate = useMemo(() => {
    let score = 0;
    if (selectedClient) score += 35;
    if (issueDate) score += 15;
    if (lines.length > 0 && lines[0].description && lines[0].unitPriceExcludingTax) score += 50;
    return score;
  }, [selectedClient, issueDate, lines]);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    try {
      const data: Record<string, unknown> = {
        buyer: selectedClient,
        issueDate,
        validUntil: validUntil || undefined,
        legalMention: legalMention || undefined,
        paymentTerms: paymentTerms || undefined,
        lines: lines.map((line, i) => ({
          position: i + 1,
          description: line.description,
          quantity: line.quantity,
          unit: line.unit,
          unitPriceExcludingTax: line.unitPriceExcludingTax,
          vatRate: line.vatRate,
        })),
      };
      if (depositEnabled) {
        const pct = parseFloat(depositPercent) || 0;
        data.depositPercent = pct;
        data.depositAmount = (totals.ttc * pct / 100).toFixed(2);
      }
      await createQuote(data);
      success('Devis cree avec succes.');
      navigate('/quotes');
    } catch {
      error('Erreur lors de la creation du devis.');
    }
  };

  return (
    <div className="app-container">
      <h1 className="app-page-title">Nouveau devis</h1>

      <form onSubmit={handleSubmit} style={{ maxWidth: 700 }}>
        <div className="app-form-group">
          <label className="app-label">Client</label>
          <select value={selectedClient} onChange={(e) => setSelectedClient(e.target.value)} required className="app-select">
            <option value="">Selectionner un client</option>
            {clients.map((c) => (
              <option key={c.id} value={c['@id']}>{c.name}</option>
            ))}
          </select>
        </div>

        <div className="app-form-row">
          <div className="app-form-group">
            <label className="app-label">Date d'emission</label>
            <DatePicker value={issueDate} onChange={setIssueDate} />
          </div>
          <div className="app-form-group">
            <label className="app-label">Valide jusqu'au</label>
            <DatePicker value={validUntil} onChange={setValidUntil} />
          </div>
        </div>

        <h2 className="app-section-title">Lignes</h2>

        {lines.map((line) => (
          <div key={line.id} className="app-card app-mb-2">
            <div className="app-form-row">
              <div className="app-form-group flex-2">
                <label className="app-label">Description</label>
                <input type="text" value={line.description} onChange={(e) => updateLine(line.id, 'description', e.target.value)} required className="app-input" />
              </div>
              <div className="app-form-group">
                <label className="app-label">Quantite</label>
                <input type="number" step="0.01" value={line.quantity} onChange={(e) => updateLine(line.id, 'quantity', e.target.value)} required className="app-input" />
              </div>
              <div className="app-form-group">
                <label className="app-label">Unite</label>
                <select value={line.unit} onChange={(e) => updateLine(line.id, 'unit', e.target.value)} className="app-select">
                  <option value="EA">Unite</option>
                  <option value="HUR">Heure</option>
                  <option value="DAY">Jour</option>
                </select>
              </div>
            </div>
            <div className="app-form-row" style={{ alignItems: 'flex-end', marginTop: '0.75rem' }}>
              <div className="app-form-group">
                <label className="app-label">Prix HT</label>
                <input type="number" step="0.01" value={line.unitPriceExcludingTax} onChange={(e) => updateLine(line.id, 'unitPriceExcludingTax', e.target.value)} required className="app-input" />
              </div>
              <div className="app-form-group">
                <label className="app-label">TVA %</label>
                <select value={line.vatRate} onChange={(e) => updateLine(line.id, 'vatRate', e.target.value)} className="app-select">
                  <option value="20">20%</option>
                  <option value="10">10%</option>
                  <option value="5.5">5.5%</option>
                  <option value="2.1">2.1%</option>
                  <option value="0">0% (exoneration)</option>
                </select>
              </div>
              <div className="app-form-group" style={{ alignItems: 'flex-end' }}>
                <p className="app-label app-mt-0">{computeLineTotal(line).ht.toFixed(2)} EUR HT</p>
                <button type="button" onClick={() => removeLine(line.id)} disabled={lines.length === 1} className="app-btn-outline-danger">
                  Supprimer
                </button>
              </div>
            </div>
          </div>
        ))}

        <button type="button" onClick={addLine} className="app-btn-outline" style={{ marginBottom: '1.5rem' }}>
          + Ajouter une ligne
        </button>

        {/* Acompte */}
        <div className="app-card" style={{ marginBottom: '1.5rem' }}>
          <div style={{ display: 'flex', alignItems: 'center', gap: '0.75rem', marginBottom: depositEnabled ? '1rem' : 0 }}>
            <input
              type="checkbox"
              id="deposit-toggle"
              checked={depositEnabled}
              onChange={(e) => setDepositEnabled(e.target.checked)}
              style={{ width: 18, height: 18, accentColor: 'var(--accent)' }}
            />
            <label htmlFor="deposit-toggle" className="app-label" style={{ margin: 0, cursor: 'pointer' }}>
              Demander un acompte
            </label>
          </div>
          {depositEnabled && (
            <div className="app-form-row">
              <div className="app-form-group">
                <label className="app-label">Pourcentage</label>
                <select
                  value={depositPercent}
                  onChange={(e) => setDepositPercent(e.target.value)}
                  className="app-select"
                >
                  <option value="10">10%</option>
                  <option value="20">20%</option>
                  <option value="30">30%</option>
                  <option value="40">40%</option>
                  <option value="50">50%</option>
                </select>
              </div>
              <div className="app-form-group">
                <label className="app-label">Montant de l'acompte TTC</label>
                <p className="app-card-value" style={{ margin: 0 }}>
                  {(totals.ttc * (parseFloat(depositPercent) || 0) / 100).toFixed(2)} EUR
                </p>
              </div>
              <div className="app-form-group">
                <label className="app-label">Solde restant TTC</label>
                <p className="app-card-value" style={{ margin: 0 }}>
                  {(totals.ttc - totals.ttc * (parseFloat(depositPercent) || 0) / 100).toFixed(2)} EUR
                </p>
              </div>
            </div>
          )}
        </div>

        <div className="app-form-group">
          <label className="app-label">Conditions de paiement</label>
          <input type="text" value={paymentTerms} onChange={(e) => setPaymentTerms(e.target.value)} placeholder="Paiement a 30 jours fin de mois" className="app-input" />
        </div>

        <div className="app-form-group">
          <label className="app-label">Mention legale</label>
          <textarea value={legalMention} onChange={(e) => setLegalMention(e.target.value)} placeholder="TVA non applicable - art. 293 B du CGI" className="app-input" style={{ resize: 'vertical', minHeight: '80px' }} />
        </div>

        <div className="app-card app-totals" style={{ background: 'var(--social-bg)' }}>
          <p>Total HT : <strong>{totals.ht.toFixed(2)} EUR</strong></p>
          <p>Total TVA : <strong>{totals.vat.toFixed(2)} EUR</strong></p>
          <p className="app-totals-grand">Total TTC : <strong>{totals.ttc.toFixed(2)} EUR</strong></p>
        </div>

        {/* Barre de progression */}
        <div className="app-bottom-bar-progress" style={{ marginBottom: '1rem' }}>
          <div className="app-bottom-bar-label">
            <span>Progression ({completionRate}%)</span>
            <span>{completionRate === 100 ? 'Pret a valider' : 'Champs manquants'}</span>
          </div>
          <div className="app-progress">
            <div className="app-progress-fill" style={{ width: `${completionRate}%` }} />
          </div>
        </div>

        <div className="app-actions-row">
          <button type="button" onClick={() => navigate('/quotes')} className="app-btn-outline">
            Annuler
          </button>
          <button type="submit" disabled={completionRate < 100} className="app-btn-primary">
            Creer le devis
          </button>
        </div>
      </form>
    </div>
  );
}
