import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/factura';
import './AppLayout.css';

// Facture en retard avec historique de relances
interface OverdueInvoice {
  id: string;
  number: string;
  buyer: { name: string };
  totalIncludingTax: string;
  dueDate: string;
  daysOverdue: number;
  reminders: ReminderEntry[];
}

interface ReminderEntry {
  id: string;
  type: string;
  sentAt: string;
  channel: string;
}

export default function Unpaid() {
  const [invoices, setInvoices] = useState<OverdueInvoice[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    api.get('/invoices', { params: { status: 'SENT', 'dueDate[before]': new Date().toISOString().split('T')[0] } })
      .then((res) => {
        const raw = res.data['hydra:member'] || [];
        const now = new Date();
        const mapped: OverdueInvoice[] = raw.map((inv: Record<string, unknown>) => {
          const dueDate = new Date(inv.dueDate as string);
          const daysOverdue = Math.floor((now.getTime() - dueDate.getTime()) / (1000 * 60 * 60 * 24));
          return {
            id: inv.id,
            number: inv.number || 'Brouillon',
            buyer: inv.buyer || { name: '—' },
            totalIncludingTax: inv.totalIncludingTax || '0',
            dueDate: inv.dueDate,
            daysOverdue,
            reminders: [],
          };
        });
        setInvoices(mapped.sort((a: OverdueInvoice, b: OverdueInvoice) => b.daysOverdue - a.daysOverdue));
      })
      .catch(() => {/* Pas de factures en retard */})
      .finally(() => setLoading(false));
  }, []);

  const totalUnpaid = invoices.reduce((s, inv) => s + parseFloat(inv.totalIncludingTax), 0);

  // Regroupement par tranche de retard
  const brackets = [
    { label: '1-15 jours', min: 1, max: 15, color: '#f59e0b' },
    { label: '16-30 jours', min: 16, max: 30, color: '#f97316' },
    { label: '31-60 jours', min: 31, max: 60, color: '#ef4444' },
    { label: '60+ jours', min: 61, max: Infinity, color: '#991b1b' },
  ];

  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      {[1, 2, 3].map((i) => (
        <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginTop: '0.75rem' }} />
      ))}
    </div>
  );

  return (
    <div className="app-container">
      <h1 className="app-page-title">Impayes</h1>

      {/* KPIs */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(200px, 1fr))', gap: '1rem', marginBottom: '1.5rem' }}>
        <div className="app-card" style={{ textAlign: 'center', padding: '1.25rem' }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: '#ef4444' }}>
            {totalUnpaid.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
          </div>
          <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>Total impayes</div>
        </div>
        <div className="app-card" style={{ textAlign: 'center', padding: '1.25rem' }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: '#f59e0b' }}>
            {invoices.length}
          </div>
          <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>Factures en retard</div>
        </div>
        <div className="app-card" style={{ textAlign: 'center', padding: '1.25rem' }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>
            {invoices.length > 0 ? Math.round(invoices.reduce((s, i) => s + i.daysOverdue, 0) / invoices.length) : 0}j
          </div>
          <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>Retard moyen</div>
        </div>
      </div>

      {/* Repartition par tranche */}
      <div style={{ display: 'flex', gap: '0.5rem', marginBottom: '1.5rem', flexWrap: 'wrap' }}>
        {brackets.map((b) => {
          const count = invoices.filter((i) => i.daysOverdue >= b.min && i.daysOverdue <= b.max).length;
          return (
            <span key={b.label} style={{ padding: '4px 12px', borderRadius: '1rem', fontSize: '0.85rem', fontWeight: 600, background: `${b.color}15`, color: b.color }}>
              {b.label} ({count})
            </span>
          );
        })}
      </div>

      {invoices.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
          <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>Aucun impaye</p>
          <p style={{ fontSize: '0.9rem' }}>Toutes vos factures sont a jour.</p>
        </div>
      ) : (
        <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
          {invoices.map((inv) => {
            const bracket = brackets.find((b) => inv.daysOverdue >= b.min && inv.daysOverdue <= b.max) || brackets[0];
            return (
              <Link
                key={inv.id}
                to={`/invoices/${inv.id}`}
                className="app-card"
                style={{ textDecoration: 'none', display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '0.5rem', borderLeft: `3px solid ${bracket.color}` }}
              >
                <div style={{ flex: 1, minWidth: 200 }}>
                  <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.95rem' }}>{inv.number}</div>
                  <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.15rem' }}>{inv.buyer.name}</div>
                </div>
                <div style={{ fontSize: '0.85rem', color: 'var(--text)' }}>
                  Echeance : {new Date(inv.dueDate).toLocaleDateString('fr-FR')}
                </div>
                <span style={{ padding: '3px 10px', borderRadius: '1rem', fontSize: '0.8rem', fontWeight: 600, background: `${bracket.color}15`, color: bracket.color }}>
                  J+{inv.daysOverdue}
                </span>
                <div style={{ fontWeight: 600, color: '#ef4444', minWidth: 100, textAlign: 'right' }}>
                  {parseFloat(inv.totalIncludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
