<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Ajoute le nom du provider Open Banking sur les connexions bancaires.
 */
final class Version20260410130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la colonne provider_name a bank_connections';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE bank_connections ADD provider_name VARCHAR(50) NOT NULL DEFAULT 'yapily'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE bank_connections DROP COLUMN provider_name');
    }
}
