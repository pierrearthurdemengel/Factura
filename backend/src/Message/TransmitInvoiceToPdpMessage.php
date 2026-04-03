<?php

namespace App\Message;

/**
 * Message Messenger pour la transmission asynchrone d'une facture a la PDP.
 */
class TransmitInvoiceToPdpMessage
{
    public function __construct(
        private readonly string $invoiceId,
    ) {
    }

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }
}
