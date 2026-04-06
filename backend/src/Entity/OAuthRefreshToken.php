<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Refresh token OAuth 2.1.
 * Permet de renouveler l'access token sans redemander le consentement.
 * Duree de vie longue (30 jours).
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_refresh_tokens')]
#[ORM\Index(columns: ['token'], name: 'idx_oauth_refresh_token')]
class OAuthRefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 512, unique: true)]
    private string $token;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private OAuthAccessToken $accessToken;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        // Refresh token valide 30 jours
        $this->expiresAt = new \DateTimeImmutable('+30 days');
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): static
    {
        $this->token = $token;

        return $this;
    }

    public function getAccessToken(): OAuthAccessToken
    {
        return $this->accessToken;
    }

    public function setAccessToken(OAuthAccessToken $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return null !== $this->revokedAt;
    }

    public function revoke(): static
    {
        $this->revokedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
