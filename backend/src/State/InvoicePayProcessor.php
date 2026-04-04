<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Service\Invoice\AuditTrailRecorder;
use App\Service\Invoice\InvoiceStateMachine;
use Doctrine\ORM\EntityManagerInterface;

/**
 * State Processor API Platform : appele lors de POST /invoices/{id}/pay.
 * Applique la transition vers PAID via le workflow Symfony.
 *
 * @implements ProcessorInterface<Invoice, Invoice>
 */
class InvoicePayProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly InvoiceStateMachine $stateMachine,
        private readonly AuditTrailRecorder $auditTrail,
        private readonly EntityManagerInterface $em,
    ) {
    }

    /** @param Invoice $data */
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Invoice
    {
        $oldStatus = $data->getStatus();

        // Transition vers PAID via le workflow Symfony
        $this->stateMachine->apply($data, 'pay');

        $this->em->flush();

        // Enregistrement dans la piste d'audit
        $this->auditTrail->recordTransition($data, $oldStatus, $data->getStatus());

        return $data;
    }
}
