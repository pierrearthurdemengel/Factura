import { useState, useEffect } from 'react';
import api from '../api/factura';
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

export default function Declarations() {
  const [activeSection, setActiveSection] = useState<'calendar' | 'tva' | 'urssaf'>('calendar');
  const [vatBalance, setVatBalance] = useState<VatBalance | null>(null);
  const [urssafAmount, setUrssafAmount] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);

  // Echeances fictives basees sur le calendrier fiscal standard
  const currentYear = new Date().getFullYear();
  const deadlines: Deadline[] = [
    { id: '1', type: 'tva', label: 'Declaration TVA CA3 — Avril', dueDate: `${currentYear}-05-24`, status: 'pending', amount: null },
    { id: '2', type: 'urssaf', label: 'Declaration URSSAF T1', dueDate: `${currentYear}-04-30`, status: 'pending', amount: null },
    { id: '3', type: 'tva', label: 'Declaration TVA CA3 — Mars', dueDate: `${currentYear}-04-24`, status: 'done', amount: '1 250.00' },
    { id: '4', type: 'urssaf', label: 'Declaration URSSAF — Mars', dueDate: `${currentYear}-03-31`, status: 'done', amount: '2 340.00' },
    { id: '5', type: 'cfe', label: 'Acompte CFE', dueDate: `${currentYear}-06-15`, status: 'pending', amount: null },
    { id: '6', type: 'ir', label: 'Declaration de revenus', dueDate: `${currentYear}-06-08`, status: 'pending', amount: null },
  ];

  useEffect(() => {
    Promise.all([
      api.get('/tax/vat/balance', { params: { year: currentYear, month: new Date().getMonth() + 1 } }).catch(() => null),
      api.get('/tax/urssaf/contributions', { params: { year: currentYear } }).catch(() => null),
    ]).then(([vatRes, urssafRes]) => {
      if (vatRes?.data) setVatBalance(vatRes.data);
      if (urssafRes?.data?.totalContributions) setUrssafAmount(urssafRes.data.totalContributions);
    }).finally(() => setLoading(false));
  }, [currentYear]);

  const sections: { key: typeof activeSection; label: string }[] = [
    { key: 'calendar', label: 'Calendrier' },
    { key: 'tva', label: 'TVA' },
    { key: 'urssaf', label: 'URSSAF' },
  ];

  const statusConfig: Record<string, { label: string; color: string; bg: string }> = {
    pending: { label: 'A faire', color: '#f59e0b', bg: 'rgba(245,158,11,0.1)' },
    done: { label: 'Fait', color: '#22c55e', bg: 'rgba(34,197,94,0.1)' },
    late: { label: 'En retard', color: '#ef4444', bg: 'rgba(239,68,68,0.1)' },
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
            {deadlines.sort((a, b) => a.dueDate.localeCompare(b.dueDate)).map((d) => {
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
                    <div className="app-list-item-value">{d.amount} EUR</div>
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
                <div className="app-card-value" style={{ color: '#2563eb' }}>
                  {parseFloat(vatBalance.collected).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                </div>
                <div className="app-card-sub">TVA collectee</div>
              </div>
              <div className="app-card app-kpi-card">
                <div className="app-card-value" style={{ color: '#22c55e' }}>
                  {parseFloat(vatBalance.deductible).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                </div>
                <div className="app-card-sub">TVA deductible</div>
              </div>
              <div className="app-card app-kpi-card">
                <div className="app-card-value" style={{
                  color: parseFloat(vatBalance.balance) >= 0 ? '#ef4444' : '#22c55e',
                }}>
                  {parseFloat(vatBalance.balance).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR
                </div>
                <div className="app-card-sub">
                  {parseFloat(vatBalance.balance) >= 0 ? 'TVA a payer' : 'Credit de TVA'}
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
                {urssafAmount ? `${parseFloat(urssafAmount).toLocaleString('fr-FR', { minimumFractionDigits: 2 })} EUR` : '—'}
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
