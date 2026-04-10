<?php

namespace App\Tests\Unit\Controller;

use App\Controller\AuthController;
use App\Entity\User;
use App\Service\Auth\AuthTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;

class AuthControllerTest extends TestCase
{
    private AuthTokenService&MockObject $authTokenService;
    private JWTTokenManagerInterface&MockObject $jwtManager;
    private AuthController $controller;

    protected function setUp(): void
    {
        $this->authTokenService = $this->createMock(AuthTokenService::class);
        $this->jwtManager = $this->createMock(JWTTokenManagerInterface::class);

        $this->controller = new AuthController(
            $this->authTokenService,
            $this->jwtManager,
            new NullLogger(),
        );
    }

    // --- refresh() ---

    public function testRefreshReturns401WhenCookieMissing(): void
    {
        $request = Request::create('/api/auth/refresh', 'POST');

        $response = $this->controller->refresh($request);

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame('Refresh token manquant.', $body['error']);
    }

    public function testRefreshReturns401WhenCookieEmpty(): void
    {
        $request = Request::create('/api/auth/refresh', 'POST');
        $request->cookies->set('refresh_token', '');

        $response = $this->controller->refresh($request);

        self::assertSame(401, $response->getStatusCode());
    }

    public function testRefreshReturns401WhenTokenInvalid(): void
    {
        $request = Request::create('/api/auth/refresh', 'POST');
        $request->cookies->set('refresh_token', 'invalid_token_value');

        $this->authTokenService->method('rotateRefreshToken')->willReturn(null);

        $response = $this->controller->refresh($request);

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame('Refresh token invalide ou expire.', $body['error']);
    }

    public function testRefreshClearsCookieOnInvalidToken(): void
    {
        $request = Request::create('/api/auth/refresh', 'POST');
        $request->cookies->set('refresh_token', 'expired_token');

        $this->authTokenService->method('rotateRefreshToken')->willReturn(null);

        $response = $this->controller->refresh($request);

        // Le cookie doit etre supprime (valeur vide ou expire)
        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ('refresh_token' === $cookie->getName()) {
                $found = true;
                // Un cookie supprime a une date d'expiration dans le passe
                self::assertLessThan(time(), $cookie->getExpiresTime());
            }
        }
        self::assertTrue($found, 'Le cookie refresh_token devrait etre present (supprime)');
    }

    public function testRefreshReturnsNewJwtAndRotatesToken(): void
    {
        $user = $this->createUser();

        $request = Request::create('/api/auth/refresh', 'POST');
        $request->cookies->set('refresh_token', 'valid_old_token');

        $this->authTokenService->method('rotateRefreshToken')->willReturn([
            'user' => $user,
            'newToken' => 'new_raw_refresh_token',
        ]);
        $this->jwtManager->method('create')->with($user)->willReturn('new_jwt_access_token');

        $response = $this->controller->refresh($request);

        self::assertSame(200, $response->getStatusCode());

        // Le corps doit contenir le nouveau JWT
        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame('new_jwt_access_token', $body['token']);
    }

    public function testRefreshSetsNewCookieWithRotatedToken(): void
    {
        $user = $this->createUser();

        $request = Request::create('/api/auth/refresh', 'POST');
        $request->cookies->set('refresh_token', 'old_token');

        $this->authTokenService->method('rotateRefreshToken')->willReturn([
            'user' => $user,
            'newToken' => 'rotated_refresh_token',
        ]);
        $this->jwtManager->method('create')->willReturn('jwt');

        $response = $this->controller->refresh($request);

        $cookies = $response->headers->getCookies();
        self::assertCount(1, $cookies);

        $cookie = $cookies[0];
        self::assertSame('refresh_token', $cookie->getName());
        self::assertSame('rotated_refresh_token', $cookie->getValue());
        self::assertTrue($cookie->isHttpOnly());
        self::assertTrue($cookie->isSecure());
        self::assertSame('strict', $cookie->getSameSite());
        self::assertSame('/api/auth', $cookie->getPath());
    }

    // --- logout() ---

    public function testLogoutRevokesTokenWhenPresent(): void
    {
        $request = Request::create('/api/auth/logout', 'POST');
        $request->cookies->set('refresh_token', 'token_to_revoke');

        $this->authTokenService->expects(self::once())
            ->method('revokeToken')
            ->with('token_to_revoke');

        $response = $this->controller->logout($request);

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame('Deconnecte.', $body['message']);
    }

    public function testLogoutClearsCookie(): void
    {
        $request = Request::create('/api/auth/logout', 'POST');
        $request->cookies->set('refresh_token', 'some_token');

        $response = $this->controller->logout($request);

        $cookies = $response->headers->getCookies();
        $found = false;
        foreach ($cookies as $cookie) {
            if ('refresh_token' === $cookie->getName()) {
                $found = true;
                self::assertLessThan(time(), $cookie->getExpiresTime());
            }
        }
        self::assertTrue($found);
    }

    public function testLogoutHandlesMissingCookieGracefully(): void
    {
        $request = Request::create('/api/auth/logout', 'POST');

        // Ne doit PAS appeler revokeToken si le cookie est absent
        $this->authTokenService->expects(self::never())->method('revokeToken');

        $response = $this->controller->logout($request);

        self::assertSame(200, $response->getStatusCode());
    }

    public function testLogoutHandlesEmptyCookieGracefully(): void
    {
        $request = Request::create('/api/auth/logout', 'POST');
        $request->cookies->set('refresh_token', '');

        $this->authTokenService->expects(self::never())->method('revokeToken');

        $response = $this->controller->logout($request);

        self::assertSame(200, $response->getStatusCode());
    }

    private function createUser(): User
    {
        $user = new User();
        $user->setEmail('test@example.com');
        $user->setFirstName('Test');
        $user->setLastName('User');
        $user->setPassword('hashed');

        return $user;
    }
}
