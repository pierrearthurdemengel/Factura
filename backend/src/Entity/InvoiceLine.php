<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'invoice_lines')]
class InvoiceLine
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['invoice:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    #[ORM\Column(type: 'smallint')]
    #[Groups(['invoice:read', 'invoice:write'])]
    private int $position = 1;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $description;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 4)]
    #[Assert\Positive]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $quantity;

    // EA = chaque, HUR = heure, DAY = jour
    #[ORM\Column(length: 10)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $unit = 'EA';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 4)]
    #[Assert\NotNull]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $unitPriceExcludingTax;

    // Taux TVA en pourcentage : 0, 5.5, 10, 20
    #[ORM\Column(length: 5)]
    #[Groups(['invoice:read', 'invoice:write'])]
    private string $vatRate = '20';

    // quantity * unitPrice HT
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['invoice:read'])]
    private string $lineAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    #[Groups(['invoice:read'])]
    private string $vatAmount = '0.00';

    public function __construct()
    {
        $this->id = Uuid::v7();
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getQuantity(): string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnit(): string
    {
        return $this->unit;
    }

    public function setUnit(string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }

    public function getUnitPriceExcludingTax(): string
    {
        return $this->unitPriceExcludingTax;
    }

    public function setUnitPriceExcludingTax(string $unitPriceExcludingTax): static
    {
        $this->unitPriceExcludingTax = $unitPriceExcludingTax;

        return $this;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function setVatRate(string $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function getLineAmount(): string
    {
        return $this->lineAmount;
    }

    public function getVatAmount(): string
    {
        return $this->vatAmount;
    }

    /**
     * Calcule le montant HT de la ligne et le montant de TVA.
     * Utilise bcmath pour la precision comptable.
     */
    public function computeAmounts(): void
    {
        // Montant HT = quantite * prix unitaire HT
        \assert(is_numeric($this->quantity));
        \assert(is_numeric($this->unitPriceExcludingTax));
        $this->lineAmount = bcmul($this->quantity, $this->unitPriceExcludingTax, 2);

        // Montant TVA = montant HT * taux TVA / 100
        if (is_numeric($this->vatRate)) {
            $vatDecimal = bcdiv($this->vatRate, '100', 6);
            $this->vatAmount = bcmul($this->lineAmount, $vatDecimal, 2);
        } else {
            // Autoliquidation (AE), exonere (E), zero (Z) : pas de TVA
            $this->vatAmount = '0.00';
        }
    }
}
