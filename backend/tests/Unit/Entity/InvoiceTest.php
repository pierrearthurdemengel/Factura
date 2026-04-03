<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use PHPUnit\Framework\TestCase;

class InvoiceTest extends TestCase
{
    /**
     * Verifie le calcul des totaux a partir des lignes.
     */
    public function testComputeTotals(): void
    {
        $invoice = new Invoice();

        $line1 = new InvoiceLine();
        $line1->setDescription('Developpement web');
        $line1->setQuantity('5');
        $line1->setUnitPriceExcludingTax('600.0000');
        $line1->setVatRate('20');
        $line1->computeAmounts();

        $line2 = new InvoiceLine();
        $line2->setDescription('Design graphique');
        $line2->setQuantity('2');
        $line2->setUnitPriceExcludingTax('400.0000');
        $line2->setVatRate('20');
        $line2->computeAmounts();

        $invoice->addLine($line1);
        $invoice->addLine($line2);
        $invoice->computeTotals();

        // 5 * 600 = 3000, 2 * 400 = 800, total HT = 3800
        $this->assertSame('3800.00', $invoice->getTotalExcludingTax());
        // TVA 20% de 3800 = 760
        $this->assertSame('760.00', $invoice->getTotalTax());
        // TTC = 3800 + 760 = 4560
        $this->assertSame('4560.00', $invoice->getTotalIncludingTax());
    }

    /**
     * Verifie le calcul avec un taux TVA a 0 (micro-entrepreneur).
     */
    public function testComputeTotalsWithZeroVat(): void
    {
        $invoice = new Invoice();

        $line = new InvoiceLine();
        $line->setDescription('Prestation de conseil');
        $line->setQuantity('10');
        $line->setUnitPriceExcludingTax('100.0000');
        $line->setVatRate('0');
        $line->computeAmounts();

        $invoice->addLine($line);
        $invoice->computeTotals();

        $this->assertSame('1000.00', $invoice->getTotalExcludingTax());
        $this->assertSame('0.00', $invoice->getTotalTax());
        $this->assertSame('1000.00', $invoice->getTotalIncludingTax());
    }

    /**
     * Verifie que isValid retourne false sans lignes.
     */
    public function testIsValidReturnsFalseWithoutLines(): void
    {
        $invoice = new Invoice();
        $this->assertFalse($invoice->isValid());
    }

    /**
     * Verifie le statut par defaut.
     */
    public function testDefaultStatusIsDraft(): void
    {
        $invoice = new Invoice();
        $this->assertSame('DRAFT', $invoice->getStatus());
    }

    /**
     * Verifie la devise par defaut.
     */
    public function testDefaultCurrencyIsEur(): void
    {
        $invoice = new Invoice();
        $this->assertSame('EUR', $invoice->getCurrency());
    }
}
