<?php

namespace App\Banking\Exception;

/**
 * Le consentement PSD2 a expire (duree max 90 jours).
 */
class ConsentExpiredException extends \RuntimeException
{
    public function __construct(string $authorizationId, string $providerName)
    {
        parent::__construct(sprintf(
            'Le consentement "%s" a expire chez le provider "%s". Renouvellement necessaire.',
            $authorizationId,
            $providerName,
        ));
    }
}
