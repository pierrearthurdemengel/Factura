<?php

namespace App\Service\Ocr;

use App\Entity\BankTransaction;
use App\Entity\Receipt;

/**
 * Rapproche les justificatifs avec les transactions bancaires.
 *
 * Utilise le montant et la date extraits par OCR pour trouver
 * la transaction bancaire correspondante.
 */
class ReceiptMatcher
{
    /**
     * Calcule un score de correspondance entre un justificatif et une transaction.
     *
     * @return int Score entre 0 et 100
     */
    public function computeScore(Receipt $receipt, BankTransaction $transaction): int
    {
        $ocrData = $receipt->getOcrData();
        if (null === $ocrData) {
            return 0;
        }

        $score = 0;

        // Critere 1 : montant (60 points)
        $receiptAmount = isset($ocrData['amount']) ? (float) $ocrData['amount'] : null;
        $txAmount = abs((float) $transaction->getAmount());

        if (null !== $receiptAmount && $txAmount > 0) {
            $diff = abs($receiptAmount - $txAmount);
            if ($diff < 0.01) {
                $score += 60;
            } elseif ($diff <= $txAmount * 0.02) {
                $score += 30;
            }
        }

        // Critere 2 : date (30 points)
        $receiptDate = isset($ocrData['date']) ? $this->parseDate((string) $ocrData['date']) : null;
        if (null !== $receiptDate) {
            $txDate = $transaction->getTransactionDate();
            $daysDiff = abs((int) $txDate->diff($receiptDate)->format('%r%a'));

            if (0 === $daysDiff) {
                $score += 30;
            } elseif ($daysDiff <= 3) {
                $score += 15;
            }
        }

        // Critere 3 : nom du fournisseur dans le libelle (10 points)
        $vendor = isset($ocrData['vendor']) ? strtolower((string) $ocrData['vendor']) : null;
        if (null !== $vendor && '' !== $vendor) {
            $txLabel = strtolower($transaction->getLabel());
            $vendorWords = array_filter(explode(' ', $vendor), static fn (string $w): bool => mb_strlen($w) >= 3);
            foreach ($vendorWords as $word) {
                if (str_contains($txLabel, $word)) {
                    $score += 10;

                    break;
                }
            }
        }

        return min(100, $score);
    }

    /**
     * Parse une date au format DD/MM/YYYY.
     */
    private function parseDate(string $date): ?\DateTimeImmutable
    {
        $parsed = \DateTimeImmutable::createFromFormat('d/m/Y', $date);

        return false !== $parsed ? $parsed : null;
    }
}
