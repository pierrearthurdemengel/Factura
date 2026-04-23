<?php

namespace App\Tests\Unit\Service\Tax;

use App\Entity\AccountingEntry;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Tax\VatCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use App\Tests\Trait\ReflectionHelperTrait;
use PHPUnit\Framework\TestCase;

class VatCalculatorTest extends TestCase
{
    use ReflectionHelperTrait;
    /**
     * Cree un mock du VatCalculator qui retourne les factures et ecritures fournies.
     *
     * @param Invoice[]         $invoices
     * @param AccountingEntry[] $entries
     */
    private function createCalculator(array $invoices = [], array $entries = []): VatCalculator
    {
        $callCount = 0;
        $allResults = [$invoices, $entries];

        $query = $this->createMock(Query::class);
        $query->method('getResult')
            ->willReturnCallback(function () use (&$callCount, $allResults) {
                return $allResults[$callCount++] ?? [];
            });

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new VatCalculator($em);
    }

    /**
     * Cree une facture avec des lignes de test.
     *
     * @param array<int, array{description: string, quantity: string, unitPrice: string, vatRate: string}> $lines
     */
    private function createInvoice(array $lines, string $status = 'SENT'): Invoice
    {
        $company = new Company();
        $company->setName('Test SARL');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue de Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($company);

        // Utiliser la reflexion pour setter le statut
        $this->setPrivateProperty($invoice, 'status', $status);

        foreach ($lines as $lineData) {
            $line = new InvoiceLine();
            $line->setDescription($lineData['description']);
            $line->setQuantity($lineData['quantity']);
            $line->setUnitPriceExcludingTax($lineData['unitPrice']);
            $line->setVatRate($lineData['vatRate']);
            $line->computeAmounts();
            $invoice->addLine($line);
        }

        $invoice->computeTotals();

        return $invoice;
    }

    public function testCalculateCollectedVatSingleRate(): void
    {
        $invoice = $this->createInvoice([
            ['description' => 'Prestation', 'quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
        ]);

        $calculator = $this->createCalculator([$invoice]);

        $result = $calculator->calculateCollectedVat(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('200.00', $result['total']);
        self::assertArrayHasKey('20', $result['byRate']);
        self::assertSame('1000.00', $result['byRate']['20']['base']);
        self::assertSame('200.00', $result['byRate']['20']['vat']);
        self::assertSame(1, $result['invoiceCount']);
    }

    public function testCalculateCollectedVatMultipleRates(): void
    {
        $invoice = $this->createInvoice([
            ['description' => 'Prestation', 'quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
            ['description' => 'Livraison', 'quantity' => '1', 'unitPrice' => '500.00', 'vatRate' => '5.5'],
        ]);

        $calculator = $this->createCalculator([$invoice]);

        $result = $calculator->calculateCollectedVat(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        // TVA 20% sur 1000 = 200, TVA 5.5% sur 500 = 27.50
        self::assertSame('227.50', $result['total']);
        self::assertArrayHasKey('20', $result['byRate']);
        self::assertArrayHasKey('5.5', $result['byRate']);
        self::assertSame('200.00', $result['byRate']['20']['vat']);
        self::assertSame('27.50', $result['byRate']['5.5']['vat']);
    }

    public function testCalculateCollectedVatZeroRate(): void
    {
        $invoice = $this->createInvoice([
            ['description' => 'Formation exoneree', 'quantity' => '1', 'unitPrice' => '2000.00', 'vatRate' => '0'],
        ]);

        $calculator = $this->createCalculator([$invoice]);

        $result = $calculator->calculateCollectedVat(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        self::assertSame('0.00', $result['total']);
        self::assertArrayHasKey('0', $result['byRate']);
        self::assertSame('2000.00', $result['byRate']['0']['base']);
    }

    public function testCalculateCollectedVatMultipleInvoices(): void
    {
        $invoice1 = $this->createInvoice([
            ['description' => 'Prestation A', 'quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
        ]);
        $invoice2 = $this->createInvoice([
            ['description' => 'Prestation B', 'quantity' => '2', 'unitPrice' => '500.00', 'vatRate' => '20'],
        ]);

        $calculator = $this->createCalculator([$invoice1, $invoice2]);

        $result = $calculator->calculateCollectedVat(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        // 200 + 200 = 400
        self::assertSame('400.00', $result['total']);
        self::assertSame('2000.00', $result['byRate']['20']['base']);
        self::assertSame(2, $result['invoiceCount']);
    }

    public function testCalculateCollectedVatEmptyPeriod(): void
    {
        $calculator = $this->createCalculator([]);

        $result = $calculator->calculateCollectedVat(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('0.00', $result['total']);
        self::assertEmpty($result['byRate']);
        self::assertSame(0, $result['invoiceCount']);
    }

    public function testCalculateDeductibleVat(): void
    {
        $entry1 = new AccountingEntry();
        $entry1->setAmount('150.00');

        $entry2 = new AccountingEntry();
        $entry2->setAmount('75.00');

        // calculateDeductibleVat fait un seul appel : les ecritures en premier resultat
        $calculator = $this->createCalculator([$entry1, $entry2]);

        $result = $calculator->calculateDeductibleVat(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('225.00', $result['total']);
        self::assertSame(2, $result['entryCount']);
    }

    public function testCalculateVatBalanceDue(): void
    {
        // Facture avec TVA collectee de 200
        $invoice = $this->createInvoice([
            ['description' => 'Prestation', 'quantity' => '1', 'unitPrice' => '1000.00', 'vatRate' => '20'],
        ]);

        // Ecriture deductible de 50
        $entry = new AccountingEntry();
        $entry->setAmount('50.00');

        // Le VatCalculator fait deux appels : un pour collectee (factures), un pour deductible (ecritures)
        // Mais calculateVatBalance appelle calculateCollectedVat et calculateDeductibleVat
        // Chacun cree son propre QueryBuilder, donc on ne peut pas simplement mock 2 resultats sequentiels
        // On doit creer un mock plus sophistique

        $callCount = 0;
        $allResults = [[$invoice], [$entry]];

        $query = $this->createMock(Query::class);
        $query->method('getResult')
            ->willReturnCallback(function () use (&$callCount, $allResults) {
                return $allResults[$callCount++] ?? [];
            });

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $calculator = new VatCalculator($em);

        $result = $calculator->calculateVatBalance(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('200.00', $result['collected']);
        self::assertSame('50.00', $result['deductible']);
        self::assertSame('150.00', $result['balance']);
        self::assertTrue($result['isDue']);
    }

    public function testCalculateVatBalanceCredit(): void
    {
        // Pas de factures emises
        $entry = new AccountingEntry();
        $entry->setAmount('300.00');

        $callCount = 0;
        $allResults = [[], [$entry]];

        $query = $this->createMock(Query::class);
        $query->method('getResult')
            ->willReturnCallback(function () use (&$callCount, $allResults) {
                return $allResults[$callCount++] ?? [];
            });

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        $calculator = new VatCalculator($em);

        $result = $calculator->calculateVatBalance(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('0.00', $result['collected']);
        self::assertSame('300.00', $result['deductible']);
        self::assertSame('-300.00', $result['balance']);
        self::assertFalse($result['isDue']);
    }
}
