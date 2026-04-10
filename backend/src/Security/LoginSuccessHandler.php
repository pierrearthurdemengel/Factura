<?php

namespace App\Security;

use App\Entity\User;
use App\Service\Auth\AuthTokenService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * Gestionnaire d'authentification reussie.
 *
 * Genere un JWT d'acces (15 min) et un refresh token (30 jours).
 * Le JWT est retourne dans le corps de la reponse, le refresh token
 * est place dans un cookie HTTP-only securise.
 */
class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly AuthTokenService $authTokenService,
        private readonly LoginThrottler $throttler,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        /** @var User $user */
        $user = $token->getUser();

        // Reinitialiser le compteur de tentatives echouees
        $ip = $request->getClientIp() ?? 'unknown';
        $this->throttler->resetAttempts($ip);

        // Generer le JWT d'acces (15 min)
        $jwt = $this->jwtManager->create($user);

        // Generer le refresh token (30 jours, stocke hashe en BDD)
        $rawRefreshToken = $this->authTokenService->createRefreshToken($user, $request);

        // Log d'audit
        $this->logger->info('Connexion reussie', [
            'user_id' => $user->getId()?->toRfc4122(),
            'ip' => $request->getClientIp(),
        ]);

        $response = new JsonResponse([
            'token' => $jwt,
        ]);

        // Refresh token en cookie HTTP-only securise
        $response->headers->setCookie(
            Cookie::create('refresh_token')
                ->withValue($rawRefreshToken)
                ->withExpires(new \DateTimeImmutable('+30 days'))
                ->withPath('/api/auth')
                ->withSecure(true)
                ->withHttpOnly(true)
                ->withSameSite('strict'),
        );

        return $response;
    }
}
