import { useEffect, useState } from 'react';
import { getInvoices, getInvoiceEvents, type Invoice, type InvoiceEvent } from '../api/factura';
import { Link } from 'react-router-dom';
import { LineChart, Line, ResponsiveContainer } from 'recharts';
import EmptyState from '../components/EmptyState';
import RevenueChart from '../components/RevenueChart';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, rectSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import './AppLayout.css';

// Mini Sparkline Component
function Sparkline({ data, color }: { data: number[], color: string }) {
  const chartData = data.map((v, i) => ({ index: i, value: v }));
  return (
    <div style={{ width: '80px', height: '30px' }}>
      <ResponsiveContainer width="100%" height="100%">
        <LineChart data={chartData}>
          <Line type="monotone" dataKey="value" stroke={color} strokeWidth={2} dot={false} isAnimationActive={true} />
        </LineChart>
      </ResponsiveContainer>
    </div>
  );
}

// Activity Feed Item Props
interface ActivityItem {
  id: string;
  invoiceNumber: string;
  eventType: string;
  date: string;
}

// Badges de statut avec couleurs
const statusBadge = (status: string) => {
  const classes: Record<string, string> = {
    DRAFT: 'app-badge-draft',
    SENT: 'app-badge-sent',
    ACKNOWLEDGED: 'app-badge-acknowledged',
    REJECTED: 'app-badge-rejected',
    PAID: 'app-badge-paid',
    CANCELLED: 'app-badge-cancelled',
  };
  const labels: Record<string, string> = {
    DRAFT: 'Brouillon',
    SENT: 'Envoyee',
    ACKNOWLEDGED: 'Acceptee',
    REJECTED: 'Rejetee',
    PAID: 'Payee',
    CANCELLED: 'Annulee',
  };
  return (
    <span className={`app-badge ${classes[status] || 'app-badge-draft'}`}>
      {labels[status] || status}
    </span>
  );
};

// --- WIDGET ENCAPSULATION ---
function SortableWidget({ id, className, children }: { id: string, className: string, children: React.ReactNode }) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });
  
  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.4 : 1,
    zIndex: isDragging ? 10 : 1,
    cursor: isDragging ? 'grabbing' : 'auto',
    display: 'flex',
    flexDirection: 'column',
  };

  return (
    <div ref={setNodeRef} style={style} className={className}>
      <div style={{ outline: 'none', flex: 1, display: 'flex', flexDirection: 'column', position: 'relative', width: '100%' }}>
        {/* Poignee magnetique de glissement (Drag Handle) pour re-organiser */}
        <div 
          {...attributes} 
          {...listeners} 
          style={{ 
            position: 'absolute', top: '10px', right: '10px', width: '20px', height: '20px', 
            cursor: 'grab', background: 'var(--social-bg)', borderRadius: '4px', 
            display: 'flex', alignItems: 'center', justifyContent: 'center', zIndex: 10,
            opacity: 0, transition: 'opacity 0.2s'
          }}
          className="widget-drag-handle"
          title="Déplacer le widget"
        >
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--text)" strokeWidth="2"><circle cx="9" cy="12" r="1"/><circle cx="9" cy="5" r="1"/><circle cx="9" cy="19" r="1"/><circle cx="15" cy="12" r="1"/><circle cx="15" cy="5" r="1"/><circle cx="15" cy="19" r="1"/></svg>
        </div>
        
        {children}
      </div>
    </div>
  );
}

