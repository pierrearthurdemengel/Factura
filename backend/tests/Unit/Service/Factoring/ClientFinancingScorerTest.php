<?php

namespace App\Tests\Unit\Service\Factoring;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Service\Factoring\ClientFinancingScorer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ClientFinancingScorerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private ClientFinancingScorer $scorer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->scorer = new ClientFinancingScorer($this->em);
    }

    public function testScoreBaselineForClientWithNoInvoices(): void
    {
        $client = $this->createClient();
        $this->mockClientInvoices($client, []);

        $score = $this->scorer->calculateScore($client);

        self::assertSame(50, $score);
    }

    public function testScoreIncreasesWithPaidInvoices(): void
    {
        $client = $this->createClient();
        $invoices = [];

        // 5 factures payees
        for ($i = 0; $i < 5; ++$i) {
            $invoices[] = $this->createInvoice('PAID', '1000.00', 15);
        }

        $this->mockClientInvoices($client, $invoices);

        $score = $this->scorer->calculateScore($client);

        // Base 50 + historique paiement (40) + delai (5-10) + stabilite (10) = >80
        self::assertGreaterThan(80, $score);
    }

    public function testScoreCappedAt70ForFewInvoices(): void
    {
        $client = $this->createClient();

        // 2 factures payees uniquement
        $invoices = [
            $this->createInvoice('PAID', '1000.00', 10),
            $this->createInvoice('PAID', '1200.00', 10),
        ];

        $this->mockClientInvoices($client, $invoices);

        $score = $this->scorer->calculateScore($client);

        // Plafonne a 70 avec 2 factures ou moins
        self::assertLessThanOrEqual(70, $score);
    }

    public function testScorePenalizedForOverdueInvoices(): void
    {
        $client = $this->createClient();

        // Facture non payee avec echeance depassee de 45 jours
        $overdue = $this->createInvoice('SENT', '2000.00');
        $overdue->setDueDate(new \DateTimeImmutable('-45 days'));

        $paid = $this->createInvoice('PAID', '1000.00', 15);

        $this->mockClientInvoices($client, [$overdue, $paid, $paid, $paid, $paid]);

        $score = $this->scorer->calculateScore($client);

        // Penalite de 20 pour facture > 30 jours en retard
        self::assertLessThan(80, $score);
    }

    public function testScoreSeverelyPenalizedForVeryOverdueInvoices(): void
    {
        $client = $this->createClient();

        // Facture non payee avec echeance depassee de 90 jours
        $overdue = $this->createInvoice('SENT', '5000.00');
        $overdue->setDueDate(new \DateTimeImmutable('-90 days'));

        $this->mockClientInvoices($client, [$overdue]);

        $score = $this->scorer->calculateScore($client);

        // Score tres bas pour facture > 60 jours en retard
        self::assertSame(0, $score);
    }

    public function testFeePercentageForHighScore(): void
    {
        self::assertSame(0.02, $this->scorer->getFeePercentage(95));
        self::assertSame(0.02, $this->scorer->getFeePercentage(90));
    }

    public function testFeePercentageForMediumScore(): void
    {
        self::assertSame(0.025, $this->scorer->getFeePercentage(85));
        self::assertSame(0.03, $this->scorer->getFeePercentage(75));
        self::assertSame(0.035, $this->scorer->getFeePercentage(65));
    }

    public function testFeePercentageForLowScore(): void
    {
        self::assertSame(0.05, $this->scorer->getFeePercentage(50));
        self::assertSame(0.05, $this->scorer->getFeePercentage(55));
    }

    public function testFeePercentageForIneligibleScore(): void
    {
        self::assertSame(0.0, $this->scorer->getFeePercentage(49));
        self::assertSame(0.0, $this->scorer->getFeePercentage(0));
    }

    public function testScoreBetween0And100(): void
    {
        $client = $this->createClient();

        // Beaucoup de factures payees
        $invoices = [];
        for ($i = 0; $i < 20; ++$i) {
            $invoices[] = $this->createInvoice('PAID', '1000.00', 10);
        }

        $this->mockClientInvoices($client, $invoices);

        $score = $this->scorer->calculateScore($client);

        self::assertGreaterThanOrEqual(0, $score);
        self::assertLessThanOrEqual(100, $score);
    }

    public function testStabilityScoreForVariableAmounts(): void
    {
        $client = $this->createClient();

        // Montants tres variables
        $invoices = [
            $this->createInvoice('PAID', '100.00', 15),
            $this->createInvoice('PAID', '5000.00', 15),
            $this->createInvoice('PAID', '200.00', 15),
            $this->createInvoice('PAID', '8000.00', 15),
            $this->createInvoice('PAID', '50.00', 15),
        ];

        $this->mockClientInvoices($client, $invoices);

        $scoreVariable = $this->scorer->calculateScore($client);

        // Montants stables
        $stableInvoices = [];
        for ($i = 0; $i < 5; ++$i) {
            $stableInvoices[] = $this->createInvoice('PAID', '1000.00', 15);
        }

        $this->mockClientInvoices($client, $stableInvoices);

        $scoreStable = $this->scorer->calculateScore($client);

        // Les montants stables donnent un score >= aux montants variables
        self::assertGreaterThanOrEqual($scoreVariable, $scoreStable);
    }

    private function createClient(): Client
    {
        $company = new Company();
        $client = new Client();

        // Utiliser la reflection pour definir la company
        $ref = new \ReflectionProperty(Client::class, 'company');
        $ref->setValue($client, $company);

        $nameRef = new \ReflectionProperty(Client::class, 'name');
        $nameRef->setValue($client, 'Client Test');

        return $client;
    }

    private function createInvoice(string $status, string $amount, ?int $dueDaysFromIssue = null): Invoice
    {
        $invoice = new Invoice();
        $invoice->setStatus($status);
        $invoice->setTotalIncludingTax($amount);
        $invoice->setSeller(new Company());

        $client = $this->createClient();
        $invoice->setBuyer($client);

        $issueDate = new \DateTimeImmutable('-60 days');
        $invoice->setIssueDate($issueDate);

        if (null !== $dueDaysFromIssue) {
            $invoice->setDueDate($issueDate->modify("+{$dueDaysFromIssue} days"));
        }

        return $invoice;
    }

    /**
     * @param Invoice[] $invoices
     */
    private function mockClientInvoices(Client $client, array $invoices): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findBy')->willReturn($invoices);

        $this->em->method('getRepository')
            ->with(Invoice::class)
            ->willReturn($repository);
    }
}
