<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Message\TransmitInvoiceToPdpMessage;
use App\Service\Invoice\AuditTrailRecorder;
use App\Service\Invoice\InvoiceNumberGenerator;
use App\Service\Invoice\InvoiceStateMachine;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * State Processor API Platform : appele lors de POST /invoices/{id}/send.
 * Applique la transition DRAFT -> SENT, genere le numero, et lance la transmission PDP.
 *
 * @implements ProcessorInterface<Invoice, Invoice>
 */
class InvoiceSendProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly InvoiceStateMachine $stateMachine,
        private readonly InvoiceNumberGenerator $numberGenerator,
        private readonly AuditTrailRecorder $auditTrail,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param Invoice $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        // Genere le numero de facture s'il n'existe pas encore
        if (null === $data->getNumber()) {
            $seller = $data->getSeller();
            if (null === $seller) {
                throw new \RuntimeException('La facture doit avoir un vendeur pour etre envoyee.');
            }
            $number = $this->numberGenerator->generate($seller);
            $data->setNumber($number);
        }

        // Recalcule les totaux
        $data->computeTotals();

        $oldStatus = $data->getStatus();

        // Transition DRAFT -> SENT via le workflow Symfony
        $this->stateMachine->apply($data, 'send');

        $this->em->flush();

        // Enregistrement dans la piste d'audit
        $this->auditTrail->recordTransition($data, $oldStatus, $data->getStatus());

        // Transmission asynchrone a la PDP via Messenger
        $invoiceId = $data->getId();
        \assert(null !== $invoiceId);
        $this->bus->dispatch(new TransmitInvoiceToPdpMessage($invoiceId->toRfc4122()));

        return $data;
    }
}
