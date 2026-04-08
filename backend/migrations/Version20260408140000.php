<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 16 : multi-entite natif.
 *
 * Transforme la relation User<->Company de OneToOne en ManyToOne
 * et ajoute le champ active_company_id sur la table users.
 */
final class Version20260408140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le support multi-entite : active_company_id sur users, relation ManyToOne.';
    }

    public function up(Schema $schema): void
    {
        // Ajouter le champ active_company_id sur users
        $this->addSql('ALTER TABLE users ADD COLUMN active_company_id UUID DEFAULT NULL');
        $this->addSql('COMMENT ON COLUMN users.active_company_id IS \'(DC2Type:uuid)\'');

        // Initialiser active_company_id avec l'entreprise existante
        $this->addSql('UPDATE users u SET active_company_id = (
            SELECT c.id FROM companies c WHERE c.owner_id = u.id LIMIT 1
        )');

        // Ajouter la FK
        $this->addSql('ALTER TABLE users ADD CONSTRAINT fk_users_active_company
            FOREIGN KEY (active_company_id) REFERENCES companies(id) ON DELETE SET NULL');

        // Supprimer la contrainte UNIQUE sur companies.owner_id (passer de OneToOne a ManyToOne)
        $this->addSql('ALTER TABLE companies DROP CONSTRAINT IF EXISTS uniq_companies_owner');
        $this->addSql('DROP INDEX IF EXISTS uniq_companies_owner');
        $this->addSql('DROP INDEX IF EXISTS uniq_8b870b051cf98720');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP CONSTRAINT IF EXISTS fk_users_active_company');
        $this->addSql('ALTER TABLE users DROP COLUMN IF EXISTS active_company_id');
        // Re-ajouter la contrainte unique serait destructif si des users ont plusieurs entreprises
    }
}
