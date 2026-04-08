<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Demande d'affacturage pour une facture.
 *
 * Represente une demande de financement envoyee a un partenaire
 * (Defacto, Silvr, Aria, Hokodo). Le cycle de vie suit les etats :
 * PENDING → APPROVED/REJECTED → PAID/CANCELLED.
 */
#[ORM\Entity]
#[ORM\Table(name: 'factoring_requests')]
#[ORM\Index(columns: ['status'], name: 'idx_factoring_status')]
#[ORM\Index(columns: ['partner_id'], name: 'idx_factoring_partner')]
#[ORM\UniqueConstraint(name: 'uniq_factoring_invoice_active', columns: ['invoice_id', 'status'])]
#[ORM\HasLifecycleCallbacks]
class FactoringRequest
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_PAID = 'PAID';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    // Identifiant du partenaire (defacto, silvr, aria, hokodo)
    #[ORM\Column(length: 50)]
    private string $partnerId;

    // Montant finance en centimes
    #[ORM\Column(type: 'bigint')]
    private int $amount;

    // Frais du partenaire en centimes
    #[ORM\Column(type: 'bigint')]
    private int $fee;

    // Commission Ma Facture Pro en centimes
    #[ORM\Column(type: 'bigint')]
    private int $commission = 0;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // Reference retournee par le partenaire
    #[ORM\Column(nullable: true)]
    private ?string $partnerReferenceId = null;

    // Score du client au moment de la demande
    #[ORM\Column]
    private int $clientScore;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    /** @var Collection<int, FactoringEvent> */
    #[ORM\OneToMany(mappedBy: 'factoringRequest', targetEntity: FactoringEvent::class, cascade: ['persist'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $events;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->requestedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->events = new ArrayCollection();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function setInvoice(Invoice $invoice): static
    {
        $this->invoice = $invoice;

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

    public function getPartnerId(): string
    {
        return $this->partnerId;
    }

    public function setPartnerId(string $partnerId): static
    {
        $this->partnerId = $partnerId;

        return $this;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getFee(): int
    {
        return $this->fee;
    }

    public function setFee(int $fee): static
    {
        $this->fee = $fee;

        return $this;
    }

    public function getCommission(): int
    {
        return $this->commission;
    }

    public function setCommission(int $commission): static
    {
        $this->commission = $commission;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getPartnerReferenceId(): ?string
    {
        return $this->partnerReferenceId;
    }

    public function setPartnerReferenceId(?string $partnerReferenceId): static
    {
        $this->partnerReferenceId = $partnerReferenceId;

        return $this;
    }

    public function getClientScore(): int
    {
        return $this->clientScore;
    }

    public function setClientScore(int $clientScore): static
    {
        $this->clientScore = $clientScore;

        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): static
    {
        $this->rejectionReason = $rejectionReason;

        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): static
    {
        $this->approvedAt = $approvedAt;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }

    /** @return Collection<int, FactoringEvent> */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Verifie si la demande est dans un etat terminal.
     */
    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_PAID, self::STATUS_REJECTED, self::STATUS_CANCELLED], true);
    }

    /**
     * Verifie si la demande peut etre annulee.
     */
    public function isCancellable(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }
}
