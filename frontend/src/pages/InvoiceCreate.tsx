import { useState, useEffect, useMemo, useRef } from 'react';
import { useNavigate } from 'react-router-dom';
import { createInvoice, getClients, type Client } from '../api/factura';
import { useToast } from '../context/ToastContext';
import DatePicker from '../components/DatePicker';
import { downloadLocalPdf } from '../utils/pdfGenerator';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, verticalListSortingStrategy, useSortable, arrayMove } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { motion } from 'framer-motion';
import './AppLayout.css';
import './InvoiceCreate.css';

interface LineForm {
  id: string;
  description: string;
  quantity: string;
  unit: string;
  unitPriceExcludingTax: string;
  vatRate: string;
}

function SortableLineItem({ id, children }: { id: string, children: React.ReactNode }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  const style = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.8 : 1,
    boxShadow: isDragging ? '0 10px 25px rgba(0,0,0,0.1)' : 'none',
    zIndex: isDragging ? 100 : 1,
    position: 'relative' as const,
    marginBottom: '1rem', 
    padding: '0', 
    display: 'flex',
    background: 'var(--bg)',
    borderRadius: '8px',
    border: '1px solid var(--border)'
  };

  return (
    <div ref={setNodeRef} style={style}>
      <div 
        {...attributes} 
        {...listeners} 
        style={{ 
          cursor: isDragging ? 'grabbing' : 'grab', 
          padding: '0 1rem', 
          display: 'flex', 
          alignItems: 'center', 
          justifyContent: 'center', 
          borderRight: '1px solid var(--border)', 
          backgroundColor: 'var(--social-bg)', 
          borderTopLeftRadius: '8px', 
          borderBottomLeftRadius: '8px', 
          color: 'var(--l-gray)' 
        }}
      >
        <svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="9" cy="12" r="1.5"></circle>
          <circle cx="9" cy="5" r="1.5"></circle>
          <circle cx="9" cy="19" r="1.5"></circle>
          <circle cx="15" cy="12" r="1.5"></circle>
          <circle cx="15" cy="5" r="1.5"></circle>
          <circle cx="15" cy="19" r="1.5"></circle>
        </svg>
      </div>
      <div style={{ flex: 1, padding: '1rem' }}>
        {children}
      </div>
    </div>
  );
}

