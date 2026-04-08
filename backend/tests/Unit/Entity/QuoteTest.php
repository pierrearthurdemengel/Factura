<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Quote;
use App\Entity\QuoteLine;
use PHPUnit\Framework\TestCase;

class QuoteTest extends TestCase
{
    /**
     * Verifie le calcul des totaux avec une seule ligne.
     */
    public function testComputeTotalsWithSingleLine(): void
    {
        $quote = new Quote();

        $line = new QuoteLine();
        $line->setDescription('Prestation');
        $line->setQuantity('3.0000');
        $line->setUnitPriceExcludingTax('200.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $quote->addLine($line);

        $quote->computeTotals();

        $this->assertSame('600.00', $quote->getTotalExcludingTax());
        $this->assertSame('120.00', $quote->getTotalTax());
        $this->assertSame('720.00', $quote->getTotalIncludingTax());
    }

    /**
     * Verifie le calcul des totaux avec plusieurs lignes et taux de TVA.
     */
    public function testComputeTotalsWithMultipleLines(): void
    {
        $quote = new Quote();

        $line1 = new QuoteLine();
        $line1->setDescription('Service A');
        $line1->setQuantity('1.0000');
        $line1->setUnitPriceExcludingTax('1000.0000');
        $line1->setVatRate('20');
        $line1->computeAmounts();
        $quote->addLine($line1);

        $line2 = new QuoteLine();
        $line2->setDescription('Service B');
        $line2->setQuantity('2.0000');
        $line2->setUnitPriceExcludingTax('500.0000');
        $line2->setVatRate('10');
        $line2->computeAmounts();
        $quote->addLine($line2);

        $quote->computeTotals();

        // 1000 + 1000 = 2000 HT, TVA = 200 + 100 = 300
        $this->assertSame('2000.00', $quote->getTotalExcludingTax());
        $this->assertSame('300.00', $quote->getTotalTax());
        $this->assertSame('2300.00', $quote->getTotalIncludingTax());
    }

    /**
     * Verifie qu'un devis sans ligne est invalide.
     */
    public function testIsInvalidWithoutLines(): void
    {
        $quote = new Quote();
        $this->assertFalse($quote->isValid());
    }

    /**
     * Verifie qu'un devis avec des lignes et un total non nul est valide.
     */
    public function testIsValidWithLinesAndTotal(): void
    {
        $quote = new Quote();

        $line = new QuoteLine();
        $line->setDescription('Service');
        $line->setQuantity('1.0000');
        $line->setUnitPriceExcludingTax('100.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $quote->addLine($line);
        $quote->computeTotals();

        $this->assertTrue($quote->isValid());
    }

    /**
     * Verifie le statut initial du devis.
     */
    public function testInitialStatusIsDraft(): void
    {
        $quote = new Quote();
        $this->assertSame('DRAFT', $quote->getStatus());
    }

    /**
     * Verifie la devise par defaut.
     */
    public function testDefaultCurrencyIsEur(): void
    {
        $quote = new Quote();
        $this->assertSame('EUR', $quote->getCurrency());
    }
}
