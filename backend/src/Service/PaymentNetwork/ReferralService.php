<?php

namespace App\Service\PaymentNetwork;

use App\Entity\Referral;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gere le programme de parrainage.
 * Chaque utilisateur dispose d'un code unique.
 * Quand un filleul s'inscrit via ce code, les deux parties sont recompensees.
 */
class ReferralService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere ou recupere le code de parrainage d'un utilisateur.
     */
    public function getOrCreateReferralCode(User $user): string
    {
        // Chercher un parrainage existant pour cet utilisateur (en tant que parrain)
        $existing = $this->em->getRepository(Referral::class)->findOneBy([
            'referrer' => $user,
            'referee' => null,
            'status' => Referral::STATUS_PENDING,
        ]);

        if (null !== $existing) {
            return $existing->getCode();
        }

        // Creer un nouveau code
        $referral = new Referral();
        $referral->setReferrer($user);
        $referral->setCode(Referral::generateCode());

        $this->em->persist($referral);
        $this->em->flush();

        return $referral->getCode();
    }

    /**
     * Enregistre l'inscription d'un filleul via un code de parrainage.
     */
    public function completeReferral(string $code, User $referee): ?Referral
    {
        $referral = $this->em->getRepository(Referral::class)->findOneBy([
            'code' => $code,
            'status' => Referral::STATUS_PENDING,
        ]);

        if (null === $referral) {
            return null;
        }

        // Empecher l'auto-parrainage
        if ($referral->getReferrer()->getId()?->toRfc4122() === $referee->getId()?->toRfc4122()) {
            return null;
        }

        $referral->complete($referee);
        $this->em->flush();

        return $referral;
    }

    /**
     * Attribue la recompense (1 mois Pro gratuit pour les deux parties).
     * Retourne true si la recompense a ete attribuee.
     */
    public function rewardReferral(Referral $referral): bool
    {
        if (!$referral->isCompleted() || $referral->isRewarded()) {
            return false;
        }

        $referral->reward();
        $this->em->flush();

        return true;
    }

    /**
     * Recupere les statistiques de parrainage d'un utilisateur.
     *
     * @return array{totalReferrals: int, completedReferrals: int, rewardedReferrals: int, code: string}
     */
    public function getReferralStats(User $user): array
    {
        $referrals = $this->em->getRepository(Referral::class)->findBy([
            'referrer' => $user,
        ]);

        $completed = 0;
        $rewarded = 0;
        $code = '';

        foreach ($referrals as $r) {
            if ($r->isCompleted()) {
                ++$completed;
            }
            if ($r->isRewarded()) {
                ++$rewarded;
            }
            // Le dernier code en date est le code actif
            $code = $r->getCode();
        }

        // Si pas de code, en generer un
        if ('' === $code) {
            $code = $this->getOrCreateReferralCode($user);
        }

        return [
            'totalReferrals' => count($referrals),
            'completedReferrals' => $completed,
            'rewardedReferrals' => $rewarded,
            'code' => $code,
        ];
    }
}
