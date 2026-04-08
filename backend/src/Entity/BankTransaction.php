<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Transaction bancaire synchronisee depuis Open Banking.
 *
 * Peut etre reconciliee avec une facture pour declencher
 * automatiquement la transition vers le statut PAID.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bank_transactions')]
#[ORM\Index(columns: ['bank_account_id', 'transaction_date'], name: 'idx_bank_tx_account_date')]
class BankTransaction
{
    // Statuts de reconciliation
    public const RECONCILIATION_NONE = 'NONE';
    public const RECONCILIATION_SUGGESTED = 'SUGGESTED';
    public const RECONCILIATION_CONFIRMED = 'CONFIRMED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['bank_transaction:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'transactions')]
    #[ORM\JoinColumn(nullable: false)]
    private BankAccount $bankAccount;

    // Identifiant externe de la transaction chez GoCardless
    #[ORM\Column(length: 255, unique: true)]
    private string $externalTransactionId;

    #[ORM\Column(type: 'date_immutable')]
    #[Groups(['bank_transaction:read'])]
    private \DateTimeImmutable $transactionDate;

    // Montant (positif = credit, negatif = debit)
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['bank_transaction:read'])]
    private string $amount;

    #[ORM\Column(length: 3)]
    #[Groups(['bank_transaction:read'])]
    private string $currency = 'EUR';

    // Libelle de la transaction bancaire
    #[ORM\Column(length: 500)]
    #[Groups(['bank_transaction:read'])]
    private string $label;

    // Categorie (si fournie par la banque)
    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['bank_transaction:read'])]
    private ?string $category = null;

    // Facture reconciliee (si une correspondance a ete trouvee)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['bank_transaction:read'])]
    private ?Invoice $reconciledInvoice = null;

    // Statut de reconciliation
    #[ORM\Column(length: 20)]
    #[Groups(['bank_transaction:read'])]
    private string $reconciliationStatus = self::RECONCILIATION_NONE;

    // Score de confiance de la reconciliation (0-100)
    #[ORM\Column(nullable: true)]
    #[Groups(['bank_transaction:read'])]
    private ?int $reconciliationScore = null;

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

    public function getBankAccount(): BankAccount
    {
        return $this->bankAccount;
    }

    public function setBankAccount(BankAccount $bankAccount): static
    {
        $this->bankAccount = $bankAccount;

        return $this;
    }

    public function getExternalTransactionId(): string
    {
        return $this->externalTransactionId;
    }

    public function setExternalTransactionId(string $externalTransactionId): static
    {
        $this->externalTransactionId = $externalTransactionId;

        return $this;
    }

    public function getTransactionDate(): \DateTimeImmutable
    {
        return $this->transactionDate;
    }

    public function setTransactionDate(\DateTimeImmutable $transactionDate): static
    {
        $this->transactionDate = $transactionDate;

        return $this;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getReconciledInvoice(): ?Invoice
    {
        return $this->reconciledInvoice;
    }

    public function setReconciledInvoice(?Invoice $reconciledInvoice): static
    {
        $this->reconciledInvoice = $reconciledInvoice;

        return $this;
    }

    public function getReconciliationStatus(): string
    {
        return $this->reconciliationStatus;
    }

    public function setReconciliationStatus(string $reconciliationStatus): static
    {
        $this->reconciliationStatus = $reconciliationStatus;

        return $this;
    }

    public function getReconciliationScore(): ?int
    {
        return $this->reconciliationScore;
    }

    public function setReconciliationScore(?int $reconciliationScore): static
    {
        $this->reconciliationScore = $reconciliationScore;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
