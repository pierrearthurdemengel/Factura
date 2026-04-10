<?php

namespace App\Banking\DTO;

/**
 * Information normalisee sur une banque disponible, quel que soit le provider.
 */
final readonly class BankInfo
{
    public function __construct(
        public string $id,
        public string $name,
        public string $countryCode,
        public ?string $logoUrl,
        public string $providerName,
    ) {
    }
}
