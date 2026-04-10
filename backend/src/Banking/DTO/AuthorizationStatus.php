<?php

namespace App\Banking\DTO;

/**
 * Statut de l'autorisation d'acces bancaire.
 */
enum AuthorizationStatus: string
{
    case Pending = 'pending';
    case Authorized = 'authorized';
    case Rejected = 'rejected';
    case Expired = 'expired';
}
