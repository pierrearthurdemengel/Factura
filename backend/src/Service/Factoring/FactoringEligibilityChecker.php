<?php

namespace App\Service\Factoring;

use App\Entity\FactoringRequest;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verifie l'eligibilite d'une facture pour l'affacturage.
 *
 * Conditions d'eligibilite :
 * - Facture en statut SENT ou ACKNOWLEDGED
 * - Montant TTC >= seuil minimum (500 EUR par defaut)
 * - Score du client >= 50
 * - Pas de demande d'affacturage active en cours
 */
class FactoringEligibilityChecker
{
    public function __construct(
        private readonly ClientFinancingScorer $scorer,
        private readonly EntityManagerInterface $em,
        private readonly int $minAmountCents = 50000,
        private readonly int $minClientScore = 50,
        private readonly int $commissionBasisPoints = 100,
    ) {
    }

    /**
     * Verifie l'eligibilite et retourne le resultat detaille.
     *
     * @return array{eligible: bool, reason: ?string, proposedAmount: ?int, estimatedFee: ?int, netAmount: ?int, estimatedPayoutDays: ?int, clientScore: int, commission: ?int}
     */
    public function check(Invoice $invoice): array
    {
        $client = $invoice->getBuyer();
        $clientScore = $this->scorer->calculateScore($client);
        $amountCents = (int) round((float) $invoice->getTotalIncludingTax() * 100);

        $base = [
            'eligible' => false,
            'reason' => null,
            'proposedAmount' => null,
            'estimatedFee' => null,
            'netAmount' => null,
            'estimatedPayoutDays' => null,
            'clientScore' => $clientScore,
            'commission' => null,
        ];

        $rejectionReason = $this->findRejectionReason($invoice, $clientScore, $amountCents);
        if (null !== $rejectionReason) {
            $base['reason'] = $rejectionReason;

            return $base;
        }

        // Eligible : calculer les frais
        $feePercentage = $this->scorer->getFeePercentage($clientScore);
        $estimatedFee = (int) round($amountCents * $feePercentage);
        $commission = (int) round($amountCents * $this->commissionBasisPoints / 10000);
        $netAmount = $amountCents - $estimatedFee - $commission;

        // Delai de versement : 24h si ACKNOWLEDGED, 48h si SENT
        $payoutDays = 'ACKNOWLEDGED' === $invoice->getStatus() ? 1 : 2;

        return [
            'eligible' => true,
            'reason' => null,
            'proposedAmount' => $amountCents,
            'estimatedFee' => $estimatedFee,
            'netAmount' => $netAmount,
            'estimatedPayoutDays' => $payoutDays,
            'clientScore' => $clientScore,
            'commission' => $commission,
        ];
    }

    /**
     * Verifie les conditions d'eligibilite et retourne la raison du rejet, ou null si eligible.
     */
    private function findRejectionReason(Invoice $invoice, int $clientScore, int $amountCents): ?string
    {
        if (!in_array($invoice->getStatus(), ['SENT', 'ACKNOWLEDGED'], true)) {
            return 'La facture doit etre en statut SENT ou ACKNOWLEDGED.';
        }

        if ($amountCents < $this->minAmountCents) {
            return sprintf(
                'Le montant TTC (%.2f EUR) est inferieur au seuil minimum (%.2f EUR).',
                $amountCents / 100,
                $this->minAmountCents / 100,
            );
        }

        if ($clientScore < $this->minClientScore) {
            return sprintf(
                'Le score du client (%d) est inferieur au minimum requis (%d).',
                $clientScore,
                $this->minClientScore,
            );
        }

        $existingRequest = $this->em->getRepository(FactoringRequest::class)->findOneBy([
            'invoice' => $invoice,
            'status' => [FactoringRequest::STATUS_PENDING, FactoringRequest::STATUS_APPROVED],
        ]);

        if (null !== $existingRequest) {
            return 'Une demande d\'affacturage est deja en cours pour cette facture.';
        }

        return null;
    }
}
