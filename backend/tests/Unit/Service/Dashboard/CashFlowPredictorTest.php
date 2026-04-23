<?php

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\AccountingEntry;
use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Dashboard\CashFlowPredictor;
use App\Service\Dashboard\ClientPaymentScorer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use App\Tests\Trait\ReflectionHelperTrait;
use PHPUnit\Framework\TestCase;

class CashFlowPredictorTest extends TestCase
{
    use ReflectionHelperTrait;
    /**
     * Cree un predictor avec des resultats mock pour les 3 appels : factures en attente, ecritures, factures en retard.
     *
     * @param Invoice[]         $pendingInvoices
     * @param AccountingEntry[] $bankEntries
     * @param Invoice[]         $overdueInvoices
     */
    private function createPredictor(
        array $pendingInvoices = [],
        array $bankEntries = [],
        array $overdueInvoices = [],
    ): CashFlowPredictor {
        $callCount = 0;
        $allResults = [$pendingInvoices, $bankEntries, $overdueInvoices];

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

        $scorer = $this->createMock(ClientPaymentScorer::class);
        $scorer->method('getScore')->willReturn(80);

        return new CashFlowPredictor($em, $scorer);
    }

    private function createPendingInvoice(string $amountTtc, string $status = 'SENT'): Invoice
    {
        $company = new Company();
        $company->setName('Entreprise Test');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $client = new Client();
        $client->setName('Client Test');
        $client->setEmail('client@test.fr');
        $client->setCompany($company);

        $invoice = new Invoice();
        $invoice->setSeller($company);
        $invoice->setBuyer($client);

        $this->setPrivateProperty($invoice, 'status', $status);

        // Simuler le TTC directement
        $line = new InvoiceLine();
        $line->setDescription('Prestation');
        $line->setQuantity('1');
        $line->setUnitPriceExcludingTax($amountTtc);
        $line->setVatRate('0'); // Simplifie pour le test
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testPredictReturnsThreeHorizons(): void
    {
        $predictor = $this->createPredictor();

        $result = $predictor->predict(new Company(), '10000.00');

        self::assertCount(3, $result['projections']);
        self::assertSame(30, $result['projections'][0]['horizon']);
        self::assertSame(60, $result['projections'][1]['horizon']);
        self::assertSame(90, $result['projections'][2]['horizon']);
    }

    public function testPredictWithCurrentBalance(): void
    {
        $predictor = $this->createPredictor();

        $result = $predictor->predict(new Company(), '5000.00');

        self::assertSame('5000.00', $result['currentBalance']);
    }

    public function testPredictWithPendingInvoices(): void
    {
        $invoice = $this->createPendingInvoice('3000.00', 'SENT');

        $predictor = $this->createPredictor([$invoice]);

        $result = $predictor->predict(new Company(), '5000.00');

        // Les factures en attente augmentent les projections
        self::assertSame(1, $result['pendingInvoices']['count']);
        self::assertSame('3000.00', $result['pendingInvoices']['total']);
    }

    public function testPredictGeneratesLowBalanceAlert(): void
    {
        // Pas de factures, charges mensuelles elevees
        $entry = new AccountingEntry();
        $entry->setAmount('15000.00');

        $predictor = $this->createPredictor([], [$entry]);

        $result = $predictor->predict(new Company(), '1000.00', '500.00');

        // Avec 1000 EUR et des charges mensuelles de 5000 EUR (15000/3),
        // le solde a J+30 sera negatif → alerte
        $hasLowBalanceAlert = false;
        foreach ($result['alerts'] as $alert) {
            if ('low_balance' === $alert['type']) {
                $hasLowBalanceAlert = true;
            }
        }
        self::assertTrue($hasLowBalanceAlert);
    }

    public function testPredictNoAlertWhenBalanceSufficient(): void
    {
        $predictor = $this->createPredictor();

        // Balance elevee, pas de charges
        $result = $predictor->predict(new Company(), '100000.00', '1000.00');

        $lowBalanceAlerts = array_filter(
            $result['alerts'],
            static fn (array $a) => 'low_balance' === $a['type'],
        );
        self::assertEmpty($lowBalanceAlerts);
    }

    public function testPredictRecurringCharges(): void
    {
        // 3 mois de charges : 9000 total = 3000/mois
        $entry1 = new AccountingEntry();
        $entry1->setAmount('3000.00');
        $entry2 = new AccountingEntry();
        $entry2->setAmount('3000.00');
        $entry3 = new AccountingEntry();
        $entry3->setAmount('3000.00');

        $predictor = $this->createPredictor([], [$entry1, $entry2, $entry3]);

        $result = $predictor->predict(new Company(), '10000.00');

        // Charges mensuelles = 9000/3 = 3000
        self::assertSame('3000.00', $result['recurringCharges']);
    }

    public function testPredictWeightedInflows(): void
    {
        // Facture ACKNOWLEDGED = probabilite 0.95 * score 80/100 = 0.76
        $invoice = $this->createPendingInvoice('10000.00', 'ACKNOWLEDGED');

        $predictor = $this->createPredictor([$invoice]);

        $result = $predictor->predict(new Company(), '5000.00');

        // Le montant pondere doit etre inferieur au total
        self::assertNotSame($result['pendingInvoices']['total'], $result['pendingInvoices']['weightedTotal']);
        $weighted = $result['pendingInvoices']['weightedTotal'];
        self::assertTrue(bccomp($weighted, '0.00', 2) > 0);
        self::assertTrue(bccomp($weighted, $result['pendingInvoices']['total'], 2) <= 0);
    }
}
