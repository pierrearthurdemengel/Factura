<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Connexion bancaire Open Banking (Yapily, Bridge, etc.).
 *
 * Stocke les tokens d'acces, le provider utilise et le statut de la connexion.
 * Chaque connexion peut avoir plusieurs comptes bancaires associes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'bank_connections')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
        new GetCollection(),
        new Delete(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['bank_connection:read']],
)]
class BankConnection
{
    // Statuts de la connexion
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_SUSPENDED = 'SUSPENDED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['bank_connection:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    // Nom du provider Open Banking utilise (ex: 'yapily', 'bridge')
    #[ORM\Column(length: 50, options: ['default' => 'yapily'])]
    #[Groups(['bank_connection:read'])]
    private string $providerName = 'yapily';

    // Identifiant de la banque chez le provider (ex: SANDBOXFINANCE_SFIN0000)
    #[ORM\Column(length: 100)]
    #[Groups(['bank_connection:read'])]
    private string $bankId;

    // Nom de la banque pour affichage
    #[ORM\Column(length: 255)]
    #[Groups(['bank_connection:read'])]
    private string $bankName;

    // Identifiant de l'autorisation PSD2 chez le provider
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $requisitionId = null;

    // Token d'acces du provider (chiffre en BDD en production)
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $accessToken = null;

    // Token de rafraichissement du provider
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(length: 20)]
    #[Groups(['bank_connection:read'])]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column(nullable: true)]
    #[Groups(['bank_connection:read'])]
    private ?\DateTimeImmutable $lastSyncAt = null;

    /** @var Collection<int, BankAccount> */
    #[ORM\OneToMany(mappedBy: 'bankConnection', targetEntity: BankAccount::class, cascade: ['persist', 'remove'])]
    #[Groups(['bank_connection:read'])]
    private Collection $accounts;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->accounts = new ArrayCollection();
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

    public function getProviderName(): string
    {
        return $this->providerName;
    }

    public function setProviderName(string $providerName): static
    {
        $this->providerName = $providerName;

        return $this;
    }

    public function getBankId(): string
    {
        return $this->bankId;
    }

    public function setBankId(string $bankId): static
    {
        $this->bankId = $bankId;

        return $this;
    }

    public function getBankName(): string
    {
        return $this->bankName;
    }

    public function setBankName(string $bankName): static
    {
        $this->bankName = $bankName;

        return $this;
    }

    public function getRequisitionId(): ?string
    {
        return $this->requisitionId;
    }

    public function setRequisitionId(?string $requisitionId): static
    {
        $this->requisitionId = $requisitionId;

        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;

        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;

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

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): static
    {
        $this->lastSyncAt = $lastSyncAt;

        return $this;
    }

    /** @return Collection<int, BankAccount> */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
