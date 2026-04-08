<?php

namespace App\Tests\Unit\Service\Assistant;

use App\Entity\AssistantCache;
use App\Service\Assistant\AssistantCacheService;
use App\Service\Assistant\FiscalKnowledgeBase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

class AssistantCacheServiceTest extends TestCase
{
    private FiscalKnowledgeBase $kb;

    protected function setUp(): void
    {
        $this->kb = new FiscalKnowledgeBase();
    }

    /**
     * Cree un service de cache avec un mock du repository.
     */
    private function createService(?AssistantCache $cacheEntry = null): AssistantCacheService
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($cacheEntry);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        return new AssistantCacheService($em, $this->kb);
    }

    public function testGetReturnsCachedResponse(): void
    {
        $cache = new AssistantCache();
        $cache->setQuestionHash($this->kb->hashQuestion($this->kb->normalizeQuestion('taux tva')));
        $cache->setNormalizedQuestion('taux tva');
        $cache->setResponse(['answer' => 'Le taux normal est de 20%.', 'references' => [], 'actions' => []]);
        $cache->setCategory(FiscalKnowledgeBase::CATEGORY_TVA);
        $cache->setExpiresAt(new \DateTimeImmutable('+30 days'));

        $service = $this->createService($cache);
        $result = $service->get('taux tva');

        self::assertNotNull($result);
        self::assertSame('Le taux normal est de 20%.', $result['answer']);
    }

    public function testGetReturnsNullForMissingEntry(): void
    {
        $service = $this->createService(null);
        $result = $service->get('question inconnue');

        self::assertNull($result);
    }

    public function testGetReturnsNullForExpiredEntry(): void
    {
        $cache = new AssistantCache();
        $cache->setQuestionHash('test');
        $cache->setNormalizedQuestion('test');
        $cache->setResponse(['answer' => 'Expiree']);
        $cache->setCategory('general');
        $cache->setExpiresAt(new \DateTimeImmutable('-1 day'));

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($cache);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::once())->method('remove')->with($cache);
        $em->expects(self::once())->method('flush');

        $service = new AssistantCacheService($em, $this->kb);
        $result = $service->get('test');

        self::assertNull($result);
    }

    public function testGetIncrementsHitCount(): void
    {
        $cache = new AssistantCache();
        $cache->setQuestionHash('test');
        $cache->setNormalizedQuestion('test');
        $cache->setResponse(['answer' => 'Valide']);
        $cache->setCategory('general');
        $cache->setExpiresAt(new \DateTimeImmutable('+30 days'));

        self::assertSame(0, $cache->getHitCount());

        $service = $this->createService($cache);
        $service->get('test');

        self::assertSame(1, $cache->getHitCount());
    }

    public function testPutCreatesNewEntry(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn(null);
        $em->method('getRepository')->willReturn($repo);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new AssistantCacheService($em, $this->kb);
        $service->put('taux tva', ['answer' => 'Test'], FiscalKnowledgeBase::CATEGORY_TVA);

        // Le test passe si persist et flush sont appeles
    }

    public function testPutUpdatesExistingEntry(): void
    {
        $existing = new AssistantCache();
        $existing->setQuestionHash('test');
        $existing->setNormalizedQuestion('test');
        $existing->setResponse(['answer' => 'Ancienne reponse']);
        $existing->setCategory('general');

        $em = $this->createMock(EntityManagerInterface::class);
        $repo = $this->createMock(EntityRepository::class);
        $repo->method('findOneBy')->willReturn($existing);
        $em->method('getRepository')->willReturn($repo);
        // Pas d'appel a persist (mise a jour)
        $em->expects(self::never())->method('persist');
        $em->expects(self::once())->method('flush');

        $service = new AssistantCacheService($em, $this->kb);
        $service->put('test', ['answer' => 'Nouvelle reponse'], 'general');

        self::assertSame(['answer' => 'Nouvelle reponse'], $existing->getResponse());
    }

    public function testDefaultTtlIs30Days(): void
    {
        self::assertSame(30, AssistantCacheService::DEFAULT_TTL_DAYS);
    }
}
