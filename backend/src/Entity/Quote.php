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
use App\State\CompanyOwnerProcessor;
use App\State\QuoteConvertProcessor;
use App\State\QuoteSendProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'quotes')]
#[ORM\Index(columns: ['status', 'issue_date'], name: 'idx_quote_status_date')]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('VIEW', object)"),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER')", processor: CompanyOwnerProcessor::class),
        new Put(security: "is_granted('EDIT', object)"),
        new Delete(security: "is_granted('DELETE', object)"),
        new Post(
            uriTemplate: '/quotes/{id}/send',
            security: "is_granted('SEND', object)",
            processor: QuoteSendProcessor::class,
            denormalizationContext: ['groups' => []],
            name: 'quote_send',
        ),
        new Post(
            uriTemplate: '/quotes/{id}/convert',
            security: "is_granted('CONVERT', object)",
            processor: QuoteConvertProcessor::class,
            denormalizationContext: ['groups' => []],
            normalizationContext: ['groups' => ['invoice:read']],
            output: Invoice::class,
            name: 'quote_convert',
        ),
    ],
    normalizationContext: ['groups' => ['quote:read']],
    denormalizationContext: ['groups' => ['quote:write']],
    order: ['issueDate' => 'DESC'],
)]
#[ApiFilter(SearchFilter::class, properties: [
    'number' => 'partial',
    'buyer.name' => 'partial',
    'status' => 'exact',
])]
#[ApiFilter(DateFilter::class, properties: ['issueDate', 'validityEndDate'])]
#[ApiFilter(OrderFilter::class, properties: ['issueDate', 'totalIncludingTax', 'status'])]
#[ApiFilter(RangeFilter::class, properties: ['totalIncludingTax'])]
class Quote
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['quote:read'])]
    private ?Uuid $id = null;

    // Numero sequentiel obligatoire (ex: DV-2026-0001)
    #[ORM\Column(length: 50, unique: true, nullable: true)]
    #[Groups(['quote:read'])]
    private ?string $number = null;

    // Statuts : DRAFT, SENT, ACCEPTED, REJECTED, EXPIRED, CONVERTED
    #[ORM\Column(length: 20)]
    #[Groups(['quote:read'])]
    private string $status = 'DRAFT';

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['quote:read'])]
    private ?Company $seller = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['quote:read', 'quote:write'])]
    private Client $buyer;

    #[ORM\Column(type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['quote:read', 'quote:write'])]
    private \DateTimeImmutable $issueDate;

    // Date de fin de validite du devis
    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?\DateTimeImmutable $validityEndDate = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['quote:read'])]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'date_immutable', nullable: true)]
    #[Groups(['quote:read'])]
    private ?\DateTimeImmutable $rejectedAt = null;

    // Facture generee lors de la conversion devis → facture
    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['quote:read'])]
    private ?Invoice $convertedInvoice = null;

    #[ORM\Column(length: 3)]
    #[Groups(['quote:read', 'quote:write'])]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['quote:read'])]
    private string $totalExcludingTax = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['quote:read'])]
    private string $totalTax = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['quote:read'])]
    private string $totalIncludingTax = '0.00';

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $legalMention = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['quote:read', 'quote:write'])]
    private ?string $notes = null;

    /** @var Collection<int, QuoteLine> */
    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteLine::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Groups(['quote:read', 'quote:write'])]
    private Collection $lines;

    /** @var Collection<int, QuoteEvent> */
    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteEvent::class, cascade: ['persist'])]
    #[ORM\OrderBy(['occurredAt' => 'ASC'])]
    private Collection $events;

    #[ORM\Column]
    #[Groups(['quote:read'])]
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

    public function getSeller(): ?Company
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

    public function getValidityEndDate(): ?\DateTimeImmutable
    {
        return $this->validityEndDate;
    }

    public function setValidityEndDate(?\DateTimeImmutable $validityEndDate): static
    {
        $this->validityEndDate = $validityEndDate;

        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): static
    {
        $this->acceptedAt = $acceptedAt;

        return $this;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): static
    {
        $this->rejectedAt = $rejectedAt;

        return $this;
    }

    public function getConvertedInvoice(): ?Invoice
    {
        return $this->convertedInvoice;
    }

    public function setConvertedInvoice(?Invoice $convertedInvoice): static
    {
        $this->convertedInvoice = $convertedInvoice;

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

    public function getLegalMention(): ?string
    {
        return $this->legalMention;
    }

    public function setLegalMention(?string $legalMention): static
    {
        $this->legalMention = $legalMention;

        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;

        return $this;
    }

    /** @return Collection<int, QuoteLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(QuoteLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setQuote($this);
        }

        return $this;
    }

    public function removeLine(QuoteLine $line): static
    {
        $this->lines->removeElement($line);

        return $this;
    }

    /** @return Collection<int, QuoteEvent> */
    public function getEvents(): Collection
    {
        return $this->events;
    }

    public function addEvent(QuoteEvent $event): static
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
     * Verifie que le devis contient les donnees minimales pour etre envoye.
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
