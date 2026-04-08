<?php

namespace App\Service\Assistant;

use App\Entity\AssistantCache;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service de cache PostgreSQL pour l'assistant comptable.
 *
 * Les questions fiscales de base (~60% du volume) ne changent qu'une fois par an.
 * Le cache stocke les reponses indexees par le hash de la question normalisee
 * avec un TTL de 30 jours, ce qui reduit les appels LLM de ~60%.
 */
class AssistantCacheService
{
    // TTL par defaut en jours
    public const DEFAULT_TTL_DAYS = 30;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FiscalKnowledgeBase $knowledgeBase,
    ) {
    }

    /**
     * Cherche une reponse en cache pour une question donnee.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $question): ?array
    {
        $normalized = $this->knowledgeBase->normalizeQuestion($question);
        $hash = $this->knowledgeBase->hashQuestion($normalized);

        $cache = $this->em->getRepository(AssistantCache::class)->findOneBy([
            'questionHash' => $hash,
        ]);

        if (null === $cache) {
            return null;
        }

        // Verifier l'expiration
        if ($cache->isExpired()) {
            $this->em->remove($cache);
            $this->em->flush();

            return null;
        }

        // Incrementer le compteur de hits
        $cache->incrementHitCount();
        $this->em->flush();

        return $cache->getResponse();
    }

    /**
     * Stocke une reponse en cache.
     *
     * @param array<string, mixed> $response
     */
    public function put(string $question, array $response, string $category, int $ttlDays = self::DEFAULT_TTL_DAYS): void
    {
        $normalized = $this->knowledgeBase->normalizeQuestion($question);
        $hash = $this->knowledgeBase->hashQuestion($normalized);

        // Verifier si une entree existe deja (mise a jour)
        $existing = $this->em->getRepository(AssistantCache::class)->findOneBy([
            'questionHash' => $hash,
        ]);

        if (null !== $existing) {
            $existing->setResponse($response);
            $existing->setExpiresAt(new \DateTimeImmutable(sprintf('+%d days', $ttlDays)));
            $this->em->flush();

            return;
        }

        $cache = new AssistantCache();
        $cache->setQuestionHash($hash);
        $cache->setNormalizedQuestion($normalized);
        $cache->setResponse($response);
        $cache->setCategory($category);
        $cache->setExpiresAt(new \DateTimeImmutable(sprintf('+%d days', $ttlDays)));

        $this->em->persist($cache);
        $this->em->flush();
    }

    /**
     * Supprime les entrees de cache expirees.
     *
     * @return int Nombre d'entrees supprimees
     */
    public function purgeExpired(): int
    {
        $qb = $this->em->createQueryBuilder();

        return $qb->delete(AssistantCache::class, 'c')
            ->where('c.expiresAt < :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Invalide tout le cache d'une categorie donnee.
     * Utile lors de la mise a jour annuelle des baremes.
     *
     * @return int Nombre d'entrees supprimees
     */
    public function invalidateCategory(string $category): int
    {
        $qb = $this->em->createQueryBuilder();

        return $qb->delete(AssistantCache::class, 'c')
            ->where('c.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->execute();
    }
}
