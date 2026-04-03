<?php

namespace App\Controller;

use App\Service\Stripe\StripeWebhookHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class StripeWebhookController extends AbstractController
{
    #[Route('/webhooks/stripe', name: 'webhook_stripe', methods: ['POST'])]
    public function handle(Request $request, StripeWebhookHandler $handler): Response
    {
        $signature = $request->headers->get('Stripe-Signature', '');
        $payload = $request->getContent();

        try {
            // Verification de la signature Stripe (securite obligatoire)
            $event = $handler->constructEvent($payload, $signature);
        } catch (\Throwable) {
            return new Response('Signature invalide.', Response::HTTP_BAD_REQUEST);
        }

        return match ($event->type) {
            'invoice.payment_succeeded' => $handler->handlePaymentSucceeded($event),
            'invoice.payment_failed' => $handler->handlePaymentFailed($event),
            'customer.subscription.deleted' => $handler->handleSubscriptionCancelled($event),
            default => new Response('OK', 200),
        };
    }
}
