<?php

namespace App\Controller;

use App\Service\PaymentNetwork\InvoiceShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Portail public de paiement.
 * Accessible sans authentification via un token unique.
 */
class PayPortalController extends AbstractController
{
    public function __construct(
        private readonly InvoiceShareService $shareService,
    ) {
    }

    /**
     * Visualise une facture partagee via un lien public.
     */
    #[Route('/pay/{token}', name: 'pay_portal_view', methods: ['GET'])]
    public function view(string $token): JsonResponse
    {
        $result = $this->shareService->viewInvoice($token);

        if (null === $result) {
            return new JsonResponse([
                'error' => 'Lien invalide ou expire.',
            ], Response::HTTP_NOT_FOUND);
        }

        $invoice = $result['invoice'];
        $shareLink = $result['shareLink'];

        $lines = [];
        foreach ($invoice->getLines() as $line) {
            $lines[] = [
                'description' => $line->getDescription(),
                'quantity' => $line->getQuantity(),
                'unitPriceExcludingTax' => $line->getUnitPriceExcludingTax(),
                'vatRate' => $line->getVatRate(),
                'lineAmount' => $line->getLineAmount(),
            ];
        }

        return new JsonResponse([
            'invoice' => [
                'number' => $invoice->getNumber(),
                'status' => $invoice->getStatus(),
                'issueDate' => $invoice->getIssueDate()->format('Y-m-d'),
                'dueDate' => $invoice->getDueDate()?->format('Y-m-d'),
                'seller' => $invoice->getSeller()?->getName() ?? '',
                'buyer' => $invoice->getBuyer()->getName(),
                'totalExcludingTax' => $invoice->getTotalExcludingTax(),
                'totalTax' => $invoice->getTotalTax(),
                'totalIncludingTax' => $invoice->getTotalIncludingTax(),
                'currency' => $invoice->getCurrency(),
                'lines' => $lines,
            ],
            'acknowledged' => $shareLink->isAcknowledged(),
            'referralCode' => $shareLink->getReferralCode(),
        ]);
    }

    /**
     * Confirme la reception de la facture en 1 clic.
     */
    #[Route('/pay/{token}/acknowledge', name: 'pay_portal_acknowledge', methods: ['POST'])]
    public function acknowledge(string $token): JsonResponse
    {
        $success = $this->shareService->acknowledgeReceipt($token);

        if (!$success) {
            return new JsonResponse([
                'error' => 'Lien invalide, expire ou deja confirme.',
            ], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse([
            'message' => 'Reception confirmee.',
        ]);
    }
}
