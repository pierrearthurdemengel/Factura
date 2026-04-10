<?php

namespace App\Security;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * Limiteur de tentatives de connexion par IP.
 *
 * Bloque les tentatives apres un nombre maximal d'echecs dans une fenetre
 * de temps donnee. Previent les attaques par force brute sans necessiter
 * Redis ou une dependance externe.
 */
class LoginThrottler
{
    /** Nombre maximal de tentatives par fenetre */
    private const MAX_ATTEMPTS = 5;

    /** Duree de la fenetre en secondes (15 minutes) */
    private const WINDOW_SECONDS = 900;

    private readonly CacheItemPoolInterface $cache;

    public function __construct(
        private readonly LoggerInterface $logger,
    ) {
        $this->cache = new FilesystemAdapter('login_throttle', self::WINDOW_SECONDS);
    }

    /**
     * Verifie si une tentative de connexion est autorisee pour cette IP.
     */
    public function isBlocked(string $ip): bool
    {
        $key = $this->getCacheKey($ip);

        try {
            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                return false;
            }

            /** @var int $attempts */
            $attempts = $item->get();

            return $attempts >= self::MAX_ATTEMPTS;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Enregistre une tentative echouee pour une IP.
     */
    public function recordFailedAttempt(string $ip): void
    {
        $key = $this->getCacheKey($ip);

        try {
            $item = $this->cache->getItem($key);

            /** @var int $current */
            $current = $item->isHit() ? $item->get() : 0;
            $attempts = $current + 1;

            $item->set($attempts);
            $item->expiresAfter(self::WINDOW_SECONDS);
            $this->cache->save($item);

            if ($attempts >= self::MAX_ATTEMPTS) {
                $this->logger->warning('Tentatives de connexion excessives', [
                    'ip' => $ip,
                    'attempts' => $attempts,
                ]);
            }
        } catch (\Throwable) {
            // Ne pas bloquer le flux en cas d'erreur de cache
        }
    }

    /**
     * Reinitialise le compteur apres une connexion reussie.
     */
    public function resetAttempts(string $ip): void
    {
        try {
            $this->cache->deleteItem($this->getCacheKey($ip));
        } catch (\Throwable) {
            // Silencieux
        }
    }

    private function getCacheKey(string $ip): string
    {
        return 'login_attempts_' . md5($ip);
    }
}
