<?php

namespace App\Banking\DTO;

/**
 * Transaction bancaire normalisee retournee par un provider Open Banking.
 */
final readonly class BankTransactionInfo
{
    public function __construct(
        public string $id,
        public string $amount,
        public string $currency,
        public \DateTimeImmutable $date,
        public string $description,
        public ?string $reference,
        public TransactionType $type,
        public ?string $category,
        public string $providerName,
        public string $providerTransactionId,
    ) {
    }
}
