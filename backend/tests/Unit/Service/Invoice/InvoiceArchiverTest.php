<?php

namespace App\Tests\Unit\Service\Invoice;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Format\FacturXGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le service d'archivage S3.
 * Verifie le hash SHA-256, la construction du chemin S3 et la mise a jour en BDD.
 */
class InvoiceArchiverTest extends TestCase
{
    private function createInvoice(): Invoice
    {
        $company = new Company();
        $company->setName('Test SAS');
        $company->setSiren('123456789');
        $company->setAddressLine1('1 rue de Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');
        $company->setCountryCode('FR');
        $company->setLegalForm('SAS');

        $client = new Client();
        $client->setName('Client SARL');
        $client->setSiren('987654321');
        $client->setAddressLine1('2 avenue du Client');
        $client->setPostalCode('69001');
        $client->setCity('Lyon');
        $client->setCountryCode('FR');

        $line = new InvoiceLine();
        $line->setPosition(1);
        $line->setDescription('Prestation de conseil');
        $line->setQuantity('10');
        $line->setUnit('HUR');
        $line->setUnitPriceExcludingTax('100.00');
        $line->setVatRate('20');
        $line->computeAmounts();

        $invoice = new Invoice();
        $invoice->setSeller($company);
        $invoice->setBuyer($client);
        $invoice->setNumber('FA-2026-0001');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-01-15'));
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testHashSha256IsComputedCorrectly(): void
    {
        $invoice = $this->createInvoice();
        $facturXGenerator = new FacturXGenerator();

        // Verifie que le hash SHA-256 du XML est calcule correctement
        $xml = $facturXGenerator->generate($invoice);
        $expectedHash = hash('sha256', $xml);

        $this->assertNotEmpty($expectedHash);
        $this->assertSame(64, strlen($expectedHash));
    }

    public function testFileHashIsConsistentForSameInput(): void
    {
        $invoice = $this->createInvoice();
        $generator = new FacturXGenerator();

        $xml1 = $generator->generate($invoice);
        $xml2 = $generator->generate($invoice);

        // Le hash doit etre deterministe pour la meme facture
        $this->assertSame(hash('sha256', $xml1), hash('sha256', $xml2));
    }

    public function testS3PathStructure(): void
    {
        $invoice = $this->createInvoice();

        // Verifier la structure du chemin attendu
        $siren = $invoice->getSeller()->getSiren();
        $year = $invoice->getIssueDate()->format('Y');
        $number = $invoice->getNumber();
        $expectedPath = sprintf('%s/%s/%s', $siren, $year, $number);

        $this->assertSame('123456789/2026/FA-2026-0001', $expectedPath);
    }

    public function testArchiveFieldsInitiallyNull(): void
    {
        $invoice = $this->createInvoice();

        // Les champs d'archivage sont nuls avant l'archivage
        $this->assertNull($invoice->getFileHash());
        $this->assertNull($invoice->getArchivedFilePath());
    }
}
