<?php

namespace App\Tests\Unit\Service\Banking;

use App\Entity\BankTransaction;
use App\Entity\Client;
use App\Entity\Company;
use App\Entity\Invoice;
use App\Entity\InvoiceLine;
use App\Service\Banking\ReconciliationEngine;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du moteur de reconciliation bancaire.
 *
 * Verifie le calcul du score de confiance et la reconciliation
 * automatique selon les criteres : montant, date, libelle.
 */
class ReconciliationEngineTest extends TestCase
{
    private ReconciliationEngine $engine;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->engine = new ReconciliationEngine($em);
    }

    /**
     * Verifie un score maximal quand montant, date et nom correspondent.
     */
    public function testPerfectMatchScore(): void
    {
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        $transaction = $this->createTransaction('1500.00', '2026-05-01', 'Virement Acme Corp facture');

        $score = $this->engine->computeScore($transaction, $invoice);

        // Montant exact (60) + date exacte (20) + nom match (20) = 100
        $this->assertSame(100, $score);
    }

    /**
     * Verifie le score avec uniquement le montant exact.
     */
    public function testExactAmountOnlyScore(): void
    {
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        $transaction = $this->createTransaction('1500.00', '2026-06-15', 'Virement banque');

        $score = $this->engine->computeScore($transaction, $invoice);

        // Montant exact (60), date loin (0), nom absent (0) = 60
        $this->assertSame(60, $score);
    }

    /**
     * Verifie le score avec un montant approchant (tolerance 1%).
     */
    public function testApproximateAmountScore(): void
    {
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        // 1505 est dans la tolerance de 1% (15.00)
        $transaction = $this->createTransaction('1505.00', '2026-06-15', 'Virement banque');

        $score = $this->engine->computeScore($transaction, $invoice);

        // Montant approchant (30) = 30
        $this->assertSame(30, $score);
    }

    /**
     * Verifie un score nul quand rien ne correspond.
     */
    public function testNoMatchScore(): void
    {
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        $transaction = $this->createTransaction('250.00', '2026-06-15', 'Abonnement internet');

        $score = $this->engine->computeScore($transaction, $invoice);

        $this->assertSame(0, $score);
    }

    /**
     * Verifie le score avec correspondance date proche.
     */
    public function testDateProximityScore(): void
    {
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        // Date a J+3 de l'echeance
        $transaction = $this->createTransaction('1500.00', '2026-05-04', 'Virement banque');

        $score = $this->engine->computeScore($transaction, $invoice);

        // Montant exact (60) + date proche J+3 (10) = 70
        $this->assertSame(70, $score);
    }

    /**
     * Verifie le score avec correspondance partielle du nom.
     */
    public function testPartialNameMatchScore(): void
    {
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corporation SAS');
        // Seul "acme" et "corporation" correspondent
        $transaction = $this->createTransaction('1500.00', '2026-06-15', 'Paiement acme corporation');

        $score = $this->engine->computeScore($transaction, $invoice);

        // Montant exact (60) + nom partiel (20, plus de 50% des mots) = 80
        $this->assertSame(80, $score);
    }

    /**
     * Verifie la reconciliation automatique au-dessus du seuil.
     */
    public function testAutoReconcileAboveThreshold(): void
    {
        $transaction = $this->createTransaction('1500.00', '2026-05-01', 'Virement Acme Corp');
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        $company = $this->createCompany();

        // Mock du repository pour retourner la facture
        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findBy')->willReturn([$invoice]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $engine = new ReconciliationEngine($em);
        $result = $engine->autoReconcile($transaction, $company);

        $this->assertTrue($result);
        $this->assertSame(BankTransaction::RECONCILIATION_CONFIRMED, $transaction->getReconciliationStatus());
        $this->assertSame($invoice, $transaction->getReconciledInvoice());
        $this->assertSame(100, $transaction->getReconciliationScore());
    }

    /**
     * Verifie la suggestion quand le score est entre 40 et 95.
     */
    public function testSuggestionBelowThreshold(): void
    {
        $transaction = $this->createTransaction('1500.00', '2026-06-15', 'Virement banque');
        $invoice = $this->createInvoice('1500.00', '2026-05-01', 'Acme Corp');
        $company = $this->createCompany();

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findBy')->willReturn([$invoice]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $engine = new ReconciliationEngine($em);
        $result = $engine->autoReconcile($transaction, $company);

        $this->assertFalse($result);
        $this->assertSame(BankTransaction::RECONCILIATION_SUGGESTED, $transaction->getReconciliationStatus());
    }

    /**
     * Verifie qu'un debit n'est pas reconcilie.
     */
    public function testDebitTransactionNotReconciled(): void
    {
        $transaction = $this->createTransaction('-1500.00', '2026-05-01', 'Paiement fournisseur');
        $company = $this->createCompany();

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('findBy')->willReturn([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $engine = new ReconciliationEngine($em);
        $result = $engine->autoReconcile($transaction, $company);

        $this->assertFalse($result);
        $this->assertSame(BankTransaction::RECONCILIATION_NONE, $transaction->getReconciliationStatus());
    }

    private function createInvoice(string $totalTtc, string $dueDate, string $buyerName): Invoice
    {
        $seller = new Company();
        $seller->setName('Ma Boite SAS');
        $seller->setSiren('123456789');
        $seller->setLegalForm('SAS');
        $seller->setAddressLine1('1 rue Test');
        $seller->setPostalCode('75001');
        $seller->setCity('Paris');

        $buyer = new Client();
        $buyer->setName($buyerName);
        $buyer->setAddressLine1('2 rue Client');
        $buyer->setPostalCode('75002');
        $buyer->setCity('Paris');

        $invoice = new Invoice();
        $invoice->setSeller($seller);
        $invoice->setBuyer($buyer);
        $invoice->setNumber('FA-2026-0001');
        $invoice->setIssueDate(new \DateTimeImmutable('2026-04-01'));
        $invoice->setDueDate(new \DateTimeImmutable($dueDate));

        // Configurer le montant TTC directement via une ligne
        $line = new InvoiceLine();
        $line->setDescription('Prestation');
        // Calculer quantite * prix pour obtenir le TTC souhaite
        // TTC = HT * 1.20, donc HT = TTC / 1.20
        $ht = bcdiv($totalTtc, '1.20', 4);
        $line->setQuantity('1.0000');
        $line->setUnitPriceExcludingTax($ht);
        $line->setVatRate('20');
        $line->computeAmounts();
        $invoice->addLine($line);
        $invoice->computeTotals();

        return $invoice;
    }

    private function createTransaction(string $amount, string $date, string $label): BankTransaction
    {
        $transaction = new BankTransaction();
        $transaction->setExternalTransactionId('tx_' . uniqid());
        $transaction->setAmount($amount);
        $transaction->setTransactionDate(new \DateTimeImmutable($date));
        $transaction->setLabel($label);

        return $transaction;
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Ma Boite SAS');
        $company->setSiren('123456789');
        $company->setLegalForm('SAS');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }
}
