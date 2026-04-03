<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValidSiren extends Constraint
{
    public string $message = 'Le SIREN "{{ value }}" n\'est pas valide.';
}
