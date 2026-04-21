import { useEffect, useState, useCallback } from 'react';
import api from '../api/factura';
import { getCached, setCache } from '../utils/apiCache';
import './AppLayout.css';

// Reponse de l'endpoint /benchmarks
interface BenchmarkData {
  averagePaymentDelay: number;
  unpaidRate: number;
  averageRevenue: number;
  netMargin: number;
  userPaymentDelay: number;
  userUnpaidRate: number;
  userRevenue: number;
  userNetMargin: number;
}

// Secteurs NAF proposes dans le selecteur
const SECTORS = [
  { code: '62', label: 'Informatique' },
  { code: '69', label: 'Comptabilite' },
  { code: '70', label: 'Conseil' },
  { code: '71', label: 'Architecture' },
  { code: '73', label: 'Communication' },
  { code: '74', label: 'Design' },
] as const;

// Formate un nombre en euros avec separateur de milliers
function formatEur(n: number): string {
  return (n ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' \u20AC';
}

// Determine la couleur selon que la valeur utilisateur est meilleure ou moins bonne
// Pour certains KPI, une valeur plus basse est meilleure (delai, impayes)
function diffColor(userValue: number, sectorValue: number, lowerIsBetter: boolean): string {
  if (userValue === sectorValue) return 'var(--text)';
  const isBetter = lowerIsBetter
    ? userValue < sectorValue
    : userValue > sectorValue;
  return isBetter ? 'var(--success)' : 'var(--danger)';
}

// Calcule et formate l'ecart entre la valeur utilisateur et la moyenne sectorielle
function formatDiff(userValue: number, sectorValue: number, unit: string, lowerIsBetter: boolean): string {
  const diff = (userValue ?? 0) - (sectorValue ?? 0);
  const sign = diff > 0 ? '+' : '';
  const formatted = unit === '\u20AC'
    ? sign + diff.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' \u20AC'
    : sign + diff.toFixed(1) + unit;
  if (diff === 0) return '=';
  let qualifier: string;
  if (lowerIsBetter) {
    qualifier = diff < 0 ? ' (mieux)' : ' (moins bien)';
  } else {
    qualifier = diff > 0 ? ' (mieux)' : ' (moins bien)';
  }
  return formatted + qualifier;
}

// Carte KPI individuelle comparant une metrique utilisateur a la moyenne sectorielle
function KpiCard({
  title,
  userValue,
  sectorValue,
  unit,
  lowerIsBetter,
}: Readonly<{
  title: string;
  userValue: number;
  sectorValue: number;
  unit: string;
  lowerIsBetter: boolean;
}>) {
  const safeUser = userValue ?? 0;
  const safeSector = sectorValue ?? 0;
  const color = diffColor(safeUser, safeSector, lowerIsBetter);
  const formattedUser = unit === '\u20AC'
    ? formatEur(safeUser)
    : safeUser.toFixed(1) + unit;
  const formattedSector = unit === '\u20AC'
    ? formatEur(safeSector)
    : safeSector.toFixed(1) + unit;

  return (
    <div className="app-card">
      <h3 className="app-card-title">{title}</h3>

      <div className="app-card-body">
        {/* Valeur utilisateur */}
        <div>
          <p className="app-card-sub">Votre valeur</p>
          <p className="app-card-value">{formattedUser}</p>
        </div>

        {/* Moyenne sectorielle */}
        <div style={{ marginTop: '0.75rem' }}>
          <p className="app-card-sub">Moyenne du secteur</p>
          <p className="app-card-text" style={{ fontWeight: 600 }}>{formattedSector}</p>
        </div>

        {/* Ecart */}
        <div
          className="app-status-pill"
          style={{
            marginTop: 'auto',
            padding: '0.4rem 0.75rem',
            borderRadius: '6px',
            fontWeight: 600,
            color,
            backgroundColor: (() => {
              if (color === 'var(--success)') return 'var(--success-bg)';
              if (color === 'var(--danger)') return 'var(--danger-bg)';
              return 'var(--social-bg)';
            })(),
            textAlign: 'center',
            display: 'block',
            width: '100%',
            boxSizing: 'border-box',
          }}
        >
          {formatDiff(safeUser, safeSector, unit, lowerIsBetter)}
        </div>
      </div>
    </div>
  );
}

// Page de benchmarks sectoriels : compare les metriques de l'utilisateur
// aux moyennes anonymisees de son secteur d'activite.
export default function Benchmarks() {
  const [sector, setSector] = useState(SECTORS[0].code);
  const [data, setData] = useState<BenchmarkData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  // Charge les donnees de benchmark pour le secteur selectionne
  const fetchBenchmarks = useCallback(async (sectorCode: string) => {
    const params = { sector: sectorCode };
    const cached = getCached<BenchmarkData>('/benchmarks', params);
    if (cached) {
      setData(cached);
      setLoading(false);
    } else {
      setLoading(true);
    }
    setError(null);
    try {
      const response = await api.get<BenchmarkData>('/benchmarks', {
        params,
      });
      setData(response.data);
      setCache('/benchmarks', response.data, params);
    } catch {
      if (!cached) {
        setError('Impossible de charger les benchmarks. Veuillez reessayer.');
        setData(null);
      }
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchBenchmarks(sector);
  }, [sector, fetchBenchmarks]);

  // Gestion du changement de secteur
  const handleSectorChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
    setSector(e.target.value);
  };

  // Squelette de chargement
  if (loading) {
    return (
      <div className="app-container">
        <div className="app-skeleton app-skeleton-title" />
        <div className="app-skeleton" style={{ width: '250px', height: '40px', marginBottom: '1.5rem', borderRadius: '6px' }} />
        <div className="app-grid">
          <div className="app-skeleton app-skeleton-card" />
          <div className="app-skeleton app-skeleton-card" />
          <div className="app-skeleton app-skeleton-card" />
          <div className="app-skeleton app-skeleton-card" />
        </div>
      </div>
    );
  }

  return (
    <div className="app-container">
      <h1 className="app-page-title">Benchmarks sectoriels</h1>

      <p className="app-desc" style={{ maxWidth: 650 }}>
        Comparez vos indicateurs cles avec les moyennes de votre secteur d'activite.
        Les donnees sont calculees a partir de statistiques anonymisees.
      </p>

      {/* Selecteur de secteur NAF */}
      <div className="app-form-group" style={{ maxWidth: '300px', marginBottom: '2rem' }}>
        <label className="app-label" htmlFor="sector-select">Secteur d'activite (code NAF)</label>
        <select
          id="sector-select"
          value={sector}
          onChange={handleSectorChange}
          className="app-select"
        >
          {SECTORS.map((s) => (
            <option key={s.code} value={s.code}>
              {s.code} — {s.label}
            </option>
          ))}
        </select>
      </div>

      {/* Message d'erreur */}
      {error && (
        <div className="app-alert app-alert--error">
          {error}
        </div>
      )}

      {/* Grille des 4 KPI */}
      {data && (
        <div className="app-kpi-grid" style={{ marginBottom: '2rem' }}>
          <KpiCard
            title="Delai moyen de paiement (jours)"
            userValue={data.userPaymentDelay}
            sectorValue={data.averagePaymentDelay}
            unit=" j"
            lowerIsBetter={true}
          />
          <KpiCard
            title="Taux d'impayes (%)"
            userValue={data.userUnpaidRate}
            sectorValue={data.unpaidRate}
            unit="%"
            lowerIsBetter={true}
          />
          <KpiCard
            title="Chiffre d'affaires moyen"
            userValue={data.userRevenue}
            sectorValue={data.averageRevenue}
            unit={'\u20AC'}
            lowerIsBetter={false}
          />
          <KpiCard
            title="Marge nette (%)"
            userValue={data.userNetMargin}
            sectorValue={data.netMargin}
            unit="%"
            lowerIsBetter={false}
          />
        </div>
      )}

      {/* Mention d'anonymisation */}
      <div className="app-alert app-alert--info" style={{ maxWidth: 700 }}>
        <div>
          <span className="app-alert-title">Confidentialite des donnees</span>
          <br />
          <span className="app-alert-sub">
            Les moyennes sectorielles sont calculees a partir de donnees strictement anonymisees
            et agregees. Aucune donnee individuelle n'est partagee ni identifiable.
            Les indicateurs ne sont affiches que lorsque le nombre d'entreprises
            dans le secteur est suffisant pour garantir l'anonymat.
          </span>
        </div>
      </div>
    </div>
  );
}
