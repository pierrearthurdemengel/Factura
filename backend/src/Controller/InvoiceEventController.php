<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Endpoint de lecture de la piste d'audit fiable (PAF) d'une facture.
 */
class InvoiceEventController extends AbstractController
{
    /**
     * Retourne le journal PAF d'une facture sous forme de liste chronologique.
     */
    #[Route('/api/invoices/{id}/events', name: 'api_invoice_events', methods: ['GET'])]
    public function events(
        string $id,
        EntityManagerInterface $em,
        SerializerInterface $serializer,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (null === $company) {
            return new JsonResponse(['error' => 'Aucune entreprise associee.'], Response::HTTP_FORBIDDEN);
        }

        $invoice = $em->getRepository(Invoice::class)->find($id);

        if (null === $invoice) {
            return new JsonResponse(['error' => 'Facture introuvable.'], Response::HTTP_NOT_FOUND);
        }

        // Verifier que la facture appartient a l'entreprise de l'utilisateur
        if ($invoice->getSeller()->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return new JsonResponse(['error' => 'Acces interdit.'], Response::HTTP_FORBIDDEN);
        }

        $events = $invoice->getEvents()->toArray();
        $json = $serializer->serialize($events, 'json', ['groups' => ['event:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }
}
