<?php

namespace App\Tests\Unit\Service\Accounting;

use App\Service\Accounting\TransactionCategorizer;
use PHPUnit\Framework\TestCase;

class TransactionCategorizerTest extends TestCase
{
    private TransactionCategorizer $categorizer;

    protected function setUp(): void
    {
        $this->categorizer = new TransactionCategorizer();
    }

    public function testCategorizesUrssaf(): void
    {
        $result = $this->categorizer->categorize('PRELEVEMENT URSSAF IDF');

        self::assertNotNull($result);
        self::assertSame('646000', $result['account']);
        self::assertGreaterThanOrEqual(90, $result['confidence']);
    }

    public function testCategorizesLoyer(): void
    {
        $result = $this->categorizer->categorize('VIR LOYER BUREAU MARS 2026');

        self::assertNotNull($result);
        self::assertSame('613200', $result['account']);
    }

    public function testCategorizesAssurance(): void
    {
        $result = $this->categorizer->categorize('PRLV MAIF ASSURANCE RC PRO');

        self::assertNotNull($result);
        self::assertSame('616000', $result['account']);
    }

    public function testCategorizesTransport(): void
    {
        $result = $this->categorizer->categorize('ACHAT CB SNCF BILLET TGV');

        self::assertNotNull($result);
        self::assertSame('625100', $result['account']);
    }

    public function testCategorizesImpots(): void
    {
        $result = $this->categorizer->categorize('PRLV DGFIP IMPOT SUR LE REVENU');

        self::assertNotNull($result);
        self::assertSame('447100', $result['account']);
    }

    public function testCategorizesLogiciels(): void
    {
        $result = $this->categorizer->categorize('PAIEMENT ADOBE CREATIVE CLOUD');

        self::assertNotNull($result);
        self::assertSame('651000', $result['account']);
    }

    public function testCategorizesPublicite(): void
    {
        $result = $this->categorizer->categorize('PAIEMENT GOOGLE ADS CAMPAGNE');

        self::assertNotNull($result);
        self::assertSame('623000', $result['account']);
    }

    public function testReturnsNullForUnknownTransaction(): void
    {
        $result = $this->categorizer->categorize('VIR DUPONT JEAN REMBOURSEMENT');

        self::assertNull($result);
    }

    public function testCaseInsensitive(): void
    {
        $result = $this->categorizer->categorize('prlv URSSAF idf');

        self::assertNotNull($result);
        self::assertSame('646000', $result['account']);
    }

    public function testSelectsHighestConfidenceMatch(): void
    {
        // "orange" a un score de 80, verifier qu'il est bien categorise
        $result = $this->categorizer->categorize('FACTURE ORANGE MOBILE');

        self::assertNotNull($result);
        self::assertSame('626000', $result['account']);
    }

    public function testRulesListIsNotEmpty(): void
    {
        $rules = TransactionCategorizer::getRules();

        self::assertNotEmpty($rules);
        self::assertGreaterThan(20, count($rules));
    }
}
