<?php

namespace App\Service\Tax;

use App\Entity\Company;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule les cotisations URSSAF pour les auto-entrepreneurs.
 *
 * Les taux varient selon l'activite (BIC vente, BIC prestation, BNC).
 * Le chiffre d'affaires est calcule sur les factures payees de la periode.
 *
 * Les echeances sont mensuelles ou trimestrielles selon le choix
 * de l'auto-entrepreneur a la creation de son statut.
 */
class UrssafCalculator
{
    // Taux de cotisations auto-entrepreneur 2026
    // BIC = Benefices Industriels et Commerciaux
    // BNC = Benefices Non Commerciaux
    public const RATE_BIC_SALE = '12.3';
    public const RATE_BIC_SERVICE = '21.2';
    public const RATE_BNC_LIBERAL = '21.1';
    public const RATE_BNC_CIPAV = '21.2';

    // Types d'activite reconnus
    public const ACTIVITY_BIC_SALE = 'bic_sale';
    public const ACTIVITY_BIC_SERVICE = 'bic_service';
    public const ACTIVITY_BNC_LIBERAL = 'bnc_liberal';
    public const ACTIVITY_BNC_CIPAV = 'bnc_cipav';

    // Plafonds de chiffre d'affaires annuel (2026)
    public const CEILING_BIC_SALE = '188700';
    public const CEILING_BIC_SERVICE = '77700';
    public const CEILING_BNC = '77700';

    // Periodes de declaration
    public const FREQUENCY_MONTHLY = 'monthly';
    public const FREQUENCY_QUARTERLY = 'quarterly';

    private const DATE_FORMAT_FIRST_OF_MONTH = '%d-%02d-01';
    private const MODIFIER_LAST_DAY_OF_MONTH = self::MODIFIER_LAST_DAY_OF_MONTH;

    private const ACTIVITY_RATES = [
        self::ACTIVITY_BIC_SALE => self::RATE_BIC_SALE,
        self::ACTIVITY_BIC_SERVICE => self::RATE_BIC_SERVICE,
        self::ACTIVITY_BNC_LIBERAL => self::RATE_BNC_LIBERAL,
        self::ACTIVITY_BNC_CIPAV => self::RATE_BNC_CIPAV,
    ];

