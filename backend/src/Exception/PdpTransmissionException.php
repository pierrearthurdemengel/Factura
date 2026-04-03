<?php

namespace App\Exception;

class PdpTransmissionException extends \RuntimeException
{
    public function __construct(string $pdpName, string $invoiceNumber, string $reason)
    {
        parent::__construct(sprintf(
            'Echec de la transmission de la facture %s via la PDP %s : %s',
            $invoiceNumber,
            $pdpName,
            $reason,
        ));
    }
}
