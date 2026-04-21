<?php

namespace App\Message;

use App\Entity\Invoice;
use App\Service\Invoice\AuditTrailRecorder;
use App\Service\Pdp\PdpDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler Messenger : transmet la facture a la PDP de maniere asynchrone.
 * Retry automatique en cas d'echec (configure dans messenger.yaml).
 */
#[AsMessageHandler]
class TransmitInvoiceToPdpHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PdpDispatcher $pdpDispatcher,
        private readonly AuditTrailRecorder $auditTrail,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(TransmitInvoiceToPdpMessage $message): void
    {
        $invoice = $this->em->getRepository(Invoice::class)->find($message->getInvoiceId());

        if (null === $invoice) {
            $this->logger->error('Facture introuvable pour la transmission PDP.', [
                'invoiceId' => $message->getInvoiceId(),
            ]);

            return;
        }

        $company = $invoice->getSeller();
        if (null === $company) {
            $this->logger->error('Transmission PDP impossible : facture sans vendeur.', [
                'invoiceId' => $message->getInvoiceId(),
            ]);

            return;
        }
        $pdpClient = $this->pdpDispatcher->getClientForCompany($company);

        try {
            // Transmission a la PDP
            $pdpReference = $pdpClient->transmit($invoice, '', 'facturx');

            $invoice->setPdpReference($pdpReference);
            $this->em->flush();

            $this->auditTrail->record($invoice, 'TRANSMITTED_TO_PDP', [
                'pdp' => $pdpClient->getName(),
                'reference' => $pdpReference,
            ]);

            $this->logger->info('Facture transmise a la PDP.', [
                'invoiceNumber' => $invoice->getNumber(),
                'pdp' => $pdpClient->getName(),
                'reference' => $pdpReference,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Echec de la transmission PDP.', [
                'invoiceNumber' => $invoice->getNumber(),
                'pdp' => $pdpClient->getName(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
