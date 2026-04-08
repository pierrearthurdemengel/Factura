<?php

namespace App\Service\Benchmark;

use App\Entity\AnonymizedBenchmark;
use App\Entity\Company;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule et expose les benchmarks sectoriels anonymises.
 * Toutes les donnees sont agregees — jamais de donnees individuelles.
 * Un agregat n'est publie que si au moins 5 entreprises y contribuent.
 */
class BenchmarkService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Recupere les benchmarks disponibles pour le secteur d'une entreprise.
     * Retourne uniquement les agregats publiables (>= 5 contributeurs).
     *
     * @return list<array{metric: string, value: string, contributorCount: int, period: string}>
     */
    public function getBenchmarksForCompany(Company $company): array
    {
        $sector = $this->extractSector($company);
        if (null === $sector) {
            return [];
        }

        $benchmarks = $this->em->getRepository(AnonymizedBenchmark::class)->findBy(
            ['sector' => $sector],
            ['period' => 'DESC', 'metric' => 'ASC'],
        );

        $result = [];
        foreach ($benchmarks as $b) {
            if (!$b->isPublishable()) {
                continue;
            }
            $result[] = [
                'metric' => $b->getMetric(),
                'value' => $b->getValue(),
                'contributorCount' => $b->getContributorCount(),
                'period' => $b->getPeriod(),
            ];
        }

        return $result;
    }

    /**
     * Compare les performances de l'entreprise aux benchmarks du secteur.
     *
     * @return array{sector: string, period: string, comparisons: list<array{metric: string, yourValue: string, sectorAvg: string, delta: string, position: string}>}|null
     */
    public function compareToSector(Company $company, string $period): ?array
    {
        $sector = $this->extractSector($company);
        if (null === $sector) {
            return null;
        }

        $benchmarks = $this->em->getRepository(AnonymizedBenchmark::class)->findBy([
            'sector' => $sector,
            'period' => $period,
        ]);

        $companyMetrics = $this->computeCompanyMetrics($company, $period);
        $comparisons = [];

        foreach ($benchmarks as $b) {
            if (!$b->isPublishable()) {
                continue;
            }

            $metricName = $b->getMetric();
            $sectorAvg = $b->getValue();
            $yourValue = $companyMetrics[$metricName] ?? '0';

            $delta = '0';
            $position = 'equal';

            if ('0' !== $sectorAvg && '' !== $sectorAvg && is_numeric($yourValue) && is_numeric($sectorAvg)) {
                $deltaNum = bcsub($yourValue, $sectorAvg, 2);
                $delta = $deltaNum;
                $cmp = bccomp($deltaNum, '0', 2);
                $position = match (true) {
                    $cmp > 0 => 'above',
                    $cmp < 0 => 'below',
                    default => 'equal',
                };
            }

            $comparisons[] = [
                'metric' => $metricName,
                'yourValue' => $yourValue,
                'sectorAvg' => $sectorAvg,
                'delta' => $delta,
                'position' => $position,
            ];
        }

        return [
            'sector' => $sector,
            'period' => $period,
            'comparisons' => $comparisons,
        ];
    }

    /**
     * Extrait le code secteur (2 premiers caracteres du code NAF) depuis l'entreprise.
     */
    private function extractSector(Company $company): ?string
    {
        $nafCode = $company->getNafCode();
        if (null === $nafCode || '' === $nafCode) {
            return null;
        }

        // Le code NAF est au format XXYYZ (ex: 6201Z)
        // On extrait les 2 premiers chiffres pour le secteur
        return substr($nafCode, 0, 2);
    }

    /**
     * Calcule les metriques de l'entreprise pour une periode donnee.
     *
     * @return array<string, string>
     */
    private function computeCompanyMetrics(Company $company, string $period): array
    {
        $companyId = $company->getId();
        if (null === $companyId) {
            return [];
        }

        $conn = $this->em->getConnection();

        // Montant moyen des factures
        $avgInvoice = $conn->executeQuery(
            "SELECT COALESCE(AVG(CAST(total_excluding_tax AS DECIMAL(15,2))), 0) as val
             FROM invoices
             WHERE seller_id = :seller_id
               AND TO_CHAR(issue_date, 'YYYY-MM') = :period
               AND status != 'CANCELLED'",
            ['seller_id' => $companyId->toRfc4122(), 'period' => $period],
        )->fetchOne();

        // Delai moyen de paiement (jours entre emission et paiement)
        $avgDelay = $conn->executeQuery(
            "SELECT COALESCE(AVG(EXTRACT(DAY FROM (updated_at - issue_date))), 0) as val
             FROM invoices
             WHERE seller_id = :seller_id
               AND TO_CHAR(issue_date, 'YYYY-MM') = :period
               AND status = 'PAID'",
            ['seller_id' => $companyId->toRfc4122(), 'period' => $period],
        )->fetchOne();

        // CA mensuel
        $monthlyRevenue = $conn->executeQuery(
            "SELECT COALESCE(SUM(CAST(total_excluding_tax AS DECIMAL(15,2))), 0) as val
             FROM invoices
             WHERE seller_id = :seller_id
               AND TO_CHAR(issue_date, 'YYYY-MM') = :period
               AND status IN ('SENT', 'ACKNOWLEDGED', 'PAID')",
            ['seller_id' => $companyId->toRfc4122(), 'period' => $period],
        )->fetchOne();

        return [
            AnonymizedBenchmark::METRIC_AVG_INVOICE_AMOUNT => (string) round((float) $avgInvoice, 2),
            AnonymizedBenchmark::METRIC_MEDIAN_PAYMENT_DELAY => (string) round((float) $avgDelay, 0),
            AnonymizedBenchmark::METRIC_AVG_MONTHLY_REVENUE => (string) round((float) $monthlyRevenue, 2),
        ];
    }
}
