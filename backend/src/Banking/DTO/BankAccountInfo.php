<?php

namespace App\Banking\DTO;

/**
 * Compte bancaire normalise retourne par un provider Open Banking.
 */
final readonly class BankAccountInfo
{
    public function __construct(
        public string $id,
        public ?string $iban,
        public string $currency,
        public string $type,
        public string $providerName,
        public string $providerAccountId,
    ) {
    }
}
