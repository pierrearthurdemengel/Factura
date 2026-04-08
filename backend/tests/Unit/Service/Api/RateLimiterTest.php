<?php

namespace App\Tests\Unit\Service\Api;

use App\Entity\ApiKey;
use App\Service\Api\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private function createApiKey(string $plan = ApiKey::PLAN_FREE): ApiKey
    {
        $apiKey = new ApiKey();
        $apiKey->setName('Test');
        $apiKey->setKeyHash(bin2hex(random_bytes(32)));
        $apiKey->setKeyPrefix('mfp_live_test');
        $apiKey->setPlan($plan);

        return $apiKey;
    }

    public function testCheckAllowsRequestUnderLimit(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey(ApiKey::PLAN_FREE);

        $result = $limiter->check($apiKey);

        self::assertTrue($result['allowed']);
        self::assertSame(100, $result['limit']);
        self::assertSame(99, $result['remaining']);
    }

    public function testCheckDecreasesRemaining(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey(ApiKey::PLAN_FREE);

        $limiter->check($apiKey);
        $result = $limiter->check($apiKey);

        self::assertTrue($result['allowed']);
        self::assertSame(98, $result['remaining']);
    }

    public function testCheckBlocksWhenLimitReached(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey(ApiKey::PLAN_FREE);

        // Epuiser les 100 requetes
        for ($i = 0; $i < 100; ++$i) {
            $limiter->check($apiKey);
        }

        $result = $limiter->check($apiKey);

        self::assertFalse($result['allowed']);
        self::assertSame(0, $result['remaining']);
    }

    public function testCheckProPlanHasHigherLimit(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey(ApiKey::PLAN_PRO);

        $result = $limiter->check($apiKey);

        self::assertTrue($result['allowed']);
        self::assertSame(1000, $result['limit']);
        self::assertSame(999, $result['remaining']);
    }

    public function testCheckTeamPlanHasHighestLimit(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey(ApiKey::PLAN_TEAM);

        $result = $limiter->check($apiKey);

        self::assertSame(10000, $result['limit']);
    }

    public function testCheckIncludesResetTime(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey();

        $result = $limiter->check($apiKey);

        // Le resetAt doit etre dans environ 1 heure
        self::assertGreaterThan(time(), $result['resetAt']);
        self::assertLessThanOrEqual(time() + 3601, $result['resetAt']);
    }

    public function testGetStatusDoesNotIncrement(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey();

        $status1 = $limiter->getStatus($apiKey);
        $status2 = $limiter->getStatus($apiKey);

        self::assertSame($status1['remaining'], $status2['remaining']);
    }

    public function testResetClearsCounter(): void
    {
        $limiter = new RateLimiter();
        $apiKey = $this->createApiKey();

        // Consommer quelques requetes
        for ($i = 0; $i < 50; ++$i) {
            $limiter->check($apiKey);
        }

        $limiter->reset($apiKey);

        $result = $limiter->check($apiKey);
        self::assertTrue($result['allowed']);
        self::assertSame(99, $result['remaining']);
    }

    public function testDifferentKeysHaveIndependentLimits(): void
    {
        $limiter = new RateLimiter();
        $key1 = $this->createApiKey();
        $key2 = $this->createApiKey();

        // Epuiser key1
        for ($i = 0; $i < 100; ++$i) {
            $limiter->check($key1);
        }

        // key2 doit encore fonctionner
        $result = $limiter->check($key2);
        self::assertTrue($result['allowed']);
    }
}
