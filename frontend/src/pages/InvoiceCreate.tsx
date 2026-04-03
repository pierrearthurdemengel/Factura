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
                <option value="0">0%</option>
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

        <button type="submit" style={{ padding: '10px 20px', cursor: 'pointer' }}>
          Creer la facture
        </button>
      </form>
    </div>
  );
}
