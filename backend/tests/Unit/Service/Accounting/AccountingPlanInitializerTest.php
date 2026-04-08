<?php

namespace App\Tests\Unit\Service\Accounting;

use App\Entity\Company;
use App\Service\Accounting\AccountingPlanInitializer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AccountingPlanInitializerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AccountingPlanInitializer $initializer;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->initializer = new AccountingPlanInitializer($this->em);
    }

    public function testInitializeCreatesPlanWithDefaultAccounts(): void
    {
        $company = new Company();

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $plan = $this->initializer->initialize($company);

        self::assertSame('Plan Comptable General', $plan->getName());
        self::assertSame($company, $plan->getCompany());
        self::assertGreaterThan(30, $plan->getAccounts()->count());
    }

    public function testDefaultAccountsContainEssentialAccounts(): void
    {
        $company = new Company();

        $plan = $this->initializer->initialize($company);

        // Comptes essentiels
        self::assertNotNull($plan->findAccount('411000'), 'Compte 411 Clients manquant');
        self::assertNotNull($plan->findAccount('401000'), 'Compte 401 Fournisseurs manquant');
        self::assertNotNull($plan->findAccount('512000'), 'Compte 512 Banque manquant');
        self::assertNotNull($plan->findAccount('706000'), 'Compte 706 Prestations manquant');
        self::assertNotNull($plan->findAccount('445710'), 'Compte 44571 TVA collectee manquant');
        self::assertNotNull($plan->findAccount('445660'), 'Compte 44566 TVA deductible manquant');
        self::assertNotNull($plan->findAccount('646000'), 'Compte 646 Cotisations exploitant manquant');
    }

    public function testAllAccountsMarkedAsDefault(): void
    {
        $company = new Company();

        $plan = $this->initializer->initialize($company);

        foreach ($plan->getAccounts() as $account) {
            self::assertTrue($account->isDefault(), sprintf(
                'Le compte %s devrait etre marque comme defaut',
                $account->getNumber(),
            ));
        }
    }

    public function testAccountTypesAreValid(): void
    {
        $validTypes = ['actif', 'passif', 'charge', 'produit'];
        $company = new Company();

        $plan = $this->initializer->initialize($company);

        foreach ($plan->getAccounts() as $account) {
            self::assertContains($account->getType(), $validTypes, sprintf(
                'Le compte %s a un type invalide : %s',
                $account->getNumber(),
                $account->getType(),
            ));
        }
    }

    public function testChargeAccountsStartWith6(): void
    {
        $company = new Company();
        $plan = $this->initializer->initialize($company);

        foreach ($plan->getAccounts() as $account) {
            if ('charge' === $account->getType()) {
                self::assertStringStartsWith('6', $account->getNumber(), sprintf(
                    'Le compte de charge %s devrait commencer par 6',
                    $account->getNumber(),
                ));
            }
        }
    }

    public function testProduitAccountsStartWith7(): void
    {
        $company = new Company();
        $plan = $this->initializer->initialize($company);

        foreach ($plan->getAccounts() as $account) {
            if ('produit' === $account->getType()) {
                self::assertStringStartsWith('7', $account->getNumber(), sprintf(
                    'Le compte de produit %s devrait commencer par 7',
                    $account->getNumber(),
                ));
            }
        }
    }

    public function testGetDefaultAccountsReturnsArray(): void
    {
        $accounts = AccountingPlanInitializer::getDefaultAccounts();

        self::assertIsArray($accounts);
        self::assertNotEmpty($accounts);

        foreach ($accounts as $account) {
            self::assertArrayHasKey('number', $account);
            self::assertArrayHasKey('label', $account);
            self::assertArrayHasKey('type', $account);
        }
    }
}
