<?php

namespace App\Tests\Unit\EventListener;

use App\EventListener\LoginRateLimitListener;
use App\Security\LoginThrottler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LoginRateLimitListenerTest extends TestCase
{
    private LoginThrottler&MockObject $throttler;
    private LoginRateLimitListener $listener;

    protected function setUp(): void
    {
        $this->throttler = $this->createMock(LoginThrottler::class);
        $this->listener = new LoginRateLimitListener($this->throttler);
    }

    public function testBlocksLoginWhenIpIsThrottled(): void
    {
        $request = Request::create('/api/auth/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.1']);
        $event = $this->createRequestEvent($request);

        $this->throttler->method('isBlocked')->with('10.0.0.1')->willReturn(true);

        ($this->listener)($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertSame(429, $response->getStatusCode());
        self::assertSame('900', $response->headers->get('Retry-After'));
    }

    public function testAllowsLoginWhenIpIsNotThrottled(): void
    {
        $request = Request::create('/api/auth/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.2']);
        $event = $this->createRequestEvent($request);

        $this->throttler->method('isBlocked')->willReturn(false);

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    public function testIgnoresNonLoginEndpoints(): void
    {
        $request = Request::create('/api/invoices', 'GET');
        $event = $this->createRequestEvent($request);

        // Le throttler ne doit meme pas etre appele
        $this->throttler->expects(self::never())->method('isBlocked');

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    public function testIgnoresGetRequestsOnLoginPath(): void
    {
        $request = Request::create('/api/auth/login', 'GET');
        $event = $this->createRequestEvent($request);

        $this->throttler->expects(self::never())->method('isBlocked');

        ($this->listener)($event);

        self::assertFalse($event->hasResponse());
    }

    public function testReturnsRetryAfterHeader(): void
    {
        $request = Request::create('/api/auth/login', 'POST', [], [], [], ['REMOTE_ADDR' => '10.0.0.3']);
        $event = $this->createRequestEvent($request);

        $this->throttler->method('isBlocked')->willReturn(true);

        ($this->listener)($event);

        $response = $event->getResponse();
        self::assertNotNull($response);
        $body = json_decode($response->getContent() ?: '', true);
        self::assertSame('Trop de tentatives. Reessayez dans quelques minutes.', $body['error']);
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
