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
        $invoice = $this->findInvoiceForUser($id, $em);
        if (null === $invoice) {
            return new JsonResponse(['error' => 'Facture introuvable ou acces interdit.'], Response::HTTP_NOT_FOUND);
        }

        $events = $invoice->getEvents()->toArray();
        $json = $serializer->serialize($events, 'json', ['groups' => ['event:read']]);

        return new JsonResponse($json, Response::HTTP_OK, [], true);
    }

    /**
     * Recherche une facture appartenant a l'utilisateur connecte.
     */
    private function findInvoiceForUser(string $id, EntityManagerInterface $em): ?Invoice
    {
        /** @var User $user */
        $user = $this->getUser();
        $company = $user->getCompany();

        if (null === $company) {
            return null;
        }

        $invoice = $em->getRepository(Invoice::class)->find($id);

        if (null === $invoice) {
            return null;
        }

        $seller = $invoice->getSeller();
        if (null === $seller || $seller->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return null;
        }

        return $invoice;
    }
}
