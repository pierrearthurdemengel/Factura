<?php

namespace App\Tests\Unit\Service\Format;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Format\UblGenerator;
use PHPUnit\Framework\TestCase;

class UblGeneratorTest extends TestCase
{
    private UblGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new UblGenerator();
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
        $seller->setIban('FR7630001007941234567890185');
        $seller->setBic('BNPAFRPPXXX');

        $buyer = new Client();
        $buyer->setCompany($seller);
        $buyer->setName('Client SARL');
        $buyer->setAddressLine1('20 avenue des Champs');
        $buyer->setPostalCode('75008');
        $buyer->setCity('Paris');
        $buyer->setCountryCode('FR');
        $buyer->setVatNumber('FR98987654321');

        $invoice = new Invoice();
        $invoice->setNumber('FA-2026-0002');
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setCurrency('EUR');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-04-01'));
        $invoice->setDueDate(new \DateTimeImmutable('2026-05-01'));

        $line = new InvoiceLine();
        $line->setPosition(1);
        $line->setDescription('Audit securite');
        $line->setQuantity('3');
        $line->setUnitPriceExcludingTax('1500.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testGeneratesValidUblXml(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertNotEmpty($xml);
        $this->assertStringContainsString('<?xml', $xml);
    }

    public function testContainsUblNamespace(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('urn:oasis:names:specification:ubl:schema:xsd:Invoice-2', $xml);
    }

    public function testContainsPeppolCustomizationId(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        // Profil Peppol BIS Billing 3.0
        $this->assertStringContainsString('peppol.eu', $xml);
    }

    public function testContainsInvoiceNumber(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('FA-2026-0002', $xml);
    }

    public function testContainsSellerInfo(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('Factura SAS', $xml);
        $this->assertStringContainsString('FR12123456789', $xml);
    }

    public function testContainsBuyerInfo(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('Client SARL', $xml);
        $this->assertStringContainsString('FR98987654321', $xml);
    }

    public function testContainsPaymentMeans(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        // Le vendeur a un IBAN, donc les moyens de paiement doivent etre inclus
        $this->assertStringContainsString('PaymentMeans', $xml);
        $this->assertStringContainsString('FR7630001007941234567890185', $xml);
    }

    public function testContainsTaxTotal(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('TaxTotal', $xml);
        $this->assertStringContainsString('TaxSubtotal', $xml);
    }

    public function testContainsLegalMonetaryTotal(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('LegalMonetaryTotal', $xml);
    }

    public function testContainsInvoiceLine(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('InvoiceLine', $xml);
        $this->assertStringContainsString('Audit securite', $xml);
    }

    public function testHandlesDueDate(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $this->assertStringContainsString('2026-05-01', $xml);
    }

    public function testHandlesZeroVatRate(): void
    {
        $invoice = $this->createInvoice();
        $line = $invoice->getLines()->first();
        $line->setVatRate('0');
        $line->computeAmounts();
        $invoice->computeTotals();

        $xml = $this->generator->generate($invoice);

        $this->assertNotEmpty($xml);
        // Le code categorie Z doit apparaitre pour un taux a 0
        $this->assertStringContainsString('>Z<', $xml);
    }

    public function testOutputIsValidXmlDocument(): void
    {
        $invoice = $this->createInvoice();
        $xml = $this->generator->generate($invoice);

        $doc = new \DOMDocument();
        $loaded = $doc->loadXML($xml);

        $this->assertTrue($loaded, 'Le XML genere doit etre un document valide');
    }
}
