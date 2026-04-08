<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Configuration par pays pour l'expansion europeenne.
 * Chaque pays a ses regles fiscales, son format de facture et son protocole de transmission.
 */
#[ORM\Entity]
#[ORM\Table(name: 'country_configs')]
class CountryConfig
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // Code ISO 3166-1 alpha-2 (FR, IT, DE, ES, PL, BE, NL)
    #[ORM\Column(length: 2, unique: true)]
    private string $countryCode;

    // Nom de l'autorite fiscale (DGFiP, Agenzia delle Entrate, etc.)
    #[ORM\Column(length: 100)]
    private string $taxAuthority;

    // Format de facture electronique (Factur-X, FatturaPA, XRechnung, FacturaE)
    #[ORM\Column(length: 50)]
    private string $invoiceFormat;

    // Protocole de transmission (Chorus Pro, SDI, Peppol, VERI*FACTU)
    #[ORM\Column(length: 50)]
    private string $transmissionProtocol;

    // Taux de TVA standard du pays
    #[ORM\Column(length: 10)]
    private string $standardVatRate;

    /** @var list<string> Taux de TVA reduits du pays */
    #[ORM\Column(type: 'json')]
    private array $reducedVatRates = [];

    // Format du numero d'identification fiscale (regex)
    #[ORM\Column(length: 100)]
    private string $taxIdFormat;

    // La facturation electronique est-elle obligatoire dans ce pays ?
    #[ORM\Column]
    private bool $eMandatory = false;

    // Date d'entree en vigueur de l'obligation
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $mandatoryDate = null;

    // Actif (le pays est supporte par la plateforme)
    #[ORM\Column]
    private bool $active = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function setCountryCode(string $countryCode): static
    {
        $this->countryCode = $countryCode;

        return $this;
    }

    public function getTaxAuthority(): string
    {
        return $this->taxAuthority;
    }

    public function setTaxAuthority(string $taxAuthority): static
    {
        $this->taxAuthority = $taxAuthority;

        return $this;
    }

    public function getInvoiceFormat(): string
    {
        return $this->invoiceFormat;
    }

    public function setInvoiceFormat(string $invoiceFormat): static
    {
        $this->invoiceFormat = $invoiceFormat;

        return $this;
    }

    public function getTransmissionProtocol(): string
    {
        return $this->transmissionProtocol;
    }

    public function setTransmissionProtocol(string $transmissionProtocol): static
    {
        $this->transmissionProtocol = $transmissionProtocol;

        return $this;
    }

    public function getStandardVatRate(): string
    {
        return $this->standardVatRate;
    }

    public function setStandardVatRate(string $standardVatRate): static
    {
        $this->standardVatRate = $standardVatRate;

        return $this;
    }

    /** @return list<string> */
    public function getReducedVatRates(): array
    {
        return $this->reducedVatRates;
    }

    /** @param list<string> $reducedVatRates */
    public function setReducedVatRates(array $reducedVatRates): static
    {
        $this->reducedVatRates = $reducedVatRates;

        return $this;
    }

    public function getTaxIdFormat(): string
    {
        return $this->taxIdFormat;
    }

    public function setTaxIdFormat(string $taxIdFormat): static
    {
        $this->taxIdFormat = $taxIdFormat;

        return $this;
    }

    public function isEMandatory(): bool
    {
        return $this->eMandatory;
    }

    public function setEMandatory(bool $eMandatory): static
    {
        $this->eMandatory = $eMandatory;

        return $this;
    }

    public function getMandatoryDate(): ?\DateTimeImmutable
    {
        return $this->mandatoryDate;
    }

    public function setMandatoryDate(?\DateTimeImmutable $mandatoryDate): static
    {
        $this->mandatoryDate = $mandatoryDate;

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
}
