<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creation de la table refresh_tokens pour le systeme de renouvellement JWT.
 */
final class Version20260410120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table refresh_tokens avec stockage de hash et support de revocation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
                id UUID NOT NULL,
                user_id UUID NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
                device_info VARCHAR(512) DEFAULT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);

        $this->addSql('CREATE UNIQUE INDEX UNIQ_9BACE7E1B74409F1 ON refresh_tokens (token_hash)');
        $this->addSql('CREATE INDEX idx_refresh_token_user_active ON refresh_tokens (user_id, revoked_at)');
        $this->addSql('CREATE INDEX idx_refresh_token_expiry ON refresh_tokens (expires_at)');

        $this->addSql('COMMENT ON COLUMN refresh_tokens.id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.user_id IS \'(DC2Type:uuid)\'');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.expires_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.revoked_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN refresh_tokens.created_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(<<<'SQL'
            ALTER TABLE refresh_tokens
            ADD CONSTRAINT FK_9BACE7E1A76ED395
            FOREIGN KEY (user_id) REFERENCES users (id)
            ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE refresh_tokens');
    }
}
