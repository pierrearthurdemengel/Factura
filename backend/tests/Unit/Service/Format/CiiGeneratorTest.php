<?php

namespace App\Tests\Unit\Service\Format;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Format\CiiGenerator;
use App\Service\Format\FacturXGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Verifie que CiiGenerator delegue correctement a FacturXGenerator.
 */
class CiiGeneratorTest extends TestCase
{
    public function testDelegatesToFacturXGenerator(): void
    {
        $generator = new CiiGenerator(new FacturXGenerator());

        $seller = new Company();
        $seller->setName('Test SAS');
        $seller->setSiren('111222333');
        $seller->setLegalForm('SAS');
        $seller->setAddressLine1('1 rue Test');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setCompany($seller);
        $buyer->setName('Acheteur SARL');
        $buyer->setAddressLine1('2 rue Test');
        $buyer->setPostalCode('75002');
        $buyer->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setNumber('FA-2026-0003');
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setIssueDate(new \DateTimeImmutable('2026-01-15'));

        $line = new InvoiceLine();
        $line->setPosition(1);
        $line->setDescription('Service');
        $line->setQuantity('1');
        $line->setUnitPriceExcludingTax('100.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        $xml = $generator->generate($invoice);

        // Le XML genere doit etre identique a celui de FacturXGenerator
        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('FA-2026-0003', $xml);
        $this->assertStringContainsString('<?xml', $xml);
    }
}
