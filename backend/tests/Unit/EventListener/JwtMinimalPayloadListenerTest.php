<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\User;
use App\EventListener\JwtMinimalPayloadListener;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use PHPUnit\Framework\TestCase;

class JwtMinimalPayloadListenerTest extends TestCase
{
    public function testStripsPersonalDataFromPayload(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');
        $user->setRoles(['ROLE_ADMIN']);

        // Payload par defaut de Lexik (simule)
        $payload = [
            'email' => 'test@example.com',
            'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
            'iat' => 1700000000,
            'exp' => 1700000900,
        ];

        $event = new JWTCreatedEvent($payload, $user);

        $listener = new JwtMinimalPayloadListener();
        $listener($event);

        $result = $event->getData();

        // Ne doit contenir que sub, iat, exp
        self::assertArrayHasKey('sub', $result);
        self::assertArrayHasKey('iat', $result);
        self::assertArrayHasKey('exp', $result);
        self::assertCount(3, $result);

        // sub doit etre l'email (identifiant utilisateur)
        self::assertSame('test@example.com', $result['sub']);

        // Pas de donnees personnelles
        self::assertArrayNotHasKey('email', $result);
        self::assertArrayNotHasKey('roles', $result);
        self::assertArrayNotHasKey('username', $result);
    }
}