export default function InvoiceCreate() {
  const navigate = useNavigate();
  const [clients, setClients] = useState<Client[]>([]);
  const [selectedClient, setSelectedClient] = useState('');
  const [issueDate, setIssueDate] = useState(new Date().toISOString().split('T')[0]);
  const [dueDate, setDueDate] = useState('');
  const [legalMention, setLegalMention] = useState('');
  const [paymentTerms, setPaymentTerms] = useState('');
  const [lines, setLines] = useState<LineForm[]>([
    { id: crypto.randomUUID(), description: '', quantity: '1', unit: 'EA', unitPriceExcludingTax: '', vatRate: '20' },
  ]);
  const { error, success } = useToast();
  
  // Holographic 3D state
  const previewRef = useRef<HTMLDivElement>(null);
  const [tilt, setTilt] = useState({ x: 0, y: 0, glareX: 50, glareY: 50 });

  const handleMouseMove = (e: React.MouseEvent) => {
    if (!previewRef.current) return;
    const rect = previewRef.current.getBoundingClientRect();
    const x = e.clientX - rect.left;
    const y = e.clientY - rect.top;
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    
    // Rotate max 8 degrees
    const rotateX = ((y - centerY) / centerY) * -8;
    const rotateY = ((x - centerX) / centerX) * 8;
    
    // Glare position
    const glareX = (x / rect.width) * 100;
    const glareY = (y / rect.height) * 100;

    setTilt({ x: rotateX, y: rotateY, glareX, glareY });
  };

  const handleMouseLeave = () => {
    setTilt({ x: 0, y: 0, glareX: 50, glareY: 50 });
  };

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor)
  );

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

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      setLines((items) => {
        const oldIndex = items.findIndex((item) => item.id === active.id);
        const newIndex = items.findIndex((item) => item.id === over.id);
        return arrayMove(items, oldIndex, newIndex);
      });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

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
      success('Facture creee avec succes.');
      navigate('/invoices');
    } catch {
      error('Erreur lors de la creation de la facture.');
    }
  };

  const handlePreviewPdf = async () => {
    try {
      success('Capture Ultra-HD en cours...');
      await downloadLocalPdf('.a4-paper', `Aperçu_Facture_${selectedClient ? 'Client' : 'Brouillon'}.pdf`);
    } catch {
      error('Erreur lors de la génération PDF locale.');
    }
  };

  // Typeform completion gauge logic
  const completionRate = useMemo(() => {
    let score = 0;
    if (selectedClient) score += 35;
    if (issueDate) score += 15;
    if (lines.length > 0 && lines[0].description && lines[0].unitPriceExcludingTax) score += 50;
    return score;
  }, [selectedClient, issueDate, lines]);

  return (
    <div className="app-container">
      <h1 className="app-page-title">Nouvelle facture</h1>

      <div className="invoice-create-layout">
        <div className="invoice-create-form">
          <form onSubmit={handleSubmit}>
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
                <label className="app-label">Date d'echeance</label>
                <DatePicker value={dueDate} onChange={setDueDate} placeholder="Facultatif" />
              </div>
            </div>

            <h2 className="app-section-title">Lignes</h2>
            
            <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
              <SortableContext items={lines.map(l => l.id)} strategy={verticalListSortingStrategy}>
                {lines.map((line) => (
                  <SortableLineItem key={line.id} id={line.id}>
                    <div className="app-form-row">
                      <div className="app-form-group flex-2">
                        <label className="app-label">Description</label>
                        <input
                          type="text"
                          value={line.description}
                          onChange={(e) => updateLine(line.id, 'description', e.target.value)}
                          required
                          className="app-input"
                        />
                      </div>
                      <div className="app-form-group">
                        <label className="app-label">Quantite</label>
                        <input
                          type="number"
                          step="0.01"
                          value={line.quantity}
                          onChange={(e) => updateLine(line.id, 'quantity', e.target.value)}
                          required
                          className="app-input"
                        />
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
                    <div className="app-form-row" style={{ alignItems: 'flex-end', marginTop: '1rem' }}>
                      <div className="app-form-group">
                        <label className="app-label">Prix HT</label>
                        <input
                          type="number"
                          step="0.01"
                          value={line.unitPriceExcludingTax}
                          onChange={(e) => updateLine(line.id, 'unitPriceExcludingTax', e.target.value)}
                          required
                          className="app-input"
                        />
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
                        <p style={{ margin: '0 0 10px', fontWeight: 600 }}>{computeLineTotal(line).ht.toFixed(2)} EUR HT</p>
                        <button type="button" onClick={() => removeLine(line.id)} disabled={lines.length === 1} className="app-btn-outline-danger">
                          Supprimer
                        </button>
                      </div>
                    </div>
                  </SortableLineItem>
                ))}
              </SortableContext>
            </DndContext>

            <button type="button" onClick={addLine} className="app-btn-outline-danger" style={{ marginBottom: 'clamp(1.5rem, 3vw, 2rem)', color: 'var(--text-h)', borderColor: 'var(--border)' }}>
              + Ajouter une ligne
            </button>

            <div className="app-form-group">
              <label className="app-label">Conditions de paiement</label>
              <input
                type="text"
                value={paymentTerms}
                onChange={(e) => setPaymentTerms(e.target.value)}
                placeholder="Paiement a 30 jours fin de mois"
                className="app-input"
              />
            </div>

            <div className="app-form-group">
              <label className="app-label">Mention legale</label>
              <textarea
                value={legalMention}
                onChange={(e) => setLegalMention(e.target.value)}
                placeholder="TVA non applicable - art. 293 B du CGI"
                className="app-input"
                style={{ resize: 'vertical', minHeight: '80px' }}
              />
            </div>

            <div className="app-card" style={{ marginBottom: 'clamp(2.5rem, 5vw, 4rem)', alignItems: 'flex-end', background: 'var(--social-bg)' }}>
              <p style={{ margin: '0 0 0.5rem', color: 'var(--text)' }}>Total HT : <strong style={{ color: 'var(--text-h)' }}>{totals.ht.toFixed(2)} EUR</strong></p>
              <p style={{ margin: '0 0 0.5rem', color: 'var(--text)' }}>Total TVA : <strong style={{ color: 'var(--text-h)' }}>{totals.vat.toFixed(2)} EUR</strong></p>
              <p style={{ margin: 0, fontSize: '1.25rem', color: 'var(--text-h)' }}>Total TTC : <strong>{totals.ttc.toFixed(2)} EUR</strong></p>
            </div>

            {/* Barre de Progression / Focus Bottom Bar */}
            <div style={{ position: 'fixed', bottom: 0, left: 0, width: '50vw', padding: '24px', background: 'var(--bg)', borderTop: '1px solid var(--border)', display: 'flex', gap: '20px', alignItems: 'center', zIndex: 100, borderRight: '1px solid var(--border)' }}>
              <div style={{ flex: 1, display: 'flex', flexDirection: 'column', gap: '8px' }}>
                <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.8rem', color: 'var(--text-h)', fontWeight: 600 }}>
                  <span>Progression ({completionRate}%)</span>
                  <span>{completionRate === 100 ? 'Prêt à valider' : 'Champs manquants'}</span>
                </div>
                <div style={{ width: '100%', height: '6px', background: 'var(--social-bg)', borderRadius: '3px', overflow: 'hidden' }}>
                  <div style={{ width: `${completionRate}%`, background: 'var(--accent)', height: '100%', transition: 'width 0.4s cubic-bezier(0.22, 1, 0.36, 1)' }} />
                </div>
              </div>
              <button type="button" onClick={() => navigate('/invoices')} className="app-btn-outline" style={{ padding: '12px 24px' }}>
                Annuler
              </button>
              <button type="submit" disabled={completionRate < 100} className="app-btn-primary" style={{ padding: '12px 24px', opacity: completionRate === 100 ? 1 : 0.5 }}>
                Creer la facture
              </button>
            </div>
            
            <div className="app-form-row" style={{ marginTop: '0.5rem', marginBottom: '8rem' }}>
              <button type="button" onClick={handlePreviewPdf} className="app-btn-outline" style={{ width: '100%', padding: '12px' }}>
                ⬇️ Télécharger Aperçu PDF (Ultra-HD)
              </button>
            </div>
          </form>
        </div>

        <div className="invoice-create-preview-sticky" style={{ perspective: '1500px' }}>
          <motion.div 
            ref={previewRef}
            onMouseMove={handleMouseMove}
            onMouseLeave={handleMouseLeave}
            animate={{ rotateX: tilt.x, rotateY: tilt.y }}
            transition={{ type: 'spring', stiffness: 300, damping: 30 }}
            className="a4-paper force-light-theme"
            style={{ position: 'relative', overflow: 'hidden', transformStyle: 'preserve-3d' }}
          >
            {/* Glare effect */}
            <div style={{
              position: 'absolute',
              inset: 0,
              background: `radial-gradient(circle at ${tilt.glareX}% ${tilt.glareY}%, rgba(255,255,255,0.4) 0%, rgba(255,255,255,0) 60%)`,
              mixBlendMode: 'overlay',
              pointerEvents: 'none',
              zIndex: 10,
              transition: 'background 0.1s'
            }} />

            <h2 style={{ fontSize: '1.25rem', fontWeight: 800, margin: '0 0 2rem 0', color: '#111' }}>FACTURE {selectedClient ? '' : '(Brouillon)'}</h2>

            <div className="app-grid">
              <div className="app-form-group">
                <p className="app-label">Vendeur</p>
                <p style={{ color: 'var(--text)', fontSize: '0.9rem', margin: 0 }}>(Informations de votre entreprise)</p>
              </div>
              <div className="app-form-group" style={{ textAlign: 'right' }}>
                <p className="app-label">Client</p>
                <p style={{ color: 'var(--text)', margin: 0 }}>{clients.find((c) => c['@id'] === selectedClient)?.name || '—'}</p>
              </div>
            </div>

            <div className="app-grid" style={{ fontSize: '0.9rem', color: 'var(--text)', marginBottom: '1.5rem' }}>
              <p style={{ margin: 0 }}>Date d'emission : <strong style={{ color: 'var(--text-h)' }}>{issueDate || '—'}</strong></p>
              {dueDate && <p style={{ margin: 0 }}>Date d'echeance : <strong style={{ color: 'var(--text-h)' }}>{dueDate}</strong></p>}
            </div>

            <div className="app-table-wrapper" style={{ marginBottom: '1.5rem' }}>
              <table className="app-table">
                <thead>
                  <tr>
                    <th style={{ fontSize: '0.85rem' }}>Description</th>
                    <th style={{ textAlign: 'right', fontSize: '0.85rem' }}>Qte</th>
                    <th style={{ textAlign: 'center', fontSize: '0.85rem' }}>Unite</th>
                    <th style={{ textAlign: 'right', fontSize: '0.85rem' }}>Prix HT</th>
                    <th style={{ textAlign: 'right', fontSize: '0.85rem' }}>TVA</th>
                    <th style={{ textAlign: 'right', fontSize: '0.85rem' }}>Total HT</th>
                  </tr>
                </thead>
                <tbody>
                  {lines.map((line, i) => {
                    const t = computeLineTotal(line);
                    return (
                      <tr key={i}>
                        <td style={{ fontSize: '0.85rem' }}>{line.description || '—'}</td>
                        <td style={{ textAlign: 'right', fontSize: '0.85rem' }}>{line.quantity}</td>
                        <td style={{ textAlign: 'center', fontSize: '0.85rem' }}>{line.unit}</td>
                        <td style={{ textAlign: 'right', fontSize: '0.85rem' }}>{parseFloat(line.unitPriceExcludingTax || '0').toFixed(2)} EUR</td>
                        <td style={{ textAlign: 'right', fontSize: '0.85rem' }}>{line.vatRate}%</td>
                        <td style={{ textAlign: 'right', fontSize: '0.85rem' }}>{t.ht.toFixed(2)} EUR</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div style={{ textAlign: 'right', marginBottom: '1.5rem', color: '#111' }}>
              <p style={{ margin: '0 0 0.25rem' }}>Total HT : <strong>{totals.ht.toFixed(2)} EUR</strong></p>
              <p style={{ margin: '0 0 0.25rem' }}>Total TVA : <strong>{totals.vat.toFixed(2)} EUR</strong></p>
              <p style={{ margin: 0, fontSize: '1.15rem' }}>Total TTC : <strong>{totals.ttc.toFixed(2)} EUR</strong></p>
            </div>

            {paymentTerms && <p style={{ fontSize: '0.85rem', color: '#444', margin: '0 0 0.5rem' }}>Conditions : {paymentTerms}</p>}
            {legalMention && <p style={{ fontSize: '0.85rem', fontStyle: 'italic', color: '#666', margin: 0 }}>{legalMention}</p>}
          </motion.div>
        </div>
      </div>
    </div>
  );
}
