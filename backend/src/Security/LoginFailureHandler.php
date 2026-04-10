<?php

namespace App\Security;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;

/**
 * Gestionnaire d'echec d'authentification.
 *
 * Retourne un message generique qui ne distingue pas entre
 * « email inconnu » et « mot de passe incorrect » pour prevenir
 * l'enumeration de comptes. Enregistre les tentatives echouees
 * pour le rate limiting.
 */
class LoginFailureHandler implements AuthenticationFailureHandlerInterface
{
    public function __construct(
        private readonly LoginThrottler $throttler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $ip = $request->getClientIp() ?? 'unknown';

        $this->throttler->recordFailedAttempt($ip);

        $this->logger->info('Tentative de connexion echouee', [
            'ip' => $ip,
        ]);

        // Message generique : ne pas reveler si l'email existe ou non
        return new JsonResponse(
            ['error' => 'Identifiants invalides.'],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
