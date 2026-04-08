<?php

namespace App\Service\Factoring;

use App\Entity\Client;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Calcule le score de financement d'un client pour l'affacturage.
 *
 * Le score (0-100) determine les frais appliques :
 * - Score >= 90 : 2.0% de frais
 * - Score 80-89 : 2.5%
 * - Score 70-79 : 3.0%
 * - Score 60-69 : 3.5%
 * - Score 50-59 : 5.0%
 * - Score < 50 : non eligible
 */
class ClientFinancingScorer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule le score de financement du client.
     */
    public function calculateScore(Client $client): int
    {
        $invoices = $this->getClientInvoices($client);
        $totalInvoices = count($invoices);

        // Pas d'historique : score de base
        if (0 === $totalInvoices) {
            return 50;
        }

        $score = 50;

        // Critere 1 : historique de paiement (max +40 points)
        $score += $this->computePaymentHistoryScore($invoices);

        // Critere 2 : delai moyen de paiement (max +10 points)
        $score += $this->computePaymentDelayScore($invoices);

        // Critere 3 : stabilite des montants (max +10 points)
        $score += $this->computeStabilityScore($invoices);

        // Penalite : factures en retard
        $score -= $this->computeOverduePenalty($invoices);

        // Plafond pour les clients avec peu d'historique
        if ($totalInvoices <= 2) {
            $score = min($score, 70);
        }

        return max(0, min(100, $score));
    }

    /**
     * Retourne le pourcentage de frais en fonction du score.
     */
    public function getFeePercentage(int $score): float
    {
        return match (true) {
            $score >= 90 => 0.02,
            $score >= 80 => 0.025,
            $score >= 70 => 0.03,
            $score >= 60 => 0.035,
            $score >= 50 => 0.05,
            default => 0.0,
        };
    }

    /**
     * Score base sur le ratio de paiements dans les delais.
     *
     * @param Invoice[] $invoices
     */
    private function computePaymentHistoryScore(array $invoices): int
    {
        $paid = array_filter($invoices, static fn (Invoice $i): bool => 'PAID' === $i->getStatus());
        $totalPaid = count($paid);

        if (0 === $totalPaid) {
            return 0;
        }

        // Paiements dans les delais (echeance + 5 jours de grace)
        $onTime = 0;
        foreach ($paid as $invoice) {
            if (null === $invoice->getDueDate()) {
                ++$onTime;

                continue;
            }
            // On considere "a temps" si le statut PAID est atteint
            // (pas de champ paidAt : on se base sur le fait que PAID = paye)
            ++$onTime;
        }

        $ratio = $onTime / $totalPaid;

        return match (true) {
            $ratio >= 0.95 => 40,
            $ratio >= 0.85 => 30,
            $ratio >= 0.70 => 20,
            $ratio >= 0.50 => 10,
            default => 0,
        };
    }

    /**
     * Score base sur le delai moyen entre emission et paiement.
     *
     * @param Invoice[] $invoices
     */
    private function computePaymentDelayScore(array $invoices): int
    {
        $paidInvoices = array_filter($invoices, static fn (Invoice $i): bool => 'PAID' === $i->getStatus() && null !== $i->getDueDate());

        if (0 === count($paidInvoices)) {
            return 0;
        }

        // Delai moyen entre date d'emission et date d'echeance (approximation)
        $totalDays = 0;
        foreach ($paidInvoices as $invoice) {
            $dueDate = $invoice->getDueDate();
            $issueDate = $invoice->getIssueDate();
            if (null !== $dueDate) {
                $totalDays += abs((int) $issueDate->diff($dueDate)->format('%r%a'));
            }
        }

        $avgDays = $totalDays / count($paidInvoices);

        return match (true) {
            $avgDays < 10 => 10,
            $avgDays < 20 => 8,
            $avgDays < 30 => 5,
            default => 0,
        };
    }

    /**
     * Score base sur la stabilite des montants factures.
     *
     * @param Invoice[] $invoices
     */
    private function computeStabilityScore(array $invoices): int
    {
        if (count($invoices) < 2) {
            return 5;
        }

        $amounts = array_map(
            static fn (Invoice $i): float => (float) $i->getTotalIncludingTax(),
            $invoices,
        );

        $mean = array_sum($amounts) / count($amounts);
        if ($mean <= 0) {
            return 0;
        }

        // Ecart-type
        $variance = array_sum(array_map(
            static fn (float $a): float => ($a - $mean) ** 2,
            $amounts,
        )) / count($amounts);

        $stdDev = sqrt($variance);
        $coeffVariation = $stdDev / $mean;

        return match (true) {
            $coeffVariation < 0.10 => 10,
            $coeffVariation < 0.25 => 5,
            default => 0,
        };
    }

    /**
     * Penalite pour les factures en retard non payees.
     *
     * @param Invoice[] $invoices
     */
    private function computeOverduePenalty(array $invoices): int
    {
        $now = new \DateTimeImmutable();
        $penalty = 0;

        foreach ($invoices as $invoice) {
            if ('PAID' === $invoice->getStatus() || 'CANCELLED' === $invoice->getStatus()) {
                continue;
            }

            $dueDate = $invoice->getDueDate();
            if (null === $dueDate) {
                continue;
            }

            $daysOverdue = (int) $now->diff($dueDate)->format('%r%a');
            // Valeur negative = en retard
            if ($daysOverdue < 0) {
                $overdueDays = abs($daysOverdue);
                if ($overdueDays > 60) {
                    return 60; // Score max = 40 (base 50 - 60 = invalide → cap a 0)
                }
                if ($overdueDays > 30) {
                    $penalty = max($penalty, 20);
                }
            }
        }

        return $penalty;
    }

    /**
     * Recupere toutes les factures emises vers ce client.
     *
     * @return Invoice[]
     */
    private function getClientInvoices(Client $client): array
    {
        /** @var Invoice[] $invoices */
        $invoices = $this->em->getRepository(Invoice::class)->findBy(
            ['buyer' => $client],
            ['issueDate' => 'DESC'],
        );

        return $invoices;
    }
}
