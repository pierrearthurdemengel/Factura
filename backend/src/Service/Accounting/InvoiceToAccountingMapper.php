<?php

namespace App\Service\Accounting;

use App\Entity\AccountingEntry;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere les ecritures comptables depuis une facture emise.
 *
 * Applique le schema comptable standard :
 * - Debit 411 (Clients) pour le TTC
 * - Credit 706 (Prestations) pour le HT
 * - Credit 44571 (TVA collectee) pour la TVA
 *
 * Les ecritures sont generees par taux de TVA pour respecter
 * l'obligation de ventilation dans le FEC.
 */
class InvoiceToAccountingMapper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere les ecritures comptables pour une facture emise.
     *
     * @return AccountingEntry[]
     */
    public function map(Invoice $invoice): array
    {
        $entries = [];
        $company = $invoice->getSeller();
        if (null === $company) {
            throw new \RuntimeException('La facture doit avoir un vendeur pour generer les ecritures comptables.');
        }
        $totalTtc = $invoice->getTotalIncludingTax();
        $issueDate = $invoice->getIssueDate();

        // Regrouper les lignes par taux de TVA
        $vatGroups = $this->groupByVatRate($invoice);

        // Ecriture 1 : Debit 411 (Clients) pour le TTC
        $clientEntry = new AccountingEntry();
        $clientEntry->setCompany($company);
        $clientEntry->setEntryDate($issueDate);
        $clientEntry->setJournalCode(AccountingEntry::JOURNAL_VENTES);
        $clientEntry->setDebitAccount('411000');
        $clientEntry->setCreditAccount('411000'); // Meme compte, c'est le debit
        $clientEntry->setAmount($totalTtc);
        $clientEntry->setLabel(sprintf('Facture %s - %s', $invoice->getNumber() ?? '', $invoice->getBuyer()->getName()));
        $clientEntry->setPieceReference($invoice->getNumber());
        $clientEntry->setSourceType(AccountingEntry::SOURCE_INVOICE);
        $clientEntry->setSourceId($invoice->getId());

        $this->em->persist($clientEntry);
        $entries[] = $clientEntry;

        // Ecritures 2+ : Credit 706 (Prestations) par taux de TVA
        foreach ($vatGroups as $vatRate => $amounts) {
            $htAmount = $amounts['ht'];
            $tvaAmount = $amounts['tva'];

            // Credit 706 pour le HT
            $revenueEntry = new AccountingEntry();
            $revenueEntry->setCompany($company);
            $revenueEntry->setEntryDate($issueDate);
            $revenueEntry->setJournalCode(AccountingEntry::JOURNAL_VENTES);
            $revenueEntry->setDebitAccount('706000');
            $revenueEntry->setCreditAccount('706000');
            $revenueEntry->setAmount($htAmount);
            $revenueEntry->setLabel(sprintf(
                'Facture %s - Prestations HT (TVA %s%%)',
                $invoice->getNumber() ?? '',
                $vatRate,
            ));
            $revenueEntry->setPieceReference($invoice->getNumber());
            $revenueEntry->setSourceType(AccountingEntry::SOURCE_INVOICE);
            $revenueEntry->setSourceId($invoice->getId());

            $this->em->persist($revenueEntry);
            $entries[] = $revenueEntry;

            // Credit 44571 pour la TVA collectee (sauf taux 0)
            /** @var numeric-string $tvaAmount */
            if (bccomp($tvaAmount, '0.00', 2) > 0) {
                $vatEntry = new AccountingEntry();
                $vatEntry->setCompany($company);
                $vatEntry->setEntryDate($issueDate);
                $vatEntry->setJournalCode(AccountingEntry::JOURNAL_VENTES);
                $vatEntry->setDebitAccount('445710');
                $vatEntry->setCreditAccount('445710');
                $vatEntry->setAmount($amounts['tva']);
                $vatEntry->setLabel(sprintf(
                    'Facture %s - TVA collectee %s%%',
                    $invoice->getNumber() ?? '',
                    $vatRate,
                ));
                $vatEntry->setPieceReference($invoice->getNumber());
                $vatEntry->setSourceType(AccountingEntry::SOURCE_INVOICE);
                $vatEntry->setSourceId($invoice->getId());

                $this->em->persist($vatEntry);
                $entries[] = $vatEntry;
            }
        }

        $this->em->flush();

        return $entries;
    }

    /**
     * Regroupe les montants par taux de TVA.
     *
     * @return array<string, array{ht: string, tva: string}>
     */
    private function groupByVatRate(Invoice $invoice): array
    {
        $groups = [];

        /** @var InvoiceLine $line */
        foreach ($invoice->getLines() as $line) {
            $rate = $line->getVatRate();
            if (!isset($groups[$rate])) {
                $groups[$rate] = ['ht' => '0.00', 'tva' => '0.00'];
            }

            /** @var numeric-string $unitPrice */
            $unitPrice = $line->getUnitPriceExcludingTax();
            /** @var numeric-string $quantity */
            $quantity = $line->getQuantity();
            /** @var numeric-string $rateStr */
            $rateStr = $rate;

            $lineHt = bcmul($unitPrice, $quantity, 2);
            $lineTva = bcmul($lineHt, bcdiv($rateStr, '100', 6), 2);

            $groups[$rate]['ht'] = bcadd($groups[$rate]['ht'], $lineHt, 2);
            $groups[$rate]['tva'] = bcadd($groups[$rate]['tva'], $lineTva, 2);
        }

        return $groups;
    }
}
