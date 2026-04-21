<?php

namespace App\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Valide un IBAN selon le format et la cle de controle (modulo 97).
 */
class ValidIbanValidator extends ConstraintValidator
{
    private const PARAM_VALUE = '{{ value }}';

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidIban) {
            throw new UnexpectedTypeException($constraint, ValidIban::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        // Supprime les espaces et met en majuscules
        $iban = strtoupper(str_replace(' ', '', (string) $value));

        // Longueur minimale 15 caracteres, maximale 34
        if (strlen($iban) < 15 || strlen($iban) > 34) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(self::PARAM_VALUE, (string) $value)
                ->addViolation();

            return;
        }

        // Format : 2 lettres (pays) + 2 chiffres (cle) + BBAN alphanumerique
        if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/', $iban)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(self::PARAM_VALUE, (string) $value)
                ->addViolation();

            return;
        }

        // Verification de la cle de controle : deplacer les 4 premiers caracteres a la fin
        $rearranged = substr($iban, 4) . substr($iban, 0, 4);

        // Convertir les lettres en chiffres (A=10, B=11, ..., Z=35)
        $numericString = '';
        for ($i = 0; $i < strlen($rearranged); ++$i) {
            $char = $rearranged[$i];
            if (ctype_alpha($char)) {
                $numericString .= (string) (ord($char) - 55);
            } else {
                $numericString .= $char;
            }
        }

        // Modulo 97 sur le nombre obtenu (doit donner 1)
        if (1 !== self::mod97($numericString)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter(self::PARAM_VALUE, (string) $value)
                ->addViolation();
        }
    }

    /**
     * Calcule le modulo 97 d'un grand nombre represente en chaine de caracteres.
     */
    private static function mod97(string $numericString): int
    {
        $remainder = 0;
        for ($i = 0; $i < strlen($numericString); ++$i) {
            $remainder = (int) (($remainder * 10 + (int) $numericString[$i]) % 97);
        }

        return $remainder;
    }
}
