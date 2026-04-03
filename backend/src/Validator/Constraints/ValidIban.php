<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class ValidIban extends Constraint
{
    public string $message = 'L\'IBAN "{{ value }}" n\'est pas valide.';
}
