<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valide un numero SIREN selon l'algorithme de Luhn.
 * Le SIREN est compose de 9 chiffres dont le dernier est une cle de controle.
 */
class ValidSirenValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidSiren) {
            throw new UnexpectedTypeException($constraint, ValidSiren::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        $siren = (string) $value;

        // Le SIREN doit contenir exactement 9 chiffres
        if (!preg_match('/^\d{9}$/', $siren)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $siren)
                ->addViolation();

            return;
        }

        // Algorithme de Luhn : on double les chiffres en position paire (index 1, 3, 5, 7)
        $sum = 0;
        for ($i = 0; $i < 9; ++$i) {
            $digit = (int) $siren[$i];

            if (0 !== $i % 2) {
                $digit *= 2;
                if ($digit > 9) {
                    $digit -= 9;
                }
            }

            $sum += $digit;
        }

        if (0 !== $sum % 10) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $siren)
                ->addViolation();
        }
    }
}
