<?php

namespace App\Tests\Unit\Entity;

use App\Entity\InvoiceLine;
use PHPUnit\Framework\TestCase;

class InvoiceLineTest extends TestCase
{
    /**
     * Verifie le calcul des montants d'une ligne avec TVA a 20%.
     */
    public function testComputeAmountsWithStandardVat(): void
    {
        $line = new InvoiceLine();
        $line->setQuantity('5');
        $line->setUnitPriceExcludingTax('600.0000');
        $line->setVatRate('20');
        $line->computeAmounts();

        // 5 * 600 = 3000
        $this->assertSame('3000.00', $line->getLineAmount());
        // 3000 * 20% = 600
        $this->assertSame('600.00', $line->getVatAmount());
    }

    /**
     * Verifie le calcul avec TVA reduite a 5.5%.
     */
    public function testComputeAmountsWithReducedVat(): void
    {
        $line = new InvoiceLine();
        $line->setQuantity('1');
        $line->setUnitPriceExcludingTax('1000.0000');
        $line->setVatRate('5.5');
        $line->computeAmounts();

        $this->assertSame('1000.00', $line->getLineAmount());
        $this->assertSame('55.00', $line->getVatAmount());
    }

    /**
     * Verifie le calcul avec autoliquidation (pas de TVA).
     */
    public function testComputeAmountsWithAutoliquidation(): void
    {
        $line = new InvoiceLine();
        $line->setQuantity('3');
        $line->setUnitPriceExcludingTax('250.0000');
        $line->setVatRate('AE');
        $line->computeAmounts();

        $this->assertSame('750.00', $line->getLineAmount());
        $this->assertSame('0.00', $line->getVatAmount());
    }

    /**
     * Verifie les valeurs par defaut.
     */
    public function testDefaultValues(): void
    {
        $line = new InvoiceLine();

        $this->assertSame('EA', $line->getUnit());
        $this->assertSame('20', $line->getVatRate());
        $this->assertSame(1, $line->getPosition());
    }
}
