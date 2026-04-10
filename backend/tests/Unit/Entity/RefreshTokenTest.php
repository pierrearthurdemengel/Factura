<?php

namespace App\Tests\Unit\Entity;

use App\Entity\RefreshToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class RefreshTokenTest extends TestCase
{
    public function testNewTokenHasUuidAndDates(): void
    {
        $token = new RefreshToken();

        self::assertNotNull($token->getId());
        // Verifie que les dates sont bien initialisees dans le constructeur
        self::assertInstanceOf(\DateTimeImmutable::class, $token->getCreatedAt());
        self::assertInstanceOf(\DateTimeImmutable::class, $token->getExpiresAt());
    }

    public function testDefaultExpiresIn30Days(): void
    {
        $token = new RefreshToken();

        $now = new \DateTimeImmutable();
        $diff = $token->getExpiresAt()->diff($now);

        // Entre 29 et 31 jours pour absorber le temps d'execution
        self::assertGreaterThanOrEqual(29, $diff->days);
        self::assertLessThanOrEqual(31, $diff->days);
    }

    public function testIsNotExpiredWhenFresh(): void
    {
        $token = new RefreshToken();

        self::assertFalse($token->isExpired());
    }

    public function testIsExpiredWhenPastExpiresAt(): void
    {
        $token = new RefreshToken();
        $token->setExpiresAt(new \DateTimeImmutable('-1 hour'));

        self::assertTrue($token->isExpired());
    }

    public function testIsNotRevokedByDefault(): void
    {
        $token = new RefreshToken();

        self::assertFalse($token->isRevoked());
        self::assertNull($token->getRevokedAt());
    }

    public function testRevokeMarksAsRevoked(): void
    {
        $token = new RefreshToken();
        $token->revoke();

        self::assertTrue($token->isRevoked());
        self::assertNotNull($token->getRevokedAt());
    }

    public function testIsValidWhenNotExpiredAndNotRevoked(): void
    {
        $token = new RefreshToken();

        self::assertTrue($token->isValid());
    }

    public function testIsInvalidWhenExpired(): void
    {
        $token = new RefreshToken();
        $token->setExpiresAt(new \DateTimeImmutable('-1 second'));

        self::assertFalse($token->isValid());
    }

    public function testIsInvalidWhenRevoked(): void
    {
        $token = new RefreshToken();
        $token->revoke();

        self::assertFalse($token->isValid());
    }

    public function testIsInvalidWhenBothExpiredAndRevoked(): void
    {
        $token = new RefreshToken();
        $token->setExpiresAt(new \DateTimeImmutable('-1 hour'));
        $token->revoke();

        self::assertFalse($token->isValid());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        $token = new RefreshToken();
        $result = $token->setUser($user);

        self::assertSame($user, $token->getUser());
        self::assertSame($token, $result);
    }

    public function testSetAndGetTokenHash(): void
    {
        $token = new RefreshToken();
        $hash = hash('sha256', 'raw_token_value');
        $result = $token->setTokenHash($hash);

        self::assertSame($hash, $token->getTokenHash());
        self::assertSame($token, $result);
    }

    public function testSetAndGetDeviceInfo(): void
    {
        $token = new RefreshToken();

        self::assertNull($token->getDeviceInfo());

        $token->setDeviceInfo('192.168.1.xxx | Mozilla/5.0');

        self::assertSame('192.168.1.xxx | Mozilla/5.0', $token->getDeviceInfo());
    }

    public function testSetExpiresAt(): void
    {
        $token = new RefreshToken();
        $custom = new \DateTimeImmutable('+7 days');
        $result = $token->setExpiresAt($custom);

        self::assertSame($custom, $token->getExpiresAt());
        self::assertSame($token, $result);
    }
}
