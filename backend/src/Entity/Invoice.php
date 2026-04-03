<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\DateFilter;
use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\RangeFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'invoices')]
#[ORM\Index(columns: ['status', 'issue_date'], name: 'idx_invoice_status_date')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER')"),
        new Put(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)"),
    ],
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']],
    order: ['issueDate' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'number' => 'partial',
    'buyer.name' => 'partial',
    'status' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['issueDate', 'dueDate'])]
#[ApiFilter(OrderFilter::class, properties: ['issueDate', 'totalIncludingTax', 'status'])]
#[ApiFilter(RangeFilter::class, properties: ['totalIncludingTax'])]
class Invoice
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['invoice:read'])]
    private ?Uuid $id = null;

    // Numero sequentiel obligatoire (ex: FA-2026-0001)
    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $number = null;

    // Statuts : DRAFT, SENT, ACKNOWLEDGED, REJECTED, PAID, CANCELLED
    #[ORM\Column(length: 20)]
    #[Groups(['invoice:read'])]
    private string $status = 'DRAFT';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['invoice:read'])]
    private Company $seller;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['invoice:read', 'invoice:write'])]
    private Client $buyer;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['invoice:read', 'invoice:write'])]
    private \DateTimeImmutable $issueDate;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeImmutable $deliveryDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 3)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $currency = 'EUR';

    // Totaux calcules (denormalises pour la performance)
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['invoice:read'])]
    private string $totalExcludingTax = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['invoice:read'])]
    private string $totalTax = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['invoice:read'])]
    private string $totalIncludingTax = '0.00';

    // Reference PDP (retournee par la PDP apres transmission)
    #[ORM\Column(nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdpReference = null;

    // Hash SHA-256 du fichier transmis (piste d'audit fiable)
    #[ORM\Column(nullable: true)]
    private ?string $fileHash = null;

    // Chemin S3 du fichier archive
    #[ORM\Column(nullable: true)]
    private ?string $archivedFilePath = null;

    // Mention legale specifique (autoliquidation, exoneration TVA, art. 293B...)
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $legalMention = null;

    // Conditions de paiement
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?string $paymentTerms = null;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['invoice:read', 'invoice:write'])]
    private Collection $lines;

    /** @var Collection<int, InvoiceEvent> */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceEvent::class, cascade: ['persist'])]
    #[ORM\OrderBy(['occurredAt' => 'ASC'])]
    private Collection $events;

    #[ORM\Column]
    #[Groups(['invoice:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->lines = new ArrayCollection();
        $this->events = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->issueDate = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(?string $number): static
    {
        $this->number = $number;

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

    public function getSeller(): Company
    {
        return $this->seller;
    }

    public function setSeller(Company $seller): static
    {
        $this->seller = $seller;

        return $this;
    }

    public function getBuyer(): Client
    {
        return $this->buyer;
    }

    public function setBuyer(Client $buyer): static
    {
        $this->buyer = $buyer;

        return $this;
    }

    public function getIssueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function setIssueDate(\DateTimeImmutable $issueDate): static
    {
        $this->issueDate = $issueDate;

        return $this;
    }

    public function getDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->deliveryDate;
    }

    public function setDeliveryDate(?\DateTimeImmutable $deliveryDate): static
    {
        $this->deliveryDate = $deliveryDate;

        return $this;
    }

    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

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

    public function getTotalExcludingTax(): string
    {
        return $this->totalExcludingTax;
    }

    public function setTotalExcludingTax(string $totalExcludingTax): static
    {
        $this->totalExcludingTax = $totalExcludingTax;

        return $this;
    }

    public function getTotalTax(): string
    {
        return $this->totalTax;
    }

    public function setTotalTax(string $totalTax): static
    {
        $this->totalTax = $totalTax;

        return $this;
    }

    public function getTotalIncludingTax(): string
    {
        return $this->totalIncludingTax;
    }

    public function setTotalIncludingTax(string $totalIncludingTax): static
    {
        $this->totalIncludingTax = $totalIncludingTax;

        return $this;
    }

    public function getPdpReference(): ?string
    {
        return $this->pdpReference;
    }

    public function setPdpReference(?string $pdpReference): static
    {
        $this->pdpReference = $pdpReference;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    public function getArchivedFilePath(): ?string
    {
        return $this->archivedFilePath;
    }

    public function setArchivedFilePath(?string $archivedFilePath): static
    {
        $this->archivedFilePath = $archivedFilePath;

        return $this;
    }

    public function getLegalMention(): ?string
    {
        return $this->legalMention;
    }

    public function setLegalMention(?string $legalMention): static
    {
        $this->legalMention = $legalMention;

        return $this;
    }

    public function getPaymentTerms(): ?string
    {
        return $this->paymentTerms;
    }

    public function setPaymentTerms(?string $paymentTerms): static
    {
        $this->paymentTerms = $paymentTerms;

        return $this;
    }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(InvoiceLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }

        return $this;
    }

    public function removeLine(InvoiceLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    /** @return Collection<int, InvoiceEvent> */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(InvoiceEvent $event): static
    {
        if (!$this->events->contains($event)) {
            $this->events->add($event);
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Recalcule les totaux a partir des lignes.
     * Utilise bcmath pour la precision comptable.
     */
    public function computeTotals(): void
    {
        $totalHt = '0.00';
        $totalTax = '0.00';

        foreach ($this->lines as $line) {
            $lineAmount = $line->getLineAmount();
            $vatAmount = $line->getVatAmount();
            \assert(is_numeric($lineAmount));
            \assert(is_numeric($vatAmount));
            $totalHt = bcadd($totalHt, $lineAmount, 2);
            $totalTax = bcadd($totalTax, $vatAmount, 2);
        }

        $this->totalExcludingTax = $totalHt;
        $this->totalTax = $totalTax;
        $this->totalIncludingTax = bcadd($totalHt, $totalTax, 2);
    }

    /**
     * Verifie que la facture contient les donnees minimales pour etre emise.
     */
    public function isValid(): bool
    {
        if ($this->lines->isEmpty()) {
            return false;
        }

        if ('0.00' === $this->totalIncludingTax) {
            return false;
        }

        return true;
    }
}
