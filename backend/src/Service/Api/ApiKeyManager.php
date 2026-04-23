<?php

namespace App\Service\Api;

use App\Entity\ApiKey;
use App\Entity\Company;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Gestionnaire de cles d'API.
 *
 * Genere, valide et revoque les cles d'acces programmatique.
 * Le format est : mfp_live_{32 caracteres aleatoires}.
 * Seul le hash SHA-256 est stocke en base, pas la cle en clair.
 */
class ApiKeyManager
{
    // Prefixe des cles d'API
    private const KEY_PREFIX = 'mfp_live_';
    private const KEY_LENGTH = 32;

    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Genere une nouvelle cle d'API.
     *
     * @param list<string> $scopes
     *
     * @return array{apiKey: ApiKey, plainKey: string}
     */
    public function generate(
        User $user,
        Company $company,
        string $name,
        string $plan = ApiKey::PLAN_FREE,
        array $scopes = [],
    ): array {
        $randomPart = bin2hex(random_bytes(self::KEY_LENGTH / 2));
        $plainKey = self::KEY_PREFIX . $randomPart;

        $apiKey = new ApiKey();
        $apiKey->setUser($user);
        $apiKey->setCompany($company);
        $apiKey->setName($name);
        $apiKey->setKeyHash(hash('sha256', $plainKey));
        $apiKey->setKeyPrefix(substr($plainKey, 0, 15));
        $apiKey->setPlan($plan);
        $apiKey->setScopes($scopes);

        $this->em->persist($apiKey);
        $this->em->flush();

        return ['apiKey' => $apiKey, 'plainKey' => $plainKey];
    }

    /**
     * Valide une cle d'API et retourne l'entite associee.
     */
    public function validate(string $plainKey): ?ApiKey
    {
        $hash = hash('sha256', $plainKey);

        $apiKey = $this->em->getRepository(ApiKey::class)->findOneBy([
            'keyHash' => $hash,
        ]);

        if (null === $apiKey || !$apiKey->isActive() || $apiKey->isExpired()) {
            return null;
        }

        $apiKey->incrementRequestCount();
        $this->em->flush();

        return $apiKey;
    }

    /**
     * Revoque une cle d'API.
     */
    public function revoke(ApiKey $apiKey): void
    {
        $apiKey->setActive(false);
        $this->em->flush();
    }

    /**
     * Liste les cles d'API d'un utilisateur pour une entreprise.
     *
     * @return ApiKey[]
     */
    public function listKeys(User $user, Company $company): array
    {
        return $this->em->getRepository(ApiKey::class)->findBy([
            'user' => $user,
            'company' => $company,
        ]);
    }
}
