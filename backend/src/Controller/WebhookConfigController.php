<?php

namespace App\Controller;

use App\Entity\Company;
use App\Entity\WebhookDelivery;
use App\Entity\WebhookEndpoint;
use App\Service\Webhook\WebhookDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Configuration des webhooks par l'utilisateur.
 *
 * Permet de creer, lister, modifier et supprimer des endpoints,
 * de consulter l'historique des envois, et de rejouer les echecs.
 */
#[Route('/api/webhooks')]
class WebhookConfigController extends AbstractController
{
    private const MSG_ENDPOINT_NOT_FOUND = 'Endpoint non trouve.';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WebhookDispatcher $dispatcher,
    ) {
    }

    /**
     * Cree un nouveau webhook endpoint.
     */
    #[Route('/endpoints', methods: ['POST'])]
    public function createEndpoint(Request $request): JsonResponse
    {
        /** @var array{company_id?: string, url?: string, events?: list<string>, description?: string} $data */
        $data = json_decode((string) $request->getContent(), true) ?? [];

        $validationError = $this->validateCreateEndpoint($data);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var Company $company */
        $company = $this->getCompany($data['company_id'] ?? '');
        /** @var string $url */
        $url = $data['url'] ?? '';
        /** @var list<string> $events */
        $events = $data['events'] ?? [];

        $endpoint = new WebhookEndpoint();
        $endpoint->setCompany($company);
        $endpoint->setUrl($url);
        $endpoint->setEvents($events);
        $endpoint->setSecret(bin2hex(random_bytes(32)));
        $endpoint->setDescription($data['description'] ?? null);

        $this->em->persist($endpoint);
        $this->em->flush();

        return new JsonResponse([
            'id' => (string) $endpoint->getId(),
            'url' => $endpoint->getUrl(),
            'events' => $endpoint->getEvents(),
            'secret' => $endpoint->getSecret(),
            'active' => $endpoint->isActive(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Valide les donnees de creation d'un endpoint webhook.
     *
     * @param array<string, mixed> $data
     */
    private function validateCreateEndpoint(array $data): ?JsonResponse
    {
        $company = $this->getCompany($data['company_id'] ?? '');
        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $url = $data['url'] ?? '';
        if ('' === $url) {
            return new JsonResponse(['error' => 'L\'URL est requise.'], Response::HTTP_BAD_REQUEST);
        }

        /** @var list<string> $events */
        $events = $data['events'] ?? [];
        if ([] === $events) {
            return new JsonResponse(['error' => 'Au moins un evenement est requis.'], Response::HTTP_BAD_REQUEST);
        }

        return null;
    }

    /**
     * Liste les endpoints d'une entreprise.
     */
    #[Route('/endpoints', methods: ['GET'])]
    public function listEndpoints(Request $request): JsonResponse
    {
        $company = $this->getCompany($request->query->getString('company_id'));
        if (null === $company) {
            return new JsonResponse(['error' => 'Entreprise non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        $endpoints = $this->em->getRepository(WebhookEndpoint::class)->findBy([
            'company' => $company,
        ]);

        $result = [];
        foreach ($endpoints as $endpoint) {
            $result[] = [
                'id' => (string) $endpoint->getId(),
                'url' => $endpoint->getUrl(),
                'events' => $endpoint->getEvents(),
                'active' => $endpoint->isActive(),
                'description' => $endpoint->getDescription(),
                'createdAt' => $endpoint->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse(['endpoints' => $result]);
    }

    /**
     * Supprime un endpoint.
     */
    #[Route('/endpoints/{id}', methods: ['DELETE'])]
    public function deleteEndpoint(string $id): JsonResponse
    {
        $endpoint = $this->em->getRepository(WebhookEndpoint::class)->find($id);
        if (null === $endpoint) {
            return new JsonResponse(['error' => self::MSG_ENDPOINT_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $this->em->remove($endpoint);
        $this->em->flush();

        return new JsonResponse(['message' => 'Endpoint supprime.']);
    }

    /**
     * Historique des livraisons pour un endpoint.
     */
    #[Route('/endpoints/{id}/deliveries', methods: ['GET'])]
    public function deliveries(string $id): JsonResponse
    {
        $endpoint = $this->em->getRepository(WebhookEndpoint::class)->find($id);
        if (null === $endpoint) {
            return new JsonResponse(['error' => self::MSG_ENDPOINT_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $result = [];
        foreach ($endpoint->getDeliveries() as $delivery) {
            $result[] = [
                'id' => (string) $delivery->getId(),
                'eventType' => $delivery->getEventType(),
                'status' => $delivery->getStatus(),
                'httpStatusCode' => $delivery->getHttpStatusCode(),
                'attempts' => $delivery->getAttempts(),
                'lastError' => $delivery->getLastError(),
                'createdAt' => $delivery->getCreatedAt()->format('c'),
                'deliveredAt' => $delivery->getDeliveredAt()?->format('c'),
            ];
        }

        return new JsonResponse(['deliveries' => $result]);
    }

    /**
     * Rejoue une livraison echouee.
     */
    #[Route('/deliveries/{id}/retry', methods: ['POST'])]
    public function retryDelivery(string $id): JsonResponse
    {
        $delivery = $this->em->getRepository(WebhookDelivery::class)->find($id);
        if (null === $delivery) {
            return new JsonResponse(['error' => 'Livraison non trouvee.'], Response::HTTP_NOT_FOUND);
        }

        if (!$delivery->canRetry()) {
            return new JsonResponse(['error' => 'Nombre maximum de tentatives atteint.'], Response::HTTP_BAD_REQUEST);
        }

        $this->dispatcher->retry($delivery);

        return new JsonResponse(['status' => $delivery->getStatus()]);
    }

    /**
     * Liste les evenements disponibles.
     */
    #[Route('/events', methods: ['GET'])]
    public function events(): JsonResponse
    {
        return new JsonResponse(['events' => $this->dispatcher->getAvailableEvents()]);
    }

    /**
     * Envoie un evenement de test a un endpoint.
     */
    #[Route('/endpoints/{id}/test', methods: ['POST'])]
    public function testEndpoint(string $id): JsonResponse
    {
        $endpoint = $this->em->getRepository(WebhookEndpoint::class)->find($id);
        if (null === $endpoint) {
            return new JsonResponse(['error' => self::MSG_ENDPOINT_NOT_FOUND], Response::HTTP_NOT_FOUND);
        }

        $testPayload = [
            'event' => 'test.ping',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
            'data' => ['message' => 'Ceci est un test de webhook.'],
        ];

        $deliveries = $this->dispatcher->dispatch($endpoint->getCompany(), 'test.ping', $testPayload);

        $status = 'no_delivery';
        if ([] !== $deliveries) {
            $status = $deliveries[0]->getStatus();
        }

        return new JsonResponse(['status' => $status]);
    }

    private function getCompany(string $companyId): ?Company
    {
        if ('' === $companyId) {
            return null;
        }

        return $this->em->getRepository(Company::class)->find($companyId);
    }
}
