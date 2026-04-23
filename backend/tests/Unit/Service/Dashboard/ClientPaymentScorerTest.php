<?php

namespace App\Tests\Unit\Service\Dashboard;

use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Dashboard\ClientPaymentScorer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use App\Tests\Trait\ReflectionHelperTrait;
use PHPUnit\Framework\TestCase;

class ClientPaymentScorerTest extends TestCase
{
    use ReflectionHelperTrait;
    /**
     * Cree un scorer avec des factures mockees pour un client.
     *
     * @param Invoice[] $invoices
     */
    private function createScorer(array $invoices = []): ClientPaymentScorer
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

        return new ClientPaymentScorer($em);
    }

    private function createClient(): Client
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

        return $client;
    }

    /**
     * Cree une facture pour les tests de scoring.
     */
    private function createInvoice(
        string $status = 'PAID',
        int $dueDays = 30,
    ): Invoice {
        $company = new Company();
        $company->setName('Entreprise Test');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $client = $this->createClient();

        $invoice = new Invoice();
        $invoice->setSeller($company);
        $invoice->setBuyer($client);

        $this->setPrivateProperty($invoice, 'status', $status);

        // Setter la date d'echeance
        $this->setPrivateProperty($invoice, 'dueDate', new \DateTimeImmutable(sprintf('+%d days', $dueDays)));

        $line = new InvoiceLine();
        $line->setDescription('Prestation');
        $line->setQuantity('1');
        $line->setUnitPriceExcludingTax('1000.00');
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    public function testNewClientScoreNeutral(): void
    {
        $scorer = $this->createScorer([]);

        $score = $scorer->getScore($this->createClient());

        // Un nouveau client sans factures a un score neutre de 50
        self::assertSame(50, $score);
    }

    public function testGoodPayerHighScore(): void
    {
        $invoices = [];
        for ($i = 0; $i < 12; ++$i) {
            $invoices[] = $this->createInvoice('PAID', 15);
        }

        $scorer = $this->createScorer($invoices);
        $score = $scorer->getScore($this->createClient());

        // 12 factures toutes payees a 15 jours = score eleve
        self::assertGreaterThanOrEqual(ClientPaymentScorer::THRESHOLD_EXCELLENT, $score);
    }

    public function testBadPayerLowScore(): void
    {
        $invoices = [];
        // Mix : quelques payees, beaucoup en retard
        for ($i = 0; $i < 3; ++$i) {
            $invoices[] = $this->createInvoice('PAID', 45);
        }
        for ($i = 0; $i < 5; ++$i) {
            $invoices[] = $this->createInvoice('SENT', 60);
        }

        $scorer = $this->createScorer($invoices);
        $score = $scorer->getScore($this->createClient());

        // Beaucoup de factures non payees = score bas
        self::assertLessThan(ClientPaymentScorer::THRESHOLD_EXCELLENT, $score);
    }

    public function testScoreBoundedZeroToHundred(): void
    {
        // Cas extreme : toutes les factures non payees
        $invoices = [];
        for ($i = 0; $i < 10; ++$i) {
            $invoices[] = $this->createInvoice('SENT', 90);
        }

        $scorer = $this->createScorer($invoices);
        $score = $scorer->getScore($this->createClient());

        self::assertGreaterThanOrEqual(0, $score);
        self::assertLessThanOrEqual(100, $score);
    }

    public function testAveragePaymentDelayWithPaidInvoices(): void
    {
        $inv1 = $this->createInvoice('PAID', 15);
        $inv2 = $this->createInvoice('PAID', 30);

        $scorer = $this->createScorer([$inv1, $inv2]);
        $delay = $scorer->getAveragePaymentDelay($this->createClient());

        self::assertNotNull($delay);
        // Delai moyen entre 15 et 30 jours
        self::assertGreaterThanOrEqual(15, $delay);
        self::assertLessThanOrEqual(30, $delay);
    }

    public function testAveragePaymentDelayNullWhenNoPaid(): void
    {
        $invoices = [
            $this->createInvoice('SENT', 30),
            $this->createInvoice('ACKNOWLEDGED', 30),
        ];

        $scorer = $this->createScorer($invoices);
        $delay = $scorer->getAveragePaymentDelay($this->createClient());

        self::assertNull($delay);
    }

    public function testGetProfileContainsAllFields(): void
    {
        $invoices = [
            $this->createInvoice('PAID', 15),
            $this->createInvoice('SENT', 30),
        ];

        $scorer = $this->createScorer($invoices);
        $profile = $scorer->getProfile($this->createClient());

        self::assertArrayHasKey('score', $profile);
        self::assertArrayHasKey('label', $profile);
        self::assertArrayHasKey('level', $profile);
        self::assertArrayHasKey('averageDelay', $profile);
        self::assertArrayHasKey('paidCount', $profile);
        self::assertArrayHasKey('totalCount', $profile);
        self::assertArrayHasKey('paymentRate', $profile);
        self::assertArrayHasKey('suggestion', $profile);
    }

    public function testGetProfileSuggestionForBadPayer(): void
    {
        // Toutes les factures non payees → suggestion
        $invoices = [];
        for ($i = 0; $i < 5; ++$i) {
            $invoices[] = $this->createInvoice('SENT', 60);
        }

        $scorer = $this->createScorer($invoices);
        $profile = $scorer->getProfile($this->createClient());

        // Score bas → suggestion non null
        self::assertNotNull($profile['suggestion']);
    }

    public function testGetProfileNoSuggestionForGoodPayer(): void
    {
        $invoices = [];
        for ($i = 0; $i < 12; ++$i) {
            $invoices[] = $this->createInvoice('PAID', 10);
        }

        $scorer = $this->createScorer($invoices);
        $profile = $scorer->getProfile($this->createClient());

        // Score eleve → pas de suggestion
        self::assertNull($profile['suggestion']);
    }

    public function testPaymentRateCalculation(): void
    {
        $invoices = [
            $this->createInvoice('PAID', 15),
            $this->createInvoice('PAID', 15),
            $this->createInvoice('SENT', 30),
            $this->createInvoice('SENT', 30),
        ];

        $scorer = $this->createScorer($invoices);
        $profile = $scorer->getProfile($this->createClient());

        // 2 payees sur 4 = 50%
        self::assertSame('50.00', $profile['paymentRate']);
        self::assertSame(2, $profile['paidCount']);
        self::assertSame(4, $profile['totalCount']);
    }
}
