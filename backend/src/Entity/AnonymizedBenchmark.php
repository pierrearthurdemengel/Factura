<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Donnees agregees et anonymisees pour les benchmarks sectoriels.
 * Chaque ligne represente un agregat mensuel par secteur d'activite.
 * Aucune donnee individuelle n'est stockee — minimum 5 contributeurs par agregat.
 */
#[ORM\Entity]
#[ORM\Table(name: 'anonymized_benchmarks')]
#[ORM\UniqueConstraint(columns: ['sector', 'metric', 'period'], name: 'uniq_benchmark_sector_metric_period')]
class AnonymizedBenchmark
{
    // Metriques disponibles
    public const METRIC_AVG_INVOICE_AMOUNT = 'avg_invoice_amount';
    public const METRIC_MEDIAN_PAYMENT_DELAY = 'median_payment_delay';
    public const METRIC_AVG_MONTHLY_REVENUE = 'avg_monthly_revenue';
    public const METRIC_LATE_PAYMENT_RATE = 'late_payment_rate';
    public const METRIC_AVG_CLIENT_COUNT = 'avg_client_count';

    // Seuil minimum de contributeurs pour publier un agregat
    public const MIN_CONTRIBUTORS = 5;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: 'doctrine.uuid_generator')]
    private ?Uuid $id = null;

    // Code NAF / secteur d'activite agrege (ex: "62" pour dev informatique)
    #[ORM\Column(length: 10)]
    private string $sector;

    // Type de metrique
    #[ORM\Column(length: 50)]
    private string $metric;

    // Periode au format YYYY-MM
    #[ORM\Column(length: 7)]
    private string $period;

    // Valeur agregee (montant, delai, pourcentage)
    #[ORM\Column(length: 30)]
    private string $value;

    // Nombre d'entreprises ayant contribue a cet agregat
    #[ORM\Column]
    private int $contributorCount;

    #[ORM\Column]
    private \DateTimeImmutable $computedAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->computedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }

    public function getSector(): string
    {
        return $this->sector;
    }

    public function setSector(string $sector): static
    {
        $this->sector = $sector;

        return $this;
    }

    public function getMetric(): string
    {
        return $this->metric;
    }

    public function setMetric(string $metric): static
    {
        $this->metric = $metric;

        return $this;
    }

    public function getPeriod(): string
    {
        return $this->period;
    }

    public function setPeriod(string $period): static
    {
        $this->period = $period;

        return $this;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getContributorCount(): int
    {
        return $this->contributorCount;
    }

    public function setContributorCount(int $contributorCount): static
    {
        $this->contributorCount = $contributorCount;

        return $this;
    }

    /**
     * Verifie que l'agregat respecte le seuil minimum de contributeurs.
     */
    public function isPublishable(): bool
    {
        return $this->contributorCount >= self::MIN_CONTRIBUTORS;
    }

    public function getComputedAt(): \DateTimeImmutable
    {
        return $this->computedAt;
    }
}
