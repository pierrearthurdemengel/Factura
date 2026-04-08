import { useState, useEffect } from 'react';
import api from '../api/factura';
import { useToast } from '../context/ToastContext';
import './AppLayout.css';

// Types de declencheurs supportes par le moteur de regles
type TriggerType =
  | 'INVOICE_OVERDUE'
  | 'PAYMENT_RECEIVED'
  | 'REVENUE_THRESHOLD'
  | 'VAT_DECLARATION_DUE'
  | 'NEW_CLIENT';

// Types d'actions executables
type ActionType = 'SEND_REMINDER' | 'SEND_NOTIFICATION' | 'GENERATE_REPORT';

// Regle d'automatisation configurable par l'utilisateur
interface AutopilotRule {
  id: string;
  name: string;
  description: string;
  trigger: TriggerType;
  action: ActionType;
  active: boolean;
}

// Entree dans l'historique des actions executees
interface HistoryEntry {
  id: string;
  date: string;
  ruleName: string;
  actionTaken: string;
  result: 'success' | 'failure' | 'pending';
}

// Couleurs associees a chaque type de declencheur
const triggerColors: Record<TriggerType, string> = {
  INVOICE_OVERDUE: '#f97316',
  PAYMENT_RECEIVED: '#22c55e',
  REVENUE_THRESHOLD: '#3b82f6',
  VAT_DECLARATION_DUE: '#a855f7',
  NEW_CLIENT: '#14b8a6',
};

// Libelles lisibles pour chaque type de declencheur
const triggerLabels: Record<TriggerType, string> = {
  INVOICE_OVERDUE: 'Facture en retard',
  PAYMENT_RECEIVED: 'Paiement recu',
  REVENUE_THRESHOLD: 'Seuil de CA',
  VAT_DECLARATION_DUE: 'Declaration TVA',
  NEW_CLIENT: 'Nouveau client',
};

// Libelles lisibles pour chaque type d'action
const actionLabels: Record<ActionType, string> = {
  SEND_REMINDER: 'Envoyer une relance',
  SEND_NOTIFICATION: 'Envoyer une notification',
  GENERATE_REPORT: 'Generer un rapport',
};

// Libelles pour le resultat d'une action dans l'historique
const resultLabels: Record<string, { label: string; color: string }> = {
  success: { label: 'Succes', color: '#22c55e' },
  failure: { label: 'Echec', color: '#ef4444' },
  pending: { label: 'En cours', color: '#f59e0b' },
};

// Regles par defaut si le serveur ne repond pas
const defaultRules: AutopilotRule[] = [
  {
    id: 'rule-1',
    name: 'Relance automatique J+7',
    description: 'Envoie une relance amicale 7 jours apres l\'echeance si la facture est toujours impayee.',
    trigger: 'INVOICE_OVERDUE',
    action: 'SEND_REMINDER',
    active: true,
  },
  {
    id: 'rule-2',
    name: 'Relance formelle J+30',
    description: 'Envoie une relance formelle 30 jours apres l\'echeance avec mention des penalites de retard.',
    trigger: 'INVOICE_OVERDUE',
    action: 'SEND_REMINDER',
    active: true,
  },
  {
    id: 'rule-3',
    name: 'Notification paiement recu',
    description: 'Vous notifie par email des qu\'un paiement est enregistre sur une facture.',
    trigger: 'PAYMENT_RECEIVED',
    action: 'SEND_NOTIFICATION',
    active: true,
  },
  {
    id: 'rule-4',
    name: 'Alerte seuil micro-entreprise',
    description: 'Alerte lorsque votre chiffre d\'affaires approche le plafond de la micro-entreprise.',
    trigger: 'REVENUE_THRESHOLD',
    action: 'SEND_NOTIFICATION',
    active: false,
  },
  {
    id: 'rule-5',
    name: 'Rappel declaration TVA',
    description: 'Rappel automatique avant la date limite de declaration de TVA.',
    trigger: 'VAT_DECLARATION_DUE',
    action: 'SEND_NOTIFICATION',
    active: false,
  },
  {
    id: 'rule-6',
    name: 'Rapport mensuel',
    description: 'Genere un rapport de synthese chaque mois avec les nouveaux clients et le chiffre d\'affaires.',
    trigger: 'NEW_CLIENT',
    action: 'GENERATE_REPORT',
    active: false,
  },
];

