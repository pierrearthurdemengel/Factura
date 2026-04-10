<?php

namespace App\Banking\DTO;

/**
 * Solde d'un compte bancaire normalise.
 */
final readonly class AccountBalance
{
    public function __construct(
        public string $amount,
        public string $currency,
        public BalanceType $type,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
