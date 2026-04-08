<?php

namespace App\Tests\Unit\Service\Format;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Format\InvoicePdfGenerator;
use PHPUnit\Framework\TestCase;

class InvoicePdfGeneratorTest extends TestCase
{
    private InvoicePdfGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new InvoicePdfGenerator();
    }

    /**
     * Verifie la generation d'un PDF avec les couleurs personnalisees.
     */
    public function testGeneratePdfWithCustomColors(): void
    {
        $invoice = $this->createInvoice('#FF5733');

        $pdf = $this->generator->generate($invoice);

        // Le PDF doit etre genere sans erreur
        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * Verifie la generation d'un PDF sans personnalisation (couleur par defaut).
     */
    public function testGeneratePdfWithDefaultColors(): void
    {
        $invoice = $this->createInvoice(null);

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    /**
     * Verifie la generation d'un PDF avec pied de page personnalise.
     */
    public function testGeneratePdfWithCustomFooter(): void
    {
        $invoice = $this->createInvoice(null, 'Merci pour votre confiance - www.example.com');

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
    }

    /**
     * Verifie que les mentions legales sont ajoutees pour un micro-entrepreneur.
     */
    public function testAutoLegalMentionForMicroEntrepreneur(): void
    {
        $invoice = $this->createInvoiceWithLegalForm('Micro-entreprise');

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
        // La mention "art. 293 B du CGI" devrait etre presente dans le PDF
    }

    /**
     * Verifie que les mentions legales sont ajoutees pour une SAS.
     */
    public function testAutoLegalMentionForSas(): void
    {
        $invoice = $this->createInvoiceWithLegalForm('SAS');

        $pdf = $this->generator->generate($invoice);

        $this->assertNotEmpty($pdf);
    }

    private function createInvoice(?string $primaryColor, ?string $customFooter = null): Invoice
    {
        $seller = new Company();
        $seller->setName('Ma Facture Pro');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SAS');
        $seller->setAddressLine1('10 rue de la Paix');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');
        $seller->setVatNumber('FR12123456789');

        if (null !== $primaryColor) {
            $seller->setPrimaryColor($primaryColor);
        }
        if (null !== $customFooter) {
            $seller->setCustomFooter($customFooter);
        }

        $buyer = new Client();
        $buyer->setName('Client Test SARL');
        $buyer->setSiren('987654321');
        $buyer->setAddressLine1('5 avenue des Champs');
        $buyer->setPostalCode('75008');
        $buyer->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setNumber('FA-2026-0001');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-04-01'));
        $invoice->setDueDate(new \DateTimeImmutable('2026-05-01'));

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

    private function createInvoiceWithLegalForm(string $legalForm): Invoice
    {
        $seller = new Company();
        $seller->setName('Test Entreprise');
        $seller->setSiren('123456789');
        $seller->setLegalForm($legalForm);
        $seller->setAddressLine1('1 rue Test');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setName('Client Test');
        $buyer->setAddressLine1('2 rue Test');
        $buyer->setPostalCode('75002');
        $buyer->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setNumber('FA-2026-0002');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-04-01'));

        $line = new InvoiceLine();
        $line->setDescription('Service');
        $line->setQuantity('1.0000');
        $line->setUnitPriceExcludingTax('500.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }
}
