<?php

namespace App\Message;

/**
 * Message Messenger pour la synchronisation asynchrone des transactions bancaires.
 */
class SyncBankTransactionsMessage
{
    public function __construct(
        private readonly string $bankConnectionId,
    ) {
    }

    public function getBankConnectionId(): string
    {
        return $this->bankConnectionId;
    }
}
