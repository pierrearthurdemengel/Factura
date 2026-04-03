<?php

namespace App\Service\Pdp;

/**
 * Represente le statut d'une facture transmise a une PDP.
 */
enum PdpStatus: string
{
    case PENDING = 'PENDING';
    case RECEIVED = 'RECEIVED';
    case ACKNOWLEDGED = 'ACKNOWLEDGED';
    case REJECTED = 'REJECTED';
    case ERROR = 'ERROR';
}
