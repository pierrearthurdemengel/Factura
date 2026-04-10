<?php

namespace App\Banking\Exception;

/**
 * Aucun provider ne peut traiter la requete bancaire.
 */
class NoBankProviderAvailableException extends \RuntimeException
{
    public function __construct(string $operation, string $detail = '')
    {
        parent::__construct(sprintf(
            'Aucun provider bancaire disponible pour l\'operation "%s"%s.',
            $operation,
            '' !== $detail ? ' (' . $detail . ')' : '',
        ));
    }
}
