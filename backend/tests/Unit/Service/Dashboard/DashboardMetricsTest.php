<?php

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Dashboard\DashboardMetrics;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use App\Tests\Trait\ReflectionHelperTrait;
use PHPUnit\Framework\TestCase;

class DashboardMetricsTest extends TestCase
{
    use ReflectionHelperTrait;
    /**
     * Cree un service avec des factures mockees.
     *
     * @param Invoice[] $invoices
     */
    private function createMetrics(array $invoices = []): DashboardMetrics
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($invoices);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new DashboardMetrics($em);
    }

    /**
     * Cree une facture de test.
     */
    private function createInvoice(string $amountHt, string $status = 'PAID', ?string $clientName = null): Invoice
    {
        $company = new Company();
        $company->setName('Mon Entreprise');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $client = new Client();
        $client->setName($clientName ?? 'Client Test');
        $client->setEmail('client@test.fr');
        $client->setCompany($company);

        $invoice = new Invoice();
        $invoice->setSeller($company);
        $invoice->setBuyer($client);

        $this->setPrivateProperty($invoice, 'status', $status);

        $line = new InvoiceLine();
        $line->setDescription('Prestation');
        $line->setQuantity('1');
        $line->setUnitPriceExcludingTax($amountHt);
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testGetMetricsSingleInvoice(): void
    {
        $invoice = $this->createInvoice('1000.00', 'PAID');
        $metrics = $this->createMetrics([$invoice]);

        $result = $metrics->getMetrics(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        self::assertSame('1000.00', $result['turnover']);
        self::assertSame('200.00', $result['totalTax']);
        self::assertSame(1, $result['invoiceCount']);
        self::assertSame(1, $result['paidCount']);
        self::assertSame(0, $result['pendingCount']);
    }

    public function testGetMetricsMultipleStatuses(): void
    {
        $paid = $this->createInvoice('1000.00', 'PAID');
        $sent = $this->createInvoice('500.00', 'SENT');
        $ack = $this->createInvoice('300.00', 'ACKNOWLEDGED');

        $metrics = $this->createMetrics([$paid, $sent, $ack]);

        $result = $metrics->getMetrics(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        self::assertSame('1800.00', $result['turnover']);
        self::assertSame(3, $result['invoiceCount']);
        self::assertSame(1, $result['paidCount']);
        self::assertSame(2, $result['pendingCount']);
    }

    public function testGetMetricsEmpty(): void
    {
        $metrics = $this->createMetrics([]);

        $result = $metrics->getMetrics(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        self::assertSame('0.00', $result['turnover']);
        self::assertSame(0, $result['invoiceCount']);
        self::assertSame('0.00', $result['averageInvoiceAmount']);
    }

    public function testGetMetricsAverageAmount(): void
    {
        $inv1 = $this->createInvoice('1000.00', 'PAID');
        $inv2 = $this->createInvoice('2000.00', 'PAID');

        $metrics = $this->createMetrics([$inv1, $inv2]);

        $result = $metrics->getMetrics(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        // Moyenne = 3000 / 2 = 1500
        self::assertSame('1500.00', $result['averageInvoiceAmount']);
    }

    public function testGetTurnoverByClient(): void
    {
        $inv1 = $this->createInvoice('1000.00', 'PAID', 'Client A');
        $inv2 = $this->createInvoice('2000.00', 'PAID', 'Client B');
        $inv3 = $this->createInvoice('500.00', 'SENT', 'Client A');

        $metrics = $this->createMetrics([$inv1, $inv2, $inv3]);

        $result = $metrics->getTurnoverByClient(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
        );

        // Le resultat est trie par CA decroissant
        self::assertGreaterThanOrEqual(2, \count($result));
    }

    public function testGetTopClients(): void
    {
        $invoices = [];
        for ($i = 0; $i < 15; ++$i) {
            $invoices[] = $this->createInvoice((string) (100 * ($i + 1)) . '.00', 'PAID', 'Client ' . $i);
        }

        $metrics = $this->createMetrics($invoices);

        $result = $metrics->getTopClients(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-12-31'),
            5,
        );

        // Limite a 5 clients
        self::assertCount(5, $result);
    }

    public function testGetMonthlyTurnover(): void
    {
        // Les mocks retournent toujours les memes factures pour chaque mois
        $invoice = $this->createInvoice('500.00', 'PAID');
        $metrics = $this->createMetrics([$invoice]);

        $result = $metrics->getMonthlyTurnover(new Company(), 2026);

        self::assertCount(12, $result);
        self::assertSame(1, $result[0]['month']);
        self::assertSame(12, $result[11]['month']);
        self::assertSame(2026, $result[0]['year']);
    }
}
