<?php

namespace App\Tests\Unit\Service\Tax;

use App\Entity\Company;
use App\Service\Tax\VatCalculator;
use App\Service\Tax\VatDeclarationGenerator;
use PHPUnit\Framework\TestCase;

class VatDeclarationGeneratorTest extends TestCase
{
    /**
     * Cree un mock du VatCalculator avec des resultats predetermines.
     *
     * @param array<string, array{base: string, vat: string}> $byRate
     */
    private function createGenerator(
        string $collectedTotal,
        array $byRate,
        int $invoiceCount,
        string $deductibleTotal,
        int $entryCount = 0,
    ): VatDeclarationGenerator {
        $vatCalculator = $this->createMock(VatCalculator::class);
        $vatCalculator->method('calculateCollectedVat')
            ->willReturn([
                'total' => $collectedTotal,
                'byRate' => $byRate,
                'invoiceCount' => $invoiceCount,
            ]);
        $vatCalculator->method('calculateDeductibleVat')
            ->willReturn([
                'total' => $deductibleTotal,
                'entryCount' => $entryCount,
            ]);

        return new VatDeclarationGenerator($vatCalculator);
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Test SARL');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue de Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }

    public function testGenerateCA3WithSingleRate(): void
    {
        $generator = $this->createGenerator(
            '200.00',
            ['20' => ['base' => '1000.00', 'vat' => '200.00']],
            5,
            '50.00',
        );

        $company = $this->createCompany();
        $ca3 = $generator->generateCA3(
            $company,
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('CA3', $ca3['form']);
        self::assertSame('3310', $ca3['cerfa']);
        self::assertSame('123456789', $ca3['company']['siren']);

        // Verifier les lignes
        self::assertSame('1000.00', $ca3['lines']['A01']['base']);
        self::assertSame('1000.00', $ca3['lines']['A08']['amount']);
        self::assertSame('200.00', $ca3['lines']['B08A']['amount']);
        self::assertSame('200.00', $ca3['lines']['B15']['amount']);
        self::assertSame('50.00', $ca3['lines']['C20']['amount']);
        self::assertSame('150.00', $ca3['lines']['D28']['amount']);
        self::assertSame('0.00', $ca3['lines']['D25']['amount']);
    }

    public function testGenerateCA3WithMultipleRates(): void
    {
        $generator = $this->createGenerator(
            '227.50',
            [
                '20' => ['base' => '1000.00', 'vat' => '200.00'],
                '5.5' => ['base' => '500.00', 'vat' => '27.50'],
            ],
            3,
            '100.00',
        );

        $ca3 = $generator->generateCA3(
            $this->createCompany(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('1000.00', $ca3['lines']['A01']['base']);
        self::assertSame('500.00', $ca3['lines']['A02']['base']);
        self::assertSame('1500.00', $ca3['lines']['A08']['amount']);
        self::assertSame('200.00', $ca3['lines']['B08A']['amount']);
        self::assertSame('27.50', $ca3['lines']['B09']['amount']);
        self::assertSame('127.50', $ca3['summary']['netDue']);
    }

    public function testGenerateCA3WithCreditTva(): void
    {
        // TVA deductible > collectee : credit de TVA
        $generator = $this->createGenerator(
            '100.00',
            ['20' => ['base' => '500.00', 'vat' => '100.00']],
            2,
            '300.00',
        );

        $ca3 = $generator->generateCA3(
            $this->createCompany(),
            new \DateTimeImmutable('2026-01-01'),
            new \DateTimeImmutable('2026-03-31'),
        );

        self::assertSame('0.00', $ca3['lines']['D28']['amount']);
        self::assertSame('200.00', $ca3['lines']['D25']['amount']);
        self::assertSame('0.00', $ca3['summary']['netDue']);
        self::assertSame('200.00', $ca3['summary']['creditTva']);
    }

    public function testGenerateCA12(): void
    {
        $generator = $this->createGenerator(
            '2400.00',
            [
                '20' => ['base' => '10000.00', 'vat' => '2000.00'],
                '10' => ['base' => '4000.00', 'vat' => '400.00'],
            ],
            50,
            '600.00',
        );

        $ca12 = $generator->generateCA12($this->createCompany(), 2026);

        self::assertSame('CA12', $ca12['form']);
        self::assertSame('3517', $ca12['cerfa']);
        self::assertSame(2026, $ca12['year']);
        self::assertSame('14000.00', $ca12['turnover']['total']);
        self::assertSame('2400.00', $ca12['vat']['collected']);
        self::assertSame('600.00', $ca12['vat']['deductible']);
        self::assertSame('1800.00', $ca12['vat']['netDue']);
        self::assertSame('0.00', $ca12['vat']['creditTva']);
    }

    public function testGenerateCA12WithCreditTva(): void
    {
        $generator = $this->createGenerator(
            '50.00',
            ['20' => ['base' => '250.00', 'vat' => '50.00']],
            2,
            '500.00',
        );

        $ca12 = $generator->generateCA12($this->createCompany(), 2026);

        self::assertSame('0.00', $ca12['vat']['netDue']);
        self::assertSame('450.00', $ca12['vat']['creditTva']);
    }

    public function testGenerateCA3Period(): void
    {
        $generator = $this->createGenerator('0.00', [], 0, '0.00');

        $ca3 = $generator->generateCA3(
            $this->createCompany(),
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-06-30'),
        );

        self::assertSame('2026-04-01', $ca3['period']['start']);
        self::assertSame('2026-06-30', $ca3['period']['end']);
    }

    public function testCA12TurnoverByRate(): void
    {
        $generator = $this->createGenerator(
            '300.00',
            [
                '20' => ['base' => '1000.00', 'vat' => '200.00'],
                '10' => ['base' => '1000.00', 'vat' => '100.00'],
            ],
            10,
            '0.00',
        );

        $ca12 = $generator->generateCA12($this->createCompany(), 2026);

        self::assertCount(2, $ca12['turnover']['byRate']);
        self::assertSame(10, $ca12['summary']['invoiceCount']);
    }

    public function testJournalLabels(): void
    {
        $labels = VatDeclarationGenerator::getJournalLabels();

        self::assertArrayHasKey('VE', $labels);
        self::assertArrayHasKey('AC', $labels);
        self::assertArrayHasKey('BQ', $labels);
        self::assertArrayHasKey('OD', $labels);
    }
}
