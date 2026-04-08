<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Programme de parrainage.
 * Chaque utilisateur a un code unique. Quand un filleul s'inscrit,
 * les deux parties recoivent 1 mois Pro gratuit.
 */
#[ORM\Entity]
#[ORM\Table(name: 'referrals')]
#[ORM\Index(columns: ['referrer_id'], name: 'idx_referral_referrer')]
#[ORM\Index(columns: ['code'], name: 'idx_referral_code')]
class Referral
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REWARDED = 'rewarded';

    // Recompense : 1 mois Pro gratuit pour les deux parties
    public const REWARD_MONTHS = 1;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // Utilisateur qui parraine
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $referrer;

    // Code de parrainage unique (ex: "MFP-A1B2C3")
    #[ORM\Column(length: 20, unique: true)]
    private string $code;

    // Utilisateur parraine (null tant que non inscrit)
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $referee = null;

    // Email du filleul invite
    #[ORM\Column(length: 180, nullable: true)]
    private ?string $refereeEmail = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_PENDING;

    // Date a laquelle le filleul a termine son inscription
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    // Date a laquelle la recompense a ete attribuee
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $rewardedAt = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getReferrer(): User
    {
        return $this->referrer;
    }

    public function setReferrer(User $referrer): static
    {
        $this->referrer = $referrer;

        return $this;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getReferee(): ?User
    {
        return $this->referee;
    }

    public function setReferee(?User $referee): static
    {
        $this->referee = $referee;

        return $this;
    }

    public function getRefereeEmail(): ?string
    {
        return $this->refereeEmail;
    }

    public function setRefereeEmail(?string $refereeEmail): static
    {
        $this->refereeEmail = $refereeEmail;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Marque le parrainage comme complete quand le filleul s'inscrit.
     */
    public function complete(User $referee): static
    {
        $this->referee = $referee;
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTimeImmutable();

        return $this;
    }

    /**
     * Marque la recompense comme attribuee.
     */
    public function reward(): static
    {
        $this->status = self::STATUS_REWARDED;
        $this->rewardedAt = new \DateTimeImmutable();

        return $this;
    }

    public function isCompleted(): bool
    {
        return self::STATUS_COMPLETED === $this->status || self::STATUS_REWARDED === $this->status;
    }

    public function isRewarded(): bool
    {
        return self::STATUS_REWARDED === $this->status;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function getRewardedAt(): ?\DateTimeImmutable
    {
        return $this->rewardedAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Genere un code de parrainage aleatoire.
     */
    public static function generateCode(): string
    {
        return 'MFP-' . strtoupper(bin2hex(random_bytes(3)));
    }
}