// Page tableau de bord : compteurs et dernieres factures.
export default function Dashboard() {
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [activities, setActivities] = useState<ActivityItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [showConsolidated, setShowConsolidated] = useState(false);

  // Widget Layout Order
  const [widgets, setWidgets] = useState<string[]>(() => {
    const saved = localStorage.getItem('factura-dashboard-layout');
    return saved ? JSON.parse(saved) : ['kpi-month', 'kpi-revenue', 'kpi-pending', 'kpi-treasury', 'chart-revenue', 'list-recent', 'list-feed', 'suggestions'];
  });

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  useEffect(() => {
    let cancelled = false;
    getInvoices()
      .then(async (res) => {
        if (cancelled) return;
        const invs = res.data['hydra:member'];
        setInvoices(invs);
        
        // Fetch recent activities from top 5 newest invoices
        const recentInvs = [...invs].sort((a,b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()).slice(0, 5);
        const allActs: ActivityItem[] = [];
        
        for (const inv of recentInvs) {
          try {
            const evts = await getInvoiceEvents(inv.id);
            evts.data.forEach((e: InvoiceEvent) => {
              allActs.push({
                id: e.id,
                invoiceNumber: inv.number || 'Brouillon',
                eventType: e.eventType,
                date: e.occurredAt
              });
            });
          } catch {
            // Ignore failure on specific event fetch
          }
        }
        
        allActs.sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
        if (!cancelled) setActivities(allActs);
      })
      .catch(() => { if (!cancelled) setInvoices([]); })
      .finally(() => { if (!cancelled) setLoading(false); });
      
    return () => { cancelled = true; };
  }, []);

  const handleDragEnd = (event: DragEndEvent) => {
    const { active, over } = event;
    if (over && active.id !== over.id) {
      setWidgets((items) => {
        const oldIndex = items.indexOf(active.id.toString());
        const newIndex = items.indexOf(over.id.toString());
        const newLayout = arrayMove(items, oldIndex, newIndex);
        localStorage.setItem('factura-dashboard-layout', JSON.stringify(newLayout));
        return newLayout;
      });
    }
  };

  const thisMonth = invoices.filter((inv) => {
    const d = new Date(inv.issueDate);
    const now = new Date();
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });

  const totalHt = thisMonth.reduce((sum, inv) => sum + parseFloat(inv.totalExcludingTax), 0);
  const pending = invoices.filter((inv) => inv.status === 'SENT' || inv.status === 'ACKNOWLEDGED');

  const lastMonth = invoices.filter((inv) => {
    const d = new Date(inv.issueDate);
    const now = new Date();
    const lastM = now.getMonth() === 0 ? 11 : now.getMonth() - 1;
    const lastY = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear();
    return d.getMonth() === lastM && d.getFullYear() === lastY;
  });

  const totalHtLastMonth = lastMonth.reduce((sum, inv) => sum + parseFloat(inv.totalExcludingTax), 0);
  const trendHt = totalHtLastMonth > 0 ? ((totalHt - totalHtLastMonth) / totalHtLastMonth) * 100 : 100;

  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      <div className="app-grid">
        <div className="app-skeleton app-skeleton-card" />
        <div className="app-skeleton app-skeleton-card" />
        <div className="app-skeleton app-skeleton-card" />
      </div>
    </div>
  );

  return (
    <div className="app-container app-dashboard-root">
      {/* Un peu de CSS global inline pour cibler le hover du drag-handle sur le root */}
      <style>{`
        .app-dashboard-root .widget-drag-handle { opacity: 0; }
        .app-dashboard-root .app-card:hover .widget-drag-handle { opacity: 1; }
      `}</style>
      
      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem', marginBottom: '0.5rem' }}>
        <h1 className="app-page-title" style={{ margin: 0 }}>Tableau de bord</h1>
        <button
          onClick={() => setShowConsolidated(!showConsolidated)}
          style={{
            padding: '4px 12px', borderRadius: '1rem', border: '1px solid var(--border)',
            background: showConsolidated ? 'var(--accent)' : 'var(--surface)',
            color: showConsolidated ? '#fff' : 'var(--text)', fontSize: '0.8rem',
            cursor: 'pointer', fontWeight: 500,
          }}
        >
          {showConsolidated ? 'Vue consolidee' : 'Entreprise active'}
        </button>
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragEnd={handleDragEnd}>
        <SortableContext items={widgets} strategy={rectSortingStrategy}>
          <div className="app-dashboard-grid">

            {widgets.map((wId) => {
              if (wId === 'kpi-month') {
                return (
                  <SortableWidget key={wId} id={wId} className="widget-kpi">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title">Factures du mois</h3>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginTop: 'auto' }}>
                        <p className="app-card-value" style={{ margin: 0 }}>{thisMonth.length}</p>
                        <Sparkline data={[1, 3, 2, 5, 4, thisMonth.length]} color="#3b82f6" />
                      </div>
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'kpi-revenue') {
                return (
                  <SortableWidget key={wId} id={wId} className="widget-kpi">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title">CA HT du mois</h3>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginTop: 'auto' }}>
                        <div>
                          <p className="app-card-value" style={{ margin: 0 }}>{totalHt.toFixed(2)} &euro;</p>
                          <div style={{ display: 'inline-block', marginTop: '8px', padding: '2px 8px', borderRadius: '12px', fontSize: '0.75rem', fontWeight: 700, backgroundColor: trendHt >= 0 ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)', color: trendHt >= 0 ? '#4ade80' : '#f87171' }}>
                            {trendHt >= 0 ? '+' : ''}{trendHt.toFixed(1)}% vs. prec
                          </div>
                        </div>
                        <Sparkline data={[totalHtLastMonth * 0.8, totalHtLastMonth, totalHtLastMonth * 1.1, totalHt]} color={trendHt >= 0 ? '#10b981' : '#ef4444'} />
                      </div>
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'kpi-pending') {
                return (
                  <SortableWidget key={wId} id={wId} className="widget-kpi">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title">En attente</h3>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginTop: 'auto' }}>
                        <p className="app-card-value" style={{ margin: 0 }}>{pending.length}</p>
                        <Sparkline data={[0, 2, 1, 4, pending.length]} color="#f59e0b" />
                      </div>
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'kpi-treasury') {
                // Tresorerie predictive : CA en attente - charges estimees
                const pendingAmount = pending.reduce((s, inv) => s + parseFloat(inv.totalIncludingTax), 0);
                const estimatedTreasury = pendingAmount * 0.85; // Estimation nette apres charges
                return (
                  <SortableWidget key={wId} id={wId} className="widget-kpi">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title">Tresorerie previsionnelle</h3>
                      <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-end', marginTop: 'auto' }}>
                        <div>
                          <p className="app-card-value" style={{ margin: 0 }}>{estimatedTreasury.toFixed(0)} &euro;</p>
                          <div style={{ fontSize: '0.75rem', color: 'var(--text)', marginTop: '4px' }}>
                            {pending.length} facture(s) en attente
                          </div>
                        </div>
                        <Sparkline data={[pendingAmount * 0.6, pendingAmount * 0.7, pendingAmount * 0.8, estimatedTreasury]} color="#8b5cf6" />
                      </div>
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'chart-revenue') {
                return (
                  <SortableWidget key={wId} id={wId} className="widget-full">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title" style={{ marginBottom: '1rem' }}>Evolution du CA (HT)</h3>
                      <RevenueChart invoices={invoices} />
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'list-recent') {
                return (
                  <SortableWidget key={wId} id={wId} className="widget-wide">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title" style={{ marginBottom: '1rem' }}>Dernieres factures</h3>
                      {invoices.length === 0 ? (
                        <div style={{flex: 1}}>
                          <EmptyState 
                            title="Bienvenue !" 
                            description="Vous n'avez pas encore emis de facture. Il est temps de rediger la premiere."
                            action={<Link to="/invoices/new" className="app-btn-primary" style={{ textDecoration: 'none' }}>Creer une facture</Link>}
                          />
                        </div>
                      ) : (
                        <div className="app-table-wrapper" style={{flex: 1}}>
                          <table className="app-table">
                            <thead>
                              <tr>
                                <th>Numero</th>
                                <th>Client</th>
                                <th>Date</th>
                                <th style={{ textAlign: 'right' }}>Montant TTC</th>
                                <th style={{ textAlign: 'center' }}>Statut</th>
                              </tr>
                            </thead>
                            <tbody>
                              {invoices.slice(0, 5).map((inv) => (
                                <tr key={inv.id}>
                                  <td>
                                    <Link to={`/invoices/${inv.id}`} style={{ color: 'var(--accent)', textDecoration: 'none', fontWeight: 500 }}>{inv.number || 'Brouillon'}</Link>
                                  </td>
                                  <td>{inv.buyer?.name}</td>
                                  <td>{new Date(inv.issueDate).toLocaleDateString('fr-FR')}</td>
                                  <td style={{ textAlign: 'right' }}>{inv.totalIncludingTax} &euro;</td>
                                  <td style={{ textAlign: 'center' }}>{statusBadge(inv.status)}</td>
                                </tr>
                              ))}
                            </tbody>
                          </table>
                        </div>
                      )}
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'list-feed') {
                return (
                  <SortableWidget key={wId} id={wId} className="widget-narrow">
                    <div className="app-card" style={{ width: '100%' }}>
                      <h3 className="app-card-title" style={{ marginBottom: '1rem' }}>Fil d'actualite</h3>
                      {activities.length === 0 ? (
                        <p style={{ color: 'var(--l-gray)', fontStyle: 'italic', fontSize: '0.9rem' }}>Aucune activite recente.</p>
                      ) : (
                        <div style={{ display: 'flex', flexDirection: 'column', gap: '1rem', position: 'relative' }}>
                          <div style={{ position: 'absolute', left: '6px', top: '10px', bottom: '10px', width: '2px', background: 'var(--border)' }} />
                          {activities.slice(0, 5).map((act, index) => {
                            const isCreation = act.eventType === 'CREATED';
                            const isPaid = act.eventType === 'PAID';
                            return (
                              <div key={index} style={{ display: 'flex', gap: '12px', alignItems: 'flex-start', position: 'relative', zIndex: 1 }}>
                                <div style={{ width: '14px', height: '14px', borderRadius: '50%', background: isPaid ? '#10b981' : isCreation ? '#3b82f6' : '#c084fc', flexShrink: 0, marginTop: '4px', border: '2px solid var(--bg)' }} />
                                <div>
                                  <p style={{ margin: '0 0 2px', fontSize: '0.85rem', color: 'var(--text-h)', fontWeight: 600 }}>
                                    Facture {act.invoiceNumber}
                                  </p>
                                  <p style={{ margin: '0 0 4px', fontSize: '0.8rem', color: 'var(--text)' }}>
                                    {act.eventType === 'CREATED' && 'a ete creee.'}
                                    {act.eventType === 'SENT' && 'a ete envoyee au client.'}
                                    {act.eventType === 'ACKNOWLEDGED' && 'a ete acceptee par le client.'}
                                    {act.eventType === 'PAID' && 'a ete classee passee.'}
                                    {act.eventType === 'CANCELLED' && 'a ete annulee.'}
                                    {act.eventType === 'STATUS_CHANGED' && "a change d'etat."}
                                    {!['CREATED','SENT','ACKNOWLEDGED','PAID','CANCELLED','STATUS_CHANGED'].includes(act.eventType) && `(${act.eventType})`}
                                  </p>
                                  <p style={{ margin: 0, fontSize: '0.75rem', color: 'var(--l-gray)' }}>
                                    {new Date(act.date).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                  </p>
                                </div>
                              </div>
                            );
                          })}
                        </div>
                      )}
                    </div>
                  </SortableWidget>
                );
              }
              if (wId === 'suggestions') {
                // Suggestions contextuelles basees sur les donnees
                const suggestions: { icon: string; text: string; action: string }[] = [];
                if (invoices.length === 0) {
                  suggestions.push({ icon: '📝', text: 'Creez votre premiere facture pour commencer', action: '/invoices/new' });
                }
                const overdue = invoices.filter(inv => inv.status === 'SENT' && inv.dueDate && new Date(inv.dueDate) < new Date());
                if (overdue.length > 0) {
                  suggestions.push({ icon: '⚠️', text: `${overdue.length} facture(s) en retard de paiement`, action: '/unpaid' });
                }
                if (pending.length > 3) {
                  suggestions.push({ icon: '💰', text: 'Pensez a activer l\'affacturage pour recevoir vos paiements plus vite', action: '/settings' });
                }
                if (thisMonth.length >= 10) {
                  suggestions.push({ icon: '🏦', text: 'Connectez votre banque pour automatiser le rapprochement', action: '/banking' });
                }
                if (suggestions.length === 0) {
                  suggestions.push({ icon: '✅', text: 'Tout est en ordre. Continuez comme ca !', action: '/' });
                }
                return (
                  <SortableWidget key={wId} id={wId} className="widget-full">
                    <div className="app-card" style={{ width: '100%', padding: '1.25rem' }}>
                      <h3 className="app-card-title" style={{ marginBottom: '0.75rem' }}>Suggestions</h3>
                      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem' }}>
                        {suggestions.map((s, i) => (
                          <Link
                            key={i}
                            to={s.action}
                            style={{
                              display: 'flex', alignItems: 'center', gap: '0.75rem',
                              padding: '0.6rem 0.75rem', borderRadius: '8px',
                              background: 'var(--social-bg)', textDecoration: 'none',
                              color: 'var(--text-h)', fontSize: '0.85rem',
                              transition: 'background 0.2s',
                            }}
                          >
                            <span style={{ fontSize: '1.1rem' }}>{s.icon}</span>
                            <span>{s.text}</span>
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--text)" strokeWidth="2" style={{ marginLeft: 'auto', flexShrink: 0 }}><polyline points="9 18 15 12 9 6"></polyline></svg>
                          </Link>
                        ))}
                      </div>
                    </div>
                  </SortableWidget>
                );
              }
              return null;
            })}

          </div>
        </SortableContext>
      </DndContext>
    </div>
  );
}
