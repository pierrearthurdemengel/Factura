<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Journal de la piste d'audit fiable pour les devis.
 * Immuable : aucun setter, constructeur uniquement.
 */
#[ORM\Entity]
#[ORM\Table(name: 'quote_events')]
class QuoteEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['event:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private Quote $quote;

    // CREATED, STATUS_CHANGED, SENT, ACCEPTED, REJECTED, EXPIRED, CONVERTED
    #[ORM\Column(length: 50)]
    #[Groups(['event:read'])]
    private string $eventType;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    #[Groups(['event:read'])]
    private array $metadata;

    #[ORM\Column(nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column]
    #[Groups(['event:read'])]
    private \DateTimeImmutable $occurredAt;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        Quote $quote,
        string $eventType,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->id = Uuid::v7();
        $this->quote = $quote;
        $this->eventType = $eventType;
        $this->metadata = $metadata;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->occurredAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getQuote(): Quote
    {
        return $this->quote;
    }

    public function getEventType(): string
    {
        return $this->eventType;
    }

    /** @return array<string, mixed> */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }
}
