<?php

namespace App\Tests\Unit\Service\Webhook;

use App\Entity\Company;
use App\Entity\WebhookDelivery;
use App\Entity\WebhookEndpoint;
use App\Service\Webhook\WebhookDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class WebhookDispatcherTest extends TestCase
{
    private function createCompany(): Company
    {
        $company = new Company();
        $company->setName('Entreprise Test');
        $company->setSiren('123456789');
        $company->setLegalForm('SARL');
        $company->setAddressLine1('1 rue Test');
        $company->setPostalCode('75001');
        $company->setCity('Paris');

        return $company;
    }

    private function createEndpoint(Company $company, string $url = 'https://example.com/webhook'): WebhookEndpoint
    {
        $endpoint = new WebhookEndpoint();
        $endpoint->setCompany($company);
        $endpoint->setUrl($url);
        $endpoint->setEvents([
            WebhookDispatcher::EVENT_INVOICE_CREATED,
            WebhookDispatcher::EVENT_INVOICE_PAID,
        ]);
        $endpoint->setSecret('test-secret-key-123456');

        return $endpoint;
    }

    /**
     * @param WebhookEndpoint[] $endpoints
     */
    private function createDispatcher(array $endpoints = [], int $httpStatus = 200, string $httpBody = '{"ok":true}'): WebhookDispatcher
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findBy')->willReturn($endpoints);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn($httpStatus);
        $response->method('getContent')->willReturn($httpBody);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->method('request')->willReturn($response);

        return new WebhookDispatcher($em, $httpClient, new NullLogger());
    }

    public function testDispatchSendsToSubscribedEndpoints(): void
    {
        $company = $this->createCompany();
        $endpoint = $this->createEndpoint($company);
        $dispatcher = $this->createDispatcher([$endpoint]);

        $deliveries = $dispatcher->dispatch($company, WebhookDispatcher::EVENT_INVOICE_CREATED, ['id' => '123']);

        self::assertCount(1, $deliveries);
        self::assertSame(WebhookDelivery::STATUS_SUCCESS, $deliveries[0]->getStatus());
    }

    public function testDispatchSkipsUnsubscribedEvents(): void
    {
        $company = $this->createCompany();
        $endpoint = $this->createEndpoint($company);
        $dispatcher = $this->createDispatcher([$endpoint]);

        // L'endpoint n'est pas abonne a quote.accepted
        $deliveries = $dispatcher->dispatch($company, WebhookDispatcher::EVENT_QUOTE_ACCEPTED, ['id' => '123']);

        self::assertEmpty($deliveries);
    }

    public function testDispatchHandlesMultipleEndpoints(): void
    {
        $company = $this->createCompany();
        $endpoint1 = $this->createEndpoint($company, 'https://example.com/hook1');
        $endpoint2 = $this->createEndpoint($company, 'https://example.com/hook2');
        $dispatcher = $this->createDispatcher([$endpoint1, $endpoint2]);

        $deliveries = $dispatcher->dispatch($company, WebhookDispatcher::EVENT_INVOICE_CREATED, ['id' => '123']);

        self::assertCount(2, $deliveries);
    }

    public function testDispatchRecordsHttpSuccess(): void
    {
        $company = $this->createCompany();
        $endpoint = $this->createEndpoint($company);
        $dispatcher = $this->createDispatcher([$endpoint], 200, '{"ok":true}');

        $deliveries = $dispatcher->dispatch($company, WebhookDispatcher::EVENT_INVOICE_PAID, ['id' => '456']);

        self::assertSame(200, $deliveries[0]->getHttpStatusCode());
        self::assertSame('{"ok":true}', $deliveries[0]->getResponseBody());
        self::assertNotNull($deliveries[0]->getDeliveredAt());
    }

    public function testDispatchRecordsHttpError(): void
    {
        $company = $this->createCompany();
        $endpoint = $this->createEndpoint($company);
        $dispatcher = $this->createDispatcher([$endpoint], 500, 'Internal Server Error');

        $deliveries = $dispatcher->dispatch($company, WebhookDispatcher::EVENT_INVOICE_CREATED, ['id' => '789']);

        self::assertSame(WebhookDelivery::STATUS_FAILED, $deliveries[0]->getStatus());
        self::assertSame(500, $deliveries[0]->getHttpStatusCode());
    }

    public function testSignPayloadHmacSha256(): void
    {
        $dispatcher = $this->createDispatcher();

        $payload = '{"event":"invoice.created","data":{"id":"123"}}';
        $secret = 'my-webhook-secret';

        $signature = $dispatcher->signPayload($payload, $secret);

        self::assertSame(64, \strlen($signature)); // SHA-256 = 64 hex chars
        self::assertSame(hash_hmac('sha256', $payload, $secret), $signature);
    }

    public function testVerifySignatureAcceptsValid(): void
    {
        $dispatcher = $this->createDispatcher();

        $payload = '{"test":"data"}';
        $secret = 'secret123';
        $signature = hash_hmac('sha256', $payload, $secret);

        self::assertTrue($dispatcher->verifySignature($payload, $signature, $secret));
    }

    public function testVerifySignatureRejectsInvalid(): void
    {
        $dispatcher = $this->createDispatcher();

        $payload = '{"test":"data"}';
        $secret = 'secret123';

        self::assertFalse($dispatcher->verifySignature($payload, 'invalid_signature', $secret));
    }

    public function testVerifySignatureRejectsWrongSecret(): void
    {
        $dispatcher = $this->createDispatcher();

        $payload = '{"test":"data"}';
        $signature = hash_hmac('sha256', $payload, 'correct_secret');

        self::assertFalse($dispatcher->verifySignature($payload, $signature, 'wrong_secret'));
    }

    public function testGetAvailableEventsReturnsAll(): void
    {
        $dispatcher = $this->createDispatcher();
        $events = $dispatcher->getAvailableEvents();

        self::assertContains(WebhookDispatcher::EVENT_INVOICE_CREATED, $events);
        self::assertContains(WebhookDispatcher::EVENT_INVOICE_SENT, $events);
        self::assertContains(WebhookDispatcher::EVENT_INVOICE_PAID, $events);
        self::assertContains(WebhookDispatcher::EVENT_QUOTE_ACCEPTED, $events);
        self::assertContains(WebhookDispatcher::EVENT_PAYMENT_RECEIVED, $events);
        self::assertCount(10, $events);
    }

    public function testWebhookDeliveryCanRetry(): void
    {
        $delivery = new WebhookDelivery();
        $delivery->setEndpoint($this->createEndpoint($this->createCompany()));
        $delivery->setEventType('test');
        $delivery->setPayload([]);
        $delivery->incrementAttempts();
        $delivery->markAsFailed('Error');

        self::assertTrue($delivery->canRetry());
    }

    public function testWebhookDeliveryCannotRetryAfterMaxAttempts(): void
    {
        $delivery = new WebhookDelivery();
        $delivery->setEndpoint($this->createEndpoint($this->createCompany()));
        $delivery->setEventType('test');
        $delivery->setPayload([]);

        // Simuler 3 tentatives echouees
        for ($i = 0; $i < 3; ++$i) {
            $delivery->incrementAttempts();
        }
        $delivery->markAsFailed('Error');

        self::assertFalse($delivery->canRetry());
    }

    public function testWebhookDeliveryBackoffExponentiel(): void
    {
        $delivery = new WebhookDelivery();
        $delivery->setEndpoint($this->createEndpoint($this->createCompany()));
        $delivery->setEventType('test');
        $delivery->setPayload([]);
        $delivery->incrementAttempts();
        $delivery->markAsFailed('Error');

        // Apres 1 tentative, nextRetryAt doit etre defini
        self::assertNotNull($delivery->getNextRetryAt());
    }

    public function testEndpointIsSubscribedTo(): void
    {
        $endpoint = $this->createEndpoint($this->createCompany());

        self::assertTrue($endpoint->isSubscribedTo(WebhookDispatcher::EVENT_INVOICE_CREATED));
        self::assertTrue($endpoint->isSubscribedTo(WebhookDispatcher::EVENT_INVOICE_PAID));
        self::assertFalse($endpoint->isSubscribedTo(WebhookDispatcher::EVENT_QUOTE_REJECTED));
    }

    public function testDispatchEmptyEndpoints(): void
    {
        $company = $this->createCompany();
        $dispatcher = $this->createDispatcher([]);

        $deliveries = $dispatcher->dispatch($company, WebhookDispatcher::EVENT_INVOICE_CREATED, ['id' => '123']);

        self::assertEmpty($deliveries);
    }

    public function testRetryFailedDelivery(): void
    {
        $company = $this->createCompany();
        $endpoint = $this->createEndpoint($company);
        $delivery = new WebhookDelivery();
        $delivery->setEndpoint($endpoint);
        $delivery->setEventType(WebhookDispatcher::EVENT_INVOICE_CREATED);
        $delivery->setPayload(['id' => '123']);
        $delivery->incrementAttempts();
        $delivery->markAsFailed('Timeout');

        $dispatcher = $this->createDispatcher([$endpoint], 200, 'OK');
        $result = $dispatcher->retry($delivery);

        self::assertTrue($result);
    }

    public function testRetryReturnsfalseWhenMaxAttempts(): void
    {
        $company = $this->createCompany();
        $delivery = new WebhookDelivery();
        $delivery->setEndpoint($this->createEndpoint($company));
        $delivery->setEventType('test');
        $delivery->setPayload([]);

        for ($i = 0; $i < 3; ++$i) {
            $delivery->incrementAttempts();
        }
        $delivery->setStatus(WebhookDelivery::STATUS_FAILED);

        $dispatcher = $this->createDispatcher();
        $result = $dispatcher->retry($delivery);

        self::assertFalse($result);
    }
}
