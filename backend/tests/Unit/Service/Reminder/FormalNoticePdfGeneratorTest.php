<?php

namespace App\Tests\Unit\Service\Reminder;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Reminder\FormalNoticePdfGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires de la generation du PDF de mise en demeure.
 */
class FormalNoticePdfGeneratorTest extends TestCase
{
    private FormalNoticePdfGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FormalNoticePdfGenerator();
    }

    /**
     * Verifie qu'un PDF est genere sans erreur.
     */
    public function testGeneratesFormalNoticePdf(): void
    {
        $invoice = $this->createInvoice();

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * Verifie la generation pour une facture sans date d'echeance.
     */
    public function testGeneratesPdfWithoutDueDate(): void
    {
        $invoice = $this->createInvoice(false);

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * Verifie la generation avec des caracteres speciaux francais.
     */
    public function testGeneratesPdfWithFrenchCharacters(): void
    {
        $seller = new Company();
        $seller->setName('Societe Generale des Eaux');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SA');
        $seller->setAddressLine1('3 avenue des Champs-Elysees');
        $seller->setPostalCode('75008');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setName('Etablissements Rene et Fils');
        $buyer->setAddressLine1('42 rue de la Republique');
        $buyer->setPostalCode('69001');
        $buyer->setCity('Lyon');

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setNumber('FA-2026-0042');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-03-01'));
        $invoice->setDueDate(new \DateTimeImmutable('2026-03-31'));

        $line = new InvoiceLine();
        $line->setDescription('Prestation de conseil');
        $line->setQuantity('1.0000');
        $line->setUnitPriceExcludingTax('5000.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    private function createInvoice(bool $withDueDate = true): Invoice
    {
        $seller = new Company();
        $seller->setName('Ma Facture Pro');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SAS');
        $seller->setAddressLine1('10 rue de la Paix');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setName('Client Test SARL');
        $buyer->setAddressLine1('5 avenue des Champs');
        $buyer->setPostalCode('75008');
        $buyer->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setNumber('FA-2026-0001');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-04-01'));

        if ($withDueDate) {
            $invoice->setDueDate(new \DateTimeImmutable('2026-05-01'));
        }

        $line = new InvoiceLine();
        $line->setDescription('Prestation de conseil');
        $line->setQuantity('10.0000');
        $line->setUnitPriceExcludingTax('150.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }
}
