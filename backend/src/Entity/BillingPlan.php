<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Plan de facturation.
 * Deux modeles coexistent : forfait fixe et succes partage.
 * L'utilisateur choisit celui qui lui convient le mieux.
 */
#[ORM\Entity]
#[ORM\Table(name: 'billing_plans')]
class BillingPlan
{
    // Types de plans
    public const TYPE_FREE = 'free';
    public const TYPE_FIXED = 'fixed';
    public const TYPE_SUCCESS_BASED = 'success_based';
    public const TYPE_CABINET = 'cabinet';

    // Seuil gratuit pour le plan succes partage (50 000 EUR/an)
    public const SUCCESS_THRESHOLD = '50000.00';

    // Taux au-dela du seuil (0.1%)
    public const SUCCESS_RATE = '0.001';

    // Plafond annuel (588 EUR/an = 49 EUR/mois)
    public const SUCCESS_CAP_ANNUAL = '588.00';

    // Prix fixes mensuels
    public const PRICE_PRO_MONTHLY = '14.00';
    public const PRICE_CABINET_BASE = '79.00';
    public const PRICE_CABINET_PER_CLIENT = '2.00';
    public const CABINET_INCLUDED_CLIENTS = 20;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 30)]
    private string $type;

    #[ORM\Column(length: 50)]
    private string $name;

    // Prix mensuel pour les plans fixes (null pour succes partage)
    #[ORM\Column(length: 15, nullable: true)]
    private ?string $monthlyPrice = null;

    // Actif ou desactive
    #[ORM\Column]
    private bool $active = true;

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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

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

    public function getMonthlyPrice(): ?string
    {
        return $this->monthlyPrice;
    }

    public function setMonthlyPrice(?string $monthlyPrice): static
    {
        $this->monthlyPrice = $monthlyPrice;

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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
