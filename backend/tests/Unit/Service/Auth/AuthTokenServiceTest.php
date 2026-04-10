<?php

namespace App\Tests\Unit\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Service\Auth\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class AuthTokenServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private AuthTokenService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->service = new AuthTokenService($this->em);
    }

    public function testCreateRefreshTokenReturnsRawToken(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        $request = Request::create('/api/auth/login', 'POST');
        $request->headers->set('User-Agent', 'PHPUnit');

        $this->em->expects(self::once())->method('persist');
        $this->em->expects(self::once())->method('flush');

        $rawToken = $this->service->createRefreshToken($user, $request);

        // Le token brut fait 64 caracteres hex (32 octets)
        self::assertSame(64, strlen($rawToken));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $rawToken);
    }

    public function testRotateRefreshTokenReturnsNullForInvalidToken(): void
    {
        /** @var EntityRepository<RefreshToken>&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $this->em->method('getRepository')->with(RefreshToken::class)->willReturn($repo);

        $request = Request::create('/api/auth/refresh', 'POST');

        $result = $this->service->rotateRefreshToken('invalid_token', $request);

        self::assertNull($result);
    }

    public function testRotateRefreshTokenRevokesOldAndReturnsNew(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        $oldToken = new RefreshToken();
        $oldToken->setUser($user);
        $oldToken->setTokenHash(hash('sha256', 'old_raw_token'));

        /** @var EntityRepository<RefreshToken>&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($oldToken);
        $this->em->method('getRepository')->with(RefreshToken::class)->willReturn($repo);

        $request = Request::create('/api/auth/refresh', 'POST');
        $request->headers->set('User-Agent', 'PHPUnit');

        $result = $this->service->rotateRefreshToken('old_raw_token', $request);

        self::assertNotNull($result);
        self::assertSame($user, $result['user']);
        self::assertSame(64, strlen($result['newToken']));
        // L'ancien token doit etre revoque
        self::assertTrue($oldToken->isRevoked());
    }

    public function testRotateRefreshTokenRevokesAllOnReuse(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        // Token deja revoque — tentative de reutilisation
        $revokedToken = new RefreshToken();
        $revokedToken->setUser($user);
        $revokedToken->setTokenHash(hash('sha256', 'reused_token'));
        $revokedToken->revoke();

        // Un autre token actif de l'utilisateur
        $activeToken = new RefreshToken();
        $activeToken->setUser($user);
        $activeToken->setTokenHash(hash('sha256', 'active_token'));

        /** @var EntityRepository<RefreshToken>&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($revokedToken);
        $repo->method('findBy')->willReturn([$revokedToken, $activeToken]);
        $this->em->method('getRepository')->with(RefreshToken::class)->willReturn($repo);

        $request = Request::create('/api/auth/refresh', 'POST');

        $result = $this->service->rotateRefreshToken('reused_token', $request);

        self::assertNull($result);
        // Le token actif doit aussi etre revoque
        self::assertTrue($activeToken->isRevoked());
    }

    public function testRevokeTokenMarksAsRevoked(): void
    {
        $token = new RefreshToken();
        $token->setTokenHash(hash('sha256', 'to_revoke'));
        $token->setUser(new User());

        /** @var EntityRepository<RefreshToken>&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($token);
        $this->em->method('getRepository')->with(RefreshToken::class)->willReturn($repo);

        $this->service->revokeToken('to_revoke');

        self::assertTrue($token->isRevoked());
    }

    public function testRevokeAllUserTokens(): void
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        $t1 = new RefreshToken();
        $t1->setUser($user);
        $t1->setTokenHash('hash1');

        $t2 = new RefreshToken();
        $t2->setUser($user);
        $t2->setTokenHash('hash2');

        /** @var EntityRepository<RefreshToken>&MockObject $repo */
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn([$t1, $t2]);
        $this->em->method('getRepository')->with(RefreshToken::class)->willReturn($repo);

        $this->service->revokeAllUserTokens($user);

        self::assertTrue($t1->isRevoked());
        self::assertTrue($t2->isRevoked());
    }
}
