<?php

namespace App\Message;

use App\Banking\BankProviderChain;
use App\Banking\Exception\NoBankProviderAvailableException;
use App\Entity\BankAccount;
use App\Entity\BankConnection;
use App\Entity\BankTransaction;
use App\Service\Banking\ReconciliationEngine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger : synchronise les transactions bancaires.
 *
 * Recupere les nouvelles transactions via le BankProviderChain,
 * les persiste en base, puis lance la reconciliation automatique.
 */
#[AsMessageHandler]
class SyncBankTransactionsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly BankProviderChain $providerChain,
        private readonly ReconciliationEngine $reconciliationEngine,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncBankTransactionsMessage $message): void
    {
        $connection = $this->em->getRepository(BankConnection::class)->find($message->getBankConnectionId());

        if (null === $connection) {
            $this->logger->error('Connexion bancaire introuvable.', [
                'connectionId' => $message->getBankConnectionId(),
            ]);

            return;
        }

        if (BankConnection::STATUS_ACTIVE !== $connection->getStatus()) {
            $this->logger->info('Synchronisation ignoree : connexion inactive.', [
                'connectionId' => $message->getBankConnectionId(),
                'status' => $connection->getStatus(),
            ]);

            return;
        }

        // Synchroniser les transactions pour chaque compte
        foreach ($connection->getAccounts() as $account) {
            $this->syncAccountTransactions($account, $connection);
        }

        // Reconciliation automatique des transactions non reconciliees
        foreach ($connection->getAccounts() as $account) {
            foreach ($account->getTransactions() as $transaction) {
                if ('NONE' === $transaction->getReconciliationStatus()) {
                    $this->reconciliationEngine->autoReconcile($transaction, $connection->getCompany());
                }
            }
        }

        $connection->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->logger->info('Synchronisation bancaire terminee.', [
            'connectionId' => $message->getBankConnectionId(),
            'bank' => $connection->getBankName(),
            'provider' => $connection->getProviderName(),
        ]);
    }

    /**
     * Recupere les nouvelles transactions d'un compte via le provider chain.
     */
    private function syncAccountTransactions(BankAccount $account, BankConnection $connection): void
    {
        // Fenetre de synchronisation : 30 jours ou depuis la derniere sync
        $from = $connection->getLastSyncAt() ?? new \DateTimeImmutable('-30 days');
        $to = new \DateTimeImmutable();

        try {
            $transactions = $this->providerChain->getTransactions(
                $account->getExternalAccountId(),
                $from,
                $to,
            );
        } catch (NoBankProviderAvailableException $e) {
            $this->logger->warning('Impossible de recuperer les transactions.', [
                'accountId' => (string) $account->getId(),
                'error' => $e->getMessage(),
            ]);

            return;
        }

        // Collecter les identifiants existants pour eviter les doublons
        $existingIds = [];
        foreach ($account->getTransactions() as $existing) {
            $existingIds[$existing->getExternalTransactionId()] = true;
        }

        $newCount = 0;
        foreach ($transactions as $txInfo) {
            if (isset($existingIds[$txInfo->providerTransactionId])) {
                continue;
            }

            $tx = new BankTransaction();
            $tx->setBankAccount($account);
            $tx->setExternalTransactionId($txInfo->providerTransactionId);
            $tx->setTransactionDate($txInfo->date);
            // Montant signe : negatif pour les debits
            $amount = \App\Banking\DTO\TransactionType::Debit === $txInfo->type
                ? '-' . $txInfo->amount
                : $txInfo->amount;
            $tx->setAmount($amount);
            $tx->setCurrency($txInfo->currency);
            $tx->setLabel($txInfo->description);
            $tx->setCategory($txInfo->category);

            $this->em->persist($tx);
            ++$newCount;
        }

        if ($newCount > 0) {
            $this->logger->info('Nouvelles transactions synchronisees.', [
                'accountId' => (string) $account->getId(),
                'count' => $newCount,
            ]);
        }

        // Mettre a jour le solde du compte
        $this->syncAccountBalance($account);
    }

    /**
     * Met a jour le solde du compte via le provider chain.
     */
    private function syncAccountBalance(BankAccount $account): void
    {
        try {
            $balances = $this->providerChain->getBalances($account->getExternalAccountId());

            if ([] !== $balances) {
                $account->setBalance($balances[0]->amount);
                $account->setCurrency($balances[0]->currency);
            }
        } catch (NoBankProviderAvailableException $e) {
            $this->logger->warning('Impossible de recuperer le solde.', [
                'accountId' => (string) $account->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
