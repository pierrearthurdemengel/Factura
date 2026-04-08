<?php

namespace App\Tests\Unit\Service\Quote;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Service\Quote\QuoteStateMachine;
use App\Service\Quote\QuoteToInvoiceConverter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class QuoteToInvoiceConverterTest extends TestCase
{
    /**
     * Verifie que la conversion copie toutes les donnees du devis vers la facture.
     */
    public function testConvertCopiesAllData(): void
    {
        $company = $this->createMock(Company::class);
        $client = $this->createMock(Client::class);

        $quote = new Quote();
        $quote->setStatus('ACCEPTED');
        $quote->setSeller($company);
        $quote->setBuyer($client);
        $quote->setCurrency('EUR');
        $quote->setLegalMention('TVA non applicable - art. 293B du CGI');

        $line = new QuoteLine();
        $line->setDescription('Prestation de conseil');
        $line->setQuantity('10.0000');
        $line->setUnit('HUR');
        $line->setUnitPriceExcludingTax('150.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $quote->addLine($line);
        $quote->computeTotals();

        $stateMachine = $this->createMock(QuoteStateMachine::class);
        $stateMachine->expects($this->once())
            ->method('apply')
            ->with($quote, 'convert');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(Invoice::class));
        $em->expects($this->once())->method('flush');

        $converter = new QuoteToInvoiceConverter($em, $stateMachine);
        $invoice = $converter->convert($quote);

        // Verifie que les donnees sont copiees
        $this->assertSame($company, $invoice->getSeller());
        $this->assertSame($client, $invoice->getBuyer());
        $this->assertSame('EUR', $invoice->getCurrency());
        $this->assertSame('TVA non applicable - art. 293B du CGI', $invoice->getLegalMention());
        $this->assertSame('DRAFT', $invoice->getStatus());
        $this->assertSame($quote, $invoice->getSourceQuote());

        // Verifie que les lignes sont copiees
        $this->assertCount(1, $invoice->getLines());
        $invoiceLine = $invoice->getLines()->first();
        $this->assertSame('Prestation de conseil', $invoiceLine->getDescription());
        $this->assertSame('10.0000', $invoiceLine->getQuantity());
        $this->assertSame('HUR', $invoiceLine->getUnit());
        $this->assertSame('150.0000', $invoiceLine->getUnitPriceExcludingTax());
        $this->assertSame('20', $invoiceLine->getVatRate());

        // Verifie les totaux
        $this->assertSame('1500.00', $invoice->getTotalExcludingTax());
        $this->assertSame('300.00', $invoice->getTotalTax());
        $this->assertSame('1800.00', $invoice->getTotalIncludingTax());
    }

    /**
     * Verifie qu'un devis non accepte ne peut pas etre converti.
     */
    public function testRejectsConversionOfNonAcceptedQuote(): void
    {
        $quote = new Quote();
        $quote->setStatus('DRAFT');

        $stateMachine = $this->createMock(QuoteStateMachine::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $converter = new QuoteToInvoiceConverter($em, $stateMachine);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Seul un devis accepte');
        $converter->convert($quote);
    }

    /**
     * Verifie que la conversion fonctionne avec plusieurs lignes et taux TVA.
     */
    public function testConvertWithMultipleLinesAndVatRates(): void
    {
        $company = $this->createMock(Company::class);
        $client = $this->createMock(Client::class);

        $quote = new Quote();
        $quote->setStatus('ACCEPTED');
        $quote->setSeller($company);
        $quote->setBuyer($client);

        // Ligne a 20%
        $line1 = new QuoteLine();
        $line1->setDescription('Developpement web');
        $line1->setQuantity('5.0000');
        $line1->setUnitPriceExcludingTax('500.0000');
        $line1->setVatRate('20');
        $line1->computeAmounts();
        $quote->addLine($line1);

        // Ligne a 5.5%
        $line2 = new QuoteLine();
        $line2->setDescription('Formation');
        $line2->setQuantity('2.0000');
        $line2->setUnitPriceExcludingTax('300.0000');
        $line2->setVatRate('5.5');
        $line2->computeAmounts();
        $quote->addLine($line2);

        $quote->computeTotals();

        $stateMachine = $this->createMock(QuoteStateMachine::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $converter = new QuoteToInvoiceConverter($em, $stateMachine);
        $invoice = $converter->convert($quote);

        $this->assertCount(2, $invoice->getLines());
        // 5*500 = 2500 HT, TVA 20% = 500 + 2*300 = 600 HT, TVA 5.5% = 33
        $this->assertSame('3100.00', $invoice->getTotalExcludingTax());
        $this->assertSame('533.00', $invoice->getTotalTax());
        $this->assertSame('3633.00', $invoice->getTotalIncludingTax());
    }

    /**
     * Verifie que le devis passe en statut CONVERTED et lie la facture.
     */
    public function testQuoteIsMarkedAsConverted(): void
    {
        $company = $this->createMock(Company::class);
        $client = $this->createMock(Client::class);

        $quote = new Quote();
        $quote->setStatus('ACCEPTED');
        $quote->setSeller($company);
        $quote->setBuyer($client);

        $line = new QuoteLine();
        $line->setDescription('Service');
        $line->setQuantity('1.0000');
        $line->setUnitPriceExcludingTax('100.0000');
        $line->setVatRate('20');
        $line->computeAmounts();
        $quote->addLine($line);
        $quote->computeTotals();

        $stateMachine = $this->createMock(QuoteStateMachine::class);
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $converter = new QuoteToInvoiceConverter($em, $stateMachine);
        $invoice = $converter->convert($quote);

        // Le devis doit pointer vers la facture creee
        $this->assertSame($invoice, $quote->getConvertedInvoice());
    }
}
