<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Compte comptable du plan comptable.
 *
 * Les types suivent la norme PCG :
 * - actif (classes 1-5 actif)
 * - passif (classes 1-5 passif)
 * - charge (classe 6)
 * - produit (classe 7)
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_account_plan_number', columns: ['plan_id', 'number'])]
class AccountingAccount
{
    public const TYPE_ACTIF = 'actif';
    public const TYPE_PASSIF = 'passif';
    public const TYPE_CHARGE = 'charge';
    public const TYPE_PRODUIT = 'produit';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'accounts')]
    #[ORM\JoinColumn(nullable: false)]
    private AccountingPlan $plan;

    // Numero du compte (ex: 411000, 706000, 445710)
    #[ORM\Column(length: 10)]
    private string $number;

    #[ORM\Column(length: 255)]
    private string $label;

    // actif, passif, charge, produit
    #[ORM\Column(length: 20)]
    private string $type;

    // Compte parent (ex: 411000 → parent 411)
    #[ORM\Column(length: 10, nullable: true)]
    private ?string $parentNumber = null;

    #[ORM\Column]
    private bool $isDefault = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getPlan(): AccountingPlan
    {
        return $this->plan;
    }

    public function setPlan(AccountingPlan $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
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

    public function getParentNumber(): ?string
    {
        return $this->parentNumber;
    }

    public function setParentNumber(?string $parentNumber): static
    {
        $this->parentNumber = $parentNumber;

        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;

        return $this;
    }
}
