<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['email'], message: 'Un compte existe deja avec cet email.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column]
    private string $password;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $firstName;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    private string $lastName;

    /** @var Collection<int, Company> */
    #[ORM\OneToMany(mappedBy: 'owner', targetEntity: Company::class, cascade: ['persist', 'remove'])]
    private Collection $companies;

    // Entreprise active (selectionnee par l'utilisateur)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $activeCompany = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->companies = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /** @return non-empty-string */
    public function getUserIdentifier(): string
    {
        \assert('' !== $this->email);

        return $this->email;
    }

    /** @return list<string> */
    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';

        return array_values(array_unique($roles));
    }

    /** @param list<string> $roles */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function eraseCredentials(): void
    {
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    /**
     * Retourne l'entreprise active de l'utilisateur.
     *
     * Cette methode sert de point d'acces unique pour tous les services
     * qui ont besoin de l'entreprise courante. En mode multi-entite,
     * elle retourne l'entreprise explicitement selectionnee, sinon la premiere.
     */
    public function getCompany(): ?Company
    {
        if (null !== $this->activeCompany) {
            return $this->activeCompany;
        }

        // Fallback : premiere entreprise si aucune active selectionnee
        $first = $this->companies->first();

        return false !== $first ? $first : null;
    }

    public function getActiveCompany(): ?Company
    {
        return $this->activeCompany;
    }

    public function setActiveCompany(?Company $activeCompany): static
    {
        $this->activeCompany = $activeCompany;

        return $this;
    }

    /** @return Collection<int, Company> */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    /**
     * Ajoute une entreprise a l'utilisateur et la definit comme active
     * si c'est la premiere.
     */
    public function addCompany(Company $company): static
    {
        if (!$this->companies->contains($company)) {
            $this->companies->add($company);
            $company->setOwner($this);

            // Premiere entreprise : la definir comme active automatiquement
            if (1 === $this->companies->count()) {
                $this->activeCompany = $company;
            }
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
