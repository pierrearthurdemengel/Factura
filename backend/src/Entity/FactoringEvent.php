<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Evenement d'audit pour les demandes d'affacturage.
 * Immuable : aucun setter, constructeur uniquement.
 */
#[ORM\Entity]
#[ORM\Table(name: 'factoring_events')]
#[ORM\Index(columns: ['factoring_request_id'], name: 'idx_factoring_event_request')]
class FactoringEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private FactoringRequest $factoringRequest;

    // REQUESTED, APPROVED, REJECTED, PAID, CANCELLED, WEBHOOK_RECEIVED
    #[ORM\Column(length: 50)]
    private string $eventType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        FactoringRequest $factoringRequest,
        string $eventType,
        array $payload = [],
    ) {
        $this->id = Uuid::v7();
        $this->factoringRequest = $factoringRequest;
        $this->eventType = $eventType;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getFactoringRequest(): FactoringRequest
    {
        return $this->factoringRequest;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
