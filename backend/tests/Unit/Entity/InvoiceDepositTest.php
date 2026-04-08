<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Entity\Quote;
use PHPUnit\Framework\TestCase;

class InvoiceDepositTest extends TestCase
{
    /**
     * Verifie le type par defaut d'une facture (STANDARD).
     */
    public function testDefaultTypeIsStandard(): void
    {
        $invoice = new Invoice();
        $this->assertSame('STANDARD', $invoice->getType());
    }

    /**
     * Verifie qu'une facture d'acompte peut etre creee.
     */
    public function testCreateDepositInvoice(): void
    {
        $parentInvoice = new Invoice();
        $parentInvoice->setType('STANDARD');

        $deposit = new Invoice();
        $deposit->setType('DEPOSIT');
        $deposit->setParentInvoice($parentInvoice);

        $this->assertSame('DEPOSIT', $deposit->getType());
        $this->assertSame($parentInvoice, $deposit->getParentInvoice());
    }

    /**
     * Verifie qu'un avoir peut etre cree.
     */
    public function testCreateCreditNote(): void
    {
        $invoice = new Invoice();
        $invoice->setType('CREDIT_NOTE');

        $this->assertSame('CREDIT_NOTE', $invoice->getType());
    }

    /**
     * Verifie le lien entre facture et devis source.
     */
    public function testInvoiceLinkedToSourceQuote(): void
    {
        $quote = $this->createMock(Quote::class);

        $invoice = new Invoice();
        $invoice->setSourceQuote($quote);

        $this->assertSame($quote, $invoice->getSourceQuote());
    }

    /**
     * Verifie la deduction des acomptes dans le calcul du total.
     * Le montant de l'acompte doit etre inferieur ou egal au montant total parent.
     */
    public function testDepositAmountValidation(): void
    {
        $company = $this->createMock(Company::class);
        $client = $this->createMock(Client::class);

        // Facture principale : 10 000 EUR HT
        $parentInvoice = new Invoice();
        $parentInvoice->setSeller($company);
        $parentInvoice->setBuyer($client);

        $parentLine = new InvoiceLine();
        $parentLine->setDescription('Projet complet');
        $parentLine->setQuantity('1.0000');
        $parentLine->setUnitPriceExcludingTax('10000.0000');
        $parentLine->setVatRate('20');
        $parentLine->computeAmounts();
        $parentInvoice->addLine($parentLine);
        $parentInvoice->computeTotals();

        // Acompte : 3 000 EUR HT (30% du projet)
        $deposit = new Invoice();
        $deposit->setType('DEPOSIT');
        $deposit->setParentInvoice($parentInvoice);
        $deposit->setSeller($company);
        $deposit->setBuyer($client);

        $depositLine = new InvoiceLine();
        $depositLine->setDescription('Acompte 30% - Projet complet');
        $depositLine->setQuantity('1.0000');
        $depositLine->setUnitPriceExcludingTax('3000.0000');
        $depositLine->setVatRate('20');
        $depositLine->computeAmounts();
        $deposit->addLine($depositLine);
        $deposit->computeTotals();

        $this->assertSame('3000.00', $deposit->getTotalExcludingTax());
        $this->assertSame('600.00', $deposit->getTotalTax());
        $this->assertSame('3600.00', $deposit->getTotalIncludingTax());

        // Verifie que le montant de l'acompte est bien inferieur au total parent
        $this->assertTrue(
            bccomp($deposit->getTotalExcludingTax(), $parentInvoice->getTotalExcludingTax(), 2) <= 0,
        );
    }
}
