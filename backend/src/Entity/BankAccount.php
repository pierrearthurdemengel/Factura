<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Compte bancaire synchronise via Open Banking.
 *
 * Lie a une connexion bancaire, contient l'IBAN, le solde
 * et la liste des transactions synchronisees.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bank_accounts')]
class BankAccount
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['bank_connection:read', 'bank_account:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'accounts')]
    #[ORM\JoinColumn(nullable: false)]
    private BankConnection $bankConnection;

    // Identifiant du compte chez le provider Open Banking
    #[ORM\Column(length: 255)]
    private string $externalAccountId;

    #[ORM\Column(length: 34, nullable: true)]
    #[Groups(['bank_connection:read', 'bank_account:read'])]
    private ?string $iban = null;

    #[ORM\Column(length: 255)]
    #[Groups(['bank_connection:read', 'bank_account:read'])]
    private string $label;

    // Solde en centimes ou en decimal (mis a jour a chaque sync)
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    #[Groups(['bank_connection:read', 'bank_account:read'])]
    private ?string $balance = null;

    #[ORM\Column(length: 3)]
    #[Groups(['bank_account:read'])]
    private string $currency = 'EUR';

    /** @var Collection<int, BankTransaction> */
    #[ORM\OneToMany(mappedBy: 'bankAccount', targetEntity: BankTransaction::class, cascade: ['persist'])]
    #[ORM\OrderBy(['transactionDate' => 'DESC'])]
    private Collection $transactions;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->transactions = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getBankConnection(): BankConnection
    {
        return $this->bankConnection;
    }

    public function setBankConnection(BankConnection $bankConnection): static
    {
        $this->bankConnection = $bankConnection;

        return $this;
    }

    public function getExternalAccountId(): string
    {
        return $this->externalAccountId;
    }

    public function setExternalAccountId(string $externalAccountId): static
    {
        $this->externalAccountId = $externalAccountId;

        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;

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

    public function getBalance(): ?string
    {
        return $this->balance;
    }

    public function setBalance(?string $balance): static
    {
        $this->balance = $balance;

        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    /** @return Collection<int, BankTransaction> */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
