<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Symfony\Component\Uid\Uuid;

/**
 * Pre-enregistrement des clients OAuth pour les integrations LLM.
 */
final class Version20260406120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Pre-enregistre les clients OAuth : MCP (protocole universel) et ChatGPT Custom GPT.';
    }

    public function up(Schema $schema): void
    {
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Client MCP universel (utilise par Claude et tout client compatible MCP)
        // Client public : pas de secret, PKCE obligatoire
        $this->addSql('INSERT INTO oauth_clients (id, client_id, client_secret, name, redirect_uris, allowed_scopes, is_public, created_at) VALUES (:id, :client_id, NULL, :name, :redirect_uris, :allowed_scopes, TRUE, :created_at)', [
            'id' => Uuid::v7()->toRfc4122(),
            'client_id' => 'mfp_mcp',
            'name' => 'Assistants IA (MCP)',
            'redirect_uris' => json_encode([
                'https://claude.ai/api/mcp/auth_callback',
                'https://claude.ai/oauth/callback',
                'http://localhost:*/oauth/callback',
            ]),
            'allowed_scopes' => json_encode([
                'invoices:read', 'invoices:write',
                'clients:read', 'clients:write',
                'company:read', 'stats:read',
            ]),
            'created_at' => $now,
        ]);

        // Client ChatGPT Custom GPT (OAuth classique avec secret)
        $this->addSql('INSERT INTO oauth_clients (id, client_id, client_secret, name, redirect_uris, allowed_scopes, is_public, created_at) VALUES (:id, :client_id, :client_secret, :name, :redirect_uris, :allowed_scopes, FALSE, :created_at)', [
            'id' => Uuid::v7()->toRfc4122(),
            'client_id' => 'mfp_chatgpt',
            'client_secret' => bin2hex(random_bytes(32)),
            'name' => 'ChatGPT',
            'redirect_uris' => json_encode([
                'https://chatgpt.com/aip/*/oauth/callback',
            ]),
            'allowed_scopes' => json_encode([
                'invoices:read', 'invoices:write',
                'clients:read', 'clients:write',
                'company:read', 'stats:read',
            ]),
            'created_at' => $now,
        ]);
    }

    public function down(Schema $schema): void
    {
        $this->addSql("DELETE FROM oauth_clients WHERE client_id IN ('mfp_mcp', 'mfp_chatgpt')");
    }
}
