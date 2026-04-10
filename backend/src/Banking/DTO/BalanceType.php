<?php

namespace App\Banking\DTO;

/**
 * Type de solde bancaire.
 */
enum BalanceType: string
{
    case Available = 'available';
    case Booked = 'booked';
}
