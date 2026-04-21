import { useEffect, useState } from 'react';
import { useIntl } from 'react-intl';
import { getInvoices, getInvoiceEvents, type Invoice, type InvoiceEvent } from '../api/factura';
import { Link } from 'react-router-dom';
import RevenueChart from '../components/RevenueChart';
import { DndContext, closestCenter, KeyboardSensor, PointerSensor, useSensor, useSensors, DragOverlay, type DragStartEvent, type DragEndEvent } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, verticalListSortingStrategy, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { getCached, setCache } from '../utils/apiCache';
import './Dashboard.css';

// --- Sparkline (SVG pur) ---
function Sparkline({ data, color }: Readonly<{ data: number[], color: string }>) {
  const w = 64;
  const h = 28;
  const pad = 2;
  const max = Math.max(...data, 1);
  const min = Math.min(...data, 0);
  const range = max - min || 1;
  const points = data
    .map((v, i) => {
      const x = pad + (i / Math.max(data.length - 1, 1)) * (w - pad * 2);
      const y = pad + (1 - (v - min) / range) * (h - pad * 2);
      return `${x},${y}`;
    })
    .join(' ');

  return (
    <div className="dash-sparkline">
      <svg width={w} height={h} viewBox={`0 0 ${w} ${h}`} style={{ display: 'block', width: '100%', height: '100%' }}>
        <polyline points={points} fill="none" stroke={color} strokeWidth={2} strokeLinecap="round" strokeLinejoin="round" />
      </svg>
    </div>
  );
}

// --- Activity Feed ---
interface ActivityItem {
  id: string;
  invoiceNumber: string;
  eventType: string;
  date: string;
}

// --- Status helpers ---
const STATUS_CONFIG: Record<string, { label: string; cls: string }> = {
  DRAFT: { label: 'Brouillon', cls: 'dash-badge--draft' },
  SENT: { label: 'Envoyee', cls: 'dash-badge--sent' },
  ACKNOWLEDGED: { label: 'Acceptee', cls: 'dash-badge--acknowledged' },
  REJECTED: { label: 'Rejetee', cls: 'dash-badge--rejected' },
  PAID: { label: 'Payee', cls: 'dash-badge--paid' },
  CANCELLED: { label: 'Annulee', cls: 'dash-badge--cancelled' },
};

function StatusBadge({ status }: Readonly<{ status: string }>) {
  const cfg = STATUS_CONFIG[status] || { label: status, cls: 'dash-badge--draft' };
  return <span className={`dash-badge ${cfg.cls}`}>{cfg.label}</span>;
}

const EVENT_LABELS: Record<string, string> = {
  CREATED: 'a ete creee.',
  SENT: 'a ete envoyee au client.',
  ACKNOWLEDGED: 'a ete acceptee par le client.',
  PAID: 'a ete classee payee.',
  CANCELLED: 'a ete annulee.',
  STATUS_CHANGED: "a change d'etat.",
};

// --- Sortable Widget ---
function SortableWidget({ id, className, children }: Readonly<{ id: string, className: string, children: React.ReactNode }>) {
  const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id });

  const style: React.CSSProperties = {
    transform: CSS.Transform.toString(transform),
    transition,
    opacity: isDragging ? 0.3 : 1,
    zIndex: isDragging ? 0 : 1,
    display: 'flex',
    flexDirection: 'column',
  };

  return (
    <div ref={setNodeRef} style={style} className={className}>
      <div className="dash-card" style={{ flex: 1, position: 'relative' }}>
        <div
          {...attributes}
          {...listeners}
          className="dash-drag-handle"
          title="Deplacer le widget"
        >
          <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="var(--text)" strokeWidth="2">
            <circle cx="9" cy="5" r="1" /><circle cx="9" cy="12" r="1" /><circle cx="9" cy="19" r="1" />
            <circle cx="15" cy="5" r="1" /><circle cx="15" cy="12" r="1" /><circle cx="15" cy="19" r="1" />
          </svg>
        </div>
        {children}
      </div>
    </div>
  );
}

