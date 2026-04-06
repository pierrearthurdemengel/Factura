<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Token d'acces OAuth 2.1.
 * Utilise par les LLM pour appeler l'API au nom de l'utilisateur.
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_access_tokens')]
#[ORM\Index(columns: ['token'], name: 'idx_oauth_access_token')]
class OAuthAccessToken
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
    private OAuthClient $client;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
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

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function setClient(OAuthClient $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
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

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function markUsed(): static
    {
        $this->lastUsedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Verifie que le token est valide (non expire, non revoque).
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
