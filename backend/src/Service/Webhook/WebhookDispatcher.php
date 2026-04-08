<?php

namespace App\Service\Webhook;

use App\Entity\Company;
use App\Entity\WebhookDelivery;
use App\Entity\WebhookEndpoint;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Dispatcheur de webhooks vers les endpoints configures.
 *
 * Pour chaque evenement, recherche les endpoints abonnes,
 * signe le payload avec HMAC-SHA256, et envoie la requete HTTP.
 * Gere les retries avec backoff exponentiel (3 tentatives max).
 */
class WebhookDispatcher
{
    // Evenements disponibles
    public const EVENT_INVOICE_CREATED = 'invoice.created';
    public const EVENT_INVOICE_SENT = 'invoice.sent';
    public const EVENT_INVOICE_PAID = 'invoice.paid';
    public const EVENT_INVOICE_CANCELLED = 'invoice.cancelled';
    public const EVENT_QUOTE_CREATED = 'quote.created';
    public const EVENT_QUOTE_ACCEPTED = 'quote.accepted';
    public const EVENT_QUOTE_REJECTED = 'quote.rejected';
    public const EVENT_QUOTE_CONVERTED = 'quote.converted';
    public const EVENT_PAYMENT_RECEIVED = 'payment.received';
    public const EVENT_CLIENT_CREATED = 'client.created';

    public const ALL_EVENTS = [
        self::EVENT_INVOICE_CREATED,
        self::EVENT_INVOICE_SENT,
        self::EVENT_INVOICE_PAID,
        self::EVENT_INVOICE_CANCELLED,
        self::EVENT_QUOTE_CREATED,
        self::EVENT_QUOTE_ACCEPTED,
        self::EVENT_QUOTE_REJECTED,
        self::EVENT_QUOTE_CONVERTED,
        self::EVENT_PAYMENT_RECEIVED,
        self::EVENT_CLIENT_CREATED,
    ];

    // Timeout HTTP en secondes
    private const HTTP_TIMEOUT = 10;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Dispatche un evenement vers tous les endpoints abonnes.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<WebhookDelivery>
     */
    public function dispatch(Company $company, string $eventType, array $payload): array
    {
        $endpoints = $this->em->getRepository(WebhookEndpoint::class)->findBy([
            'company' => $company,
            'active' => true,
        ]);

        $deliveries = [];

        foreach ($endpoints as $endpoint) {
            if (!$endpoint->isSubscribedTo($eventType)) {
                continue;
            }

            $delivery = $this->createDelivery($endpoint, $eventType, $payload);
            $this->send($delivery);
            $deliveries[] = $delivery;
        }

        $this->em->flush();

        return $deliveries;
    }

    /**
     * Retente l'envoi d'une livraison echouee.
     */
    public function retry(WebhookDelivery $delivery): bool
    {
        if (!$delivery->canRetry()) {
            return false;
        }

        $delivery->incrementAttempts();
        $this->send($delivery);
        $this->em->flush();

        return true;
    }

    /**
     * Signe un payload avec HMAC-SHA256.
     */
    public function signPayload(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    /**
     * Verifie la signature d'un payload.
     */
    public function verifySignature(string $payload, string $signature, string $secret): bool
    {
        $expected = $this->signPayload($payload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * Retourne la liste des evenements disponibles.
     *
     * @return list<string>
     */
    public function getAvailableEvents(): array
    {
        return self::ALL_EVENTS;
    }

    /**
     * Cree un enregistrement de livraison.
     *
     * @param array<string, mixed> $payload
     */
    private function createDelivery(WebhookEndpoint $endpoint, string $eventType, array $payload): WebhookDelivery
    {
        $delivery = new WebhookDelivery();
        $delivery->setEndpoint($endpoint);
        $delivery->setEventType($eventType);
        $delivery->setPayload($payload);
        $delivery->incrementAttempts();

        $this->em->persist($delivery);

        return $delivery;
    }

    /**
     * Envoie le webhook HTTP.
     */
    private function send(WebhookDelivery $delivery): void
    {
        $endpoint = $delivery->getEndpoint();
        $payloadJson = json_encode($delivery->getPayload(), \JSON_THROW_ON_ERROR);
        $signature = $this->signPayload($payloadJson, $endpoint->getSecret());

        try {
            $response = $this->httpClient->request('POST', $endpoint->getUrl(), [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Event' => $delivery->getEventType(),
                    'X-Webhook-Id' => (string) $delivery->getId(),
                ],
                'body' => $payloadJson,
                'timeout' => self::HTTP_TIMEOUT,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getContent(false);

            if ($statusCode >= 200 && $statusCode < 300) {
                $delivery->markAsSuccess($statusCode, $body);
            } else {
                $delivery->markAsFailed(
                    sprintf('HTTP %d : %s', $statusCode, substr($body, 0, 500)),
                    $statusCode,
                );
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Echec envoi webhook vers {url} : {error}', [
                'url' => $endpoint->getUrl(),
                'error' => $e->getMessage(),
                'deliveryId' => (string) $delivery->getId(),
            ]);

            $delivery->markAsFailed($e->getMessage());
        }
    }
}
