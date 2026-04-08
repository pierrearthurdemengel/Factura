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

        // Verification du statut
        if (!in_array($invoice->getStatus(), ['SENT', 'ACKNOWLEDGED'], true)) {
            $base['reason'] = 'La facture doit etre en statut SENT ou ACKNOWLEDGED.';

            return $base;
        }

        // Verification du montant minimum
        $amountCents = (int) round((float) $invoice->getTotalIncludingTax() * 100);
        if ($amountCents < $this->minAmountCents) {
            $base['reason'] = sprintf(
                'Le montant TTC (%.2f EUR) est inferieur au seuil minimum (%.2f EUR).',
                $amountCents / 100,
                $this->minAmountCents / 100,
            );

            return $base;
        }

        // Verification du score client
        if ($clientScore < $this->minClientScore) {
            $base['reason'] = sprintf(
                'Le score du client (%d) est inferieur au minimum requis (%d).',
                $clientScore,
                $this->minClientScore,
            );

            return $base;
        }

        // Verification qu'il n'y a pas de demande active
        $existingRequest = $this->em->getRepository(FactoringRequest::class)->findOneBy([
            'invoice' => $invoice,
            'status' => [FactoringRequest::STATUS_PENDING, FactoringRequest::STATUS_APPROVED],
        ]);

        if (null !== $existingRequest) {
            $base['reason'] = 'Une demande d\'affacturage est deja en cours pour cette facture.';

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
}
