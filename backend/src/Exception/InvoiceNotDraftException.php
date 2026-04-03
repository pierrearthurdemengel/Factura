<?php

namespace App\Exception;

class InvoiceNotDraftException extends \DomainException
{
    public function __construct(string $invoiceId, string $currentStatus)
    {
        parent::__construct(sprintf(
            'La facture %s ne peut pas etre modifiee car elle est au statut %s (seul DRAFT est autorise).',
            $invoiceId,
            $currentStatus,
        ));
    }
}
