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
    public function __construct(
        private readonly ProcessorInterface $persistProcessor,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        /** @var User|null $user */
        $user = $this->security->getUser();

        if ($user instanceof User) {
            $company = $user->getCompany();

            if (null !== $company) {
                // Entites avec champ "company" (Client, Product, ReminderTemplate...)
                if (method_exists($data, 'setCompany') && method_exists($data, 'getCompany')) {
                    try {
                        $current = $data->getCompany();
                    } catch (\Error) {
                        $current = null;
                    }
                    if (null === $current) {
                        $data->setCompany($company);
                    }
                }

                // Entites avec champ "seller" (Invoice, Quote)
                if (method_exists($data, 'setSeller') && method_exists($data, 'getSeller')) {
                    try {
                        $current = $data->getSeller();
                    } catch (\Error) {
                        $current = null;
                    }
                    if (null === $current) {
                        $data->setSeller($company);
                    }
                }
            }
        }

        // Compute line amounts and totals for Invoice/Quote
        if ($data instanceof Invoice || $data instanceof Quote) {
            foreach ($data->getLines() as $line) {
                $line->computeAmounts();
            }
            $data->computeTotals();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
