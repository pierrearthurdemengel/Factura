<?php

namespace App\Service\OAuth;

use App\Entity\OAuthAccessToken;
use App\Entity\OAuthAuthorizationCode;
use App\Entity\OAuthClient;
use App\Entity\OAuthRefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service central du serveur OAuth 2.1.
 * Gere la creation des codes, tokens et la verification PKCE.
 */
class OAuthService
{
    /** @var list<string> Scopes valides pour l'application */
    private const VALID_SCOPES = [
        'invoices:read',
        'invoices:write',
        'clients:read',
        'clients:write',
        'company:read',
        'stats:read',
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Trouve un client OAuth par son identifiant public.
     */
    public function findClient(string $clientId): ?OAuthClient
    {
        return $this->em->getRepository(OAuthClient::class)->findOneBy([
            'clientId' => $clientId,
        ]);
    }

    /**
     * Cree un code d'autorisation apres le consentement de l'utilisateur.
     */
    public function createAuthorizationCode(
        OAuthClient $client,
        User $user,
        string $redirectUri,
        string $scope,
        ?string $codeChallenge = null,
        ?string $codeChallengeMethod = null,
    ): OAuthAuthorizationCode {
        $authCode = new OAuthAuthorizationCode();
        $authCode->setCode(bin2hex(random_bytes(32)));
        $authCode->setClient($client);
        $authCode->setUser($user);
        $authCode->setRedirectUri($redirectUri);
        $authCode->setScopes($this->filterScopes($scope));
        $authCode->setCodeChallenge($codeChallenge);
        $authCode->setCodeChallengeMethod($codeChallengeMethod);

        $this->em->persist($authCode);
        $this->em->flush();

        return $authCode;
    }

    /**
     * Echange un code d'autorisation contre un access token + refresh token.
     * Verifie le PKCE si le code_challenge etait present.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}|null
     */
    public function exchangeAuthorizationCode(
        string $code,
        string $clientId,
        string $redirectUri,
        ?string $codeVerifier = null,
    ): ?array {
        $authCode = $this->em->getRepository(OAuthAuthorizationCode::class)->findOneBy([
            'code' => $code,
        ]);

        if (null === $authCode
            || $authCode->isExpired()
            || $authCode->isUsed()
            || $authCode->getClient()->getClientId() !== $clientId
            || $authCode->getRedirectUri() !== $redirectUri
            || (null !== $authCode->getCodeChallenge() && (null === $codeVerifier || !$authCode->verifyCodeChallenge($codeVerifier)))
        ) {
            return null;
        }

        // Marquer le code comme utilise (usage unique)
        $authCode->markUsed();

        // Creer l'access token (1 heure)
        $accessToken = new OAuthAccessToken();
        $accessToken->setToken('mfp_' . bin2hex(random_bytes(32)));
        $accessToken->setClient($authCode->getClient());
        $accessToken->setUser($authCode->getUser());
        $accessToken->setScopes($authCode->getScopes());
        $accessToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        // Creer le refresh token (30 jours)
        $refreshToken = new OAuthRefreshToken();
        $refreshToken->setToken('mfp_rt_' . bin2hex(random_bytes(32)));
        $refreshToken->setAccessToken($accessToken);

        $this->em->persist($accessToken);
        $this->em->persist($refreshToken);
        $this->em->flush();

        return [
            'access_token' => $accessToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $refreshToken->getToken(),
            'scope' => implode(' ', $accessToken->getScopes()),
        ];
    }

    /**
     * Renouvelle un access token a partir d'un refresh token.
     *
     * @return array{access_token: string, token_type: string, expires_in: int, refresh_token: string, scope: string}|null
     */
    public function refreshAccessToken(string $refreshTokenValue): ?array
    {
        $refreshToken = $this->em->getRepository(OAuthRefreshToken::class)->findOneBy([
            'token' => $refreshTokenValue,
        ]);

        if (null === $refreshToken || !$refreshToken->isValid()) {
            return null;
        }

        $oldAccessToken = $refreshToken->getAccessToken();

        // Revoquer l'ancien access token et refresh token
        $oldAccessToken->revoke();
        $refreshToken->revoke();

        // Creer un nouvel access token
        $newAccessToken = new OAuthAccessToken();
        $newAccessToken->setToken('mfp_' . bin2hex(random_bytes(32)));
        $newAccessToken->setClient($oldAccessToken->getClient());
        $newAccessToken->setUser($oldAccessToken->getUser());
        $newAccessToken->setScopes($oldAccessToken->getScopes());
        $newAccessToken->setExpiresAt(new \DateTimeImmutable('+1 hour'));

        // Creer un nouveau refresh token
        $newRefreshToken = new OAuthRefreshToken();
        $newRefreshToken->setToken('mfp_rt_' . bin2hex(random_bytes(32)));
        $newRefreshToken->setAccessToken($newAccessToken);

        $this->em->persist($newAccessToken);
        $this->em->persist($newRefreshToken);
        $this->em->flush();

        return [
            'access_token' => $newAccessToken->getToken(),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'refresh_token' => $newRefreshToken->getToken(),
            'scope' => implode(' ', $newAccessToken->getScopes()),
        ];
    }

    /**
     * Trouve un access token valide par sa valeur.
     */
    public function findValidAccessToken(string $token): ?OAuthAccessToken
    {
        $accessToken = $this->em->getRepository(OAuthAccessToken::class)->findOneBy([
            'token' => $token,
        ]);

        if (null === $accessToken || !$accessToken->isValid()) {
            return null;
        }

        // Mettre a jour la date de derniere utilisation
        $accessToken->markUsed();
        $this->em->flush();

        return $accessToken;
    }

    /**
     * Revoque un access token et ses refresh tokens associes.
     */
    public function revokeAccessToken(OAuthAccessToken $accessToken): void
    {
        $accessToken->revoke();

        // Revoquer tous les refresh tokens associes
        $refreshTokens = $this->em->getRepository(OAuthRefreshToken::class)->findBy([
            'accessToken' => $accessToken,
        ]);

        foreach ($refreshTokens as $rt) {
            $rt->revoke();
        }

        $this->em->flush();
    }

    /**
     * Liste les integrations actives (tokens non revoques, non expires) d'un utilisateur.
     *
     * @return list<OAuthAccessToken>
     */
    public function getActiveIntegrations(User $user): array
    {
        $tokens = $this->em->getRepository(OAuthAccessToken::class)->findBy([
            'user' => $user,
        ]);

        // Garder uniquement le token le plus recent par client
        $byClient = [];
        foreach ($tokens as $token) {
            if ($token->isRevoked()) {
                continue;
            }
            $clientId = $token->getClient()->getClientId();
            if (!isset($byClient[$clientId]) || $token->getCreatedAt() > $byClient[$clientId]->getCreatedAt()) {
                $byClient[$clientId] = $token;
            }
        }

        return array_values($byClient);
    }

    /**
     * Filtre les scopes demandes pour ne garder que les scopes valides.
     *
     * @return list<string>
     */
    private function filterScopes(string $scope): array
    {
        $requested = explode(' ', $scope);
        $filtered = array_values(array_intersect($requested, self::VALID_SCOPES));

        // Si aucun scope valide, accorder tous les scopes par defaut
        return [] === $filtered ? self::VALID_SCOPES : $filtered;
    }
}
