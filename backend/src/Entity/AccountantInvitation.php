<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Invitation envoyee par un comptable a un client.
 *
 * Le token permet au client d'accepter l'invitation
 * et de lier son entreprise au cabinet comptable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'accountant_invitations')]
#[ORM\Index(columns: ['token'], name: 'idx_invitation_token')]
class AccountantInvitation
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_ACCEPTED = 'ACCEPTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_EXPIRED = 'EXPIRED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne(inversedBy: 'invitations')]
    #[ORM\JoinColumn(nullable: false)]
    private AccountantProfile $accountantProfile;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // Entreprise liee si le client existe deja
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Company $company = null;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->token = bin2hex(random_bytes(32));
        $this->expiresAt = new \DateTimeImmutable('+30 days');
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getAccountantProfile(): AccountantProfile
    {
        return $this->accountantProfile;
    }

    public function setAccountantProfile(AccountantProfile $accountantProfile): static
    {
        $this->accountantProfile = $accountantProfile;

        return $this;
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

    public function getToken(): string
    {
        return $this->token;
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

    public function getCompany(): ?Company
    {
        return $this->company;
    }

    public function setCompany(?Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Verifie si l'invitation est expiree.
     */
    public function isExpired(): bool
    {
        return new \DateTimeImmutable() > $this->expiresAt;
    }

    /**
     * Verifie si l'invitation peut etre acceptee.
     */
    public function isAcceptable(): bool
    {
        return self::STATUS_PENDING === $this->status && !$this->isExpired();
    }

    /**
     * Accepte l'invitation et lie l'entreprise au cabinet.
     */
    public function accept(Company $company): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->company = $company;
        $this->acceptedAt = new \DateTimeImmutable();
        $this->accountantProfile->addCompany($company);
    }
}
