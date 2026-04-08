<?php

namespace App\Tests\Unit\Service\Ocr;

use App\Entity\BankTransaction;
use App\Entity\Receipt;
use App\Service\Ocr\ReceiptMatcher;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du rapprochement justificatif ↔ transaction.
 */
class ReceiptMatcherTest extends TestCase
{
    private ReceiptMatcher $matcher;

    protected function setUp(): void
    {
        $this->matcher = new ReceiptMatcher();
    }

    /**
     * Verifie un score parfait : montant, date et fournisseur correspondent.
     */
    public function testPerfectMatch(): void
    {
        $receipt = $this->createReceipt(['amount' => '42.50', 'date' => '08/04/2026', 'vendor' => 'Amazon']);
        $transaction = $this->createTransaction('-42.50', '2026-04-08', 'Paiement Amazon');

        $score = $this->matcher->computeScore($receipt, $transaction);

        // Montant exact (60) + date exacte (30) + fournisseur (10) = 100
        $this->assertSame(100, $score);
    }

    /**
     * Verifie un score avec uniquement le montant exact.
     */
    public function testAmountOnlyMatch(): void
    {
        $receipt = $this->createReceipt(['amount' => '150.00', 'date' => null, 'vendor' => null]);
        $transaction = $this->createTransaction('-150.00', '2026-04-15', 'Virement divers');

        $score = $this->matcher->computeScore($receipt, $transaction);

        // Montant exact (60)
        $this->assertSame(60, $score);
    }

    /**
     * Verifie un score nul sans donnees OCR.
     */
    public function testNoOcrData(): void
    {
        $receipt = $this->createReceipt(null);
        $transaction = $this->createTransaction('-50.00', '2026-04-08', 'Achat');

        $score = $this->matcher->computeScore($receipt, $transaction);

        $this->assertSame(0, $score);
    }

    /**
     * Verifie un score avec date proche.
     */
    public function testDateProximityMatch(): void
    {
        $receipt = $this->createReceipt(['amount' => '100.00', 'date' => '06/04/2026', 'vendor' => null]);
        $transaction = $this->createTransaction('-100.00', '2026-04-08', 'Achat divers');

        $score = $this->matcher->computeScore($receipt, $transaction);

        // Montant exact (60) + date proche J+2 (15) = 75
        $this->assertSame(75, $score);
    }

    /**
     * Verifie un score nul quand rien ne correspond.
     */
    public function testNoMatch(): void
    {
        $receipt = $this->createReceipt(['amount' => '999.99', 'date' => '01/01/2025', 'vendor' => 'xyz']);
        $transaction = $this->createTransaction('-50.00', '2026-04-08', 'Abonnement internet');

        $score = $this->matcher->computeScore($receipt, $transaction);

        $this->assertSame(0, $score);
    }

    /**
     * @param array<string, mixed>|null $ocrData
     */
    private function createReceipt(?array $ocrData): Receipt
    {
        $receipt = new Receipt();
        $receipt->setFilePath('/tmp/test.pdf');
        $receipt->setOriginalFilename('test.pdf');
        $receipt->setMimeType('application/pdf');
        $receipt->setFileSize(1024);
        $receipt->setFileHash(hash('sha256', 'test'));
        $receipt->setOcrData($ocrData);

        return $receipt;
    }

    private function createTransaction(string $amount, string $date, string $label): BankTransaction
    {
        $transaction = new BankTransaction();
        $transaction->setExternalTransactionId('tx_' . uniqid());
        $transaction->setAmount($amount);
        $transaction->setTransactionDate(new \DateTimeImmutable($date));
        $transaction->setLabel($label);

        return $transaction;
    }
}
