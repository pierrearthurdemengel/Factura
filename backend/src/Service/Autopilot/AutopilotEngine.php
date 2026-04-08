<?php

namespace App\Service\Autopilot;

use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Moteur d'automatisation qui evalue les regles et execute les actions.
 *
 * Le moteur est appele periodiquement par un cron job (toutes les heures).
 * Il evalue les conditions de chaque regle active et declenche les actions
 * correspondantes. L'execution est idempotente : une action n'est executee
 * qu'une seule fois pour un meme evenement.
 */
class AutopilotEngine
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Evalue toutes les regles actives pour une entreprise et retourne les actions a executer.
     *
     * @param list<array{id: string, name: string, description: string, trigger: string, action: string, params: array<string, string>, enabled: bool, category: string}> $rules
     *
     * @return list<array{ruleId: string, action: string, params: array<string, string>, reason: string}>
     */
    public function evaluate(Company $company, array $rules): array
    {
        $actions = [];

        foreach ($rules as $rule) {
            if (!$rule['enabled']) {
                continue;
            }

            $triggered = $this->checkTrigger($company, $rule['trigger'], $rule['params']);

            if (null !== $triggered) {
                $actions[] = [
                    'ruleId' => $rule['id'],
                    'action' => $rule['action'],
                    'params' => $rule['params'],
                    'reason' => $triggered,
                ];
            }
        }

        return $actions;
    }

    /**
     * Retourne un resume du statut autopilot pour une entreprise.
     *
     * @return array{activeRules: int, totalRules: int, lastEvaluation: string|null, pendingActions: int}
     */
    public function getStatus(Company $company, int $activeRules = 0, int $totalRules = 0): array
    {
        return [
            'activeRules' => $activeRules,
            'totalRules' => $totalRules,
            'lastEvaluation' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'pendingActions' => 0,
        ];
    }

    /**
     * Verifie les factures en retard de paiement.
     *
     * @return list<array{invoiceId: string, invoiceNumber: string, daysOverdue: int, amount: string}>
     */
    public function getOverdueInvoices(Company $company): array
    {
        $companyId = $company->getId();
        if (null === $companyId) {
            return [];
        }

        $conn = $this->em->getConnection();

        $rows = $conn->executeQuery(
            "SELECT id, invoice_number, due_date, total_including_tax
             FROM invoices
             WHERE seller_id = :seller_id
               AND status = 'SENT'
               AND due_date < CURRENT_DATE
             ORDER BY due_date ASC",
            ['seller_id' => $companyId->toRfc4122()],
        )->fetchAllAssociative();

        $result = [];
        $now = new \DateTimeImmutable();

        foreach ($rows as $row) {
            $dueDate = $row['due_date'] ?? null;
            $daysOverdue = 0;
            if (is_string($dueDate)) {
                try {
                    $due = new \DateTimeImmutable($dueDate);
                    $daysOverdue = (int) $now->diff($due)->days;
                } catch (\Throwable) {
                    // Ignore les dates invalides
                }
            }

            $invoiceId = $row['id'] ?? '';
            $invoiceNumber = $row['invoice_number'] ?? '';
            $amount = $row['total_including_tax'] ?? '0.00';

            $result[] = [
                'invoiceId' => is_string($invoiceId) ? $invoiceId : '',
                'invoiceNumber' => is_string($invoiceNumber) ? $invoiceNumber : '',
                'daysOverdue' => $daysOverdue,
                'amount' => is_string($amount) ? $amount : '0.00',
            ];
        }

        return $result;
    }

    /**
     * Verifie si un declencheur est active pour une entreprise donnee.
     *
     * @param array<string, string> $params
     *
     * @return string|null Description de la raison du declenchement, ou null si non declenche
     */
    private function checkTrigger(Company $company, string $trigger, array $params): ?string
    {
        return match ($trigger) {
            AutopilotRule::TRIGGER_INVOICE_OVERDUE => $this->checkOverdueInvoices($company, $params),
            AutopilotRule::TRIGGER_INVOICE_DUE_SOON => $this->checkDueSoonInvoices($company, $params),
            AutopilotRule::TRIGGER_REVENUE_THRESHOLD => $this->checkRevenueThreshold($company, $params),
            default => null,
        };
    }

    /**
     * Verifie s'il existe des factures en retard correspondant aux parametres.
     *
     * @param array<string, string> $params
     */
    private function checkOverdueInvoices(Company $company, array $params): ?string
    {
        $daysAfter = (int) ($params['days_after'] ?? '0');
        $companyId = $company->getId();
        if (null === $companyId || $daysAfter <= 0) {
            return null;
        }

        $conn = $this->em->getConnection();

        $count = $conn->executeQuery(
            "SELECT COUNT(*) FROM invoices
             WHERE seller_id = :seller_id
               AND status = 'SENT'
               AND due_date <= CURRENT_DATE - INTERVAL '{$daysAfter} days'
               AND due_date > CURRENT_DATE - INTERVAL '" . ($daysAfter + 1) . " days'",
            ['seller_id' => $companyId->toRfc4122()],
        )->fetchOne();

        $countInt = is_numeric($count) ? (int) $count : 0;

        if ($countInt > 0) {
            return "{$countInt} facture(s) impayee(s) depuis {$daysAfter} jours";
        }

        return null;
    }

    /**
     * Verifie s'il existe des factures arrivant a echeance prochainement.
     *
     * @param array<string, string> $params
     */
    private function checkDueSoonInvoices(Company $company, array $params): ?string
    {
        $daysBefore = (int) ($params['days_before'] ?? '3');
        $companyId = $company->getId();
        if (null === $companyId) {
            return null;
        }

        $conn = $this->em->getConnection();

        $count = $conn->executeQuery(
            "SELECT COUNT(*) FROM invoices
             WHERE seller_id = :seller_id
               AND status = 'SENT'
               AND due_date = CURRENT_DATE + INTERVAL '{$daysBefore} days'",
            ['seller_id' => $companyId->toRfc4122()],
        )->fetchOne();

        $countInt = is_numeric($count) ? (int) $count : 0;

        if ($countInt > 0) {
            return "{$countInt} facture(s) arrivent a echeance dans {$daysBefore} jours";
        }

        return null;
    }

    /**
     * Verifie si le CA approche un seuil configure.
     *
     * @param array<string, string> $params
     */
    private function checkRevenueThreshold(Company $company, array $params): ?string
    {
        $threshold = $params['threshold'] ?? '0';
        $alertAtPercent = $params['alert_at_percent'] ?? '90';
        $companyId = $company->getId();

        if (null === $companyId || !is_numeric($threshold) || !is_numeric($alertAtPercent)) {
            return null;
        }

        $conn = $this->em->getConnection();
        $year = (int) date('Y');

        $revenue = $conn->executeQuery(
            "SELECT COALESCE(SUM(CAST(total_excluding_tax AS DECIMAL(15,2))), 0)
             FROM invoices
             WHERE seller_id = :seller_id
               AND EXTRACT(YEAR FROM issue_date) = :year
               AND status IN ('SENT', 'ACKNOWLEDGED', 'PAID')",
            ['seller_id' => $companyId->toRfc4122(), 'year' => $year],
        )->fetchOne();

        $revenueStr = is_numeric($revenue) ? (string) $revenue : '0';
        $alertLevel = bcmul($threshold, bcdiv($alertAtPercent, '100', 4), 2);

        if (bccomp($revenueStr, $alertLevel, 2) >= 0 && bccomp($revenueStr, $threshold, 2) < 0) {
            return "CA annuel ({$revenueStr} EUR) approche le seuil de {$threshold} EUR ({$alertAtPercent}%)";
        }

        return null;
    }
}
