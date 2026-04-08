import { useState } from 'react';
import './AppLayout.css';

// Simulateur de prix pour le plan Succes Partage
function simulate(annualRevenue: number): { fee: number; effectiveRate: number; monthlyEquivalent: number } {
  const threshold = 50000;
  const rate = 0.001;
  const cap = 588;

  if (annualRevenue <= threshold) {
    return { fee: 0, effectiveRate: 0, monthlyEquivalent: 0 };
  }

  const taxable = annualRevenue - threshold;
  let fee = taxable * rate;
  if (fee > cap) fee = cap;

  const effectiveRate = annualRevenue > 0 ? (fee / annualRevenue) * 100 : 0;
  const monthlyEquivalent = fee / 12;

  return { fee, effectiveRate, monthlyEquivalent };
}

export default function Pricing() {
  const [revenue, setRevenue] = useState(80000);
  const result = simulate(revenue);

  const plans = [
    {
      name: 'Succes Partage',
      price: 'Gratuit + 0.1%',
      description: 'Ideal pour les freelances et TPE. Gratuit sous 50k EUR/an.',
      features: [
        'Gratuit sous 50 000 EUR/an de CA facture',
        '0.1% au-dela, plafonne a 588 EUR/an (49 EUR/mois)',
        'Factures illimitees',
        'Factur-X + UBL + Chorus Pro',
        'Relances automatiques',
        'Facturation annuelle (pas de frais mensuels)',
      ],
      highlighted: true,
      cta: 'Commencer gratuitement',
    },
    {
      name: 'Pro',
      price: '14 EUR/mois',
      description: 'Montant fixe previsible. Pour ceux qui preferent la simplicite.',
      features: [
        'Montant fixe : 14 EUR/mois HT',
        'Factures illimitees',
        'Factur-X + UBL + Chorus Pro',
        'Relances automatiques',
        'Assistant fiscal',
        'Export FEC',
      ],
      highlighted: false,
      cta: 'Choisir Pro',
    },
    {
      name: 'Cabinet',
      price: 'A partir de 79 EUR/mois',
      description: 'Pour les experts-comptables gerant plusieurs clients.',
      features: [
        '79 EUR/mois base (20 clients inclus)',
        '+2 EUR/client actif supplementaire',
        'Portail comptable white-label',
        'Vue consolidee multi-clients',
        'Export FEC groupe',
        'Branding personnalise',
      ],
      highlighted: false,
      cta: 'Contacter l\'equipe',
    },
  ];

  return (
    <div className="app-container" style={{ textAlign: 'left' }}>
      <h1 className="app-page-title">Tarifs</h1>
      <p style={{ color: 'var(--text)', marginBottom: '2rem', maxWidth: 650, lineHeight: 1.6 }}>
        Un modele de prix aligne sur votre reussite. Vous ne payez que si votre activite genere du chiffre d'affaires.
      </p>

      {/* Cartes de plans */}
      <div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit, minmax(280px, 1fr))', gap: '1rem', marginBottom: '3rem' }}>
        {plans.map((plan) => (
          <div
            key={plan.name}
            className="app-card"
            style={{
              display: 'flex', flexDirection: 'column', padding: '1.5rem',
              border: plan.highlighted ? '2px solid var(--accent)' : undefined,
              position: 'relative',
            }}
          >
            {plan.highlighted && (
              <div style={{
                position: 'absolute', top: -12, left: '50%', transform: 'translateX(-50%)',
                background: 'var(--accent)', color: '#fff', padding: '2px 12px',
                borderRadius: '1rem', fontSize: '0.75rem', fontWeight: 700,
              }}>
                Recommande
              </div>
            )}
            <h3 style={{ margin: '0 0 0.25rem', color: 'var(--text-h)', fontSize: '1.2rem' }}>{plan.name}</h3>
            <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--accent)', margin: '0.5rem 0' }}>
              {plan.price}
            </div>
            <p style={{ fontSize: '0.9rem', color: 'var(--text)', marginBottom: '1rem', flex: 1 }}>
              {plan.description}
            </p>
            <ul style={{ listStyle: 'none', padding: 0, margin: '0 0 1.5rem', fontSize: '0.85rem' }}>
              {plan.features.map((f) => (
                <li key={f} style={{ padding: '0.3rem 0', color: 'var(--text)', display: 'flex', gap: '0.5rem' }}>
                  <span style={{ color: 'var(--accent)' }}>✓</span> {f}
                </li>
              ))}
            </ul>
            <button className={plan.highlighted ? 'app-btn-primary' : 'app-btn-outline'} style={{ width: '100%' }}>
              {plan.cta}
            </button>
          </div>
        ))}
      </div>

      {/* Simulateur */}
      <div className="app-card" style={{ maxWidth: 600, padding: '1.5rem' }}>
        <h2 style={{ margin: '0 0 0.5rem', color: 'var(--text-h)', fontSize: '1.1rem' }}>
          Simulateur de prix — Succes Partage
        </h2>
        <p style={{ fontSize: '0.85rem', color: 'var(--text)', marginBottom: '1rem' }}>
          Deplacez le curseur pour estimer vos frais annuels.
        </p>

        <div style={{ marginBottom: '1rem' }}>
          <label style={{ display: 'block', fontSize: '0.85rem', fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>
            Chiffre d'affaires annuel facture : {revenue.toLocaleString('fr-FR')} EUR
          </label>
          <input
            type="range"
            min={0}
            max={500000}
            step={5000}
            value={revenue}
            onChange={(e) => setRevenue(parseInt(e.target.value))}
            style={{ width: '100%', accentColor: 'var(--accent)' }}
          />
          <div style={{ display: 'flex', justifyContent: 'space-between', fontSize: '0.75rem', color: 'var(--text)' }}>
            <span>0 EUR</span>
            <span>500 000 EUR</span>
          </div>
        </div>

        <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr 1fr', gap: '1rem', textAlign: 'center' }}>
          <div>
            <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--accent)' }}>
              {result.fee.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} EUR
            </div>
            <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Frais annuels</div>
          </div>
          <div>
            <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>
              {result.monthlyEquivalent.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })} EUR
            </div>
            <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Equivalent mensuel</div>
          </div>
          <div>
            <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>
              {result.effectiveRate.toFixed(3)}%
            </div>
            <div style={{ fontSize: '0.8rem', color: 'var(--text)' }}>Taux effectif</div>
          </div>
        </div>

        {revenue <= 50000 && (
          <div style={{ marginTop: '1rem', padding: '0.75rem', background: 'rgba(34,197,94,0.1)', borderRadius: '6px', textAlign: 'center', fontSize: '0.9rem', color: '#22c55e', fontWeight: 600 }}>
            Totalement gratuit sous 50 000 EUR de CA annuel
          </div>
        )}
        {revenue > 50000 && result.fee < 168 && (
          <div style={{ marginTop: '1rem', padding: '0.75rem', background: 'rgba(37,99,235,0.1)', borderRadius: '6px', textAlign: 'center', fontSize: '0.9rem', color: '#2563eb' }}>
            Economie de <strong>{(168 - result.fee).toFixed(2)} EUR/an</strong> par rapport au plan Pro fixe
          </div>
        )}
      </div>
    </div>
  );
}
