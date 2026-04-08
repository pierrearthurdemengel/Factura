<?php

namespace App\Tests\Unit\Service\Tax;

use App\Entity\AccountingEntry;
use App\Entity\Company;
use App\Service\Tax\FecExporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

class FecExporterTest extends TestCase
{
    /**
     * Cree un exporter avec des ecritures comptables mockees.
     *
     * @param AccountingEntry[] $entries
     */
    private function createExporter(array $entries = []): FecExporter
    {
        $query = $this->createMock(Query::class);
        $query->method('getResult')->willReturn($entries);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);
        $em->method('getRepository')->willReturn($repo);

        return new FecExporter($em);
    }

    /**
     * Cree une ecriture comptable de test.
     */
    private function createEntry(
        string $journalCode,
        string $debitAccount,
        string $creditAccount,
        string $amount,
        string $label,
        ?string $pieceReference = null,
        bool $validated = false,
    ): AccountingEntry {
        $company = new Company();
        $company->setName('Test SARL');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        $entry = new AccountingEntry();
        $entry->setCompany($company);
        $entry->setEntryDate(new \DateTimeImmutable('2026-03-15'));
        $entry->setJournalCode($journalCode);
        $entry->setDebitAccount($debitAccount);
        $entry->setCreditAccount($creditAccount);
        $entry->setAmount($amount);
        $entry->setLabel($label);
        $entry->setPieceReference($pieceReference);
        $entry->setSourceType(AccountingEntry::SOURCE_INVOICE);
        $entry->setValidated($validated);

        return $entry;
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Test SARL');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }

    public function testExportHeaderContains18Columns(): void
    {
        $exporter = $this->createExporter([]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $header = explode("\t", $lines[0]);

        self::assertCount(18, $header);
        self::assertSame('JournalCode', $header[0]);
        self::assertSame('JournalLib', $header[1]);
        self::assertSame('EcritureNum', $header[2]);
        self::assertSame('EcritureDate', $header[3]);
        self::assertSame('Idevise', $header[17]);
    }

    public function testExportGeneratesTwoLinesPerEntry(): void
    {
        $entry = $this->createEntry(
            'VE',
            '411000',
            '706000',
            '1200.00',
            'Facture FA-2026-0001',
            'FA-2026-0001',
        );

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));

        // En-tete + 2 lignes (debit + credit)
        self::assertCount(3, $lines);
    }

    public function testExportDebitCreditAmounts(): void
    {
        $entry = $this->createEntry(
            'VE',
            '411000',
            '706000',
            '1000.00',
            'Facture test',
        );

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));

        // Ligne debit : montant en colonne 12 (index 11)
        $debitLine = explode("\t", $lines[1]);
        self::assertSame('411000', $debitLine[4]);
        self::assertSame('1000,00', $debitLine[11]); // Debit
        self::assertSame('0,00', $debitLine[12]);     // Credit

        // Ligne credit : montant en colonne 13 (index 12)
        $creditLine = explode("\t", $lines[2]);
        self::assertSame('706000', $creditLine[4]);
        self::assertSame('0,00', $creditLine[11]);    // Debit
        self::assertSame('1000,00', $creditLine[12]); // Credit
    }

    public function testExportDateFormat(): void
    {
        $entry = $this->createEntry('VE', '411000', '706000', '500.00', 'Test');

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $fields = explode("\t", $lines[1]);

        // Date au format AAAAMMJJ
        self::assertSame('20260315', $fields[3]);
    }

    public function testExportJournalLabels(): void
    {
        $entry = $this->createEntry('VE', '411000', '706000', '500.00', 'Test');

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $fields = explode("\t", $lines[1]);

        self::assertSame('VE', $fields[0]);
        self::assertSame('Journal des ventes', $fields[1]);
    }

    public function testExportPieceReference(): void
    {
        $entry = $this->createEntry(
            'VE',
            '411000',
            '706000',
            '500.00',
            'Facture FA-2026-0042',
            'FA-2026-0042',
        );

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $fields = explode("\t", $lines[1]);

        self::assertSame('FA-2026-0042', $fields[8]); // PieceRef
    }

    public function testExportValidatedEntry(): void
    {
        $entry = $this->createEntry(
            'VE',
            '411000',
            '706000',
            '500.00',
            'Test',
            null,
            true,
        );

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $fields = explode("\t", $lines[1]);

        // ValidDate doit etre remplie si l'ecriture est validee
        self::assertSame('20260315', $fields[15]);
    }

    public function testExportNonValidatedEntry(): void
    {
        $entry = $this->createEntry(
            'VE',
            '411000',
            '706000',
            '500.00',
            'Test',
            null,
            false,
        );

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $fields = explode("\t", $lines[1]);

        // ValidDate doit etre vide si non validee
        self::assertSame('', $fields[15]);
    }

    public function testExportAmountFormatFrench(): void
    {
        $entry = $this->createEntry('BQ', '512000', '411000', '1234.56', 'Encaissement');

        $exporter = $this->createExporter([$entry]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));
        $fields = explode("\t", $lines[1]);

        // Les montants doivent utiliser la virgule
        self::assertSame('1234,56', $fields[11]);
    }

    public function testExportMultipleEntries(): void
    {
        $entry1 = $this->createEntry('VE', '411000', '706000', '1000.00', 'Facture 1');
        $entry2 = $this->createEntry('BQ', '512000', '411000', '1000.00', 'Encaissement 1');

        $exporter = $this->createExporter([$entry1, $entry2]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));

        // En-tete + 2 lignes par ecriture * 2 ecritures = 5 lignes
        self::assertCount(5, $lines);
    }

    public function testExportEcritureNumIncremental(): void
    {
        $entry1 = $this->createEntry('VE', '411000', '706000', '1000.00', 'Facture 1');
        $entry2 = $this->createEntry('BQ', '512000', '411000', '1000.00', 'Encaissement');

        $exporter = $this->createExporter([$entry1, $entry2]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));

        // Ecriture 1 : lignes 1 et 2 (meme EcritureNum)
        $fields1a = explode("\t", $lines[1]);
        $fields1b = explode("\t", $lines[2]);
        self::assertSame('1', $fields1a[2]);
        self::assertSame('1', $fields1b[2]);

        // Ecriture 2 : lignes 3 et 4
        $fields2a = explode("\t", $lines[3]);
        $fields2b = explode("\t", $lines[4]);
        self::assertSame('2', $fields2a[2]);
        self::assertSame('2', $fields2b[2]);
    }

    public function testGenerateFileNameFormat(): void
    {
        $company = $this->createCompany();

        $exporter = $this->createExporter([]);

        $fileName = $exporter->generateFileName($company, 2026);

        self::assertSame('123456789FEC20261231.txt', $fileName);
    }

    public function testGetColumnCount(): void
    {
        $exporter = $this->createExporter([]);

        self::assertSame(18, $exporter->getColumnCount());
    }

    public function testExportEmptyReturnsHeaderOnly(): void
    {
        $exporter = $this->createExporter([]);

        $content = $exporter->export($this->createCompany(), 2026);
        $lines = explode("\n", trim($content));

        self::assertCount(1, $lines);
        self::assertStringStartsWith('JournalCode', $lines[0]);
    }
}
