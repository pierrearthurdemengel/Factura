<?php

namespace App\Tests\Unit\Service\Accounting;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Accounting\InvoiceToAccountingMapper;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InvoiceToAccountingMapperTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private InvoiceToAccountingMapper $mapper;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->mapper = new InvoiceToAccountingMapper($this->em);
    }

    public function testMapSingleLineSingleVatRate(): void
    {
        // TTC = 1000 HT + 200 TVA = 1200 TTC
        $invoice = $this->createInvoice('1200.00', [
            ['quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
        ]);

        $this->em->expects(self::exactly(3))->method('persist'); // Client + Revenue + TVA
        $this->em->expects(self::once())->method('flush');

        $entries = $this->mapper->map($invoice);

        self::assertCount(3, $entries);

        // Ecriture client (TTC)
        self::assertSame('411000', $entries[0]->getDebitAccount());
        self::assertSame('1200.00', $entries[0]->getAmount()); // 1000 + 200 TVA

        // Ecriture chiffre d'affaires (HT)
        self::assertSame('706000', $entries[1]->getDebitAccount());
        self::assertSame('1000.00', $entries[1]->getAmount());

        // Ecriture TVA collectee
        self::assertSame('445710', $entries[2]->getDebitAccount());
        self::assertSame('200.00', $entries[2]->getAmount());
    }

    public function testMapMultipleVatRates(): void
    {
        $invoice = $this->createInvoice('1155.00', [
            ['quantity' => '1', 'unitPrice' => '500.00', 'vatRate' => '20'],
            ['quantity' => '1', 'unitPrice' => '500.00', 'vatRate' => '5.5'],
        ]);

        $entries = $this->mapper->map($invoice);

        // 1 client + 2 revenue + 2 TVA = 5
        self::assertCount(5, $entries);

        // Les ecritures sont dans le journal des ventes
        foreach ($entries as $entry) {
            self::assertSame('VE', $entry->getJournalCode());
        }
    }

    public function testMapZeroVatRate(): void
    {
        $invoice = $this->createInvoice('500.00', [
            ['quantity' => '1', 'unitPrice' => '500.00', 'vatRate' => '0'],
        ]);

        $entries = $this->mapper->map($invoice);

        // 1 client + 1 revenue (pas de TVA pour taux 0)
        self::assertCount(2, $entries);
    }

    public function testEntriesContainInvoiceReference(): void
    {
        $invoice = $this->createInvoice('1200.00', [
            ['quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
        ]);
        $invoice->setNumber('FA-2026-0042');

        $entries = $this->mapper->map($invoice);

        foreach ($entries as $entry) {
            self::assertSame('FA-2026-0042', $entry->getPieceReference());
            self::assertSame('invoice', $entry->getSourceType());
            self::assertStringContainsString('FA-2026-0042', $entry->getLabel());
        }
    }

    public function testEntriesContainBuyerName(): void
    {
        $invoice = $this->createInvoice('1200.00', [
            ['quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
        ]);

        $entries = $this->mapper->map($invoice);

        self::assertStringContainsString('Client Test', $entries[0]->getLabel());
    }

    /**
     * @param array<int, array{quantity: string, unitPrice: string, vatRate: string}> $lineData
     */
    private function createInvoice(string $totalTtc, array $lineData): Invoice
    {
        $company = new Company();
        $client = new Client();

        $nameRef = new \ReflectionProperty(Client::class, 'name');
        $nameRef->setValue($client, 'Client Test');

        $companyRef = new \ReflectionProperty(Client::class, 'company');
        $companyRef->setValue($client, $company);

        $invoice = new Invoice();
        $invoice->setStatus('SENT');
        $invoice->setTotalIncludingTax($totalTtc);
        $invoice->setSeller($company);
        $invoice->setBuyer($client);
        $invoice->setIssueDate(new \DateTimeImmutable('2026-04-08'));

        foreach ($lineData as $data) {
            $line = new InvoiceLine();
            $line->setDescription('Prestation');
            $line->setQuantity($data['quantity']);
            $line->setUnitPriceExcludingTax($data['unitPrice']);
            $line->setVatRate($data['vatRate']);
            $invoice->addLine($line);
        }

        return $invoice;
    }
}
