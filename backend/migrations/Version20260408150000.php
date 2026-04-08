<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 17 : synchronisation bancaire Open Banking.
 */
final class Version20260408150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables pour la synchronisation bancaire Open Banking.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE bank_connections (
            id UUID NOT NULL,
            company_id UUID NOT NULL,
            bank_id VARCHAR(100) NOT NULL,
            bank_name VARCHAR(255) NOT NULL,
            requisition_id VARCHAR(255) DEFAULT NULL,
            access_token TEXT DEFAULT NULL,
            refresh_token TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'ACTIVE\',
            last_sync_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_bank_connections_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )');
        $this->addSql('COMMENT ON COLUMN bank_connections.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_connections.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_connections.last_sync_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bank_connections.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE bank_accounts (
            id UUID NOT NULL,
            bank_connection_id UUID NOT NULL,
            external_account_id VARCHAR(255) NOT NULL,
            iban VARCHAR(34) DEFAULT NULL,
            label VARCHAR(255) NOT NULL,
            balance NUMERIC(15, 2) DEFAULT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_bank_accounts_connection FOREIGN KEY (bank_connection_id) REFERENCES bank_connections(id) ON DELETE CASCADE
        )');
        $this->addSql('COMMENT ON COLUMN bank_accounts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.bank_connection_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_accounts.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE bank_transactions (
            id UUID NOT NULL,
            bank_account_id UUID NOT NULL,
            external_transaction_id VARCHAR(255) NOT NULL,
            transaction_date DATE NOT NULL,
            amount NUMERIC(15, 2) NOT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            label VARCHAR(500) NOT NULL,
            category VARCHAR(100) DEFAULT NULL,
            reconciled_invoice_id UUID DEFAULT NULL,
            reconciliation_status VARCHAR(20) NOT NULL DEFAULT \'NONE\',
            reconciliation_score INT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_bank_tx_account FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE,
            CONSTRAINT fk_bank_tx_invoice FOREIGN KEY (reconciled_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL,
            CONSTRAINT uq_bank_tx_external_id UNIQUE (external_transaction_id)
        )');
        $this->addSql('CREATE INDEX idx_bank_tx_account_date ON bank_transactions (bank_account_id, transaction_date)');
        $this->addSql('COMMENT ON COLUMN bank_transactions.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_transactions.bank_account_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_transactions.transaction_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN bank_transactions.reconciled_invoice_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN bank_transactions.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS bank_transactions');
        $this->addSql('DROP TABLE IF EXISTS bank_accounts');
        $this->addSql('DROP TABLE IF EXISTS bank_connections');
    }
}
