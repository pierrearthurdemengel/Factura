<?php

namespace App\Message;

/**
 * Message Messenger pour l'envoi asynchrone d'une relance par email.
 */
class SendReminderMessage
{
    public function __construct(
        private readonly string $invoiceId,
        private readonly string $reminderType,
    ) {
    }

    public function getInvoiceId(): string
    {
        return $this->invoiceId;
    }

    public function getReminderType(): string
    {
        return $this->reminderType;
    }
}
