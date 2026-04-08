<?php

namespace App\Message;

use App\Entity\BankConnection;
use App\Service\Banking\ReconciliationEngine;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger : synchronise les transactions bancaires.
 *
 * En production, appelle l'API GoCardless pour recuperer les nouvelles
 * transactions. Pour l'instant, met a jour le lastSyncAt et lance
 * la reconciliation automatique sur les transactions existantes.
 */
#[AsMessageHandler]
class SyncBankTransactionsHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
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

        $company = $connection->getCompany();

        // Reconciliation automatique des transactions non reconciliees
        foreach ($connection->getAccounts() as $account) {
            foreach ($account->getTransactions() as $transaction) {
                if ('NONE' === $transaction->getReconciliationStatus()) {
                    $this->reconciliationEngine->autoReconcile($transaction, $company);
                }
            }
        }

        $connection->setLastSyncAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->logger->info('Synchronisation bancaire terminee.', [
            'connectionId' => $message->getBankConnectionId(),
            'bank' => $connection->getBankName(),
        ]);
    }
}
