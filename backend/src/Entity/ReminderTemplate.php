<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use App\State\CompanyOwnerProcessor;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Template d'email de relance, configurable par entreprise.
 *
 * Variables disponibles dans subject et body :
 * {client} = nom du client, {montant} = montant TTC,
 * {echeance} = date d'echeance, {numero} = numero de facture,
 * {entreprise} = nom de l'entreprise emettrice.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reminder_templates')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
        new GetCollection(),
        new Post(security: "is_granted('ROLE_USER')", processor: CompanyOwnerProcessor::class),
        new Put(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
        new Delete(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['reminder_template:read']],
    denormalizationContext: ['groups' => ['reminder_template:write']],
)]
class ReminderTemplate
{
    // Types de relance
    public const TYPE_BEFORE_DUE = 'BEFORE_DUE';
    public const TYPE_FIRST_REMINDER = 'FIRST_REMINDER';
    public const TYPE_SECOND_REMINDER = 'SECOND_REMINDER';
    public const TYPE_FORMAL_NOTICE = 'FORMAL_NOTICE';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['reminder_template:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    // Type de relance : BEFORE_DUE, FIRST_REMINDER, SECOND_REMINDER, FORMAL_NOTICE
    #[ORM\Column(length: 30)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: [self::TYPE_BEFORE_DUE, self::TYPE_FIRST_REMINDER, self::TYPE_SECOND_REMINDER, self::TYPE_FORMAL_NOTICE])]
    #[Groups(['reminder_template:read', 'reminder_template:write'])]
    private string $type;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Groups(['reminder_template:read', 'reminder_template:write'])]
    private string $subject;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['reminder_template:read', 'reminder_template:write'])]
    private string $body;

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

    public function getCompany(): Company
    {
        return $this->company;
    }

    public function setCompany(Company $company): static
    {
        $this->company = $company;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): static
    {
        $this->subject = $subject;

        return $this;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setBody(string $body): static
    {
        $this->body = $body;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Remplace les variables dans l'objet et le corps du template.
     *
     * @param array<string, string> $variables Tableau cle => valeur des variables
     */
    public function render(array $variables): string
    {
        $body = $this->body;
        foreach ($variables as $key => $value) {
            $body = str_replace('{' . $key . '}', $value, $body);
        }

        return $body;
    }

    /**
     * Remplace les variables dans l'objet du template.
     *
     * @param array<string, string> $variables Tableau cle => valeur des variables
     */
    public function renderSubject(array $variables): string
    {
        $subject = $this->subject;
        foreach ($variables as $key => $value) {
            $subject = str_replace('{' . $key . '}', $value, $subject);
        }

        return $subject;
    }
}
