<?php

namespace App\Tests\Unit\Service\Tax;

use App\Service\Tax\FecExporter;
use App\Service\Tax\FecValidator;
use PHPUnit\Framework\TestCase;

class FecValidatorTest extends TestCase
{
    private FecValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new FecValidator();
    }

    /**
     * Genere un FEC valide minimal avec une ecriture equilibree.
     */
    private function buildValidFec(string $debit = '1000,00', string $credit = '1000,00'): string
    {
        $header = implode("\t", FecExporter::COLUMNS);

        // Ligne debit
        $debitLine = implode("\t", [
            'VE', 'Journal des ventes', '1', '20260315',
            '411000', 'Clients', '', '',
            'FA-2026-0001', '20260315', 'Facture test',
            $debit, '0,00', '', '', '20260315', '', '',
        ]);

        // Ligne credit
        $creditLine = implode("\t", [
            'VE', 'Journal des ventes', '1', '20260315',
            '706000', 'Prestations', '', '',
            'FA-2026-0001', '20260315', 'Facture test',
            '0,00', $credit, '', '', '20260315', '', '',
        ]);

        return $header . "\n" . $debitLine . "\n" . $creditLine;
    }

    public function testValidFec(): void
    {
        $fec = $this->buildValidFec();
        $result = $this->validator->validate($fec);

        self::assertTrue($result['valid']);
        self::assertEmpty($result['errors']);
        self::assertSame(2, $result['lineCount']);
        self::assertSame(1, $result['entryCount']);
        self::assertSame('1000.00', $result['totalDebit']);
        self::assertSame('1000.00', $result['totalCredit']);
    }

    public function testValidFecEquilibrium(): void
    {
        $fec = $this->buildValidFec('500,00', '500,00');
        $result = $this->validator->validate($fec);

        self::assertTrue($result['valid']);
        self::assertSame('500.00', $result['totalDebit']);
        self::assertSame('500.00', $result['totalCredit']);
    }

    public function testRejectsEmptyFec(): void
    {
        $result = $this->validator->validate('');

        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['errors']);
    }

    public function testRejectsHeaderOnly(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);
        $result = $this->validator->validate($header);

        // Un FEC sans ecritures est invalide
        self::assertFalse($result['valid']);
        self::assertNotEmpty($result['errors']);
    }

    public function testRejectsWrongColumnCount(): void
    {
        $header = "Col1\tCol2\tCol3";
        $result = $this->validator->validate($header . "\ndata\tdata\tdata");

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('colonnes', $errors);
    }

    public function testRejectsInvalidDate(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);
        $line = implode("\t", [
            'VE', 'Journal des ventes', '1', '20261301', // Mois 13 invalide
            '411000', 'Clients', '', '',
            'FA-2026-0001', '20260315', 'Facture test',
            '1000,00', '0,00', '', '', '', '', '',
        ]);

        $result = $this->validator->validate($header . "\n" . $line);

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('date invalide', $errors);
    }

    public function testRejectsUnbalancedEntries(): void
    {
        $fec = $this->buildValidFec('1000,00', '500,00');
        $result = $this->validator->validate($fec);

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('equilibre', $errors);
    }

    public function testRejectsMissingAccountNumber(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);
        $line = implode("\t", [
            'VE', 'Journal des ventes', '1', '20260315',
            '', 'Sans compte', '', '', // Compte vide
            'FA-2026-0001', '20260315', 'Test',
            '100,00', '0,00', '', '', '', '', '',
        ]);

        $result = $this->validator->validate($header . "\n" . $line);

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('compte', $errors);
    }

    public function testRejectsInvalidAmount(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);
        $line = implode("\t", [
            'VE', 'Journal des ventes', '1', '20260315',
            '411000', 'Clients', '', '',
            'FA-2026-0001', '20260315', 'Test',
            'abc', '0,00', '', '', '', '', '',
        ]);

        $result = $this->validator->validate($header . "\n" . $line);

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('debit invalide', $errors);
    }

    public function testRejectsMissingEntryNumber(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);
        $line = implode("\t", [
            'VE', 'Journal des ventes', '', '20260315', // Numero vide
            '411000', 'Clients', '', '',
            'FA-2026-0001', '20260315', 'Test',
            '100,00', '0,00', '', '', '', '', '',
        ]);

        $result = $this->validator->validate($header . "\n" . $line);

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString("numero d'ecriture", $errors);
    }

    public function testValidatesChronologicalOrder(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);

        // Premiere ligne : mars
        $line1 = implode("\t", [
            'VE', 'Journal des ventes', '1', '20260315',
            '411000', 'Clients', '', '',
            'REF1', '20260315', 'Test 1',
            '500,00', '0,00', '', '', '', '', '',
        ]);
        $line1b = implode("\t", [
            'VE', 'Journal des ventes', '1', '20260315',
            '706000', 'Prestations', '', '',
            'REF1', '20260315', 'Test 1',
            '0,00', '500,00', '', '', '', '', '',
        ]);

        // Deuxieme ligne : janvier (anterieure = erreur)
        $line2 = implode("\t", [
            'VE', 'Journal des ventes', '2', '20260115',
            '411000', 'Clients', '', '',
            'REF2', '20260115', 'Test 2',
            '500,00', '0,00', '', '', '', '', '',
        ]);
        $line2b = implode("\t", [
            'VE', 'Journal des ventes', '2', '20260115',
            '706000', 'Prestations', '', '',
            'REF2', '20260115', 'Test 2',
            '0,00', '500,00', '', '', '', '', '',
        ]);

        $fec = implode("\n", [$header, $line1, $line1b, $line2, $line2b]);
        $result = $this->validator->validate($fec);

        self::assertFalse($result['valid']);
        $errors = implode(' ', $result['errors']);
        self::assertStringContainsString('chronologique', $errors);
    }

    public function testCountsMultipleEntries(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);

        $line1 = implode("\t", [
            'VE', 'Ventes', '1', '20260101',
            '411000', 'Clients', '', '',
            'REF1', '20260101', 'Test 1',
            '500,00', '0,00', '', '', '', '', '',
        ]);
        $line2 = implode("\t", [
            'VE', 'Ventes', '1', '20260101',
            '706000', 'Prestations', '', '',
            'REF1', '20260101', 'Test 1',
            '0,00', '500,00', '', '', '', '', '',
        ]);
        $line3 = implode("\t", [
            'BQ', 'Banque', '2', '20260201',
            '512000', 'Banque', '', '',
            'REF2', '20260201', 'Test 2',
            '300,00', '0,00', '', '', '', '', '',
        ]);
        $line4 = implode("\t", [
            'BQ', 'Banque', '2', '20260201',
            '411000', 'Clients', '', '',
            'REF2', '20260201', 'Test 2',
            '0,00', '300,00', '', '', '', '', '',
        ]);

        $fec = implode("\n", [$header, $line1, $line2, $line3, $line4]);
        $result = $this->validator->validate($fec);

        self::assertTrue($result['valid']);
        self::assertSame(4, $result['lineCount']);
        self::assertSame(2, $result['entryCount']);
        self::assertSame('800.00', $result['totalDebit']);
        self::assertSame('800.00', $result['totalCredit']);
    }

    public function testAcceptsEmptyOptionalDates(): void
    {
        $header = implode("\t", FecExporter::COLUMNS);
        $line = implode("\t", [
            'VE', 'Ventes', '1', '20260315',
            '411000', 'Clients', '', '',
            'REF1', '20260315', 'Test',
            '100,00', '0,00', '', '', '', '', '', // Dates optionnelles vides
        ]);
        $line2 = implode("\t", [
            'VE', 'Ventes', '1', '20260315',
            '706000', 'Prestations', '', '',
            'REF1', '20260315', 'Test',
            '0,00', '100,00', '', '', '', '', '',
        ]);

        $result = $this->validator->validate($header . "\n" . $line . "\n" . $line2);

        self::assertTrue($result['valid']);
    }

    public function testColumnsMatchFecExporter(): void
    {
        // Verifie que le validateur utilise les memes colonnes que l'exporteur
        self::assertCount(18, FecExporter::COLUMNS);
        self::assertSame('JournalCode', FecExporter::COLUMNS[0]);
        self::assertSame('Idevise', FecExporter::COLUMNS[17]);
    }
}
