<?php

namespace App\Banking\Exception;

/**
 * Le provider Open Banking est indisponible (down, rate-limited, erreur reseau).
 */
class ProviderUnavailableException extends \RuntimeException
{
    public function __construct(string $providerName, string $reason = '', ?\Throwable $previous = null)
    {
        parent::__construct(sprintf(
            'Le provider "%s" est indisponible%s.',
            $providerName,
            '' !== $reason ? ' : ' . $reason : '',
        ), 0, $previous);
    }
}
