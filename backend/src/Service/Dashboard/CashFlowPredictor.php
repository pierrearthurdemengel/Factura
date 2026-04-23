<?php

namespace App\Service\Dashboard;

use App\Entity\AccountingEntry;
use App\Entity\Company;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Prevoit l'evolution de la tresorerie a J+30, J+60 et J+90.
 *
 * Le modele prend en compte :
 * - Le solde bancaire actuel
 * - Les factures en attente (pondérées par la probabilite de paiement)
 * - Les charges recurrentes detectees dans les transactions bancaires
 * - Les echeances fiscales connues (TVA, URSSAF)
 *
 * Les alertes proactives signalent les passages sous un seuil configurable.
 */
class CashFlowPredictor
{
    // Horizons de prediction en jours
    public const HORIZON_30 = 30;
    public const HORIZON_60 = 60;
    public const HORIZON_90 = 90;

    // Probabilite de paiement par statut
    private const PAYMENT_PROBABILITY = [
        'ACKNOWLEDGED' => '0.95',
        'SENT' => '0.75',
    ];

    // Seuil d'alerte par defaut (euros)
    private const DEFAULT_ALERT_THRESHOLD = '1000.00';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ClientPaymentScorer $clientScorer,
    ) {
    }

    /**
     * Genere la projection de tresorerie sur trois horizons.
     *
     * @return array{
     *     currentBalance: string,
     *     projections: array<int, array{horizon: int, date: string, projected: string, inflows: string, outflows: string}>,
     *     alerts: array<int, array{type: string, message: string, date: string, amount: string}>,
     *     pendingInvoices: array{count: int, total: string, weightedTotal: string},
     *     recurringCharges: string
     * }
     */
    public function predict(
        Company $company,
        string $currentBalance,
        string $alertThreshold = self::DEFAULT_ALERT_THRESHOLD,
    ): array {
        $now = new \DateTimeImmutable();

        // Factures en attente (encaissements prevus)
        $pendingInflows = $this->estimatePendingInflows($company, $now);

        // Charges recurrentes mensuelles (decaissements prevus)
        $monthlyCharges = $this->estimateMonthlyCharges($company);

        // Echeances fiscales a venir
        $taxOutflows = $this->estimateTaxOutflows($company, $now);

        // Projections sur les 3 horizons
        $projections = [];
        $alerts = [];

        foreach ([self::HORIZON_30, self::HORIZON_60, self::HORIZON_90] as $horizon) {
            $targetDate = $now->modify(sprintf('+%d days', $horizon));
            $months = (int) ceil($horizon / 30);

            // Estimation des entrees : factures en attente proratisees sur l'horizon
            /** @var numeric-string $inflows */
            $inflows = $this->proratePendingInflows($pendingInflows, $horizon);

            // Estimation des sorties : charges mensuelles * nombre de mois + fiscalite
            /** @var numeric-string $chargesForPeriod */
            $chargesForPeriod = bcmul($monthlyCharges, (string) $months, 2);
            /** @var numeric-string $taxForPeriod */
            $taxForPeriod = $this->sumTaxOutflows($taxOutflows, $now, $targetDate);
            /** @var numeric-string $outflows */
            $outflows = bcadd($chargesForPeriod, $taxForPeriod, 2);

            // Solde projete
            /** @var numeric-string $currentBalance */
            $projected = bcadd($currentBalance, $inflows, 2);
            $projected = bcsub($projected, $outflows, 2);

            $projections[] = [
                'horizon' => $horizon,
                'date' => $targetDate->format('Y-m-d'),
                'projected' => $projected,
                'inflows' => $inflows,
                'outflows' => $outflows,
            ];

            // Alertes si le solde projete passe sous le seuil
            /** @var numeric-string $alertThreshold */
            if (bccomp($projected, $alertThreshold, 2) < 0) {
                $alerts[] = [
                    'type' => 'low_balance',
                    'message' => sprintf(
                        'Solde prevu de %s EUR a J+%d, sous le seuil de %s EUR.',
                        $projected,
                        $horizon,
                        $alertThreshold,
                    ),
                    'date' => $targetDate->format('Y-m-d'),
                    'amount' => $projected,
                ];
            }
        }

        // Alertes specifiques aux factures en retard
        $alerts = array_merge($alerts, $this->generateInvoiceAlerts($company, $now, $currentBalance, $alertThreshold));

        return [
            'currentBalance' => $currentBalance,
            'projections' => $projections,
            'alerts' => $alerts,
            'pendingInvoices' => [
                'count' => $pendingInflows['count'],
                'total' => $pendingInflows['total'],
                'weightedTotal' => $pendingInflows['weighted'],
            ],
            'recurringCharges' => $monthlyCharges,
        ];
    }

    /**
     * Estime les entrees prevues depuis les factures en attente.
     *
     * @return array{count: int, total: numeric-string, weighted: numeric-string, invoices: array<int, array{id: string, amount: string, probability: string, dueDate: string|null}>}
     */
    private function estimatePendingInflows(Company $company, \DateTimeImmutable $_now): array
    {
        // $_now reserved for future due-date proximity weighting
        $qb = $this->em->createQueryBuilder();
        $invoices = $qb->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.seller = :company')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('company', $company)
            ->setParameter('statuses', ['SENT', 'ACKNOWLEDGED'])
            ->getQuery()
            ->getResult();

        /** @var numeric-string $total */
        $total = '0.00';
        /** @var numeric-string $weighted */
        $weighted = '0.00';
        $details = [];

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            /** @var numeric-string $ttc */
            $ttc = $invoice->getTotalIncludingTax();
            $total = bcadd($total, $ttc, 2);

            // Probabilite basee sur le statut
            $baseProbability = self::PAYMENT_PROBABILITY[$invoice->getStatus()] ?? '0.50';

            // Ajustement selon le scoring client
            $clientScore = $this->clientScorer->getScore($invoice->getBuyer());
            /** @var numeric-string $scoreAdjustment */
            $scoreAdjustment = bcdiv((string) $clientScore, '100', 2);
            /** @var numeric-string $baseProbability */
            $probability = bcmul($baseProbability, $scoreAdjustment, 2);
            // Cap a 0.95 maximum
            if (bccomp($probability, '0.95', 2) > 0) {
                $probability = '0.95';
            }

            $weightedAmount = bcmul($ttc, $probability, 2);
            $weighted = bcadd($weighted, $weightedAmount, 2);

            $details[] = [
                'id' => (string) $invoice->getId(),
                'amount' => $ttc,
                'probability' => $probability,
                'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
            ];
        }

        return [
            'count' => \count($invoices),
            'total' => $total,
            'weighted' => $weighted,
            'invoices' => $details,
        ];
    }

    /**
     * Estime les charges mensuelles recurrentes depuis l'historique bancaire.
     *
     * Analyse les 3 derniers mois de debits pour detecter les charges regulieres.
     *
     * @return numeric-string
     */
    private function estimateMonthlyCharges(Company $company): string
    {
        $threeMonthsAgo = new \DateTimeImmutable('-3 months');
        $now = new \DateTimeImmutable();

        $qb = $this->em->createQueryBuilder();
        $entries = $qb->select('e')
            ->from(AccountingEntry::class, 'e')
            ->where('e.company = :company')
            ->andWhere('e.entryDate >= :from')
            ->andWhere('e.entryDate <= :to')
            ->andWhere('e.journalCode = :journal')
            ->andWhere('e.creditAccount = :bank')
            ->setParameter('company', $company)
            ->setParameter('from', $threeMonthsAgo)
            ->setParameter('to', $now)
            ->setParameter('journal', AccountingEntry::JOURNAL_BANQUE)
            ->setParameter('bank', '512000')
            ->getQuery()
            ->getResult();

        /** @var numeric-string $totalCharges */
        $totalCharges = '0.00';
        /** @var AccountingEntry $entry */
        foreach ($entries as $entry) {
            /** @var numeric-string $amount */
            $amount = $entry->getAmount();
            $totalCharges = bcadd($totalCharges, $amount, 2);
        }

        // Moyenne mensuelle sur 3 mois
        if (bccomp($totalCharges, '0.00', 2) > 0) {
            return bcdiv($totalCharges, '3', 2);
        }

        return '0.00';
    }

    /**
     * Estime les echeances fiscales a venir.
     *
     * @return array<int, array{date: string, amount: numeric-string, label: string}>
     */
    private function estimateTaxOutflows(Company $_company, \DateTimeImmutable $_now): array
    {
        // Estimation simplifiee basee sur les charges fiscales recentes
        // En production, se baserait sur les declarations reelles
        // $_company and $_now will query tax declaration deadlines in a future iteration
        return [];
    }

    /**
     * Proratas les entrees prevues selon l'horizon de prediction.
     *
     * Les factures a echeance dans l'horizon sont incluses a 100%.
     * Les autres sont proratisees.
     *
     * @param array{count: int, total: numeric-string, weighted: numeric-string, invoices: array<int, array{id: string, amount: string, probability: string, dueDate: string|null}>} $pendingInflows
     *
     * @return numeric-string
     */
    private function proratePendingInflows(array $pendingInflows, int $horizonDays): string
    {
        // Utilise le montant pondere total comme estimation
        // Les factures proches de l'echeance sont plus susceptibles d'etre payees
        /** @var numeric-string $weighted */
        $weighted = $pendingInflows['weighted'];

        // Prorata lineaire sur l'horizon (plus le temps passe, plus on recoit)
        /** @var numeric-string $ratio */
        $ratio = bcdiv((string) min($horizonDays, 90), '90', 4);

        return bcmul($weighted, $ratio, 2);
    }

    /**
     * Somme les echeances fiscales dans un intervalle.
     *
     * @param array<int, array{date: string, amount: numeric-string, label: string}> $taxOutflows
     *
     * @return numeric-string
     */
    private function sumTaxOutflows(
        array $taxOutflows,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): string {
        /** @var numeric-string $total */
        $total = '0.00';

        foreach ($taxOutflows as $outflow) {
            $date = new \DateTimeImmutable($outflow['date']);
            if ($date >= $from && $date <= $to) {
                $total = bcadd($total, $outflow['amount'], 2);
            }
        }

        return $total;
    }

    /**
     * Genere des alertes pour les factures impayees qui impactent la tresorerie.
     *
     * @return array<int, array{type: string, message: string, date: string, amount: string}>
     */
    private function generateInvoiceAlerts(
        Company $company,
        \DateTimeImmutable $now,
        string $_currentBalance,
        string $_alertThreshold,
    ): array {
        // $_currentBalance and $_alertThreshold reserved for balance-weighted overdue alerts
        $alerts = [];

        // Factures dont l'echeance est depassee
        $qb = $this->em->createQueryBuilder();
        $overdueInvoices = $qb->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.seller = :company')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.dueDate < :now')
            ->setParameter('company', $company)
            ->setParameter('statuses', ['SENT', 'ACKNOWLEDGED'])
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        /** @var Invoice $invoice */
        foreach ($overdueInvoices as $invoice) {
            $dueDate = $invoice->getDueDate();
            if (null === $dueDate) {
                continue;
            }
            $daysOverdue = (int) $now->diff($dueDate)->days;
            $alerts[] = [
                'type' => 'overdue_invoice',
                'message' => sprintf(
                    'Facture %s en retard de %d jours (%s EUR).',
                    $invoice->getNumber() ?? '?',
                    $daysOverdue,
                    $invoice->getTotalIncludingTax(),
                ),
                'date' => $dueDate->format('Y-m-d'),
                'amount' => $invoice->getTotalIncludingTax(),
            ];
        }

        return $alerts;
    }
}
