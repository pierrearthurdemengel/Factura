<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use App\Entity\Quote;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * State Processor qui assigne automatiquement l'entreprise active de
 * l'utilisateur connecte sur les entites qui possedent une methode setCompany().
 *
 * Chaine avec le PersistProcessor d'API Platform pour que le persist
 * et le flush soient effectues apres l'affectation de l'entreprise.
 *
 * @implements ProcessorInterface<object, object>
 */
class CompanyOwnerProcessor implements ProcessorInterface
{
    /**
     * @param ProcessorInterface<object, object> $persistProcessor
     */
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $this->assignCompanyOwner($data);

        // Compute line amounts and totals for Invoice/Quote
        if ($data instanceof Invoice || $data instanceof Quote) {
            foreach ($data->getLines() as $line) {
                $line->computeAmounts();
            }
            $data->computeTotals();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Assigne l'entreprise de l'utilisateur connecte sur l'entite si applicable.
     */
    private function assignCompanyOwner(mixed $data): void
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return;
        }

        $company = $user->getCompany();
        if (null === $company) {
            return;
        }

        // Entites avec champ "company" (Client, Product, ReminderTemplate...)
        $this->assignIfNull($data, 'Company', $company);

        // Entites avec champ "seller" (Invoice, Quote)
        $this->assignIfNull($data, 'Seller', $company);
    }

    /**
     * Assigne la valeur au setter si le getter retourne null.
     */
    private function assignIfNull(mixed $data, string $property, mixed $value): void
    {
        $getter = 'get' . $property;
        $setter = 'set' . $property;

        if (!method_exists($data, $setter) || !method_exists($data, $getter)) {
            return;
        }

        try {
            $current = $data->{$getter}();
        } catch (\Error) {
            $current = null;
        }

        if (null === $current) {
            $data->{$setter}($value);
        }
    }
}
