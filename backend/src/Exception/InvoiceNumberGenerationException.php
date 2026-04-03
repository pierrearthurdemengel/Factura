<?php

namespace App\Exception;

class InvoiceNumberGenerationException extends \RuntimeException
{
    public function __construct(string $message = 'Impossible de generer le numero de facture.')
    {
        parent::__construct($message);
    }
}
