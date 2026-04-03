<?php

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\InvoiceEvent;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Enregistre un evenement dans la piste d'audit fiable (PAF) a chaque
 * changement d'etat d'une facture.
 */
class AuditTrailRecorder
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Enregistre un evenement de changement de statut.
     *
     * @param array<string, mixed> $metadata Donnees contextuelles supplementaires
     */
    public function record(
        Invoice $invoice,
        string $eventType,
        array $metadata = [],
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): void {
        $event = new InvoiceEvent(
            $invoice,
            $eventType,
            $metadata,
            $ipAddress,
            $userAgent,
        );

        $invoice->addEvent($event);
        $this->em->persist($event);
        $this->em->flush();
    }

    /**
     * Enregistre une transition de workflow.
     */
    public function recordTransition(
        Invoice $invoice,
        string $fromStatus,
        string $toStatus,
        ?string $ipAddress = null,
    ): void {
        $this->record(
            $invoice,
            'STATUS_CHANGED',
            [
                'from' => $fromStatus,
                'to' => $toStatus,
                'timestamp' => (new \DateTimeImmutable())->format('c'),
            ],
            $ipAddress,
        );
    }
}
