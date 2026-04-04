import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { createInvoice, getClients, type Client } from '../api/factura';

interface LineForm {
  description: string;
  quantity: string;
  unit: string;
  unitPriceExcludingTax: string;
  vatRate: string;
}

// Formulaire de creation de facture avec calcul automatique HT/TVA/TTC.
export default function InvoiceCreate() {
  const navigate = useNavigate();
  const [clients, setClients] = useState<Client[]>([]);
  const [selectedClient, setSelectedClient] = useState('');
  const [issueDate, setIssueDate] = useState(new Date().toISOString().split('T')[0]);
  const [dueDate, setDueDate] = useState('');
  const [legalMention, setLegalMention] = useState('');
  const [paymentTerms, setPaymentTerms] = useState('');
  const [showPreview, setShowPreview] = useState(false);
  const [lines, setLines] = useState<LineForm[]>([
    { description: '', quantity: '1', unit: 'EA', unitPriceExcludingTax: '', vatRate: '20' },
  ]);
  const [error, setError] = useState('');

  useEffect(() => {
    getClients()
      .then((res) => setClients(res.data['hydra:member']))
      .catch(() => setClients([]));
  }, []);

  // Calcul automatique des totaux
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
    setLines([...lines, { description: '', quantity: '1', unit: 'EA', unitPriceExcludingTax: '', vatRate: '20' }]);
  };

  const removeLine = (index: number) => {
    setLines(lines.filter((_, i) => i !== index));
  };

  const updateLine = (index: number, field: keyof LineForm, value: string) => {
    const updated = [...lines];
    updated[index] = { ...updated[index], [field]: value };
    setLines(updated);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    try {
      const data = {
        buyer: selectedClient,
        issueDate,
        dueDate: dueDate || undefined,
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

      await createInvoice(data);
      navigate('/invoices');
    } catch {
      setError('Erreur lors de la creation de la facture.');
    }
  };

  return (
    <div>
      <h1>Nouvelle facture</h1>

      {error && <p style={{ color: 'red' }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <div style={{ marginBottom: '20px' }}>
          <label>Client</label>
          <select value={selectedClient} onChange={(e) => setSelectedClient(e.target.value)} required>
            <option value="">Selectionner un client</option>
            {clients.map((c) => (
              <option key={c.id} value={c['@id']}>{c.name}</option>
            ))}
          </select>
        </div>

        <div style={{ display: 'flex', gap: '20px', marginBottom: '20px' }}>
          <div>
            <label>Date d'emission</label>
            <input type="date" value={issueDate} onChange={(e) => setIssueDate(e.target.value)} required />
          </div>
          <div>
            <label>Date d'echeance</label>
            <input type="date" value={dueDate} onChange={(e) => setDueDate(e.target.value)} />
          </div>
        </div>

        <h2>Lignes</h2>
        {lines.map((line, index) => (
          <div key={index} style={{ display: 'flex', gap: '10px', marginBottom: '10px', alignItems: 'end' }}>
            <div style={{ flex: 3 }}>
              <label>Description</label>
              <input
                type="text"
                value={line.description}
                onChange={(e) => updateLine(index, 'description', e.target.value)}
                required
                style={{ width: '100%' }}
              />
            </div>
            <div style={{ flex: 1 }}>
              <label>Quantite</label>
              <input
                type="number"
                step="0.01"
                value={line.quantity}
                onChange={(e) => updateLine(index, 'quantity', e.target.value)}
                required
              />
            </div>
            <div style={{ flex: 1 }}>
              <label>Unite</label>
              <select value={line.unit} onChange={(e) => updateLine(index, 'unit', e.target.value)}>
                <option value="EA">Unite</option>
                <option value="HUR">Heure</option>
                <option value="DAY">Jour</option>
              </select>
            </div>
            <div style={{ flex: 1 }}>
              <label>Prix HT</label>
              <input
                type="number"
                step="0.01"
                value={line.unitPriceExcludingTax}
                onChange={(e) => updateLine(index, 'unitPriceExcludingTax', e.target.value)}
                required
              />
            </div>
            <div style={{ flex: 1 }}>
              <label>TVA %</label>
              <select value={line.vatRate} onChange={(e) => updateLine(index, 'vatRate', e.target.value)}>
                <option value="20">20%</option>
                <option value="10">10%</option>
                <option value="5.5">5.5%</option>
                <option value="2.1">2.1%</option>
                <option value="0">0% (exoneration / autoliquidation)</option>
              </select>
            </div>
            <div style={{ flex: 1 }}>
              <p>{computeLineTotal(line).ht.toFixed(2)} EUR HT</p>
            </div>
            <button type="button" onClick={() => removeLine(index)} disabled={lines.length === 1}>
              Supprimer
            </button>
          </div>
        ))}

        <button type="button" onClick={addLine} style={{ marginBottom: '20px' }}>
          Ajouter une ligne
        </button>

        <div style={{ marginBottom: '20px' }}>
          <label>Conditions de paiement</label>
          <input
            type="text"
            value={paymentTerms}
            onChange={(e) => setPaymentTerms(e.target.value)}
            placeholder="Paiement a 30 jours fin de mois"
            style={{ width: '100%', padding: '8px', boxSizing: 'border-box' }}
          />
        </div>

        <div style={{ marginBottom: '20px' }}>
          <label>Mention legale</label>
          <textarea
            value={legalMention}
            onChange={(e) => setLegalMention(e.target.value)}
            placeholder="TVA non applicable - art. 293 B du CGI"
            style={{ width: '100%' }}
          />
        </div>

        <div style={{ padding: '15px', backgroundColor: '#f9fafb', borderRadius: '8px', marginBottom: '20px' }}>
          <p>Total HT : <strong>{totals.ht.toFixed(2)} EUR</strong></p>
          <p>Total TVA : <strong>{totals.vat.toFixed(2)} EUR</strong></p>
          <p>Total TTC : <strong>{totals.ttc.toFixed(2)} EUR</strong></p>
        </div>

        <div style={{ display: 'flex', gap: '12px', marginBottom: '20px' }}>
          <button type="submit" style={{ padding: '10px 20px', cursor: 'pointer' }}>
            Creer la facture
          </button>
          <button type="button" onClick={() => setShowPreview(!showPreview)} style={{ padding: '10px 20px', cursor: 'pointer' }}>
            {showPreview ? 'Masquer' : 'Previsualiser'}
          </button>
        </div>
      </form>

      {showPreview && (
        <div style={{ border: '1px solid #e5e7eb', borderRadius: '8px', padding: '30px', marginTop: '20px', background: '#fff', maxWidth: '800px' }}>
          <h2 style={{ textAlign: 'center', marginBottom: '24px', color: '#2563eb' }}>Apercu de la facture</h2>

          <div style={{ display: 'flex', justifyContent: 'space-between', marginBottom: '20px' }}>
            <div>
              <p style={{ fontWeight: 'bold' }}>Vendeur</p>
              <p style={{ color: '#6b7280', fontSize: '14px' }}>(Informations de votre entreprise)</p>
            </div>
            <div style={{ textAlign: 'right' }}>
              <p style={{ fontWeight: 'bold' }}>Client</p>
              <p>{clients.find((c) => c['@id'] === selectedClient)?.name || '—'}</p>
            </div>
          </div>

          <div style={{ display: 'flex', gap: '30px', marginBottom: '20px', fontSize: '14px', color: '#374151' }}>
            <p>Date d'emission : <strong>{issueDate || '—'}</strong></p>
            {dueDate && <p>Date d'echeance : <strong>{dueDate}</strong></p>}
          </div>

          <table style={{ width: '100%', borderCollapse: 'collapse', marginBottom: '20px' }}>
            <thead>
              <tr style={{ borderBottom: '2px solid #e5e7eb' }}>
                <th style={{ textAlign: 'left', padding: '8px', fontSize: '13px' }}>Description</th>
                <th style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>Qte</th>
                <th style={{ textAlign: 'center', padding: '8px', fontSize: '13px' }}>Unite</th>
                <th style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>Prix HT</th>
                <th style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>TVA</th>
                <th style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>Total HT</th>
              </tr>
            </thead>
            <tbody>
              {lines.map((line, i) => {
                const t = computeLineTotal(line);
                return (
                  <tr key={i} style={{ borderBottom: '1px solid #f3f4f6' }}>
                    <td style={{ padding: '8px', fontSize: '13px' }}>{line.description || '—'}</td>
                    <td style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>{line.quantity}</td>
                    <td style={{ textAlign: 'center', padding: '8px', fontSize: '13px' }}>{line.unit}</td>
                    <td style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>{parseFloat(line.unitPriceExcludingTax || '0').toFixed(2)} EUR</td>
                    <td style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>{line.vatRate}%</td>
                    <td style={{ textAlign: 'right', padding: '8px', fontSize: '13px' }}>{t.ht.toFixed(2)} EUR</td>
                  </tr>
                );
              })}
            </tbody>
          </table>

          <div style={{ textAlign: 'right', marginBottom: '20px' }}>
            <p>Total HT : <strong>{totals.ht.toFixed(2)} EUR</strong></p>
            <p>Total TVA : <strong>{totals.vat.toFixed(2)} EUR</strong></p>
            <p style={{ fontSize: '18px' }}>Total TTC : <strong>{totals.ttc.toFixed(2)} EUR</strong></p>
          </div>

          {paymentTerms && <p style={{ fontSize: '13px', color: '#6b7280' }}>Conditions : {paymentTerms}</p>}
          {legalMention && <p style={{ fontSize: '13px', fontStyle: 'italic', color: '#6b7280' }}>{legalMention}</p>}
        </div>
      )}
    </div>
  );
}
