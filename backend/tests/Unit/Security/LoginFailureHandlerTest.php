<?php

namespace App\Tests\Unit\Security;

use App\Security\LoginFailureHandler;
use App\Security\LoginThrottler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class LoginFailureHandlerTest extends TestCase
{
    private LoginThrottler&MockObject $throttler;
    private LoginFailureHandler $handler;

    protected function setUp(): void
    {
        $this->throttler = $this->createMock(LoginThrottler::class);
        $this->handler = new LoginFailureHandler($this->throttler, new NullLogger());
    }

    public function testReturns401WithGenericMessage(): void
    {
        $request = Request::create('/api/auth/login', 'POST');
        $exception = new BadCredentialsException();

        $response = $this->handler->onAuthenticationFailure($request, $exception);

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame('Identifiants invalides.', $body['error']);
    }

    public function testDoesNotLeakExceptionDetails(): void
    {
        $request = Request::create('/api/auth/login', 'POST');
        $exception = new AuthenticationException('User "attacker@evil.com" not found.');

        $response = $this->handler->onAuthenticationFailure($request, $exception);

        $body = json_decode($response->getContent() ?: '', true);
        // Le message ne doit PAS contenir les details de l'exception
        self::assertStringNotContainsString('attacker@evil.com', $body['error']);
        self::assertStringNotContainsString('not found', $body['error']);
    }

    public function testRecordsFailedAttemptInThrottler(): void
    {
        $request = Request::create('/api/auth/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $exception = new BadCredentialsException();

        $this->throttler->expects(self::once())
            ->method('recordFailedAttempt')
            ->with('10.0.0.1');

        $this->handler->onAuthenticationFailure($request, $exception);
    }

    public function testUsesFallbackIpWhenMissing(): void
    {
        // Requete sans REMOTE_ADDR
        $request = Request::create('/api/auth/login', 'POST');

        $this->throttler->expects(self::once())
            ->method('recordFailedAttempt')
            ->with(self::isType('string'));

        $this->handler->onAuthenticationFailure($request, new BadCredentialsException());
    }
}
