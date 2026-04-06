<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Client OAuth 2.1 (Claude, ChatGPT, etc.).
 * Chaque integration externe est un client OAuth pre-enregistre.
 */
#[ORM\Entity]
#[ORM\Table(name: 'oauth_clients')]
class OAuthClient
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // Identifiant public du client (ex: "claude_connector")
    #[ORM\Column(length: 100, unique: true)]
    private string $clientId;

    // Secret du client (null pour les clients publics PKCE)
    #[ORM\Column(nullable: true)]
    private ?string $clientSecret = null;

    // Nom affiche sur la page de consentement
    #[ORM\Column(length: 100)]
    private string $name;

    /** @var list<string> URIs de redirection autorisees */
    #[ORM\Column(type: 'json')]
    private array $redirectUris = [];

    /** @var list<string> Scopes autorises pour ce client */
    #[ORM\Column(type: 'json')]
    private array $allowedScopes = [];

    // Client public = PKCE obligatoire, pas de secret
    #[ORM\Column]
    private bool $isPublic = true;

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

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): static
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function getClientSecret(): ?string
    {
        return $this->clientSecret;
    }

    public function setClientSecret(?string $clientSecret): static
    {
        $this->clientSecret = $clientSecret;

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

    /** @return list<string> */
    public function getRedirectUris(): array
    {
        return $this->redirectUris;
    }

    /** @param list<string> $redirectUris */
    public function setRedirectUris(array $redirectUris): static
    {
        $this->redirectUris = $redirectUris;

        return $this;
    }

    /** @return list<string> */
    public function getAllowedScopes(): array
    {
        return $this->allowedScopes;
    }

    /** @param list<string> $allowedScopes */
    public function setAllowedScopes(array $allowedScopes): static
    {
        $this->allowedScopes = $allowedScopes;

        return $this;
    }

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): static
    {
        $this->isPublic = $isPublic;

        return $this;
    }

    /**
     * Verifie qu'une URI de redirection est autorisee pour ce client.
     */
    public function isRedirectUriAllowed(string $uri): bool
    {
        foreach ($this->redirectUris as $allowedUri) {
            // Support des wildcards simples (ex: https://chatgpt.com/aip/*/oauth/callback)
            $pattern = str_replace('*', '[^/]+', preg_quote($allowedUri, '/'));
            if (preg_match('/^' . $pattern . '$/', $uri)) {
                return true;
            }
        }

        return false;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