// --- Drag Overlay Widget (static, no sortable hooks) ---
function DragOverlayWidget({ className, children }: Readonly<{ className: string, children: React.ReactNode }>) {
  return (
    <div className={className} style={{ display: 'flex', flexDirection: 'column', cursor: 'grabbing' }}>
      <div className="dash-card dash-card--dragging" style={{ flex: 1, position: 'relative' }}>
        {children}
      </div>
    </div>
  );
}

// --- Dashboard ---
const DEFAULT_WIDGETS = ['kpi-month', 'kpi-revenue', 'kpi-pending', 'kpi-treasury', 'chart-revenue', 'list-recent', 'list-feed', 'suggestions'];

export default function Dashboard() {
  const intl = useIntl();
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [activities, setActivities] = useState<ActivityItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [showConsolidated, setShowConsolidated] = useState(false);
  const [activeId, setActiveId] = useState<string | null>(null);

  const [widgets, setWidgets] = useState<string[]>(() => {
    const saved = localStorage.getItem('factura-dashboard-layout');
    return saved ? JSON.parse(saved) : DEFAULT_WIDGETS;
  });

  const sensors = useSensors(
    useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
    useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates })
  );

  useEffect(() => {
    let cancelled = false;

    // SWR: show cached data instantly
    const cachedInv = getCached<{ 'hydra:member': Invoice[] }>('/invoices');
    const cachedActs = getCached<ActivityItem[]>('/dashboard/activities');
    if (cachedInv || cachedActs) {
      queueMicrotask(() => {
        if (cancelled) return;
        if (cachedInv) {
          setInvoices(cachedInv['hydra:member']);
          setLoading(false);
        }
        if (cachedActs) {
          setActivities(cachedActs);
        }
      });
    }

    getInvoices()
      .then((res) => {
        if (cancelled) return;
        const invs = res.data['hydra:member'];
        setInvoices(invs);
        setLoading(false);
        setCache('/invoices', res.data);

        // Charger les events en parallele sans bloquer le rendu
        const recentInvs = [...invs].sort((a, b) => new Date(b.createdAt).getTime() - new Date(a.createdAt).getTime()).slice(0, 5);
        Promise.allSettled(
          recentInvs.map((inv) =>
            getInvoiceEvents(inv.id).then((evts) =>
              evts.data.map((e: InvoiceEvent) => ({
                id: e.id,
                invoiceNumber: inv.number || 'Brouillon',
                eventType: e.eventType,
                date: e.occurredAt,
              }))
            )
          )
        ).then((results) => {
          if (cancelled) return;
          const allActs = results
            .filter((r): r is PromiseFulfilledResult<ActivityItem[]> => r.status === 'fulfilled')
            .flatMap((r) => r.value)
            .sort((a, b) => new Date(b.date).getTime() - new Date(a.date).getTime());
          setActivities(allActs);
          setCache('/dashboard/activities', allActs);
        });
      })
      .catch(() => { if (!cancelled) { setInvoices([]); setLoading(false); } });

    return () => { cancelled = true; };
  }, []);

  const handleDragStart = (event: DragStartEvent) => {
    setActiveId(event.active.id.toString());
  };

  const handleDragEnd = (event: DragEndEvent) => {
    setActiveId(null);
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

  const handleDragCancel = () => {
    setActiveId(null);
  };

  // --- Computed data ---
  const now = new Date();
  const thisMonth = invoices.filter((inv) => {
    const d = new Date(inv.issueDate);
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  });

  const totalHt = thisMonth.reduce((sum, inv) => sum + Number.parseFloat(inv.totalExcludingTax), 0);
  const pending = invoices.filter((inv) => inv.status === 'SENT' || inv.status === 'ACKNOWLEDGED');

  const lastMonth = invoices.filter((inv) => {
    const d = new Date(inv.issueDate);
    const lastM = now.getMonth() === 0 ? 11 : now.getMonth() - 1;
    const lastY = now.getMonth() === 0 ? now.getFullYear() - 1 : now.getFullYear();
    return d.getMonth() === lastM && d.getFullYear() === lastY;
  });

  const totalHtLastMonth = lastMonth.reduce((sum, inv) => sum + Number.parseFloat(inv.totalExcludingTax), 0);
  const trendHt = totalHtLastMonth > 0 ? ((totalHt - totalHtLastMonth) / totalHtLastMonth) * 100 : 100;

  // --- Loading skeleton ---
  if (loading) return (
    <div className="dash">
      <div className="dash-skeleton dash-skeleton-title" />
      <div className="dash-grid">
        <div className="dash-skeleton dash-skeleton-card dash-widget-kpi" />
        <div className="dash-skeleton dash-skeleton-card dash-widget-kpi" />
        <div className="dash-skeleton dash-skeleton-card dash-widget-kpi" />
        <div className="dash-skeleton dash-skeleton-card dash-widget-kpi" />
      </div>
    </div>
  );

  // --- Widget config ---
  const pendingAmount = pending.reduce((s, inv) => s + Number.parseFloat(inv.totalIncludingTax), 0);
  const estimatedTreasury = pendingAmount * 0.85;

  const suggestions: { icon: string; text: string; action: string }[] = [];
  if (invoices.length === 0) {
    suggestions.push({ icon: '\u{1F4DD}', text: 'Creez votre premiere facture pour commencer', action: '/invoices/new' });
  }
  const overdue = invoices.filter(inv => inv.status === 'SENT' && inv.dueDate && new Date(inv.dueDate) < new Date());
  if (overdue.length > 0) {
    suggestions.push({ icon: '\u26A0\uFE0F', text: `${overdue.length} facture(s) en retard de paiement`, action: '/unpaid' });
  }
  if (pending.length > 3) {
    suggestions.push({ icon: '\u{1F4B0}', text: "Pensez a activer l'affacturage pour recevoir vos paiements plus vite", action: '/settings' });
  }
  if (thisMonth.length >= 10) {
    suggestions.push({ icon: '\u{1F3E6}', text: 'Connectez votre banque pour automatiser le rapprochement', action: '/banking' });
  }
  if (suggestions.length === 0) {
    suggestions.push({ icon: '\u2705', text: 'Tout est en ordre. Continuez comme ca !', action: '/' });
  }

  const getWidgetClassName = (wId: string): string => {
    switch (wId) {
      case 'kpi-month': return 'dash-widget-kpi dash-animate dash-animate-d1';
      case 'kpi-revenue': return 'dash-widget-kpi dash-animate dash-animate-d2';
      case 'kpi-pending': return 'dash-widget-kpi dash-animate dash-animate-d3';
      case 'kpi-treasury': return 'dash-widget-kpi dash-animate dash-animate-d4';
      case 'chart-revenue': return 'dash-widget-full dash-animate dash-animate-d5';
      case 'list-recent': return 'dash-widget-wide dash-animate dash-animate-d5';
      case 'list-feed': return 'dash-widget-narrow dash-animate dash-animate-d6';
      case 'suggestions': return 'dash-widget-full dash-animate dash-animate-d6';
      default: return '';
    }
  };

  const renderWidgetContent = (wId: string) => {
    switch (wId) {
      case 'kpi-month':
        return (
          <>
            <h3 className="dash-card-title">{intl.formatMessage({ id: 'dashboard.invoicesThisMonth', defaultMessage: 'Factures du mois' })}</h3>
            <div className="dash-card-body">
              <p className="dash-card-value">{thisMonth.length}</p>
              <Sparkline data={[1, 3, 2, 5, 4, thisMonth.length]} color="var(--accent)" />
            </div>
          </>
        );

      case 'kpi-revenue':
        return (
          <>
            <h3 className="dash-card-title">{intl.formatMessage({ id: 'dashboard.revenueHt', defaultMessage: 'CA HT du mois' })}</h3>
            <div className="dash-card-body">
              <div>
                <p className="dash-card-value">{totalHt.toFixed(2)} &euro;</p>
                <div className={`dash-trend ${trendHt >= 0 ? 'dash-trend--up' : 'dash-trend--down'}`}>
                  {trendHt >= 0 ? '+' : ''}{trendHt.toFixed(1)}% vs. prec
                </div>
              </div>
              <Sparkline data={[totalHtLastMonth * 0.8, totalHtLastMonth, totalHtLastMonth * 1.1, totalHt]} color={trendHt >= 0 ? 'var(--success)' : 'var(--danger)'} />
            </div>
          </>
        );

      case 'kpi-pending':
        return (
          <>
            <h3 className="dash-card-title">{intl.formatMessage({ id: 'dashboard.pending', defaultMessage: 'En attente' })}</h3>
            <div className="dash-card-body">
              <p className="dash-card-value">{pending.length}</p>
              <Sparkline data={[0, 2, 1, 4, pending.length]} color="var(--warning)" />
            </div>
          </>
        );

      case 'kpi-treasury':
        return (
          <>
            <h3 className="dash-card-title">{intl.formatMessage({ id: 'dashboard.treasury', defaultMessage: 'Tresorerie previsionnelle' })}</h3>
            <div className="dash-card-body">
              <div>
                <p className="dash-card-value">{estimatedTreasury.toFixed(0)} &euro;</p>
                <p className="dash-card-sub">{pending.length} facture(s) en attente</p>
              </div>
              <Sparkline data={[pendingAmount * 0.6, pendingAmount * 0.7, pendingAmount * 0.8, estimatedTreasury]} color="#8b5cf6" />
            </div>
          </>
        );

      case 'chart-revenue':
        return (
          <>
            <h3 className="dash-card-title" style={{ textTransform: 'none', letterSpacing: 'normal', fontSize: '0.95rem' }}>
              Evolution du CA (HT)
            </h3>
            <div className="dash-chart-wrapper">
              <RevenueChart invoices={invoices} />
            </div>
          </>
        );

      case 'list-recent':
        return (
          <>
            <h3 className="dash-invoices-title">{intl.formatMessage({ id: 'dashboard.recentInvoices', defaultMessage: 'Dernieres factures' })}</h3>
            {invoices.length === 0 ? (
              <div className="dash-empty">
                <div className="dash-empty-icon">
                  <svg viewBox="0 0 24 24" width="28" height="28" fill="none" stroke="currentColor" strokeWidth="1.5">
                    <rect x="3" y="3" width="18" height="18" rx="2" />
                    <line x1="9" y1="9" x2="15" y2="9" /><line x1="9" y1="15" x2="15" y2="15" />
                  </svg>
                </div>
                <p className="dash-empty-title">Bienvenue !</p>
                <p className="dash-empty-desc">Vous n'avez pas encore emis de facture. Il est temps de rediger la premiere.</p>
                <Link to="/invoices/new" className="dash-empty-btn">Creer une facture</Link>
              </div>
            ) : (
              <>
                {/* Mobile: card list */}
                <div className="dash-invoice-list">
                  {invoices.slice(0, 5).map((inv) => (
                    <Link key={inv.id} to={`/invoices/${inv.id}`} className="dash-invoice-item">
                      <div className="dash-invoice-info">
                        <span className="dash-invoice-number">{inv.number || 'Brouillon'}</span>
                        <span className="dash-invoice-client">{inv.buyer?.name}</span>
                      </div>
                      <div className="dash-invoice-meta">
                        <span className="dash-invoice-amount">{inv.totalIncludingTax} &euro;</span>
                        <StatusBadge status={inv.status} />
                      </div>
                    </Link>
                  ))}
                </div>

                {/* Desktop: table */}
                <div className="dash-table-wrapper">
                  <table className="dash-table">
                    <thead>
                      <tr>
                        <th>{intl.formatMessage({ id: 'invoice.number', defaultMessage: 'Numero' })}</th>
                        <th>{intl.formatMessage({ id: 'invoice.client', defaultMessage: 'Client' })}</th>
                        <th>{intl.formatMessage({ id: 'invoice.date', defaultMessage: 'Date' })}</th>
                        <th className="dash-table-right">{intl.formatMessage({ id: 'invoice.amount', defaultMessage: 'Montant TTC' })}</th>
                        <th className="dash-table-center">{intl.formatMessage({ id: 'invoice.status', defaultMessage: 'Statut' })}</th>
                      </tr>
                    </thead>
                    <tbody>
                      {invoices.slice(0, 5).map((inv) => (
                        <tr key={inv.id}>
                          <td><Link to={`/invoices/${inv.id}`} className="dash-table-link">{inv.number || 'Brouillon'}</Link></td>
                          <td>{inv.buyer?.name}</td>
                          <td>{new Date(inv.issueDate).toLocaleDateString('fr-FR')}</td>
                          <td className="dash-table-right">{inv.totalIncludingTax} &euro;</td>
                          <td className="dash-table-center"><StatusBadge status={inv.status} /></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              </>
            )}
          </>
        );

      case 'list-feed':
        return (
          <>
            <h3 className="dash-invoices-title">{intl.formatMessage({ id: 'dashboard.activityFeed', defaultMessage: "Fil d'actualite" })}</h3>
            {activities.length === 0 ? (
              <p className="dash-feed-empty">Aucune activite recente.</p>
            ) : (
              <div className="dash-feed">
                <div className="dash-feed-line" />
                {activities.slice(0, 5).map((act) => (
                  <div key={act.id} className="dash-feed-item">
                    <div className={`dash-feed-dot ${(() => { if (act.eventType === 'PAID') return 'dash-feed-dot--paid'; if (act.eventType === 'CREATED') return 'dash-feed-dot--created'; return 'dash-feed-dot--default'; })()}`} />
                    <div className="dash-feed-content">
                      <p className="dash-feed-label">Facture {act.invoiceNumber}</p>
                      <p className="dash-feed-desc">{EVENT_LABELS[act.eventType] || `(${act.eventType})`}</p>
                      <p className="dash-feed-time">
                        {new Date(act.date).toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </>
        );

      case 'suggestions':
        return (
          <>
            <h3 className="dash-invoices-title">{intl.formatMessage({ id: 'dashboard.suggestions', defaultMessage: 'Suggestions' })}</h3>
            <div className="dash-suggestions">
              {suggestions.map((s, i) => (
                <Link key={i} to={s.action} className="dash-suggestion-item">
                  <span className="dash-suggestion-icon">{s.icon}</span>
                  <span className="dash-suggestion-text">{s.text}</span>
                  <svg className="dash-suggestion-arrow" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="var(--text)" strokeWidth="2">
                    <polyline points="9 18 15 12 9 6" />
                  </svg>
                </Link>
              ))}
            </div>
          </>
        );

      default:
        return null;
    }
  };

  const renderWidget = (wId: string) => (
    <SortableWidget key={wId} id={wId} className={getWidgetClassName(wId)}>
      {renderWidgetContent(wId)}
    </SortableWidget>
  );

  return (
    <div className="dash">
      <div className="dash-header">
        <h1 className="dash-title">{intl.formatMessage({ id: 'dashboard.title', defaultMessage: 'Tableau de bord' })}</h1>
        <button
          className={`dash-toggle ${showConsolidated ? 'dash-toggle--active' : ''}`}
          onClick={() => setShowConsolidated(!showConsolidated)}
        >
          {showConsolidated
            ? intl.formatMessage({ id: 'dashboard.consolidated', defaultMessage: 'Vue consolidee' })
            : intl.formatMessage({ id: 'dashboard.activeCompany', defaultMessage: 'Entreprise active' })}
        </button>
      </div>

      <DndContext sensors={sensors} collisionDetection={closestCenter} onDragStart={handleDragStart} onDragEnd={handleDragEnd} onDragCancel={handleDragCancel}>
        <SortableContext items={widgets} strategy={verticalListSortingStrategy}>
          <div className="dash-grid">
            {widgets.map(renderWidget)}
          </div>
        </SortableContext>
        <DragOverlay dropAnimation={null}>
          {activeId ? (
            <DragOverlayWidget className={getWidgetClassName(activeId)}>
              {renderWidgetContent(activeId)}
            </DragOverlayWidget>
          ) : null}
        </DragOverlay>
      </DndContext>
    </div>
  );
}
