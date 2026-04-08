<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 21 : tables pour le portail comptable multi-clients.
 * - accountant_profiles : profil expert-comptable
 * - accountant_invitations : invitations aux clients
 * - accountant_companies : table de liaison comptable ↔ entreprises.
 */
final class Version20260408210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Cree les tables pour le portail comptable multi-clients';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE accountant_profiles (
            id UUID NOT NULL,
            user_id UUID NOT NULL,
            firm_name VARCHAR(255) NOT NULL,
            firm_siren VARCHAR(9) DEFAULT NULL,
            logo_path VARCHAR(255) DEFAULT NULL,
            primary_color VARCHAR(7) DEFAULT NULL,
            custom_domain VARCHAR(255) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_accountant_user ON accountant_profiles (user_id)');
        $this->addSql('ALTER TABLE accountant_profiles ADD CONSTRAINT fk_accountant_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN accountant_profiles.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accountant_profiles.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accountant_profiles.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE TABLE accountant_companies (
            accountant_profile_id UUID NOT NULL,
            company_id UUID NOT NULL,
            PRIMARY KEY (accountant_profile_id, company_id)
        )');

        $this->addSql('ALTER TABLE accountant_companies ADD CONSTRAINT fk_ac_profile FOREIGN KEY (accountant_profile_id) REFERENCES accountant_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accountant_companies ADD CONSTRAINT fk_ac_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN accountant_companies.accountant_profile_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accountant_companies.company_id IS \'(DC2Type:uuid)\'');

        $this->addSql('CREATE TABLE accountant_invitations (
            id UUID NOT NULL,
            accountant_profile_id UUID NOT NULL,
            email VARCHAR(255) NOT NULL,
            token VARCHAR(128) NOT NULL,
            status VARCHAR(20) DEFAULT \'PENDING\' NOT NULL,
            company_id UUID DEFAULT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            accepted_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY (id)
        )');

        $this->addSql('CREATE UNIQUE INDEX uniq_invitation_token ON accountant_invitations (token)');
        $this->addSql('CREATE INDEX idx_invitation_token ON accountant_invitations (token)');

        $this->addSql('ALTER TABLE accountant_invitations ADD CONSTRAINT fk_invitation_profile FOREIGN KEY (accountant_profile_id) REFERENCES accountant_profiles (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE accountant_invitations ADD CONSTRAINT fk_invitation_company FOREIGN KEY (company_id) REFERENCES companies (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');

        $this->addSql('COMMENT ON COLUMN accountant_invitations.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accountant_invitations.accountant_profile_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accountant_invitations.company_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN accountant_invitations.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN accountant_invitations.accepted_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN accountant_invitations.created_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE accountant_invitations');
        $this->addSql('DROP TABLE accountant_companies');
        $this->addSql('DROP TABLE accountant_profiles');
    }
}
