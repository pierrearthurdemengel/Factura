<?php

namespace App\Service\Auth;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Gere le cycle de vie des refresh tokens.
 *
 * Les tokens sont generes avec un CSPRNG, hashes en SHA-256 avant
 * persistance. La rotation est appliquee a chaque renouvellement :
 * l'ancien token est revoque et un nouveau est emis.
 */
class AuthTokenService
{
    /** Duree de validite d'un refresh token en jours */
    private const REFRESH_TOKEN_LIFETIME_DAYS = 30;

    /** Longueur du token brut en octets (64 caracteres hex) */
    private const TOKEN_BYTES = 32;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere un nouveau refresh token pour l'utilisateur.
     *
     * @return string Le token en clair (a transmettre au client une seule fois)
     */
    public function createRefreshToken(User $user, Request $request): string
    {
        $rawToken = bin2hex(random_bytes(self::TOKEN_BYTES));

        $refreshToken = new RefreshToken();
        $refreshToken->setUser($user);
        $refreshToken->setTokenHash($this->hashToken($rawToken));
        $refreshToken->setExpiresAt(
            new \DateTimeImmutable(sprintf('+%d days', self::REFRESH_TOKEN_LIFETIME_DAYS)),
        );
        $refreshToken->setDeviceInfo($this->extractDeviceInfo($request));

        $this->em->persist($refreshToken);
        $this->em->flush();

        return $rawToken;
    }

    /**
     * Valide un refresh token et applique la rotation.
     *
     * Retourne un nouveau token en clair si le token soumis est valide,
     * null sinon. L'ancien token est revoque dans tous les cas.
     *
     * @return array{user: User, newToken: string}|null
     */
    public function rotateRefreshToken(string $rawToken, Request $request): ?array
    {
        $hash = $this->hashToken($rawToken);

        $existing = $this->em->getRepository(RefreshToken::class)->findOneBy([
            'tokenHash' => $hash,
        ]);

        if (null === $existing || !$existing->isValid()) {
            // Si le token existe mais est revoque, c'est potentiellement
            // une tentative de reutilisation → revoquer toute la famille
            if (null !== $existing && $existing->isRevoked()) {
                $this->revokeAllUserTokens($existing->getUser());
            }

            return null;
        }

        // Revoquer l'ancien token (rotation)
        $existing->revoke();

        // Creer le nouveau token
        $user = $existing->getUser();
        $newRawToken = $this->createRefreshToken($user, $request);

        return [
            'user' => $user,
            'newToken' => $newRawToken,
        ];
    }

    /**
     * Revoque un refresh token specifique (logout).
     */
    public function revokeToken(string $rawToken): void
    {
        $hash = $this->hashToken($rawToken);

        $token = $this->em->getRepository(RefreshToken::class)->findOneBy([
            'tokenHash' => $hash,
        ]);

        if (null !== $token && !$token->isRevoked()) {
            $token->revoke();
            $this->em->flush();
        }
    }

    /**
     * Revoque tous les refresh tokens d'un utilisateur.
     *
     * Utilise lors d'un changement de mot de passe, d'une compromission
     * suspectee ou d'une deconnexion globale.
     */
    public function revokeAllUserTokens(User $user): void
    {
        $tokens = $this->em->getRepository(RefreshToken::class)->findBy([
            'user' => $user,
        ]);

        foreach ($tokens as $token) {
            if (!$token->isRevoked()) {
                $token->revoke();
            }
        }

        $this->em->flush();
    }

    /**
     * Hash un token brut avec SHA-256.
     *
     * SHA-256 est suffisant pour des tokens aleatoires de haute entropie
     * (256 bits). Pas besoin de bcrypt/argon2 car le token n'est pas
     * un mot de passe choisi par l'utilisateur.
     */
    private function hashToken(string $rawToken): string
    {
        return hash('sha256', $rawToken);
    }

    /**
     * Extrait les informations du client pour l'audit.
     * Tronque le User-Agent pour eviter le stockage de donnees excessives.
     */
    private function extractDeviceInfo(Request $request): string
    {
        $ua = $request->headers->get('User-Agent', 'inconnu');
        $ip = $request->getClientIp() ?? 'inconnu';

        // Masquer le dernier octet de l'IP pour la vie privee
        $maskedIp = preg_replace('/\.\d+$/', '.xxx', $ip) ?? $ip;

        return sprintf('%s | %s', $maskedIp, mb_substr($ua, 0, 200));
    }
}
