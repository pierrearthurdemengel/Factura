import { useState, useEffect } from 'react';
import api from '../api/factura';
import './AppLayout.css';

// Configuration fiscale (chargee depuis l'API ou valeurs par defaut 2026)
interface TaxConfig {
  microAbatementService: number;
  microAbatementVente: number;
  microUrssafService: number;
  microUrssafVente: number;
  reelUrssafRate: number;
  sasuChargesRate: number;
  sasuRemunerationRate: number;
  sasuISRate: number;
  eiUrssafRate: number;
  irBrackets: { limit: number; rate: number }[];
  year: number;
}

const defaultTaxConfig: TaxConfig = {
  microAbatementService: 0.50,
  microAbatementVente: 0.71,
  microUrssafService: 0.212,
  microUrssafVente: 0.123,
  reelUrssafRate: 0.45,
  sasuChargesRate: 0.30,
  sasuRemunerationRate: 0.60,
  sasuISRate: 0.25,
  eiUrssafRate: 0.212,
  irBrackets: [
    { limit: 11497, rate: 0 },
    { limit: 29315, rate: 0.11 },
    { limit: 82341, rate: 0.30 },
    { limit: 177106, rate: 0.41 },
    { limit: Infinity, rate: 0.45 },
  ],
  year: 2026,
};

function simulateMicroVsReel(ca: number, charges: number, activity: 'service' | 'vente', config: TaxConfig) {
  const microAbatement = activity === 'vente' ? config.microAbatementVente : config.microAbatementService;
  const microTaxableIncome = ca * (1 - microAbatement);
  const microUrssaf = ca * (activity === 'vente' ? config.microUrssafVente : config.microUrssafService);
  const microNet = ca - microUrssaf;

  const reelTaxableIncome = ca - charges;
  const reelUrssaf = Math.max(0, reelTaxableIncome * config.reelUrssafRate);
  const reelNet = ca - charges - reelUrssaf;

  return {
    micro: { taxableIncome: microTaxableIncome, urssaf: microUrssaf, net: microNet },
    reel: { taxableIncome: reelTaxableIncome, urssaf: reelUrssaf, net: reelNet },
    recommendation: charges > ca * microAbatement ? 'reel' : 'micro',
  };
}

function simulateIR(revenuNet: number, parts: number, config: TaxConfig) {
  const quotient = revenuNet / parts;
  let impot = 0;
  let prev = 0;

  for (const bracket of config.irBrackets) {
    if (quotient > prev) {
      const taxable = Math.min(quotient, bracket.limit) - prev;
      impot += taxable * bracket.rate;
    }
    prev = bracket.limit;
  }

  return { impot: impot * parts, tauxEffectif: revenuNet > 0 ? (impot * parts / revenuNet) * 100 : 0 };
}

type SimType = 'micro-reel' | 'ir' | 'ei-sasu';

