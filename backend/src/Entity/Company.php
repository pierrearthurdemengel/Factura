<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'companies')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getOwner() == user"),
        new Put(security: "is_granted('ROLE_USER') and object.getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['company:read']],
    denormalizationContext: ['groups' => ['company:write']],
)]
class Company
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['company:read', 'invoice:read'])]
    private ?Uuid $id = null;

    #[ORM\OneToOne(inversedBy: 'company')]
    #[ORM\JoinColumn(nullable: false)]
    private User $owner;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private string $name;

    #[ORM\Column(length: 9)]
    #[Assert\NotBlank]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private string $siren;

    #[ORM\Column(length: 14, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $siret = null;

    #[ORM\Column(length: 30, nullable: true)]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private ?string $vatNumber = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private string $legalForm;

    #[ORM\Column(length: 5, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $nafCode = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private string $addressLine1;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $addressLine2 = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private string $postalCode;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Groups(['company:read', 'company:write', 'invoice:read'])]
    private string $city;

    #[ORM\Column(length: 2)]
    #[Groups(['company:read', 'company:write'])]
    private string $countryCode = 'FR';

    #[ORM\Column(length: 34, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $iban = null;

    #[ORM\Column(length: 11, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $bic = null;

    // PDP par defaut pour cette entreprise
    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $defaultPdp = null;

    /** @var Collection<int, Client> */
    #[ORM\OneToMany(mappedBy: 'company', targetEntity: Client::class, cascade: ['persist', 'remove'])]
    private Collection $clients;

    // Personnalisation PDF
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $logoPath = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $primaryColor = null;

    #[ORM\Column(length: 7, nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $secondaryColor = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['company:read', 'company:write'])]
    private ?string $customFooter = null;

    // Compteur de sequence pour la numerotation des factures
    #[ORM\Column(nullable: true)]
    private ?int $lastInvoiceNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastInvoiceYear = null;

    // Compteur de sequence pour la numerotation des devis
    #[ORM\Column(nullable: true)]
    private ?int $lastQuoteNumber = null;

    #[ORM\Column(nullable: true)]
    private ?int $lastQuoteYear = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->clients = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getOwner(): User
    {
        return $this->owner;
    }

    public function setOwner(User $owner): static
    {
        $this->owner = $owner;

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

    public function getSiren(): string
    {
        return $this->siren;
    }

    public function setSiren(string $siren): static
    {
        $this->siren = $siren;

        return $this;
    }

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): static
    {
        $this->siret = $siret;

        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): static
    {
        $this->vatNumber = $vatNumber;

        return $this;
    }

    public function getLegalForm(): string
    {
        return $this->legalForm;
    }

    public function setLegalForm(string $legalForm): static
    {
        $this->legalForm = $legalForm;

        return $this;
    }

    public function getNafCode(): ?string
    {
        return $this->nafCode;
    }

    public function setNafCode(?string $nafCode): static
    {
        $this->nafCode = $nafCode;

        return $this;
    }

    public function getAddressLine1(): string
    {
        return $this->addressLine1;
    }

    public function setAddressLine1(string $addressLine1): static
    {
        $this->addressLine1 = $addressLine1;

        return $this;
    }

    public function getAddressLine2(): ?string
    {
        return $this->addressLine2;
    }

    public function setAddressLine2(?string $addressLine2): static
    {
        $this->addressLine2 = $addressLine2;

        return $this;
    }

    public function getPostalCode(): string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): static
    {
        $this->postalCode = $postalCode;

        return $this;
    }

    public function getCity(): string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;

        return $this;
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

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(?string $iban): static
    {
        $this->iban = $iban;

        return $this;
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(?string $bic): static
    {
        $this->bic = $bic;

        return $this;
    }

    public function getDefaultPdp(): ?string
    {
        return $this->defaultPdp;
    }

    public function setDefaultPdp(?string $defaultPdp): static
    {
        $this->defaultPdp = $defaultPdp;

        return $this;
    }

    /** @return Collection<int, Client> */
    public function getClients(): Collection
    {
        return $this->clients;
    }

    public function getLastInvoiceNumber(): ?int
    {
        return $this->lastInvoiceNumber;
    }

    public function setLastInvoiceNumber(?int $lastInvoiceNumber): static
    {
        $this->lastInvoiceNumber = $lastInvoiceNumber;

        return $this;
    }

    public function getLastInvoiceYear(): ?int
    {
        return $this->lastInvoiceYear;
    }

    public function setLastInvoiceYear(?int $lastInvoiceYear): static
    {
        $this->lastInvoiceYear = $lastInvoiceYear;

        return $this;
    }

    public function getLogoPath(): ?string
    {
        return $this->logoPath;
    }

    public function setLogoPath(?string $logoPath): static
    {
        $this->logoPath = $logoPath;

        return $this;
    }

    public function getPrimaryColor(): ?string
    {
        return $this->primaryColor;
    }

    public function setPrimaryColor(?string $primaryColor): static
    {
        $this->primaryColor = $primaryColor;

        return $this;
    }

    public function getSecondaryColor(): ?string
    {
        return $this->secondaryColor;
    }

    public function setSecondaryColor(?string $secondaryColor): static
    {
        $this->secondaryColor = $secondaryColor;

        return $this;
    }

    public function getCustomFooter(): ?string
    {
        return $this->customFooter;
    }

    public function setCustomFooter(?string $customFooter): static
    {
        $this->customFooter = $customFooter;

        return $this;
    }

    public function getLastQuoteNumber(): ?int
    {
        return $this->lastQuoteNumber;
    }

    public function setLastQuoteNumber(?int $lastQuoteNumber): static
    {
        $this->lastQuoteNumber = $lastQuoteNumber;

        return $this;
    }

    public function getLastQuoteYear(): ?int
    {
        return $this->lastQuoteYear;
    }

    public function setLastQuoteYear(?int $lastQuoteYear): static
    {
        $this->lastQuoteYear = $lastQuoteYear;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
