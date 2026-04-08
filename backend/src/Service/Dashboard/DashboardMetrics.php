<?php

namespace App\Service\Dashboard;

use App\Entity\Company;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule les indicateurs financiers du tableau de bord.
 *
 * Fournit le chiffre d'affaires mensuel/annuel, l'evolution N/N-1,
 * la repartition par client et par categorie, et le classement
 * des meilleurs clients.
 */
class DashboardMetrics
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule les metriques principales pour une periode.
     *
     * @return array{
     *     turnover: string,
     *     turnoverPreviousYear: string,
     *     evolutionPercent: string|null,
     *     totalTax: string,
     *     invoiceCount: int,
     *     paidCount: int,
     *     pendingCount: int,
     *     averageInvoiceAmount: string
     * }
     */
    public function getMetrics(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $invoices = $this->getInvoices($company, $from, $to);
        $previousYearInvoices = $this->getInvoices(
            $company,
            $from->modify('-1 year'),
            $to->modify('-1 year'),
        );

        /** @var numeric-string $turnover */
        $turnover = '0.00';
        /** @var numeric-string $totalTax */
        $totalTax = '0.00';
        $paidCount = 0;
        $pendingCount = 0;

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            /** @var numeric-string $ht */
            $ht = $invoice->getTotalExcludingTax();
            /** @var numeric-string $tax */
            $tax = $invoice->getTotalTax();
            $turnover = bcadd($turnover, $ht, 2);
            $totalTax = bcadd($totalTax, $tax, 2);

            if ('PAID' === $invoice->getStatus()) {
                ++$paidCount;
            } elseif (\in_array($invoice->getStatus(), ['SENT', 'ACKNOWLEDGED'], true)) {
                ++$pendingCount;
            }
        }

        /** @var numeric-string $previousTurnover */
        $previousTurnover = '0.00';
        /** @var Invoice $invoice */
        foreach ($previousYearInvoices as $invoice) {
            /** @var numeric-string $ht */
            $ht = $invoice->getTotalExcludingTax();
            $previousTurnover = bcadd($previousTurnover, $ht, 2);
        }

        // Evolution N/N-1 en pourcentage
        $evolutionPercent = null;
        if (bccomp($previousTurnover, '0.00', 2) > 0) {
            /** @var numeric-string $diff */
            $diff = bcsub($turnover, $previousTurnover, 2);
            $evolutionPercent = bcmul(bcdiv($diff, $previousTurnover, 4), '100', 2);
        }

        // Montant moyen par facture
        $count = \count($invoices);
        $averageAmount = '0.00';
        if ($count > 0) {
            $averageAmount = bcdiv($turnover, (string) $count, 2);
        }

        return [
            'turnover' => $turnover,
            'turnoverPreviousYear' => $previousTurnover,
            'evolutionPercent' => $evolutionPercent,
            'totalTax' => $totalTax,
            'invoiceCount' => $count,
            'paidCount' => $paidCount,
            'pendingCount' => $pendingCount,
            'averageInvoiceAmount' => $averageAmount,
        ];
    }

    /**
     * Repartition du CA par client.
     *
     * @return array<int, array{clientId: string, clientName: string, turnover: string, invoiceCount: int, percentage: string}>
     */
    public function getTurnoverByClient(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $invoices = $this->getInvoices($company, $from, $to);

        /** @var array<string, array{name: string, turnover: numeric-string, count: int}> $clients */
        $clients = [];
        /** @var numeric-string $total */
        $total = '0.00';

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            $clientId = (string) $invoice->getBuyer()->getId();
            $clientName = $invoice->getBuyer()->getName();

            if (!isset($clients[$clientId])) {
                $clients[$clientId] = ['name' => $clientName, 'turnover' => '0.00', 'count' => 0];
            }

            /** @var numeric-string $ht */
            $ht = $invoice->getTotalExcludingTax();
            $clients[$clientId]['turnover'] = bcadd($clients[$clientId]['turnover'], $ht, 2);
            ++$clients[$clientId]['count'];
            $total = bcadd($total, $ht, 2);
        }

        // Trier par CA decroissant
        uasort($clients, static fn (array $a, array $b) => bccomp($b['turnover'], $a['turnover'], 2));

        $result = [];
        foreach ($clients as $clientId => $data) {
            $percentage = '0.00';
            if (bccomp($total, '0.00', 2) > 0) {
                $percentage = bcmul(bcdiv($data['turnover'], $total, 4), '100', 2);
            }

            $result[] = [
                'clientId' => $clientId,
                'clientName' => $data['name'],
                'turnover' => $data['turnover'],
                'invoiceCount' => $data['count'],
                'percentage' => $percentage,
            ];
        }

        return $result;
    }

    /**
     * CA mensuel sur une annee complete.
     *
     * @return array<int, array{month: int, year: int, label: string, turnover: string}>
     */
    public function getMonthlyTurnover(Company $company, int $year): array
    {
        $result = [];

        for ($month = 1; $month <= 12; ++$month) {
            $from = new \DateTimeImmutable(sprintf('%d-%02d-01', $year, $month));
            $to = $from->modify('last day of this month');

            $invoices = $this->getInvoices($company, $from, $to);

            /** @var numeric-string $turnover */
            $turnover = '0.00';
            /** @var Invoice $invoice */
            foreach ($invoices as $invoice) {
                /** @var numeric-string $ht */
                $ht = $invoice->getTotalExcludingTax();
                $turnover = bcadd($turnover, $ht, 2);
            }

            $result[] = [
                'month' => $month,
                'year' => $year,
                'label' => $from->format('F Y'),
                'turnover' => $turnover,
            ];
        }

        return $result;
    }

    /**
     * Top clients par CA.
     *
     * @return array<int, array{clientId: string, clientName: string, turnover: string, invoiceCount: int, percentage: string}>
     */
    public function getTopClients(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
        int $limit = 10,
    ): array {
        $byClient = $this->getTurnoverByClient($company, $from, $to);

        return \array_slice($byClient, 0, $limit);
    }

    /**
     * Recupere les factures emises sur une periode (hors brouillons et annulees).
     *
     * @return Invoice[]
     */
    private function getInvoices(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->em->createQueryBuilder();

        return $qb->select('i')
            ->from(Invoice::class, 'i')
            ->where('i.seller = :company')
            ->andWhere('i.issueDate >= :from')
            ->andWhere('i.issueDate <= :to')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('company', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('statuses', ['SENT', 'ACKNOWLEDGED', 'PAID'])
            ->getQuery()
            ->getResult();
    }
}
