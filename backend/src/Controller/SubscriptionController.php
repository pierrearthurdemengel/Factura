<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\Stripe\SubscriptionManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des abonnements utilisateur (portail Stripe, infos plan).
 */
class SubscriptionController extends AbstractController
{
    /**
     * Retourne l'URL du portail de facturation Stripe pour l'utilisateur connecte.
     */
    #[Route('/api/subscription/portal', name: 'api_subscription_portal', methods: ['GET'])]
    public function portal(SubscriptionManager $subscriptionManager): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $url = $subscriptionManager->getPortalUrl($user);

        if (null === $url) {
            return $this->json(
                ['message' => 'Aucun abonnement Stripe associe a ce compte.'],
                Response::HTTP_NOT_FOUND,
            );
        }

        return $this->json(['url' => $url]);
    }
}
