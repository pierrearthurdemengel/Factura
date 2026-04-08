<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Uid\Uuid;

/**
 * Justificatif de depense (facture fournisseur, ticket de caisse, etc.).
 *
 * Stocke le fichier sur S3 et les donnees extraites par OCR.
 * Peut etre rapproche d'une transaction bancaire pour preuve comptable.
 */
#[ORM\Entity]
#[ORM\Table(name: 'receipts')]
#[ORM\Index(columns: ['company_id', 'created_at'], name: 'idx_receipt_company_date')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
        new GetCollection(),
        new Delete(security: "is_granted('ROLE_USER') and object.getCompany().getOwner() == user"),
    ],
    normalizationContext: ['groups' => ['receipt:read']],
)]
class Receipt
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    #[Groups(['receipt:read'])]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Company $company;

    // Chemin du fichier sur S3
    #[ORM\Column(length: 500)]
    #[Groups(['receipt:read'])]
    private string $filePath;

    // Nom du fichier original
    #[ORM\Column(length: 255)]
    #[Groups(['receipt:read'])]
    private string $originalFilename;

    #[ORM\Column(length: 50)]
    #[Groups(['receipt:read'])]
    private string $mimeType;

    // Taille en octets
    #[ORM\Column]
    #[Groups(['receipt:read'])]
    private int $fileSize;

    // Hash SHA-256 du fichier original (piste d'audit, valeur probante)
    #[ORM\Column(length: 64)]
    #[Groups(['receipt:read'])]
    private string $fileHash;

    // Donnees extraites par OCR (JSON structure)
    /** @var array<string, mixed>|null */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['receipt:read'])]
    private ?array $ocrData = null;

    // Statut de l'extraction OCR
    #[ORM\Column(length: 20)]
    #[Groups(['receipt:read'])]
    private string $ocrStatus = 'PENDING';

    // Transaction bancaire rapprochee
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['receipt:read'])]
    private ?BankTransaction $bankTransaction = null;

    #[ORM\Column]
    #[Groups(['receipt:read'])]
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

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getOriginalFilename(): string
    {
        return $this->originalFilename;
    }

    public function setOriginalFilename(string $originalFilename): static
    {
        $this->originalFilename = $originalFilename;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getFileSize(): int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): static
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getFileHash(): string
    {
        return $this->fileHash;
    }

    public function setFileHash(string $fileHash): static
    {
        $this->fileHash = $fileHash;

        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getOcrData(): ?array
    {
        return $this->ocrData;
    }

    /** @param array<string, mixed>|null $ocrData */
    public function setOcrData(?array $ocrData): static
    {
        $this->ocrData = $ocrData;

        return $this;
    }

    public function getOcrStatus(): string
    {
        return $this->ocrStatus;
    }

    public function setOcrStatus(string $ocrStatus): static
    {
        $this->ocrStatus = $ocrStatus;

        return $this;
    }

    public function getBankTransaction(): ?BankTransaction
    {
        return $this->bankTransaction;
    }

    public function setBankTransaction(?BankTransaction $bankTransaction): static
    {
        $this->bankTransaction = $bankTransaction;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
