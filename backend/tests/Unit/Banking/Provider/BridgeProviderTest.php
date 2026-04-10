<?php

namespace App\Tests\Unit\Banking\Provider;

use App\Banking\DTO\AuthorizationStatus;
use App\Banking\DTO\BalanceType;
use App\Banking\DTO\TransactionType;
use App\Banking\Exception\ProviderUnavailableException;
use App\Banking\Exception\UnsupportedBankException;
use App\Banking\Provider\BridgeProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Tests du provider Bridge avec des reponses HTTP simulees.
 */
class BridgeProviderTest extends TestCase
{
    public function testGetNameReturnsBridge(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
        ]);

        $this->assertSame('bridge', $provider->getName());
    }

    public function testGetAvailableBanksMapsResources(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse(json_encode([
                'resources' => [
                    [
                        'id' => 408,
                        'name' => 'BNP Paribas',
                        'logo_url' => 'https://bridge.example.com/bnp.png',
                    ],
                    [
                        'id' => 412,
                        'name' => 'Credit Agricole',
                        'logo_url' => null,
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $banks = $provider->getAvailableBanks('FR');

        $this->assertCount(2, $banks);
        $this->assertSame('408', $banks[0]->id);
        $this->assertSame('BNP Paribas', $banks[0]->name);
        $this->assertSame('https://bridge.example.com/bnp.png', $banks[0]->logoUrl);
        $this->assertSame('bridge', $banks[0]->providerName);
    }

    public function testIsBankSupportedReturnsTrueForKnownBank(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse(json_encode(['id' => 408], JSON_THROW_ON_ERROR)),
        ]);

        $this->assertTrue($provider->isBankSupported('408'));
    }

    public function testIsBankSupportedReturnsFalseOnError(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse('', ['http_code' => 404]),
        ]);

        $this->assertFalse($provider->isBankSupported('999'));
    }

    public function testCreateUserAuthorizationReturnsResult(): void
    {
        $provider = $this->createProvider([
            // Auth token
            $this->authResponse(),
            // isBankSupported
            new MockResponse(json_encode(['id' => 408], JSON_THROW_ON_ERROR)),
            // createUserAuthorization
            new MockResponse(json_encode([
                'id' => 'connect-item-123',
                'redirect_url' => 'https://bridge.example.com/connect',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $result = $provider->createUserAuthorization('user-1', '408', 'https://app.test/callback');

        $this->assertSame('connect-item-123', $result->authorizationId);
        $this->assertSame('https://bridge.example.com/connect', $result->redirectUrl);
        $this->assertSame(AuthorizationStatus::Pending, $result->status);
        $this->assertSame('bridge', $result->providerName);
    }

    public function testCreateUserAuthorizationThrowsOnUnsupportedBank(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse('', ['http_code' => 404]),
        ]);

        $this->expectException(UnsupportedBankException::class);

        $provider->createUserAuthorization('user-1', '999', 'https://app.test/callback');
    }

    public function testGetAccountsMapsResources(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse(json_encode([
                'resources' => [
                    [
                        'id' => 12345,
                        'iban' => 'FR7630001007941234567890185',
                        'currency_code' => 'EUR',
                        'type' => 'checking',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $accounts = $provider->getAccounts('item-001');

        $this->assertCount(1, $accounts);
        $this->assertSame('12345', $accounts[0]->id);
        $this->assertSame('FR7630001007941234567890185', $accounts[0]->iban);
        $this->assertSame('bridge', $accounts[0]->providerName);
    }

    public function testGetTransactionsMapsResources(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse(json_encode([
                'resources' => [
                    [
                        'id' => 'tx-bridge-001',
                        'amount' => 200.00,
                        'currency_code' => 'EUR',
                        'date' => '2026-04-01',
                        'description' => 'Virement entrant',
                        'raw_description' => 'VIR SEPA',
                        'bank_description' => 'REF-001',
                        'category' => ['id' => 42],
                    ],
                    [
                        'id' => 'tx-bridge-002',
                        'amount' => -35.50,
                        'currency_code' => 'EUR',
                        'date' => '2026-04-02',
                        'description' => 'Paiement CB',
                    ],
                ],
            ], JSON_THROW_ON_ERROR)),
        ]);

        $transactions = $provider->getTransactions(
            '12345',
            new \DateTimeImmutable('2026-04-01'),
            new \DateTimeImmutable('2026-04-10'),
        );

        $this->assertCount(2, $transactions);
        $this->assertSame('tx-bridge-001', $transactions[0]->id);
        $this->assertSame('200', $transactions[0]->amount);
        $this->assertSame(TransactionType::Credit, $transactions[0]->type);
        $this->assertSame('42', $transactions[0]->category);
        $this->assertSame(TransactionType::Debit, $transactions[1]->type);
        $this->assertSame('bridge', $transactions[0]->providerName);
    }

    public function testGetBalancesMapsAccountBalance(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse(json_encode([
                'balance' => 3456.78,
                'currency_code' => 'EUR',
                'updated_at' => '2026-04-09T10:00:00Z',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $balances = $provider->getBalances('12345');

        $this->assertCount(1, $balances);
        $this->assertSame('3456.78', $balances[0]->amount);
        $this->assertSame('EUR', $balances[0]->currency);
        $this->assertSame(BalanceType::Booked, $balances[0]->type);
    }

    public function testGetBalancesReturnsEmptyWhenNoBalance(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse(json_encode([
                'currency_code' => 'EUR',
            ], JSON_THROW_ON_ERROR)),
        ]);

        $balances = $provider->getBalances('12345');

        $this->assertSame([], $balances);
    }

    public function testRequestThrowsProviderUnavailableOnHttpError(): void
    {
        $provider = $this->createProvider([
            $this->authResponse(),
            new MockResponse('', ['http_code' => 500]),
        ]);

        $this->expectException(ProviderUnavailableException::class);

        $provider->getAccounts('item-001');
    }

    public function testAuthenticationFailureThrowsProviderUnavailable(): void
    {
        $provider = $this->createProvider([
            new MockResponse('', ['http_code' => 401]),
        ]);

        $this->expectException(ProviderUnavailableException::class);

        $provider->getAccounts('item-001');
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function createProvider(array $responses): BridgeProvider
    {
        $client = new MockHttpClient($responses);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn (string $key, callable $callback) => $callback($this->createStub(ItemInterface::class)),
        );

        return new BridgeProvider($client, $cache, new NullLogger(), 'test-client-id', 'test-client-secret');
    }

    private function authResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            'access_token' => 'test-bearer-token-123',
        ], JSON_THROW_ON_ERROR));
    }
}
