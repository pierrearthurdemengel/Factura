<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use App\Service\Factoring\FactoringEligibilityChecker;
use App\Service\Factoring\FactoringRequestService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gere les operations d'affacturage sur les factures.
 *
 * Expose deux endpoints :
 * - Verification d'eligibilite (check)
 * - Demande de financement (request)
 */
class FactoringController extends AbstractController
{
    public function __construct(
        private readonly FactoringEligibilityChecker $eligibilityChecker,
        private readonly FactoringRequestService $requestService,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Verifie l'eligibilite d'une facture pour l'affacturage.
     */
    #[Route('/api/invoices/{id}/factoring/check', name: 'api_factoring_check', methods: ['POST'])]
    public function check(string $id): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifie'], Response::HTTP_UNAUTHORIZED);
        }

        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (null === $invoice) {
            return new JsonResponse(['error' => 'Facture introuvable'], Response::HTTP_NOT_FOUND);
        }

        // Verification d'autorisation : seul le vendeur peut verifier
        $this->denyAccessUnlessGranted('VIEW', $invoice);

        $result = $this->eligibilityChecker->check($invoice);

        return new JsonResponse($result);
    }

    /**
     * Soumet une demande d'affacturage pour une facture.
     */
    #[Route('/api/invoices/{id}/factoring/request', name: 'api_factoring_request', methods: ['POST'])]
    public function request(string $id, Request $request): JsonResponse
    {
        $validationError = $this->validateFactoringRequest($id, $request);
        if (null !== $validationError) {
            return $validationError;
        }

        /** @var Invoice $invoice */
        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        $this->denyAccessUnlessGranted('EDIT', $invoice);

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $partnerId = (string) ($data['partnerId'] ?? '');

        try {
            $factoringRequest = $this->requestService->requestFinancing($invoice, $partnerId);

            return new JsonResponse([
                'id' => $factoringRequest->getId()?->toRfc4122(),
                'invoiceId' => $invoice->getId()?->toRfc4122(),
                'partnerId' => $factoringRequest->getPartnerId(),
                'amount' => $factoringRequest->getAmount(),
                'fee' => $factoringRequest->getFee(),
                'commission' => $factoringRequest->getCommission(),
                'status' => $factoringRequest->getStatus(),
                'clientScore' => $factoringRequest->getClientScore(),
                'requestedAt' => $factoringRequest->getRequestedAt()->format('c'),
            ], Response::HTTP_CREATED);
        } catch (\InvalidArgumentException $e) {
            return new JsonResponse(
                ['error' => $e->getMessage()],
                Response::HTTP_BAD_REQUEST,
            );
        }
    }

    /**
     * Valide les preconditions d'une demande d'affacturage.
     */
    private function validateFactoringRequest(string $id, Request $request): ?JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Non authentifie'], Response::HTTP_UNAUTHORIZED);
        }

        $invoice = $this->em->getRepository(Invoice::class)->find($id);
        if (null === $invoice) {
            return new JsonResponse(['error' => 'Facture introuvable'], Response::HTTP_NOT_FOUND);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $partnerId = isset($data['partnerId']) ? (string) $data['partnerId'] : '';

        if ('' === $partnerId) {
            return new JsonResponse(
                ['error' => 'Le champ partnerId est obligatoire.'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        return null;
    }
}
