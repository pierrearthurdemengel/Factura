<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Plan comptable d'une entreprise.
 *
 * Par defaut, utilise le PCG (Plan Comptable General) francais.
 * Chaque entreprise peut personnaliser ses comptes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_plans')]
class AccountingPlan
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(length: 100)]
    private string $name = 'Plan Comptable General';

    /** @var Collection<int, AccountingAccount> */
    #[ORM\OneToMany(mappedBy: 'plan', targetEntity: AccountingAccount::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['number' => 'ASC'])]
    private Collection $accounts;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->accounts = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /** @return Collection<int, AccountingAccount> */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function addAccount(AccountingAccount $account): static
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->add($account);
            $account->setPlan($this);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Trouve un compte par son numero.
     */
    public function findAccount(string $number): ?AccountingAccount
    {
        foreach ($this->accounts as $account) {
            if ($account->getNumber() === $number) {
                return $account;
            }
        }

        return null;
    }
}
