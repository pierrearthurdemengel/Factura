<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Put;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Configuration des relances automatiques par entreprise.
 *
 * Definit les delais de relance (en jours par rapport a l'echeance)
 * et l'activation/desactivation du systeme de relances.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reminder_configs')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
        new Put(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['reminder_config:read']],
    denormalizationContext: ['groups' => ['reminder_config:write']],
)]
class ReminderConfig
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['reminder_config:read'])]
    private ?Uuid $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    // Activation des relances automatiques
    #[ORM\Column]
    #[Groups(['reminder_config:read', 'reminder_config:write'])]
    private bool $enabled = true;

    // Rappel avant echeance (jours negatifs = avant, ex: -3 = J-3)
    #[ORM\Column]
    #[Groups(['reminder_config:read', 'reminder_config:write'])]
    private int $daysBefore = 3;

    // Premiere relance apres echeance (J+1)
    #[ORM\Column]
    #[Groups(['reminder_config:read', 'reminder_config:write'])]
    private int $daysFirstReminder = 1;

    // Deuxieme relance (J+7)
    #[ORM\Column]
    #[Groups(['reminder_config:read', 'reminder_config:write'])]
    private int $daysSecondReminder = 7;

    // Troisieme relance / mise en demeure (J+30)
    #[ORM\Column]
    #[Groups(['reminder_config:read', 'reminder_config:write'])]
    private int $daysFormalNotice = 30;

    // Activer la generation automatique de mise en demeure PDF
    #[ORM\Column]
    #[Groups(['reminder_config:read', 'reminder_config:write'])]
    private bool $formalNoticeEnabled = true;

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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function getDaysBefore(): int
    {
        return $this->daysBefore;
    }

    public function setDaysBefore(int $daysBefore): static
    {
        $this->daysBefore = $daysBefore;

        return $this;
    }

    public function getDaysFirstReminder(): int
    {
        return $this->daysFirstReminder;
    }

    public function setDaysFirstReminder(int $daysFirstReminder): static
    {
        $this->daysFirstReminder = $daysFirstReminder;

        return $this;
    }

    public function getDaysSecondReminder(): int
    {
        return $this->daysSecondReminder;
    }

    public function setDaysSecondReminder(int $daysSecondReminder): static
    {
        $this->daysSecondReminder = $daysSecondReminder;

        return $this;
    }

    public function getDaysFormalNotice(): int
    {
        return $this->daysFormalNotice;
    }

    public function setDaysFormalNotice(int $daysFormalNotice): static
    {
        $this->daysFormalNotice = $daysFormalNotice;

        return $this;
    }

    public function isFormalNoticeEnabled(): bool
    {
        return $this->formalNoticeEnabled;
    }

    public function setFormalNoticeEnabled(bool $formalNoticeEnabled): static
    {
        $this->formalNoticeEnabled = $formalNoticeEnabled;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
