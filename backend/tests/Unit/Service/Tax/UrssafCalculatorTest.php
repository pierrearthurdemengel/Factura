<?php

namespace App\Tests\Unit\Service\Tax;

use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Tax\UrssafCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use App\Tests\Trait\ReflectionHelperTrait;
use PHPUnit\Framework\TestCase;

class UrssafCalculatorTest extends TestCase
{
    use ReflectionHelperTrait;
    /**
     * Cree un calculator avec des factures mockees.
     *
     * @param Invoice[] $invoices
     */
    private function createCalculator(array $invoices = []): UrssafCalculator
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

        return new UrssafCalculator($em);
    }

    /**
     * Cree une facture payee pour les tests.
     */
    private function createPaidInvoice(string $amountHt): Invoice
    {
        $company = new Company();
        $company->setName('Test Auto');
        $company->setSiren('123456789');
        $company->setLegalForm('EI');
        $company->setAddressLine1('1 rue de Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($company);

        // Setter le statut PAID via reflexion
        $this->setPrivateProperty($invoice, 'status', 'PAID');

        $line = new InvoiceLine();
        $line->setDescription('Prestation');
        $line->setQuantity('1');
        $line->setUnitPriceExcludingTax($amountHt);
        $line->setVatRate('0'); // Auto-entrepreneur en franchise TVA
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testCalculateTurnover(): void
    {
        $invoice1 = $this->createPaidInvoice('1500.00');
        $invoice2 = $this->createPaidInvoice('2500.00');

        $calculator = $this->createCalculator([$invoice1, $invoice2]);

        $turnover = $calculator->calculateTurnover(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('4000.00', $turnover);
    }

    public function testCalculateTurnoverEmpty(): void
    {
        $calculator = $this->createCalculator([]);

        $turnover = $calculator->calculateTurnover(
            new Company(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('0.00', $turnover);
    }

    public function testCalculateContributionsBncLiberal(): void
    {
        $invoice = $this->createPaidInvoice('5000.00');

        $calculator = $this->createCalculator([$invoice]);

        $result = $calculator->calculateContributions(
            new Company(),
            UrssafCalculator::ACTIVITY_BNC_LIBERAL,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        // 5000 * 21.1% = 1055.00
        self::assertSame('5000.00', $result['turnover']);
        self::assertSame('21.1', $result['rate']);
        self::assertSame('1055.00', $result['contributions']);
        self::assertSame(UrssafCalculator::ACTIVITY_BNC_LIBERAL, $result['activityType']);
    }

    public function testCalculateContributionsBicSale(): void
    {
        $invoice = $this->createPaidInvoice('10000.00');

        $calculator = $this->createCalculator([$invoice]);

        $result = $calculator->calculateContributions(
            new Company(),
            UrssafCalculator::ACTIVITY_BIC_SALE,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        // 10000 * 12.3% = 1230.00
        self::assertSame('1230.00', $result['contributions']);
        self::assertSame('12.3', $result['rate']);
    }

    public function testCalculateContributionsBicService(): void
    {
        $invoice = $this->createPaidInvoice('3000.00');

        $calculator = $this->createCalculator([$invoice]);

        $result = $calculator->calculateContributions(
            new Company(),
            UrssafCalculator::ACTIVITY_BIC_SERVICE,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        // 3000 * 21.2% = 636.00
        self::assertSame('636.00', $result['contributions']);
        self::assertSame('21.2', $result['rate']);
    }

    public function testCalculateContributionsInvalidActivity(): void
    {
        $calculator = $this->createCalculator([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Type d\'activite inconnu/');

        $calculator->calculateContributions(
            new Company(),
            'invalid_type',
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );
    }

    public function testGetDeclarationDeadlinesMonthly(): void
    {
        $calculator = $this->createCalculator([]);

        $deadlines = $calculator->getDeclarationDeadlines(2026, UrssafCalculator::FREQUENCY_MONTHLY);

        self::assertCount(12, $deadlines);

        // Janvier : echeance fin fevrier
        self::assertSame('2026-01-01', $deadlines[0]['start']);
        self::assertSame('2026-01-31', $deadlines[0]['end']);
        self::assertSame('2026-02-28', $deadlines[0]['deadline']);

        // Decembre : echeance fin janvier suivant
        self::assertSame('2026-12-01', $deadlines[11]['start']);
        self::assertSame('2026-12-31', $deadlines[11]['end']);
        self::assertSame('2027-01-31', $deadlines[11]['deadline']);
    }

    public function testGetDeclarationDeadlinesQuarterly(): void
    {
        $calculator = $this->createCalculator([]);

        $deadlines = $calculator->getDeclarationDeadlines(2026, UrssafCalculator::FREQUENCY_QUARTERLY);

        self::assertCount(4, $deadlines);

        // T1 : echeance fin avril
        self::assertSame('2026-01-01', $deadlines[0]['start']);
        self::assertSame('2026-03-31', $deadlines[0]['end']);
        self::assertSame('2026-04-30', $deadlines[0]['deadline']);

        // T4 : echeance fin janvier suivant
        self::assertSame('2026-10-01', $deadlines[3]['start']);
        self::assertSame('2026-12-31', $deadlines[3]['end']);
        self::assertSame('2027-01-31', $deadlines[3]['deadline']);
    }

    public function testGetRate(): void
    {
        $calculator = $this->createCalculator([]);

        self::assertSame('12.3', $calculator->getRate(UrssafCalculator::ACTIVITY_BIC_SALE));
        self::assertSame('21.2', $calculator->getRate(UrssafCalculator::ACTIVITY_BIC_SERVICE));
        self::assertSame('21.1', $calculator->getRate(UrssafCalculator::ACTIVITY_BNC_LIBERAL));
        self::assertSame('21.2', $calculator->getRate(UrssafCalculator::ACTIVITY_BNC_CIPAV));
    }

    public function testGetActivityTypes(): void
    {
        $types = UrssafCalculator::getActivityTypes();

        self::assertCount(4, $types);
        self::assertArrayHasKey(UrssafCalculator::ACTIVITY_BIC_SALE, $types);
        self::assertArrayHasKey(UrssafCalculator::ACTIVITY_BIC_SERVICE, $types);
        self::assertArrayHasKey(UrssafCalculator::ACTIVITY_BNC_LIBERAL, $types);
        self::assertArrayHasKey(UrssafCalculator::ACTIVITY_BNC_CIPAV, $types);
    }

    public function testContributionsIncludePeriodLabel(): void
    {
        $calculator = $this->createCalculator([]);

        $result = $calculator->calculateContributions(
            new Company(),
            UrssafCalculator::ACTIVITY_BNC_LIBERAL,
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-06-30'),
        );

        self::assertSame('01/04/2026 au 30/06/2026', $result['periodLabel']);
    }

    public function testContributionsIncludeAnnualCeiling(): void
    {
        $calculator = $this->createCalculator([]);

        $result = $calculator->calculateContributions(
            new Company(),
            UrssafCalculator::ACTIVITY_BIC_SALE,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame(UrssafCalculator::CEILING_BIC_SALE, $result['annualCeiling']);
    }
}
