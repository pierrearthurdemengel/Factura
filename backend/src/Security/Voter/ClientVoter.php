<?php

namespace App\Security\Voter;

use App\Entity\Client;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Gere les autorisations d'acces aux clients.
 * Seul le proprietaire de l'entreprise peut voir, modifier ou supprimer ses clients.
 *
 * @extends Voter<string, Client>
 */
class ClientVoter extends Voter
{
    public const VIEW = 'VIEW';
    public const EDIT = 'EDIT';
    public const DELETE = 'DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof Client
            && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        /** @var Client $client */
        $client = $subject;

        $company = $user->getCompany();
        if (null === $company) {
            return false;
        }

        return $client->getCompany()->getId()?->toRfc4122() === $company->getId()?->toRfc4122();
    }
}
