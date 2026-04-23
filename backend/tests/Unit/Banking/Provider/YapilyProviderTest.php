<?php

namespace App\Tests\Unit\Banking\Provider;

use App\Banking\DTO\AuthorizationStatus;
use App\Banking\DTO\BalanceType;
use App\Banking\DTO\TransactionType;
use App\Banking\Exception\ProviderUnavailableException;
use App\Banking\Exception\UnsupportedBankException;
use App\Banking\Provider\YapilyProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Tests du provider Yapily avec des reponses HTTP simulees.
 */
class YapilyProviderTest extends TestCase
{
    public function testGetNameReturnsYapily(): void
    {
        $provider = $this->createProvider(new MockResponse('{}'));
        $this->assertSame('yapily', $provider->getName());
    }

    public function testGetAvailableBanksMapsInstitutions(): void
    {
        $responseBody = json_encode([
            'data' => [
                [
                    'id' => 'bnp-paribas',
                    'name' => 'BNP Paribas',
                    'media' => [['source' => 'https://logo.example.com/bnp.png']],
                ],
                [
                    'id' => 'societe-generale',
                    'name' => 'Societe Generale',
                    'media' => [],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        // Le cache doit appeler le callback directement
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class)),
        );

        $provider = $this->createProviderWithCache(new MockResponse($responseBody), $cache);

        $banks = $provider->getAvailableBanks('FR');

        $this->assertCount(2, $banks);
        $this->assertSame('bnp-paribas', $banks[0]->id);
        $this->assertSame('BNP Paribas', $banks[0]->name);
        $this->assertSame('https://logo.example.com/bnp.png', $banks[0]->logoUrl);
        $this->assertSame('yapily', $banks[0]->providerName);
        $this->assertNull($banks[1]->logoUrl);
    }

    public function testIsBankSupportedReturnsTrueForKnownBank(): void
    {
        $responseBody = json_encode(['id' => 'bnp-paribas', 'name' => 'BNP'], JSON_THROW_ON_ERROR);
        $provider = $this->createProvider(new MockResponse($responseBody));

        $this->assertTrue($provider->isBankSupported('bnp-paribas'));
    }

    public function testIsBankSupportedReturnsFalseOnError(): void
    {
        $response = new MockResponse('', ['http_code' => 404]);
        $provider = $this->createProvider($response);

        $this->assertFalse($provider->isBankSupported('unknown'));
    }

    public function testCreateUserAuthorizationReturnsResult(): void
    {
        $responses = [
            // Appel isBankSupported
            new MockResponse(json_encode(['id' => 'bnp-paribas'], JSON_THROW_ON_ERROR)),
            // Appel createUserAuthorization
            new MockResponse(json_encode([
                'data' => [
                    'id' => 'auth-uuid-001',
                    'authorisationUrl' => 'https://yapily.com/auth/redirect',
                    'expiresAt' => '2026-07-09T12:00:00Z',
                ],
            ], JSON_THROW_ON_ERROR)),
        ];

        $provider = $this->createProviderMulti($responses);

        $result = $provider->createUserAuthorization('user-1', 'bnp-paribas', 'https://app.test/callback');

        $this->assertSame('auth-uuid-001', $result->authorizationId);
        $this->assertSame('https://yapily.com/auth/redirect', $result->redirectUrl);
        $this->assertSame(AuthorizationStatus::Pending, $result->status);
        $this->assertSame('yapily', $result->providerName);
    }

    public function testCreateUserAuthorizationThrowsOnUnsupportedBank(): void
    {
        // isBankSupported retourne 404
        $provider = $this->createProvider(new MockResponse('', ['http_code' => 404]));

        $this->expectException(UnsupportedBankException::class);

        $provider->createUserAuthorization('user-1', 'unknown-bank', 'https://app.test/callback');
    }

    public function testGetAccountsMapsResponse(): void
    {
        $responseBody = json_encode([
            'data' => [
                [
                    'id' => 'acc-001',
                    'accountIdentifications' => [['identification' => 'FR7630001007941234567890185']],
                    'currency' => 'EUR',
                    'type' => 'CURRENT',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $provider = $this->createProvider(new MockResponse($responseBody));

        $accounts = $provider->getAccounts('auth-123');

        $this->assertCount(1, $accounts);
        $this->assertSame('acc-001', $accounts[0]->id);
        $this->assertSame('FR7630001007941234567890185', $accounts[0]->iban);
        $this->assertSame('yapily', $accounts[0]->providerName);
    }

    public function testGetTransactionsMapsResponse(): void
    {
        $responseBody = json_encode([
            'data' => [
                [
                    'id' => 'tx-001',
                    'amount' => 150.50,
                    'currency' => 'EUR',
                    'date' => '2026-04-01',
                    'description' => 'Virement entrant',
                    'reference' => 'REF-001',
                    'enrichment' => ['categorisation' => ['category' => 'INCOME']],
                ],
                [
                    'id' => 'tx-002',
                    'amount' => -42.00,
                    'currency' => 'EUR',
                    'date' => '2026-04-02',
                    'description' => 'Paiement carte',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $provider = $this->createProvider(new MockResponse($responseBody));

        $transactions = $provider->getTransactions(
            'acc-001',
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-10'),
        );

        $this->assertCount(2, $transactions);
        $this->assertSame('tx-001', $transactions[0]->id);
        $this->assertSame('150.5', $transactions[0]->amount);
        $this->assertSame(TransactionType::Credit, $transactions[0]->type);
        $this->assertSame('INCOME', $transactions[0]->category);
        $this->assertSame(TransactionType::Debit, $transactions[1]->type);
    }

    public function testGetBalancesMapsResponse(): void
    {
        $responseBody = json_encode([
            'data' => [
                [
                    'balanceAmount' => ['amount' => '5432.10', 'currency' => 'EUR'],
                    'type' => 'EXPECTED',
                    'dateTime' => '2026-04-09T08:00:00Z',
                ],
                [
                    'balanceAmount' => ['amount' => '5400.00', 'currency' => 'EUR'],
                    'type' => 'CLOSING_BOOKED',
                    'dateTime' => '2026-04-09T08:00:00Z',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $provider = $this->createProvider(new MockResponse($responseBody));

        $balances = $provider->getBalances('acc-001');

        $this->assertCount(2, $balances);
        $this->assertSame('5432.10', $balances[0]->amount);
        $this->assertSame(BalanceType::Available, $balances[0]->type);
        $this->assertSame(BalanceType::Booked, $balances[1]->type);
    }

    public function testRequestThrowsProviderUnavailableOnHttpError(): void
    {
        $provider = $this->createProvider(new MockResponse('', ['http_code' => 500]));

        $this->expectException(ProviderUnavailableException::class);

        $provider->getAccounts('auth-123');
    }

    private function createProvider(MockResponse $response): YapilyProvider
    {
        $client = new MockHttpClient($response);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class)),
        );

        return new YapilyProvider($client, $cache, new NullLogger(), 'test-app-id', 'test-app-secret');
    }

    private function createProviderWithCache(MockResponse $response, CacheInterface $cache): YapilyProvider
    {
        $client = new MockHttpClient($response);

        return new YapilyProvider($client, $cache, new NullLogger(), 'test-app-id', 'test-app-secret');
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function createProviderMulti(array $responses): YapilyProvider
    {
        $client = new MockHttpClient($responses);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class)),
        );

        return new YapilyProvider($client, $cache, new NullLogger(), 'test-app-id', 'test-app-secret');
    }
}
