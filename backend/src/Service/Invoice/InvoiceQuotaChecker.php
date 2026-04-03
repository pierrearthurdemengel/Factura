<?php

namespace App\Service\Invoice;

use App\Entity\User;
use App\Exception\QuotaExceededException;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Verifie que l'utilisateur n'a pas depasse son quota mensuel de factures.
 * En plan Free, le quota est de 30 factures par mois.
 */
class InvoiceQuotaChecker
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Leve une exception si le quota est depasse.
     *
     * @throws QuotaExceededException
     */
    public function check(User $user): void
    {
        $subscription = $this->em->getRepository(\App\Entity\Subscription::class)
            ->findOneBy(['user' => $user]);

        if (null === $subscription) {
            return;
        }

        if ($subscription->isQuotaExceeded()) {
            throw new QuotaExceededException();
        }
    }
}
