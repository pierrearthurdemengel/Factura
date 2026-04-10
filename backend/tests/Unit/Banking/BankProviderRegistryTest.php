<?php

namespace App\Tests\Unit\Banking;

use App\Banking\BankProviderRegistry;
use App\Banking\Provider\BankProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Tests du registre de providers Open Banking.
 */
class BankProviderRegistryTest extends TestCase
{
    public function testGetProviderReturnsNamedProvider(): void
    {
        $yapily = $this->createProviderStub('yapily');
        $bridge = $this->createProviderStub('bridge');

        $registry = new BankProviderRegistry([$yapily, $bridge]);

        $this->assertSame($yapily, $registry->getProvider('yapily'));
        $this->assertSame($bridge, $registry->getProvider('bridge'));
    }

    public function testGetProviderThrowsOnUnknown(): void
    {
        $registry = new BankProviderRegistry([$this->createProviderStub('yapily')]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Provider bancaire "unknown" inconnu');

        $registry->getProvider('unknown');
    }

    public function testGetAllProvidersReturnsOrderedByPriority(): void
    {
        $bridge = $this->createProviderStub('bridge');
        $yapily = $this->createProviderStub('yapily');

        // bridge enregistre avant yapily, mais priorite = yapily d'abord
        $registry = new BankProviderRegistry(
            [$bridge, $yapily],
            ['yapily', 'bridge'],
        );

        $all = $registry->getAllProviders();

        $this->assertCount(2, $all);
        $this->assertSame('yapily', $all[0]->getName());
        $this->assertSame('bridge', $all[1]->getName());
    }

    public function testGetAllProvidersAppendsUnprioritized(): void
    {
        $yapily = $this->createProviderStub('yapily');
        $bridge = $this->createProviderStub('bridge');
        $custom = $this->createProviderStub('custom');

        $registry = new BankProviderRegistry(
            [$custom, $bridge, $yapily],
            ['yapily', 'bridge'],
        );

        $all = $registry->getAllProviders();

        $this->assertCount(3, $all);
        $this->assertSame('yapily', $all[0]->getName());
        $this->assertSame('bridge', $all[1]->getName());
        $this->assertSame('custom', $all[2]->getName());
    }

    public function testGetProviderForBankReturnsFirstSupporting(): void
    {
        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('isBankSupported')->with('BNP_FR')->willReturn(false);

        $bridge = $this->createMock(BankProviderInterface::class);
        $bridge->method('getName')->willReturn('bridge');
        $bridge->method('isBankSupported')->with('BNP_FR')->willReturn(true);

        $registry = new BankProviderRegistry([$yapily, $bridge]);

        $this->assertSame($bridge, $registry->getProviderForBank('BNP_FR', 'FR'));
    }

    public function testGetProviderForBankThrowsWhenNoneSupports(): void
    {
        $yapily = $this->createMock(BankProviderInterface::class);
        $yapily->method('getName')->willReturn('yapily');
        $yapily->method('isBankSupported')->willReturn(false);

        $registry = new BankProviderRegistry([$yapily]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucun provider ne supporte la banque');

        $registry->getProviderForBank('UNKNOWN_BANK', 'FR');
    }

    private function createProviderStub(string $name): BankProviderInterface
    {
        $provider = $this->createStub(BankProviderInterface::class);
        $provider->method('getName')->willReturn($name);

        return $provider;
    }
}
