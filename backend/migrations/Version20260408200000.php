<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 20 : tables pour la comptabilite automatisee.
 * - accounting_plans : plan comptable par entreprise
 * - accounting_accounts : comptes du plan comptable
 * - accounting_entries : ecritures comptables.
 */
final class Version20260408200000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables accounting_plans, accounting_accounts et accounting_entries';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accounting_plans (
            id UUID NOT NULL,
            company_id UUID NOT NULL,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_accounting_plan_company ON accounting_plans (company_id)');
        $this->addSql('ALTER TABLE accounting_plans ADD CONSTRAINT fk_accounting_plan_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN accounting_plans.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accounting_plans.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accounting_plans.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE accounting_accounts (
            id UUID NOT NULL,
            plan_id UUID NOT NULL,
            number VARCHAR(10) NOT NULL,
            label VARCHAR(255) NOT NULL,
            type VARCHAR(20) NOT NULL,
            parent_number VARCHAR(10) DEFAULT NULL,
            is_default BOOLEAN DEFAULT FALSE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_account_plan_number ON accounting_accounts (plan_id, number)');
        $this->addSql('ALTER TABLE accounting_accounts ADD CONSTRAINT fk_accounting_account_plan FOREIGN KEY (plan_id) REFERENCES accounting_plans (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN accounting_accounts.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accounting_accounts.plan_id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE TABLE accounting_entries (
            id UUID NOT NULL,
            company_id UUID NOT NULL,
            entry_date DATE NOT NULL,
            journal_code VARCHAR(5) NOT NULL,
            debit_account VARCHAR(10) NOT NULL,
            credit_account VARCHAR(10) NOT NULL,
            amount NUMERIC(15, 2) NOT NULL,
            label VARCHAR(255) NOT NULL,
            piece_reference VARCHAR(100) DEFAULT NULL,
            source_type VARCHAR(30) NOT NULL,
            source_id UUID DEFAULT NULL,
            validated BOOLEAN DEFAULT FALSE NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE INDEX idx_entry_company_date ON accounting_entries (company_id, entry_date)');
        $this->addSql('CREATE INDEX idx_entry_journal ON accounting_entries (journal_code)');
        $this->addSql('CREATE INDEX idx_entry_source ON accounting_entries (source_type, source_id)');

        $this->addSql('ALTER TABLE accounting_entries ADD CONSTRAINT fk_entry_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN accounting_entries.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accounting_entries.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accounting_entries.entry_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN accounting_entries.source_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accounting_entries.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE accounting_entries');
        $this->addSql('DROP TABLE accounting_accounts');
        $this->addSql('DROP TABLE accounting_plans');
    }
}
