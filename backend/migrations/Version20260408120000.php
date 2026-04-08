<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 13 : devis, acomptes et champs complementaires factures.
 */
final class Version20260408120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables quotes, quote_lines, quote_events, et les champs type/sourceQuote/parentInvoice sur invoices.';
    }

    public function up(Schema $schema): void
    {
        // Compteurs de sequence devis sur la table companies
        $this->addSql('ALTER TABLE companies ADD COLUMN last_quote_number INT DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD COLUMN last_quote_year INT DEFAULT NULL');

        // Table des devis (creee avant les FK sur invoices qui la referencent)
        $this->addSql('CREATE TABLE quotes (
            id UUID NOT NULL,
            number VARCHAR(50) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT \'DRAFT\',
            seller_id UUID NOT NULL,
            buyer_id UUID NOT NULL,
            issue_date DATE NOT NULL,
            validity_end_date DATE DEFAULT NULL,
            accepted_at DATE DEFAULT NULL,
            rejected_at DATE DEFAULT NULL,
            converted_invoice_id UUID DEFAULT NULL,
            currency VARCHAR(3) NOT NULL DEFAULT \'EUR\',
            total_excluding_tax NUMERIC(15,2) NOT NULL DEFAULT 0.00,
            total_tax NUMERIC(15,2) NOT NULL DEFAULT 0.00,
            total_including_tax NUMERIC(15,2) NOT NULL DEFAULT 0.00,
            legal_mention TEXT DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_quote_number ON quotes (number)');
        $this->addSql('CREATE INDEX idx_quote_status_date ON quotes (status, issue_date)');
        $this->addSql('ALTER TABLE quotes ADD CONSTRAINT fk_quotes_seller FOREIGN KEY (seller_id) REFERENCES companies (id)');
        $this->addSql('ALTER TABLE quotes ADD CONSTRAINT fk_quotes_buyer FOREIGN KEY (buyer_id) REFERENCES clients (id)');
        $this->addSql('ALTER TABLE quotes ADD CONSTRAINT fk_quotes_converted_invoice FOREIGN KEY (converted_invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');

        // Champs complementaires sur les factures (apres creation de la table quotes)
        $this->addSql('ALTER TABLE invoices ADD COLUMN type VARCHAR(20) NOT NULL DEFAULT \'STANDARD\'');
        $this->addSql('ALTER TABLE invoices ADD COLUMN source_quote_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD COLUMN parent_invoice_id UUID DEFAULT NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoices_source_quote FOREIGN KEY (source_quote_id) REFERENCES quotes (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE invoices ADD CONSTRAINT fk_invoices_parent_invoice FOREIGN KEY (parent_invoice_id) REFERENCES invoices (id) ON DELETE SET NULL');

        // Table des lignes de devis
        $this->addSql('CREATE TABLE quote_lines (
            id UUID NOT NULL,
            quote_id UUID NOT NULL,
            position SMALLINT NOT NULL DEFAULT 1,
            description VARCHAR(500) NOT NULL,
            quantity NUMERIC(10,4) NOT NULL,
            unit VARCHAR(10) NOT NULL DEFAULT \'EA\',
            unit_price_excluding_tax NUMERIC(15,4) NOT NULL,
            vat_rate VARCHAR(5) NOT NULL DEFAULT \'20\',
            line_amount NUMERIC(15,2) NOT NULL DEFAULT 0.00,
            vat_amount NUMERIC(15,2) NOT NULL DEFAULT 0.00,
            PRIMARY KEY (id)
        )');
        $this->addSql('ALTER TABLE quote_lines ADD CONSTRAINT fk_quote_lines_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE');

        // Table des evenements de devis (piste d audit)
        $this->addSql('CREATE TABLE quote_events (
            id UUID NOT NULL,
            quote_id UUID NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            metadata JSON NOT NULL,
            ip_address VARCHAR(255) DEFAULT NULL,
            user_agent VARCHAR(255) DEFAULT NULL,
            occurred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');
        $this->addSql('ALTER TABLE quote_events ADD CONSTRAINT fk_quote_events_quote FOREIGN KEY (quote_id) REFERENCES quotes (id) ON DELETE CASCADE');

        // Commenter les types immutables pour les dates
        $this->addSql('COMMENT ON COLUMN quotes.issue_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quotes.validity_end_date IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quotes.accepted_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quotes.rejected_at IS \'(DC2Type:date_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quotes.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quotes.updated_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN quote_events.occurred_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS fk_invoices_source_quote');
        $this->addSql('ALTER TABLE invoices DROP CONSTRAINT IF EXISTS fk_invoices_parent_invoice');
        $this->addSql('ALTER TABLE invoices DROP COLUMN IF EXISTS type');
        $this->addSql('ALTER TABLE invoices DROP COLUMN IF EXISTS source_quote_id');
        $this->addSql('ALTER TABLE invoices DROP COLUMN IF EXISTS parent_invoice_id');

        $this->addSql('DROP TABLE IF EXISTS quote_events');
        $this->addSql('DROP TABLE IF EXISTS quote_lines');
        $this->addSql('DROP TABLE IF EXISTS quotes');

        $this->addSql('ALTER TABLE companies DROP COLUMN IF EXISTS last_quote_number');
        $this->addSql('ALTER TABLE companies DROP COLUMN IF EXISTS last_quote_year');
    }
}
