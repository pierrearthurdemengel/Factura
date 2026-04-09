<?php

namespace App\Tests\Unit\Service\Benchmark;

use App\Entity\AnonymizedBenchmark;
use App\Entity\Company;
use App\Service\Benchmark\BenchmarkService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class BenchmarkServiceTest extends TestCase
{
    private function createCompany(string $nafCode = '6201Z'): Company
    {
        $company = new Company();
        $company->setName('Dev SARL');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Code');
        $company->setPostalCode('75001');
        $company->setCity('Paris');
        $company->setNafCode($nafCode);

        return $company;
    }

    private function createBenchmark(string $sector, string $metric, string $value, int $contributors, string $period = '2026-03'): AnonymizedBenchmark
    {
        $b = new AnonymizedBenchmark();
        $b->setSector($sector);
        $b->setMetric($metric);
        $b->setValue($value);
        $b->setContributorCount($contributors);
        $b->setPeriod($period);

        return $b;
    }

    public function testGetBenchmarksForCompanyReturnsPublishableOnly(): void
    {
        $company = $this->createCompany();

        $benchmarks = [
            $this->createBenchmark('62', AnonymizedBenchmark::METRIC_AVG_INVOICE_AMOUNT, '1500.00', 10),
            $this->createBenchmark('62', AnonymizedBenchmark::METRIC_MEDIAN_PAYMENT_DELAY, '35', 3), // < 5, pas publiable
            $this->createBenchmark('62', AnonymizedBenchmark::METRIC_AVG_MONTHLY_REVENUE, '8500.00', 7),
        ];

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($benchmarks);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AnonymizedBenchmark::class)->willReturn($repo);

        $service = new BenchmarkService($em);
        $result = $service->getBenchmarksForCompany($company);

        $this->assertCount(2, $result);
        $this->assertSame('avg_invoice_amount', $result[0]['metric']);
        $this->assertSame('8500.00', $result[1]['value']);
    }

    public function testGetBenchmarksReturnsEmptyForCompanyWithoutNaf(): void
    {
        $company = new Company();
        $company->setName('Sans NAF');
        $company->setSiren('999999999');
        $company->setLegalForm('SAS');
        $company->setAddressLine1('Adresse');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new BenchmarkService($em);
        $result = $service->getBenchmarksForCompany($company);

        $this->assertSame([], $result);
    }

    public function testMinContributorsThreshold(): void
    {
        $this->assertSame(5, AnonymizedBenchmark::MIN_CONTRIBUTORS);
    }

    public function testBenchmarkIsPublishableAboveThreshold(): void
    {
        $b = $this->createBenchmark('62', 'avg_invoice_amount', '1000.00', 5);

        $this->assertTrue($b->isPublishable());
    }

    public function testBenchmarkIsNotPublishableBelowThreshold(): void
    {
        $b = $this->createBenchmark('62', 'avg_invoice_amount', '1000.00', 4);

        $this->assertFalse($b->isPublishable());
    }

    public function testSectorExtraction(): void
    {
        // Le service extrait les 2 premiers caracteres du code NAF
        $company = $this->createCompany('6201Z');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')
            ->with(
                $this->callback(fn (array $criteria) => '62' === $criteria['sector']),
                $this->anything(),
            )
            ->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(AnonymizedBenchmark::class)->willReturn($repo);

        $service = new BenchmarkService($em);
        $result = $service->getBenchmarksForCompany($company);

        $this->assertSame([], $result);
    }

    public function testMetricConstants(): void
    {
        $this->assertSame('avg_invoice_amount', AnonymizedBenchmark::METRIC_AVG_INVOICE_AMOUNT);
        $this->assertSame('median_payment_delay', AnonymizedBenchmark::METRIC_MEDIAN_PAYMENT_DELAY);
        $this->assertSame('avg_monthly_revenue', AnonymizedBenchmark::METRIC_AVG_MONTHLY_REVENUE);
        $this->assertSame('late_payment_rate', AnonymizedBenchmark::METRIC_LATE_PAYMENT_RATE);
        $this->assertSame('avg_client_count', AnonymizedBenchmark::METRIC_AVG_CLIENT_COUNT);
    }

    public function testCompareToSectorReturnsNullWithoutNaf(): void
    {
        $company = new Company();
        $company->setName('Sans NAF');
        $company->setSiren('999999999');
        $company->setLegalForm('SAS');
        $company->setAddressLine1('Adresse');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $em = $this->createMock(EntityManagerInterface::class);

        $service = new BenchmarkService($em);

        $this->assertNull($service->compareToSector($company, '2026-03'));
    }

    public function testBenchmarkEntitySettersAndGetters(): void
    {
        $b = new AnonymizedBenchmark();
        $b->setSector('62');
        $b->setMetric('avg_invoice_amount');
        $b->setPeriod('2026-03');
        $b->setValue('1500.50');
        $b->setContributorCount(12);

        $this->assertSame('62', $b->getSector());
        $this->assertSame('avg_invoice_amount', $b->getMetric());
        $this->assertSame('2026-03', $b->getPeriod());
        $this->assertSame('1500.50', $b->getValue());
        $this->assertSame(12, $b->getContributorCount());
        $this->assertNotNull($b->getId());
        $this->assertNotNull($b->getComputedAt());
    }
}
