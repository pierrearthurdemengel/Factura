<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Cle d'API pour l'acces programmatique a la plateforme.
 *
 * Chaque cle est liee a un utilisateur et une entreprise,
 * avec un plan tarifaire qui determine les limites de debit.
 * Le hash de la cle est stocke (pas la cle en clair).
 */
#[ORM\Entity]
#[ORM\Table(name: 'api_keys')]
#[ORM\Index(columns: ['key_hash'], name: 'idx_api_key_hash')]
class ApiKey
{
    // Plans tarifaires et leurs limites
    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_TEAM = 'team';

    public const RATE_LIMITS = [
        self::PLAN_FREE => 100,
        self::PLAN_PRO => 1000,
        self::PLAN_TEAM => 10000,
    ];

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(length: 100)]
    private string $name;

    // Hash SHA-256 du prefixe + cle
    #[ORM\Column(length: 64, unique: true)]
    private string $keyHash;

    // Prefixe visible (ex: "mfp_live_abc12")
    #[ORM\Column(length: 20)]
    private string $keyPrefix;

    #[ORM\Column(length: 20)]
    private string $plan = self::PLAN_FREE;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    #[ORM\Column]
    private int $requestCount = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getKeyHash(): string
    {
        return $this->keyHash;
    }

    public function setKeyHash(string $keyHash): static
    {
        $this->keyHash = $keyHash;

        return $this;
    }

    public function getKeyPrefix(): string
    {
        return $this->keyPrefix;
    }

    public function setKeyPrefix(string $keyPrefix): static
    {
        $this->keyPrefix = $keyPrefix;

        return $this;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * @param list<string> $scopes
     */
    public function setScopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }

    public function getLastUsedAt(): ?\DateTimeImmutable
    {
        return $this->lastUsedAt;
    }

    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static
    {
        $this->lastUsedAt = $lastUsedAt;

        return $this;
    }

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function incrementRequestCount(): static
    {
        ++$this->requestCount;
        $this->lastUsedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Retourne la limite horaire de requetes selon le plan.
     */
    public function getRateLimit(): int
    {
        return self::RATE_LIMITS[$this->plan] ?? self::RATE_LIMITS[self::PLAN_FREE];
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
