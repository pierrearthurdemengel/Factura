<?php

namespace App\Tests\Unit\Security;

use App\Entity\User;
use App\Security\LoginSuccessHandler;
use App\Security\LoginThrottler;
use App\Service\Auth\AuthTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class LoginSuccessHandlerTest extends TestCase
{
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private AuthTokenService&MockObject $authTokenService;
    private LoginThrottler&MockObject $throttler;
    private LoginSuccessHandler $handler;

    protected function setUp(): void
    {
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);
        $this->authTokenService = $this->createMock(AuthTokenService::class);
        $this->throttler = $this->createMock(LoginThrottler::class);

        $this->handler = new LoginSuccessHandler(
            $this->jwtManager,
            $this->authTokenService,
            $this->throttler,
            new NullLogger(),
        );
    }

    public function testReturnsJwtInResponseBody(): void
    {
        $user = $this->createUser();
        $token = $this->createSecurityToken($user);
        $request = Request::create('/api/auth/login', 'POST');

        $this->jwtManager->method('create')->with($user)->willReturn('jwt_access_token');
        $this->authTokenService->method('createRefreshToken')->willReturn('raw_refresh_token');

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('jwt_access_token', $body['token']);
    }

    public function testSetsRefreshTokenCookie(): void
    {
        $user = $this->createUser();
        $token = $this->createSecurityToken($user);
        $request = Request::create('/api/auth/login', 'POST');

        $this->jwtManager->method('create')->willReturn('jwt_token');
        $this->authTokenService->method('createRefreshToken')->willReturn('raw_refresh_value');

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        $cookie = $cookies[0];
        self::assertSame('refresh_token', $cookie->getName());
        self::assertSame('raw_refresh_value', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath());
    }

    public function testCookieExpiresIn30Days(): void
    {
        $user = $this->createUser();
        $token = $this->createSecurityToken($user);
        $request = Request::create('/api/auth/login', 'POST');

        $this->jwtManager->method('create')->willReturn('jwt');
        $this->authTokenService->method('createRefreshToken')->willReturn('refresh');

        $response = $this->handler->onAuthenticationSuccess($request, $token);

        $cookie = $response->headers->getCookies()[0];
        $expiresAt = $cookie->getExpiresTime();

        // Le cookie doit expirer dans environ 30 jours (± 60 secondes)
        $expected = time() + (30 * 86400);
        self::assertEqualsWithDelta($expected, $expiresAt, 60);
    }

    public function testResetsThrottlerOnSuccess(): void
    {
        $user = $this->createUser();
        $token = $this->createSecurityToken($user);
        $request = Request::create('/api/auth/login', 'POST', [], [], [], ['REMOTE_ADDR' => '192.168.1.42']);

        $this->jwtManager->method('create')->willReturn('jwt');
        $this->authTokenService->method('createRefreshToken')->willReturn('refresh');

        $this->throttler->expects(self::once())
            ->method('resetAttempts')
            ->with('192.168.1.42');

        $this->handler->onAuthenticationSuccess($request, $token);
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('pierre@example.com');
        $user->setFirstName('Pierre');
        $user->setLastName('Dupont');
        $user->setPassword('hashed');

        return $user;
    }

    private function createSecurityToken(User $user): TokenInterface&MockObject
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($user);

        return $token;
    }
}