    private const ACTIVITY_CEILINGS = [
        self::ACTIVITY_BIC_SALE => self::CEILING_BIC_SALE,
        self::ACTIVITY_BIC_SERVICE => self::CEILING_BIC_SERVICE,
        self::ACTIVITY_BNC_LIBERAL => self::CEILING_BNC,
        self::ACTIVITY_BNC_CIPAV => self::CEILING_BNC,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule le chiffre d'affaires encaisse sur la periode.
     *
     * Seules les factures au statut PAID sont prises en compte,
     * car l'URSSAF utilise le regime d'encaissement.
     */
    public function calculateTurnover(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): string {
        $qb = $this->em->createQueryBuilder();
        $invoices = $qb->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.seller = :company')
            ->andWhere('i.issueDate >= :from')
            ->andWhere('i.issueDate <= :to')
            ->andWhere('i.status = :status')
            ->setParameter('company', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('status', 'PAID')
            ->getQuery()
            ->getResult();

        /** @var numeric-string $total */
        $total = '0.00';

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            // Les auto-entrepreneurs declarent le CA TTC (inclut la TVA si assujettis)
            // Mais la plupart sont en franchise de TVA (art. 293 B CGI)
            // On utilise le montant HT comme base de calcul
            /** @var numeric-string $amountHt */
            $amountHt = $invoice->getTotalExcludingTax();
            $total = bcadd($total, $amountHt, 2);
        }

        return $total;
    }

    /**
     * Calcule les cotisations URSSAF pour la periode.
     *
     * @return array{
     *     turnover: string,
     *     rate: string,
     *     contributions: string,
     *     activityType: string,
     *     annualCeiling: string,
     *     ceilingRemaining: string,
     *     periodLabel: string
     * }
     */
    public function calculateContributions(
        Company $company,
        string $activityType,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $this->validateActivityType($activityType);

        $turnover = $this->calculateTurnover($company, $from, $to);
        $rate = self::ACTIVITY_RATES[$activityType];
        $ceiling = self::ACTIVITY_CEILINGS[$activityType];

        // Cotisations = CA * taux / 100
        /** @var numeric-string $turnover */
        /** @var numeric-string $rate */
        $contributions = bcmul($turnover, bcdiv($rate, '100', 6), 2);

        // CA annuel pour verifier le plafond
        $yearStart = new \DateTimeImmutable($from->format('Y') . '-01-01');
        $yearEnd = new \DateTimeImmutable($from->format('Y') . '-12-31');
        $annualTurnover = $this->calculateTurnover($company, $yearStart, $yearEnd);

        /** @var numeric-string $annualTurnover */
        /** @var numeric-string $ceiling */
        $ceilingRemaining = bcsub($ceiling, $annualTurnover, 2);
        if (bccomp($ceilingRemaining, '0.00', 2) < 0) {
            $ceilingRemaining = '0.00';
        }

        return [
            'turnover' => $turnover,
            'rate' => $rate,
            'contributions' => $contributions,
            'activityType' => $activityType,
            'annualCeiling' => $ceiling,
            'ceilingRemaining' => $ceilingRemaining,
            'periodLabel' => sprintf(
                '%s au %s',
                $from->format('d/m/Y'),
                $to->format('d/m/Y'),
            ),
        ];
    }

    /**
     * Retourne les echeances de declaration pour une annee.
     *
     * @return array<int, array{period: string, deadline: string, start: string, end: string}>
     */
    public function getDeclarationDeadlines(int $year, string $frequency): array
    {
        $deadlines = [];

        if (self::FREQUENCY_MONTHLY === $frequency) {
            // Declaration mensuelle : le dernier jour du mois suivant
            for ($month = 1; $month <= 12; ++$month) {
                $periodStart = new \DateTimeImmutable(sprintf(self::DATE_FORMAT_FIRST_OF_MONTH, $year, $month));
                $periodEnd = $periodStart->modify(self::MODIFIER_LAST_DAY_OF_MONTH);
                $deadline = $periodEnd->modify('+1 month last day of this month');

                $deadlines[] = [
                    'period' => $periodStart->format('F Y'),
                    'deadline' => $deadline->format('Y-m-d'),
                    'start' => $periodStart->format('Y-m-d'),
                    'end' => $periodEnd->format('Y-m-d'),
                ];
            }
        } elseif (self::FREQUENCY_QUARTERLY === $frequency) {
            // Declaration trimestrielle : fin du mois suivant le trimestre
            $quarters = [
                ['start' => 1, 'end' => 3, 'deadlineMonth' => 4],
                ['start' => 4, 'end' => 6, 'deadlineMonth' => 7],
                ['start' => 7, 'end' => 9, 'deadlineMonth' => 10],
                ['start' => 10, 'end' => 12, 'deadlineMonth' => 1],
            ];

            foreach ($quarters as $i => $q) {
                $periodStart = new \DateTimeImmutable(sprintf(self::DATE_FORMAT_FIRST_OF_MONTH, $year, $q['start']));
                $periodEnd = new \DateTimeImmutable(sprintf(self::DATE_FORMAT_FIRST_OF_MONTH, $year, $q['end']));
                $periodEnd = $periodEnd->modify(self::MODIFIER_LAST_DAY_OF_MONTH);

                $deadlineYear = 1 === $q['deadlineMonth'] ? $year + 1 : $year;
                $deadline = new \DateTimeImmutable(sprintf(self::DATE_FORMAT_FIRST_OF_MONTH, $deadlineYear, $q['deadlineMonth']));
                $deadline = $deadline->modify(self::MODIFIER_LAST_DAY_OF_MONTH);

                $deadlines[] = [
                    'period' => sprintf('T%d %d', $i + 1, $year),
                    'deadline' => $deadline->format('Y-m-d'),
                    'start' => $periodStart->format('Y-m-d'),
                    'end' => $periodEnd->format('Y-m-d'),
                ];
            }
        }

        return $deadlines;
    }

    /**
     * Retourne le taux de cotisation pour un type d'activite.
     */
    public function getRate(string $activityType): string
    {
        $this->validateActivityType($activityType);

        return self::ACTIVITY_RATES[$activityType];
    }

    /**
     * Retourne les types d'activite supportes.
     *
     * @return array<string, string>
     */
    public static function getActivityTypes(): array
    {
        return [
            self::ACTIVITY_BIC_SALE => 'Vente de marchandises (BIC)',
            self::ACTIVITY_BIC_SERVICE => 'Prestations de services (BIC)',
            self::ACTIVITY_BNC_LIBERAL => 'Profession liberale (BNC)',
            self::ACTIVITY_BNC_CIPAV => 'Profession liberale CIPAV (BNC)',
        ];
    }

    /**
     * Verifie que le type d'activite est valide.
     *
     * @throws \InvalidArgumentException Si le type d'activite n'est pas reconnu
     */
    private function validateActivityType(string $activityType): void
    {
        if (!isset(self::ACTIVITY_RATES[$activityType])) {
            throw new \InvalidArgumentException(sprintf('Type d\'activite inconnu : %s. Types valides : %s', $activityType, implode(', ', array_keys(self::ACTIVITY_RATES))));
        }
    }
}
