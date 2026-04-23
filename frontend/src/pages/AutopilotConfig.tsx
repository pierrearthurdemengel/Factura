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
  PAYMENT_RECEIVED: 'var(--success)',
  REVENUE_THRESHOLD: 'var(--accent)',
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
  success: { label: 'Succes', color: 'var(--success)' },
  failure: { label: 'Echec', color: 'var(--danger)' },
  pending: { label: 'En cours', color: 'var(--warning)' },
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

// Parse les regles depuis la reponse API ou retourne les regles par defaut
function parseRulesResponse(res: { data: Record<string, unknown> }): AutopilotRule[] {
  const data = (res.data['hydra:member'] || res.data) as unknown;
  if (Array.isArray(data) && data.length > 0) {
    return data as AutopilotRule[];
  }
  return defaultRules;
}

// Parse l'historique depuis la reponse API
function parseHistoryResponse(res: { data: Record<string, unknown> }): HistoryEntry[] {
  const data = (res.data['hydra:member'] || res.data) as unknown;
  if (Array.isArray(data)) {
    return data as HistoryEntry[];
  }
  return [];
}

export default function AutopilotConfig() {
  const [rules, setRules] = useState<AutopilotRule[]>([]);
  const [history, setHistory] = useState<HistoryEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const [historyLoading, setHistoryLoading] = useState(true);
  const toast = useToast();

  // Chargement des regles depuis le serveur avec fallback sur les regles par defaut
  useEffect(() => {
    api.get('/autopilot/rules')
      .then((res) => setRules(parseRulesResponse(res)))
      .catch(() => setRules(defaultRules))
      .finally(() => setLoading(false));
  }, []);

  // Chargement de l'historique des actions executees
  useEffect(() => {
    api.get('/autopilot/history')
      .then((res) => setHistory(parseHistoryResponse(res)))
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
      <p className="app-desc">
        Regles automatisees pour gerer vos factures, relances et notifications.
        Activez ou desactivez chaque regle selon vos besoins.
      </p>

      {/* Compteur de regles actives */}
      <div className="app-kpi-grid" style={{ gridTemplateColumns: 'repeat(3, 1fr)' }}>
        <div className="app-card app-kpi-card">
          <div className="app-card-value" style={{ color: 'var(--accent)' }}>
            {activeCount}
          </div>
          <div className="app-card-sub">
            {activeCount > 1 ? 'Regles actives' : 'Regle active'}
          </div>
        </div>
        <div className="app-card app-kpi-card">
          <div className="app-card-value">
            {rules.length}
          </div>
          <div className="app-card-sub">
            Regles configurees
          </div>
        </div>
        <div className="app-card app-kpi-card">
          <div className="app-card-value" style={{ color: 'var(--success)' }}>
            {history.filter((h) => h.result === 'success').length}
          </div>
          <div className="app-card-sub">
            Actions reussies
          </div>
        </div>
      </div>

      {/* Liste des regles */}
      <h2 className="app-section-title">Regles d'automatisation</h2>
      <div className="app-list" style={{ marginBottom: '2.5rem' }}>
        {rules.map((rule) => {
          const triggerColor = triggerColors[rule.trigger];
          return (
            <div
              key={rule.id}
              className="app-list-item"
              style={{
                opacity: rule.active ? 1 : 0.65,
                borderLeft: `3px solid ${triggerColor}`,
              }}
            >
              {/* Interrupteur actif/inactif */}
              <button
                className={`app-toggle ${rule.active ? 'app-toggle--on' : ''}`}
                onClick={() => toggleRule(rule.id)}
                aria-label={rule.active ? `Desactiver ${rule.name}` : `Activer ${rule.name}`}
              >
                <span className="app-toggle-knob" />
              </button>

              {/* Nom et description */}
              <div className="app-list-item-info">
                <div className="app-list-item-title">
                  {rule.name}
                </div>
                <div className="app-list-item-sub">
                  {rule.description}
                </div>
              </div>

              {/* Badge du type de declencheur */}
              <span
                className="app-status-pill"
                style={{
                  background: `${triggerColor}15`,
                  color: triggerColor,
                }}
              >
                {triggerLabels[rule.trigger]}
              </span>

              {/* Label du type d'action */}
              <span className="app-list-item-sub" style={{ whiteSpace: 'nowrap' }}>
                {actionLabels[rule.action]}
              </span>
            </div>
          );
        })}
      </div>

      {/* Historique des actions */}
      <h2 className="app-section-title">Historique des actions</h2>

      {historyLoading && (
        <div>
          {[1, 2, 3].map((i) => (
            <div key={i} className="app-skeleton app-skeleton-table-row" style={{ marginTop: '0.5rem' }} />
          ))}
        </div>
      )}
      {!historyLoading && history.length === 0 && (
        <div className="app-empty">
          <p className="app-empty-title">
            Aucune action executee
          </p>
          <p className="app-empty-desc">
            L'historique apparaitra ici lorsque vos regles se declencheront.
          </p>
        </div>
      )}
      {!historyLoading && history.length > 0 && (
        <div className="app-table-wrapper">
          <table className="app-table">
            <thead>
              <tr>
                <th>Date</th>
                <th>Regle</th>
                <th>Action</th>
                <th>Resultat</th>
              </tr>
            </thead>
            <tbody>
              {history.map((entry) => {
                const resultInfo = resultLabels[entry.result] || resultLabels.pending;
                return (
                  <tr key={entry.id}>
                    <td style={{ whiteSpace: 'nowrap' }}>
                      {new Date(entry.date).toLocaleDateString('fr-FR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                      })}
                    </td>
                    <td style={{ fontWeight: 500 }}>
                      {entry.ruleName}
                    </td>
                    <td>
                      {entry.actionTaken}
                    </td>
                    <td>
                      <span
                        className="app-status-pill"
                        style={{
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
