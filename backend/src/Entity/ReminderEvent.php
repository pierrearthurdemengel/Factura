<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Historique des relances envoyees.
 *
 * Immuable : aucun setter, constructeur uniquement.
 * Enregistre chaque relance avec le type, la facture, le destinataire et le statut.
 */
#[ORM\Entity]
#[ORM\Table(name: 'reminder_events')]
#[ORM\Index(columns: ['invoice_id', 'sent_at'], name: 'idx_reminder_invoice_date')]
class ReminderEvent
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['reminder_event:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Invoice $invoice;

    // Type de relance envoyee (BEFORE_DUE, FIRST_REMINDER, SECOND_REMINDER, FORMAL_NOTICE)
    #[ORM\Column(length: 30)]
    #[Groups(['reminder_event:read'])]
    private string $reminderType;

    // Adresse email du destinataire au moment de l'envoi
    #[ORM\Column(length: 255)]
    #[Groups(['reminder_event:read'])]
    private string $recipientEmail;

    // Objet de l'email envoye
    #[ORM\Column(length: 255)]
    #[Groups(['reminder_event:read'])]
    private string $subject;

    // Statut d'envoi : SENT, FAILED
    #[ORM\Column(length: 20)]
    #[Groups(['reminder_event:read'])]
    private string $status;

    // Message d'erreur en cas d'echec
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    // Chemin S3 du PDF de mise en demeure (si applicable)
    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['reminder_event:read'])]
    private ?string $formalNoticePath = null;

    #[ORM\Column]
    #[Groups(['reminder_event:read'])]
    private \DateTimeImmutable $sentAt;

    public function __construct(
        Invoice $invoice,
        string $reminderType,
        string $recipientEmail,
        string $subject,
        string $status,
        ?string $errorMessage = null,
        ?string $formalNoticePath = null,
    ) {
        $this->id = Uuid::v7();
        $this->invoice = $invoice;
        $this->reminderType = $reminderType;
        $this->recipientEmail = $recipientEmail;
        $this->subject = $subject;
        $this->status = $status;
        $this->errorMessage = $errorMessage;
        $this->formalNoticePath = $formalNoticePath;
        $this->sentAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getInvoice(): Invoice
    {
        return $this->invoice;
    }

    public function getReminderType(): string
    {
        return $this->reminderType;
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getFormalNoticePath(): ?string
    {
        return $this->formalNoticePath;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }
}
