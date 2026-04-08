<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Profil d'un expert-comptable gerant plusieurs clients.
 *
 * Un comptable peut acceder aux donnees de toutes les entreprises
 * liees a son profil, avec des permissions etendues.
 */
#[ORM\Entity]
#[ORM\Table(name: 'accountant_profiles')]
class AccountantProfile
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $firmName;

    #[ORM\Column(length: 9, nullable: true)]
    private ?string $firmSiren = null;

    #[ORM\Column(nullable: true)]
    private ?string $logoPath = null;

    // Personnalisation white-label
    #[ORM\Column(length: 7, nullable: true)]
    private ?string $primaryColor = null;

    #[ORM\Column(nullable: true)]
    private ?string $customDomain = null;

    /** @var Collection<int, Company> */
    #[ORM\ManyToMany(targetEntity: Company::class)]
    #[ORM\JoinTable(name: 'accountant_companies')]
    private Collection $companies;

    /** @var Collection<int, AccountantInvitation> */
    #[ORM\OneToMany(mappedBy: 'accountantProfile', targetEntity: AccountantInvitation::class, cascade: ['persist', 'remove'])]
    private Collection $invitations;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->companies = new ArrayCollection();
        $this->invitations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getFirmName(): string
    {
        return $this->firmName;
    }

    public function setFirmName(string $firmName): static
    {
        $this->firmName = $firmName;

        return $this;
    }

    public function getFirmSiren(): ?string
    {
        return $this->firmSiren;
    }

    public function setFirmSiren(?string $firmSiren): static
    {
        $this->firmSiren = $firmSiren;

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

    public function getCustomDomain(): ?string
    {
        return $this->customDomain;
    }

    public function setCustomDomain(?string $customDomain): static
    {
        $this->customDomain = $customDomain;

        return $this;
    }

    /** @return Collection<int, Company> */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function addCompany(Company $company): static
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
        }

        return $this;
    }

    public function removeCompany(Company $company): static
    {
        $this->companies->removeElement($company);

        return $this;
    }

    /**
     * Verifie si le comptable gere cette entreprise.
     */
    public function hasCompany(Company $company): bool
    {
        return $this->companies->contains($company);
    }

    /** @return Collection<int, AccountantInvitation> */
    public function getInvitations(): Collection
    {
        return $this->invitations;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Nombre de clients actifs du cabinet.
     */
    public function getClientCount(): int
    {
        return $this->companies->count();
    }
}
