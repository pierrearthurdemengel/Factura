<?php

namespace App\Service\Banking;

use App\Entity\BankTransaction;
use App\Entity\Company;
use App\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Moteur de reconciliation bancaire.
 *
 * Compare les transactions bancaires avec les factures en attente
 * de paiement pour proposer des correspondances avec un score de confiance.
 *
 * Criteres de scoring :
 * - Montant exact : +60 points
 * - Montant approchant (+/- 1%) : +30 points
 * - Date proche de l'echeance (+/- 5 jours) : +20 points
 * - Libelle contenant le nom du client : +20 points
 */
class ReconciliationEngine
{
    // Seuil de reconciliation automatique (95%)
    public const AUTO_RECONCILE_THRESHOLD = 95;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Calcule un score de confiance entre une transaction et une facture.
     *
     * @return int Score entre 0 et 100
     */
    public function computeScore(BankTransaction $transaction, Invoice $invoice): int
    {
        $score = 0;

        // Critere 1 : montant exact (60 points)
        $txAmount = abs((float) $transaction->getAmount());
        $invoiceAmount = (float) $invoice->getTotalIncludingTax();

        if ($invoiceAmount > 0) {
            $diff = abs($txAmount - $invoiceAmount);
            $tolerance = $invoiceAmount * 0.01; // 1% de tolerance

            if ($diff < 0.01) {
                // Montant exact
                $score += 60;
            } elseif ($diff <= $tolerance) {
                // Montant approchant
                $score += 30;
            }
        }

        // Critere 2 : date proche de l'echeance (20 points)
        $dueDate = $invoice->getDueDate();
        if (null !== $dueDate) {
            $txDate = $transaction->getTransactionDate();
            $daysDiff = abs((int) $txDate->diff($dueDate)->format('%r%a'));

            if ($daysDiff <= 2) {
                $score += 20;
            } elseif ($daysDiff <= 5) {
                $score += 10;
            }
        }

        // Critere 3 : libelle contenant le nom du client (20 points)
        $buyerName = strtolower($invoice->getBuyer()->getName());
        $txLabel = strtolower($transaction->getLabel());

        // Recherche par mots significatifs (au moins 3 caracteres)
        $words = array_filter(explode(' ', $buyerName), static fn (string $w): bool => mb_strlen($w) >= 3);
        $matchedWords = 0;
        foreach ($words as $word) {
            if (str_contains($txLabel, $word)) {
                ++$matchedWords;
            }
        }

        if ($matchedWords > 0 && count($words) > 0) {
            $ratio = $matchedWords / count($words);
            if ($ratio >= 0.5) {
                $score += 20;
            } else {
                $score += 10;
            }
        }

        return min(100, $score);
    }

    /**
     * Trouve les meilleures correspondances pour une transaction donnee.
     *
     * Retourne les factures candidates triees par score decroissant.
     *
     * @return list<array{invoice: Invoice, score: int}>
     */
    public function findMatches(BankTransaction $transaction, Company $company): array
    {
        // Ne chercher que les factures envoyees ou acquittees (en attente de paiement)
        $invoices = $this->em->getRepository(Invoice::class)->findBy([
            'seller' => $company,
            'status' => ['SENT', 'ACKNOWLEDGED'],
        ]);

        // Ne garder que les credits (montant positif = paiement recu)
        if ((float) $transaction->getAmount() <= 0) {
            return [];
        }

        $matches = [];
        foreach ($invoices as $invoice) {
            $score = $this->computeScore($transaction, $invoice);
            if ($score >= 20) {
                $matches[] = ['invoice' => $invoice, 'score' => $score];
            }
        }

        // Trier par score decroissant
        usort($matches, static fn (array $a, array $b): int => $b['score'] <=> $a['score']);

        return $matches;
    }

    /**
     * Tente la reconciliation automatique d'une transaction.
     *
     * Si un seul match depasse le seuil de 95%, la reconciliation est confirmee
     * et la facture passe en statut PAID.
     *
     * @return bool true si la reconciliation automatique a eu lieu
     */
    public function autoReconcile(BankTransaction $transaction, Company $company): bool
    {
        $matches = $this->findMatches($transaction, $company);

        if ([] === $matches) {
            return false;
        }

        $bestMatch = $matches[0];

        if ($bestMatch['score'] >= self::AUTO_RECONCILE_THRESHOLD) {
            $transaction->setReconciledInvoice($bestMatch['invoice']);
            $transaction->setReconciliationStatus(BankTransaction::RECONCILIATION_CONFIRMED);
            $transaction->setReconciliationScore($bestMatch['score']);

            return true;
        }

        // Si le meilleur score est suffisant pour une suggestion
        if ($bestMatch['score'] >= 40) {
            $transaction->setReconciledInvoice($bestMatch['invoice']);
            $transaction->setReconciliationStatus(BankTransaction::RECONCILIATION_SUGGESTED);
            $transaction->setReconciliationScore($bestMatch['score']);
        }

        return false;
    }
}
