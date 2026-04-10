<?php

namespace App\EventListener;

use App\Security\LoginThrottler;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Bloque les requetes de connexion si l'IP est temporairement bannie.
 *
 * Intercepte les requetes sur /api/auth/login avant que le firewall
 * ne les traite, pour eviter les attaques par force brute.
 */
#[AsEventListener(event: 'kernel.request', priority: 256)]
class LoginRateLimitListener
{
    public function __construct(
        private readonly LoginThrottler $throttler,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Appliquer uniquement sur l'endpoint de login
        if ('/api/auth/login' !== $request->getPathInfo() || 'POST' !== $request->getMethod()) {
            return;
        }

        $ip = $request->getClientIp() ?? 'unknown';

        if ($this->throttler->isBlocked($ip)) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Trop de tentatives. Reessayez dans quelques minutes.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => '900'],
            ));
        }
    }
}
