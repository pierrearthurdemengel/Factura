<?php

namespace App\Banking\DTO;

/**
 * Resultat d'une demande d'autorisation d'acces bancaire.
 */
final readonly class AuthorizationResult
{
    public function __construct(
        public string $authorizationId,
        public ?string $redirectUrl,
        public AuthorizationStatus $status,
        public ?\DateTimeImmutable $expiresAt,
        public string $providerName,
    ) {
    }
}
