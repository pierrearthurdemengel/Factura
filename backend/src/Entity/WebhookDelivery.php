<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Historique d'envoi d'un webhook.
 *
 * Chaque tentative d'envoi est enregistree avec le statut HTTP
 * de la reponse, le nombre de tentatives, et les eventuelles erreurs.
 * Permet le replay en cas d'echec.
 */
#[ORM\Entity]
#[ORM\Table(name: 'webhook_deliveries')]
#[ORM\Index(columns: ['event_type'], name: 'idx_webhook_delivery_event')]
#[ORM\Index(columns: ['status'], name: 'idx_webhook_delivery_status')]
class WebhookDelivery
{
    // Statuts de livraison
    public const STATUS_PENDING = 'pending';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    // Nombre maximum de tentatives
    public const MAX_ATTEMPTS = 3;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'deliveries')]
    #[ORM\JoinColumn(nullable: false)]
    private WebhookEndpoint $endpoint;

    #[ORM\Column(length: 100)]
    private string $eventType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(nullable: true)]
    private ?int $httpStatusCode = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $responseBody = null;

    #[ORM\Column]
    private int $attempts = 0;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $nextRetryAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deliveredAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEndpoint(): WebhookEndpoint
    {
        return $this->endpoint;
    }

    public function setEndpoint(WebhookEndpoint $endpoint): static
    {
        $this->endpoint = $endpoint;

        return $this;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

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

    public function getHttpStatusCode(): ?int
    {
        return $this->httpStatusCode;
    }

    public function setHttpStatusCode(?int $httpStatusCode): static
    {
        $this->httpStatusCode = $httpStatusCode;

        return $this;
    }

    public function getResponseBody(): ?string
    {
        return $this->responseBody;
    }

    public function setResponseBody(?string $responseBody): static
    {
        $this->responseBody = $responseBody;

        return $this;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): static
    {
        ++$this->attempts;

        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): static
    {
        $this->lastError = $lastError;

        return $this;
    }

    public function getNextRetryAt(): ?\DateTimeImmutable
    {
        return $this->nextRetryAt;
    }

    public function setNextRetryAt(?\DateTimeImmutable $nextRetryAt): static
    {
        $this->nextRetryAt = $nextRetryAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): static
    {
        $this->deliveredAt = $deliveredAt;

        return $this;
    }

    /**
     * Verifie si un retry est encore possible.
     */
    public function canRetry(): bool
    {
        return $this->attempts < self::MAX_ATTEMPTS && self::STATUS_FAILED === $this->status;
    }

    /**
     * Marque la livraison comme reussie.
     */
    public function markAsSuccess(int $httpStatus, string $responseBody): static
    {
        $this->status = self::STATUS_SUCCESS;
        $this->httpStatusCode = $httpStatus;
        $this->responseBody = $responseBody;
        $this->deliveredAt = new \DateTimeImmutable();
        $this->nextRetryAt = null;

        return $this;
    }

    /**
     * Marque la livraison comme echouee avec backoff exponentiel.
     */
    public function markAsFailed(string $error, ?int $httpStatus = null): static
    {
        $this->status = self::STATUS_FAILED;
        $this->lastError = $error;
        $this->httpStatusCode = $httpStatus;

        if ($this->canRetry()) {
            // Backoff exponentiel : 60s, 300s, 900s
            $delay = (int) (60 * (5 ** ($this->attempts - 1)));
            $this->nextRetryAt = new \DateTimeImmutable(sprintf('+%d seconds', $delay));
        }

        return $this;
    }
}
