<?php

namespace App\Banking\Exception;

/**
 * La banque demandee n'est pas couverte par ce provider.
 */
class UnsupportedBankException extends \RuntimeException
{
    public function __construct(string $bankIdentifier, string $providerName)
    {
        parent::__construct(sprintf(
            'La banque "%s" n\'est pas supportee par le provider "%s".',
            $bankIdentifier,
            $providerName,
        ));
    }
}
