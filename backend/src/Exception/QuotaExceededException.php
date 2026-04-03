<?php

namespace App\Exception;

class QuotaExceededException extends \DomainException
{
    public function __construct(int $limit = 30)
    {
        parent::__construct(sprintf(
            'Quota mensuel depasse : %d factures maximum en plan gratuit. Passez au plan Pro pour un usage illimite.',
            $limit,
        ));
    }
}
