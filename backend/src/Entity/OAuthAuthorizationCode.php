<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Code d'autorisation temporaire OAuth 2.1.
 * Echange contre un access token apres le consentement utilisateur.
 * Duree de vie courte (5 minutes).
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_authorization_codes')]
class OAuthAuthorizationCode
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\Column(length: 256, unique: true)]
    private string $code;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private OAuthClient $client;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $scopes = [];

    #[ORM\Column(length: 2048)]
    private string $redirectUri;

    #[ORM\Column]
    private \DateTimeImmutable $expiresAt;

    // PKCE : code_challenge stocke pour la verification a l'echange
    #[ORM\Column(length: 256, nullable: true)]
    private ?string $codeChallenge = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $codeChallengeMethod = null;

    #[ORM\Column]
    private bool $used = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        // Code d'autorisation valide 5 minutes
        $this->expiresAt = new \DateTimeImmutable('+5 minutes');
    }

    public function getId(): ?Uuid
    {
        return $this->id;
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

    public function getClient(): OAuthClient
    {
        return $this->client;
    }

    public function setClient(OAuthClient $client): static
    {
        $this->client = $client;

        return $this;
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

    /** @return list<string> */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /** @param list<string> $scopes */
    public function setScopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    public function getRedirectUri(): string
    {
        return $this->redirectUri;
    }

    public function setRedirectUri(string $redirectUri): static
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function getCodeChallenge(): ?string
    {
        return $this->codeChallenge;
    }

    public function setCodeChallenge(?string $codeChallenge): static
    {
        $this->codeChallenge = $codeChallenge;

        return $this;
    }

    public function getCodeChallengeMethod(): ?string
    {
        return $this->codeChallengeMethod;
    }

    public function setCodeChallengeMethod(?string $codeChallengeMethod): static
    {
        $this->codeChallengeMethod = $codeChallengeMethod;

        return $this;
    }

    public function isUsed(): bool
    {
        return $this->used;
    }

    public function markUsed(): static
    {
        $this->used = true;

        return $this;
    }

    /**
     * Verifie le code_verifier PKCE contre le code_challenge stocke.
     */
    public function verifyCodeChallenge(string $codeVerifier): bool
    {
        if (null === $this->codeChallenge) {
            return true;
        }

        if ('S256' === $this->codeChallengeMethod) {
            $computed = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

            return hash_equals($this->codeChallenge, $computed);
        }

        // Methode "plain" (non recommandee mais supportee)
        return hash_equals($this->codeChallenge, $codeVerifier);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
