import { useEffect, useState, useCallback } from 'react';
import api from '../api/factura';
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
  return n.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' \u20AC';
}

// Determine la couleur selon que la valeur utilisateur est meilleure ou moins bonne
// Pour certains KPI, une valeur plus basse est meilleure (delai, impayes)
function diffColor(userValue: number, sectorValue: number, lowerIsBetter: boolean): string {
  if (userValue === sectorValue) return 'var(--text)';
  const isBetter = lowerIsBetter
    ? userValue < sectorValue
    : userValue > sectorValue;
  return isBetter ? '#10b981' : '#ef4444';
}

// Calcule et formate l'ecart entre la valeur utilisateur et la moyenne sectorielle
function formatDiff(userValue: number, sectorValue: number, unit: string, lowerIsBetter: boolean): string {
  const diff = userValue - sectorValue;
  const sign = diff > 0 ? '+' : '';
  const formatted = unit === '\u20AC'
    ? sign + diff.toLocaleString('fr-FR', { minimumFractionDigits: 0, maximumFractionDigits: 0 }) + ' \u20AC'
    : sign + diff.toFixed(1) + unit;
  if (diff === 0) return '=';
  const qualifier = lowerIsBetter
    ? (diff < 0 ? ' (mieux)' : ' (moins bien)')
    : (diff > 0 ? ' (mieux)' : ' (moins bien)');
  return formatted + qualifier;
}

// Carte KPI individuelle comparant une metrique utilisateur a la moyenne sectorielle
function KpiCard({
  title,
  userValue,
  sectorValue,
  unit,
  lowerIsBetter,
}: {
  title: string;
  userValue: number;
  sectorValue: number;
  unit: string;
  lowerIsBetter: boolean;
}) {
  const color = diffColor(userValue, sectorValue, lowerIsBetter);
  const formattedUser = unit === '\u20AC'
    ? formatEur(userValue)
    : userValue.toFixed(1) + unit;
  const formattedSector = unit === '\u20AC'
    ? formatEur(sectorValue)
    : sectorValue.toFixed(1) + unit;

  return (
    <div className="app-card" style={{ minWidth: 0 }}>
      <h3 className="app-card-title">{title}</h3>

      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem', marginTop: '0.5rem' }}>
        {/* Valeur utilisateur */}
        <div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginBottom: '0.25rem' }}>
            Votre valeur
          </div>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>
            {formattedUser}
          </div>
        </div>

        {/* Moyenne sectorielle */}
        <div>
          <div style={{ fontSize: '0.8rem', color: 'var(--text)', marginBottom: '0.25rem' }}>
            Moyenne du secteur
          </div>
          <div style={{ fontSize: '1.1rem', fontWeight: 600, color: 'var(--text)' }}>
            {formattedSector}
          </div>
        </div>

        {/* Ecart */}
        <div
          style={{
            marginTop: 'auto',
            padding: '0.4rem 0.75rem',
            borderRadius: '6px',
            fontSize: '0.85rem',
            fontWeight: 600,
            color,
            backgroundColor: color === '#10b981'
              ? 'rgba(16, 185, 129, 0.1)'
              : color === '#ef4444'
                ? 'rgba(239, 68, 68, 0.1)'
                : 'var(--social-bg)',
            textAlign: 'center',
          }}
        >
          {formatDiff(userValue, sectorValue, unit, lowerIsBetter)}
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
    setLoading(true);
    setError(null);
    try {
      const response = await api.get<BenchmarkData>('/benchmarks', {
        params: { sector: sectorCode },
      });
      setData(response.data);
    } catch {
      setError('Impossible de charger les benchmarks. Veuillez reessayer.');
      setData(null);
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

      <p style={{ color: 'var(--text)', marginBottom: '1.5rem', maxWidth: 650, lineHeight: 1.6 }}>
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
        <div style={{
          padding: '1rem',
          borderRadius: '6px',
          background: 'rgba(239, 68, 68, 0.1)',
          color: '#ef4444',
          marginBottom: '1.5rem',
          fontSize: '0.9rem',
        }}>
          {error}
        </div>
      )}

      {/* Grille des 4 KPI */}
      {data && (
        <div style={{
          display: 'grid',
          gridTemplateColumns: 'repeat(auto-fit, minmax(240px, 1fr))',
          gap: 'clamp(1rem, 2vw, 1.5rem)',
          marginBottom: '2rem',
        }}>
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
      <div style={{
        padding: '1rem 1.25rem',
        borderRadius: '8px',
        background: 'var(--social-bg)',
        border: '1px solid var(--border)',
        fontSize: '0.85rem',
        color: 'var(--text)',
        lineHeight: 1.6,
        maxWidth: 700,
      }}>
        <strong style={{ color: 'var(--text-h)' }}>Confidentialite des donnees</strong>
        <br />
        Les moyennes sectorielles sont calculees a partir de donnees strictement anonymisees
        et agregees. Aucune donnee individuelle n'est partagee ni identifiable.
        Les indicateurs ne sont affiches que lorsque le nombre d'entreprises
        dans le secteur est suffisant pour garantir l'anonymat.
      </div>
    </div>
  );
}
