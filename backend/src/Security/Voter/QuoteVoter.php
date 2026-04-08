<?php

namespace App\Security\Voter;

use App\Entity\Quote;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Gere les autorisations d'acces aux devis.
 *
 * Regles :
 * - VIEW : proprietaire de l'entreprise vendeuse
 * - EDIT : proprietaire uniquement, statut DRAFT uniquement
 * - DELETE : proprietaire uniquement, statut DRAFT uniquement
 * - SEND : proprietaire uniquement, statut DRAFT, devis valide
 * - CONVERT : proprietaire uniquement, statut ACCEPTED
 *
 * @extends Voter<string, Quote>
 */
class QuoteVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';
    public const SEND = 'SEND';
    public const CONVERT = 'CONVERT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Quote
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE, self::SEND, self::CONVERT], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Quote $quote */
        $quote = $subject;

        // Verifie que l'utilisateur est le proprietaire de l'entreprise vendeuse
        $company = $user->getCompany();
        if (null === $company || $quote->getSeller()->getId()?->toRfc4122() !== $company->getId()?->toRfc4122()) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::EDIT, self::DELETE => 'DRAFT' === $quote->getStatus(),
            self::SEND => 'DRAFT' === $quote->getStatus() && $quote->isValid(),
            self::CONVERT => 'ACCEPTED' === $quote->getStatus(),
            default => false,
        };
    }
}
