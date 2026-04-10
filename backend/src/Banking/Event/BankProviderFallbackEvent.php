<?php

namespace App\Banking\Event;

/**
 * Emis lorsqu'un provider bancaire echoue et qu'un fallback est active.
 * Permet le monitoring des defaillances de providers.
 */
final readonly class BankProviderFallbackEvent
{
    public function __construct(
        public string $failedProvider,
        public string $fallbackProvider,
        public string $operation,
        public string $reason,
    ) {
    }
}