export default function AutopilotConfig() {
  const [rules, setRules] = useState<AutopilotRule[]>([]);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [historyLoading, setHistoryLoading] = useState(true);
  const toast = useToast();

  // Chargement des regles depuis le serveur avec fallback sur les regles par defaut
  useEffect(() => {
    api.get('/autopilot/rules')
      .then((res) => {
        const data = res.data['hydra:member'] || res.data;
        if (Array.isArray(data) && data.length > 0) {
          setRules(data);
        } else {
          setRules(defaultRules);
        }
      })
      .catch(() => {
        setRules(defaultRules);
      })
      .finally(() => setLoading(false));
  }, []);

  // Chargement de l'historique des actions executees
  useEffect(() => {
    api.get('/autopilot/history')
      .then((res) => {
        const data = res.data['hydra:member'] || res.data;
        if (Array.isArray(data)) {
          setHistory(data);
        }
      })
      .catch(() => {/* Pas d'historique disponible */})
      .finally(() => setHistoryLoading(false));
  }, []);

  // Bascule l'etat actif/inactif d'une regle
  const toggleRule = (ruleId: string) => {
    setRules((prev) =>
      prev.map((r) => {
        if (r.id !== ruleId) return r;
        const updated = { ...r, active: !r.active };
        // Tentative de persistance cote serveur
        api.put(`/autopilot/rules/${ruleId}`, { active: updated.active })
          .then(() => {
            toast.success(updated.active ? 'Regle activee.' : 'Regle desactivee.');
          })
          .catch(() => {
            toast.info(updated.active ? 'Regle activee (mode local).' : 'Regle desactivee (mode local).');
          });
        return updated;
      }),
    );
  };

  // Nombre de regles actives pour le compteur
  const activeCount = rules.filter((r) => r.active).length;

  if (loading) return (
    <div className="app-container">
      <div className="app-skeleton app-skeleton-title" />
      {[1, 2, 3, 4].map((i) => (
        <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginTop: '0.75rem' }} />
      ))}
    </div>
  );

  return (
    <div className="app-container">
      <h1 className="app-page-title">Autopilot</h1>
      <p style={{ color: 'var(--text)', marginBottom: '1.5rem', fontSize: '0.95rem', lineHeight: 1.6 }}>
        Regles automatisees pour gerer vos factures, relances et notifications.
        Activez ou desactivez chaque regle selon vos besoins.
      </p>

      {/* Compteur de regles actives */}
      <div style={{ display: 'flex', gap: '1rem', marginBottom: '1.5rem', flexWrap: 'wrap' }}>
        <div className="app-card" style={{ textAlign: 'center', padding: '1.25rem', flex: 1, minWidth: 160 }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--accent)' }}>
            {activeCount}
          </div>
          <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>
            {activeCount > 1 ? 'Regles actives' : 'Regle active'}
          </div>
        </div>
        <div className="app-card" style={{ textAlign: 'center', padding: '1.25rem', flex: 1, minWidth: 160 }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: 'var(--text-h)' }}>
            {rules.length}
          </div>
          <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>
            Regles configurees
          </div>
        </div>
        <div className="app-card" style={{ textAlign: 'center', padding: '1.25rem', flex: 1, minWidth: 160 }}>
          <div style={{ fontSize: '1.5rem', fontWeight: 700, color: '#22c55e' }}>
            {history.filter((h) => h.result === 'success').length}
          </div>
          <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.25rem' }}>
            Actions reussies
          </div>
        </div>
      </div>

      {/* Liste des regles */}
      <h2 className="app-section-title">Regles d'automatisation</h2>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.5rem', marginBottom: '2.5rem' }}>
        {rules.map((rule) => {
          const triggerColor = triggerColors[rule.trigger];
          return (
            <div
              key={rule.id}
              className="app-card"
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: '1rem',
                flexWrap: 'wrap',
                opacity: rule.active ? 1 : 0.65,
                borderLeft: `3px solid ${triggerColor}`,
              }}
            >
              {/* Interrupteur actif/inactif */}
              <button
                onClick={() => toggleRule(rule.id)}
                aria-label={rule.active ? `Desactiver ${rule.name}` : `Activer ${rule.name}`}
                style={{
                  width: 44,
                  height: 24,
                  borderRadius: 12,
                  border: 'none',
                  cursor: 'pointer',
                  background: rule.active ? 'var(--accent)' : 'var(--border)',
                  position: 'relative',
                  transition: 'background 0.2s',
                  flexShrink: 0,
                }}
              >
                <span
                  style={{
                    width: 18,
                    height: 18,
                    borderRadius: '50%',
                    background: '#fff',
                    position: 'absolute',
                    top: 3,
                    left: rule.active ? 23 : 3,
                    transition: 'left 0.2s',
                  }}
                />
              </button>

              {/* Nom et description */}
              <div style={{ flex: 1, minWidth: 200 }}>
                <div style={{ fontWeight: 600, color: 'var(--text-h)', fontSize: '0.95rem' }}>
                  {rule.name}
                </div>
                <div style={{ fontSize: '0.85rem', color: 'var(--text)', marginTop: '0.2rem' }}>
                  {rule.description}
                </div>
              </div>

              {/* Badge du type de declencheur */}
              <span
                style={{
                  padding: '4px 12px',
                  borderRadius: '1rem',
                  fontSize: '0.8rem',
                  fontWeight: 600,
                  background: `${triggerColor}15`,
                  color: triggerColor,
                  whiteSpace: 'nowrap',
                }}
              >
                {triggerLabels[rule.trigger]}
              </span>

              {/* Label du type d'action */}
              <span
                style={{
                  fontSize: '0.85rem',
                  color: 'var(--text)',
                  whiteSpace: 'nowrap',
                }}
              >
                {actionLabels[rule.action]}
              </span>
            </div>
          );
        })}
      </div>

      {/* Historique des actions */}
      <h2 className="app-section-title">Historique des actions</h2>

      {historyLoading ? (
        <div>
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginTop: '0.5rem' }} />
          ))}
        </div>
      ) : history.length === 0 ? (
        <div style={{ textAlign: 'center', padding: '3rem 1rem', color: 'var(--text)' }}>
          <p style={{ fontWeight: 600, color: 'var(--text-h)', marginBottom: '0.5rem' }}>
            Aucune action executee
          </p>
          <p style={{ fontSize: '0.9rem' }}>
            L'historique apparaitra ici lorsque vos regles se declencheront.
          </p>
        </div>
      ) : (
        <div style={{ overflowX: 'auto' }}>
          <table style={{ width: '100%', borderCollapse: 'collapse', fontSize: '0.9rem' }}>
            <thead>
              <tr style={{ borderBottom: '2px solid var(--border)' }}>
                <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600 }}>
                  Date
                </th>
                <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600 }}>
                  Regle
                </th>
                <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600 }}>
                  Action
                </th>
                <th style={{ textAlign: 'left', padding: '0.75rem 0.5rem', color: 'var(--text)', fontWeight: 600 }}>
                  Resultat
                </th>
              </tr>
            </thead>
            <tbody>
              {history.map((entry) => {
                const resultInfo = resultLabels[entry.result] || resultLabels.pending;
                return (
                  <tr key={entry.id} style={{ borderBottom: '1px solid var(--border)' }}>
                    <td style={{ padding: '0.75rem 0.5rem', color: 'var(--text-h)', whiteSpace: 'nowrap' }}>
                      {new Date(entry.date).toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </td>
                    <td style={{ padding: '0.75rem 0.5rem', color: 'var(--text-h)', fontWeight: 500 }}>
                      {entry.ruleName}
                    </td>
                    <td style={{ padding: '0.75rem 0.5rem', color: 'var(--text)' }}>
                      {entry.actionTaken}
                    </td>
                    <td style={{ padding: '0.75rem 0.5rem' }}>
                      <span
                        style={{
                          padding: '3px 10px',
                          borderRadius: '1rem',
                          fontSize: '0.8rem',
                          fontWeight: 600,
                          background: `${resultInfo.color}15`,
                          color: resultInfo.color,
                        }}
                      >
                        {resultInfo.label}
                      </span>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
