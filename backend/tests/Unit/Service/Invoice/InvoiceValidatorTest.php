<?php

namespace App\Tests\Unit\Service\Invoice;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Exception\InvoiceValidationException;
use App\Service\Invoice\InvoiceValidator;
use PHPUnit\Framework\TestCase;

class InvoiceValidatorTest extends TestCase
{
    private InvoiceValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new InvoiceValidator();
    }

    /**
     * Cree une facture valide complete pour les tests.
     */
    private function createValidInvoice(): Invoice
    {
        $seller = new Company();
        $seller->setName('Ma Facture Pro');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SAS');
        $seller->setAddressLine1('10 rue de la Paix');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setCompany($seller);
        $buyer->setName('Client SARL');
        $buyer->setAddressLine1('20 avenue des Champs');
        $buyer->setPostalCode('75008');
        $buyer->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setCurrency('EUR');

        $line = new InvoiceLine();
        $line->setDescription('Developpement web');
        $line->setQuantity('10');
        $line->setUnitPriceExcludingTax('100.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testValidInvoiceHasNoErrors(): void
    {
        $invoice = $this->createValidInvoice();
        $errors = $this->validator->validate($invoice);

        $this->assertCount(0, $errors);
    }

    public function testAssertValidDoesNotThrowOnValidInvoice(): void
    {
        $invoice = $this->createValidInvoice();
        $this->validator->assertValid($invoice);
        $this->assertTrue(true);
    }

    public function testRejectsInvoiceWithMissingSellerName(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getSeller()->setName('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('raison sociale du vendeur', $errors[0]);
    }

    public function testRejectsInvoiceWithMissingSiren(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getSeller()->setSiren('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('SIREN', $errors[0]);
    }

    public function testRejectsInvoiceWithMissingSellerAddress(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getSeller()->setAddressLine1('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('adresse du vendeur', $errors[0]);
    }

    public function testRejectsInvoiceWithMissingSellerLegalForm(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getSeller()->setLegalForm('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('forme juridique', $errors[0]);
    }

    public function testRejectsInvoiceWithMissingBuyerName(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getBuyer()->setName('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('raison sociale', $errors[0]);
    }

    public function testRejectsInvoiceWithMissingBuyerAddress(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getBuyer()->setAddressLine1('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
    }

    public function testRejectsInvoiceWithNonEurCurrency(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->setCurrency('USD');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('EUR', $errors[0]);
    }

    public function testRejectsInvoiceWithNoLines(): void
    {
        $invoice = $this->createValidInvoice();
        foreach ($invoice->getLines() as $line) {
            $invoice->removeLine($line);
        }

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('au moins une ligne', $errors[0]);
    }

    public function testRejectsLineWithEmptyDescription(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getLines()->first()->setDescription('');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('designation', $errors[0]);
    }

    public function testRejectsLineWithZeroQuantity(): void
    {
        $invoice = $this->createValidInvoice();
        $line = $invoice->getLines()->first();
        $line->setQuantity('0');
        $line->computeAmounts();
        $invoice->computeTotals();

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('quantite', $errors[0]);
    }

    public function testRejectsInconsistentTotals(): void
    {
        $invoice = $this->createValidInvoice();
        // Fausser le total HT
        $invoice->setTotalExcludingTax('999.99');

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('total HT', $errors[0]);
    }

    public function testRequiresLegalMentionForExemptLines(): void
    {
        $invoice = $this->createValidInvoice();
        $line = $invoice->getLines()->first();
        $line->setVatRate('E');
        $line->computeAmounts();
        $invoice->computeTotals();

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('exoneration', $errors[0]);
    }

    public function testRequiresAutoliquidationMentionForAeLines(): void
    {
        $invoice = $this->createValidInvoice();
        $line = $invoice->getLines()->first();
        $line->setVatRate('AE');
        $line->computeAmounts();
        $invoice->computeTotals();

        $errors = $this->validator->validate($invoice);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('Autoliquidation', $errors[0]);
    }

    public function testAcceptsAutoliquidationWithCorrectMention(): void
    {
        $invoice = $this->createValidInvoice();
        $line = $invoice->getLines()->first();
        $line->setVatRate('AE');
        $line->computeAmounts();
        $invoice->computeTotals();
        $invoice->setLegalMention('TVA en autoliquidation - art. 283 du CGI');

        $errors = $this->validator->validate($invoice);
        // Aucune erreur liee a l'autoliquidation ne doit rester
        $autoliqErrors = array_filter($errors, fn (string $e) => str_contains($e, 'Autoliquidation'));
        $this->assertCount(0, $autoliqErrors, 'La mention autoliquidation devrait etre acceptee');
    }

    public function testAssertValidThrowsOnInvalidInvoice(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getSeller()->setName('');

        $this->expectException(InvoiceValidationException::class);
        $this->validator->assertValid($invoice);
    }

    public function testExceptionContainsAllErrors(): void
    {
        $invoice = $this->createValidInvoice();
        $invoice->getSeller()->setName('');
        $invoice->getSeller()->setSiren('');

        try {
            $this->validator->assertValid($invoice);
            $this->fail('Exception attendue');
        } catch (InvoiceValidationException $e) {
            $this->assertCount(2, $e->getErrors());
        }
    }
}
