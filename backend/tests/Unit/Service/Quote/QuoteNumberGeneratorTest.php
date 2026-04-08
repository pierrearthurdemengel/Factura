<?php

namespace App\Tests\Unit\Service\Quote;

use App\Entity\Company;
use App\Service\Quote\QuoteNumberGenerator;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class QuoteNumberGeneratorTest extends TestCase
{
    /**
     * Verifie que le premier numero de l'annee est DV-AAAA-0001.
     */
    public function testGeneratesFirstNumberOfYear(): void
    {
        $companyId = Uuid::v4();
        $company = $this->createCompanyMock($companyId);

        $connection = $this->createConnectionMock([
            'last_quote_number' => null,
            'last_quote_year' => null,
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $generator = new QuoteNumberGenerator($em);
        $number = $generator->generate($company);

        $currentYear = (int) date('Y');
        $this->assertSame(sprintf('DV-%d-0001', $currentYear), $number);
    }

    /**
     * Verifie que le numero s'incremente correctement.
     */
    public function testIncrementsNumber(): void
    {
        $companyId = Uuid::v4();
        $company = $this->createCompanyMock($companyId);
        $currentYear = (int) date('Y');

        $connection = $this->createConnectionMock([
            'last_quote_number' => 12,
            'last_quote_year' => $currentYear,
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $generator = new QuoteNumberGenerator($em);
        $number = $generator->generate($company);

        $this->assertSame(sprintf('DV-%d-0013', $currentYear), $number);
    }

    /**
     * Verifie la reinitialisation du compteur en debut d'annee.
     */
    public function testResetsNumberOnNewYear(): void
    {
        $companyId = Uuid::v4();
        $company = $this->createCompanyMock($companyId);
        $currentYear = (int) date('Y');

        $connection = $this->createConnectionMock([
            'last_quote_number' => 80,
            'last_quote_year' => $currentYear - 1,
        ]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getConnection')->willReturn($connection);

        $generator = new QuoteNumberGenerator($em);
        $number = $generator->generate($company);

        $this->assertSame(sprintf('DV-%d-0001', $currentYear), $number);
    }

    private function createCompanyMock(Uuid $id): Company
    {
        $company = $this->createMock(Company::class);
        $company->method('getId')->willReturn($id);

        return $company;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createConnectionMock(array $row): Connection
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAssociative')->willReturn($row);
        $connection->method('executeStatement')->willReturn(1);

        return $connection;
    }
}
