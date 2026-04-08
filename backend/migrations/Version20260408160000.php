<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 18 : justificatifs et OCR.
 */
final class Version20260408160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree la table receipts pour la gestion des justificatifs avec OCR.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE receipts (
            id UUID NOT NULL,
            company_id UUID NOT NULL,
            file_path VARCHAR(500) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            mime_type VARCHAR(50) NOT NULL,
            file_size INT NOT NULL,
            file_hash VARCHAR(64) NOT NULL,
            ocr_data JSON DEFAULT NULL,
            ocr_status VARCHAR(20) NOT NULL DEFAULT \'PENDING\',
            bank_transaction_id UUID DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_receipts_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
            CONSTRAINT fk_receipts_bank_tx FOREIGN KEY (bank_transaction_id) REFERENCES bank_transactions(id) ON DELETE SET NULL
        )');
        $this->addSql('CREATE INDEX idx_receipt_company_date ON receipts (company_id, created_at)');
        $this->addSql('COMMENT ON COLUMN receipts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN receipts.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN receipts.bank_transaction_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN receipts.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS receipts');
    }
}
