<?php

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\CorsSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CorsSubscriberTest extends TestCase
{
    private CorsSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new CorsSubscriber();
    }

    public function testSubscribesOnRequestAndResponse(): void
    {
        $events = CorsSubscriber::getSubscribedEvents();

        self::assertArrayHasKey(KernelEvents::REQUEST, $events);
        self::assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testOptionsPreflightReturns204WithCorsHeaders(): void
    {
        $request = Request::create('/api/invoices', 'OPTIONS');
        $request->headers->set('Origin', 'http://localhost:5173');

        $event = $this->createRequestEvent($request);
        $this->subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertSame(204, $response->getStatusCode());
        self::assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
        self::assertStringContainsString('Authorization', $response->headers->get('Access-Control-Allow-Headers') ?? '');
    }

    public function testOptionsPreflightIgnoresUnknownOrigin(): void
    {
        $request = Request::create('/api/invoices', 'OPTIONS');
        $request->headers->set('Origin', 'https://evil.com');

        $event = $this->createRequestEvent($request);
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testOptionsPreflightIgnoresEmptyOrigin(): void
    {
        $request = Request::create('/api/invoices', 'OPTIONS');
        // Pas de header Origin

        $event = $this->createRequestEvent($request);
        $this->subscriber->onKernelRequest($event);

        self::assertFalse($event->hasResponse());
    }

    public function testIgnoresNonOptionsRequests(): void
    {
        $request = Request::create('/api/invoices', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $event = $this->createRequestEvent($request);
        $this->subscriber->onKernelRequest($event);

        // Ne doit pas definir de reponse pour les requetes non-OPTIONS
        self::assertFalse($event->hasResponse());
    }

    public function testResponseGetsCredentialHeaders(): void
    {
        $request = Request::create('/api/invoices', 'GET');
        $request->headers->set('Origin', 'http://localhost:5173');

        $response = new Response('OK');
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertSame('http://localhost:5173', $response->headers->get('Access-Control-Allow-Origin'));
        self::assertSame('true', $response->headers->get('Access-Control-Allow-Credentials'));
    }

    public function testResponseIgnoresUnknownOrigin(): void
    {
        $request = Request::create('/api/invoices', 'GET');
        $request->headers->set('Origin', 'https://attacker.com');

        $response = new Response('OK');
        $event = $this->createResponseEvent($request, $response);

        $this->subscriber->onKernelResponse($event);

        self::assertNull($response->headers->get('Access-Control-Allow-Origin'));
    }

    public function testAcceptsLocalhostPort3000(): void
    {
        $request = Request::create('/api/invoices', 'OPTIONS');
        $request->headers->set('Origin', 'http://localhost:3000');

        $event = $this->createRequestEvent($request);
        $this->subscriber->onKernelRequest($event);

        self::assertTrue($event->hasResponse());
        $response = $event->getResponse();
        self::assertSame('http://localhost:3000', $response->headers->get('Access-Control-Allow-Origin'));
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createResponseEvent(Request $request, Response $response): ResponseEvent
    {
        $kernel = $this->createMock(HttpKernelInterface::class);

        return new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
    }
}
