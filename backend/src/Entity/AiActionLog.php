<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Journal d'audit de chaque action effectuee par un agent IA.
 * Chaque appel de tool MCP est trace pour la securite et la transparence.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ai_action_logs')]
#[ORM\Index(columns: ['connection_id', 'created_at'], name: 'idx_ai_action_log_connection_date')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_ai_action_log_user_date')]
class AiActionLog
{
    public const STATUS_SUCCESS = 'success';
    public const STATUS_DENIED = 'denied';
    public const STATUS_ERROR = 'error';
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'actionLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private AiConnection $connection;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    // Nom du tool MCP appele (ex: create_invoice, send_invoice)
    #[ORM\Column(length: 100)]
    private string $toolName;

    // Parametres de l'appel (sanitises, pas de donnees sensibles)
    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $parameters = [];

    // Resultat de l'action
    #[ORM\Column(length: 30)]
    private string $status = self::STATUS_SUCCESS;

    // Message d'erreur ou de refus
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    // Duree d'execution en millisecondes
    #[ORM\Column(nullable: true)]
    private ?int $durationMs = null;

    // Adresse IP du client
    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

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

    public function getConnection(): AiConnection
    {
        return $this->connection;
    }

    public function setConnection(AiConnection $connection): static
    {
        $this->connection = $connection;

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

    public function getToolName(): string
    {
        return $this->toolName;
    }

    public function setToolName(string $toolName): static
    {
        $this->toolName = $toolName;

        return $this;
    }

    /** @return array<string, mixed> */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    /** @param array<string, mixed> $parameters */
    public function setParameters(array $parameters): static
    {
        $this->parameters = $parameters;

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

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): static
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
