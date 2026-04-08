<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AiConnection;
use App\Entity\OAuthClient;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AiConnectionTest extends TestCase
{
    private function createConnection(): AiConnection
    {
        $user = new User();
        $user->setEmail('dev@test.fr');
        $user->setFirstName('Pierre');
        $user->setLastName('Test');
        $user->setPassword('hashed');

        $client = new OAuthClient();
        $client->setClientId('claude_connector');
        $client->setName('Claude');

        $conn = new AiConnection();
        $conn->setUser($user);
        $conn->setClient($client);
        $conn->setProvider(AiConnection::PROVIDER_CLAUDE);
        $conn->setGrantedScopes(['invoices:read', 'clients:read']);

        return $conn;
    }

    public function testNewConnectionIsActive(): void
    {
        $conn = $this->createConnection();

        $this->assertTrue($conn->isActive());
        $this->assertSame(AiConnection::STATUS_ACTIVE, $conn->getStatus());
    }

    public function testPauseConnection(): void
    {
        $conn = $this->createConnection();
        $conn->pause();

        $this->assertFalse($conn->isActive());
        $this->assertSame(AiConnection::STATUS_PAUSED, $conn->getStatus());
    }

    public function testRevokeConnection(): void
    {
        $conn = $this->createConnection();
        $conn->revoke();

        $this->assertFalse($conn->isActive());
        $this->assertSame(AiConnection::STATUS_REVOKED, $conn->getStatus());
        $this->assertNotNull($conn->getRevokedAt());
    }

    public function testIncrementRequests(): void
    {
        $conn = $this->createConnection();

        $this->assertSame(0, $conn->getTotalRequests());
        $this->assertNull($conn->getLastActivityAt());

        $conn->incrementRequests();
        $conn->incrementRequests();
        $conn->incrementRequests();

        $this->assertSame(3, $conn->getTotalRequests());
        $this->assertNotNull($conn->getLastActivityAt());
    }

    public function testRequireConfirmationDefaultsToTrue(): void
    {
        $conn = $this->createConnection();

        $this->assertTrue($conn->isRequireConfirmation());
    }

    public function testToggleConfirmationMode(): void
    {
        $conn = $this->createConnection();
        $conn->setRequireConfirmation(false);

        $this->assertFalse($conn->isRequireConfirmation());
    }

    public function testGrantedScopes(): void
    {
        $conn = $this->createConnection();

        $this->assertSame(['invoices:read', 'clients:read'], $conn->getGrantedScopes());
    }

    public function testProviderConstants(): void
    {
        $this->assertSame('claude', AiConnection::PROVIDER_CLAUDE);
        $this->assertSame('chatgpt', AiConnection::PROVIDER_CHATGPT);
        $this->assertSame('gemini', AiConnection::PROVIDER_GEMINI);
        $this->assertSame('custom', AiConnection::PROVIDER_CUSTOM);
    }

    public function testLabel(): void
    {
        $conn = $this->createConnection();
        $conn->setLabel('Mon assistant Claude');

        $this->assertSame('Mon assistant Claude', $conn->getLabel());
    }

    public function testUuidIsGenerated(): void
    {
        $conn = $this->createConnection();

        $this->assertNotNull($conn->getId());
    }
}
