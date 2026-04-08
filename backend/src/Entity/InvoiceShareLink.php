<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Lien de partage public pour une facture.
 * Permet au destinataire de visualiser et confirmer la reception sans inscription.
 */
#[ORM\Entity]
#[ORM\Table(name: 'invoice_share_links')]
#[ORM\Index(columns: ['token'], name: 'idx_share_link_token')]
class InvoiceShareLink
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    // Token unique pour l'URL publique /pay/{token}
    #[ORM\Column(length: 64, unique: true)]
    private string $token;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    // Date a laquelle le destinataire a confirme la reception
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acknowledgedAt = null;

    // Date a laquelle le paiement a ete confirme
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    // Code parrainage integre dans le lien (pour acquisition virale)
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $referralCode = null;

    #[ORM\Column]
    private int $viewCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->token = bin2hex(random_bytes(32));
        $this->expiresAt = new \DateTimeImmutable('+90 days');
        $this->createdAt = new \DateTimeImmutable();
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

    public function getToken(): string
    {
        return $this->token;
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

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    /**
     * Enregistre la premiere consultation du lien.
     */
    public function markViewed(): static
    {
        if (null === $this->viewedAt) {
            $this->viewedAt = new \DateTimeImmutable();
        }
        ++$this->viewCount;

        return $this;
    }

    public function getAcknowledgedAt(): ?\DateTimeImmutable
    {
        return $this->acknowledgedAt;
    }

    /**
     * Le destinataire confirme la reception de la facture (1 clic).
     */
    public function acknowledge(): static
    {
        $this->acknowledgedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isAcknowledged(): bool
    {
        return null !== $this->acknowledgedAt;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function markPaid(): static
    {
        $this->paidAt = new \DateTimeImmutable();

        return $this;
    }

    public function getReferralCode(): ?string
    {
        return $this->referralCode;
    }

    public function setReferralCode(?string $referralCode): static
    {
        $this->referralCode = $referralCode;

        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
