<?php

namespace App\Tests\Unit\Entity;

use App\Entity\AiActionLog;
use App\Entity\AiConnection;
use App\Entity\OAuthClient;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AiActionLogTest extends TestCase
{
    private function createLog(): AiActionLog
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

        $log = new AiActionLog();
        $log->setConnection($conn);
        $log->setUser($user);
        $log->setToolName('create_invoice');
        $log->setParameters(['clientName' => 'Acme Corp', 'lines' => []]);

        return $log;
    }

    public function testNewLogHasSuccessStatus(): void
    {
        $log = $this->createLog();

        $this->assertSame(AiActionLog::STATUS_SUCCESS, $log->getStatus());
    }

    public function testDeniedStatus(): void
    {
        $log = $this->createLog();
        $log->setStatus(AiActionLog::STATUS_DENIED);
        $log->setErrorMessage('Scope invoices:write requis');

        $this->assertSame(AiActionLog::STATUS_DENIED, $log->getStatus());
        $this->assertSame('Scope invoices:write requis', $log->getErrorMessage());
    }

    public function testErrorStatus(): void
    {
        $log = $this->createLog();
        $log->setStatus(AiActionLog::STATUS_ERROR);
        $log->setErrorMessage('Erreur interne');
        $log->setDurationMs(150);

        $this->assertSame(AiActionLog::STATUS_ERROR, $log->getStatus());
        $this->assertSame(150, $log->getDurationMs());
    }

    public function testParametersAreSanitized(): void
    {
        $log = $this->createLog();
        $params = $log->getParameters();

        $this->assertArrayHasKey('clientName', $params);
        $this->assertSame('Acme Corp', $params['clientName']);
    }

    public function testIpAddress(): void
    {
        $log = $this->createLog();
        $log->setIpAddress('192.168.1.42');

        $this->assertSame('192.168.1.42', $log->getIpAddress());
    }

    public function testToolName(): void
    {
        $log = $this->createLog();

        $this->assertSame('create_invoice', $log->getToolName());
    }

    public function testUuidIsGenerated(): void
    {
        $log = $this->createLog();

        $this->assertNotNull($log->getId());
    }

    public function testCreatedAtIsSet(): void
    {
        $log = $this->createLog();

        $this->assertNotNull($log->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $log->getCreatedAt());
    }

    public function testStatusConstants(): void
    {
        $this->assertSame('success', AiActionLog::STATUS_SUCCESS);
        $this->assertSame('denied', AiActionLog::STATUS_DENIED);
        $this->assertSame('error', AiActionLog::STATUS_ERROR);
        $this->assertSame('pending_confirmation', AiActionLog::STATUS_PENDING_CONFIRMATION);
    }
}
