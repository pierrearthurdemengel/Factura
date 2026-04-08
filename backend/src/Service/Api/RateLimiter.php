<?php

namespace App\Service\Api;

use App\Entity\ApiKey;

/**
 * Rate limiter par cle d'API.
 *
 * Limite le nombre de requetes par heure selon le plan tarifaire :
 * - Free : 100 req/h
 * - Pro : 1000 req/h
 * - Equipe : 10 000 req/h
 *
 * Utilise un compteur en memoire avec fenetre glissante.
 * En production, le compteur serait dans Redis ou en base.
 */
class RateLimiter
{
    /**
     * Compteurs en memoire : cle = keyHash, valeur = [timestamp, count].
     *
     * @var array<string, array{timestamp: int, count: int}>
     */
    private array $counters = [];

    /**
     * Verifie si une requete est autorisee pour cette cle d'API.
     *
     * @return array{allowed: bool, limit: int, remaining: int, resetAt: int}
     */
    public function check(ApiKey $apiKey): array
    {
        $keyHash = $apiKey->getKeyHash();
        $limit = $apiKey->getRateLimit();
        $now = time();
        $windowStart = $now - 3600; // Fenetre d'1 heure

        // Reinitialiser si la fenetre est expiree
        if (!isset($this->counters[$keyHash]) || $this->counters[$keyHash]['timestamp'] < $windowStart) {
            $this->counters[$keyHash] = ['timestamp' => $now, 'count' => 0];
        }

        $currentCount = $this->counters[$keyHash]['count'];
        $remaining = max(0, $limit - $currentCount);
        $resetAt = $this->counters[$keyHash]['timestamp'] + 3600;

        if ($currentCount >= $limit) {
            return [
                'allowed' => false,
                'limit' => $limit,
                'remaining' => 0,
                'resetAt' => $resetAt,
            ];
        }

        // Incrementer le compteur
        ++$this->counters[$keyHash]['count'];

        return [
            'allowed' => true,
            'limit' => $limit,
            'remaining' => $remaining - 1,
            'resetAt' => $resetAt,
        ];
    }

    /**
     * Retourne les informations de rate limit pour une cle sans incrementer.
     *
     * @return array{limit: int, remaining: int, resetAt: int}
     */
    public function getStatus(ApiKey $apiKey): array
    {
        $keyHash = $apiKey->getKeyHash();
        $limit = $apiKey->getRateLimit();
        $now = time();
        $windowStart = $now - 3600;

        if (!isset($this->counters[$keyHash]) || $this->counters[$keyHash]['timestamp'] < $windowStart) {
            return [
                'limit' => $limit,
                'remaining' => $limit,
                'resetAt' => $now + 3600,
            ];
        }

        $currentCount = $this->counters[$keyHash]['count'];
        $resetAt = $this->counters[$keyHash]['timestamp'] + 3600;

        return [
            'limit' => $limit,
            'remaining' => max(0, $limit - $currentCount),
            'resetAt' => $resetAt,
        ];
    }

    /**
     * Reinitialise le compteur d'une cle (pour les tests).
     */
    public function reset(ApiKey $apiKey): void
    {
        unset($this->counters[$apiKey->getKeyHash()]);
    }
}
