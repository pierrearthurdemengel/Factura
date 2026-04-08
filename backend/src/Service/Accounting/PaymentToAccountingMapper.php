<?php

namespace App\Service\Accounting;

use App\Entity\AccountingEntry;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere les ecritures comptables pour un paiement recu.
 *
 * Applique le schema comptable standard :
 * - Debit 512 (Banque) pour le montant recu
 * - Credit 411 (Clients) pour solder la creance
 */
class PaymentToAccountingMapper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere les ecritures pour un paiement recu sur une facture.
     *
     * @return AccountingEntry[]
     */
    public function map(Invoice $invoice): array
    {
        $entries = [];
        $company = $invoice->getSeller();
        $amount = $invoice->getTotalIncludingTax();

        // Debit 512 (Banque) - montant recu
        $bankEntry = new AccountingEntry();
        $bankEntry->setCompany($company);
        $bankEntry->setEntryDate(new \DateTimeImmutable());
        $bankEntry->setJournalCode(AccountingEntry::JOURNAL_BANQUE);
        $bankEntry->setDebitAccount('512000');
        $bankEntry->setCreditAccount('512000');
        $bankEntry->setAmount($amount);
        $bankEntry->setLabel(sprintf(
            'Encaissement facture %s - %s',
            $invoice->getNumber() ?? '',
            $invoice->getBuyer()->getName(),
        ));
        $bankEntry->setPieceReference($invoice->getNumber());
        $bankEntry->setSourceType(AccountingEntry::SOURCE_PAYMENT);
        $bankEntry->setSourceId($invoice->getId());

        $this->em->persist($bankEntry);
        $entries[] = $bankEntry;

        // Credit 411 (Clients) - solde de la creance
        $clientEntry = new AccountingEntry();
        $clientEntry->setCompany($company);
        $clientEntry->setEntryDate(new \DateTimeImmutable());
        $clientEntry->setJournalCode(AccountingEntry::JOURNAL_BANQUE);
        $clientEntry->setDebitAccount('411000');
        $clientEntry->setCreditAccount('411000');
        $clientEntry->setAmount($amount);
        $clientEntry->setLabel(sprintf(
            'Solde creance facture %s - %s',
            $invoice->getNumber() ?? '',
            $invoice->getBuyer()->getName(),
        ));
        $clientEntry->setPieceReference($invoice->getNumber());
        $clientEntry->setSourceType(AccountingEntry::SOURCE_PAYMENT);
        $clientEntry->setSourceId($invoice->getId());

        $this->em->persist($clientEntry);
        $entries[] = $clientEntry;

        $this->em->flush();

        return $entries;
    }
}
