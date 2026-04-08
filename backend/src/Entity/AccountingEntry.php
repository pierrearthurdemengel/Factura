<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Ecriture comptable.
 *
 * Chaque ecriture represente un mouvement dans le journal.
 * La source indique l'origine (facture, paiement, transaction bancaire).
 */
#[ORM\Entity]
#[ORM\Table(name: 'accounting_entries')]
#[ORM\Index(columns: ['company_id', 'entry_date'], name: 'idx_entry_company_date')]
#[ORM\Index(columns: ['journal_code'], name: 'idx_entry_journal')]
#[ORM\Index(columns: ['source_type', 'source_id'], name: 'idx_entry_source')]
class AccountingEntry
{
    // Codes journal standard
    public const JOURNAL_VENTES = 'VE';
    public const JOURNAL_ACHATS = 'AC';
    public const JOURNAL_BANQUE = 'BQ';
    public const JOURNAL_OPERATIONS_DIVERSES = 'OD';

    // Types de source
    public const SOURCE_INVOICE = 'invoice';
    public const SOURCE_PAYMENT = 'payment';
    public const SOURCE_BANK_TRANSACTION = 'bank_transaction';
    public const SOURCE_MANUAL = 'manual';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $entryDate;

    // Code journal : VE (ventes), AC (achats), BQ (banque), OD (operations diverses)
    #[ORM\Column(length: 5)]
    private string $journalCode;

    // Numero du compte debite
    #[ORM\Column(length: 10)]
    private string $debitAccount;

    // Numero du compte credite
    #[ORM\Column(length: 10)]
    private string $creditAccount;

    // Montant en euros (precision 2 decimales)
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private string $amount;

    #[ORM\Column(length: 255)]
    private string $label;

    // Reference de la piece (numero de facture, reference de transaction)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pieceReference = null;

    // Type de la source (invoice, payment, bank_transaction, manual)
    #[ORM\Column(length: 30)]
    private string $sourceType;

    // ID de l'entite source
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $sourceId = null;

    // Statut de validation (brouillon, valide)
    #[ORM\Column]
    private bool $validated = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->entryDate = new \DateTimeImmutable();
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

    public function getEntryDate(): \DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function setEntryDate(\DateTimeImmutable $entryDate): static
    {
        $this->entryDate = $entryDate;

        return $this;
    }

    public function getJournalCode(): string
    {
        return $this->journalCode;
    }

    public function setJournalCode(string $journalCode): static
    {
        $this->journalCode = $journalCode;

        return $this;
    }

    public function getDebitAccount(): string
    {
        return $this->debitAccount;
    }

    public function setDebitAccount(string $debitAccount): static
    {
        $this->debitAccount = $debitAccount;

        return $this;
    }

    public function getCreditAccount(): string
    {
        return $this->creditAccount;
    }

    public function setCreditAccount(string $creditAccount): static
    {
        $this->creditAccount = $creditAccount;

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

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getPieceReference(): ?string
    {
        return $this->pieceReference;
    }

    public function setPieceReference(?string $pieceReference): static
    {
        $this->pieceReference = $pieceReference;

        return $this;
    }

    public function getSourceType(): string
    {
        return $this->sourceType;
    }

    public function setSourceType(string $sourceType): static
    {
        $this->sourceType = $sourceType;

        return $this;
    }

    public function getSourceId(): ?Uuid
    {
        return $this->sourceId;
    }

    public function setSourceId(?Uuid $sourceId): static
    {
        $this->sourceId = $sourceId;

        return $this;
    }

    public function isValidated(): bool
    {
        return $this->validated;
    }

    public function setValidated(bool $validated): static
    {
        $this->validated = $validated;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
