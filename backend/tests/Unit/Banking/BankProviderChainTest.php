<?php

namespace App\Tests\Unit\Banking;

use App\Banking\BankProviderChain;
use App\Banking\BankProviderRegistry;
use App\Banking\DTO\AccountBalance;
use App\Banking\DTO\AuthorizationResult;
use App\Banking\DTO\AuthorizationStatus;
use App\Banking\DTO\BalanceType;
use App\Banking\DTO\BankAccountInfo;
use App\Banking\DTO\BankInfo;
use App\Banking\Exception\NoBankProviderAvailableException;
use App\Banking\Exception\ProviderUnavailableException;
use App\Banking\Exception\UnsupportedBankException;
use App\Banking\Provider\BankProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Tests du mecanisme de fallback du BankProviderChain.
 */
class BankProviderChainTest extends TestCase
{
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createStub(EventDispatcherInterface::class);
    }

    public function testFirstProviderSucceeds(): void
    {
        $expected = new AuthorizationResult(
            authorizationId: 'auth-123',
            redirectUrl: 'https://bank.example.com/auth',
            status: AuthorizationStatus::Pending,
            expiresAt: new \DateTimeImmutable('+90 days'),
            providerName: 'yapily',
        );

        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('createUserAuthorization')->willReturn($expected);

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->expects($this->never())->method('createUserAuthorization');

        $chain = $this->createChain([$yapily, $bridge]);

        $result = $chain->createUserAuthorization('user-1', 'BNP_FR', 'https://app.test/callback');

        $this->assertSame('auth-123', $result->authorizationId);
    }

    public function testFallbackOnUnsupportedBank(): void
    {
        $expected = new AuthorizationResult(
            authorizationId: 'bridge-auth-456',
            redirectUrl: 'https://bridge.example.com/auth',
            status: AuthorizationStatus::Pending,
            expiresAt: new \DateTimeImmutable('+90 days'),
            providerName: 'bridge',
        );

        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('createUserAuthorization')
            ->willThrowException(new UnsupportedBankException('BNP_FR', 'yapily'));

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('createUserAuthorization')->willReturn($expected);

        $chain = $this->createChain([$yapily, $bridge]);

        $result = $chain->createUserAuthorization('user-1', 'BNP_FR', 'https://app.test/callback');

        $this->assertSame('bridge-auth-456', $result->authorizationId);
        $this->assertSame('bridge', $result->providerName);
    }

    public function testFallbackOnProviderUnavailable(): void
    {
        $expected = [
            new BankAccountInfo(
                id: 'acc-1',
                iban: 'FR7630001007941234567890185',
                currency: 'EUR',
                type: 'checking',
                providerName: 'bridge',
                providerAccountId: 'acc-1',
            ),
        ];

        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('getAccounts')
            ->willThrowException(new ProviderUnavailableException('yapily', 'API timeout'));

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('getAccounts')->willReturn($expected);

        $chain = $this->createChain([$yapily, $bridge]);

        $result = $chain->getAccounts('auth-123');

        $this->assertCount(1, $result);
        $this->assertSame('bridge', $result[0]->providerName);
    }

    public function testThrowsNoBankProviderAvailableWhenAllFail(): void
    {
        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('getTransactions')
            ->willThrowException(new ProviderUnavailableException('yapily', 'timeout'));

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('getTransactions')
            ->willThrowException(new ProviderUnavailableException('bridge', '503'));

        $chain = $this->createChain([$yapily, $bridge]);

        $this->expectException(NoBankProviderAvailableException::class);

        $chain->getTransactions('acc-1', new \DateTimeImmutable('-30 days'), new \DateTimeImmutable());
    }

    public function testGetAvailableBanksAggregatesAllProviders(): void
    {
        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('getAvailableBanks')->willReturn([
            new BankInfo(id: 'bnp-yap', name: 'BNP Paribas', countryCode: 'FR', logoUrl: null, providerName: 'yapily'),
            new BankInfo(id: 'sg-yap', name: 'Societe Generale', countryCode: 'FR', logoUrl: null, providerName: 'yapily'),
        ]);

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('getAvailableBanks')->willReturn([
            new BankInfo(id: 'bnp-yap', name: 'BNP Paribas', countryCode: 'FR', logoUrl: null, providerName: 'bridge'),
            new BankInfo(id: 'ca-bridge', name: 'Credit Agricole', countryCode: 'FR', logoUrl: null, providerName: 'bridge'),
        ]);

        $chain = $this->createChain([$yapily, $bridge]);

        $banks = $chain->getAvailableBanks('FR');

        // 3 banques uniques (bnp-yap est un doublon par id)
        $this->assertCount(3, $banks);

        $ids = array_map(fn (BankInfo $b) => $b->id, $banks);
        $this->assertContains('bnp-yap', $ids);
        $this->assertContains('sg-yap', $ids);
        $this->assertContains('ca-bridge', $ids);
    }

    public function testGetAvailableBanksSkipsUnavailableProvider(): void
    {
        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('getAvailableBanks')
            ->willThrowException(new ProviderUnavailableException('yapily', 'down'));

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('getAvailableBanks')->willReturn([
            new BankInfo(id: 'bnp', name: 'BNP', countryCode: 'FR', logoUrl: null, providerName: 'bridge'),
        ]);

        $chain = $this->createChain([$yapily, $bridge]);

        $banks = $chain->getAvailableBanks('FR');

        $this->assertCount(1, $banks);
        $this->assertSame('bnp', $banks[0]->id);
    }

    public function testGetBalancesFallback(): void
    {
        $expected = [
            new AccountBalance(
                amount: '1234.56',
                currency: 'EUR',
                type: BalanceType::Booked,
                updatedAt: new \DateTimeImmutable(),
            ),
        ];

        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('getBalances')
            ->willThrowException(new ProviderUnavailableException('yapily', 'error'));

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('getBalances')->willReturn($expected);

        $chain = $this->createChain([$yapily, $bridge]);

        $balances = $chain->getBalances('acc-1');

        $this->assertCount(1, $balances);
        $this->assertSame('1234.56', $balances[0]->amount);
    }

    public function testRefreshConsentFallback(): void
    {
        $expected = new AuthorizationResult(
            authorizationId: 'auth-renewed',
            redirectUrl: null,
            status: AuthorizationStatus::Pending,
            expiresAt: new \DateTimeImmutable('+90 days'),
            providerName: 'bridge',
        );

        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('refreshConsent')
            ->willThrowException(new UnsupportedBankException('auth-123', 'yapily'));

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('refreshConsent')->willReturn($expected);

        $chain = $this->createChain([$yapily, $bridge]);

        $result = $chain->refreshConsent('auth-123');

        $this->assertSame('auth-renewed', $result->authorizationId);
    }

    /**
     * @param list<BankProviderInterface> $providers
     */
    private function createChain(array $providers): BankProviderChain
    {
        $names = array_map(fn (BankProviderInterface $p) => $p->getName(), $providers);
        $registry = new BankProviderRegistry($providers, $names);

        return new BankProviderChain($registry, new NullLogger(), $this->dispatcher);
    }
}
