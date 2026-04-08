<?php

namespace App\Tests\Unit\Service\Api;

use App\Entity\ApiKey;
use App\Entity\Company;
use App\Entity\User;
use App\Service\Api\ApiKeyManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class ApiKeyManagerTest extends TestCase
{
    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@test.fr');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        return $user;
    }

    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Entreprise Test');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }

    private function createManager(): ApiKeyManager
    {
        $em = $this->createMock(EntityManagerInterface::class);

        return new ApiKeyManager($em);
    }

    public function testGenerateReturnsApiKeyAndPlainKey(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $manager = new ApiKeyManager($em);
        $result = $manager->generate($this->createUser(), $this->createCompany(), 'Test Key');

        self::assertInstanceOf(ApiKey::class, $result['apiKey']);
        self::assertNotEmpty($result['plainKey']);
        self::assertStringStartsWith('mfp_live_', $result['plainKey']);
    }

    public function testGenerateKeyHasSha256Hash(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $manager = new ApiKeyManager($em);

        $result = $manager->generate($this->createUser(), $this->createCompany(), 'Test Key');
        $expectedHash = hash('sha256', $result['plainKey']);

        self::assertSame($expectedHash, $result['apiKey']->getKeyHash());
    }

    public function testGenerateKeyHasPrefix(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $manager = new ApiKeyManager($em);

        $result = $manager->generate($this->createUser(), $this->createCompany(), 'Test Key');

        self::assertSame(substr($result['plainKey'], 0, 15), $result['apiKey']->getKeyPrefix());
    }

    public function testGenerateKeyWithPlan(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $manager = new ApiKeyManager($em);

        $result = $manager->generate($this->createUser(), $this->createCompany(), 'Team Key', ApiKey::PLAN_TEAM);

        self::assertSame(ApiKey::PLAN_TEAM, $result['apiKey']->getPlan());
        self::assertSame(10000, $result['apiKey']->getRateLimit());
    }

    public function testGenerateKeyWithScopes(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $manager = new ApiKeyManager($em);

        $scopes = ['invoices:read', 'invoices:write'];
        $result = $manager->generate($this->createUser(), $this->createCompany(), 'Scoped Key', ApiKey::PLAN_PRO, $scopes);

        self::assertSame($scopes, $result['apiKey']->getScopes());
    }

    public function testGenerateTwoKeysAreDifferent(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $manager = new ApiKeyManager($em);

        $result1 = $manager->generate($this->createUser(), $this->createCompany(), 'Key 1');
        $result2 = $manager->generate($this->createUser(), $this->createCompany(), 'Key 2');

        self::assertNotSame($result1['plainKey'], $result2['plainKey']);
        self::assertNotSame($result1['apiKey']->getKeyHash(), $result2['apiKey']->getKeyHash());
    }

    public function testValidateReturnsApiKeyForValidKey(): void
    {
        $apiKey = new ApiKey();
        $apiKey->setName('Test');
        $apiKey->setKeyHash(hash('sha256', 'mfp_live_testkey123'));
        $apiKey->setKeyPrefix('mfp_live_testke');

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($apiKey);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $manager = new ApiKeyManager($em);
        $result = $manager->validate('mfp_live_testkey123');

        self::assertNotNull($result);
        self::assertSame(1, $result->getRequestCount());
    }

    public function testValidateReturnsNullForUnknownKey(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $manager = new ApiKeyManager($em);
        $result = $manager->validate('mfp_live_unknown');

        self::assertNull($result);
    }

    public function testValidateReturnsNullForInactiveKey(): void
    {
        $apiKey = new ApiKey();
        $apiKey->setName('Test');
        $apiKey->setKeyHash(hash('sha256', 'mfp_live_inactive'));
        $apiKey->setKeyPrefix('mfp_live_inacti');
        $apiKey->setActive(false);

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($apiKey);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $manager = new ApiKeyManager($em);
        $result = $manager->validate('mfp_live_inactive');

        self::assertNull($result);
    }

    public function testValidateReturnsNullForExpiredKey(): void
    {
        $apiKey = new ApiKey();
        $apiKey->setName('Test');
        $apiKey->setKeyHash(hash('sha256', 'mfp_live_expired'));
        $apiKey->setKeyPrefix('mfp_live_expire');
        $apiKey->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($apiKey);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $manager = new ApiKeyManager($em);
        $result = $manager->validate('mfp_live_expired');

        self::assertNull($result);
    }

    public function testRevokeDisablesKey(): void
    {
        $apiKey = new ApiKey();
        $apiKey->setName('Test');
        $apiKey->setKeyHash('test');
        $apiKey->setKeyPrefix('test');

        self::assertTrue($apiKey->isActive());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $manager = new ApiKeyManager($em);
        $manager->revoke($apiKey);

        self::assertFalse($apiKey->isActive());
    }

    public function testRateLimitsByPlan(): void
    {
        self::assertSame(100, ApiKey::RATE_LIMITS[ApiKey::PLAN_FREE]);
        self::assertSame(1000, ApiKey::RATE_LIMITS[ApiKey::PLAN_PRO]);
        self::assertSame(10000, ApiKey::RATE_LIMITS[ApiKey::PLAN_TEAM]);
    }
}
