<?php

namespace App\Service\Tax;

use App\Entity\AccountingEntry;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule la TVA collectee et deductible pour une entreprise sur une periode.
 *
 * La TVA collectee est calculee depuis les factures emises.
 * La TVA deductible est calculee depuis les ecritures comptables (compte 445660).
 */
class VatCalculator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule la TVA collectee sur la periode donnee.
     *
     * Somme des montants de TVA des lignes de factures emises
     * avec statut SENT, ACKNOWLEDGED ou PAID, groupee par taux.
     *
     * @return array{
     *     total: string,
     *     byRate: array<string, array{base: string, vat: string}>,
     *     invoiceCount: int
     * }
     */
    public function calculateCollectedVat(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        // Recuperer les factures emises sur la periode
        $qb = $this->em->createQueryBuilder();
        $invoices = $qb->select('i')
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

        /** @var array<string, array{base: numeric-string, vat: numeric-string}> $byRate */
        $byRate = [];
        /** @var numeric-string $total */
        $total = '0.00';

        /** @var Invoice $invoice */
        foreach ($invoices as $invoice) {
            /** @var InvoiceLine $line */
            foreach ($invoice->getLines() as $line) {
                $rate = $line->getVatRate();
                if (!isset($byRate[$rate])) {
                    $byRate[$rate] = ['base' => '0.00', 'vat' => '0.00'];
                }

                /** @var numeric-string $lineAmount */
                $lineAmount = $line->getLineAmount();
                /** @var numeric-string $vatAmount */
                $vatAmount = $line->getVatAmount();

                $byRate[$rate]['base'] = bcadd($byRate[$rate]['base'], $lineAmount, 2);
                $byRate[$rate]['vat'] = bcadd($byRate[$rate]['vat'], $vatAmount, 2);
                $total = bcadd($total, $vatAmount, 2);
            }
        }

        return [
            'total' => $total,
            'byRate' => $byRate,
            'invoiceCount' => \count($invoices),
        ];
    }

    /**
     * Calcule la TVA deductible sur la periode donnee.
     *
     * Somme des ecritures comptables sur le compte 445660 (TVA deductible).
     * Les ecritures au debit du 445660 representent la TVA recuperable.
     *
     * @return array{total: string, entryCount: int}
     */
    public function calculateDeductibleVat(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $qb = $this->em->createQueryBuilder();
        $entries = $qb->select('e')
            ->from(AccountingEntry::class, 'e')
            ->where('e.company = :company')
            ->andWhere('e.entryDate >= :from')
            ->andWhere('e.entryDate <= :to')
            ->andWhere('e.debitAccount = :vatAccount')
            ->setParameter('company', $company)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->setParameter('vatAccount', '445660')
            ->getQuery()
            ->getResult();

        /** @var numeric-string $total */
        $total = '0.00';

        /** @var AccountingEntry $entry */
        foreach ($entries as $entry) {
            /** @var numeric-string $amount */
            $amount = $entry->getAmount();
            $total = bcadd($total, $amount, 2);
        }

        return [
            'total' => $total,
            'entryCount' => \count($entries),
        ];
    }

    /**
     * Calcule le solde de TVA (collectee - deductible).
     *
     * Un resultat positif signifie un montant a payer.
     * Un resultat negatif signifie un credit de TVA.
     *
     * @return array{
     *     collected: string,
     *     deductible: string,
     *     balance: string,
     *     isDue: bool
     * }
     */
    public function calculateVatBalance(
        Company $company,
        \DateTimeImmutable $from,
        \DateTimeImmutable $to,
    ): array {
        $collected = $this->calculateCollectedVat($company, $from, $to);
        $deductible = $this->calculateDeductibleVat($company, $from, $to);

        /** @var numeric-string $collectedTotal */
        $collectedTotal = $collected['total'];
        /** @var numeric-string $deductibleTotal */
        $deductibleTotal = $deductible['total'];

        $balance = bcsub($collectedTotal, $deductibleTotal, 2);

        return [
            'collected' => $collected['total'],
            'deductible' => $deductible['total'],
            'balance' => $balance,
            'isDue' => bccomp($balance, '0.00', 2) > 0,
        ];
    }
}
