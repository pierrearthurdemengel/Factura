<?php

namespace App\Service\Accounting;

use App\Entity\AccountingEntry;
use App\Entity\BankTransaction;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Genere les ecritures comptables depuis une transaction bancaire categorisee.
 *
 * Utilise le TransactionCategorizer pour determiner le compte de charge/produit,
 * puis genere l'ecriture de debit/credit correspondante.
 */
class BankTransactionToAccountingMapper
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TransactionCategorizer $categorizer,
    ) {
    }

    /**
     * Genere les ecritures comptables pour une transaction bancaire.
     *
     * @return AccountingEntry[]
     */
    public function map(BankTransaction $transaction): array
    {
        $entries = [];
        $amount = abs((float) $transaction->getAmount());
        $amountStr = number_format($amount, 2, '.', '');
        $isDebit = (float) $transaction->getAmount() < 0;

        $account = $transaction->getBankAccount();
        $connection = $account->getBankConnection();
        $company = $connection->getCompany();

        // Tenter la categorisation automatique
        $category = $this->categorizer->categorize($transaction->getLabel());

        if (null === $category) {
            // Transaction non categorisable : pas d'ecriture generee
            return [];
        }

        $chargeAccount = $category['account'];

        if ($isDebit) {
            // Depense : debit du compte de charge, credit de la banque
            $entry = new AccountingEntry();
            $entry->setCompany($company);
            $entry->setEntryDate($transaction->getTransactionDate());
            $entry->setJournalCode(AccountingEntry::JOURNAL_BANQUE);
            $entry->setDebitAccount($chargeAccount);
            $entry->setCreditAccount('512000');
            $entry->setAmount($amountStr);
            $entry->setLabel(sprintf('%s - %s', $category['label'], $transaction->getLabel()));
            $entry->setSourceType(AccountingEntry::SOURCE_BANK_TRANSACTION);
            $entry->setSourceId($transaction->getId());

            $this->em->persist($entry);
            $entries[] = $entry;
        } else {
            // Recette : debit de la banque, credit du compte de produit
            $entry = new AccountingEntry();
            $entry->setCompany($company);
            $entry->setEntryDate($transaction->getTransactionDate());
            $entry->setJournalCode(AccountingEntry::JOURNAL_BANQUE);
            $entry->setDebitAccount('512000');
            $entry->setCreditAccount($chargeAccount);
            $entry->setAmount($amountStr);
            $entry->setLabel(sprintf('%s - %s', $category['label'], $transaction->getLabel()));
            $entry->setSourceType(AccountingEntry::SOURCE_BANK_TRANSACTION);
            $entry->setSourceId($transaction->getId());

            $this->em->persist($entry);
            $entries[] = $entry;
        }

        $this->em->flush();

        return $entries;
    }
}
