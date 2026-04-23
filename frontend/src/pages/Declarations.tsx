import { useState, useEffect } from 'react';
import api from '../api/factura';
import { getCached, setCache } from '../utils/apiCache';
import './AppLayout.css';

// Echeances fiscales avec statuts
interface Deadline {
  id: string;
  type: 'tva' | 'urssaf' | 'cfe' | 'ir';
  label: string;
  dueDate: string;
  status: 'pending' | 'done' | 'late';
  amount: string | null;
}

// Donnees de declaration TVA
interface VatBalance {
  collected: string;
  deductible: string;
  balance: string;
  period: string;
}

// Genere les echeances fiscales standard pour l'annee courante (fallback si API indisponible)
function getDefaultDeadlines(year: number): Deadline[] {
  const now = new Date();
  const deadlines: Deadline[] = [];
  // TVA CA3 mensuelle (le 24 du mois suivant)
  for (let m = 1; m <= 12; m++) {
    const dueDate = `${year}-${String(m + 1 > 12 ? 1 : m + 1).padStart(2, '0')}-24`;
    const due = new Date(dueDate);
    const monthName = new Date(year, m - 1).toLocaleDateString('fr-FR', { month: 'long' });
    deadlines.push({
      id: `tva-${m}`,
      type: 'tva',
      label: `Declaration TVA CA3 — ${monthName.charAt(0).toUpperCase() + monthName.slice(1)}`,
      dueDate: m + 1 > 12 ? `${year + 1}-01-24` : dueDate,
      status: due < now ? 'done' : 'pending',
      amount: null,
    });
  }
  // URSSAF trimestriel
  const urssafDates = [
    { q: 'T1', date: `${year}-04-30` },
    { q: 'T2', date: `${year}-07-31` },
    { q: 'T3', date: `${year}-10-31` },
    { q: 'T4', date: `${year + 1}-01-31` },
  ];
  urssafDates.forEach((u, i) => {
    const due = new Date(u.date);
    deadlines.push({
      id: `urssaf-${i}`,
      type: 'urssaf',
      label: `Declaration URSSAF ${u.q}`,
      dueDate: u.date,
      status: due < now ? 'done' : 'pending',
      amount: null,
    });
  });
  // CFE (15 juin et 15 decembre) + IR (declaration de revenus, debut juin)
  deadlines.push({
    id: 'cfe-1', type: 'cfe', label: 'Acompte CFE', dueDate: `${year}-06-15`, status: new Date(`${year}-06-15`) < now ? 'done' : 'pending', amount: null,
  }, {
    id: 'cfe-2', type: 'cfe', label: 'Solde CFE', dueDate: `${year}-12-15`, status: new Date(`${year}-12-15`) < now ? 'done' : 'pending', amount: null,
  }, {
    id: 'ir-1', type: 'ir', label: 'Declaration de revenus', dueDate: `${year}-06-08`, status: new Date(`${year}-06-08`) < now ? 'done' : 'pending', amount: null,
  });
  return deadlines;
}

