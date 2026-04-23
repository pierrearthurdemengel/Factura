<?php

namespace App\Controller;

use App\Service\Factoring\FactoringRequestService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Recoit les webhooks des partenaires d'affacturage.
 *
 * Chaque partenaire notifie les changements de statut
 * (approbation, rejet, versement des fonds) via ce endpoint.
 * La signature HMAC-SHA256 est verifiee pour chaque appel.
 */
class FactoringWebhookController extends AbstractController
{
    public function __construct(
        private readonly FactoringRequestService $requestService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Recoit et traite un webhook d'un partenaire d'affacturage.
     */
    #[Route('/api/webhooks/factoring/{partnerId}', name: 'api_factoring_webhook', methods: ['POST'])]
    public function handleWebhook(string $partnerId, Request $request): JsonResponse
    {
        if (!in_array($partnerId, FactoringRequestService::getAllowedPartners(), true)) {
            return new JsonResponse(['error' => 'Partenaire inconnu'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if ([] === $payload) {
            return new JsonResponse(['error' => 'Payload invalide'], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('Webhook affacturage recu.', [
            'partnerId' => $partnerId,
            'event' => $payload['event'] ?? 'unknown',
        ]);

        return $this->processWebhook($partnerId, $payload);
    }

    /**
     * Traite le webhook et retourne la reponse appropriee.
     *
     * @param array<string, mixed> $payload
     */
    private function processWebhook(string $partnerId, array $payload): JsonResponse
    {
        try {
            $this->requestService->handleWebhook($partnerId, $payload);

            return new JsonResponse(['status' => 'processed']);
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors du traitement du webhook affacturage.', [
                'partnerId' => $partnerId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(
                ['error' => 'Erreur de traitement'],
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }
    }
}
