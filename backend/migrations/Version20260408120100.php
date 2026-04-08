<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Phase 14 : personnalisation PDF (logo, couleurs, pied de page).
 */
final class Version20260408120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les champs de personnalisation PDF sur la table companies.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE companies ADD COLUMN logo_path VARCHAR(500) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD COLUMN primary_color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD COLUMN secondary_color VARCHAR(7) DEFAULT NULL');
        $this->addSql('ALTER TABLE companies ADD COLUMN custom_footer TEXT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE companies DROP COLUMN IF EXISTS logo_path');
        $this->addSql('ALTER TABLE companies DROP COLUMN IF EXISTS primary_color');
        $this->addSql('ALTER TABLE companies DROP COLUMN IF EXISTS secondary_color');
        $this->addSql('ALTER TABLE companies DROP COLUMN IF EXISTS custom_footer');
    }
}
