<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Point de terminaison webhook configure par un utilisateur.
 *
 * Permet de recevoir des notifications en temps reel
 * pour les evenements de la plateforme (factures, devis, paiements).
 * La signature HMAC-SHA256 garantit l'authenticite des payloads.
 */
#[ORM\Entity]
#[ORM\Table(name: 'webhook_endpoints')]
class WebhookEndpoint
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    #[ORM\Column(length: 500)]
    private string $url;

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    private array $events = [];

    // Secret utilise pour la signature HMAC-SHA256
    #[ORM\Column(length: 64)]
    private string $secret;

    #[ORM\Column]
    private bool $active = true;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $description = null;

    /** @var Collection<int, WebhookDelivery> */
    #[ORM\OneToMany(mappedBy: 'endpoint', targetEntity: WebhookDelivery::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $deliveries;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->deliveries = new ArrayCollection();
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getEvents(): array
    {
        return $this->events;
    }

    /**
     * @param list<string> $events
     */
    public function setEvents(array $events): static
    {
        $this->events = $events;

        return $this;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, WebhookDelivery>
     */
    public function getDeliveries(): Collection
    {
        return $this->deliveries;
    }

    /**
     * Verifie si cet endpoint est abonne a un evenement donne.
     */
    public function isSubscribedTo(string $event): bool
    {
        return \in_array($event, $this->events, true);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
