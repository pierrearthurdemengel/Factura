<?php

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Gere les autorisations d'acces aux factures.
 *
 * Regles :
 * - VIEW : proprietaire de l'entreprise vendeuse
 * - EDIT : proprietaire uniquement, statut DRAFT uniquement
 * - DELETE : proprietaire uniquement, statut DRAFT uniquement
 * - SEND : proprietaire uniquement, statut DRAFT, facture valide
 * - CANCEL : proprietaire uniquement, statuts DRAFT/SENT/ACKNOWLEDGED
 *
 * @extends Voter<string, Invoice>
 */
class InvoiceVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';
    public const SEND = 'SEND';
    public const CANCEL = 'CANCEL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Invoice
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::SEND, self::CANCEL], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Invoice $invoice */
        $invoice = $subject;

        // Verifie que l'utilisateur est le proprietaire de l'entreprise vendeuse
        $company = $user->getCompany();
        if (null === $company || $invoice->getSeller()->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::DELETE => 'DRAFT' === $invoice->getStatus(),
            self::SEND => 'DRAFT' === $invoice->getStatus() && $invoice->isValid(),
            self::CANCEL => in_array($invoice->getStatus(), ['DRAFT', 'SENT', 'ACKNOWLEDGED'], true),
            default => false,
        };
    }
}
