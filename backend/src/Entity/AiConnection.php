<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Connexion d'un agent IA au compte utilisateur.
 * Represente le lien entre un utilisateur et un client OAuth (Claude, ChatGPT, etc.).
 */
#[ORM\Entity]
#[ORM\Table(name: 'ai_connections')]
#[ORM\Index(columns: ['user_id', 'provider'], name: 'idx_ai_connection_user_provider')]
class AiConnection
{
    public const PROVIDER_CLAUDE = 'claude';
    public const PROVIDER_CHATGPT = 'chatgpt';
    public const PROVIDER_GEMINI = 'gemini';
    public const PROVIDER_CUSTOM = 'custom';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_REVOKED = 'revoked';

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
    private OAuthClient $client;

    // Fournisseur LLM (claude, chatgpt, gemini, custom)
    #[ORM\Column(length: 50)]
    private string $provider;

    // Nom personnalise donne par l'utilisateur
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    // Mode confirmation pour les actions destructrices
    #[ORM\Column]
    private bool $requireConfirmation = true;

    /** @var list<string> Scopes accordes a cette connexion */
    #[ORM\Column(type: 'json')]
    private array $grantedScopes = [];

    // Compteur de requetes total
    #[ORM\Column]
    private int $totalRequests = 0;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastActivityAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, AiActionLog> */
    #[ORM\OneToMany(targetEntity: AiActionLog::class, mappedBy: 'connection')]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $actionLogs;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->actionLogs = new ArrayCollection();
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

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function setClient(OAuthClient $client): static
    {
        $this->client = $client;

        return $this;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function isActive(): bool
    {
        return self::STATUS_ACTIVE === $this->status;
    }

    /**
     * Pause la connexion (l'agent ne peut plus agir).
     */
    public function pause(): static
    {
        $this->status = self::STATUS_PAUSED;

        return $this;
    }

    /**
     * Revoque definitivement la connexion (kill switch).
     */
    public function revoke(): static
    {
        $this->status = self::STATUS_REVOKED;
        $this->revokedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isRequireConfirmation(): bool
    {
        return $this->requireConfirmation;
    }

    public function setRequireConfirmation(bool $requireConfirmation): static
    {
        $this->requireConfirmation = $requireConfirmation;

        return $this;
    }

    /** @return list<string> */
    public function getGrantedScopes(): array
    {
        return $this->grantedScopes;
    }

    /** @param list<string> $grantedScopes */
    public function setGrantedScopes(array $grantedScopes): static
    {
        $this->grantedScopes = $grantedScopes;

        return $this;
    }

    public function getTotalRequests(): int
    {
        return $this->totalRequests;
    }

    public function incrementRequests(): static
    {
        ++$this->totalRequests;
        $this->lastActivityAt = new \DateTimeImmutable();

        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, AiActionLog> */
    public function getActionLogs(): Collection
    {
        return $this->actionLogs;
    }
}
