<?php

namespace App\Tests\Unit\Service\Factoring;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\FactoringRequest;
use App\Entity\Invoice;
use App\Service\Factoring\ClientFinancingScorer;
use App\Service\Factoring\FactoringEligibilityChecker;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class FactoringEligibilityCheckerTest extends TestCase
{
    private ClientFinancingScorer&MockObject $scorer;
    private EntityManagerInterface&MockObject $em;
    private FactoringEligibilityChecker $checker;

    protected function setUp(): void
    {
        $this->scorer = $this->createMock(ClientFinancingScorer::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->checker = new FactoringEligibilityChecker(
            $this->scorer,
            $this->em,
            50000, // 500 EUR minimum
            50,    // score minimum
            100,   // 1% commission
        );
    }

    public function testEligibleWhenInvoiceIsSentAndScoreSufficient(): void
    {
        $invoice = $this->createInvoice('SENT', '1000.00');
        $this->scorer->method('calculateScore')->willReturn(80);
        $this->scorer->method('getFeePercentage')->willReturn(0.025);
        $this->mockNoExistingRequest();

        $result = $this->checker->check($invoice);

        self::assertTrue($result['eligible']);
        self::assertNull($result['reason']);
        self::assertSame(100000, $result['proposedAmount']); // 1000 EUR en centimes
        self::assertSame(2500, $result['estimatedFee']); // 2.5% de 1000 EUR
        self::assertSame(1000, $result['commission']); // 1% de 1000 EUR
        self::assertSame(96500, $result['netAmount']); // 1000 - 25 - 10 = 965 EUR
        self::assertSame(2, $result['estimatedPayoutDays']); // SENT = 48h
        self::assertSame(80, $result['clientScore']);
    }

    public function testEligibleWithAcknowledgedStatusGivesFasterPayout(): void
    {
        $invoice = $this->createInvoice('ACKNOWLEDGED', '2000.00');
        $this->scorer->method('calculateScore')->willReturn(90);
        $this->scorer->method('getFeePercentage')->willReturn(0.02);
        $this->mockNoExistingRequest();

        $result = $this->checker->check($invoice);

        self::assertTrue($result['eligible']);
        self::assertSame(1, $result['estimatedPayoutDays']); // ACKNOWLEDGED = 24h
    }

    public function testNotEligibleWhenInvoiceIsDraft(): void
    {
        $invoice = $this->createInvoice('DRAFT', '1000.00');
        $this->scorer->method('calculateScore')->willReturn(80);

        $result = $this->checker->check($invoice);

        self::assertFalse($result['eligible']);
        self::assertStringContainsString('SENT ou ACKNOWLEDGED', $result['reason'] ?? '');
    }

    public function testNotEligibleWhenInvoiceIsPaid(): void
    {
        $invoice = $this->createInvoice('PAID', '1000.00');
        $this->scorer->method('calculateScore')->willReturn(80);

        $result = $this->checker->check($invoice);

        self::assertFalse($result['eligible']);
    }

    public function testNotEligibleWhenAmountBelowMinimum(): void
    {
        $invoice = $this->createInvoice('SENT', '400.00'); // < 500 EUR
        $this->scorer->method('calculateScore')->willReturn(80);

        $result = $this->checker->check($invoice);

        self::assertFalse($result['eligible']);
        self::assertStringContainsString('seuil minimum', $result['reason'] ?? '');
    }

    public function testNotEligibleWhenClientScoreTooLow(): void
    {
        $invoice = $this->createInvoice('SENT', '1000.00');
        $this->scorer->method('calculateScore')->willReturn(40); // < 50

        $result = $this->checker->check($invoice);

        self::assertFalse($result['eligible']);
        self::assertStringContainsString('score du client', $result['reason'] ?? '');
    }

    public function testNotEligibleWhenPendingRequestExists(): void
    {
        $invoice = $this->createInvoice('SENT', '1000.00');
        $this->scorer->method('calculateScore')->willReturn(80);

        // Simuler une demande active existante
        $existingRequest = new FactoringRequest();
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn($existingRequest);
        $this->em->method('getRepository')
            ->with(FactoringRequest::class)
            ->willReturn($repository);

        $result = $this->checker->check($invoice);

        self::assertFalse($result['eligible']);
        self::assertStringContainsString('deja en cours', $result['reason'] ?? '');
    }

    public function testFeeCalculationBasedOnScore(): void
    {
        $invoice = $this->createInvoice('SENT', '10000.00'); // 10 000 EUR
        $this->scorer->method('calculateScore')->willReturn(95);
        $this->scorer->method('getFeePercentage')->willReturn(0.02); // 2%
        $this->mockNoExistingRequest();

        $result = $this->checker->check($invoice);

        self::assertTrue($result['eligible']);
        self::assertSame(1000000, $result['proposedAmount']); // 10 000 EUR
        self::assertSame(20000, $result['estimatedFee']); // 2% = 200 EUR
        self::assertSame(10000, $result['commission']); // 1% = 100 EUR
    }

    private function createInvoice(string $status, string $amount): Invoice
    {
        $company = new Company();
        $client = new Client();

        $nameRef = new \ReflectionProperty(Client::class, 'name');
        $nameRef->setValue($client, 'Client Test');

        $companyRef = new \ReflectionProperty(Client::class, 'company');
        $companyRef->setValue($client, $company);

        $invoice = new Invoice();
        $invoice->setStatus($status);
        $invoice->setTotalIncludingTax($amount);
        $invoice->setSeller($company);
        $invoice->setBuyer($client);
        $invoice->setIssueDate(new \DateTimeImmutable('-10 days'));
        $invoice->setDueDate(new \DateTimeImmutable('+20 days'));

        return $invoice;
    }

    private function mockNoExistingRequest(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')
            ->with(FactoringRequest::class)
            ->willReturn($repository);
    }
}
