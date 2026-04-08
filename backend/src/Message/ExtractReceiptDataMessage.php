<?php

namespace App\Message;

/**
 * Message Messenger pour l'extraction OCR asynchrone d'un justificatif.
 */
class ExtractReceiptDataMessage
{
    public function __construct(
        private readonly string $receiptId,
    ) {
    }

    public function getReceiptId(): string
    {
        return $this->receiptId;
    }
}
