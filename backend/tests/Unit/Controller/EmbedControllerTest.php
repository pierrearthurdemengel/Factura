<?php

namespace App\Tests\Unit\Controller;

use App\Controller\EmbedController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class EmbedControllerTest extends TestCase
{
    private EmbedController $controller;

    protected function setUp(): void
    {
        $this->controller = new EmbedController();
    }

    public function testConfigReturnsDefaultValues(): void
    {
        $request = Request::create('/api/embed/config');
        $response = $this->controller->config($request);

        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('#2563eb', $data['primaryColor']);
        $this->assertSame('#ffffff', $data['backgroundColor']);
        $this->assertSame('#111827', $data['textColor']);
        $this->assertSame(8, $data['borderRadius']);
        $this->assertSame('system-ui', $data['fontFamily']);
        $this->assertSame('fr', $data['locale']);
    }

    public function testConfigAcceptsValidColor(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'primaryColor' => '#ff5500',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('#ff5500', $data['primaryColor']);
    }

    public function testConfigRejectsInvalidColor(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'primaryColor' => 'not-a-color',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('#2563eb', $data['primaryColor']);
    }

    public function testConfigRejectsInvalidFont(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'fontFamily' => 'EvilFont',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('system-ui', $data['fontFamily']);
    }

    public function testConfigAcceptsValidFont(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'fontFamily' => 'Inter',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('Inter', $data['fontFamily']);
    }

    public function testConfigRejectsHttpLogoUrl(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'logoUrl' => 'http://evil.com/logo.png',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('', $data['logoUrl']);
    }

    public function testConfigAcceptsHttpsLogoUrl(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'logoUrl' => 'https://partner.com/logo.png',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('https://partner.com/logo.png', $data['logoUrl']);
    }

    public function testConfigClampsBorderRadius(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'borderRadius' => 100,
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame(24, $data['borderRadius']);
    }

    public function testConfigRejectsInvalidLocale(): void
    {
        $request = Request::create('/api/embed/config', 'GET', [
            'locale' => 'xx',
        ]);
        $response = $this->controller->config($request);
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('fr', $data['locale']);
    }

    public function testHealthEndpoint(): void
    {
        $response = $this->controller->health();
        $data = json_decode($response->getContent() ?: '', true);

        $this->assertSame('ok', $data['status']);
        $this->assertTrue($data['embed']);
    }

    public function testFrameRedirectsWithEmbedParam(): void
    {
        $response = $this->controller->frame('invoices');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/invoices?embed=1', $response->headers->get('Location'));
    }

    public function testFrameAllowsIframeEmbedding(): void
    {
        $response = $this->controller->frame('dashboard');

        $this->assertSame('ALLOWALL', $response->headers->get('X-Frame-Options'));
        $this->assertStringContainsString('frame-ancestors *', $response->headers->get('Content-Security-Policy') ?? '');
    }
}