export default function Declarations() {
  const [activeSection, setActiveSection] = useState<'calendar' | 'tva' | 'urssaf'>('calendar');
  const [vatBalance, setVatBalance] = useState<VatBalance | null>(null);
  const [urssafAmount, setUrssafAmount] = useState<string | null>(null);
  const [deadlines, setDeadlines] = useState<Deadline[]>([]);
  const [loading, setLoading] = useState(true);

  const currentYear = new Date().getFullYear();

  useEffect(() => {
    const controller = new AbortController();
    const { signal } = controller;

    // Check SWR cache for instant display
    const deadlinesParams = { year: String(currentYear) };
    const vatParams = { year: String(currentYear), month: String(new Date().getMonth() + 1) };
    const urssafParams = { year: String(currentYear) };
    const cachedDeadlines = getCached<Deadline[]>('/declarations/deadlines', deadlinesParams);
    const cachedVat = getCached<VatBalance>('/tax/vat/balance', vatParams);
    const cachedUrssaf = getCached<{ totalContributions: string }>('/tax/urssaf/contributions', urssafParams);
    if (cachedDeadlines || cachedVat || cachedUrssaf) {
      queueMicrotask(() => {
        if (signal.aborted) return;
        if (Array.isArray(cachedDeadlines) && cachedDeadlines.length > 0) {
          setDeadlines(cachedDeadlines);
        } else {
          setDeadlines(getDefaultDeadlines(currentYear));
        }
        if (cachedVat) setVatBalance(cachedVat);
        if (cachedUrssaf?.totalContributions) setUrssafAmount(cachedUrssaf.totalContributions);
        setLoading(false);
      });
    }
    // Tenter de charger les echeances depuis l'API, sinon utiliser le calendrier par defaut
    Promise.all([
      api.get<{ 'hydra:member': Deadline[] }>('/declarations/deadlines', { params: { year: currentYear }, signal })
        .then(res => {
          const data = res.data['hydra:member'] || res.data;
          setCache('/declarations/deadlines', data, deadlinesParams);
          return data;
        })
        .catch(() => null),
      api.get<VatBalance>('/tax/vat/balance', { params: { year: currentYear, month: new Date().getMonth() + 1 }, signal })
        .then(res => {
          setCache('/tax/vat/balance', res.data, vatParams);
          return res;
        })
        .catch(() => null),
      api.get<{ totalContributions: string }>('/tax/urssaf/contributions', { params: { year: currentYear }, signal })
        .then(res => {
          setCache('/tax/urssaf/contributions', res.data, urssafParams);
          return res;
        })
        .catch(() => null),
    ]).then(([deadlinesRes, vatRes, urssafRes]) => {
      if (signal.aborted) return;
      if (Array.isArray(deadlinesRes) && deadlinesRes.length > 0) {
        setDeadlines(deadlinesRes);
      } else {
        setDeadlines(getDefaultDeadlines(currentYear));
      }
      if (vatRes?.data) setVatBalance(vatRes.data as VatBalance);
      if (urssafRes?.data?.totalContributions) setUrssafAmount(urssafRes.data.totalContributions);
    }).finally(() => { if (!signal.aborted) setLoading(false); });

    return () => controller.abort();
  }, [currentYear]);

  const sections: { key: typeof activeSection; label: string }[] = [
    { key: 'calendar', label: 'Calendrier' },
    { key: 'tva', label: 'TVA' },
    { key: 'urssaf', label: 'URSSAF' },
  ];

  const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
    pending: { label: 'A faire', color: 'var(--warning)', bg: 'var(--warning-bg)' },
    done: { label: 'Fait', color: 'var(--success)', bg: 'var(--success-bg)' },
    late: { label: 'En retard', color: 'var(--danger)', bg: 'var(--danger-bg)' },
  };

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
      <h1 className="app-page-title">Declarations fiscales</h1>

      {/* Onglets */}
      <div className="app-tabs">
        {sections.map((s) => (
          <button
            key={s.key}
            onClick={() => setActiveSection(s.key)}
            className={`app-tab${activeSection === s.key ? ' app-tab--active' : ''}`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {/* Calendrier des echeances */}
      {activeSection === 'calendar' && (
        <div>
          <p className="app-desc">
            Prochaines echeances fiscales et sociales pour {currentYear}.
          </p>

          <div className="app-list">
            {[...deadlines].sort((a, b) => a.dueDate.localeCompare(b.dueDate)).filter((d) => {
              const due = new Date(d.dueDate);
              const threeMonthsAgo = new Date();
              threeMonthsAgo.setMonth(threeMonthsAgo.getMonth() - 3);
              const sixMonthsAhead = new Date();
              sixMonthsAhead.setMonth(sixMonthsAhead.getMonth() + 6);
              return due >= threeMonthsAgo && due <= sixMonthsAhead;
            }).map((d) => {
              const cfg = statusConfig[d.status];
              const isUpcoming = d.status === 'pending' && new Date(d.dueDate) > new Date();
              return (
                <div
                  key={d.id}
                  className="app-list-item"
                  style={{
                    opacity: d.status === 'done' ? 0.6 : 1,
                    borderLeft: isUpcoming ? '3px solid var(--accent)' : undefined,
                  }}
                >
                  <div className="app-list-item-info">
                    <div className="app-list-item-title">{d.label}</div>
                    <div className="app-list-item-sub">
                      Echeance : {new Date(d.dueDate).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}
                    </div>
                  </div>
                  {d.amount && (
                    <div className="app-list-item-value">{d.amount} €</div>
                  )}
                  <span className="app-status-pill" style={{ background: cfg.bg, color: cfg.color }}>
                    {cfg.label}
                  </span>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Assistant TVA */}
      {activeSection === 'tva' && (
        <div>
          <h2 className="app-section-title app-mt-0">Situation TVA</h2>

          {vatBalance ? (
            <div className="app-kpi-grid">
              <div className="app-card app-kpi-card">
                <div className="app-card-value" style={{ color: 'var(--accent)' }}>
                  {Number.parseFloat(vatBalance.collected).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                </div>
                <div className="app-card-sub">TVA collectee</div>
              </div>
              <div className="app-card app-kpi-card">
                <div className="app-card-value" style={{ color: 'var(--success)' }}>
                  {Number.parseFloat(vatBalance.deductible).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                </div>
                <div className="app-card-sub">TVA deductible</div>
              </div>
              <div className="app-card app-kpi-card">
                <div className="app-card-value" style={{
                  color: Number.parseFloat(vatBalance.balance) >= 0 ? 'var(--danger)' : 'var(--success)',
                }}>
                  {Number.parseFloat(vatBalance.balance).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €
                </div>
                <div className="app-card-sub">
                  {Number.parseFloat(vatBalance.balance) >= 0 ? 'TVA a payer' : 'Credit de TVA'}
                </div>
              </div>
            </div>
          ) : (
            <div className="app-card app-kpi-card" style={{ marginBottom: '2rem' }}>
              <p className="app-card-sub">Aucune donnee TVA disponible pour cette periode.</p>
            </div>
          )}

          <p className="app-desc">
            Les calculs de TVA sont bases sur vos factures emises (TVA collectee) et vos justificatifs
            enregistres (TVA deductible). Verifiez les montants avant de declarer sur impots.gouv.fr.
          </p>
        </div>
      )}

      {/* Assistant URSSAF */}
      {activeSection === 'urssaf' && (
        <div>
          <h2 className="app-section-title app-mt-0">Cotisations URSSAF</h2>

          <div className="app-card" style={{ maxWidth: 500, marginBottom: '2rem' }}>
            <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: '1rem' }}>
              <span className="app-list-item-title">Estimation {currentYear}</span>
              <span className="app-card-value" style={{ color: 'var(--accent)' }}>
                {urssafAmount ? `${Number.parseFloat(urssafAmount).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} €` : '—'}
              </span>
            </div>
            <p className="app-card-sub" style={{ lineHeight: 1.5, margin: 0 }}>
              Estimation basee sur votre chiffre d'affaires facture. Le montant reel depend de votre
              taux de cotisation et de votre type d'activite.
            </p>
          </div>

          <p className="app-desc">
            Declarez vos cotisations sur <a href="https://www.autoentrepreneur.urssaf.fr" target="_blank" rel="noopener noreferrer" style={{ color: 'var(--accent)' }}>autoentrepreneur.urssaf.fr</a> selon
            votre frequence de declaration (mensuelle ou trimestrielle).
          </p>
        </div>
      )}
    </div>
  );
}
