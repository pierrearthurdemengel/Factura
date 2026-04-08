<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 19 : tables pour l'affacturage instantane.
 * - factoring_requests : demandes de financement de factures
 * - factoring_events : journal d'audit des operations d'affacturage.
 */
final class Version20260408190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables factoring_requests et factoring_events pour l\'affacturage instantane';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE factoring_requests (
            id UUID NOT NULL,
            invoice_id UUID NOT NULL,
            company_id UUID NOT NULL,
            partner_id VARCHAR(50) NOT NULL,
            amount BIGINT NOT NULL,
            fee BIGINT NOT NULL,
            commission BIGINT DEFAULT 0 NOT NULL,
            status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL,
            partner_reference_id VARCHAR(255) DEFAULT NULL,
            client_score INT NOT NULL,
            rejection_reason TEXT DEFAULT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            approved_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            paid_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE INDEX idx_factoring_status ON factoring_requests (status)');
        $this->addSql('CREATE INDEX idx_factoring_partner ON factoring_requests (partner_id)');
        $this->addSql('CREATE INDEX idx_factoring_invoice ON factoring_requests (invoice_id)');
        $this->addSql('CREATE INDEX idx_factoring_company ON factoring_requests (company_id)');

        $this->addSql('ALTER TABLE factoring_requests ADD CONSTRAINT fk_factoring_invoice FOREIGN KEY (invoice_id) REFERENCES invoices (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE factoring_requests ADD CONSTRAINT fk_factoring_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN factoring_requests.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.invoice_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.approved_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.paid_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN factoring_requests.updated_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE factoring_events (
            id UUID NOT NULL,
            factoring_request_id UUID NOT NULL,
            event_type VARCHAR(50) NOT NULL,
            payload JSON NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE INDEX idx_factoring_event_request ON factoring_events (factoring_request_id)');

        $this->addSql('ALTER TABLE factoring_events ADD CONSTRAINT fk_factoring_event_request FOREIGN KEY (factoring_request_id) REFERENCES factoring_requests (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN factoring_events.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN factoring_events.factoring_request_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN factoring_events.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE factoring_events');
        $this->addSql('DROP TABLE factoring_requests');
    }
}
