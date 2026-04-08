<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 15 : relances automatiques (reminder_configs, reminder_templates, reminder_events).
 */
final class Version20260408130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables pour le systeme de relances automatiques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reminder_configs (
            id UUID NOT NULL,
            company_id UUID NOT NULL,
            enabled BOOLEAN NOT NULL DEFAULT TRUE,
            days_before INT NOT NULL DEFAULT 3,
            days_first_reminder INT NOT NULL DEFAULT 1,
            days_second_reminder INT NOT NULL DEFAULT 7,
            days_formal_notice INT NOT NULL DEFAULT 30,
            formal_notice_enabled BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_reminder_configs_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX uniq_reminder_configs_company ON reminder_configs (company_id)');
        $this->addSql('COMMENT ON COLUMN reminder_configs.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reminder_configs.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reminder_configs.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE reminder_templates (
            id UUID NOT NULL,
            company_id UUID NOT NULL,
            type VARCHAR(30) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_reminder_templates_company FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_reminder_templates_company_type ON reminder_templates (company_id, type)');
        $this->addSql('COMMENT ON COLUMN reminder_templates.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reminder_templates.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reminder_templates.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE reminder_events (
            id UUID NOT NULL,
            invoice_id UUID NOT NULL,
            reminder_type VARCHAR(30) NOT NULL,
            recipient_email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            error_message TEXT DEFAULT NULL,
            formal_notice_path VARCHAR(500) DEFAULT NULL,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT fk_reminder_events_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE INDEX idx_reminder_invoice_date ON reminder_events (invoice_id, sent_at)');
        $this->addSql('COMMENT ON COLUMN reminder_events.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reminder_events.invoice_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN reminder_events.sent_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS reminder_events');
        $this->addSql('DROP TABLE IF EXISTS reminder_templates');
        $this->addSql('DROP TABLE IF EXISTS reminder_configs');
    }
}
