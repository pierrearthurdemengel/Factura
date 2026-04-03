<?php

namespace App\Service\Stripe;

use App\Entity\Subscription;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Traite les evenements Stripe recus via webhook.
 */
class StripeWebhookHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $stripeWebhookSecret,
        private readonly string $stripeSecretKey,
    ) {
    }

    /**
     * Construit et verifie l'evenement Stripe a partir du payload et de la signature.
     */
    public function constructEvent(string $payload, string $signature): \Stripe\Event
    {
        \Stripe\Stripe::setApiKey($this->stripeSecretKey);

        return \Stripe\Webhook::constructEvent($payload, $signature, $this->stripeWebhookSecret);
    }

    /**
     * Paiement reussi : active ou renouvelle l'abonnement.
     */
    public function handlePaymentSucceeded(\Stripe\Event $event): Response
    {
        $invoice = $event->data->object;
        $customerId = $invoice->customer;

        $subscription = $this->em->getRepository(Subscription::class)
            ->findOneBy(['stripeCustomerId' => $customerId]);

        if (null !== $subscription) {
            $subscription->setStatus('active');
            $subscription->setPlan('pro');
            $subscription->resetMonthlyCounter();
            $this->em->flush();
        }

        $this->logger->info('Paiement Stripe reussi.', ['customerId' => $customerId]);

        return new Response('OK', 200);
    }

    /**
     * Paiement echoue : downgrade vers Free.
     */
    public function handlePaymentFailed(\Stripe\Event $event): Response
    {
        $invoice = $event->data->object;
        $customerId = $invoice->customer;

        $subscription = $this->em->getRepository(Subscription::class)
            ->findOneBy(['stripeCustomerId' => $customerId]);

        if (null !== $subscription) {
            $subscription->setStatus('past_due');
            $this->em->flush();
        }

        $this->logger->warning('Paiement Stripe echoue.', ['customerId' => $customerId]);

        return new Response('OK', 200);
    }

    /**
     * Abonnement annule : retour au plan Free.
     */
    public function handleSubscriptionCancelled(\Stripe\Event $event): Response
    {
        $stripeSubscription = $event->data->object;
        $customerId = $stripeSubscription->customer;

        $subscription = $this->em->getRepository(Subscription::class)
            ->findOneBy(['stripeCustomerId' => $customerId]);

        if (null !== $subscription) {
            $subscription->setPlan('free');
            $subscription->setStatus('active');
            $subscription->setStripeSubscriptionId(null);
            $this->em->flush();
        }

        $this->logger->info('Abonnement Stripe annule.', ['customerId' => $customerId]);

        return new Response('OK', 200);
    }
}
