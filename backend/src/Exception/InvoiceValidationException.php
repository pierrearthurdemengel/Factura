<?php

namespace App\Exception;

class InvoiceValidationException extends \DomainException
{
    /** @var list<string> */
    private array $errors;

    /**
     * @param list<string> $errors Liste des erreurs de validation
     */
    public function __construct(array $errors)
    {
        $this->errors = $errors;

        parent::__construct(sprintf(
            'La facture ne peut pas etre emise : %s',
            implode(' ; ', $errors),
        ));
    }

    /**
     * @return list<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
