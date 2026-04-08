<?php

namespace App\Tests\Unit\Service\Assistant;

use App\Service\Assistant\FiscalKnowledgeBase;
use App\Service\Assistant\LlmClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LlmClientTest extends TestCase
{
    public function testSelectModelHaikuForTva(): void
    {
        $client = $this->createClient();

        self::assertSame(LlmClient::MODEL_HAIKU, $client->selectModel(FiscalKnowledgeBase::CATEGORY_TVA));
    }

    public function testSelectModelHaikuForMicro(): void
    {
        $client = $this->createClient();

        self::assertSame(LlmClient::MODEL_HAIKU, $client->selectModel(FiscalKnowledgeBase::CATEGORY_MICRO));
    }

    public function testSelectModelHaikuForUrssaf(): void
    {
        $client = $this->createClient();

        self::assertSame(LlmClient::MODEL_HAIKU, $client->selectModel(FiscalKnowledgeBase::CATEGORY_URSSAF));
    }

    public function testSelectModelSonnetForRegime(): void
    {
        $client = $this->createClient();

        self::assertSame(LlmClient::MODEL_SONNET, $client->selectModel(FiscalKnowledgeBase::CATEGORY_REGIME));
    }

    public function testSelectModelSonnetForIS(): void
    {
        $client = $this->createClient();

        self::assertSame(LlmClient::MODEL_SONNET, $client->selectModel(FiscalKnowledgeBase::CATEGORY_IS));
    }

    public function testSelectModelHaikuForGeneral(): void
    {
        $client = $this->createClient();

        self::assertSame(LlmClient::MODEL_HAIKU, $client->selectModel(FiscalKnowledgeBase::CATEGORY_GENERAL));
    }

    public function testIsEnabledWithApiKey(): void
    {
        $client = $this->createClient('sk-test-key');

        self::assertTrue($client->isEnabled());
    }

    public function testIsDisabledWithoutApiKey(): void
    {
        $client = $this->createClient('');

        self::assertFalse($client->isEnabled());
    }

    public function testAskReturnsFallbackWhenDisabled(): void
    {
        $client = $this->createClient('');

        $result = $client->ask('test question', FiscalKnowledgeBase::CATEGORY_TVA);

        self::assertSame('fallback', $result['model']);
        self::assertStringContainsString('tva', $result['answer']);
    }

    public function testAskFallbackContainsCategory(): void
    {
        $client = $this->createClient('');

        $result = $client->ask('test', FiscalKnowledgeBase::CATEGORY_URSSAF);

        self::assertStringContainsString('urssaf', $result['answer']);
    }

    public function testAskFallbackHasEmptyReferences(): void
    {
        $client = $this->createClient('');

        $result = $client->ask('test', FiscalKnowledgeBase::CATEGORY_GENERAL);

        self::assertEmpty($result['references']);
        self::assertEmpty($result['actions']);
    }

    private function createClient(string $apiKey = ''): LlmClient
    {
        $httpClient = $this->createMock(HttpClientInterface::class);

        return new LlmClient($httpClient, new NullLogger(), $apiKey);
    }
}
