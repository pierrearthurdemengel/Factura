import { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import { getCached, setCache } from '../utils/apiCache';
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
  const { error: toastError } = useToast();

  useEffect(() => {
    const params = { status: 'SENT', 'dueDate[before]': new Date().toISOString().split('T')[0] };
    const cached = getCached<{ 'hydra:member': Record<string, unknown>[] }>('/invoices', params as Record<string, string>);
    if (cached) {
      const raw = cached['hydra:member'] || [];
      const now = new Date();
      const mapped: OverdueInvoice[] = raw.map((inv) => {
        const dueDate = new Date(inv.dueDate as string);
        const daysOverdue = Math.floor((now.getTime() - dueDate.getTime()) / (1000 * 60 * 60 * 24));
        return {
          id: inv.id as string,
          number: (inv.number as string) || 'Brouillon',
          buyer: (inv.buyer as { name: string }) || { name: '\u2014' },
          totalIncludingTax: (inv.totalIncludingTax as string) || '0',
          dueDate: inv.dueDate as string,
          daysOverdue,
          reminders: (inv as { reminders?: ReminderEntry[] }).reminders || [],
        };
      });
      queueMicrotask(() => {
        setInvoices(mapped.toSorted((a, b) => b.daysOverdue - a.daysOverdue));
        setLoading(false);
      });
    }
    api.get('/invoices', { params })
      .then(async (res) => {
        setCache('/invoices', res.data, params as Record<string, string>);
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
            reminders: (inv as { reminders?: ReminderEntry[] }).reminders || [],
          };
        });
        // Charger les relances pour chaque facture si le champ est vide
        const withReminders = await Promise.all(
          mapped.map(async (inv) => {
            if (inv.reminders.length > 0) return inv;
            try {
              const r = await api.get<{ 'hydra:member': ReminderEntry[] }>(`/invoices/${inv.id}/reminders`);
              return { ...inv, reminders: r.data['hydra:member'] || [] };
            } catch {
              return inv;
            }
          })
        );
        setInvoices(withReminders.toSorted((a, b) => b.daysOverdue - a.daysOverdue));
      })
      .catch(() => toastError('Impossible de charger les factures en retard.'))
      .finally(() => setLoading(false));
  // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const totalUnpaid = invoices.reduce((s, inv) => s + Number.parseFloat(inv.totalIncludingTax), 0);

  // Regroupement par tranche de retard
  const brackets = [
    { label: '1-15 jours', min: 1, max: 15, color: 'var(--warning)' },
    { label: '16-30 jours', min: 16, max: 30, color: '#f97316' },
    { label: '31-60 jours', min: 31, max: 60, color: 'var(--danger)' },
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
      <div className="app-kpi-grid">
        <div className="app-card app-kpi-card">
          <div className="app-card-value" style={{ color: 'var(--danger)' }}>
            {totalUnpaid.toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
          </div>
          <div className="app-card-sub">Total impayes</div>
        </div>
        <div className="app-card app-kpi-card">
          <div className="app-card-value" style={{ color: 'var(--warning)' }}>
            {invoices.length}
          </div>
          <div className="app-card-sub">Factures en retard</div>
        </div>
        <div className="app-card app-kpi-card">
          <div className="app-card-value">
            {invoices.length > 0 ? Math.round(invoices.reduce((s, i) => s + i.daysOverdue, 0) / invoices.length) : 0}j
          </div>
          <div className="app-card-sub">Retard moyen</div>
        </div>
      </div>

      {/* Repartition par tranche */}
      <div className="app-pills">
        {brackets.map((b) => {
          const count = invoices.filter((i) => i.daysOverdue >= b.min && i.daysOverdue <= b.max).length;
          return (
            <span key={b.label} className="app-pill" style={{ background: `${b.color}15`, color: b.color }}>
              {b.label} ({count})
            </span>
          );
        })}
      </div>

      {invoices.length === 0 ? (
        <div className="app-empty">
          <p className="app-empty-title">Aucun impaye</p>
          <p className="app-empty-desc">Toutes vos factures sont a jour.</p>
        </div>
      ) : (
        <div className="app-list">
          {invoices.map((inv) => {
            const bracket = brackets.find((b) => inv.daysOverdue >= b.min && inv.daysOverdue <= b.max) || brackets[0];
            return (
              <Link
                key={inv.id}
                to={`/invoices/${inv.id}`}
                className="app-list-item"
                style={{ borderLeft: `3px solid ${bracket.color}` }}
              >
                <div className="app-list-item-info">
                  <div className="app-list-item-title">{inv.number}</div>
                  <div className="app-list-item-sub">
                    {inv.buyer.name}
                    {inv.reminders.length > 0 && (
                      <span style={{ marginLeft: '0.5rem', fontSize: '0.75rem', color: 'var(--text)' }}>
                        ({inv.reminders.length} relance{inv.reminders.length > 1 ? 's' : ''})
                      </span>
                    )}
                  </div>
                </div>
                <div className="app-list-item-sub">
                  Echeance : {new Date(inv.dueDate).toLocaleDateString('fr-FR')}
                </div>
                <span className="app-status-pill" style={{ background: `${bracket.color}15`, color: bracket.color }}>
                  J+{inv.daysOverdue}
                </span>
                <div className="app-list-item-value" style={{ color: 'var(--danger)' }}>
                  {Number.parseFloat(inv.totalIncludingTax).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                </div>
              </Link>
            );
          })}
        </div>
      )}
    </div>
  );
}
