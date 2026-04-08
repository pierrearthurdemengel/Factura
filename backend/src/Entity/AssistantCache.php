<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Cache des reponses de l'assistant comptable.
 *
 * Les questions fiscales de base (~60% du volume) ne changent qu'une fois par an.
 * Le cache stocke la reponse structuree indexee par le hash de la question normalisee.
 * TTL par defaut : 30 jours.
 */
#[ORM\Entity]
#[ORM\Table(name: 'assistant_cache')]
#[ORM\Index(columns: ['question_hash'], name: 'idx_assistant_cache_hash')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_assistant_cache_expires')]
class AssistantCache
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private string $questionHash;

    #[ORM\Column(type: 'text')]
    private string $normalizedQuestion;

    /** @var array<string, mixed> */
    #[ORM\Column(type: 'json')]
    private array $response;

    #[ORM\Column(length: 50)]
    private string $category;

    #[ORM\Column]
    private int $hitCount = 0;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        // TTL par defaut : 30 jours
        $this->expiresAt = new \DateTimeImmutable('+30 days');
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getQuestionHash(): string
    {
        return $this->questionHash;
    }

    public function setQuestionHash(string $questionHash): static
    {
        $this->questionHash = $questionHash;

        return $this;
    }

    public function getNormalizedQuestion(): string
    {
        return $this->normalizedQuestion;
    }

    public function setNormalizedQuestion(string $normalizedQuestion): static
    {
        $this->normalizedQuestion = $normalizedQuestion;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * @param array<string, mixed> $response
     */
    public function setResponse(array $response): static
    {
        $this->response = $response;

        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;

        return $this;
    }

    public function getHitCount(): int
    {
        return $this->hitCount;
    }

    public function incrementHitCount(): static
    {
        ++$this->hitCount;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }
}
