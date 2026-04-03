<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Journal de la piste d'audit fiable (PAF).
 * Immuable : aucun setter, constructeur uniquement.
 * Chaque evenement enregistre un changement d'etat de la facture.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice_events')]
class InvoiceEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['event:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'events')]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    // CREATED, STATUS_CHANGED, TRANSMITTED_TO_PDP, RECEIVED_BY_PDP,
    // ACKNOWLEDGED, REJECTED, PAID, ARCHIVED, VIEWED_BY_BUYER
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
        Invoice $invoice,
        string $eventType,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ) {
        $this->id = Uuid::v7();
        $this->invoice = $invoice;
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

    public function getInvoice(): Invoice
    {
        return $this->invoice;
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
