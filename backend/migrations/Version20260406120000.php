<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Creation des tables OAuth 2.1 pour les integrations LLM.
 */
final class Version20260406120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les tables OAuth 2.1 : clients, access tokens, authorization codes, refresh tokens.';
    }

    public function up(Schema $schema): void
    {
        // Table des clients OAuth (Claude, ChatGPT, etc.)
        $this->addSql('CREATE TABLE oauth_clients (
            id UUID NOT NULL,
            client_id VARCHAR(100) NOT NULL,
            client_secret VARCHAR(255) DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            redirect_uris JSON NOT NULL,
            allowed_scopes JSON NOT NULL,
            is_public BOOLEAN NOT NULL DEFAULT TRUE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id)
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_oauth_clients_client_id ON oauth_clients (client_id)');
        $this->addSql("COMMENT ON COLUMN oauth_clients.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_clients.created_at IS '(DC2Type:datetime_immutable)'");

        // Table des access tokens
        $this->addSql('CREATE TABLE oauth_access_tokens (
            id UUID NOT NULL,
            token VARCHAR(512) NOT NULL,
            client_id UUID NOT NULL,
            user_id UUID NOT NULL,
            scopes JSON NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            last_used_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_oauth_access_tokens_client FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE,
            CONSTRAINT FK_oauth_access_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_oauth_access_tokens_token ON oauth_access_tokens (token)');
        $this->addSql('CREATE INDEX idx_oauth_access_token ON oauth_access_tokens (token)');
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.client_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.user_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.revoked_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.last_used_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN oauth_access_tokens.created_at IS '(DC2Type:datetime_immutable)'");

        // Table des authorization codes (temporaires)
        $this->addSql('CREATE TABLE oauth_authorization_codes (
            id UUID NOT NULL,
            code VARCHAR(256) NOT NULL,
            client_id UUID NOT NULL,
            user_id UUID NOT NULL,
            scopes JSON NOT NULL,
            redirect_uri VARCHAR(2048) NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            code_challenge VARCHAR(256) DEFAULT NULL,
            code_challenge_method VARCHAR(10) DEFAULT NULL,
            used BOOLEAN NOT NULL DEFAULT FALSE,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_oauth_auth_codes_client FOREIGN KEY (client_id) REFERENCES oauth_clients (id) ON DELETE CASCADE,
            CONSTRAINT FK_oauth_auth_codes_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_oauth_auth_codes_code ON oauth_authorization_codes (code)');
        $this->addSql("COMMENT ON COLUMN oauth_authorization_codes.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_authorization_codes.client_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_authorization_codes.user_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_authorization_codes.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN oauth_authorization_codes.created_at IS '(DC2Type:datetime_immutable)'");

        // Table des refresh tokens
        $this->addSql('CREATE TABLE oauth_refresh_tokens (
            id UUID NOT NULL,
            token VARCHAR(512) NOT NULL,
            access_token_id UUID NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            revoked_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            PRIMARY KEY(id),
            CONSTRAINT FK_oauth_refresh_tokens_access FOREIGN KEY (access_token_id) REFERENCES oauth_access_tokens (id) ON DELETE CASCADE
        )');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_oauth_refresh_tokens_token ON oauth_refresh_tokens (token)');
        $this->addSql('CREATE INDEX idx_oauth_refresh_token ON oauth_refresh_tokens (token)');
        $this->addSql("COMMENT ON COLUMN oauth_refresh_tokens.id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_refresh_tokens.access_token_id IS '(DC2Type:uuid)'");
        $this->addSql("COMMENT ON COLUMN oauth_refresh_tokens.expires_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN oauth_refresh_tokens.revoked_at IS '(DC2Type:datetime_immutable)'");
        $this->addSql("COMMENT ON COLUMN oauth_refresh_tokens.created_at IS '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE oauth_refresh_tokens');
        $this->addSql('DROP TABLE oauth_authorization_codes');
        $this->addSql('DROP TABLE oauth_access_tokens');
        $this->addSql('DROP TABLE oauth_clients');
    }
}
