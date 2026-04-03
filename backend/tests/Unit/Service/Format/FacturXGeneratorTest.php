<?php

namespace App\Tests\Unit\Service\Format;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Format\FacturXGenerator;
use PHPUnit\Framework\TestCase;

class FacturXGeneratorTest extends TestCase
{
    private FacturXGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FacturXGenerator();
    }

    private function createInvoice(): Invoice
    {
        $seller = new Company();
        $seller->setName('Factura SAS');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SAS');
        $seller->setAddressLine1('10 rue de la Paix');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');
        $seller->setVatNumber('FR12123456789');

        $buyer = new Client();
        $buyer->setCompany($seller);
        $buyer->setName('Client SARL');
        $buyer->setAddressLine1('20 avenue des Champs');
        $buyer->setPostalCode('75008');
        $buyer->setCity('Paris');
        $buyer->setCountryCode('FR');

        $invoice = new Invoice();
        $invoice->setNumber('FA-2026-0001');
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setCurrency('EUR');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-03-15'));

        $line = new InvoiceLine();
        $line->setPosition(1);
        $line->setDescription('Developpement web');
        $line->setQuantity('5');
        $line->setUnitPriceExcludingTax('600.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testGeneratesValidXml(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('<?xml', $xml);
    }

    public function testContainsInvoiceNumber(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('FA-2026-0001', $xml);
    }

    public function testContainsSellerName(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('Factura SAS', $xml);
    }

    public function testContainsBuyerName(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('Client SARL', $xml);
    }

    public function testContainsCurrency(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('EUR', $xml);
    }

    public function testContainsLineDescription(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('Developpement web', $xml);
    }

    public function testContainsVatRegistration(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('FR12123456789', $xml);
    }

    public function testGeneratesMultipleLines(): void
    {
        $invoice = $this->createInvoice();

        $line2 = new InvoiceLine();
        $line2->setPosition(2);
        $line2->setDescription('Design graphique');
        $line2->setQuantity('2');
        $line2->setUnitPriceExcludingTax('400.0000');
        $line2->setVatRate('20');
        $line2->computeAmounts();
        $invoice->addLine($line2);
        $invoice->computeTotals();

        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('Developpement web', $xml);
        $this->assertStringContainsString('Design graphique', $xml);
    }

    public function testHandlesExemptVatRate(): void
    {
        $invoice = $this->createInvoice();
        $line = $invoice->getLines()->first();
        $line->setVatRate('E');
        $line->computeAmounts();
        $invoice->computeTotals();
        $invoice->setLegalMention('TVA non applicable — art. 293 B du CGI');

        $xml = $this->generator->generate($invoice);

        $this->assertNotEmpty($xml);
    }

    public function testHandlesDueDate(): void
    {
        $invoice = $this->createInvoice();
        $invoice->setDueDate(new \DateTimeImmutable('2026-04-15'));

        $xml = $this->generator->generate($invoice);

        // La date d'echeance doit apparaitre dans le XML au format 102
        $this->assertStringContainsString('20260415', $xml);
    }
}
