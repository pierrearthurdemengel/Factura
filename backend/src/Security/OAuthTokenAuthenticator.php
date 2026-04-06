<?php

namespace App\Security;

use App\Service\OAuth\OAuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * Authenticateur pour les tokens OAuth 2.1 (prefixe mfp_).
 * Coexiste avec le JWT : si le Bearer token commence par "mfp_",
 * cet authenticateur prend le relai, sinon le JWT est utilise.
 */
class OAuthTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly OAuthService $oauthService,
    ) {
    }

    /**
     * Verifie si la requete contient un token OAuth (prefixe mfp_).
     */
    public function supports(Request $request): ?bool
    {
        $authHeader = $request->headers->get('Authorization', '');
        if (!str_starts_with($authHeader, 'Bearer mfp_')) {
            return false;
        }

        return true;
    }

    public function authenticate(Request $request): Passport
    {
        $authHeader = $request->headers->get('Authorization', '');
        $token = substr($authHeader, 7); // Retirer "Bearer "

        $accessToken = $this->oauthService->findValidAccessToken($token);

        if (null === $accessToken) {
            throw new CustomUserMessageAuthenticationException('Token OAuth invalide ou expire.');
        }

        $user = $accessToken->getUser();

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier()),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // Laisser la requete continuer
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => 'invalid_token',
            'error_description' => $exception->getMessage(),
        ], Response::HTTP_UNAUTHORIZED);
    }
}
