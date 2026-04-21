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

        $score += $this->scoreAmount($ocrData, $transaction);
        $score += $this->scoreDate($ocrData, $transaction);
        $score += $this->scoreVendor($ocrData, $transaction);

        return min(100, $score);
    }

    /**
     * Score le critere montant (60 points max).
     *
     * @param array<string, mixed> $ocrData
     */
    private function scoreAmount(array $ocrData, BankTransaction $transaction): int
    {
        $receiptAmount = isset($ocrData['amount']) ? (float) $ocrData['amount'] : null;
        $txAmount = abs((float) $transaction->getAmount());

        if (null === $receiptAmount || $txAmount <= 0) {
            return 0;
        }

        $diff = abs($receiptAmount - $txAmount);

        if ($diff < 0.01) {
            return 60;
        }

        return $diff <= $txAmount * 0.02 ? 30 : 0;
    }

    /**
     * Score le critere date (30 points max).
     *
     * @param array<string, mixed> $ocrData
     */
    private function scoreDate(array $ocrData, BankTransaction $transaction): int
    {
        $receiptDate = isset($ocrData['date']) ? $this->parseDate((string) $ocrData['date']) : null;
        if (null === $receiptDate) {
            return 0;
        }

        $daysDiff = abs((int) $transaction->getTransactionDate()->diff($receiptDate)->format('%r%a'));

        if (0 === $daysDiff) {
            return 30;
        }

        return $daysDiff <= 3 ? 15 : 0;
    }

    /**
     * Score le critere nom du fournisseur dans le libelle (10 points max).
     *
     * @param array<string, mixed> $ocrData
     */
    private function scoreVendor(array $ocrData, BankTransaction $transaction): int
    {
        $vendor = isset($ocrData['vendor']) ? strtolower((string) $ocrData['vendor']) : null;
        if (null === $vendor || '' === $vendor) {
            return 0;
        }

        $txLabel = strtolower($transaction->getLabel());
        $vendorWords = array_filter(explode(' ', $vendor), static fn (string $w): bool => mb_strlen($w) >= 3);
        foreach ($vendorWords as $word) {
            if (str_contains($txLabel, $word)) {
                return 10;
            }
        }

        return 0;
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
