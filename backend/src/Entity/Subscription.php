<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'subscriptions')]
class Subscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    // free, pro, team
    #[ORM\Column(length: 20)]
    private string $plan = 'free';

    // Identifiant du customer Stripe
    #[ORM\Column(nullable: true)]
    private ?string $stripeCustomerId = null;

    // Identifiant de l'abonnement Stripe
    #[ORM\Column(nullable: true)]
    private ?string $stripeSubscriptionId = null;

    // active, cancelled, past_due
    #[ORM\Column(length: 20)]
    private string $status = 'active';

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $currentPeriodEnd = null;

    // Compteur de factures emises ce mois-ci (pour le quota Free)
    #[ORM\Column]
    private int $invoicesThisMonth = 0;

    #[ORM\Column(nullable: true)]
    private ?int $invoicesCountMonth = null;

    #[ORM\Column(nullable: true)]
    private ?int $invoicesCountYear = null;

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

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): static
    {
        $this->stripeCustomerId = $stripeCustomerId;

        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripeSubscriptionId;
    }

    public function setStripeSubscriptionId(?string $stripeSubscriptionId): static
    {
        $this->stripeSubscriptionId = $stripeSubscriptionId;

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

    public function getCurrentPeriodEnd(): ?\DateTimeImmutable
    {
        return $this->currentPeriodEnd;
    }

    public function setCurrentPeriodEnd(?\DateTimeImmutable $currentPeriodEnd): static
    {
        $this->currentPeriodEnd = $currentPeriodEnd;

        return $this;
    }

    public function getInvoicesThisMonth(): int
    {
        return $this->invoicesThisMonth;
    }

    public function setInvoicesThisMonth(int $invoicesThisMonth): static
    {
        $this->invoicesThisMonth = $invoicesThisMonth;

        return $this;
    }

    public function incrementInvoicesThisMonth(): static
    {
        ++$this->invoicesThisMonth;

        return $this;
    }

    public function resetMonthlyCounter(): static
    {
        $this->invoicesThisMonth = 0;

        return $this;
    }

    public function getInvoicesCountMonth(): ?int
    {
        return $this->invoicesCountMonth;
    }

    public function setInvoicesCountMonth(?int $invoicesCountMonth): static
    {
        $this->invoicesCountMonth = $invoicesCountMonth;

        return $this;
    }

    public function getInvoicesCountYear(): ?int
    {
        return $this->invoicesCountYear;
    }

    public function setInvoicesCountYear(?int $invoicesCountYear): static
    {
        $this->invoicesCountYear = $invoicesCountYear;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Verifie si le quota mensuel est depasse (30 factures max en plan Free).
     */
    public function isQuotaExceeded(): bool
    {
        if ('free' !== $this->plan) {
            return false;
        }

        return $this->invoicesThisMonth >= 30;
    }
}
