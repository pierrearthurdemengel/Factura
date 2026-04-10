<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Gere les en-tetes CORS pour permettre les requetes cross-origin.
 *
 * Necessaire car le frontend (Vite) et le backend (Symfony) tournent
 * sur des ports differents en developpement. En production, le reverse
 * proxy sert les deux sur le meme domaine.
 *
 * Allow-Credentials est active pour le transport du refresh token
 * via cookie httpOnly.
 */
class CorsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $allowedOrigin = 'http://localhost:5173',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 255],
            KernelEvents::RESPONSE => ['onKernelResponse', 0],
        ];
    }

    /**
     * Repond immediatement aux requetes preflight OPTIONS.
     */
    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if ('OPTIONS' !== $request->getMethod()) {
            return;
        }

        $origin = $request->headers->get('Origin', '');

        if (!$this->isAllowedOrigin($origin)) {
            return;
        }

        $response = new Response('', 204);
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, Accept');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '3600');

        $event->setResponse($response);
    }

    /**
     * Ajoute les en-tetes CORS a toutes les reponses.
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $origin = $event->getRequest()->headers->get('Origin', '');

        if (!$this->isAllowedOrigin($origin)) {
            return;
        }

        $response = $event->getResponse();
        $response->headers->set('Access-Control-Allow-Origin', $origin);
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
    }

    private function isAllowedOrigin(string $origin): bool
    {
        if ('' === $origin) {
            return false;
        }

        // L'origine principale est configuree via CORS_ALLOW_ORIGIN
        // Les origines localhost sont ajoutees pour le developpement local
        $allowed = [
            $this->allowedOrigin,
            'http://localhost:3000',
            'http://localhost:5173',
        ];

        return \in_array($origin, $allowed, true);
    }
}
