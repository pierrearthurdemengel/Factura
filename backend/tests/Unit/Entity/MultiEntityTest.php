<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Company;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires du support multi-entite.
 *
 * Verifie l'isolation entre entreprises, le switch d'entreprise active,
 * et le comportement par defaut quand aucune entreprise active n'est definie.
 */
class MultiEntityTest extends TestCase
{
    /**
     * Verifie qu'un utilisateur peut avoir plusieurs entreprises.
     */
    public function testUserCanHaveMultipleCompanies(): void
    {
        $user = $this->createUser();

        $company1 = $this->createCompany('Entreprise A', '111111111');
        $company2 = $this->createCompany('Entreprise B', '222222222');

        $user->addCompany($company1);
        $user->addCompany($company2);

        $this->assertCount(2, $user->getCompanies());
    }

    /**
     * Verifie que la premiere entreprise ajoutee devient automatiquement active.
     */
    public function testFirstCompanyBecomesActive(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany('Ma Boite', '123456789');

        $user->addCompany($company);

        $this->assertSame($company, $user->getActiveCompany());
        $this->assertSame($company, $user->getCompany());
    }

    /**
     * Verifie le switch d'entreprise active.
     */
    public function testSwitchActiveCompany(): void
    {
        $user = $this->createUser();

        $company1 = $this->createCompany('Entreprise A', '111111111');
        $company2 = $this->createCompany('Entreprise B', '222222222');

        $user->addCompany($company1);
        $user->addCompany($company2);

        // Par defaut, la premiere est active
        $this->assertSame($company1, $user->getCompany());

        // Switch vers la deuxieme
        $user->setActiveCompany($company2);
        $this->assertSame($company2, $user->getCompany());
        $this->assertSame($company2, $user->getActiveCompany());
    }

    /**
     * Verifie que getCompany() retourne la premiere entreprise
     * si aucune active n'est explicitement definie.
     */
    public function testGetCompanyFallsBackToFirstCompany(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany('Ma Boite', '123456789');

        $user->addCompany($company);
        $user->setActiveCompany(null);

        // Doit retourner la premiere entreprise
        $this->assertSame($company, $user->getCompany());
    }

    /**
     * Verifie que getCompany() retourne null si aucune entreprise.
     */
    public function testGetCompanyReturnsNullWithNoCompanies(): void
    {
        $user = $this->createUser();

        $this->assertNull($user->getCompany());
    }

    /**
     * Verifie qu'ajouter deux fois la meme entreprise ne cree pas de doublon.
     */
    public function testAddSameCompanyTwiceNoDuplicate(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany('Ma Boite', '123456789');

        $user->addCompany($company);
        $user->addCompany($company);

        $this->assertCount(1, $user->getCompanies());
    }

    /**
     * Verifie que l'entreprise connait son proprietaire apres ajout.
     */
    public function testCompanyOwnerSetOnAdd(): void
    {
        $user = $this->createUser();
        $company = $this->createCompany('Ma Boite', '123456789');

        $user->addCompany($company);

        $this->assertSame($user, $company->getOwner());
    }

    /**
     * Verifie l'isolation : deux users ont chacun leur entreprise.
     */
    public function testIsolationBetweenUsers(): void
    {
        $user1 = $this->createUser('user1@example.com');
        $user2 = $this->createUser('user2@example.com');

        $companyA = $this->createCompany('Entreprise A', '111111111');
        $companyB = $this->createCompany('Entreprise B', '222222222');

        $user1->addCompany($companyA);
        $user2->addCompany($companyB);

        $this->assertSame($companyA, $user1->getCompany());
        $this->assertSame($companyB, $user2->getCompany());
        $this->assertNotSame($user1->getCompany(), $user2->getCompany());
    }

    private function createUser(string $email = 'test@example.com'): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setPassword('hashed');
        $user->setFirstName('Test');
        $user->setLastName('User');

        return $user;
    }

    private function createCompany(string $name, string $siren): Company
    {
        $company = new Company();
        $company->setName($name);
        $company->setSiren($siren);
        $company->setLegalForm('SAS');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }
}
