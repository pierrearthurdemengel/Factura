<?php

namespace App\Tests\Unit\Security;

use App\Security\LoginThrottler;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class LoginThrottlerTest extends TestCase
{
    private LoginThrottler $throttler;

    protected function setUp(): void
    {
        $this->throttler = new LoginThrottler(new NullLogger());
    }

    public function testIsNotBlockedInitially(): void
    {
        // IP aleatoire pour eviter les collisions entre tests
        $ip = '10.0.0.' . random_int(1, 254);
        self::assertFalse($this->throttler->isBlocked($ip));
    }

    public function testBlocksAfterMaxAttempts(): void
    {
        $ip = '10.1.0.' . random_int(1, 254);

        // 5 tentatives echouees
        for ($i = 0; $i < 5; ++$i) {
            $this->throttler->recordFailedAttempt($ip);
        }

        self::assertTrue($this->throttler->isBlocked($ip));
    }

    public function testAllowsBeforeMaxAttempts(): void
    {
        $ip = '10.2.0.' . random_int(1, 254);

        // 4 tentatives (en dessous du seuil de 5)
        for ($i = 0; $i < 4; ++$i) {
            $this->throttler->recordFailedAttempt($ip);
        }

        self::assertFalse($this->throttler->isBlocked($ip));
    }

    public function testResetAttemptsUnblocks(): void
    {
        $ip = '10.3.0.' . random_int(1, 254);

        for ($i = 0; $i < 5; ++$i) {
            $this->throttler->recordFailedAttempt($ip);
        }

        self::assertTrue($this->throttler->isBlocked($ip));

        $this->throttler->resetAttempts($ip);

        self::assertFalse($this->throttler->isBlocked($ip));
    }
}
