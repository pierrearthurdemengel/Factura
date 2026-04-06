<?php

namespace App\Service\Stripe;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestion des abonnements Stripe.
 */
class SubscriptionManager
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $stripeSecretKey,
    ) {
    }

    /**
     * Cree un customer Stripe pour l'utilisateur.
     */
    public function createCustomer(User $user): string
    {
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);

        $customer = \Stripe\Customer::create([
            'email' => $user->getEmail(),
            'name' => $user->getFirstName() . ' ' . $user->getLastName(),
        ]);

        $subscription = $this->em->getRepository(Subscription::class)->findOneBy(['user' => $user]);
        if (null !== $subscription) {
            $subscription->setStripeCustomerId($customer->id);
            $this->em->flush();
        }

        return $customer->id;
    }

    /**
     * Souscrit a un plan.
     */
    public function subscribe(User $user, string $priceId): string
    {
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);

        $subscription = $this->em->getRepository(Subscription::class)->findOneBy(['user' => $user]);
        if (null === $subscription || null === $subscription->getStripeCustomerId()) {
            $this->createCustomer($user);
            $subscription = $this->em->getRepository(Subscription::class)->findOneBy(['user' => $user]);
        }

        if (null === $subscription) {
            throw new \RuntimeException('Abonnement introuvable pour cet utilisateur.');
        }

        $customerId = $subscription->getStripeCustomerId();
        if (null === $customerId) {
            throw new \RuntimeException('Identifiant client Stripe manquant.');
        }

        $stripeSubscription = \Stripe\Subscription::create([
            'customer' => $customerId,
            'items' => [['price' => $priceId]],
        ]);

        $subscription->setStripeSubscriptionId($stripeSubscription->id);
        $subscription->setStatus('active');
        $this->em->flush();

        return $stripeSubscription->id;
    }

    /**
     * Annule l'abonnement.
     */
    public function cancel(User $user): void
    {
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);

        $subscription = $this->em->getRepository(Subscription::class)->findOneBy(['user' => $user]);
        if (null === $subscription || null === $subscription->getStripeSubscriptionId()) {
            return;
        }

        \Stripe\Subscription::update($subscription->getStripeSubscriptionId(), [
            'cancel_at_period_end' => true,
        ]);

        $subscription->setStatus('cancelled');
        $this->em->flush();
    }

    /**
     * Retourne l'URL du portail client Stripe.
     */
    public function getPortalUrl(User $user): ?string
    {
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);

        $subscription = $this->em->getRepository(Subscription::class)->findOneBy(['user' => $user]);
        if (null === $subscription || null === $subscription->getStripeCustomerId()) {
            return null;
        }

        $session = \Stripe\BillingPortal\Session::create([
            'customer' => $subscription->getStripeCustomerId(),
            'return_url' => 'https://ma-facture-pro.com/settings',
        ]);

        return $session->url;
    }
}
