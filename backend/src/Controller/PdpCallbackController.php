<?php

namespace App\Controller;

use App\Entity\Invoice;
use App\Service\Invoice\AuditTrailRecorder;
use App\Service\Invoice\InvoiceStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Reception des mises a jour de statut depuis les PDP.
 * Webhook Chorus Pro -> transition workflow (SENT -> ACKNOWLEDGED ou REJECTED).
 */
class PdpCallbackController extends AbstractController
{
    #[Route('/webhooks/pdp/chorus-pro', name: 'webhook_chorus_pro', methods: ['POST'])]
    public function handleChorusProCallback(
        Request $request,
        EntityManagerInterface $em,
        InvoiceStateMachine $stateMachine,
        AuditTrailRecorder $auditTrail,
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!is_array($data) || !isset($data['pdpReference'], $data['status'])) {
            return new JsonResponse(['error' => 'Donnees manquantes.'], Response::HTTP_BAD_REQUEST);
        }

        $invoice = $em->getRepository(Invoice::class)->findOneBy([
            'pdpReference' => $data['pdpReference'],
        ]);

        if (null === $invoice) {
            return new JsonResponse(['error' => 'Facture introuvable.'], Response::HTTP_NOT_FOUND);
        }

        $oldStatus = $invoice->getStatus();

        $transition = match ($data['status']) {
            'ACCEPTEE', 'ACKNOWLEDGED' => 'acknowledge',
            'REFUSEE', 'REJECTED' => 'reject',
            default => null,
        };

        if (null !== $transition && $stateMachine->can($invoice, $transition)) {
            $stateMachine->apply($invoice, $transition);
            $em->flush();

            $auditTrail->recordTransition($invoice, $oldStatus, $invoice->getStatus());
            $auditTrail->record($invoice, 'RECEIVED_BY_PDP', [
                'pdp' => 'chorus_pro',
                'pdpStatus' => $data['status'],
            ]);
        }

        return new JsonResponse(['status' => 'ok']);
    }
}
