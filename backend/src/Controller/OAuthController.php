<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\OAuth\OAuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Serveur OAuth 2.1 pour les integrations LLM (Claude, ChatGPT, Gemini).
 * Implemente le Authorization Code Flow avec PKCE.
 */
class OAuthController extends AbstractController
{
    public function __construct(
        private readonly OAuthService $oauthService,
    ) {
    }

    /**
     * Metadata discovery OAuth 2.1 (RFC 8414).
     * Permet a Claude et ChatGPT de decouvrir automatiquement les endpoints.
     */
    #[Route('/.well-known/oauth-authorization-server', name: 'oauth_metadata', methods: ['GET'])]
    public function metadata(Request $request): JsonResponse
    {
        $baseUrl = $request->getSchemeAndHttpHost();

        return new JsonResponse([
            'issuer' => $baseUrl,
            'authorization_endpoint' => $baseUrl . '/oauth/authorize',
            'token_endpoint' => $baseUrl . '/oauth/token',
            'revocation_endpoint' => $baseUrl . '/oauth/revoke',
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256', 'plain'],
            'token_endpoint_auth_methods_supported' => ['client_secret_post', 'none'],
            'scopes_supported' => [
                'invoices:read',
                'invoices:write',
                'clients:read',
                'clients:write',
                'company:read',
                'stats:read',
            ],
        ]);
    }

    /**
     * Page d'autorisation OAuth.
     * Affiche la page de consentement ou redirige vers le login.
     */
    #[Route('/oauth/authorize', name: 'oauth_authorize', methods: ['GET', 'POST'])]
    public function authorize(Request $request): Response
    {
        // Recuperer les parametres OAuth
        $clientId = $request->query->getString('client_id', '');
        $redirectUri = $request->query->getString('redirect_uri', '');
        $responseType = $request->query->getString('response_type', '');
        $scope = $request->query->getString('scope', '');
        $state = $request->query->getString('state', '');
        $codeChallenge = $request->query->getString('code_challenge', '');
        $codeChallengeMethod = $request->query->getString('code_challenge_method', 'S256');

        // Validation des parametres
        if ('code' !== $responseType) {
            return new JsonResponse(['error' => 'unsupported_response_type'], Response::HTTP_BAD_REQUEST);
        }

        $client = $this->oauthService->findClient($clientId);
        if (null === $client) {
            return new JsonResponse(['error' => 'invalid_client'], Response::HTTP_BAD_REQUEST);
        }

        if ('' !== $redirectUri && !$client->isRedirectUriAllowed($redirectUri)) {
            return new JsonResponse(['error' => 'invalid_redirect_uri'], Response::HTTP_BAD_REQUEST);
        }

        // Verifier que l'utilisateur est connecte
        $user = $this->getUser();
        if (!$user instanceof User) {
            // Stocker l'URL OAuth complete pour y revenir apres le login
            $request->getSession()->set('_security.main.target_path', $request->getUri());

            return $this->redirectToRoute('app_login');
        }

        // POST = l'utilisateur a clique "Autoriser"
        if ($request->isMethod('POST')) {
            $decision = $request->request->getString('decision', '');

            if ('deny' === $decision) {
                return $this->redirect($redirectUri . '?error=access_denied&state=' . urlencode($state));
            }

            // Creer le code d'autorisation
            $authCode = $this->oauthService->createAuthorizationCode(
                $client,
                $user,
                $redirectUri,
                $scope,
                '' !== $codeChallenge ? $codeChallenge : null,
                '' !== $codeChallenge ? $codeChallengeMethod : null,
            );

            $separator = str_contains($redirectUri, '?') ? '&' : '?';
            $callbackUrl = $redirectUri . $separator . http_build_query([
                'code' => $authCode->getCode(),
                'state' => $state,
            ]);

            return $this->redirect($callbackUrl);
        }

        // GET = afficher la page de consentement
        return $this->render('oauth/authorize.html.twig', [
            'client' => $client,
            'scope' => $scope,
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => $codeChallengeMethod,
            'query_string' => $request->getQueryString(),
        ]);
    }

    /**
     * Endpoint d'echange de token OAuth 2.1.
     * Supporte : authorization_code (avec PKCE) et refresh_token.
     */
    #[Route('/oauth/token', name: 'oauth_token', methods: ['POST'])]
    public function token(Request $request): JsonResponse
    {
        $grantType = $request->request->getString('grant_type', '');

        if ('authorization_code' === $grantType) {
            return $this->handleAuthorizationCodeGrant($request);
        }

        if ('refresh_token' === $grantType) {
            return $this->handleRefreshTokenGrant($request);
        }

        return new JsonResponse([
            'error' => 'unsupported_grant_type',
        ], Response::HTTP_BAD_REQUEST);
    }

    /**
     * Revocation de token (RFC 7009).
     */
    #[Route('/oauth/revoke', name: 'oauth_revoke', methods: ['POST'])]
    public function revoke(Request $request): JsonResponse
    {
        $token = $request->request->getString('token', '');

        if ('' === $token) {
            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST);
        }

        $accessToken = $this->oauthService->findValidAccessToken($token);
        if (null !== $accessToken) {
            $this->oauthService->revokeAccessToken($accessToken);
        }

        // RFC 7009 : toujours retourner 200 meme si le token n'existe pas
        return new JsonResponse(null, Response::HTTP_OK);
    }

    /**
     * Gere le grant_type=authorization_code.
     */
    private function handleAuthorizationCodeGrant(Request $request): JsonResponse
    {
        $code = $request->request->getString('code', '');
        $clientId = $request->request->getString('client_id', '');
        $redirectUri = $request->request->getString('redirect_uri', '');
        $codeVerifier = $request->request->getString('code_verifier', '');

        if ('' === $code || '' === $clientId || '' === $redirectUri) {
            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->oauthService->exchangeAuthorizationCode(
            $code,
            $clientId,
            $redirectUri,
            '' !== $codeVerifier ? $codeVerifier : null,
        );

        if (null === $result) {
            return new JsonResponse(['error' => 'invalid_grant'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result);
    }

    /**
     * Gere le grant_type=refresh_token.
     */
    private function handleRefreshTokenGrant(Request $request): JsonResponse
    {
        $refreshToken = $request->request->getString('refresh_token', '');

        if ('' === $refreshToken) {
            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST);
        }

        $result = $this->oauthService->refreshAccessToken($refreshToken);

        if (null === $result) {
            return new JsonResponse(['error' => 'invalid_grant'], Response::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($result);
    }
}