export default function Simulators() {
  const [activeSim, setActiveSim] = useState<SimType>('micro-reel');
  const [taxConfig, setTaxConfig] = useState<TaxConfig>(defaultTaxConfig);

  useEffect(() => {
    api.get<TaxConfig>('/tax/config')
      .then(res => {
        if (res.data?.irBrackets) setTaxConfig(res.data);
      })
      .catch(() => {/* Utiliser la configuration par defaut */});
  }, []);

  // Micro vs Reel
  const [ca, setCa] = useState(60000);
  const [charges, setCharges] = useState(15000);
  const [activity, setActivity] = useState<'service' | 'vente'>('service');
  const microResult = simulateMicroVsReel(ca, charges, activity, taxConfig);

  // IR
  const [irRevenu, setIrRevenu] = useState(40000);
  const [irParts, setIrParts] = useState(1);
  const irResult = simulateIR(irRevenu, irParts, taxConfig);

  // EI vs SASU
  const [sasuCa, setSasuCa] = useState(80000);
  const sasuCharges = sasuCa * taxConfig.sasuChargesRate;
  const sasuRemuneration = (sasuCa - sasuCharges) * taxConfig.sasuRemunerationRate;
  const sasuDividendes = sasuCa - sasuCharges - sasuRemuneration - ((sasuCa - sasuCharges) * taxConfig.sasuISRate);
  const eiNet = sasuCa - sasuCa * taxConfig.eiUrssafRate;

  const sims: { key: SimType; label: string }[] = [
    { key: 'micro-reel', label: 'Micro vs Reel' },
    { key: 'ir', label: 'Estimation IR' },
    { key: 'ei-sasu', label: 'EI vs SASU' },
  ];

  const fmt = (n: number) => n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

  return (
    <div className="app-container">
      <h1 className="app-page-title">Simulateurs</h1>
      <p className="app-desc" style={{ maxWidth: 650 }}>
        Estimez votre situation fiscale avec nos simulateurs (bareme {taxConfig.year}). Ces calculs sont indicatifs et ne remplacent pas les conseils d'un expert-comptable.
      </p>

      {/* Onglets */}
      <div className="app-tabs">
        {sims.map((s) => (
          <button
            key={s.key}
            onClick={() => setActiveSim(s.key)}
            className={`app-tab${activeSim === s.key ? ' app-tab--active' : ''}`}
          >
            {s.label}
          </button>
        ))}
      </div>

      {/* Micro vs Reel */}
      {activeSim === 'micro-reel' && (
        <div style={{ maxWidth: 600 }}>
          <h2 className="app-section-title app-mt-0">Micro-entreprise vs Regime reel</h2>

          <div className="app-form-row">
            <div className="app-form-group">
              <label className="app-label">Type d'activite</label>
              <select value={activity} onChange={(e) => setActivity(e.target.value as 'service' | 'vente')} className="app-select">
                <option value="service">Prestation de services (BNC)</option>
                <option value="vente">Vente de marchandises (BIC)</option>
              </select>
            </div>
          </div>

          <div className="app-form-row">
            <div className="app-form-group">
              <label className="app-label">Chiffre d'affaires annuel</label>
              <input type="range" min={0} max={200000} step={5000} value={ca} onChange={(e) => setCa(parseInt(e.target.value))} className="app-input" style={{ padding: 0, border: 'none', accentColor: 'var(--accent)' }} />
              <span className="app-card-sub" style={{ fontWeight: 600, color: 'var(--text-h)' }}>{fmt(ca)} EUR</span>
            </div>
            <div className="app-form-group">
              <label className="app-label">Charges reelles annuelles</label>
              <input type="range" min={0} max={100000} step={1000} value={charges} onChange={(e) => setCharges(parseInt(e.target.value))} className="app-input" style={{ padding: 0, border: 'none', accentColor: 'var(--accent)' }} />
              <span className="app-card-sub" style={{ fontWeight: 600, color: 'var(--text-h)' }}>{fmt(charges)} EUR</span>
            </div>
          </div>

          <div className="app-kpi-grid" style={{ gridTemplateColumns: '1fr 1fr', marginTop: '0.5rem' }}>
            <div className="app-card" style={{ borderColor: microResult.recommendation === 'micro' ? 'var(--accent)' : undefined, borderWidth: microResult.recommendation === 'micro' ? 2 : undefined }}>
              <h3 className="app-card-title" style={{ textTransform: 'none', fontSize: '1rem' }}>Micro-entreprise</h3>
              <div className="app-card-text">URSSAF : <strong>{fmt(microResult.micro.urssaf)} EUR</strong></div>
              <div className="app-card-text">Revenu imposable : <strong>{fmt(microResult.micro.taxableIncome)} EUR</strong></div>
              <p className="app-card-value" style={{ fontSize: '1.1rem', color: 'var(--accent)', marginTop: '0.5rem' }}>Net : {fmt(microResult.micro.net)} EUR</p>
            </div>
            <div className="app-card" style={{ borderColor: microResult.recommendation === 'reel' ? 'var(--accent)' : undefined, borderWidth: microResult.recommendation === 'reel' ? 2 : undefined }}>
              <h3 className="app-card-title" style={{ textTransform: 'none', fontSize: '1rem' }}>Regime reel</h3>
              <div className="app-card-text">Charges deductibles : <strong>{fmt(charges)} EUR</strong></div>
              <div className="app-card-text">Cotisations estimees : <strong>{fmt(microResult.reel.urssaf)} EUR</strong></div>
              <p className="app-card-value" style={{ fontSize: '1.1rem', color: 'var(--accent)', marginTop: '0.5rem' }}>Net : {fmt(microResult.reel.net)} EUR</p>
            </div>
          </div>

          <div className="app-alert app-alert--info" style={{ marginTop: '1rem', justifyContent: 'center', fontWeight: 600 }}>
            {microResult.recommendation === 'micro' ? 'La micro-entreprise est plus avantageuse dans votre cas' : 'Le regime reel semble plus avantageux grace a vos charges'}
          </div>
        </div>
      )}

      {/* Estimation IR */}
      {activeSim === 'ir' && (
        <div style={{ maxWidth: 500 }}>
          <h2 className="app-section-title app-mt-0">Estimation impot sur le revenu</h2>

          <div className="app-form-group">
            <label className="app-label">Revenu net imposable</label>
            <input type="range" min={0} max={200000} step={1000} value={irRevenu} onChange={(e) => setIrRevenu(parseInt(e.target.value))} className="app-input" style={{ padding: 0, border: 'none', accentColor: 'var(--accent)' }} />
            <span className="app-card-sub" style={{ fontWeight: 600, color: 'var(--text-h)' }}>{fmt(irRevenu)} EUR</span>
          </div>

          <div className="app-form-group">
            <label className="app-label">Nombre de parts</label>
            <select value={irParts} onChange={(e) => setIrParts(parseFloat(e.target.value))} className="app-select">
              <option value={1}>1 part (celibataire)</option>
              <option value={1.5}>1.5 parts (parent isole, 1 enfant)</option>
              <option value={2}>2 parts (couple)</option>
              <option value={2.5}>2.5 parts (couple, 1 enfant)</option>
              <option value={3}>3 parts (couple, 2 enfants)</option>
              <option value={3.5}>3.5 parts (couple, 3 enfants)</option>
            </select>
          </div>

          <div className="app-card app-kpi-card">
            <p className="app-card-value" style={{ fontSize: '2rem', color: 'var(--accent)' }}>
              {fmt(irResult.impot)} EUR
            </p>
            <p className="app-card-sub">
              Impot estime (taux effectif : {irResult.tauxEffectif.toFixed(1)}%)
            </p>
            <p className="app-card-sub">
              Soit {fmt(irResult.impot / 12)} EUR/mois
            </p>
          </div>
        </div>
      )}

      {/* EI vs SASU */}
      {activeSim === 'ei-sasu' && (
        <div style={{ maxWidth: 600 }}>
          <h2 className="app-section-title app-mt-0">Entreprise individuelle vs SASU</h2>

          <div className="app-form-group">
            <label className="app-label">Chiffre d'affaires annuel</label>
            <input type="range" min={0} max={300000} step={5000} value={sasuCa} onChange={(e) => setSasuCa(parseInt(e.target.value))} className="app-input" style={{ padding: 0, border: 'none', accentColor: 'var(--accent)' }} />
            <span className="app-card-sub" style={{ fontWeight: 600, color: 'var(--text-h)' }}>{fmt(sasuCa)} EUR</span>
          </div>

          <div className="app-kpi-grid" style={{ gridTemplateColumns: '1fr 1fr' }}>
            <div className="app-card">
              <h3 className="app-card-title" style={{ textTransform: 'none', fontSize: '1rem' }}>EI (micro)</h3>
              <div className="app-card-text">URSSAF ({(taxConfig.eiUrssafRate * 100).toFixed(1)}%) : <strong>{fmt(sasuCa * taxConfig.eiUrssafRate)} EUR</strong></div>
              <p className="app-card-value" style={{ fontSize: '1.1rem', color: 'var(--accent)', marginTop: '0.5rem' }}>Net : {fmt(eiNet)} EUR</p>
            </div>
            <div className="app-card">
              <h3 className="app-card-title" style={{ textTransform: 'none', fontSize: '1rem' }}>SASU</h3>
              <div className="app-card-text">Charges (~{(taxConfig.sasuChargesRate * 100).toFixed(0)}%) : <strong>{fmt(sasuCharges)} EUR</strong></div>
              <div className="app-card-text">Remuneration : <strong>{fmt(sasuRemuneration)} EUR</strong></div>
              <div className="app-card-text">Dividendes (apres IS) : <strong>{fmt(Math.max(0, sasuDividendes))} EUR</strong></div>
              <p className="app-card-value" style={{ fontSize: '1.1rem', color: 'var(--accent)', marginTop: '0.5rem' }}>
                Total : {fmt(sasuRemuneration + Math.max(0, sasuDividendes))} EUR
              </p>
            </div>
          </div>

          <p className="app-card-sub" style={{ marginTop: '1rem', lineHeight: 1.5 }}>
            Ces calculs sont simplifies. En SASU, l'optimisation remuneration/dividendes depend de nombreux facteurs
            (protection sociale souhaitee, projection de revenus, etc.). Consultez un expert-comptable.
          </p>
        </div>
      )}
    </div>
  );
}
